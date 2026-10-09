<?php
// Mailketing adapter ringan yang mengikuti pola battle-tested Averion:
// token raw hanya dibaca dari settings, request fail-soft, retry transient,
// dan subscriber masuk antrean agar request publik tidak menunggu API provider.

// Worker memakai scribeSplitName() (dan email-sequence memakai
// scribeSubscriberUnsubscribeUrl()) dari subscribers.php. Pastikan termuat agar
// pemrosesan antrean dari konteks mana pun (home/admin/kick/tombol) tak fatal.
require_once __DIR__ . '/subscribers.php';

const SCRIBE_EMAIL_MAX_ATTEMPTS = 5;   // retry terbatas per baris antrean
const SCRIBE_EMAIL_RUN_BUDGET   = 20;  // detik maksimal pemrosesan per run (anti timeout eksekusi)
const SCRIBE_EMAIL_CALL_TIMEOUT = 20;  // detik timeout per panggilan provider (cukup utk respons lambat)

/**
 * Status akhir baris antrean dari respons provider + jumlah percobaan.
 * - ok → 'sent'.
 * - TIMEOUT (tanpa respons HTTP): AMBIGU — email MUNGKIN sudah terkirim di sisi
 *   provider meski klien tidak menerima respons → 'failed' TANPA retry, agar
 *   tidak mengirim ganda. (Admin bisa requeue manual bila yakin belum terkirim.)
 * - error lain (koneksi/DNS/HTTP dari server) → aman diulang sampai batas.
 */
function scribeQueueResultStatus(array $res, int $attempts): string
{
    if (!empty($res['ok'])) return 'sent';
    $http = (int) ($res['http'] ?? 0);
    $msg  = mb_strtolower((string) ($res['message'] ?? ''));
    if ($http === 0 && (str_contains($msg, 'timed out') || str_contains($msg, 'timeout') || str_contains($msg, 'time out'))) {
        return 'failed'; // timeout → mungkin sudah terkirim → jangan diulang (cegah dobel)
    }
    return $attempts >= SCRIBE_EMAIL_MAX_ATTEMPTS ? 'failed' : 'pending';
}

/** Kunci cron (auto-generate sekali, disimpan di settings). Untuk endpoint api/cron.php. */
function scribeCronKey(): string
{
    $k = trim((string) getSetting('cron_key', ''));
    if ($k === '') { $k = bin2hex(random_bytes(20)); setSetting('cron_key', $k, 'system'); }
    return $k;
}

/** Verifikasi kunci cron (timing-safe). */
function scribeCronVerify(string $key): bool
{
    $stored = trim((string) getSetting('cron_key', ''));
    return $stored !== '' && $key !== '' && hash_equals($stored, trim($key));
}

/** URL cron penuh untuk ditempel ke penjadwal (cPanel/crontab). */
function scribeCronUrl(): string
{
    return rtrim(APP_URL, '/') . '/api/cron.php?key=' . rawurlencode(scribeCronKey());
}

