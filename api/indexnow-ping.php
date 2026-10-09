<?php
// ════════════════════════════════════════════════════════════════════════
// Management API — POST /api/indexnow-ping.php
// Kirim ping verifikasi ke IndexNow agar agen (mis. Hermes) bisa MEMBUKTIKAN
// penerimaan tanpa menerbitkan artikel. Butuh API Manajemen aktif (aksi +
// panggilan eksternal). Body opsional JSON: {"urls":["https://host/..."]} —
// hanya URL di host sendiri yang dikirim (difilter). Default: beranda.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/indexnow.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Gunakan POST.']);
}
ingestApiAuthorize(true); // butuh manajemen

indexnowEnsureKey();

$urls = [url('/')];
$raw  = (string) file_get_contents('php://input');
if (trim($raw) !== '') {
    $in = json_decode($raw, true);
    if (is_array($in) && is_array($in['urls'] ?? null)) {
        $picked = array_values(array_filter(array_map('strval', $in['urls']), static fn($u) => trim($u) !== ''));
        if ($picked) $urls = $picked;
    }
}

$r  = indexnowSubmit($urls, true); // force: boleh untuk verifikasi walau toggle mati
$st = indexnowStatus();

ingestApiSend(200, [
    'ok'       => (bool) $r['ok'],
    'http'     => $r['http'] ?? null,
    'count'    => $r['count'] ?? 0,
    'error'    => $r['ok'] ? null : ($r['error'] ?? null),
    'indexnow' => $st,
]);
