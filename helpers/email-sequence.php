<?php
// Email sequence untuk subscriber Scribe. Trigger utama subscriber_created;
// body HTML diteruskan sebagai HTML email dan placeholder dirender server-side.
require_once __DIR__ . '/mailketing.php';
require_once __DIR__ . '/subscribers.php'; // scribeSubscriberUnsubscribeUrl() dipakai di worker

function scribeEmailSequenceRender(string $value, array $data): string
{
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($data) {
        return e((string) ($data[$m[1]] ?? ''));
    }, $value) ?? $value;
}

function scribeEmailSequenceBody(string $body, string $format, string $subject): string
{
    if ($format === 'html') return $body;
    $safe = nl2br(e($body), false);
    $brand = e(blogName());
    return '<!doctype html><html><body style="margin:0;background:#f5f7fb;color:#172033;font-family:Arial,sans-serif"><div style="max-width:620px;margin:32px auto;background:#fff;padding:28px;line-height:1.7"><h1 style="font-size:20px;margin:0 0 18px">' . e($subject) . '</h1><div>' . $safe . '</div><p style="margin-top:28px;font-size:12px;color:#718096">' . $brand . '</p></div></body></html>';
}

function scribeEnrollSubscriberToSequences(int $subscriberId, int $leadMagnetId = 0, string $triggerRef = ''): void
{
    if ($subscriberId < 1) return;
    $pdo = getDB();
    $seq = $pdo->prepare("SELECT id FROM email_sequences WHERE status = 'active' AND trigger_event = 'subscriber_created' AND (lead_magnet_id IS NULL OR lead_magnet_id = ?) ORDER BY id ASC");
    $seq->execute([$leadMagnetId > 0 ? $leadMagnetId : null]);
    $insertEnrollment = $pdo->prepare('INSERT IGNORE INTO email_sequence_enrollments (sequence_id, subscriber_id, trigger_ref) VALUES (?, ?, ?)');
    $findEnrollment = $pdo->prepare('SELECT id FROM email_sequence_enrollments WHERE sequence_id = ? AND subscriber_id = ? LIMIT 1');
    $steps = $pdo->prepare("SELECT id, delay_days FROM email_sequence_steps WHERE sequence_id = ? AND status = 'active' ORDER BY step_number ASC");
    $send = $pdo->prepare('INSERT IGNORE INTO email_sequence_sends (enrollment_id, step_id, scheduled_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))');
    foreach ($seq->fetchAll(PDO::FETCH_COLUMN) as $sequenceId) {
        $insertEnrollment->execute([(int) $sequenceId, $subscriberId, $triggerRef !== '' ? $triggerRef : null]);
        $findEnrollment->execute([(int) $sequenceId, $subscriberId]);
        $enrollmentId = (int) $findEnrollment->fetchColumn();
        if (!$enrollmentId) continue;
        $steps->execute([(int) $sequenceId]);
        foreach ($steps->fetchAll() as $step) $send->execute([$enrollmentId, (int) $step['id'], max(0, (int) $step['delay_days'])]);
    }
}

