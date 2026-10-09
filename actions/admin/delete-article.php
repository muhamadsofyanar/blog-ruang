<?php
// POST /actions/admin/delete-article — hapus artikel (staff). Writer hanya
// miliknya. Relasi article_tags + seo_ai_* ikut terhapus via FK CASCADE.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/sitemap.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
require_once __DIR__ . '/../../helpers/article-cta.php';
requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/articles');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) redirect('/admin/articles');

$pdo = getDB();
try {
    $st = $pdo->prepare("SELECT a.author_id, a.slug, c.slug AS cat_slug FROM articles a
                         LEFT JOIN categories c ON c.id = a.category_id WHERE a.id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('error', 'Artikel tidak ditemukan.', 'error');
        redirect('/admin/articles');
    }
    if (isWriter() && (int) $row['author_id'] !== currentUserId()) {
        denyAccess();
    }
    // Tangkap tag slug SEBELUM hapus (FK cascade akan menghapus relasi).
    $tg = $pdo->prepare("SELECT t.slug FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ?");
    $tg->execute([$id]);
    $tagSlugs = $tg->fetchAll(PDO::FETCH_COLUMN);

    $pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([articleCtaKey('article', $id)]);

    // Event-driven: regen sitemap + invalidate cache terkait.
    scribeGenerateSitemap();
    pageCacheInvalidateArticle((string) $row['slug'], $row['cat_slug'] ?: null, $tagSlugs);

    flash('success', 'Artikel dihapus.', 'success');
} catch (Throwable $e) {
    error_log('delete-article: ' . $e->getMessage());
    flash('error', 'Gagal menghapus artikel.', 'error');
}
redirect('/admin/articles');
