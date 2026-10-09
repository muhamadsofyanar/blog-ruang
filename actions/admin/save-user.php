<?php
// POST /actions/admin/save-user — buat/edit pengguna (admin). Password hash
// (pola A-01). Guard: admin aktif terakhir tidak boleh di-demote jadi writer.
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/users');
}

$pdo   = getDB();
$id    = (int) ($_POST['id'] ?? 0);
$name  = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$pass  = (string) ($_POST['password'] ?? '');
$role  = in_array($_POST['role'] ?? '', ['admin', 'writer'], true) ? $_POST['role'] : 'writer';

if ($name === '' || $email === '') {
    flash('error', 'Nama dan email wajib diisi.', 'error');
    redirect('/admin/users');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Format email tidak valid.', 'error');
    redirect('/admin/users');
}

// Email unik (kecuali baris sendiri).
$chk = $pdo->prepare("SELECT id FROM users WHERE email = ?" . ($id ? " AND id <> ?" : "") . " LIMIT 1");
$chk->execute($id ? [$email, $id] : [$email]);
if ($chk->fetchColumn() !== false) {
    flash('error', 'Email sudah dipakai pengguna lain.', 'error');
    redirect('/admin/users');
}

function scribeActiveAdminCount(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
}

try {
    if ($id > 0) {
        $cur = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ? LIMIT 1");
        $cur->execute([$id]);
        $u = $cur->fetch();
        if (!$u) { flash('error', 'Pengguna tidak ditemukan.', 'error'); redirect('/admin/users'); }

        // Guard demote admin aktif terakhir.
        if ($u['role'] === 'admin' && (int) $u['is_active'] === 1 && $role === 'writer' && scribeActiveAdminCount($pdo) <= 1) {
            flash('error', 'Tidak bisa menurunkan peran admin aktif terakhir. Buat admin lain dulu.', 'error');
            redirect('/admin/users');
        }

        if ($pass !== '') {
            if (strlen($pass) < 8) { flash('error', 'Password minimal 8 karakter.', 'error'); redirect('/admin/users'); }
            $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?")
                ->execute([$name, $email, $role, password_hash($pass, PASSWORD_DEFAULT), $id]);
        } else {
            $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?")
                ->execute([$name, $email, $role, $id]);
        }
        flash('success', 'Pengguna diperbarui.', 'success');
    } else {
        if (strlen($pass) < 8) { flash('error', 'Password minimal 8 karakter untuk pengguna baru.', 'error'); redirect('/admin/users'); }
        $pdo->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)")
            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
        flash('success', 'Pengguna ditambahkan.', 'success');
    }
} catch (Throwable $e) {
    error_log('save-user: ' . $e->getMessage());
    flash('error', 'Gagal menyimpan pengguna.', 'error');
}
redirect('/admin/users');
