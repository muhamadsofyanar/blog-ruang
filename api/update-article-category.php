<?php
// ════════════════════════════════════════════════════════════════════════
// Management API — /api/update-article-category.php  (TULIS, butuh ingest_manage)
// Ubah HANYA kolom category_id artikel. Single atau batch. Idempoten.
//   Body single : { "article_id":3, "category_id":2 }
//   Body batch  : { "updates":[ {"article_id":3,"category_id":2}, ... ] }
//   Opsional    : "dry_run": true  (validasi + hitung before/after, TANPA tulis)
// Batch = transaksi all-or-nothing. TIDAK menyentuh title/slug/content/status/
// published_at/author_id/external_ref. Artikel published tetap published.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/sitemap.php';
require_once __DIR__ . '/../helpers/page-cache.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Gunakan POST.']);
}
$ctype = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($ctype, 'application/json') === false) {
    ingestApiSend(415, ['ok' => false, 'error' => 'Content-Type harus application/json.']);
}
ingestApiAuthorize(true);

$pdo = getDB();
$in  = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    ingestApiSend(400, ['ok' => false, 'error' => 'Body JSON tidak valid.']);
}
$dryRun = !empty($in['dry_run']);

// Normalisasi ke daftar update.
$updates = [];
if (isset($in['updates']) && is_array($in['updates'])) {
    $updates = $in['updates'];
} elseif (array_key_exists('article_id', $in) || array_key_exists('category_id', $in)) {
    $updates = [['article_id' => $in['article_id'] ?? null, 'category_id' => $in['category_id'] ?? null]];
}
if (!$updates) {
    ingestApiSend(422, ['ok' => false, 'error' => 'Body harus memuat article_id+category_id atau updates[].']);
}

/** Integer > 0 ketat (tolak string non-numerik, float, bool). */
$posInt = static function ($v): ?int {
    if (is_int($v)) return $v > 0 ? $v : null;
    if (is_string($v) && ctype_digit($v)) { $n = (int) $v; return $n > 0 ? $n : null; }
    return null;
};

// ─── Validasi semua item (kumpulkan yang invalid) ────────────────
$plan = [];
$invalid = [];
$aStmt = $pdo->prepare("SELECT id, slug, category_id FROM articles WHERE id = ? LIMIT 1");
$cStmt = $pdo->prepare("SELECT id, slug FROM categories WHERE id = ? LIMIT 1");
foreach ($updates as $i => $u) {
    $aid = $posInt($u['article_id'] ?? null);
    $cid = $posInt($u['category_id'] ?? null);
    if ($aid === null || $cid === null) {
        $invalid[] = ['index' => $i, 'reason' => 'article_id & category_id wajib integer > 0.'];
        continue;
    }
    $aStmt->execute([$aid]);
    $art = $aStmt->fetch();
    if (!$art) {
        $invalid[] = ['article_id' => $aid, 'reason' => 'Artikel tidak ditemukan.'];
        continue;
    }
    $cStmt->execute([$cid]);
    $cat = $cStmt->fetch();
    if (!$cat) {
        $invalid[] = ['article_id' => $aid, 'category_id' => $cid, 'reason' => 'Kategori tidak ditemukan.'];
        continue;
    }
    $plan[] = [
        'article_id'  => $aid,
        'slug'        => (string) $art['slug'],
        'from'        => $art['category_id'] !== null ? (int) $art['category_id'] : null,
        'to'          => $cid,
        'new_cat'     => (string) $cat['slug'],
    ];
}

if ($invalid) {
    ingestApiSend(422, ['ok' => false, 'error' => 'Validasi gagal — tidak ada yang diubah.', 'invalid' => $invalid]);
}

// Slug kategori lama (untuk invalidasi cache) — sekali query.
$oldCatSlug = [];
$fromIds = array_values(array_unique(array_filter(array_map(fn($p) => $p['from'], $plan), fn($v) => $v !== null)));
if ($fromIds) {
    $ph = implode(',', array_fill(0, count($fromIds), '?'));
    $q = $pdo->prepare("SELECT id, slug FROM categories WHERE id IN ($ph)");
    $q->execute($fromIds);
    foreach ($q->fetchAll() as $r) $oldCatSlug[(int) $r['id']] = (string) $r['slug'];
}

// ─── Terapkan (transaksi all-or-nothing) ─────────────────────────
$results = [];
$artSlugs = [];
$catSlugs = [];
$changedIds = [];
try {
    if (!$dryRun) $pdo->beginTransaction();
    $upd = $pdo->prepare("UPDATE articles SET category_id = ? WHERE id = ?");
    foreach ($plan as $p) {
        $action = ($p['from'] === $p['to']) ? 'unchanged' : 'updated';
        if ($action === 'updated' && !$dryRun) {
            $upd->execute([$p['to'], $p['article_id']]);
            $artSlugs[] = $p['slug'];
            $catSlugs[] = $p['new_cat'];
            if ($p['from'] !== null && isset($oldCatSlug[$p['from']])) $catSlugs[] = $oldCatSlug[$p['from']];
            $changedIds[] = $p['article_id'];
        }
        $results[] = [
            'article_id'       => $p['article_id'],
            'from_category_id' => $p['from'],
            'to_category_id'   => $p['to'],
            'action'           => $action,
        ];
    }
    if (!$dryRun) $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('update-article-category: ' . $e->getMessage());
    ingestLog('500 kategori-artikel-gagal: ' . $e->getMessage());
    ingestApiSend(500, ['ok' => false, 'error' => 'Gagal memperbarui kategori artikel.']);
}

// ─── Invalidasi cache + sitemap (hanya bila benar-benar berubah) ─
if (!$dryRun && $changedIds) {
    try {
        scribeGenerateSitemap();
        $inv = ['/', '/?page=2'];
        foreach (array_unique($artSlugs) as $s) $inv[] = '/artikel/' . $s;
        foreach (array_unique($catSlugs) as $s) $inv[] = '/kategori/' . $s;
        pageCacheInvalidate($inv);
    } catch (Throwable $e) {
        error_log('update-article-category post-hook: ' . $e->getMessage());
    }
    ingestLog('kategori-artikel-diubah ids=' . implode(',', $changedIds));
}

ingestApiSend(200, ['ok' => true, 'dry_run' => $dryRun, 'results' => $results]);
