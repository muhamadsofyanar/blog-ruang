<?php
// POST /actions/admin/refresh-balance — paksa ambil saldo terbaru (AJAX). JSON.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/AverionAiAdapter.php';
requireAdmin();
header('Content-Type: application/json');

$adapter = new AverionAiAdapter();
$res = $adapter->getBalanceCached(true);

if ($res['ok']) {
    echo json_encode(['ok' => true, 'balance' => $res['data']]);
} else {
    echo json_encode(['ok' => false, 'message' => $res['message']]);
}
