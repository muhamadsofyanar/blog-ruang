<?php
// POST /actions/admin/delete-user — hapus pengguna (admin). Guard: user beratikel
// tidak bisa dihapus (nonaktifkan saja) + admin aktif terakhir tidak bisa dihapus.
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/users');
}

$pdo = getDB();
$id  = (int) ($_POST['id'] ?? 0);
if ($id <= 0) redirect('/admin/users');

try {
    $st = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u) { flash('error', 'Pengguna tidak ditemukan.', 'error'); redirect('/admin/users'); }

    // Admin aktif terakhir → tolak.
    if ($u['role'] === 'admin' && (int) $u['is_active'] === 1) {
        $cnt = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
        if ($cnt <= 1) {
            flash('error', 'Admin aktif terakhir tidak bisa dihapus.', 'error');
            redirect('/admin/users');
        }
    }

    // Beratikel → tolak (arahkan nonaktifkan).
    $ac = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE author_id = ?");
    $ac->execute([$id]);
    if ((int) $ac->fetchColumn() > 0) {
        flash('error', 'Pengguna masih memiliki artikel — tidak bisa dihapus. Nonaktifkan saja agar artikel tetap utuh.', 'error');
        redirect('/admin/users');
    }

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    flash('success', 'Pengguna dihapus.', 'success');
} catch (Throwable $e) {
    error_log('delete-user: ' . $e->getMessage());
    flash('error', 'Gagal menghapus pengguna.', 'error');
}
redirect('/admin/users');
