<?php
// POST /actions/admin/faq-save — simpan daftar FAQ (approve/edit/hapus) + refresh
// blok FAQ di konten. items = JSON [{q,a,approved}]. Approved masuk seo_ai_faq
// (approved=1) DAN blok <section data-faq-block="1"> di akhir konten (idempotent).
require_once __DIR__ . '/../../bootstrap.php';
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
if ($articleId <= 0) { echo json_encode(['ok' => false, 'message' => 'Artikel belum tersimpan.']); exit; }

$st = $pdo->prepare("SELECT author_id, content FROM articles WHERE id = ? LIMIT 1");
$st->execute([$articleId]);
$art = $st->fetch();
if (!$art) { echo json_encode(['ok' => false, 'message' => 'Artikel tidak ditemukan.']); exit; }
if (isWriter() && (int) $art['author_id'] !== currentUserId()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bukan artikel Anda.']);
    exit;
}

$items = json_decode((string) ($_POST['items'] ?? '[]'), true);
if (!is_array($items)) $items = [];

try {
    $pdo->beginTransaction();

    // Reset lalu simpan ulang seluruh daftar FAQ artikel.
    $pdo->prepare("DELETE FROM seo_ai_faq WHERE article_id = ?")->execute([$articleId]);
    $ins = $pdo->prepare("INSERT INTO seo_ai_faq (article_id, question, answer, approved, sort_order) VALUES (?, ?, ?, ?, ?)");

    $approvedHtml = '';
    $order = 0;
    foreach ($items as $it) {
        $q = trim((string) ($it['q'] ?? ''));
        $a = trim((string) ($it['a'] ?? ''));
        if ($q === '') continue;
        $approved = !empty($it['approved']) ? 1 : 0;
        $ins->execute([$articleId, $q, $a, $approved, $order++]);
        if ($approved) {
            // Q sebagai H3, A sebagai paragraf — nilai user di-escape (DATA).
            $approvedHtml .= '<h3>' . e($q) . '</h3><p>' . e($a) . '</p>';
        }
    }

    // Rakit blok FAQ (bila ada approved) + sanitasi, lalu refresh di konten.
    $faqInner = $approvedHtml !== '' ? sanitizeArticleHtml('<h2>FAQ</h2>' . $approvedHtml) : '';
    $newContent = faqUpsertBlock((string) $art['content'], $faqInner);
    $pdo->prepare("UPDATE articles SET content = ? WHERE id = ?")->execute([$newContent, $articleId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('faq-save: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Gagal menyimpan FAQ.']);
    exit;
}

// Kembalikan inner blok FAQ (tersanitasi) agar editor bisa disinkronkan client-side.
echo json_encode(['ok' => true, 'faq_block' => $faqInner]);
