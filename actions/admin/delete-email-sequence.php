<?php
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/email-sequences');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    flash('error', 'Sequence tidak ditemukan.', 'error');
    redirect('/admin/email-sequences');
}

$pdo = getDB();
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT id FROM email_sequences WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    if (!$st->fetchColumn()) throw new RuntimeException('Sequence tidak ditemukan.');

    $pdo->prepare('DELETE FROM email_sequence_sends WHERE enrollment_id IN (SELECT id FROM email_sequence_enrollments WHERE sequence_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM email_sequence_enrollments WHERE sequence_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM email_sequence_steps WHERE sequence_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM email_sequences WHERE id = ?')->execute([$id]);
    $pdo->commit();
    flash('success', 'Sequence dihapus.', 'success');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('delete email sequence: ' . $e->getMessage());
    flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Sequence belum dapat dihapus.', 'error');
}
redirect('/admin/email-sequences');
