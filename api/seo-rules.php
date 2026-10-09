<?php
// ════════════════════════════════════════════════════════════════════════
// Management API — /api/seo-rules.php  (BACA saja)
//   GET : ruleset SEO aktif (scribeGetRuleset) agar penulis eksternal bisa
//         menyelaraskan artikel ke checklist. Butuh ingest_enabled.
// TANPA endpoint ubah ruleset di v1. Endpoint mesin: Bearer token.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/seo-rules.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Hanya GET.']);
}
ingestApiAuthorize(false);

$ruleset = scribeGetRuleset(false); // cache segar → remote → bundle (self-heal)

ingestApiSend(200, [
    'ok' => true,
    'ruleset' => [
        'version' => $ruleset['version'] ?? 0,
        'source'  => $ruleset['source'] ?? 'unknown',
        'rules'   => array_map(fn($r) => [
            'id'     => $r['id'] ?? '',
            'label'  => $r['label'] ?? '',
            'weight' => (int) ($r['weight'] ?? 0),
        ], $ruleset['rules'] ?? []),
    ],
]);
