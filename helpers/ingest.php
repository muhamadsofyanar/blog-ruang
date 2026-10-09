<?php
// ════════════════════════════════════════════════════════════════════════
// Helper Ingest API — kirim artikel jadi via HTTP ber-token dari sistem
// eksternal (mis. automation/AI milik pemilik situs). Artikel SELALU masuk
// sebagai draft AI (draft_ai) untuk review manual — TIDAK PERNAH publish
// otomatis. Token disimpan HANYA sebagai hash sha256 (prinsip hash-only,
// selaras token Averion). Fitur DEFAULT MATI demi keamanan.
//
// Penyimpanan: semua konfigurasi di tabel settings (grup 'ingest'):
//   ingest_enabled    '0'|'1'  (default '0')
//   ingest_token_hash sha256(token)   (token mentah TAK pernah disimpan)
//   ingest_author_id  id user staf penulis default
// ════════════════════════════════════════════════════════════════════════

/** search_intent yang valid untuk payload ingest. */
const INGEST_SEARCH_INTENTS = ['informational', 'transactional', 'navigational'];

/** Fitur ingest aktif? (default mati) — gate baca + master switch API. */
function ingestEnabled(): bool
{
    return getSetting('ingest_enabled', '0') === '1';
}

/**
 * API manajemen aktif? (default mati). Gate TAMBAHAN untuk aksi TULIS (kelola
 * kategori & setelan) yang langsung LIVE — dipisah dari kirim draft artikel.
 */
function ingestManageEnabled(): bool
{
    return getSetting('ingest_manage_enabled', '0') === '1';
}

/**
 * Fitur gambar otomatis (unduh cover + gambar inline via provider legal) aktif?
 * (default MATI). Kill-switch produk terpisah: endpoint ini satu-satunya jalur
 * yang mengunduh berkas dari internet, jadi harus di-opt-in per instalasi.
 */
function ingestImagesEnabled(): bool
{
    return getSetting('ingest_images_enabled', '0') === '1';
}

/**
 * Validasi + normalisasi parameter gambar opsional pada payload ingest. PURE
 * (tak menyentuh DB/jaringan) agar mudah diuji. Lempar InvalidArgumentException
 * untuk input tak valid. Return:
 *   ['cover'=>['mode'=>..,'url'?,'query'?],
 *    'images'=>['mode'=>..,'count'?,'items'?],
 *    'dry_run'=>bool, 'requested'=>bool]
 * Backward-compat: dipanggil HANYA bila payload memuat cover/images/dry_run.
 */
function ingestValidateImageOpts(array $in): array
{
    $maxInline = defined('STOCK_MAX_INLINE') ? STOCK_MAX_INLINE : 6;
    $out = ['cover' => ['mode' => 'none'], 'images' => ['mode' => 'none'], 'dry_run' => false, 'requested' => false];

    if (array_key_exists('dry_run', $in)) {
        if (!is_bool($in['dry_run'])) throw new InvalidArgumentException('dry_run wajib boolean.');
        $out['dry_run'] = $in['dry_run'];
    }

    if (array_key_exists('cover', $in)) {
        $c = $in['cover'];
        if (!is_array($c)) throw new InvalidArgumentException('cover wajib object.');
        $mode = (string) ($c['mode'] ?? 'none');
        if (!in_array($mode, ['auto', 'url', 'none'], true)) throw new InvalidArgumentException('cover.mode harus auto|url|none.');
        $cover = ['mode' => $mode];
        if ($mode === 'url') {
            $u = trim((string) ($c['url'] ?? ''));
            if ($u === '') throw new InvalidArgumentException('cover.url wajib saat mode=url.');
            if (!preg_match('~^https?://~i', $u)) throw new InvalidArgumentException('cover.url harus berawalan http(s)://.');
            $cover['url'] = $u;
        }
        if (isset($c['query'])) $cover['query'] = mb_substr(trim((string) $c['query']), 0, 120);
        $out['cover'] = $cover;
    }

    if (array_key_exists('images', $in)) {
        $im = $in['images'];
        if (!is_array($im)) throw new InvalidArgumentException('images wajib object.');
        $mode = (string) ($im['mode'] ?? 'none');
        if (!in_array($mode, ['auto', 'manual', 'none'], true)) throw new InvalidArgumentException('images.mode harus auto|manual|none.');
        $images = ['mode' => $mode];
        if ($mode === 'auto') {
            $cnt = (int) ($im['count'] ?? 0);
            $images['count'] = max(0, min($maxInline, $cnt));
        } elseif ($mode === 'manual') {
            $items = is_array($im['items'] ?? null) ? $im['items'] : [];
            $clean = [];
            foreach ($items as $it) {
                if (!is_array($it)) continue;
                $u = trim((string) ($it['url'] ?? ''));
                if ($u === '') continue;
                if (!preg_match('~^https?://~i', $u)) throw new InvalidArgumentException('images.items[].url harus berawalan http(s)://.');
                $clean[] = [
                    'url'      => $u,
                    'after_h2' => max(0, (int) ($it['after_h2'] ?? 0)),
                    'alt'      => mb_substr(trim((string) ($it['alt'] ?? '')), 0, 125),
                ];
                if (count($clean) >= $maxInline) break;
            }
            $images['items'] = $clean;
        }
        $out['images'] = $images;
    }

    $out['requested'] = ($out['cover']['mode'] !== 'none') || ($out['images']['mode'] !== 'none');
    return $out;
}

