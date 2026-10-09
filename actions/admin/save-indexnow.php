<?php
// POST /actions/admin/save-indexnow — aktif/nonaktif IndexNow (indeks cepat).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/indexnow.php';
requireAdmin();

$back = '/admin/integrations';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect($back);
}

$enabled = isset($_POST['indexnow_enabled']);
if ($enabled) indexnowEnsureKey(); // pastikan key tersedia sebelum diaktifkan
setSetting('indexnow_enabled', $enabled ? '1' : '0', 'indexnow');

flash('success', $enabled
    ? 'IndexNow aktif. URL artikel otomatis dikirim ke mesin pencari saat publish/update.'
    : 'IndexNow dinonaktifkan.', 'success');
redirect($back);
