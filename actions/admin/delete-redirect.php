<?php
// POST /actions/admin/delete-redirect — hapus redirect (admin).
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/redirects');
}
$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    try {
        getDB()->prepare("DELETE FROM redirects WHERE id = ?")->execute([$id]);
        flash('success', 'Redirect dihapus.', 'success');
    } catch (Throwable $e) {
        error_log('delete-redirect: ' . $e->getMessage());
        flash('error', 'Gagal menghapus redirect.', 'error');
    }
}
redirect('/admin/redirects');
