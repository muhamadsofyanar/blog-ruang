<?php
// POST /actions/admin/toggle-user — aktif/nonaktif pengguna (admin). Guard: admin
// aktif terakhir tidak boleh dinonaktifkan.
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

    $newActive = (int) $u['is_active'] === 1 ? 0 : 1;

    // Nonaktifkan admin aktif terakhir → tolak.
    if ($newActive === 0 && $u['role'] === 'admin') {
        $cnt = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
        if ($cnt <= 1) {
            flash('error', 'Admin aktif terakhir tidak bisa dinonaktifkan.', 'error');
            redirect('/admin/users');
        }
    }

    $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newActive, $id]);
    flash('success', $newActive === 1 ? 'Pengguna diaktifkan.' : 'Pengguna dinonaktifkan.', 'success');
} catch (Throwable $e) {
    error_log('toggle-user: ' . $e->getMessage());
    flash('error', 'Gagal mengubah status pengguna.', 'error');
}
redirect('/admin/users');
