<?php
// ════════════════════════════════════════════════════════════════════════
// File cache halaman publik (Track B / B-04). TANPA cron. Simpan HTML render
// di cache/pages/<hash>.html, TTL 10 menit + invalidate TARGETED saat konten
// berubah. BYPASS bila sesi admin/writer login, query ?preview, atau ada flash
// 'subscribe' pending (halaman ber-flash JANGAN ditulis ke cache). Header
// X-Scribe-Cache: HIT|MISS|BYPASS|OFF untuk debug. Toggle dark/light = client
// localStorage (HTML sama) → aman di-cache.
// ════════════════════════════════════════════════════════════════════════

if (!defined('PAGE_CACHE_TTL')) define('PAGE_CACHE_TTL', 600); // 10 menit

function _pcDir(): string
{
    $d = dirname(__DIR__) . '/cache/pages';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

function _pcFile(string $key): string
{
    return _pcDir() . '/' . md5($key) . '.html';
}

/** Alasan bypass cache (null bila boleh di-cache). */
function _pcBypassReason(): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return 'method';
    if (isLoggedIn()) return 'session';
    if (isset($_GET['preview'])) return 'preview';
    if (isset($_SESSION['flash']['subscribe'])) return 'flash';
    return null;
}

/**
 * Cek cache untuk $key. HIT → kirim header + isi + EXIT. Selain itu kembalikan
 * state ('miss'|'bypass'|'off'). $cacheable=false → 'off' (tak pernah di-cache).
 */
function pageCacheServe(string $key, bool $cacheable = true): string
{
    if (!$cacheable) { header('X-Scribe-Cache: OFF'); return 'off'; }
    if (($r = _pcBypassReason()) !== null) { header('X-Scribe-Cache: BYPASS'); return 'bypass'; }
    $file = _pcFile($key);
    if (is_file($file) && (time() - filemtime($file)) < PAGE_CACHE_TTL) {
        header('X-Scribe-Cache: HIT');
        header('Content-Type: text/html; charset=UTF-8');
        readfile($file);
        exit;
    }
    header('X-Scribe-Cache: MISS');
    return 'miss';
}

/** Mulai buffering bila state 'miss'. */
function pageCacheBegin(string $key, string $state): void
{
    if ($state !== 'miss') return;
    $GLOBALS['__pc'] = ['file' => _pcFile($key)];
    ob_start();
}

/** Tutup: tulis buffer ke file (atomik) + flush ke klien. */
function pageCacheClose(): void
{
    $st = $GLOBALS['__pc'] ?? null;
    if (!$st) return;
    unset($GLOBALS['__pc']);
    $html = ob_get_contents();
    if ($html !== false && $html !== '') {
        $tmp = $st['file'] . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $html) !== false) {
            @rename($tmp, $st['file']);
        }
    }
    ob_end_flush();
}

// ─── Invalidasi ───────────────────────────────────────────────────────────

/** Hapus cache untuk daftar path logis (mis. '/', '/artikel/foo'). */
function pageCacheInvalidate(array $paths): void
{
    foreach (array_unique($paths) as $p) {
        @unlink(_pcFile($p));
    }
}

/** Kosongkan SELURUH page cache (dipakai saat setting global berubah). */
function pageCacheFlushAll(): void
{
    foreach (glob(_pcDir() . '/*.html') ?: [] as $f) @unlink($f);
}

/** Path home yang di-cache (page 1-2). */
function pageCacheHomePaths(): array
{
    return ['/', '/?page=2'];
}

/**
 * Invalidasi terkait perubahan artikel: artikel itu + home + kategori + tag.
 * $catSlug & $tagSlugs boleh kosong. Artikel LAIN tetap tersimpan (targeted).
 */
function pageCacheInvalidateArticle(string $slug, ?string $catSlug = null, array $tagSlugs = []): void
{
    $paths = pageCacheHomePaths();
    if ($slug !== '') $paths[] = '/artikel/' . $slug;
    if ($catSlug) $paths[] = '/kategori/' . $catSlug;
    foreach ($tagSlugs as $t) if ($t !== '') $paths[] = '/tag/' . $t;
    pageCacheInvalidate($paths);
}
