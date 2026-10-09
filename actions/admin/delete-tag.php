<?php
// POST /actions/admin/delete-tag — hapus tag (admin). Relasi article_tags ikut
// terhapus via FK ON DELETE CASCADE (artikel tetap aman, hanya kehilangan tag).
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/tags');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    try {
        getDB()->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
        flash('success', 'Tag dihapus.', 'success');
    } catch (Throwable $e) {
        error_log('delete-tag: ' . $e->getMessage());
        flash('error', 'Gagal menghapus tag.', 'error');
    }
}
redirect('/admin/tags');
