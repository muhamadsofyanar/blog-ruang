<?php
// ════════════════════════════════════════════════════════════════════════
// Bootstrap — Averion SEO Engine (client). Urutan:
//   config (+version) → db → helpers → license-token → license-client →
//   session → enforcement. define() konstanta SELALU sebelum call site
//   (define TIDAK di-hoist seperti fungsi).
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/config.php';               // define DB_*, APP_URL, APP_VERSION, LICENSE_*
require_once __DIR__ . '/db.php';                    // getDB()
require_once __DIR__ . '/helpers.php';               // redirect/flash/csrf/auth/settings
require_once __DIR__ . '/helpers/license-token.php'; // verifyLicenseTokenSignature() + licenseEnforcementActive()
require_once __DIR__ . '/helpers/license-client.php';// refreshLicenseFromServer() + resolveCurrentLicenseState()

date_default_timezone_set('Asia/Jakarta');

// ─── Error display: aman by-default ──────────────────────────────
error_reporting(E_ALL);
$__isLocalEnv = (strpos(APP_URL, 'localhost') !== false || strpos(APP_URL, '127.0.0.1') !== false
    || strpos(APP_URL, '.local') !== false);
if ($__isLocalEnv) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $__logDir = __DIR__ . '/cache/logs';
    if (!is_dir($__logDir)) { @mkdir($__logDir, 0775, true); }
    if (is_dir($__logDir) && is_writable($__logDir)) {
        ini_set('error_log', $__logDir . '/php-error.log');
    }
    unset($__logDir);
}
unset($__isLocalEnv);

// ─── Session ─────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    $__https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $__https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($__https);
    session_start();
}

// ─── License Enforcement ─────────────────────────────────────────
_enforceLicense();

/**
 * Enforcement lisensi (signed-token Ed25519). Redirect paksa sesuai state
 * machine. Saat LICENSE_ENFORCEMENT_ENABLED mati → tidak ada aksi apa pun.
 * Halaman enforcement TIDAK ikut rebrand — selalu identitas Averion.
 */
function _enforceLicense(): void
{
    if (!licenseEnforcementActive()) {
        return;
    }

    // Normalisasi path (pola sama dengan router index.php).
    $path     = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    $basePath = rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/');
    if ($basePath !== '' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }
    $path = rtrim($path, '/') ?: '/';

    // Route yang TIDAK pernah di-enforce (cegah loop + jaga akses recovery).
    $exactExcluded  = ['/admin/license', '/admin/license-activate', '/license-expired'];
    $prefixExcluded = ['/installer', '/login', '/logout', '/actions/auth', '/actions/admin/activate-license',
                       '/actions/admin/revalidate-license'];
    if (in_array($path, $exactExcluded, true)) {
        return;
    }
    foreach ($prefixExcluded as $p) {
        if (str_starts_with($path, $p)) {
            return;
        }
    }
    if (preg_match('#\.(css|js|png|jpg|jpeg|webp|avif|gif|ico|svg|woff|woff2|ttf|eot|map)$#i', $path)) {
        return;
    }

    $ls       = resolveCurrentLicenseState();
    $isAdminP = str_starts_with($path, '/admin');

    // 1) SUSPENDED → full lockdown SEMUA route (override tertinggi).
    if ($ls['suspended']) {
        redirect('/license-expired');
    }

    // 2) PENDING_ACTIVATION → hanya admin diarahkan ke aktivasi.
    if ($ls['state'] === 'pending_activation') {
        if (isAdmin() && $isAdminP) {
            flash('error', 'Lisensi belum diaktifkan. Selesaikan aktivasi untuk melanjutkan.', 'error');
            redirect('/admin/license-activate');
        }
        return;
    }

    // 3) EXPIRED → kunci admin panel saja; publik tetap jalan.
    if ($ls['state'] === 'expired') {
        if ($isAdminP) {
            flash('error', 'Admin panel terkunci — lisensi perlu re-validasi.', 'error');
            redirect('/admin/license');
        }
        return;
    }

    // 4) GRACE → tanpa redirect, hanya banner peringatan (admin).
    if ($ls['state'] === 'grace') {
        if (isAdmin() && !defined('LICENSE_GRACE_WARNING')) {
            $daysLeft = $ls['days_until_expiry'] !== null
                ? max(0, (int) $ls['days_until_expiry'] + LICENSE_OFFLINE_GRACE_DAYS)
                : LICENSE_OFFLINE_GRACE_DAYS;
            define('LICENSE_GRACE_WARNING',
                'Lisensi perlu re-validasi. Sisa sekitar ' . $daysLeft . ' hari sebelum admin panel terkunci.');
        }
        return;
    }

    // 5) VALID → tidak ada aksi.
}
