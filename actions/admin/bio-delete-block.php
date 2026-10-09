<?php
// POST /actions/admin/bio-delete-block — hapus satu blok biolink + gambarnya (admin).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/media.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/biolink');
}

$id = (int) ($_POST['block_id'] ?? 0);
if ($id <= 0) { flash('error', 'Blok tidak valid.', 'error'); redirect('/admin/biolink'); }

try {
    $pdo = getDB();
    $st  = $pdo->prepare('SELECT config_json FROM bio_blocks WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    if ($row) {
        $cfg = json_decode((string) $row['config_json'], true) ?: [];
        scribeDeleteBioUpload($cfg['photo'] ?? null); // foto tombol
        scribeDeleteBioUpload($cfg['path'] ?? null);  // gambar
        $pdo->prepare('DELETE FROM bio_blocks WHERE id = ?')->execute([$id]);
        pageCacheFlushAll();
        flash('success', 'Blok dihapus.', 'success');
    } else {
        flash('error', 'Blok tidak ditemukan.', 'error');
    }
} catch (Throwable $e) {
    error_log('bio-delete-block: ' . $e->getMessage());
    flash('error', 'Gagal menghapus blok.', 'error');
}
redirect('/admin/biolink');
