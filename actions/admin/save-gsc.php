<?php
// POST /actions/admin/save-gsc — simpan konfigurasi Google Search Console + tes koneksi.
// Service account JSON disimpan di settings (rahasia; tidak pernah dikirim balik).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/gsc.php';
requireAdmin();

$back = '/admin/content-health?view=search';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect($back);
}

try {
    $siteUrl = trim((string) ($_POST['gsc_site_url'] ?? ''));
    $saInput = trim((string) ($_POST['gsc_sa_json'] ?? ''));
    $enabled = isset($_POST['gsc_enabled']);
    $changed = false;

    // Service account: hanya diproses bila kolom diisi (kosong = pertahankan yang ada).
    if ($saInput !== '') {
        $sa = json_decode($saInput, true);
        if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            throw new InvalidArgumentException('JSON service account tidak valid (butuh client_email & private_key).');
        }
        if (($sa['type'] ?? '') !== 'service_account') {
            throw new InvalidArgumentException('File yang ditempel bukan kunci service account. Unduh JSON tipe "service_account" dari Google Cloud.');
        }
        setSetting('gsc_sa_json', (string) json_encode($sa), 'gsc');
        $changed = true;
    }

    // URL properti (dukung sc-domain: dan https://).
    if ($siteUrl !== '') {
        $okFmt = str_starts_with($siteUrl, 'sc-domain:') || preg_match('#^https?://#i', $siteUrl);
        if (!$okFmt) {
            throw new InvalidArgumentException('URL properti harus diawali "https://" atau "sc-domain:".');
        }
    }
    if ($siteUrl !== (string) getSetting('gsc_site_url', '')) $changed = true;
    setSetting('gsc_site_url', $siteUrl, 'gsc');

    // Aktivasi hanya bila sudah lengkap (SA + URL).
    if ($enabled && !gscConfigured()) {
        throw new InvalidArgumentException('Lengkapi service account dan URL properti sebelum mengaktifkan.');
    }
    setSetting('gsc_enabled', $enabled ? '1' : '0', 'gsc');

    if ($changed) gscClearCaches();

    // Tes koneksi bila sudah lengkap.
    if (gscConfigured()) {
        $test = gscTestConnection();
        if ($test['ok']) {
            flash('success', 'Konfigurasi disimpan. ' . ($test['message'] ?? 'Koneksi berhasil.'), 'success');
        } else {
            flash('error', 'Konfigurasi disimpan, tetapi tes koneksi gagal: ' . ($test['error'] ?? 'tidak diketahui'), 'error');
        }
    } else {
        flash('success', 'Konfigurasi disimpan.', 'success');
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage(), 'error');
}
redirect($back);
