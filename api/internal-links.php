<?php
// ════════════════════════════════════════════════════════════════════════
// Ingest API — GET /api/internal-links.php?article_id=N&max=5  (BACA saja)
// Saran internal link untuk sebuah artikel: artikel published lain yang
// topiknya DISEBUT di konten → {anchor, url} agar agen (mis. Hermes) menautkan
// sendiri saat menulis/optimasi. LOKAL & GRATIS (tanpa kredit). Butuh ingest_enabled.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/internal-links.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Hanya GET.']);
}
ingestApiAuthorize(false);
$pdo = getDB();

$id = (int) ($_GET['article_id'] ?? 0);
if ($id < 1) ingestApiSend(422, ['ok' => false, 'error' => 'article_id wajib integer > 0.']);
$max = max(1, min(10, (int) ($_GET['max'] ?? 5)));

$st = $pdo->prepare("SELECT id, title, content, focus_keyword, category_id FROM articles WHERE id = ? LIMIT 1");
$st->execute([$id]);
$art = $st->fetch();
if (!$art) ingestApiSend(404, ['ok' => false, 'error' => 'Artikel tidak ditemukan.']);

$sug = internalLinkSuggest($pdo, $art, $max);
ingestApiSend(200, [
    'ok' => true, 'article_id' => $id,
    'suggestions' => $sug, 'count' => count($sug),
    'note' => 'Tautkan anchor ke url pada kemunculan pertama di konten (hindari heading & teks yang sudah bertaut).',
]);
