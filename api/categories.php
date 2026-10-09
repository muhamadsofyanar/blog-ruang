<?php
// ════════════════════════════════════════════════════════════════════════
// Management API — /api/categories.php
//   GET  : daftar kategori (butuh ingest_enabled).
//   POST : buat/ubah kategori (butuh ingest_manage_enabled — LANGSUNG LIVE).
// Tanpa HAPUS di v1 (hindari kategori yatim / artikel terputus).
// Endpoint mesin: auth Bearer token (reuse token ingest), tanpa sesi/CSRF.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/sitemap.php';
require_once __DIR__ . '/../helpers/page-cache.php';
require_once __DIR__ . '/../helpers/article-cta.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo    = getDB();

// ─── GET: daftar kategori ────────────────────────────────────────
if ($method === 'GET') {
    ingestApiAuthorize(false);
    $rows = $pdo->query(
        "SELECT c.id, c.name, c.slug, c.parent_id, c.description,
                (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id) AS article_count
         FROM categories c ORDER BY c.name ASC"
    )->fetchAll();
    $out = array_map(function ($r) {
        $id = (int) $r['id'];
        return [
            'id'            => $id,
            'name'          => $r['name'],
            'slug'          => $r['slug'],
            'parent_id'     => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
            'description'   => $r['description'],
            'article_count' => (int) $r['article_count'],
            'article_cta'   => articleCtaApiOutput(articleCtaGet('category', $id)),
            'effective_article_cta' => articleCtaApiOutput(articleCtaResolve(['category_id' => $id])),
        ];
    }, $rows);
    ingestApiSend(200, ['ok' => true, 'categories' => $out]);
}

// ─── POST: buat/ubah kategori (butuh gate manajemen) ─────────────
if ($method !== 'POST') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Gunakan GET atau POST.']);
}
$ctype = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($ctype, 'application/json') === false) {
    ingestApiSend(415, ['ok' => false, 'error' => 'Content-Type harus application/json.']);
}
ingestApiAuthorize(true);

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    ingestApiSend(400, ['ok' => false, 'error' => 'Body JSON tidak valid.']);
}

$id   = (int) ($in['id'] ?? 0);
$name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 150);
if ($name === '') {
    ingestApiSend(422, ['ok' => false, 'error' => 'Field wajib: name.']);
}
$slugIn = trim((string) ($in['slug'] ?? ''));
$desc   = trim((string) ($in['description'] ?? ''));
$parent = (int) ($in['parent_id'] ?? 0) ?: null;
$categoryCta = null;
if (array_key_exists('article_cta', $in)) {
    try {
        $categoryCta = articleCtaValidateApiPayload($in['article_cta']);
    } catch (InvalidArgumentException $e) {
        ingestApiSend(422, ['ok' => false, 'error' => $e->getMessage()]);
    }
}

// Update: pastikan kategori ada.
$existing = null;
if ($id > 0) {
    $st = $pdo->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    if ($st->fetchColumn() === false) {
        ingestApiSend(404, ['ok' => false, 'error' => 'Kategori (id) tidak ditemukan.']);
    }
    $existing = $id;
}

// Validasi parent: harus ada, dan tolak self-parent.
if ($parent !== null) {
    if ($existing !== null && $parent === $existing) {
        ingestApiSend(422, ['ok' => false, 'error' => 'Kategori tidak boleh menjadi induk dirinya sendiri.']);
    }
    $st = $pdo->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
    $st->execute([$parent]);
    if ($st->fetchColumn() === false) {
        ingestApiSend(422, ['ok' => false, 'error' => 'parent_id tidak ditemukan.']);
    }
}

$slug = uniqueSlug($pdo, 'categories', $slugIn !== '' ? $slugIn : $name, $existing);

try {
    $pdo->beginTransaction();
    if ($existing !== null) {
        $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = ? WHERE id = ?")
            ->execute([$name, $slug, $desc !== '' ? $desc : null, $parent, $existing]);
        $action = 'updated';
    } else {
        $pdo->prepare("INSERT INTO categories (name, slug, description, parent_id) VALUES (?, ?, ?, ?)")
            ->execute([$name, $slug, $desc !== '' ? $desc : null, $parent]);
        $id = (int) $pdo->lastInsertId();
        $action = 'created';
    }
    if ($categoryCta !== null) articleCtaSave('category', $id, $categoryCta);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('api/categories: ' . $e->getMessage());
    ingestLog('500 categories-gagal: ' . $e->getMessage());
    ingestApiSend(500, ['ok' => false, 'error' => 'Gagal menyimpan kategori.']);
}

try {
    scribeGenerateSitemap();
    if ($categoryCta !== null) pageCacheFlushAll();
    else pageCacheInvalidate(['/', '/?page=2', '/kategori/' . $slug]);
} catch (Throwable $e) {
    // Data utama sudah commit; kegagalan cache/sitemap tidak boleh memicu retry
    // yang membuat kategori duplikat.
    error_log('api/categories post-hook: ' . $e->getMessage());
}

ingestLog(($action === 'created' ? 'kategori-dibuat' : 'kategori-diubah') . ' id=' . $id . ' slug=' . $slug . ($categoryCta !== null ? ' cta=1' : ''));
$directCta = $categoryCta ?? articleCtaGet('category', $id);
ingestApiSend(200, [
    'ok' => true, 'id' => $id, 'slug' => $slug, 'action' => $action,
    'article_cta' => articleCtaApiOutput($directCta),
    'effective_article_cta' => articleCtaApiOutput(articleCtaResolve(
        ['category_id' => $id],
        $categoryCta !== null ? ['category' => $categoryCta] : []
    )),
]);
