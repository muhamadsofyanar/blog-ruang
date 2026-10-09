<?php
// POST /actions/admin/save-byok — kirim API key ke GATEWAY (bukan simpan di DB
// client). Key hanya lewat; gateway menyimpan terenkripsi (sodium).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/AverionAiAdapter.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/settings');
}

$provider = strtolower(trim((string) ($_POST['provider'] ?? 'anthropic')));
$apiKey   = trim((string) ($_POST['api_key'] ?? ''));
if (!in_array($provider, ['anthropic', 'gemini'], true)) {
    flash('error', 'Provider BYOK tidak didukung.', 'error');
    redirect('/admin/settings');
}
if ($apiKey === '') {
    flash('error', 'API key wajib diisi.', 'error');
    redirect('/admin/settings');
}
if (strlen($apiKey) > 512 || preg_match('/[\x00-\x20\x7F]/', $apiKey)) {
    flash('error', 'Format API key tidak valid.', 'error');
    redirect('/admin/settings');
}

$adapter = new AverionAiAdapter();
$res = $adapter->saveByok($provider, $apiKey);
$adapter->invalidateBalanceCache();

if ($res['ok']) {
    $providerLabel = $provider === 'gemini' ? 'Google Gemini' : 'Anthropic';
    flash('success', 'BYOK ' . $providerLabel . ' aktif. Kredit tidak akan dipotong selama BYOK aktif.', 'success');
} else {
    flash('error', 'Gagal menyimpan BYOK: ' . $res['message'], 'error');
}
redirect('/admin/settings');
