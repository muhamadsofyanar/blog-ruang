<?php
// POST /actions/admin/autosave-article — simpan draft_content (AJAX). Tidak
// menyentuh content/status. mode=discard membuang draft. Cek kepemilikan writer.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/sanitize.php';
requireStaff();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF tidak valid.']);
    exit;
}

$id   = (int) ($_POST['id'] ?? 0);
$mode = $_POST['mode'] ?? 'autosave';
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
    exit;
}

$pdo = getDB();
try {
    $st = $pdo->prepare("SELECT author_id FROM articles WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { echo json_encode(['success' => false, 'message' => 'Artikel tidak ada.']); exit; }
    if (isWriter() && (int) $row['author_id'] !== currentUserId()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bukan artikel Anda.']);
        exit;
    }

    if ($mode === 'discard') {
        $pdo->prepare("UPDATE articles SET draft_content = NULL WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'discarded' => true]);
        exit;
    }

    $draft = sanitizeArticleHtml($_POST['content'] ?? '');
    // Outline ikut siklus autosave (bukan timer kedua). Validasi = JSON valid.
    $outline = null;
    if (isset($_POST['outline_json']) && trim($_POST['outline_json']) !== '') {
        $decoded = json_decode((string) $_POST['outline_json'], true);
        if (is_array($decoded)) $outline = json_encode($decoded);
    }
    $pdo->prepare("UPDATE articles SET draft_content = ?, outline_json = COALESCE(?, outline_json) WHERE id = ?")
        ->execute([$draft, $outline, $id]);
    echo json_encode(['success' => true, 'saved_at' => date('H:i:s')]);
} catch (Throwable $e) {
    error_log('autosave: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan draft.']);
}
