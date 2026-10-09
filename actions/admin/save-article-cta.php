<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/article-cta.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error'); redirect('/admin/rebrand');
}
try {
    if (!is_array($_POST['article_cta'] ?? null)) throw new InvalidArgumentException('Pengaturan CTA tidak valid.');
    articleCtaSave('global', 0, $_POST['article_cta']);
    pageCacheFlushAll();
    flash('success', 'CTA artikel global disimpan.', 'success');
} catch (Throwable $e) {
    flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menyimpan CTA.', 'error');
}
redirect('/admin/rebrand');
