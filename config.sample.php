<?php
// ════════════════════════════════════════════════════════════════
// TEMPLATE KONFIGURASI — Averion SEO Engine (client averion-scribe)
// config.php dibuat OTOMATIS oleh installer (/installer). File ini hanya acuan
// setup manual. `config.php` sengaja TIDAK ikut Git (kredensial) — lihat .gitignore.
// ════════════════════════════════════════════════════════════════

// Versi aplikasi dipisah ke version.php (ikut ditimpa saat self-update).
require_once __DIR__ . '/version.php';

// ─── Environment ──────────────────────────────────────────────
// Produksi = 'production'. HANYA instalasi pengembangan memakai 'dev' (bersama
// file marker .dev-mode) untuk mengaktifkan gate dev-key. JANGAN set 'dev' di
// produksi.
define('APP_ENV', 'production');

// ─── Database ─────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'averion_scribe');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3306');

// ─── Aplikasi: APP_URL ────────────────────────────────────────
// Isi URL penuh instalasi (tanpa trailing slash). Host-nya dipakai sebagai
// domain lisensi (enforcement Ed25519) — WAJIB sama dengan domain lisensi.
define('APP_URL', 'https://blog.domainkamu.com');
define('APP_NAME', 'Averion SEO Engine');

// ─── Session ──────────────────────────────────────────────────
define('SESSION_NAME', 'scribe_sess');

// ─── Upload ───────────────────────────────────────────────────
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_UPLOAD_SIZE', 3 * 1024 * 1024); // 3MB

// ─── License Server (vendor.averion.id) ───────────────────────
// Dev lokal: 'http://localhost/averion-license/api'
define('LICENSE_SERVER_URL', 'https://vendor.averion.id/api');

// ─── AI Gateway (ai.averion.id) ───────────────────────────────
// Dev lokal: 'http://127.0.0.1:8787'
define('AI_GATEWAY_URL', 'https://ai.averion.id');

// ─── License Enforcement (signed-token Ed25519) ───────────────
// Konstanta TIDAK didefinisikan = enforcement AKTIF (default). define false =
// kill-switch dev. State 'expired' hanya mengunci admin panel.
define('LICENSE_ENFORCEMENT_ENABLED', true);

// ─── Public key DEV tambahan (LOKAL SAJA — jangan diisi di produksi) ──
// Menambahkan kid dev untuk verifikasi token yang ditandatangani license server
// lokal. Produksi cukup memakai kid 'v1' bawaan helpers/license-pubkey.php.
// define('LICENSE_PUBKEYS_DEV', ['dev1' => 'BASE64_PUBKEY_DEV']);

// ─── Kontak Vendor (halaman enforcement — identitas Averion) ──
define('VENDOR_EMAIL', 'support@averion.id');
define('VENDOR_WHATSAPP', '628xxxxxxxxxx');
