<?php
// POST /actions/admin/save-profile — simpan profil penulis (bio/jabatan/avatar/sosial).
// Setiap staff menyunting profilnya sendiri (disimpan sebagai blob settings per user).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/author.php';
require_once __DIR__ . '/../../helpers/media.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
requireStaff();

$back = '/admin/profile';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect($back);
}

$uid = currentUserId();
$existing = authorProfileRaw($uid);
$avatar   = (string) ($existing['avatar'] ?? '');

$error = '';
if (isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $res = saveBrandingImage($_FILES['avatar'], 'avatar');
    if (isset($res['error'])) $error = $res['error'];
    else $avatar = $res['path'];
}

$social = preg_split('/[\r\n]+/', (string) ($_POST['social'] ?? '')) ?: [];

authorProfileSave($uid, [
    'bio'       => (string) ($_POST['bio'] ?? ''),
    'job_title' => (string) ($_POST['job_title'] ?? ''),
    'avatar'    => $avatar,
    'social'    => $social,
]);

// Halaman penulis publik di-cache → segarkan agar profil baru tampil.
try { pageCacheFlushAll(); } catch (Throwable $e) { error_log('save-profile cache: ' . $e->getMessage()); }

flash($error !== '' ? 'error' : 'success',
    $error !== '' ? ('Profil disimpan, tetapi avatar gagal: ' . $error) : 'Profil disimpan.',
    $error !== '' ? 'error' : 'success');
redirect($back);
