<?php
// POST /actions/admin/bio-save-block — buat/ubah satu blok biolink (admin).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/media.php';
require_once __DIR__ . '/../../helpers/biolink.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/biolink');
}

$pdo   = getDB();
$id    = (int) ($_POST['block_id'] ?? 0);
$type  = (string) ($_POST['type'] ?? '');
if (!in_array($type, BIO_BLOCK_TYPES, true)) {
    flash('error', 'Tipe blok tidak valid.', 'error');
    redirect('/admin/biolink');
}

// Blok existing (untuk edit: pertahankan/hapus gambar lama).
$existing = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM bio_blocks WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $existing = $st->fetch();
    if (!$existing) { flash('error', 'Blok tidak ditemukan.', 'error'); redirect('/admin/biolink'); }
    $type = $existing['type']; // tipe tak boleh berubah saat edit
}
$oldCfg = $existing ? (json_decode((string) $existing['config_json'], true) ?: []) : [];

$errors = [];
$cfg = [];

switch ($type) {
    case 'button':
        $cfg['label'] = trim($_POST['label'] ?? '');
        $cfg['url']   = trim($_POST['url'] ?? '');
        $cfg['icon']  = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['icon'] ?? '')));
        $cfg['style'] = in_array($_POST['style'] ?? 'filled', ['filled', 'outline', 'soft'], true) ? $_POST['style'] : 'filled';
        if ($cfg['url'] === '') $errors[] = 'URL tombol wajib diisi.';
        // Foto tombol (opsional).
        $cfg['photo'] = (string) ($oldCfg['photo'] ?? '');
        if (isset($_POST['remove_photo'])) { scribeDeleteBioUpload($cfg['photo']); $cfg['photo'] = ''; }
        if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $r = saveBioImage($_FILES['photo'], 'btn');
            if (isset($r['error'])) $errors[] = 'Foto: ' . $r['error'];
            else { scribeDeleteBioUpload($cfg['photo']); $cfg['photo'] = $r['path']; }
        }
        if ($cfg['icon'] !== '' && $cfg['photo'] !== '') $cfg['icon'] = ''; // foto menang
        break;

    case 'social':
        $items = [];
        foreach (array_keys(bioSocialNetworks()) as $net) {
            $val = trim($_POST['social'][$net] ?? '');
            if ($val !== '') $items[] = ['network' => $net, 'url' => $val];
        }
        if (!$items) $errors[] = 'Isi minimal satu tautan sosial.';
        $cfg['items'] = $items;
        break;

    case 'text':
        $cfg['heading'] = trim($_POST['heading'] ?? '');
        $cfg['body']    = trim($_POST['body'] ?? '');
        if ($cfg['heading'] === '' && $cfg['body'] === '') $errors[] = 'Isi judul atau teks.';
        break;

    case 'image':
        $cfg['path'] = (string) ($oldCfg['path'] ?? '');
        $cfg['alt']  = trim($_POST['alt'] ?? '');
        $cfg['link'] = trim($_POST['link'] ?? '');
        if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $r = saveBioImage($_FILES['image'], 'img');
            if (isset($r['error'])) $errors[] = 'Gambar: ' . $r['error'];
            else { scribeDeleteBioUpload($cfg['path']); $cfg['path'] = $r['path']; }
        }
        if ($cfg['path'] === '') $errors[] = 'Unggah gambar terlebih dahulu.';
        break;

    case 'divider':
        $cfg['style'] = ($_POST['divider_style'] ?? 'line') === 'space' ? 'space' : 'line';
        break;
}

if ($errors) {
    flash('error', implode(' ', $errors), 'error');
    redirect('/admin/biolink');
}

$json = json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
// Form EDIT selalu memuat checkbox is_active → absen = sengaja dinonaktifkan.
// Form TAMBAH tak punya checkbox → blok baru default aktif.
$isActive = $existing ? (isset($_POST['is_active']) ? 1 : 0) : 1;

try {
    if ($existing) {
        $pdo->prepare('UPDATE bio_blocks SET config_json = ?, is_active = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$json, $isActive, $id]);
        $msg = 'Blok ' . bioBlockTypeLabel($type) . ' diperbarui.';
    } else {
        $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM bio_blocks')->fetchColumn();
        $pdo->prepare('INSERT INTO bio_blocks (type, sort_order, is_active, config_json) VALUES (?, ?, 1, ?)')
            ->execute([$type, $next, $json]);
        $msg = 'Blok ' . bioBlockTypeLabel($type) . ' ditambahkan.';
    }
    pageCacheFlushAll();
    flash('success', $msg, 'success');
} catch (Throwable $e) {
    error_log('bio-save-block: ' . $e->getMessage());
    flash('error', 'Gagal menyimpan blok.', 'error');
}
redirect('/admin/biolink');
