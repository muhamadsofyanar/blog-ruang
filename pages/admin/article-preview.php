<?php
// ════════════════════════════════════════════════════════════════════════
// Pratinjau artikel (admin/staff) — render artikel APA PUN statusnya (termasuk
// draft/draft_ai yang TIDAK tampil publik) memakai template artikel publik yang
// SAMA (views/theme-default/article.php) agar hasilnya persis. Guard route =
// 'staff' (index.php); writer hanya boleh pratinjau artikelnya sendiri.
// Mengutamakan draft_content (hasil autosave terbaru) bila ada. TANPA cache.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../views/theme-default/layout.php';
require_once __DIR__ . '/../../helpers/frontend.php';
require_once __DIR__ . '/../../helpers/article-render.php';
require_once __DIR__ . '/../../helpers/seo-head.php';

$id      = (int) ($_GET['id'] ?? 0);
$article = $id > 0 ? feFindArticleForPreview($id) : null;

if (!$article) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    return;
}

// Writer hanya boleh pratinjau artikel miliknya.
if (isWriter() && (int) $article['author_id'] !== currentUserId()) {
    denyAccess();
}

// Utamakan draft (autosave terbaru) agar pratinjau mencerminkan editan berjalan.
$draft   = (string) ($article['draft_content'] ?? '');
$content = trim($draft) !== '' ? $draft : (string) ($article['content'] ?? '');

// Hindari indexing pratinjau + jangan cache.
header('X-Robots-Tag: noindex, nofollow', true);

$rendered = scribeRenderArticle($content);
$faqs     = feApprovedFaqs((int) $article['id']);
$related  = feRelatedArticles($article, 3);
$nav      = fePrevNextArticles($article);
$ctx      = seoArticleContext($article, $faqs);
$ctx['robots'] = 'noindex, nofollow'; // pertegas di <head> bila didukung seo-head

$tags = [];
try {
    $st = getDB()->prepare("SELECT t.name, t.slug FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ? ORDER BY t.name");
    $st->execute([(int) $article['id']]);
    $tags = $st->fetchAll();
} catch (Throwable $e) { $tags = []; }

// Aktifkan banner pratinjau di layout (theme_head).
$GLOBALS['scribe_preview'] = [
    'status'   => (string) $article['status'],
    'edit_url' => url('/admin/articles/' . (int) $article['id']),
    'is_draft' => trim($draft) !== '',
];

require __DIR__ . '/../../views/theme-default/article.php';
