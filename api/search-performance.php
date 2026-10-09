<?php
// ════════════════════════════════════════════════════════════════════════
// Management/Ingest API — GET /api/search-performance.php  (BACA saja)
// Metrik Google Search Console per artikel (klik/impresi/CTR/posisi) dari cache
// lokal. "Mata" untuk agen otomatis: memilih target tulis/optimasi dari data
// nyata, bukan tebakan. Reuse helpers/gsc.php (pencocokan URL→'/artikel/{slug}').
//
// Auth: Bearer token + ingest_enabled (baca). Parameter:
//   ?filter=zero|lowctr|page2   filter aksi (sinkron dengan UI Search Performance)
//   ?limit= (1..200, default 50) &offset=
//   ?refresh=1                  tarik data baru dari Google (butuh API Manajemen;
//                               dibatasi minimal 6 jam sekali agar hemat kuota).
// Endpoint mesin: tanpa sesi/CSRF, diakses sebagai file nyata.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/gsc.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Hanya GET.']);
}
ingestApiAuthorize(false);

if (!gscEnabled() || !gscConfigured()) {
    ingestApiSend(200, [
        'ok' => true, 'enabled' => false,
        'note' => 'Search Performance belum aktif. Atur di Admin → SEO Health → Search Performance.',
        'summary' => null, 'articles' => [], 'total' => 0,
    ]);
}

// ─── Opsional: refresh dari Google (gate manajemen + throttle 6 jam) ──
$refreshInfo = null;
if (in_array((string) ($_GET['refresh'] ?? ''), ['1', 'true', 'yes'], true)) {
    if (!ingestManageEnabled()) {
        ingestApiSend(403, ['ok' => false, 'error' => 'refresh butuh API Manajemen aktif.']);
    }
    $prev = gscCache();
    $age  = time() - (int) $prev['at'];
    if ((int) $prev['at'] > 0 && $age < 6 * 3600) {
        $refreshInfo = ['refreshed' => false, 'reason' => 'throttled', 'age_seconds' => $age];
    } else {
        $r = gscRefreshCache();
        $refreshInfo = $r['ok']
            ? ['refreshed' => true, 'count' => (int) $r['count']]
            : ['refreshed' => false, 'reason' => 'error', 'error' => $r['error']];
    }
}

$filter = trim((string) ($_GET['filter'] ?? ''));
if ($filter !== '' && !in_array($filter, ['zero', 'lowctr', 'page2'], true)) {
    ingestApiSend(422, ['ok' => false, 'error' => 'filter tidak valid. Gunakan zero, lowctr, atau page2.']);
}

$cache   = gscCache();
$metrics = gscMetricsByArticlePath(); // key: '/artikel/{slug}'

$pdo  = getDB();
$rows = $pdo->query("SELECT id, title, slug, status FROM articles")->fetchAll();

$articles = [];
$sumClicks = 0;
$sumImpr   = 0;
$posWSum   = 0.0;
$posWTot   = 0.0;
foreach ($rows as $a) {
    $key = '/artikel/' . $a['slug'];
    if (!isset($metrics[$key])) continue;
    $m = $metrics[$key];
    $clicks = (int) $m['clicks'];
    $impr   = (int) $m['impressions'];
    $ctr    = (float) $m['ctr'];
    $pos    = (float) $m['position'];

    // Filter aksi (sinkron dengan UI Search Performance).
    if ($filter === 'zero'   && $clicks !== 0) continue;
    if ($filter === 'lowctr' && !($impr >= 50 && $ctr < 0.02)) continue;
    if ($filter === 'page2'  && !($pos >= 11 && $pos <= 20)) continue;

    $sumClicks += $clicks;
    $sumImpr   += $impr;
    $w = $impr > 0 ? $impr : 1;
    $posWSum += $pos * $w;
    $posWTot += $w;

    $articles[] = [
        'article_id'  => (int) $a['id'],
        'title'       => $a['title'],
        'slug'        => $a['slug'],
        'status'      => $a['status'],
        'clicks'      => $clicks,
        'impressions' => $impr,
        'ctr'         => round($ctr, 4),
        'position'    => round($pos, 1),
        'edit_url'    => '/admin/articles/' . (int) $a['id'],
        'public_url'  => $a['status'] === 'published' ? '/artikel/' . $a['slug'] : null,
    ];
}

usort($articles, static fn(array $x, array $y): int => $y['impressions'] <=> $x['impressions']);

$limit  = max(1, min(200, (int) ($_GET['limit'] ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$total  = count($articles);
$page   = array_slice($articles, $offset, $limit);

ingestApiSend(200, [
    'ok' => true, 'enabled' => true,
    'range' => [
        'days'      => (int) $cache['range'],
        'start'     => $cache['start'] !== '' ? $cache['start'] : null,
        'end'       => $cache['end'] !== '' ? $cache['end'] : null,
        'cached_at' => (int) $cache['at'] > 0 ? date('c', (int) $cache['at']) : null,
        'stale'     => (int) $cache['at'] > 0 ? (time() - (int) $cache['at'] > 86400) : true,
    ],
    'summary' => [
        'articles_with_data' => $total,
        'clicks'             => $sumClicks,
        'impressions'        => $sumImpr,
        'ctr'                => $sumImpr > 0 ? round($sumClicks / $sumImpr, 4) : 0.0,
        'avg_position'       => $posWTot > 0 ? round($posWSum / $posWTot, 1) : 0.0,
    ],
    'filter'            => $filter !== '' ? $filter : null,
    'filters_available' => ['zero', 'lowctr', 'page2'],
    'articles' => $page,
    'limit' => $limit, 'offset' => $offset, 'total' => $total,
    'refresh' => $refreshInfo,
]);
