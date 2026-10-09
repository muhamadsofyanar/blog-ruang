<?php
// ════════════════════════════════════════════════════════════════════════
// Controller halaman artikel publik (Track B / B-03). Urutan resolusi:
// artikel published hidup → cek tabel redirects (301/302) → 404. Konten
// dirender lewat scribeRenderArticle (sanitasi ganda + anchor + TOC). Markup
// di views/theme-default/article.php.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../views/theme-default/layout.php';
require_once __DIR__ . '/../../helpers/article-render.php';
require_once __DIR__ . '/../../helpers/seo-head.php';
require_once __DIR__ . '/../../helpers/schedule.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
require_once __DIR__ . '/../../helpers/lead-magnet.php';

$slug = slugify((string) ($_GET['slug'] ?? ''));

// Lazy publish (scheduled yang lewat) lalu cek cache. HIT → exit di sini.
scribeLazyPublish();
$cacheKey   = '/artikel/' . $slug;
$cacheState = pageCacheServe($cacheKey, scribeLeadMagnetForArticle(['id' => 0]) === null);

$article = $slug !== '' ? feFindPublishedArticle($slug) : null;

if (!$article) {
    // Artikel tak hidup → cek redirects sebelum 404 (slug lama → 301).
    // (Belum ada buffering cache → halaman redirect/404 TIDAK tertulis ke cache.)
    $rt = $slug !== '' ? feRedirectTarget($slug) : null;
    if ($rt) {
        http_response_code(((int) $rt['type'] === 302) ? 302 : 301);
        header('Location: ' . url('/artikel/' . rawurlencode((string) $rt['new_slug'])));
        exit;
    }
    http_response_code(404);
    require __DIR__ . '/../404.php';
    return;
}

pageCacheBegin($cacheKey, $cacheState);

$rendered = scribeRenderArticle((string) ($article['content'] ?? ''));
$faqs     = feApprovedFaqs((int) $article['id']);
$related  = feRelatedArticles($article, 3);
$nav      = fePrevNextArticles($article);
$ctx      = seoArticleContext($article, $faqs);

// Tag artikel (untuk daftar tag di bawah konten).
$tags = [];
try {
    $st = getDB()->prepare("SELECT t.name, t.slug FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ? ORDER BY t.name");
    $st->execute([(int) $article['id']]);
    $tags = $st->fetchAll();
} catch (Throwable $e) { $tags = []; }

require __DIR__ . '/../../views/theme-default/article.php';
pageCacheClose();
