<?php
// POST /actions/admin/activate-license — validasi key+domain ke license server.
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/license-activate');
}

$key    = strtoupper(trim($_POST['license_key'] ?? ''));
$domain = parseDomainFromAppUrl(); // domain terkunci ke host instalasi

if ($key === '') {
    flash('error', 'License key wajib diisi.', 'error');
    redirect('/admin/license-activate');
}

$res = refreshLicenseFromServer($key, $domain);

if ($res['ok']) {
    flash('success', 'Lisensi berhasil diaktifkan. ' . $res['message'], 'success');
    redirect('/admin');
}

flash('error', 'Aktivasi gagal: ' . $res['message'], 'error');
redirect('/admin/license-activate');
