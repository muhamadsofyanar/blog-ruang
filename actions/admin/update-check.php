<?php
// POST /actions/admin/update-check — cek pembaruan ke license server (AJAX).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/self-update.php';
requireAdmin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Token keamanan tidak valid.']);
    exit;
}

$data = scribeUpdateCheck();
if (isset($data['error'])) {
    echo json_encode(['ok' => false, 'message' => $data['error']]);
    exit;
}

if (!empty($data['has_update'])) {
    // Simpan konteks untuk langkah apply (token single-use dari server).
    $_SESSION['scribe_update'] = [
        'token'     => (string) ($data['download_token'] ?? ''),
        'version'   => (string) ($data['latest_version'] ?? ''),
        'md5'       => (string) ($data['checksum_md5'] ?? ''),
        'changelog' => (string) ($data['changelog'] ?? ''),
    ];
    echo json_encode([
        'ok'         => true,
        'has_update' => true,
        'version'    => $data['latest_version'] ?? '',
        'title'      => $data['title'] ?? '',
        'changelog'  => $data['changelog'] ?? '',
        'file_size'  => (int) ($data['file_size'] ?? 0),
    ]);
    exit;
}

unset($_SESSION['scribe_update']);
echo json_encode([
    'ok'         => true,
    'has_update' => false,
    'latest'     => $data['latest_version'] ?? (defined('APP_VERSION') ? APP_VERSION : ''),
    'message'    => $data['message'] ?? 'Sudah versi terkini.',
]);
