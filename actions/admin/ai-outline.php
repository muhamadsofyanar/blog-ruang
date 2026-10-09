<?php
// POST /actions/admin/ai-outline — task outline (AJAX). Simpan ke seo_ai_runs,
// invalidate cache saldo, kembalikan struktur outline + saldo baru.
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
$keyword   = trim($_POST['keyword'] ?? '');
$related   = trim($_POST['related_keywords'] ?? '');
$intent    = $_POST['intent'] ?? '';
$language  = isValidLanguage($_POST['language'] ?? '') ? $_POST['language'] : getSetting('default_language', 'id');
if (!in_array($intent, ['informational', 'transactional', 'navigational'], true)) $intent = '';

if ($articleId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Simpan artikel terlebih dahulu sebelum memakai AI Assist.']);
    exit;
}
if ($keyword === '') {
    echo json_encode(['ok' => false, 'message' => 'Isi focus keyword terlebih dahulu.']);
    exit;
}

$st = $pdo->prepare("SELECT author_id FROM articles WHERE id = ? LIMIT 1");
$st->execute([$articleId]);
$row = $st->fetch();
if (!$row) { echo json_encode(['ok' => false, 'message' => 'Artikel tidak ditemukan.']); exit; }
if (isWriter() && (int) $row['author_id'] !== currentUserId()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bukan artikel Anda.']);
    exit;
}

$adapter = new AverionAiAdapter();
$payload = ['keyword' => $keyword, 'related_keywords' => $related, 'intent' => $intent, 'language' => $language];
$res = $adapter->generate('outline', $payload);

$result  = $res['ok'] ? ($res['data']['result'] ?? null) : null;
$charged = $res['ok'] ? (int) ($res['data']['credits_charged'] ?? 0) : 0;
try {
    $pdo->prepare(
        "INSERT INTO seo_ai_runs (article_id, task_type, status, credits_charged, request_payload, result, error_code)
         VALUES (?, 'outline', ?, ?, ?, ?, ?)"
    )->execute([
        $articleId, $res['ok'] ? 'success' : 'error', $charged,
        json_encode($payload), $result !== null ? json_encode($result) : null,
        $res['ok'] ? null : ($res['code'] ?? 'ERROR'),
    ]);
} catch (Throwable $e) {
    error_log('ai-outline log: ' . $e->getMessage());
}

if (!$res['ok']) {
    echo json_encode(['ok' => false, 'code' => $res['code'], 'message' => $res['message'], 'flags' => $res['flags']]);
    exit;
}

$adapter->invalidateBalanceCache();
$bal = $adapter->getBalanceCached(true);

echo json_encode([
    'ok'              => true,
    'result'          => $result,
    'credits_charged' => $charged,
    'balance'         => $bal['ok'] ? $bal['data'] : null,
]);
