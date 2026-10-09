<?php
// POST /actions/admin/save-ai-settings — bahasa default output.
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/settings');
}
$lang = isValidLanguage($_POST['default_language'] ?? '') ? $_POST['default_language'] : 'id';
setSetting('default_language', $lang, 'ai');
flash('success', 'Pengaturan AI disimpan.', 'success');
redirect('/admin/settings');
