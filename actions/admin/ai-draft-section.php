<?php
// POST /actions/admin/ai-draft-section — tulis SATU seksi H2 (AJAX). Outline diambil
// dari DB (sumber kebenaran server, BUKAN body). Hasil disanitasi lalu di-upsert ke
// articles.content dalam <section data-outline-idx="N"> (idempotent + terurut).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/AverionAiAdapter.php';
require_once __DIR__ . '/../../helpers/ai-balance.php';
require_once __DIR__ . '/../../helpers/sanitize.php';
require_once __DIR__ . '/../../helpers/draft.php';
requireStaff();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Token keamanan tidak valid.']);
    exit;
}

$pdo       = getDB();
$articleId = (int) ($_POST['article_id'] ?? 0);
$idx       = (int) ($_POST['section_index'] ?? -1);

if ($articleId <= 0) { echo json_encode(['ok' => false, 'message' => 'Artikel belum tersimpan.']); exit; }

$st = $pdo->prepare("SELECT author_id, outline_json, content, status, focus_keyword, language FROM articles WHERE id = ? LIMIT 1");
$st->execute([$articleId]);
$art = $st->fetch();
if (!$art) { echo json_encode(['ok' => false, 'message' => 'Artikel tidak ditemukan.']); exit; }
if (isWriter() && (int) $art['author_id'] !== currentUserId()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bukan artikel Anda.']);
    exit;
}

$outline = json_decode((string) $art['outline_json'], true);
$sections = is_array($outline['sections'] ?? null) ? $outline['sections'] : [];
if ($idx < 0 || $idx >= count($sections)) {
    echo json_encode(['ok' => false, 'message' => 'Indeks seksi tidak valid.']);
    exit;
}

$section = $sections[$idx];
$h2  = trim((string) ($section['h2'] ?? ''));
$h3s = is_array($section['h3s'] ?? null) ? array_map('strval', $section['h3s']) : [];

$keyword = trim((string) ($art['focus_keyword'] ?? '')) ?: trim((string) ($outline['h1'] ?? ''));
if ($keyword === '') {
    echo json_encode(['ok' => false, 'message' => 'Isi Focus Keyword dulu sebelum menulis draft.']);
    exit;
}

// Susun teks outline penuh + target seksi (konteks untuk AI).
$outlineFull = 'H1: ' . (string) ($outline['h1'] ?? '') . "\n";
foreach ($sections as $i => $s) {
    $outlineFull .= ($i + 1) . '. ' . (string) ($s['h2'] ?? '') . "\n";
    foreach (($s['h3s'] ?? []) as $h3) $outlineFull .= '   - ' . (string) $h3 . "\n";
}
$sectionTarget = $h2 . ($h3s ? (': ' . implode(', ', $h3s)) : '');

$adapter = new AverionAiAdapter();
$payload = [
    'keyword'        => $keyword,
    'outline'        => $outlineFull,
    'section_target' => $sectionTarget,
    'language'       => $art['language'] ?: getSetting('default_language', 'id'),
];
$res = $adapter->generate('draft_section', $payload);

$charged = $res['ok'] ? (int) ($res['data']['credits_charged'] ?? 0) : 0;
$rawHtml = $res['ok'] ? (string) ($res['data']['result']['html'] ?? '') : '';

// Log run.
try {
    $pdo->prepare("INSERT INTO seo_ai_runs (article_id, task_type, status, credits_charged, request_payload, result, error_code)
                   VALUES (?, 'draft_section', ?, ?, ?, ?, ?)")
        ->execute([$articleId, $res['ok'] ? 'success' : 'error', $charged,
                   json_encode(['section_index' => $idx, 'h2' => $h2]),
                   $res['ok'] ? json_encode($res['data']['result'] ?? null) : null,
                   $res['ok'] ? null : ($res['code'] ?? 'ERROR')]);
} catch (Throwable $e) { error_log('draft log: ' . $e->getMessage()); }

if (!$res['ok']) {
    echo json_encode(['ok' => false, 'code' => $res['code'], 'message' => $res['message'], 'flags' => $res['flags'], 'section_index' => $idx]);
    exit;
}

// Bungkus dengan heading H2 + sanitasi (whitelist §6.4) SAAT SIMPAN.
$innerHtml = '<h2>' . e($h2) . '</h2>' . $rawHtml;
$innerHtml = sanitizeArticleHtml($innerHtml);

// Upsert ke content (idempotent + terurut) lalu simpan.
$newContent = draftUpsertSection((string) $art['content'], $idx, $innerHtml);
$pdo->prepare("UPDATE articles SET content = ? WHERE id = ?")->execute([$newContent, $articleId]);

// Status draft_ai bila konten awal kosong (tak ada prosa manual di luar seksi).
if ($art['status'] === 'draft' && !draftHasManualContent($newContent)) {
    $pdo->prepare("UPDATE articles SET status = 'draft_ai' WHERE id = ?")->execute([$articleId]);
}

// Saldo realtime.
$adapter->invalidateBalanceCache();
$bal = $adapter->getBalanceCached(true);

echo json_encode([
    'ok'              => true,
    'section_index'   => $idx,
    'h2'              => $h2,
    'html'            => $innerHtml, // sudah tersanitasi server → aman dipakai innerHTML di client
    'credits_charged' => $charged,
    'balance'         => $bal['ok'] ? $bal['data'] : null,
]);
