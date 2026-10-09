<?php
// ════════════════════════════════════════════════════════════════════════
// Ingest API — GET /api/content-refresh.php?limit=&offset=  (BACA saja)
// Antrean "Perlu Diperbarui": artikel published berprioritas untuk dioptimasi,
// gabungan sinyal GSC + umur + skor kesehatan. Agen (Hermes) ambil daftar ini
// lalu perbaiki via POST /api/content-health.php. Butuh ingest_enabled.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/content-refresh.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Hanya GET.']);
}
ingestApiAuthorize(false);
$pdo = getDB();

$limit  = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));

$q = contentRefreshQueue($pdo, $limit, $offset);
ingestApiSend(200, [
    'ok'       => true,
    'total'    => $q['total'],
    'has_gsc'  => $q['has_gsc'],
    'has_prev' => $q['has_prev'],
    'items'    => $q['items'],
    'limit'    => $limit,
    'offset'   => $offset,
    'note'     => 'Prioritas gabungan GSC + umur + skor. Perbaiki via GET+POST /api/content-health.php lalu publish (skor >= 80).',
]);
