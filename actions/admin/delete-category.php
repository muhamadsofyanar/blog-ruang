<?php
// POST /actions/admin/delete-category — hapus kategori (admin). Guard: tolak bila
// masih ada artikel di kategori ini (bukan cascade — cegah artikel kehilangan
// kategori tanpa sadar).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/sitemap.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
require_once __DIR__ . '/../../helpers/article-cta.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/categories');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect('/admin/categories');
}

$pdo = getDB();
try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE category_id = ?");
    $cnt->execute([$id]);
    if ((int) $cnt->fetchColumn() > 0) {
        flash('error', 'Kategori tidak bisa dihapus karena masih berisi artikel. Pindahkan artikel dulu.', 'error');
        redirect('/admin/categories');
    }
    $catSlug = (string) ($pdo->query("SELECT slug FROM categories WHERE id = " . (int) $id)->fetchColumn() ?: '');
    // Lepaskan anak kategori (jadi tanpa induk) lalu hapus.
    $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([articleCtaKey('category', $id)]);

    scribeGenerateSitemap();
    pageCacheInvalidate(['/', '/?page=2', $catSlug !== '' ? '/kategori/' . $catSlug : '/']);
    flash('success', 'Kategori dihapus.', 'success');
} catch (Throwable $e) {
    error_log('delete-category: ' . $e->getMessage());
    flash('error', 'Gagal menghapus kategori.', 'error');
}
redirect('/admin/categories');
