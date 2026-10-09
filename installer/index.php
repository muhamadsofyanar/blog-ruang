<?php
// ════════════════════════════════════════════════════════════════════════
// Installer Averion SEO Engine (client). Alur:
//   1 Requirements → 2 Database + config + migrasi → 3 Admin pertama →
//   4 Aktivasi lisensi → 5 Selesai (tulis install.lock). Terkunci setelah sukses.
// ════════════════════════════════════════════════════════════════════════
error_reporting(E_ALL);
ini_set('display_errors', '0');
require __DIR__ . '/requirements.php';

$ROOT        = dirname(__DIR__);
require_once $ROOT . '/helpers.php'; // e(), icon() — tanpa side-effect saat load
$runtimeDir = getenv('AVERION_RUNTIME_DIR') ?: '';
$configPath = $runtimeDir !== '' ? $runtimeDir . '/config.php' : $ROOT . '/config.php';
$lockPath = $runtimeDir !== '' ? $runtimeDir . '/install.lock' : __DIR__ . '/install.lock';
$lockExists   = file_exists($lockPath);
$configExists = file_exists($configPath);

session_set_cookie_params([
    'httponly' => true,
    'secure' => str_starts_with(getenv('APP_URL') ?: '', 'https://') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
]);
session_start();
// A fresh public deployment must not let a stranger claim the first admin.
$setupToken = getenv('INSTALLER_TOKEN') ?: '';
if ($setupToken !== '' && empty($_SESSION['installer_authorized'])) {
    if (isset($_POST['setup_token']) && hash_equals($setupToken, (string) $_POST['setup_token'])) {
        session_regenerate_id(true);
        $_SESSION['installer_authorized'] = true;
        header('Location: ./');
        exit;
    }
    http_response_code(403);
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Setup Averion</title><body><h1>Setup Averion</h1><p>Masukkan INSTALLER_TOKEN dari konfigurasi Coolify.</p><form method="post"><input type="password" name="setup_token" required autocomplete="off"><button>Mulai instalasi</button></form></body></html>';
    exit;
}
define('INSTALLER_ENTRY', true);

$midInstall   = !empty($_SESSION['install_step1_done']);
$justFinished = !empty($_SESSION['install_finished']);

