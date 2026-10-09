<?php
// ════════════════════════════════════════════════════════════════
// Helper umum — Averion SEO Engine (client). Pola Averion:
// navigasi, flash, CSRF, auth dua role (admin/writer), settings.
// ════════════════════════════════════════════════════════════════

// ─── Navigasi ─────────────────────────────────────────────────

function redirect(string $url): void
{
    if (str_starts_with($url, '/')) {
        $url = rtrim(APP_URL, '/') . $url;
    }
    header('Location: ' . $url);
    exit;
}

/** URL absolut dari path root-relative (untuk atribut href/action di view). */
function url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/** Tujuan redirect internal aman pasca-auth (cegah open-redirect + loop login). */
function isSafeRedirectPath(string $uri): bool
{
    if ($uri === '' || $uri[0] !== '/') return false;
    if (str_contains($uri, '//')) return false;
    if (str_contains($uri, '\\')) return false;
    $path = explode('?', $uri, 2)[0];
    return !preg_match('#/(login|logout|installer)(/|$)#', $path);
}

/** Request via AJAX (fetch/XMLHttpRequest)? */
function wantsJsonResponse(): bool
{
    return strcasecmp((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0;
}

// ─── Flash Messages ───────────────────────────────────────────

function flash(string $key, string $message, string $type = 'success'): void
{
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

function getFlash(string $key): ?array
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }
    $data = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $data;
}

/** Ambil + hapus semua flash (dipakai partial global). */
function takeAllFlash(): array
{
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

// ─── Output Safety ────────────────────────────────────────────

function e(?string $str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

// ─── Auth Helpers (dua role: admin | writer) ──────────────────

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUserId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function currentRole(): string
{
    return (string) ($_SESSION['role'] ?? '');
}

function isAdmin(): bool
{
    return isLoggedIn() && currentRole() === 'admin';
}

function isWriter(): bool
{
    return isLoggedIn() && currentRole() === 'writer';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        // Simpan tujuan sebagai path relatif-root APP (TANPA base path subfolder).
        // REQUEST_URI memuat base path (mis. /averion-scribe/admin) pada instalasi
        // subfolder; bila disimpan apa adanya, redirect() menambah APP_URL yang
        // SUDAH memuat base path → path ganda (/averion-scribe/averion-scribe/...)
        // → 404 setelah login. Tanggalkan base path lebih dulu (lihat index.php).
        $reqUri   = $_SERVER['REQUEST_URI'] ?? '';
        $basePath = rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/');
        if ($basePath !== '' && str_starts_with($reqUri, $basePath)) {
            $reqUri = substr($reqUri, strlen($basePath));
        }
        if ($reqUri === '' || $reqUri[0] !== '/') $reqUri = '/' . ltrim($reqUri, '/');
        $_SESSION['login_redirect'] = $reqUri;
        flash('auth', 'Silakan login terlebih dahulu.', 'info');
        redirect('/login');
    }
}

/** Guard route admin-only. Writer → 403 (middleware route guard). */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        denyAccess();
    }
}

/** Admin ATAU writer boleh (area admin umum). */
function requireStaff(): void
{
    requireLogin();
    if (!in_array(currentRole(), ['admin', 'writer'], true)) {
        denyAccess();
    }
}

/** Tolak akses (403) dengan halaman minimal identitas Averion. */
function denyAccess(): void
{
    http_response_code(403);
    $title = 'Akses Ditolak';
    require __DIR__ . '/pages/enforcement/_403.php';
    exit;
}

// ─── CSRF ─────────────────────────────────────────────────────

function generateCSRF(): string
{
    // Idempotent — guard wajib (pola Averion).
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRF(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || $token === null || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/** Field input hidden CSRF untuk form. */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(generateCSRF()) . '">';
}

// ─── Settings (key-value) ─────────────────────────────────────

/**
 * Cache setting per-request, DIBAGI oleh getSetting & setSetting (dikembalikan by
 * reference) agar tetap koheren: menulis lalu membaca key yang sama di request
 * yang sama mengembalikan nilai terbaru, bukan nilai lama yang terlanjur ter-cache.
 */
function &settingsCacheRef(): array
{
    static $cache = [];
    return $cache;
}

function getSetting(string $key, ?string $default = null): ?string
{
    $cache = &settingsCacheRef();

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = ($value !== false) ? $value : $default;
    } catch (Exception $e) {
        error_log('getSetting error [' . $key . ']: ' . $e->getMessage());
        $cache[$key] = $default;
    }

    return $cache[$key];
}

