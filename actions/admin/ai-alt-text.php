<?php
// POST /actions/admin/ai-alt-text — isi alt text gambar yang kosong (AJAX).
// LOKAL & GRATIS (tanpa kredit). Menerima content_html dari editor, mengisi alt
// yang kosong, mengembalikan HTML baru + jumlah terisi.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/alt-text.php';
require_once __DIR__ . '/../../helpers/sanitize.php';
requireStaff();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Token keamanan tidak valid.']);
    exit;
}

$pdo       = getDB();
$articleId = (int) ($_POST['article_id'] ?? 0);
if ($articleId <= 0) { echo json_encode(['ok' => false, 'message' => 'Simpan artikel dulu.']); exit; }

$st = $pdo->prepare("SELECT author_id, title, focus_keyword FROM articles WHERE id = ? LIMIT 1");
$st->execute([$articleId]);
$art = $st->fetch();
if (!$art) { echo json_encode(['ok' => false, 'message' => 'Artikel tidak ditemukan.']); exit; }
if (isWriter() && (int) $art['author_id'] !== currentUserId()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bukan artikel Anda.']);
    exit;
}

$html = sanitizeArticleHtml((string) ($_POST['content_html'] ?? ''));
$r = altTextFill($html, ['focus_keyword' => $art['focus_keyword'], 'title' => $art['title']]);

echo json_encode(['ok' => true, 'content_html' => $r['html'], 'filled' => $r['filled']]);