/** Whitelist key setelan yang boleh dibaca/ditulis via api/settings.php (KETAT). */
const INGEST_SETTINGS_WHITELIST = [
    'blog_name', 'blog_tagline', 'brand_og_default', 'default_language',
    'brandvoice_tone', 'brandvoice_audience', 'brandvoice_business',
    'brandvoice_style_sample', 'brandvoice_banned_words',
];

/** Hash token tersimpan (kosong bila belum pernah dibuat). */
function ingestTokenHash(): string
{
    return (string) getSetting('ingest_token_hash', '');
}

/** Token sudah pernah dibuat? */
function ingestTokenIsSet(): bool
{
    return ingestTokenHash() !== '';
}

/**
 * Buat token mentah 32-byte hex, simpan HANYA hash-nya, kembalikan token mentah.
 * Token mentah hanya ada di memori panggilan ini — ditampilkan SEKALI ke admin.
 */
function ingestGenerateToken(): string
{
    $raw = bin2hex(random_bytes(32));
    setSetting('ingest_token_hash', hash('sha256', $raw), 'ingest');
    return $raw;
}

/** Verifikasi token bearer terhadap hash tersimpan (timing-safe). */
function ingestVerifyToken(string $token): bool
{
    $hash = ingestTokenHash();
    if ($hash === '' || $token === '') {
        return false;
    }
    return hash_equals($hash, hash('sha256', $token));
}

/** Daftar user staf aktif (admin + writer) untuk dropdown penulis default. */
function ingestStaffUsers(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id, name, email, role FROM users WHERE is_active = 1
         ORDER BY (role = 'admin') DESC, name ASC"
    )->fetchAll();
}

/**
 * Penulis default untuk artikel masuk: id terkonfigurasi bila valid & aktif,
 * jika tidak → admin aktif pertama, jika tidak ada → null (author_id NULL).
 */
function ingestResolveAuthorId(PDO $pdo): ?int
{
    $cfg = (int) getSetting('ingest_author_id', '0');
    if ($cfg > 0) {
        $st = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
        $st->execute([$cfg]);
        if ($st->fetchColumn() !== false) {
            return $cfg;
        }
    }
    $fallback = $pdo->query(
        "SELECT id FROM users WHERE is_active = 1 AND role = 'admin' ORDER BY id ASC LIMIT 1"
    )->fetchColumn();
    return $fallback !== false ? (int) $fallback : null;
}

/** URL endpoint absolut (untuk ditampilkan + contoh di admin). */
function ingestEndpointUrl(): string
{
    return rtrim(APP_URL, '/') . '/api/ingest-article.php';
}

/**
 * Hubungkan tag ke artikel (buat tag baru bila belum ada). $names = array string.
 * Reuse pola save-tag: cari by name, else uniqueSlug + INSERT.
 */
