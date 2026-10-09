<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/meta-pixel.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/integrations');
}
$pdo = getDB();
try {
    if (!is_string($_POST['meta_pixel_ids'] ?? '') || !is_string($_POST['meta_pixel_area'] ?? '')) {
        throw new InvalidArgumentException('Format pengaturan tidak valid.');
    }
    $ids = metaPixelIds($_POST['meta_pixel_ids'] ?? '');
    $area = $_POST['meta_pixel_area'] ?? 'both';
    if (!in_array($area, ['both', 'blog', 'biolink'], true)) throw new InvalidArgumentException('Area tracking tidak valid.');
    $enabled = isset($_POST['meta_pixel_enabled']);
    if ($enabled && !$ids) throw new InvalidArgumentException('Isi minimal satu Pixel ID sebelum mengaktifkan.');
    $values = ['meta_pixel_enabled' => $enabled ? '1' : '0', 'meta_pixel_ids' => implode("\n", $ids),
        'meta_pixel_area' => $area, 'meta_pixel_cta' => isset($_POST['meta_pixel_cta']) ? '1' : '0'];
    $pdo->beginTransaction();
    foreach ($values as $key => $value) {
        if (!setSetting($key, $value, 'tracking')) throw new RuntimeException('Gagal menyimpan setting.');
    }
    $pdo->commit();
    pageCacheFlushAll();
    flash('success', 'Pengaturan Meta Pixel disimpan.', 'success');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', $error instanceof InvalidArgumentException ? $error->getMessage() : 'Pengaturan belum dapat disimpan. Silakan coba lagi.', 'error');
}
redirect('/admin/integrations');
