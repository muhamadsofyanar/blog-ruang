<?php
// POST /actions/admin/ai-meta — generate meta_title + meta_description (AJAX).
// Mengembalikan nilai untuk DIISI ke field (TIDAK menyimpan — user melihat dulu).
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

$st = $pdo->prepare("SELECT author_id, title, content, focus_keyword, language FROM articles WHERE id = ? LIMIT 1");
$st->execute([$articleId]);
$art = $st->fetch();
if (!$art) { echo json_encode(['ok' => false, 'message' => 'Artikel tidak ditemukan.']); exit; }
if (isWriter() && (int) $art['author_id'] !== currentUserId()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bukan artikel Anda.']);
    exit;
}

// Excerpt ~500 char teks bersih dari konten.
$plain   = trim(preg_replace('/\s+/', ' ', strip_tags((string) $art['content'])));
$excerpt = mb_substr($plain, 0, 500);
$keyword = trim((string) ($art['focus_keyword'] ?? '')) ?: trim((string) $art['title']);
if ($excerpt === '') {
    echo json_encode(['ok' => false, 'message' => 'Tulis atau generate konten dulu sebelum membuat meta.']);
    exit;
}

$adapter = new AverionAiAdapter();
$payload = ['title' => (string) $art['title'], 'content' => $excerpt, 'keyword' => $keyword,
            'language' => $art['language'] ?: getSetting('default_language', 'id')];
$res = $adapter->generate('meta', $payload);

$charged = $res['ok'] ? (int) ($res['data']['credits_charged'] ?? 0) : 0;
try {
    $pdo->prepare("INSERT INTO seo_ai_runs (article_id, task_type, status, credits_charged, request_payload, result, error_code)
                   VALUES (?, 'meta', ?, ?, ?, ?, ?)")
        ->execute([$articleId, $res['ok'] ? 'success' : 'error', $charged, json_encode(['keyword' => $keyword]),
                   $res['ok'] ? json_encode($res['data']['result'] ?? null) : null,
                   $res['ok'] ? null : ($res['code'] ?? 'ERROR')]);
} catch (Throwable $e) { error_log('meta log: ' . $e->getMessage()); }

if (!$res['ok']) {
    echo json_encode(['ok' => false, 'code' => $res['code'], 'message' => $res['message'], 'flags' => $res['flags']]);
    exit;
}

$adapter->invalidateBalanceCache();
$r = $res['data']['result'] ?? [];
echo json_encode([
    'ok'               => true,
    'meta_title'       => (string) ($r['meta_title'] ?? ''),
    'meta_description' => (string) ($r['meta_description'] ?? ''),
    'credits_charged'  => $charged,
]);
