<?php
// POST /actions/admin/bio-save-profile — simpan profil biolink + mode homepage (admin).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/biolink.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/biolink');
}

$plain = static function ($value, int $max): string {
    $value = strip_tags(trim((string) $value));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $max);
};
$errors = [];

$mode = in_array($_POST['home_mode'] ?? '', ['blog', 'biolink', 'hybrid'], true) ? $_POST['home_mode'] : 'blog';
setSetting('home_mode', $mode, 'biolink');
setSetting('bio_display_name', trim($_POST['bio_display_name'] ?? ''), 'biolink');
setSetting('bio_text', trim($_POST['bio_text'] ?? ''), 'biolink');

$style = in_array($_POST['bio_header_style'] ?? '', ['gradient', 'solid', 'image'], true) ? $_POST['bio_header_style'] : 'gradient';
setSetting('bio_header_style', $style, 'biolink');

// Header background: solid = warna hex; image = upload; gradient = tak dipakai.
if ($style === 'solid') {
    $color = trim($_POST['header_color'] ?? '');
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) setSetting('bio_header_bg', strtolower($color), 'biolink');
} elseif ($style === 'image') {
    if (isset($_FILES['header_image']) && ($_FILES['header_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $res = saveBioImage($_FILES['header_image'], 'header');
        if (isset($res['error'])) { $errors[] = 'Header: ' . $res['error']; }
        else {
            scribeDeleteBioUpload(getSetting('bio_header_bg', ''));
            setSetting('bio_header_bg', $res['path'], 'biolink');
        }
    }
}

// Avatar upload / hapus.
if (isset($_POST['remove_avatar'])) {
    scribeDeleteBioUpload(getSetting('bio_avatar', ''));
    setSetting('bio_avatar', '', 'biolink');
} elseif (isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $res = saveBioImage($_FILES['avatar'], 'avatar');
    if (isset($res['error'])) { $errors[] = 'Avatar: ' . $res['error']; }
    else {
        scribeDeleteBioUpload(getSetting('bio_avatar', ''));
        setSetting('bio_avatar', $res['path'], 'biolink');
    }
}

// Panel Perkenalan BioLink. Semua konten disimpan sebagai plain text.
setSetting('bio_intro_enabled', isset($_POST['bio_intro_enabled']) ? '1' : '0', 'biolink');
setSetting('bio_intro_button', $plain($_POST['bio_intro_button'] ?? '', 80), 'biolink');
setSetting('bio_intro_title', $plain($_POST['bio_intro_title'] ?? '', 160), 'biolink');
setSetting('bio_intro_text', $plain($_POST['bio_intro_text'] ?? '', 5000), 'biolink');

$introAnimation = in_array($_POST['bio_intro_animation'] ?? '', ['typewriter', 'fade', 'normal'], true)
    ? $_POST['bio_intro_animation'] : 'typewriter';
setSetting('bio_intro_animation', $introAnimation, 'biolink');
setSetting('bio_intro_speed', (string) max(10, min(200, (int) ($_POST['bio_intro_speed'] ?? 35))), 'biolink');
setSetting('bio_intro_cta_label', $plain($_POST['bio_intro_cta_label'] ?? '', 100), 'biolink');
setSetting('bio_intro_cta_blank', isset($_POST['bio_intro_cta_blank']) ? '1' : '0', 'biolink');

$introUrlRaw = mb_substr(trim((string) ($_POST['bio_intro_cta_url'] ?? '')), 0, 500);
if ($introUrlRaw === '') {
    setSetting('bio_intro_cta_url', '', 'biolink');
} else {
    $introUrl = bioSafeUrl($introUrlRaw);
    if (!preg_match('#^https?://#i', $introUrl) || filter_var($introUrl, FILTER_VALIDATE_URL) === false) {
        $errors[] = 'URL CTA perkenalan tidak valid.';
    } else {
        setSetting('bio_intro_cta_url', $introUrl, 'biolink');
    }
}

if (isset($_POST['remove_intro_photo'])) {
    scribeDeleteBioUpload(getSetting('bio_intro_photo', ''));
    setSetting('bio_intro_photo', '', 'biolink');
} elseif (isset($_FILES['bio_intro_photo']) && ($_FILES['bio_intro_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $res = saveBioImage($_FILES['bio_intro_photo'], 'intro');
    if (isset($res['error'])) { $errors[] = 'Foto perkenalan: ' . $res['error']; }
    else {
        scribeDeleteBioUpload(getSetting('bio_intro_photo', ''));
        setSetting('bio_intro_photo', $res['path'], 'biolink');
    }
}

pageCacheFlushAll();

flash($errors ? 'error' : 'success', $errors ? implode(' ', $errors) : 'Profil biolink disimpan.', $errors ? 'error' : 'success');
redirect('/admin/biolink');
