<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/mailketing.php';
requireAdmin();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) { http_response_code(400); echo json_encode(['ok' => false, 'message' => 'Permintaan tidak valid.']); exit; }
$to = trim((string) ($_POST['email'] ?? ''));
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { http_response_code(422); echo json_encode(['ok' => false, 'message' => 'Email tujuan tidak valid.']); exit; }
$subject = 'Tes Mailketing — ' . blogName();
$html = '<p>Ini email pengujian dari <strong>' . e(blogName()) . '</strong>. Integrasi Mailketing berhasil merespons.</p>';
$res = scribeMailketingSend($to, $subject, $html, ['max_attempts' => 1]);
echo json_encode(['ok' => (bool) $res['ok'], 'message' => (string) ($res['message'] ?? '')], JSON_UNESCAPED_UNICODE);
