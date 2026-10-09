<?php
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/lead-magnets');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    flash('error', 'Lead magnet tidak ditemukan.', 'error');
    redirect('/admin/lead-magnets');
}

$pdo = getDB();
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT id, title FROM lead_magnets WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $magnet = $st->fetch();
    if (!$magnet) throw new RuntimeException('Lead magnet tidak ditemukan.');

    $st = $pdo->prepare('SELECT COUNT(*) FROM subscribers WHERE lead_magnet_id = ?');
    $st->execute([$id]);
    $subscriberCount = (int) $st->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*) FROM email_sequences WHERE lead_magnet_id = ?');
    $st->execute([$id]);
    $sequenceCount = (int) $st->fetchColumn();
    if ($subscriberCount > 0 || $sequenceCount > 0) {
        throw new RuntimeException(
            'Lead magnet belum dapat dihapus karena masih dipakai oleh '
            . $subscriberCount . ' subscriber dan ' . $sequenceCount . ' email sequence. '
            . 'Hapus relasi tersebut terlebih dahulu.'
        );
    }

    $pdo->prepare('DELETE FROM lead_magnets WHERE id = ?')->execute([$id]);
    $pdo->commit();

    if ((int) getSetting('lead_magnet_default_id', '0') === $id) {
        setSetting('lead_magnet_default_id', '0', 'lead_magnet');
    }
    require_once __DIR__ . '/../../helpers/page-cache.php';
    pageCacheFlushAll();
    flash('success', 'Lead magnet dihapus.', 'success');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('delete lead magnet: ' . $e->getMessage());
    flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Lead magnet belum dapat dihapus.', 'error');
}
redirect('/admin/lead-magnets');
