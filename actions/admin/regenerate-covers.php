<?php
// POST /actions/admin/regenerate-covers — regenerasi ULANG semua cover fallback
// (SVG) dengan palet/versi terkini + backfill yang hilang. Cover asli (upload
// user) TIDAK disentuh. Berguna setelah update palet cover (file uploads/ tidak
// ikut self-update, jadi cover lama perlu diregenerasi manual).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/cover-fallback.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
require_once __DIR__ . '/../../helpers/sitemap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/rebrand');
}

try {
    $pdo = getDB();
    $n = scribeBackfillFallbackCovers($pdo, null, true); // force regen semua fallback
    pageCacheFlushAll();
    flash('success', "Regenerasi cover selesai — $n cover diperbarui.", 'success');
} catch (Throwable $e) {
    error_log('regenerate-covers: ' . $e->getMessage());
    flash('error', 'Gagal meregenerasi cover.', 'error');
}
redirect('/admin/rebrand');