function setSetting(string $key, ?string $value, string $group = 'general'): bool
{
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value, setting_group)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
        );
        $stmt->execute([$key, $value, $group]);
        // Jaga cache tetap koheren dalam request ini (baca-setelah-tulis konsisten).
        $cache = &settingsCacheRef();
        $cache[$key] = $value;
        return true;
    } catch (Exception $e) {
        error_log('setSetting error [' . $key . ']: ' . $e->getMessage());
        return false;
    }
}

/** Nama blog aktif dengan fallback aman ke APP_NAME (setting kosong = pakai default). */
function blogName(): string
{
    $n = trim((string) getSetting('blog_name', ''));
    return $n !== '' ? $n : APP_NAME;
}

/**
 * Tag <link rel="icon">. Pakai favicon ter-upload bila ada; jika belum,
 * hasilkan favicon DEFAULT (SVG inline data-URI: kotak accent + glyph pen).
 * Dipakai admin, login, dan frontend publik.
 */
function faviconLinkTag(string $uploadedRel = '', string $accent = '#6366f1'): string
{
    // Pakai favicon ter-upload HANYA bila filenya benar-benar ada (path stale → default).
    if ($uploadedRel !== '' && is_file(rtrim(UPLOAD_PATH, '/\\') . '/' . ltrim($uploadedRel, '/'))) {
        return '<link rel="icon" href="' . e(rtrim(UPLOAD_URL, '/') . '/' . $uploadedRel) . '">';
    }
    // Guard: accent hanya hex 6 digit (hindari nilai aneh masuk SVG).
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) $accent = '#6366f1';
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
         . '<rect width="32" height="32" rx="7" fill="' . $accent . '"/>'
         . '<g transform="translate(6.2 6.2) scale(0.82)" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">'
         . '<path d="M12 20h9"/>'
         . '<path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"/>'
         . '</g></svg>';
    return '<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,' . base64_encode($svg) . '">';
}

// ─── Bahasa (satu sumber daftar bahasa output AI + konten) ─────

/** Daftar bahasa yang didukung: kode → label. Dipakai Settings & editor. */
function languageOptions(): array
{
    return [
        'id' => 'Indonesia',
        'en' => 'English',
        'ms' => 'Bahasa Melayu (Malaysia)',
    ];
}

/** Validasi kode bahasa terhadap daftar terpusat. */
function isValidLanguage(?string $lang): bool
{
    return $lang !== null && array_key_exists($lang, languageOptions());
}

// ─── Slug ─────────────────────────────────────────────────────

/** Ubah teks bebas → slug lowercase-dash aman (a-z 0-9 -). */
function slugify(string $text): string
{
    $text = trim($text);
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($conv !== false) $text = $conv;
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return $text !== '' ? $text : 'item';
}

/**
 * Pastikan slug unik pada $table (kolom slug). Bila bentrok, tambah -2, -3, dst.
 * $excludeId untuk mengecualikan baris sendiri saat update.
 */
function uniqueSlug(PDO $pdo, string $table, string $slug, ?int $excludeId = null): string
{
    $allowed = ['articles', 'categories', 'tags'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Tabel slug tidak dikenal.');
    }
    $base = slugify($slug);
    $candidate = $base;
    $i = 1;
    while (true) {
        $sql = "SELECT id FROM `$table` WHERE slug = ?" . ($excludeId ? " AND id <> ?" : "") . " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($excludeId ? [$candidate, $excludeId] : [$candidate]);
        if ($stmt->fetchColumn() === false) {
            return $candidate;
        }
        $i++;
        $candidate = $base . '-' . $i;
    }
}

// ─── Format ───────────────────────────────────────────────────

/** Tanggal ringkas Indonesia: "19 Jul 2026 14:30". */
function formatTanggal(?string $datetime, bool $withTime = true): string
{
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '-';
    $ts = strtotime($datetime);
    if ($ts === false) return '-';
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $s = date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    return $withTime ? $s . ' ' . date('H:i', $ts) : $s;
}

