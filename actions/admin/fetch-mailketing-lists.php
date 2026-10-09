<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/mailketing.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) { flash('error', 'Permintaan tidak valid.', 'error'); redirect('/admin/integrations'); }
$res = scribeMailketingViewLists();
if ($res['ok']) {
    setSetting('mailketing_lists_cache', json_encode($res['lists'], JSON_UNESCAPED_UNICODE), 'integrations');
    flash('success', 'Daftar list Mailketing berhasil dimuat.', 'success');
} else {
    flash('error', 'Daftar list belum dapat dimuat: ' . ($res['message'] ?: 'periksa token dan koneksi.'), 'error');
}
redirect('/admin/integrations');
