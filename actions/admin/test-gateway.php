<?php
// GET/POST /actions/admin/test-gateway — uji koneksi gateway (AJAX). Return JSON.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/AverionAiAdapter.php';
requireAdmin();
header('Content-Type: application/json');

$adapter = new AverionAiAdapter();
$res = $adapter->ping();

if ($res['ok']) {
    echo json_encode([
        'ok'      => true,
        'message' => 'Terhubung ke gateway AI. Lisensi & versi client valid.',
        'plan'    => $res['data']['plan'] ?? null,
    ]);
} else {
    $extra = '';
    if (($res['flags']['outdated'] ?? false)) {
        $extra = ' Versi minimal yang didukung lebih tinggi — perbarui aplikasi.';
    }
    echo json_encode([
        'ok'      => false,
        'code'    => $res['code'],
        'message' => $res['message'] . $extra,
    ]);
}
