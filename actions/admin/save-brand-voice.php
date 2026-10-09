<?php
// POST /actions/admin/save-brand-voice — preferensi gaya brand (variabel customer).
// banned_words disimpan sebagai array JSON. Semua di settings (prefix brandvoice_).
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/settings');
}

$business = trim($_POST['business'] ?? '');
$audience = trim($_POST['audience'] ?? '');
$tone     = in_array($_POST['tone'] ?? '', ['santai', 'profesional', 'edukatif', 'persuasif'], true) ? $_POST['tone'] : '';
$style    = trim($_POST['style_sample'] ?? '');

// banned_words: textarea satu per baris → array bersih.
$banned = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['banned_words'] ?? '')), fn($w) => $w !== ''));

setSetting('brandvoice_business', $business, 'brandvoice');
setSetting('brandvoice_audience', $audience, 'brandvoice');
setSetting('brandvoice_tone', $tone, 'brandvoice');
setSetting('brandvoice_banned_words', json_encode($banned), 'brandvoice');
setSetting('brandvoice_style_sample', $style, 'brandvoice');

flash('success', 'Brand Voice disimpan.', 'success');
redirect('/admin/settings');
