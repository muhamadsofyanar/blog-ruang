<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/subscribers.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/subscribers');
}

$pdo = getDB();
$id = (int) ($_POST['id'] ?? 0);
$name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
if ($id < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    flash('error', 'Subscriber atau format email tidak valid.', 'error');
    redirect('/admin/subscribers' . ($id > 0 ? '?id=' . $id : ''));
}

try {
    $st = $pdo->prepare('SELECT id, email FROM subscribers WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $current = $st->fetch();
    if (!$current) throw new RuntimeException('Subscriber tidak ditemukan.');

    $st = $pdo->prepare('SELECT id FROM subscribers WHERE email = ? AND id <> ? LIMIT 1');
    $st->execute([$email, $id]);
    if ($st->fetchColumn()) throw new RuntimeException('Email tersebut sudah dipakai subscriber lain.');

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE subscribers SET name = ?, email = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$name !== '' ? $name : null, $email, $id]);
    $pdo->prepare("UPDATE scribe_mailketing_queue SET email = ?, first_name = ?, last_name = ? WHERE subscriber_id = ? AND status = 'pending'")
        ->execute([$email, ...scribeSplitName($name), $id]);
    $pdo->commit();
    flash('success', 'Subscriber diperbarui.', 'success');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('save subscriber: ' . $e->getMessage());
    flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Subscriber belum dapat diperbarui.', 'error');
}
redirect('/admin/subscribers?id=' . $id);
