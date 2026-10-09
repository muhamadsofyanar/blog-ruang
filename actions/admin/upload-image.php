<?php
// POST /actions/admin/upload-image — upload gambar inline editor (AJAX, multipart).
// Return JSON {url}. CSRF via field form data.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/media.php';
requireStaff();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF tidak valid.']);
    exit;
}

if (!isset($_FILES['image'])) {
    echo json_encode(['error' => 'Tidak ada file.']);
    exit;
}

$res = saveEditorImage($_FILES['image']);
if (isset($res['error'])) {
    echo json_encode(['error' => $res['error']]);
    exit;
}
echo json_encode(['url' => $res['url']]);
