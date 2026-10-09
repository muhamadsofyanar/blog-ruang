<?php
// POST /actions/admin/gsc-refresh — tarik ulang data Search Console ke cache.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/gsc.php';
requireAdmin();

$back = '/admin/content-health?view=search';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect($back);
}

if (!gscConfigured()) {
    flash('error', 'Search Console belum terkonfigurasi.', 'error');
    redirect($back);
}

$res = gscRefreshCache();
if ($res['ok']) {
    flash('success', 'Data Search Console diperbarui (' . (int) $res['count'] . ' halaman, ' . e((string) $res['start']) . ' s/d ' . e((string) $res['end']) . ').', 'success');
} else {
    flash('error', 'Gagal memperbarui data: ' . ($res['error'] ?? 'tidak diketahui'), 'error');
}
redirect($back);
