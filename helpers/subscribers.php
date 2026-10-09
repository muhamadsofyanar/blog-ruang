<?php
// Subscriber + lead attribution helper. Semua operasi publik fail-soft dan
// hanya menyimpan data yang diperlukan untuk delivery serta audit sumber.

function scribeSubscriberToken(PDO $pdo, int $id): string
{
    $st = $pdo->prepare('SELECT unsubscribe_token FROM subscribers WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $token = trim((string) ($st->fetchColumn() ?: ''));
    if ($token !== '') return $token;

    $token = bin2hex(random_bytes(32));
    $up = $pdo->prepare('UPDATE subscribers SET unsubscribe_token = ?, updated_at = NOW() WHERE id = ? AND (unsubscribe_token IS NULL OR unsubscribe_token = "")');
    $up->execute([$token, $id]);
    if ($up->rowCount() === 0) {
        $st->execute([$id]);
        $token = trim((string) ($st->fetchColumn() ?: $token));
    }
    return $token;
}

function scribeSplitName(string $name): array
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') return ['', ''];
    $parts = explode(' ', $name);
    $first = (string) array_shift($parts);
    return [$first, $parts ? implode(' ', $parts) : ''];
}

/**
 * Upsert subscriber. Return id, created, and unsubscribe token. Email adalah
 * identitas unik; re-submit tidak menggandakan subscriber dan mengaktifkan ulang
 * langganan bila sebelumnya unsubscribe.
 */
function scribeCaptureSubscriber(array $data): array
{
    $pdo = getDB();
    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
    $name = trim((string) ($data['name'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        throw new InvalidArgumentException('Format email tidak valid.');
    }
    if ($name !== '') $name = mb_substr($name, 0, 120);

    $source = mb_substr(trim((string) ($data['source'] ?? '')), 0, 60) ?: 'public_form';
    $articleId = max(0, (int) ($data['source_article_id'] ?? 0));
    $leadMagnetId = max(0, (int) ($data['lead_magnet_id'] ?? 0));
    $sourceUrl = mb_substr(trim((string) ($data['source_url'] ?? '')), 0, 500);
    $listId = mb_substr(trim((string) ($data['mailketing_list_id'] ?? '')), 0, 40);

    $st = $pdo->prepare('SELECT id, unsubscribe_token FROM subscribers WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $existing = $st->fetch();
    $created = false;

    if ($existing) {
        $id = (int) $existing['id'];
        $pdo->prepare(
            'UPDATE subscribers SET name = CASE WHEN ? <> "" THEN ? ELSE name END,
             source = ?, source_article_id = NULLIF(?, 0), source_url = NULLIF(?, ""),
             lead_magnet_id = NULLIF(?, 0), mailketing_list_id = NULLIF(?, ""),
             unsubscribed_at = NULL, updated_at = NOW() WHERE id = ?'
        )->execute([$name, $name, $source, $articleId, $sourceUrl, $leadMagnetId, $listId, $id]);
        $token = trim((string) ($existing['unsubscribe_token'] ?? ''));
    } else {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare(
            'INSERT INTO subscribers (email, name, source, source_article_id, source_url, lead_magnet_id, unsubscribe_token, mailketing_list_id, created_at, updated_at)
             VALUES (?, ?, ?, NULLIF(?, 0), NULLIF(?, ""), NULLIF(?, 0), ?, NULLIF(?, ""), NOW(), NOW())'
        )->execute([$email, ($name !== '' ? $name : null), $source, $articleId, $sourceUrl, $leadMagnetId, $token, $listId]);
        $id = (int) $pdo->lastInsertId();
        $created = true;
    }

    if ($token === '') $token = scribeSubscriberToken($pdo, $id);

    // Antrean dan sequence dipanggil fail-soft agar opt-in tetap tersimpan bila
    // Mailketing sedang tidak tersedia.
    try {
        require_once __DIR__ . '/mailketing.php';
        if ($listId !== '') {
            scribeMailketingEnqueueSubscriber($id, $listId);
        } elseif ($created) {
            // Tidak ada list auto-add yang dikonfigurasi → tandai 'skipped' agar
            // tidak terlihat menggantung 'pending' selamanya (bukan kegagalan).
            $pdo->prepare("UPDATE subscribers SET mailketing_status = 'skipped', updated_at = NOW() WHERE id = ? AND (mailketing_status IS NULL OR mailketing_status = 'pending')")->execute([$id]);
        }
    } catch (Throwable $e) { error_log('subscriber mailketing enqueue: ' . $e->getMessage()); }
    try {
        require_once __DIR__ . '/email-sequence.php';
        if ($created || $leadMagnetId > 0) scribeEnrollSubscriberToSequences($id, $leadMagnetId, 'subscriber:' . $id);
    } catch (Throwable $e) { error_log('subscriber sequence enrollment: ' . $e->getMessage()); }

    // Proses antrean SETELAH respons terkirim → D+0 & list-add jalan saat itu juga.
    if ($created || $leadMagnetId > 0 || $listId !== '') {
        try { require_once __DIR__ . '/email-sequence.php'; scribeKickEmailAutomation(); }
        catch (Throwable $e) { error_log('subscriber kick automation: ' . $e->getMessage()); }
    }

    return ['id' => $id, 'created' => $created, 'email' => $email, 'name' => $name, 'unsubscribe_token' => $token];
}

function scribeSubscriberUnsubscribeUrl(string $token): string
{
    return url('/unsubscribe/' . rawurlencode($token));
}
