<?php
// ════════════════════════════════════════════════════════════════════════
// Ingest API — GET /api/capabilities.php  (BACA saja)
// Discovery: apa yang aktif & batas operasi, agar agen otomatis (mis. Hermes)
// bisa menyetel diri sendiri (idempoten) tanpa hardcode. Sekaligus health-check
// token. Auth: Bearer token + ingest_enabled.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/gsc.php';
require_once __DIR__ . '/../helpers/seo-rules.php';
require_once __DIR__ . '/../helpers/indexnow.php';
require_once __DIR__ . '/../helpers/stock-images.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Hanya GET.']);
}
ingestApiAuthorize(false); // token valid + ingest_enabled (lolos = ingest aktif)

$manage   = ingestManageEnabled();
$gscOn    = gscEnabled() && gscConfigured();
$ruleset  = scribeGetRuleset(false);
$cache    = $gscOn ? gscCache() : null;
$inStatus = indexnowStatus();

ingestApiSend(200, [
    'ok' => true,
    'app_version' => APP_VERSION,
    'api_version' => '1',
    'capabilities' => [
        'ingest'             => true,
        'manage'             => $manage,
        'publish_on_ingest'  => $manage,
        'optimize'           => $manage,
        'search_performance' => $gscOn,
        'internal_links'     => true,
        'indexnow'           => $inStatus['enabled'],
        'images'             => ingestImagesEnabled() && stockEnabled(),
    ],
    'publish_min_score' => 80,
    'ruleset_version'   => (int) ($ruleset['version'] ?? 0),
    'search_intents'    => INGEST_SEARCH_INTENTS,
    'search_performance' => $gscOn ? [
        'range_days' => (int) $cache['range'],
        'start'      => $cache['start'] !== '' ? $cache['start'] : null,
        'end'        => $cache['end'] !== '' ? $cache['end'] : null,
        'cached_at'  => (int) $cache['at'] > 0 ? date('c', (int) $cache['at']) : null,
    ] : null,
    // Gambar otomatis via Ingest: provider legal, cover + inline (unduh→WebP lokal).
    'images' => [
        'enabled'     => ingestImagesEnabled(),
        'provider'    => 'pexels',
        'configured'  => stockEnabled(),
        'cover_ratio' => '1200x630',
        'max_inline'  => STOCK_MAX_INLINE,
        'cover_modes' => ['auto', 'url', 'none'],
        'image_modes' => ['auto', 'manual', 'none'],
        'no_people'   => true,          // disaring berbasis teks alt (tolak manusia)
        'no_logo'     => true,          // tolak logo/merek
        'relevance_scored' => true,     // skor relevansi + ambang
        'query_normalized' => true,     // judul/H2 ID → query visual Inggris
    ],
    // Observability IndexNow: agen bisa memverifikasi ping terakhir tanpa publish.
    'indexnow' => [
        'enabled'      => $inStatus['enabled'],
        'configured'   => $inStatus['configured'],
        'key_location' => $inStatus['key_location'],
        'last_ping_at' => $inStatus['last_ping_at'],
        'last_status'  => $inStatus['last_status'],
        'last_url'     => $inStatus['last_url'],
    ],
    'endpoints' => [
        'capabilities'       => '/api/capabilities.php',
        'ingest'             => '/api/ingest-article.php',
        'articles'           => '/api/articles.php',
        'content_health'     => '/api/content-health.php',
        'categories'         => '/api/categories.php',
        'seo_rules'          => '/api/seo-rules.php',
        'settings'           => '/api/settings.php',
        'search_performance' => '/api/search-performance.php',
        'internal_links'     => '/api/internal-links.php',
        'content_refresh'    => '/api/content-refresh.php',
        'indexnow_ping'      => '/api/indexnow-ping.php',
    ],
]);
