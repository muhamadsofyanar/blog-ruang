<?php
// POST /actions/admin/optimize-images — optimasi aman aset lama. Sumber asli
// dipertahankan; referensi DB dipindahkan ke salinan WebP/PNG teroptimasi.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/media.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
require_once __DIR__ . '/../../helpers/sitemap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/rebrand');
}

@set_time_limit(300);
$pdo = getDB();
$changed = 0;
$errors = [];

// Branding, popup, dan profil BioLink.
$critical = scribeOptimizeCriticalImages($pdo);
$changed += (int) $critical['changed'];
$errors = array_merge($errors, $critical['errors']);

// Cover unggahan artikel. Fallback SVG sengaja tidak disentuh.
try {
    $rows = $pdo->query("SELECT id, cover_image FROM articles WHERE cover_is_fallback = 0 AND COALESCE(cover_image, '') <> ''")->fetchAll();
    $update = $pdo->prepare('UPDATE articles SET cover_image = ? WHERE id = ?');
    foreach ($rows as $row) {
        $res = scribeOptimizeStoredImage((string) $row['cover_image'], 'cover');
        if (isset($res['error'])) { $errors[] = $res['error']; continue; }
        if (!empty($res['changed'])) {
            $update->execute([$res['path'], (int) $row['id']]);
            $changed++;
        }
    }
} catch (Throwable $e) {
    error_log('optimize-images articles: ' . $e->getMessage());
    $errors[] = 'Sebagian cover artikel tidak dapat diproses.';
}

pageCacheFlushAll();
scribeGenerateSitemap();

$message = $changed > 0
    ? $changed . ' gambar berhasil dioptimalkan. Berkas asli tetap tersimpan sebagai cadangan.'
    : 'Semua gambar yang didukung sudah teroptimasi.';
if ($errors) $message .= ' ' . count($errors) . ' file dilewati; detail dicatat di log server.';
flash($errors && $changed === 0 ? 'error' : 'success', $message, $errors && $changed === 0 ? 'error' : 'success');
redirect('/admin/rebrand');
