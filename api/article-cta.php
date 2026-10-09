<?php
// Management API CTA artikel/kategori/global.
// GET  : baca CTA langsung + hasil pewarisan (cukup ingest_enabled).
// POST : ubah CTA, termasuk dry_run (wajib ingest_manage_enabled).
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/article-cta.php';
require_once __DIR__ . '/../helpers/page-cache.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Gunakan GET atau POST.']);
}
if ($method === 'POST') {
    $ctype = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    if (stripos($ctype, 'application/json') === false) {
        ingestApiSend(415, ['ok' => false, 'error' => 'Content-Type harus application/json.']);
    }
}
ingestApiAuthorize($method === 'POST');

$in = $method === 'POST'
    ? json_decode((string) file_get_contents('php://input'), true)
    : $_GET;
if (!is_array($in)) ingestApiSend(400, ['ok' => false, 'error' => 'Body JSON tidak valid.']);

$scope = is_string($in['scope'] ?? null) ? strtolower(trim($in['scope'])) : '';
if (!in_array($scope, ['global', 'category', 'article'], true)) {
    ingestApiSend(422, ['ok' => false, 'error' => 'scope wajib: global, category, atau article.']);
}
$id = 0;
if ($scope !== 'global') {
    $rawId = $in['id'] ?? null;
    if (is_int($rawId)) $id = $rawId;
    elseif (is_string($rawId) && ctype_digit($rawId)) $id = (int) $rawId;
    if ($id < 1) ingestApiSend(422, ['ok' => false, 'error' => 'id wajib integer > 0 untuk scope category/article.']);
}

$pdo = getDB();
$target = ['id' => $id, 'category_id' => 0, 'slug' => null, 'status' => null];
if ($scope === 'category') {
    $st = $pdo->prepare('SELECT id, slug FROM categories WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) ingestApiSend(404, ['ok' => false, 'error' => 'Kategori tidak ditemukan.']);
    $target['category_id'] = $id;
    $target['slug'] = (string) $row['slug'];
} elseif ($scope === 'article') {
    $st = $pdo->prepare('SELECT id, slug, category_id, status FROM articles WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) ingestApiSend(404, ['ok' => false, 'error' => 'Artikel tidak ditemukan.']);
    $target = [
        'id' => (int) $row['id'], 'slug' => (string) $row['slug'],
        'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : 0,
        'status' => (string) $row['status'],
    ];
}

$direct = articleCtaGet($scope, $id);
$effective = articleCtaResolve($target);
if ($method === 'GET') {
    ingestApiSend(200, [
        'ok' => true, 'scope' => $scope, 'id' => $scope === 'global' ? null : $id,
        'article_cta' => articleCtaApiOutput($direct),
        'effective_article_cta' => articleCtaApiOutput($effective),
    ]);
}

try {
    $after = articleCtaValidateApiPayload($in['article_cta'] ?? null);
} catch (InvalidArgumentException $e) {
    ingestApiSend(422, ['ok' => false, 'error' => $e->getMessage()]);
}
$dryRun = !empty($in['dry_run']);
$changed = $direct !== $after;

if (!$dryRun && $changed) {
    try {
        articleCtaSave($scope, $id, $after);
        ingestLog('cta-diubah scope=' . $scope . ($scope === 'global' ? '' : ' id=' . $id));
    } catch (Throwable $e) {
        error_log('api/article-cta: ' . $e->getMessage());
        ingestLog('500 cta-gagal scope=' . $scope . ($scope === 'global' ? '' : ' id=' . $id));
        ingestApiSend(500, ['ok' => false, 'error' => 'Gagal menyimpan CTA.']);
    }
    try {
        if ($scope === 'article') pageCacheInvalidate(['/artikel/' . $target['slug']]);
        else pageCacheFlushAll();
    } catch (Throwable $e) {
        // Setting sudah tersimpan; cache punya TTL dan kegagalan invalidasi tidak
        // boleh dilaporkan sebagai kegagalan write yang memicu retry.
        error_log('api/article-cta post-hook: ' . $e->getMessage());
    }
}

$effectiveAfter = articleCtaResolve($target, [$scope => $after]);
ingestApiSend(200, [
    'ok' => true, 'dry_run' => $dryRun, 'changed' => $changed,
    'scope' => $scope, 'id' => $scope === 'global' ? null : $id,
    'before' => articleCtaApiOutput($direct),
    'after' => articleCtaApiOutput($after),
    'effective_article_cta' => articleCtaApiOutput($effectiveAfter),
    'warning' => $dryRun
        ? 'Dry-run: tidak ada perubahan yang disimpan.'
        : ($scope === 'article' && $target['status'] !== 'published'
            ? 'CTA tersimpan, tetapi baru tampil setelah artikel dipublikasikan.'
            : 'Perubahan CTA langsung aktif pada halaman publik terkait.'),
]);
