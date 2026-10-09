<?php
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/subscribers');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    flash('error', 'Subscriber tidak ditemukan.', 'error');
    redirect('/admin/subscribers');
}

$pdo = getDB();
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT id FROM subscribers WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    if (!$st->fetchColumn()) throw new RuntimeException('Subscriber tidak ditemukan.');

    $pdo->prepare('DELETE FROM email_sequence_sends WHERE enrollment_id IN (SELECT id FROM email_sequence_enrollments WHERE subscriber_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM email_sequence_enrollments WHERE subscriber_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM scribe_mailketing_queue WHERE subscriber_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM subscribers WHERE id = ?')->execute([$id]);
    $pdo->commit();
    flash('success', 'Subscriber dihapus dari Scribe. Data di list Mailketing tidak dihapus.', 'success');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('delete subscriber: ' . $e->getMessage());
    flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Subscriber belum dapat dihapus.', 'error');
}
redirect('/admin/subscribers');
