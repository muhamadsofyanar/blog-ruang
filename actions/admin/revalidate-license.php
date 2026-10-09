<?php
// POST /actions/admin/revalidate-license — paksa cek ulang ke license server.
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/license');
}

$key = (string) getSetting('license_key', '');
if ($key === '') {
    flash('error', 'Belum ada lisensi. Lakukan aktivasi terlebih dahulu.', 'error');
    redirect('/admin/license-activate');
}

$res = refreshLicenseFromServer($key, parseDomainFromAppUrl());
flash($res['ok'] ? 'success' : 'error',
      $res['ok'] ? 'Lisensi tervalidasi ulang. ' . $res['message'] : 'Re-validasi: ' . $res['message'],
      $res['ok'] ? 'success' : 'error');
redirect('/admin/license');
