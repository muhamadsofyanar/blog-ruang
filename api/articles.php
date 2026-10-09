<?php
// ════════════════════════════════════════════════════════════════════════
// Management API — /api/articles.php  (BACA saja)
//   GET                       : daftar artikel terbaru (?limit=&offset=).
//   GET ?id=<id>&full=1        : satu artikel LENGKAP (termasuk content).
//   GET ?category_id=<id>      : daftar difilter per kategori (audit/manajemen).
// Butuh ingest_enabled. Endpoint mesin: Bearer token, tanpa sesi/CSRF, read-only.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/article-cta.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Hanya GET.']);
}
ingestApiAuthorize(false);
$pdo = getDB();

// ─── Mode: satu artikel LENGKAP (?id=&full=1) ────────────────────
$idParam = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$full    = in_array((string) ($_GET['full'] ?? ''), ['1', 'true', 'yes'], true);
if ($idParam > 0 && $full) {
    $st = $pdo->prepare(
        "SELECT id, title, slug, status, category_id, focus_keyword, related_keywords, search_intent,
                language, meta_title, meta_description, excerpt, content, cover_image, published_at, author_id
         FROM articles WHERE id = ? LIMIT 1"
    );
    $st->execute([$idParam]);
    $a = $st->fetch();
    if (!$a) {
        ingestApiSend(404, ['ok' => false, 'error' => 'Artikel tidak ditemukan.']);
    }
    $directCta = articleCtaGet('article', (int) $a['id']);
    ingestApiSend(200, ['ok' => true, 'article' => [
        'id'               => (int) $a['id'],
        'title'            => $a['title'],
        'slug'             => $a['slug'],
        'status'           => $a['status'],
        'category_id'      => $a['category_id'] !== null ? (int) $a['category_id'] : null,
        'focus_keyword'    => $a['focus_keyword'],
        'related_keywords' => $a['related_keywords'],
        'search_intent'    => $a['search_intent'],
        'language'         => $a['language'],
        'meta_title'       => $a['meta_title'],
        'meta_description' => $a['meta_description'],
        'excerpt'          => $a['excerpt'],
        'content'          => $a['content'],
        'cover_image'      => $a['cover_image'],
        'published_at'     => $a['published_at'],
        'author_id'        => $a['author_id'] !== null ? (int) $a['author_id'] : null,
        'article_cta'      => articleCtaApiOutput($directCta),
        'effective_article_cta' => articleCtaApiOutput(articleCtaResolve($a)),
    ]]);
}

// ─── Mode: daftar (opsional filter category_id) + paginasi ───────
$limit  = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$catId  = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;

$where = $catId > 0 ? ' WHERE category_id = :cat' : '';

$totalSt = $pdo->prepare("SELECT COUNT(*) FROM articles" . $where);
if ($catId > 0) $totalSt->bindValue(':cat', $catId, PDO::PARAM_INT);
$totalSt->execute();
$total = (int) $totalSt->fetchColumn();

$st = $pdo->prepare(
    "SELECT id, title, slug, status, category_id, focus_keyword, published_at
     FROM articles" . $where . " ORDER BY id DESC LIMIT :lim OFFSET :off"
);
if ($catId > 0) $st->bindValue(':cat', $catId, PDO::PARAM_INT);
$st->bindValue(':lim', $limit, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();

$rows = array_map(fn($r) => [
    'id'            => (int) $r['id'],
    'title'         => $r['title'],
    'slug'          => $r['slug'],
    'status'        => $r['status'],
    'category_id'   => $r['category_id'] !== null ? (int) $r['category_id'] : null,
    'focus_keyword' => $r['focus_keyword'],
    'published_at'  => $r['published_at'],
], $st->fetchAll());

ingestApiSend(200, [
    'ok' => true, 'articles' => $rows,
    'limit' => $limit, 'offset' => $offset, 'total' => $total,
    'category_id' => $catId > 0 ? $catId : null,
]);