/** Log worker email (terlihat admin) → cache/logs/email-worker.log. Fail-soft. */
function scribeEmailWorkerLog(string $line): void
{
    $dir = dirname(__DIR__) . '/cache/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) return;
    @file_put_contents($dir . '/email-worker.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

function scribeMailketingToken(): string
{
    return trim((string) (getSetting('mailketing_api_token', '') ?: getSetting('mailketing_api_key', '')));
}

function scribeMailketingEndpoint(): string
{
    return trim((string) getSetting('mailketing_endpoint', 'https://api.mailketing.co.id/api/v1/send'));
}

function scribeMailketingPost(string $url, array $params, int $timeout = 20): array
{
    if (!function_exists('curl_init')) return ['ok' => false, 'http' => 0, 'body' => '', 'data' => null, 'message' => 'Ekstensi cURL tidak aktif.'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(5, $timeout),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => 'Averion-Scribe/1.0',
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $body = $body === false ? '' : (string) $body;
    $data = json_decode($body, true);
    $ok = $body !== '' && $http >= 200 && $http < 300;
    if (is_array($data)) {
        if (array_key_exists('success', $data)) $ok = (bool) $data['success'];
        elseif (isset($data['status'])) $ok = in_array(strtolower((string) $data['status']), ['success', 'ok', '200', 'true'], true);
        if (!empty($data['error']) || !empty($data['errors'])) $ok = false;
    }
    $msg = is_array($data) ? (string) ($data['response'] ?? $data['message'] ?? $data['error'] ?? ($ok ? 'Berhasil' : 'Gagal')) : ($error !== '' ? $error : ($ok ? 'Berhasil' : 'HTTP ' . $http));
    return ['ok' => $ok, 'http' => $http, 'body' => $body, 'data' => $data, 'message' => $msg];
}

function scribeMailketingSend(string $to, string $subject, string $content, array $options = []): array
{
    $token = scribeMailketingToken();
    if ($token === '') return ['ok' => false, 'http' => 0, 'message' => 'Token Mailketing belum diisi.'];
    $fromName = trim((string) (getSetting('mailketing_from_name', '') ?: getSetting('mailketing_sender_name', blogName())));
    $fromEmail = trim((string) (getSetting('mailketing_from_email', '') ?: getSetting('mailketing_sender_email', '')));
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $adminEmail = trim((string) getSetting('admin_email', ''));
        if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $fromEmail = $adminEmail;
    }
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'http' => 0, 'message' => 'Email pengirim Mailketing belum valid.'];
    $endpoint = scribeMailketingEndpoint();
    if ($endpoint === '') return ['ok' => false, 'http' => 0, 'message' => 'Endpoint Mailketing kosong.'];

    $params = ['api_token' => $token, 'from_name' => $fromName, 'from_email' => $fromEmail, 'recipient' => $to, 'subject' => $subject, 'content' => $content];
    $max = max(1, min(3, (int) ($options['max_attempts'] ?? 3)));
    $res = ['ok' => false, 'http' => 0, 'message' => 'Gagal'];
    for ($attempt = 1; $attempt <= $max; $attempt++) {
        $res = scribeMailketingPost($endpoint, $params, (int) ($options['timeout'] ?? 20));
        $transient = !$res['ok'] && ($res['http'] === 0 || $res['http'] === 429 || $res['http'] >= 500);
        if (!$transient || $attempt === $max) break;
        usleep(500000 * $attempt);
    }
    error_log('[Scribe Email] recipient=' . $to . ' status=' . ($res['ok'] ? 'sent' : 'failed') . ' http=' . (int) ($res['http'] ?? 0));
    return $res;
}

function scribeMailketingViewLists(): array
{
    $token = scribeMailketingToken();
    if ($token === '') return ['ok' => false, 'lists' => [], 'message' => 'Token Mailketing belum diisi.'];
    $r = scribeMailketingPost('https://api.mailketing.co.id/api/v1/viewlist', ['api_token' => $token], 15);
    $lists = [];
    if (is_array($r['data'] ?? null) && !empty($r['data']['lists']) && is_array($r['data']['lists'])) {
        foreach ($r['data']['lists'] as $item) {
            if (is_array($item) && isset($item['list_id'])) $lists[] = ['list_id' => (string) $item['list_id'], 'list_name' => (string) ($item['list_name'] ?? $item['list_id'])];
        }
    }
    return ['ok' => (bool) $r['ok'] && $lists !== [], 'lists' => $lists, 'message' => (string) ($r['message'] ?? '')];
}

function scribeMailketingAddToList(string $listId, string $email, array $fields = [], int $timeout = 15): array
{
    $token = scribeMailketingToken();
    if ($token === '' || trim($listId) === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'Token, list, atau email tidak valid.', 'http' => 0];
    [$first, $last] = scribeSplitName((string) ($fields['name'] ?? ''));
    $params = array_filter(['api_token' => $token, 'list_id' => trim($listId), 'email' => trim($email), 'first_name' => $first, 'last_name' => $last], fn($v) => $v !== '');
    $r = scribeMailketingPost('https://api.mailketing.co.id/api/v1/addsubtolist', $params, max(5, $timeout));
    $data = $r['data'] ?? null;
    $ok = is_array($data)
        ? strtolower((string) ($data['status'] ?? '')) === 'success'
        : (bool) $r['ok'];
    $message = is_array($data)
        ? (string) ($data['response'] ?? $data['message'] ?? ($ok ? 'Subscriber ditambahkan.' : 'Gagal menambahkan subscriber.'))
        : (string) ($r['message'] ?? '');
    return ['ok' => $ok, 'message' => $message, 'http' => (int) ($r['http'] ?? 0), 'raw' => (string) ($r['body'] ?? '')];
}

function scribeMailketingEnqueueSubscriber(int $subscriberId, string $listId): void
{
    if ($subscriberId < 1 || trim($listId) === '') return;
    $pdo = getDB();
    $st = $pdo->prepare('SELECT email, name FROM subscribers WHERE id = ? AND unsubscribed_at IS NULL LIMIT 1');
    $st->execute([$subscriberId]);
    $sub = $st->fetch();
    if (!$sub || !filter_var($sub['email'] ?? '', FILTER_VALIDATE_EMAIL)) return;
    $check = $pdo->prepare('SELECT id, status FROM scribe_mailketing_queue WHERE subscriber_id = ? AND list_id = ? LIMIT 1');
    $check->execute([$subscriberId, $listId]);
    if ($check->fetch()) return;
    $pdo->prepare('INSERT INTO scribe_mailketing_queue (subscriber_id, list_id, email, first_name, last_name) VALUES (?, ?, ?, ?, ?)')
        ->execute([$subscriberId, trim($listId), $sub['email'], ...scribeSplitName((string) ($sub['name'] ?? ''))]);
    $pdo->prepare("UPDATE subscribers SET mailketing_list_id = ?, mailketing_status = 'pending', updated_at = NOW() WHERE id = ?")
        ->execute([$listId, $subscriberId]);
}

function scribeProcessMailketingQueue(int $limit = 10): array
{
    $out = ['processed' => 0, 'sent' => 0, 'failed' => 0];
    if (scribeMailketingToken() === '') return $out;
    try {
        $pdo = getDB();
        // Sapu baris yang sudah habis percobaan → tandai failed (terlihat), bukan nyangkut pending.
        $pdo->prepare('UPDATE scribe_mailketing_queue SET status = "failed", response = COALESCE(NULLIF(response, ""), "Gagal setelah percobaan maksimal") WHERE status = "pending" AND attempts >= ?')->execute([SCRIBE_EMAIL_MAX_ATTEMPTS]);
        $sel = $pdo->prepare('SELECT id, subscriber_id, list_id, email, first_name, last_name, attempts FROM scribe_mailketing_queue WHERE status = "pending" AND attempts < ? ORDER BY id ASC LIMIT ' . max(1, min(50, $limit)));
        $sel->execute([SCRIBE_EMAIL_MAX_ATTEMPTS]);
        $rows = $sel->fetchAll();
        if (!$rows) return $out;
        // Klaim atomik (optimistic lock via attempts) + catat waktu percobaan SEBELUM panggil provider.
        $claim = $pdo->prepare('UPDATE scribe_mailketing_queue SET attempts = attempts + 1, last_attempt_at = NOW() WHERE id = ? AND status = "pending" AND attempts = ?');
        $fin = $pdo->prepare('UPDATE scribe_mailketing_queue SET status = ?, sent_at = CASE WHEN ? = "sent" THEN NOW() ELSE sent_at END, response = ? WHERE id = ?');
        $subUp = $pdo->prepare('UPDATE subscribers SET mailketing_status = ?, updated_at = NOW() WHERE id = ?');
        $t0 = microtime(true);
        scribeEmailWorkerLog('list-add RUN mulai kandidat=' . count($rows));
        foreach ($rows as $row) {
            if (microtime(true) - $t0 > SCRIBE_EMAIL_RUN_BUDGET) { scribeEmailWorkerLog('list-add budget habis, lanjut run berikutnya'); break; }
            $claim->execute([(int) $row['id'], (int) $row['attempts']]);
            if ($claim->rowCount() !== 1) continue; // sudah diklaim run lain → cegah dobel
            $out['processed']++;
            $attempts = (int) $row['attempts'] + 1;
            $started = microtime(true);
            try {
                $res = scribeMailketingAddToList((string) $row['list_id'], (string) $row['email'], ['name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))], SCRIBE_EMAIL_CALL_TIMEOUT);
            } catch (Throwable $e) {
                $res = ['ok' => false, 'message' => 'Exception: ' . $e->getMessage(), 'http' => 0];
            }
            $ms = (int) round((microtime(true) - $started) * 1000);
            $status = scribeQueueResultStatus($res, $attempts);
            $fin->execute([$status, $status, mb_substr((string) ($res['message'] ?? ''), 0, 1000), (int) $row['id']]);
            $subUp->execute([$status, (int) $row['subscriber_id']]);
            if ($status === 'sent') $out['sent']++; elseif ($status === 'failed') $out['failed']++;
            scribeEmailWorkerLog('list-add id=' . $row['id'] . ' email=' . $row['email'] . ' attempt=' . $attempts . ' status=' . $status . ' http=' . (int) ($res['http'] ?? 0) . ' ' . $ms . 'ms msg=' . mb_substr((string) ($res['message'] ?? ''), 0, 140));
        }
    } catch (Throwable $e) { error_log('mailketing queue: ' . $e->getMessage()); scribeEmailWorkerLog('list-add ERROR ' . $e->getMessage()); }
    return $out;
}
