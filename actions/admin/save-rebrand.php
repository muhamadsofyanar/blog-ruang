<?php
// POST /actions/admin/save-rebrand — simpan identitas rebrand + aset (admin).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/media.php';
require_once __DIR__ . '/../../helpers/frontend.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/rebrand');
}

setSetting('blog_name', trim($_POST['blog_name'] ?? ''), 'rebrand');
setSetting('blog_tagline', trim($_POST['blog_tagline'] ?? ''), 'rebrand');
setSetting('email_sender_name', trim($_POST['email_sender_name'] ?? ''), 'rebrand');

// Profil sosial (sameAs schema Organization) — satu URL http(s) per baris, maks 10.
$social = [];
foreach (preg_split('/[\r\n]+/', (string) ($_POST['brand_social'] ?? '')) as $line) {
    $line = trim((string) $line);
    if ($line !== '' && preg_match('#^https?://#i', $line)) $social[$line] = true;
}
setSetting('brand_social', implode("\n", array_slice(array_keys($social), 0, 10)), 'rebrand');

$accent = trim($_POST['accent_color'] ?? '');
if (preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
    setSetting('accent_color', strtolower($accent), 'rebrand');
}

// Toggle Powered by (default ON) — checkbox tak dikirim = mati.
setSetting('powered_by_enabled', isset($_POST['powered_by_enabled']) ? '1' : '0', 'rebrand');
setSetting('powered_by_text', trim($_POST['powered_by_text'] ?? ''), 'rebrand');
setSetting('powered_by_url', trim($_POST['powered_by_url'] ?? ''), 'rebrand');
setSetting('powered_by_blank', isset($_POST['powered_by_blank']) ? '1' : '0', 'rebrand');

// CTA header + band beranda (Track B).
setSetting('cta_label', trim($_POST['cta_label'] ?? ''), 'rebrand');
setSetting('cta_url', trim($_POST['cta_url'] ?? ''), 'rebrand');
$bandMode = in_array($_POST['cta_band_mode'] ?? '', ['off', 'link', 'newsletter'], true) ? $_POST['cta_band_mode'] : 'off';
setSetting('cta_band_mode', $bandMode, 'rebrand');
setSetting('cta_band_title', trim($_POST['cta_band_title'] ?? ''), 'rebrand');
setSetting('cta_band_text', trim($_POST['cta_band_text'] ?? ''), 'rebrand');
setSetting('cta_band_btn_label', trim($_POST['cta_band_btn_label'] ?? ''), 'rebrand');
setSetting('cta_band_url', trim($_POST['cta_band_url'] ?? ''), 'rebrand');

// Aset gambar (opsional).
$assets = ['logo' => 'brand_logo', 'favicon' => 'brand_favicon', 'og_default' => 'brand_og_default'];
$errors = [];
foreach ($assets as $field => $settingKey) {
    if (isset($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $res = saveBrandingImage($_FILES[$field], $field);
        if (isset($res['error'])) { $errors[] = ucfirst($field) . ': ' . $res['error']; }
        else { setSetting($settingKey, $res['path'], 'rebrand'); }
    }
}

// ─── Popup Promo (frontend) ──────────────────────────────────────
setSetting('promo_enabled', isset($_POST['promo_enabled']) ? '1' : '0', 'promo');
setSetting('promo_caption', trim($_POST['promo_caption'] ?? ''), 'promo');
setSetting('promo_cta_label', trim($_POST['promo_cta_label'] ?? ''), 'promo');
setSetting('promo_cta_url', trim($_POST['promo_cta_url'] ?? ''), 'promo');
$promoPos = in_array($_POST['promo_position'] ?? '', PROMO_POSITIONS, true) ? $_POST['promo_position'] : 'bottom-left';
setSetting('promo_position', $promoPos, 'promo');
$promoDelay = (int) ($_POST['promo_delay'] ?? 3);
if ($promoDelay < 0 || $promoDelay > 120) $promoDelay = 3;
setSetting('promo_delay', (string) $promoDelay, 'promo');
// Gambar promo: unggahan baru menang; jika tidak ada & ditandai hapus → kosongkan.
if (isset($_FILES['promo_image']) && ($_FILES['promo_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $res = saveBrandingImage($_FILES['promo_image'], 'promo');
    if (isset($res['error'])) { $errors[] = 'Promo: ' . $res['error']; }
    else { setSetting('promo_image', $res['path'], 'promo'); }
} elseif (isset($_POST['promo_image_remove'])) {
    setSetting('promo_image', '', 'promo');
}

// Rebrand (nama/logo/accent/CTA/og) memengaruhi SEMUA halaman publik → flush
// seluruh page cache + regen sitemap (og_default bisa berubah).
require_once __DIR__ . '/../../helpers/page-cache.php';
require_once __DIR__ . '/../../helpers/sitemap.php';
pageCacheFlushAll();
scribeGenerateSitemap();

if ($errors) {
    flash('error', 'Sebagian aset gagal: ' . implode(' ', $errors), 'error');
} else {
    flash('success', 'Rebrand disimpan.', 'success');
}
redirect('/admin/rebrand');