/** Potong teks polos ke $len karakter (tambah elipsis). */
function truncateText(string $text, int $len = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    if (mb_strlen($text) <= $len) return $text;
    return rtrim(mb_substr($text, 0, $len - 1)) . '…';
}

// ─── Badge status artikel (satu komponen untuk semua halaman) ─────

/** [label, warna] untuk status artikel. */
function articleStatusMeta(string $status): array
{
    return [
        'draft'     => ['Draft', 'gray'],
        'draft_ai'  => ['Draft AI', 'violet'],
        'scheduled' => ['Terjadwal', 'amber'],
        'published' => ['Published', 'emerald'],
    ][$status] ?? [ucfirst($status), 'gray'];
}

/** HTML badge status artikel — konsisten di listing & dashboard. */
function articleStatusBadge(string $status): string
{
    [$label, $c] = articleStatusMeta($status);
    return '<span class="inline-block text-[11px] font-medium px-2 py-0.5 rounded-md '
         . 'bg-' . $c . '-100 text-' . $c . '-700 dark:bg-' . $c . '-950/50 dark:text-' . $c . '-300">'
         . e($label) . '</span>';
}

// ─── Ikon Lucide inline (SVG, tanpa emoji, tanpa library eksternal) ──
function icon(string $name, string $class = 'w-5 h-5'): string
{
    $paths = [
        'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'activity'         => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'file-text'        => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'folder'           => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'tag'              => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'arrow-left-right' => '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
        'settings'         => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'users'            => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'             => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'coins'            => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>',
        'log-out'          => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'sun'              => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'moon'             => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'shield-check'     => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'alert-triangle'   => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'lock'             => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'menu'             => '<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>',
        'pen-line'         => '<path d="M12 20h9"/><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"/>',
        'check-circle'     => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
        'plus'             => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'search'           => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'trash'            => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
        'x'                => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'image'            => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'chevron-down'     => '<path d="m6 9 6 6 6-6"/>',
        'save'             => '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>',
        'bold'             => '<path d="M6 12h9a4 4 0 0 1 0 8H6z"/><path d="M6 4h7a4 4 0 0 1 0 8H6z"/>',
        'italic'           => '<line x1="19" x2="10" y1="4" y2="4"/><line x1="14" x2="5" y1="20" y2="20"/><line x1="15" x2="9" y1="4" y2="20"/>',
        'list'             => '<line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/>',
        'list-ordered'     => '<line x1="10" x2="21" y1="6" y2="6"/><line x1="10" x2="21" y1="12" y2="12"/><line x1="10" x2="21" y1="18" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>',
        'quote'            => '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>',
        'link'             => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'unlink'           => '<path d="m18.84 12.25 1.72-1.71h-.02a5.004 5.004 0 0 0-.12-7.07 5.006 5.006 0 0 0-6.95 0l-1.72 1.71"/><path d="m5.17 11.75-1.71 1.71a5.004 5.004 0 0 0 .12 7.07 5.006 5.006 0 0 0 6.95 0l1.71-1.71"/><line x1="8" x2="8" y1="2" y2="5"/><line x1="2" x2="5" y1="8" y2="8"/><line x1="16" x2="16" y1="19" y2="22"/><line x1="19" x2="22" y1="16" y2="16"/>',
        'heading'          => '<path d="M6 12h12"/><path d="M6 20V4"/><path d="M18 20V4"/>',
        'pen'              => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>',
        'external-link'    => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'download'         => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        'mail'             => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a2 2 0 0 1-2.06 0L2 7"/>',
        'send'             => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'arrow-right'      => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'refresh-cw'       => '<path d="M3 12a9 9 0 0 1 15.66-6.19L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.66 6.19L3 16"/><path d="M3 21v-5h5"/>',
        'pin'              => '<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/>',
        'key'              => '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>',
        'copy'             => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
        'eye'              => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
        'book-open'        => '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" class="' . e($class) . '" viewBox="0 0 24 24" '
         . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
         . 'stroke-linejoin="round">' . $inner . '</svg>';
}
