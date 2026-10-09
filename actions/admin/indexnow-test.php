<?php
// POST /actions/admin/indexnow-test — kirim ping uji (URL beranda) ke IndexNow.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/indexnow.php';
requireAdmin();

$back = '/admin/integrations';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect($back);
}

indexnowEnsureKey();
$r = indexnowSubmit([url('/')], true); // force: boleh tes walau belum diaktifkan
if (!empty($r['ok'])) {
    flash('success', 'Ping IndexNow berhasil (HTTP ' . ($r['http'] ?? '-') . ', ' . ($r['count'] ?? 0)
        . ' URL). Verifikasi key dilakukan mesin pencari secara asinkron.', 'success');
} else {
    flash('error', 'Ping IndexNow gagal: ' . ($r['error'] ?? 'tidak diketahui') . '.', 'error');
}
redirect($back);
