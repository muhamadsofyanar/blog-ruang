<?php
// POST /actions/admin/ai-internal-links — saran/sisip internal link (AJAX).
// LOKAL & GRATIS (tanpa kredit). Mode:
//   default            → kembalikan daftar saran {anchor,url,title,...}
//   apply=<json items> → sisipkan {anchor,url} terpilih ke content_html, balikkan HTML baru.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/internal-links.php';
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

$st = $pdo->prepare("SELECT author_id, title, focus_keyword, category_id FROM articles WHERE id = ? LIMIT 1");
$st->execute([$articleId]);
$art = $st->fetch();
if (!$art) { echo json_encode(['ok' => false, 'message' => 'Artikel tidak ditemukan.']); exit; }
if (isWriter() && (int) $art['author_id'] !== currentUserId()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bukan artikel Anda.']);
    exit;
}

// Konten dari editor (belum tentu tersimpan) — sanitasi dulu.
$html = sanitizeArticleHtml((string) ($_POST['content_html'] ?? ''));

// ─── Mode APPLY: sisipkan tautan terpilih ────────────────────────
$apply = (string) ($_POST['apply'] ?? '');
if ($apply !== '') {
    $items = json_decode($apply, true);
    if (!is_array($items)) { echo json_encode(['ok' => false, 'message' => 'Data pilihan tidak valid.']); exit; }
    $selfHost = (string) parse_url(APP_URL, PHP_URL_HOST);
    $clean = [];
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $anchor = trim((string) ($it['anchor'] ?? ''));
        $url    = trim((string) ($it['url'] ?? ''));
        if ($anchor === '' || $url === '') continue;
        // KEAMANAN: hanya izinkan URL internal (host sendiri) — cegah sisip link asing.
        if (strcasecmp((string) parse_url($url, PHP_URL_HOST), $selfHost) !== 0) continue;
        $clean[] = ['anchor' => $anchor, 'url' => $url];
    }
    $res = internalLinkInsert($html, $clean, 10);
    echo json_encode(['ok' => true, 'content_html' => $res['html'], 'inserted' => $res['inserted'], 'used' => $res['used']]);
    exit;
}

// ─── Mode SUGGEST (default) ───────────────────────────────────────
$max = max(1, min(10, (int) ($_POST['max'] ?? 5)));
$current = [
    'id' => $articleId, 'content' => $html,
    'focus_keyword' => $art['focus_keyword'], 'title' => $art['title'],
    'category_id' => $art['category_id'],
];
$sug = internalLinkSuggest($pdo, $current, $max);
echo json_encode(['ok' => true, 'suggestions' => $sug, 'count' => count($sug)]);
