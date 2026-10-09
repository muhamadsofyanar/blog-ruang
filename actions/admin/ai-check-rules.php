<?php
// POST /actions/admin/ai-check-rules — SEO score lapis lokal (AJAX). TANPA gateway
// = TANPA kredit. Menilai konten + meta yang dikirim client (nilai editor terkini).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/seo-rules.php';
requireStaff();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Token keamanan tidak valid.']);
    exit;
}

$articleId = (int) ($_POST['article_id'] ?? 0);
if ($articleId > 0) {
    $st = getDB()->prepare("SELECT author_id FROM articles WHERE id = ? LIMIT 1");
    $st->execute([$articleId]);
    $row = $st->fetch();
    if ($row && isWriter() && (int) $row['author_id'] !== currentUserId()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Bukan artikel Anda.']);
        exit;
    }
}

$force   = (int) ($_POST['refresh'] ?? 0) === 1;
$ruleset = scribeGetRuleset($force);

$ctx = [
    'content'          => (string) ($_POST['content'] ?? ''),
    'meta_title'       => (string) ($_POST['meta_title'] ?? ''),
    'meta_description' => (string) ($_POST['meta_description'] ?? ''),
    'focus_keyword'    => (string) ($_POST['focus_keyword'] ?? ''),
    'title'            => (string) ($_POST['title'] ?? ''),
];
$result = scribeRunRules($ruleset, $ctx);

echo json_encode([
    'ok'              => true,
    'score'           => $result['score'],
    'checks'          => $result['checks'],
    'word_count'      => $result['word_count'],
    'ruleset_version' => (int) ($ruleset['version'] ?? 0),
    'ruleset_source'  => $ruleset['source'] ?? 'bundle',
]);
