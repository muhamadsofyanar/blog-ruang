<?php
// POST /actions/auth/login — verifikasi kredensial, set session, revalidasi lisensi.
require_once __DIR__ . '/../../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/login');
}
if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token keamanan tidak valid. Coba lagi.', 'error');
    redirect('/login');
}

$email    = trim($_POST['email'] ?? '');
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    flash('error', 'Email dan password wajib diisi.', 'error');
    redirect('/login');
}

try {
    $stmt = getDB()->prepare('SELECT id, name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
} catch (Throwable $e) {
    error_log('login query error: ' . $e->getMessage());
    $user = false;
}

if (!$user || !password_verify($password, $user['password'])) {
    flash('error', 'Email atau password salah.', 'error');
    redirect('/login');
}
if ((int) $user['is_active'] !== 1) {
    flash('error', 'Akun dinonaktifkan. Hubungi admin.', 'error');
    redirect('/login');
}

// Catat waktu login terakhir (best-effort).
try { getDB()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]); }
catch (Throwable $e) { error_log('last_login update: ' . $e->getMessage()); }

// Set session (regenerate id — cegah fixation).
session_regenerate_id(true);
$_SESSION['user_id']   = (int) $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['role']      = $user['role'];

// Auto-revalidasi lisensi saat login admin (pola Averion) — sinkron, best-effort.
if ($user['role'] === 'admin') {
    $key = (string) getSetting('license_key', '');
    if ($key !== '') {
        try {
            refreshLicenseFromServer($key, parseDomainFromAppUrl());
        } catch (Throwable $e) {
            error_log('login revalidate error: ' . $e->getMessage());
        }
    }
}

// Redirect ke tujuan awal bila aman.
$dest = $_SESSION['login_redirect'] ?? '';
unset($_SESSION['login_redirect']);
if (is_string($dest) && $dest !== '' && isSafeRedirectPath($dest)) {
    redirect($dest);
}
redirect('/admin');