function scribeProcessDueEmailSequenceSends(int $limit = 10, ?callable $sendFn = null): array
{
    $out = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
    $useDefault = $sendFn === null;              // $sendFn: seam uji (default scribeMailketingSend)
    if ($useDefault && scribeMailketingToken() === '') return $out;
    $send = $sendFn ?? 'scribeMailketingSend';
    try {
        $pdo = getDB();
        // Sapu yang habis percobaan → failed (terlihat), bukan nyangkut pending.
        $pdo->prepare("UPDATE email_sequence_sends SET status = 'failed', response = COALESCE(NULLIF(response, ''), 'Gagal setelah percobaan maksimal') WHERE status = 'pending' AND scheduled_at <= NOW() AND attempts >= ?")->execute([SCRIBE_EMAIL_MAX_ATTEMPTS]);
        $sql = "SELECT ess.id AS send_id, ess.enrollment_id, ess.attempts, est.id AS step_id, est.subject, est.body, est.body_format,
                       es.name AS sequence_name, s.email, s.name AS subscriber_name, s.unsubscribe_token,
                       s.source_article_id, s.lead_magnet_id, a.title AS article_title, a.slug AS article_slug,
                       lm.title AS lead_magnet_title, lm.delivery_url
                FROM email_sequence_sends ess
                JOIN email_sequence_enrollments ese ON ese.id = ess.enrollment_id AND ese.status = 'active'
                JOIN email_sequence_steps est ON est.id = ess.step_id AND est.status = 'active'
                JOIN email_sequences es ON es.id = ese.sequence_id AND es.status = 'active'
                JOIN subscribers s ON s.id = ese.subscriber_id AND s.unsubscribed_at IS NULL
                LEFT JOIN articles a ON a.id = s.source_article_id
                LEFT JOIN lead_magnets lm ON lm.id = s.lead_magnet_id
                WHERE ess.status = 'pending' AND ess.scheduled_at <= NOW() AND ess.attempts < " . SCRIBE_EMAIL_MAX_ATTEMPTS . "
                ORDER BY ess.scheduled_at ASC LIMIT " . max(1, min(50, $limit));
        $rows = $pdo->query($sql)->fetchAll();
        if (!$rows) return $out;
        // Klaim atomik (optimistic lock via attempts) + catat waktu percobaan SEBELUM kirim.
        $claim = $pdo->prepare("UPDATE email_sequence_sends SET attempts = attempts + 1, last_attempt_at = NOW() WHERE id = ? AND status = 'pending' AND attempts = ?");
        $fin = $pdo->prepare('UPDATE email_sequence_sends SET status = ?, sent_at = CASE WHEN ? = "sent" THEN NOW() ELSE sent_at END, response = ? WHERE id = ?');
        $t0 = microtime(true);
        scribeEmailWorkerLog('sequence RUN mulai kandidat=' . count($rows));
        foreach ($rows as $row) {
            if (microtime(true) - $t0 > SCRIBE_EMAIL_RUN_BUDGET) { scribeEmailWorkerLog('sequence budget habis, lanjut run berikutnya'); break; }
            $claim->execute([(int) $row['send_id'], (int) $row['attempts']]);
            if ($claim->rowCount() !== 1) continue; // sudah diklaim run lain → cegah kirim ganda
            $out['processed']++;
            $attempts = (int) $row['attempts'] + 1;
            $data = [
                'name' => (string) ($row['subscriber_name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'blog_name' => blogName(),
                'sequence_name' => (string) ($row['sequence_name'] ?? ''),
                'article_title' => (string) ($row['article_title'] ?? ''),
                'article_url' => !empty($row['article_slug']) ? url('/artikel/' . rawurlencode((string) $row['article_slug'])) : '',
                'lead_magnet_title' => (string) ($row['lead_magnet_title'] ?? ''),
                'lead_magnet_url' => (string) ($row['delivery_url'] ?? ''),
                'unsubscribe_url' => !empty($row['unsubscribe_token']) ? scribeSubscriberUnsubscribeUrl((string) $row['unsubscribe_token']) : '',
            ];
            $subject = trim(str_replace(["\r", "\n"], '', scribeEmailSequenceRender((string) $row['subject'], $data)));
            $body = scribeEmailSequenceRender((string) $row['body'], $data);
            if ($subject === '' || $body === '') { $fin->execute(['skipped', 'skipped', 'Subject/body kosong', (int) $row['send_id']]); $out['skipped']++; scribeEmailWorkerLog('sequence id=' . $row['send_id'] . ' SKIP subject/body kosong'); continue; }
            $unsubscribeUrl = (string) ($data['unsubscribe_url'] ?? '');
            $hasUnsubscribe = str_contains((string) $row['body'], 'unsubscribe_url')
                || ($unsubscribeUrl !== '' && str_contains($body, $unsubscribeUrl));
            if (!$hasUnsubscribe && $unsubscribeUrl !== '') {
                $body .= '<p style="font-size:12px;color:#718096;margin-top:28px"><a href="' . e($unsubscribeUrl) . '">Berhenti berlangganan</a></p>';
            }
            $html = scribeEmailSequenceBody($body, (string) $row['body_format'], $subject);
            $started = microtime(true);
            try {
                $res = $send((string) $row['email'], $subject, $html, ['max_attempts' => 1, 'timeout' => SCRIBE_EMAIL_CALL_TIMEOUT]);
            } catch (Throwable $e) {
                $res = ['ok' => false, 'message' => 'Exception: ' . $e->getMessage(), 'http' => 0];
            }
            $ms = (int) round((microtime(true) - $started) * 1000);
            $status = scribeQueueResultStatus($res, $attempts);
            $fin->execute([$status, $status, mb_substr((string) ($res['message'] ?? ''), 0, 1000), (int) $row['send_id']]);
            if ($status === 'sent') $out['sent']++; elseif ($status === 'failed') $out['failed']++;
            scribeEmailWorkerLog('sequence id=' . $row['send_id'] . ' email=' . $row['email'] . ' attempt=' . $attempts . ' status=' . $status . ' http=' . (int) ($res['http'] ?? 0) . ' ' . $ms . 'ms msg=' . mb_substr((string) ($res['message'] ?? ''), 0, 140));
        }
        $pdo->exec("UPDATE email_sequence_enrollments e SET status = 'completed' WHERE e.status = 'active' AND NOT EXISTS (SELECT 1 FROM email_sequence_sends s WHERE s.enrollment_id = e.id AND s.status = 'pending') AND EXISTS (SELECT 1 FROM email_sequence_sends s2 WHERE s2.enrollment_id = e.id)");
    } catch (Throwable $e) { error_log('email sequence queue: ' . $e->getMessage()); scribeEmailWorkerLog('sequence ERROR ' . $e->getMessage()); }
    return $out;
}

/**
 * Picu pemrosesan antrean email SETELAH respons dikirim ke klien (sekali per
 * request). Dipakai tepat setelah capture subscriber agar email D+0 dan
 * penambahan ke list Mailketing diproses "saat itu juga", tanpa menunggu
 * kunjungan homepage/admin berikutnya. Non-blocking di FPM
 * (fastcgi_finish_request); jika tidak, berjalan saat shutdown. Throttle 60s di
 * scribeMaybeProcessEmailAutomation mencegah stampede & pemrosesan ganda.
 */
function scribeKickEmailAutomation(): void
{
    static $armed = false;
    if ($armed) return;
    $armed = true;
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
        try { scribeMaybeProcessEmailAutomation(); }
        catch (Throwable $e) { error_log('kick email automation: ' . $e->getMessage()); }
    });
}

function scribeMaybeProcessEmailAutomation(): array
{
    $flag = dirname(__DIR__) . '/cache/last-email-automation';
    if (is_file($flag) && (time() - (int) @file_get_contents($flag)) < 60) return ['processed' => 0, 'sent' => 0, 'failed' => 0];
    @file_put_contents($flag, (string) time(), LOCK_EX);
    require_once __DIR__ . '/mailketing.php';
    $mk = scribeProcessMailketingQueue(10);
    $seq = scribeProcessDueEmailSequenceSends(10);
    return ['processed' => (int) ($mk['processed'] ?? 0) + (int) ($seq['processed'] ?? 0), 'sent' => (int) ($mk['sent'] ?? 0) + (int) ($seq['sent'] ?? 0), 'failed' => (int) ($mk['failed'] ?? 0) + (int) ($seq['failed'] ?? 0)];
}
