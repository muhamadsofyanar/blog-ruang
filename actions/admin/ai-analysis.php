<?php
// POST /actions/admin/ai-analysis — Analisis AI berbayar (AJAX). Simpan hasil ke
// seo_ai_analysis + seo_ai_keywords. prompt_version=NULL (versi prompt hanya
// tercatat di gateway ls_ai_logs); ruleset_version = versi ruleset lokal aktif.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/AverionAiAdapter.php';
require_once __DIR__ . '/../../helpers/ai-balance.php';
require_once __DIR__ . '/../../helpers/seo-rules.php';
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

$st = $pdo->prepare("SELECT author_id, content, focus_keyword, title, language FROM articles WHERE id = ? LIMIT 1");
$st->execute([$articleId]);
$art = $st->fetch();
if (!$art) { echo json_encode(['ok' => false, 'message' => 'Artikel tidak ditemukan.']); exit; }
if (isWriter() && (int) $art['author_id'] !== currentUserId()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bukan artikel Anda.']);
    exit;
}

$plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $art['content'])));
if ($plain === '') { echo json_encode(['ok' => false, 'message' => 'Tulis atau generate konten dulu.']); exit; }
$wordCount = count(preg_split('/\s+/', $plain));
$keyword   = trim((string) ($art['focus_keyword'] ?? '')) ?: trim((string) $art['title']);

$adapter = new AverionAiAdapter();
$payload = ['content' => $plain, 'keyword' => $keyword, 'language' => $art['language'] ?: getSetting('default_language', 'id')];
$res = $adapter->generate('analysis', $payload);

$charged = $res['ok'] ? (int) ($res['data']['credits_charged'] ?? 0) : 0;
try {
    $pdo->prepare("INSERT INTO seo_ai_runs (article_id, task_type, status, credits_charged, request_payload, result, error_code)
                   VALUES (?, 'analysis', ?, ?, ?, ?, ?)")
        ->execute([$articleId, $res['ok'] ? 'success' : 'error', $charged, json_encode(['keyword' => $keyword]),
                   $res['ok'] ? json_encode($res['data']['result'] ?? null) : null,
                   $res['ok'] ? null : ($res['code'] ?? 'ERROR')]);
} catch (Throwable $e) { error_log('analysis log: ' . $e->getMessage()); }

if (!$res['ok']) {
    echo json_encode(['ok' => false, 'code' => $res['code'], 'message' => $res['message'], 'flags' => $res['flags']]);
    exit;
}

$result      = $res['data']['result'] ?? [];
$score       = isset($result['score']) ? (int) $result['score'] : null;
$issues      = is_array($result['issues'] ?? null) ? array_map('strval', $result['issues']) : [];
$suggestions = is_array($result['suggestions'] ?? null) ? array_map('strval', $result['suggestions']) : [];
$keywords    = is_array($result['keywords'] ?? null) ? $result['keywords'] : [];

// ruleset_version aktif (dari cache/bundle) — tanpa memaksa refresh.
$rv = (int) (scribeGetRuleset(false)['version'] ?? 0);

try {
    $pdo->prepare("INSERT INTO seo_ai_analysis (article_id, score, issues, suggestions, keyword_coverage, prompt_version, ruleset_version)
                   VALUES (?, ?, ?, ?, ?, NULL, ?)")
        ->execute([$articleId, $score, json_encode($issues), json_encode($suggestions),
                   json_encode(['word_count' => $wordCount, 'keywords' => $keywords]), $rv]);

    // seo_ai_keywords — segarkan dari hasil analisis.
    $pdo->prepare("DELETE FROM seo_ai_keywords WHERE article_id = ?")->execute([$articleId]);
    $ins = $pdo->prepare("INSERT INTO seo_ai_keywords (article_id, keyword, keyword_type, is_selected) VALUES (?, ?, 'related', 0)");
    foreach ($keywords as $k) {
        $kw = trim((string) ($k['keyword'] ?? ''));
        if ($kw !== '') $ins->execute([$articleId, $kw]);
    }
} catch (Throwable $e) { error_log('analysis save: ' . $e->getMessage()); }

$adapter->invalidateBalanceCache();

echo json_encode([
    'ok'              => true,
    'score'           => $score,
    'issues'          => $issues,
    'suggestions'     => $suggestions,
    'keywords'        => $keywords,
    'word_count'      => $wordCount,
    'ruleset_version' => $rv,
    'credits_charged' => $charged,
]);