function installer_block(string $heading, string $body, string $link = ''): void
{
    http_response_code(403);
    die('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Installer</title><style>body{font-family:system-ui,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;background:#f1f5f9;padding:1rem}
        .b{max-width:420px;text-align:center;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:2.5rem 2rem}
        h2{margin:0 0 .5rem;font-size:1.2rem;color:#0f172a}p{color:#64748b;font-size:.9rem;line-height:1.6}
        a{color:#6366f1;font-weight:600;text-decoration:none}code{background:#f1f5f9;padding:2px 6px;border-radius:4px}</style></head>
        <body><div class="b"><h2>' . $heading . '</h2><p>' . $body . '</p>' . $link . '</div></body></html>');
}

// ─── Guard: sudah terpasang? ─────────────────────────────────────
if ($lockExists && !$configExists) {
    installer_block('Instalasi Rusak',
        'File <code>config.php</code> hilang padahal installer sudah selesai. Hapus <code>installer/install.lock</code> untuk mengulang.',
        '<a href="mailto:support@averion.id">Hubungi Support &rarr;</a>');
}
if (($lockExists && !$justFinished) || ($configExists && !$midInstall && !$justFinished)) {
    installer_block('Aplikasi Sudah Terpasang',
        'Installer tidak bisa dijalankan ulang demi keamanan.',
        '<a href="../login">Masuk ke Aplikasi &rarr;</a>');
}

// ─── CSRF installer ──────────────────────────────────────────────
if (empty($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}
function installer_check_csrf(): bool
{
    return isset($_POST['csrf']) && hash_equals($_SESSION['installer_csrf'], (string) $_POST['csrf']);
}

$errors = [];
$step   = isset($_GET['step']) ? max(1, min(5, (int) $_GET['step'])) : 1;

// ════════════════════════════════════════════════════════════════
// POST handlers (PRG)
// ════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && installer_check_csrf()) {
    $postStep = (int) ($_POST['step'] ?? 0);

    // ── STEP 1 → 2 ──────────────────────────────────────────────
    if ($postStep === 1) {
        if (scribe_required_all_pass()) {
            $_SESSION['install_step1_done'] = true;
            header('Location: ?step=2'); exit;
        }
        $errors[] = 'Beberapa kebutuhan wajib belum terpenuhi.';
        $step = 1;
    }

    // ── STEP 2: Database + config + migrasi ─────────────────────
    elseif ($postStep === 2 && !empty($_SESSION['install_step1_done'])) {
        $db = [
            'host' => trim($_POST['db_host'] ?? 'localhost'),
            'name' => trim($_POST['db_name'] ?? ''),
            'user' => trim($_POST['db_user'] ?? ''),
            'pass' => (string) ($_POST['db_pass'] ?? ''),
            'port' => trim($_POST['db_port'] ?? '3306'),
        ];
        $appUrl     = rtrim(trim($_POST['app_url'] ?? ''), '/');
        $licServer  = rtrim(trim($_POST['license_server'] ?? 'https://vendor.averion.id/api'), '/');
        $aiGateway  = rtrim(trim($_POST['ai_gateway'] ?? 'https://ai.averion.id'), '/');

        if ($db['name'] === '' || $db['user'] === '' || $appUrl === '') {
            $errors[] = 'Nama database, user, dan APP_URL wajib diisi.';
        } elseif (!preg_match('#^https?://#', $appUrl)) {
            $errors[] = 'APP_URL harus diawali http:// atau https://';
        } else {
            // Uji koneksi.
            try {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
                $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (Throwable $e) {
                $errors[] = 'Koneksi database gagal: ' . $e->getMessage();
                $pdo = null;
            }

            if ($pdo instanceof PDO && !$errors) {
                // Tulis config.php dari template.
                $esc = fn($v) => str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $v);
                $cfg = "<?php\n"
                    . "// Dibuat otomatis oleh installer Averion SEO Engine.\n"
                    . "require_once " . var_export($ROOT . "/version.php", true) . ";\n\n"
                    . "define('DB_HOST', '" . $esc($db['host']) . "');\n"
                    . "define('DB_NAME', '" . $esc($db['name']) . "');\n"
                    . "define('DB_USER', '" . $esc($db['user']) . "');\n"
                    . "define('DB_PASS', '" . $esc($db['pass']) . "');\n"
                    . "define('DB_PORT', '" . $esc($db['port']) . "');\n\n"
                    . "define('APP_URL', '" . $esc($appUrl) . "');\n"
                    . "define('APP_NAME', 'Averion SEO Engine');\n"
                    . "define('SESSION_NAME', 'scribe_sess');\n\n"
                    . "define('UPLOAD_PATH', " . var_export($ROOT . "/uploads/", true) . ");\n"
                    . "define('UPLOAD_URL', APP_URL . '/uploads/');\n"
                    . "define('MAX_UPLOAD_SIZE', 3 * 1024 * 1024);\n\n"
                    . "define('LICENSE_SERVER_URL', '" . $esc($licServer) . "');\n"
                    . "define('AI_GATEWAY_URL', '" . $esc($aiGateway) . "');\n\n"
                    . "define('LICENSE_ENFORCEMENT_ENABLED', true);\n\n"
                    . "define('VENDOR_EMAIL', 'support@averion.id');\n"
                    . "define('VENDOR_WHATSAPP', '628xxxxxxxxxx');\n";

                if (@file_put_contents($configPath, $cfg) === false) {
                    $errors[] = 'Gagal menulis config.php. Pastikan folder root writable.';
                } else {
                    // Jalankan migrasi awal.
                    require_once $configPath;
                    require_once $ROOT . '/db.php';
                    require_once $ROOT . '/helpers.php';
                    require_once $ROOT . '/helpers/migrate.php';
                    try {
                        $res = scribeRunMigrations(getDB(), $ROOT . '/migrations');
                        if (!empty($res['errors'])) {
                            $errors[] = 'Migrasi gagal: ' . implode('; ', $res['errors']);
                            @unlink($configPath); // batalkan agar bisa diulang
                        } else {
                            $_SESSION['install_step2_done'] = true;
                            $_SESSION['install_migrated']   = $res['applied'];
                            header('Location: ?step=3'); exit;
                        }
                    } catch (Throwable $e) {
                        $errors[] = 'Migrasi error: ' . $e->getMessage();
                        @unlink($configPath);
                    }
                }
            }
        }
        $step = 2;
        $_POST_KEEP = $db + ['app_url' => $appUrl, 'license_server' => $licServer, 'ai_gateway' => $aiGateway];
    }

    // ── STEP 3: Admin pertama ───────────────────────────────────
    elseif ($postStep === 3 && !empty($_SESSION['install_step2_done'])) {
        $name  = trim($_POST['admin_name'] ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $pass  = (string) ($_POST['admin_password'] ?? '');
        if ($name === '' || $email === '' || $pass === '') {
            $errors[] = 'Semua field admin wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        } elseif (strlen($pass) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        } else {
            require_once $configPath;
            require_once $ROOT . '/db.php';
            try {
                $stmt = getDB()->prepare('INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)');
                $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin']);
                $_SESSION['install_step3_done'] = true;
                header('Location: ?step=4'); exit;
            } catch (Throwable $e) {
                $errors[] = 'Gagal membuat admin: ' . $e->getMessage();
            }
        }
        $step = 3;
        $_POST_KEEP = ['admin_name' => $name ?? '', 'admin_email' => $email ?? ''];
    }

    // ── STEP 4: Aktivasi lisensi ────────────────────────────────
    elseif ($postStep === 4 && !empty($_SESSION['install_step3_done'])) {
        require_once $configPath;
        require_once $ROOT . '/db.php';
        require_once $ROOT . '/helpers.php';
        require_once $ROOT . '/helpers/license-token.php';
        require_once $ROOT . '/helpers/license-client.php';

        $action = $_POST['license_action'] ?? 'activate';
        if ($action === 'skip') {
            setSetting('license_activation_pending', '1', 'license');
            $_SESSION['install_step4_done'] = true;
            $_SESSION['install_license_ok'] = false;
            header('Location: ?step=5'); exit;
        }

        $key = strtoupper(trim($_POST['license_key'] ?? ''));
        if ($key === '') {
            $errors[] = 'License key wajib diisi (atau lewati untuk aktivasi nanti).';
        } else {
            $res = refreshLicenseFromServer($key, parseDomainFromAppUrl());
            if ($res['ok']) {
                $_SESSION['install_step4_done'] = true;
                $_SESSION['install_license_ok'] = true;
                header('Location: ?step=5'); exit;
            }
            $errors[] = 'Aktivasi gagal: ' . $res['message'];
        }
        $step = 4;
    }
}

// ─── Guard urutan step ───────────────────────────────────────────
if ($step > 1 && empty($_SESSION['install_step1_done'])) $step = 1;
if ($step > 2 && empty($_SESSION['install_step2_done'])) $step = 2;
if ($step > 3 && empty($_SESSION['install_step3_done'])) $step = 3;
if ($step > 4 && empty($_SESSION['install_step4_done'])) $step = 4;

// ─── STEP 5: tulis lock (finalize) ───────────────────────────────
if ($step === 5) {
    if (!$lockExists) {
        if (file_put_contents($lockPath, 'installed ' . date('c'), LOCK_EX) === false) {
            installer_block('Instalasi belum terkunci', 'Gagal menulis install.lock. Periksa izin penyimpanan lalu muat ulang halaman ini.');
        }
    }
    $_SESSION['install_finished'] = true;
}

// ─── Render view ─────────────────────────────────────────────────
$views = [
    1 => 'step1-requirements.php',
    2 => 'step2-database.php',
    3 => 'step3-admin.php',
    4 => 'step4-license.php',
    5 => 'step5-finish.php',
];
require __DIR__ . '/views/_layout.php';
