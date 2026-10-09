<?php
// POST /actions/admin/ai-faq — generate Q&A FAQ penuh (AJAX). Mengembalikan daftar
// {q,a} untuk di-review/approve di client (BELUM disimpan).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/AverionAiAdapter.php';
require_once __DIR__ . '/../../helpers/ai-balance.php';
requireStaff();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Token keamanan tidak valid.']);
    exit;
}

$pdo       = getDB();
$articleId = (int) ($_POST['article_id'] ?? 0);
if ($articleId <= 0) { echo json_encode(['ok' => false, 'message' => 'Artikel belum tersimpan.']); exit; }

$st = $pdo->prepare("SELECT author_id, content, outline_json, focus_keyword, title, language FROM articles WHERE id = ? LIMIT 1");
$st->execute([$articleId]);
$art = $st->fetch();
if (!$art) { echo json_encode(['ok' => false, 'message' => 'Artikel tidak ditemukan.']); exit; }
if (isWriter() && (int) $art['author_id'] !== currentUserId()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bukan artikel Anda.']);
    exit;
}

// Konteks: teks konten (fallback ke ringkasan outline).
$plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $art['content'])));
if ($plain === '') {
    $ol = json_decode((string) $art['outline_json'], true);
    $lines = [(string) ($ol['h1'] ?? '')];
    foreach (($ol['sections'] ?? []) as $s) $lines[] = (string) ($s['h2'] ?? '');
    $plain = trim(implode('. ', array_filter($lines)));
}
if ($plain === '') { echo json_encode(['ok' => false, 'message' => 'Buat outline atau konten dulu.']); exit; }

$keyword = trim((string) ($art['focus_keyword'] ?? '')) ?: trim((string) $art['title']);

$adapter = new AverionAiAdapter();
$payload = ['keyword' => $keyword, 'outline' => $plain, 'content' => $plain,
            'language' => $art['language'] ?: getSetting('default_language', 'id')];
$res = $adapter->generate('faq', $payload);

$charged = $res['ok'] ? (int) ($res['data']['credits_charged'] ?? 0) : 0;
try {
    $pdo->prepare("INSERT INTO seo_ai_runs (article_id, task_type, status, credits_charged, request_payload, result, error_code)
                   VALUES (?, 'faq', ?, ?, ?, ?, ?)")
        ->execute([$articleId, $res['ok'] ? 'success' : 'error', $charged, json_encode(['keyword' => $keyword]),
                   $res['ok'] ? json_encode($res['data']['result'] ?? null) : null,
                   $res['ok'] ? null : ($res['code'] ?? 'ERROR')]);
} catch (Throwable $e) { error_log('faq log: ' . $e->getMessage()); }

if (!$res['ok']) {
    echo json_encode(['ok' => false, 'code' => $res['code'], 'message' => $res['message'], 'flags' => $res['flags']]);
    exit;
}

$faqs = [];
foreach (($res['data']['result']['faqs'] ?? []) as $f) {
    $q = trim((string) ($f['q'] ?? ''));
    $a = trim((string) ($f['a'] ?? ''));
    if ($q !== '') $faqs[] = ['q' => $q, 'a' => $a];
}

$adapter->invalidateBalanceCache();
echo json_encode(['ok' => true, 'faqs' => $faqs, 'credits_charged' => $charged]);