function ingestSyncTags(PDO $pdo, int $articleId, array $names): void
{
    $clean = [];
    foreach ($names as $n) {
        $n = trim((string) $n);
        if ($n !== '') {
            $clean[mb_strtolower($n)] = $n; // dedup case-insensitive
        }
    }
    if (!$clean) {
        return;
    }
    $ins = $pdo->prepare("INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (?, ?)");
    foreach ($clean as $name) {
        $st = $pdo->prepare("SELECT id FROM tags WHERE name = ? LIMIT 1");
        $st->execute([$name]);
        $tid = $st->fetchColumn();
        if ($tid === false) {
            $slug = uniqueSlug($pdo, 'tags', $name);
            $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
            $tid = (int) $pdo->lastInsertId();
        }
        $ins->execute([$articleId, (int) $tid]);
    }
}

/** Ambil token dari header Authorization: Bearer (fallback lintas-server). Kosong bila tak ada. */
function ingestBearerToken(): string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    if ($auth === '' && function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        $auth = $h['Authorization'] ?? ($h['authorization'] ?? '');
    }
    return preg_match('/^Bearer\s+(.+)$/i', trim((string) $auth), $m) ? trim($m[1]) : '';
}

/** Kirim respon JSON lalu berhenti (endpoint mesin, tak bocorkan detail internal). */
function ingestApiSend(int $code, array $payload): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Guard bersama endpoint API (categories/settings/articles/article-cta/content-health/seo-rules). Cek
 * berurutan lalu exit JSON bila gagal: rate-limit → token → master switch
 * (ingest_enabled) → khusus TULIS butuh ingest_manage_enabled.
 * @param bool $writeAction true bila aksi menulis (butuh gate manajemen).
 */
function ingestApiAuthorize(bool $writeAction): void
{
    if (!ingestRateLimitOk()) {
        ingestApiSend(429, ['ok' => false, 'error' => 'Terlalu banyak permintaan. Coba lagi sebentar.']);
    }
    $token = ingestBearerToken();
    if ($token === '') {
        ingestApiSend(401, ['ok' => false, 'error' => 'Token tidak ada. Sertakan header Authorization: Bearer <token>.']);
    }
    if (!ingestTokenIsSet() || !ingestVerifyToken($token)) {
        ingestLog('401 auth gagal ip=' . ($_SERVER['REMOTE_ADDR'] ?? '-'));
        ingestApiSend(401, ['ok' => false, 'error' => 'Token tidak valid.']);
    }
    if (!ingestEnabled()) {
        ingestApiSend(403, ['ok' => false, 'error' => 'Ingest API nonaktif.']);
    }
    if ($writeAction && !ingestManageEnabled()) {
        ingestApiSend(403, ['ok' => false, 'error' => 'API manajemen nonaktif. Aktifkan di Pengaturan → Integrasi.']);
    }
}

/** Log satu baris permintaan ingest (TANPA token) ke cache/logs/ingest.log. */
function ingestLog(string $line): void
{
    $dir = __DIR__ . '/../cache/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }
    @file_put_contents(
        $dir . '/ingest.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Rate-limit fixed-window sederhana (default 30 permintaan / 60 detik) berbagi
 * satu bucket file. Return true bila diizinkan. Non-fatal: bila FS gagal →
 * izinkan (jangan mematikan endpoint karena masalah disk).
 */
function ingestRateLimitOk(int $max = 30, int $windowSec = 60): bool
{
    $dir = __DIR__ . '/../cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/ingest-rate.json';
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return true;
    }
    @flock($fp, LOCK_EX);
    $now  = time();
    $data = json_decode((string) stream_get_contents($fp), true);
    if (!is_array($data) || ($now - (int) ($data['start'] ?? 0)) >= $windowSec) {
        $data = ['start' => $now, 'count' => 0];
    }
    $data['count'] = (int) $data['count'] + 1;
    $ok = $data['count'] <= $max;
    @ftruncate($fp, 0);
    @rewind($fp);
    @fwrite($fp, json_encode($data));
    @flock($fp, LOCK_UN);
    @fclose($fp);
    return $ok;
}
