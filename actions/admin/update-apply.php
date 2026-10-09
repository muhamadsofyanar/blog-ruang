<?php
// POST /actions/admin/update-apply — terapkan pembaruan bertahap (AJAX).
// step: download → backup → extract → migrate. Pola anti-timeout (satu langkah
// per request). State di session (scribe_update).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/self-update.php';
requireAdmin();
@set_time_limit(300);
ignore_user_abort(true);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Token keamanan tidak valid.']);
    exit;
}

if (getenv('AVERION_CONTAINER') === '1') {
    http_response_code(409);
    echo json_encode(['ok' => false, 'message' => 'Deployment Docker: perbarui source di GitHub lalu redeploy melalui Coolify.']);
    exit;
}

$ctx  = $_SESSION['scribe_update'] ?? null;
$step = $_POST['step'] ?? '';
if (!is_array($ctx) || ($ctx['token'] ?? '') === '') {
    echo json_encode(['ok' => false, 'message' => 'Sesi pembaruan tidak ditemukan. Cek pembaruan ulang.']);
    exit;
}

try {
    switch ($step) {
        case 'download':
            $res = scribeUpdateDownload($ctx['token'], $ctx['md5'] ?? '');
            if (isset($res['error'])) { echo json_encode(['ok' => false, 'message' => $res['error']]); exit; }
            $_SESSION['scribe_update']['zip'] = $res['zip'];
            echo json_encode(['ok' => true, 'step' => 'download', 'label' => 'Paket terunduh', 'next' => 'backup']);
            break;

        case 'backup':
            $zip = $_SESSION['scribe_update']['zip'] ?? '';
            if (!is_file($zip)) { echo json_encode(['ok' => false, 'message' => 'File paket hilang. Ulangi.']); exit; }
            $res = scribeUpdateBackup($zip);
            if (isset($res['error'])) { echo json_encode(['ok' => false, 'message' => $res['error']]); exit; }
            $_SESSION['scribe_update']['backup'] = $res['dir'];
            echo json_encode(['ok' => true, 'step' => 'backup', 'label' => $res['count'] . ' file dibackup', 'next' => 'extract']);
            break;

        case 'extract':
            $zip = $_SESSION['scribe_update']['zip'] ?? '';
            if (!is_file($zip)) { echo json_encode(['ok' => false, 'message' => 'File paket hilang. Ulangi.']); exit; }
            $res = scribeUpdateExtract($zip);
            if (isset($res['error'])) {
                $bk = $_SESSION['scribe_update']['backup'] ?? '';
                echo json_encode(['ok' => false, 'message' => $res['error'] . ' Restore manual dari: ' . $bk]);
                exit;
            }
            echo json_encode(['ok' => true, 'step' => 'extract', 'label' => $res['written'] . ' file diperbarui', 'next' => 'migrate']);
            break;

        case 'migrate':
            require_once __DIR__ . '/../../helpers/migrate.php';
            $r = scribeRunMigrations(getDB(), __DIR__ . '/../../migrations');
            $ver = $_SESSION['scribe_update']['version'] ?? '';
            // Bersihkan file sementara + state.
            $zip = $_SESSION['scribe_update']['zip'] ?? '';
            if ($zip && is_file($zip)) @unlink($zip);
            unset($_SESSION['scribe_update']);
            if (!empty($r['errors'])) {
                echo json_encode(['ok' => false, 'message' => 'File terpasang, tapi migrasi gagal: ' . implode('; ', $r['errors']) . '. Jalankan manual.']);
                exit;
            }
            // Optimasi satu kali untuk aset kritis lama. Sumber asli tetap ada,
            // sehingga aman bila format tertentu tidak dapat diproses.
            if (getSetting('image_optimizer_version', '0') !== '1') {
                require_once __DIR__ . '/../../helpers/media.php';
                $optimized = scribeOptimizeCriticalImages(getDB());
                if (!empty($optimized['errors'])) {
                    error_log('update image optimizer: ' . implode(' | ', $optimized['errors']));
                }
                setSetting('image_optimizer_version', '1', 'performance');
            }
            // HTML cache bisa masih memuat CSS/markup versi lama setelah file
            // update selesai. Bersihkan agar versi baru langsung digunakan.
            require_once __DIR__ . '/../../helpers/page-cache.php';
            pageCacheFlushAll();
            echo json_encode(['ok' => true, 'step' => 'migrate', 'label' => 'Migrasi ' . (implode(', ', $r['applied']) ?: 'tidak ada') . ' selesai', 'next' => 'done', 'version' => $ver]);
            break;

        default:
            echo json_encode(['ok' => false, 'message' => 'Langkah tidak dikenal.']);
    }
} catch (Throwable $e) {
    error_log('update-apply [' . $step . ']: ' . $e->getMessage());
    $bk = $_SESSION['scribe_update']['backup'] ?? '';
    echo json_encode(['ok' => false, 'message' => 'Gagal pada langkah ' . $step . '. ' . ($bk ? 'Restore dari: ' . $bk : '')]);
}
