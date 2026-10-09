<?php
// ════════════════════════════════════════════════════════════════════════
// Management API — /api/settings.php  (SEO/brand global, WHITELIST KETAT)
//   GET  : baca HANYA key whitelist (butuh ingest_enabled).
//   POST : update HANYA key whitelist (butuh ingest_manage_enabled). Key di luar
//          whitelist → 400 (tolak seluruh permintaan, tak terapkan sebagian).
// Perubahan LANGSUNG LIVE (tak ada tahap draft). Endpoint mesin: Bearer token.
// Whitelist di helpers/ingest.php → INGEST_SETTINGS_WHITELIST.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/page-cache.php';
require_once __DIR__ . '/../helpers/sitemap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/** Nilai setting untuk output: banned_words dikembalikan sebagai array. */
function ingestSettingOut(string $key): mixed
{
    if ($key === 'brandvoice_banned_words') {
        return json_decode((string) getSetting($key, '[]'), true) ?: [];
    }
    return getSetting($key, '');
}

// ─── GET: baca whitelist ─────────────────────────────────────────
if ($method === 'GET') {
    ingestApiAuthorize(false);
    $out = [];
    foreach (INGEST_SETTINGS_WHITELIST as $k) {
        $out[$k] = ingestSettingOut($k);
    }
    ingestApiSend(200, ['ok' => true, 'settings' => $out]);
}

// ─── POST: update whitelist ──────────────────────────────────────
if ($method !== 'POST') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Gunakan GET atau POST.']);
}
$ctype = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($ctype, 'application/json') === false) {
    ingestApiSend(415, ['ok' => false, 'error' => 'Content-Type harus application/json.']);
}
ingestApiAuthorize(true);

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in) || $in === []) {
    ingestApiSend(400, ['ok' => false, 'error' => 'Body JSON tidak valid atau kosong.']);
}

// TOLAK MUTLAK: key apa pun di luar whitelist → 400 (tak terapkan apa pun).
$unknown = array_diff(array_keys($in), INGEST_SETTINGS_WHITELIST);
if ($unknown) {
    ingestApiSend(400, [
        'ok' => false,
        'error' => 'Key di luar whitelist ditolak: ' . implode(', ', $unknown),
        'allowed' => INGEST_SETTINGS_WHITELIST,
    ]);
}

// Grup penyimpanan per key (metadata; getSetting tak bergantung grup).
$group = static function (string $k): string {
    if ($k === 'default_language') return 'ai';
    if (str_starts_with($k, 'brandvoice_')) return 'brandvoice';
    return 'rebrand';
};

$updated = [];
try {
    foreach ($in as $key => $val) {
        switch ($key) {
            case 'default_language':
                if (!isValidLanguage(is_string($val) ? $val : '')) {
                    ingestApiSend(422, ['ok' => false, 'error' => 'default_language tidak valid (id|en|ms).']);
                }
                setSetting($key, $val, 'ai');
                break;

            case 'brandvoice_tone':
                $tone = is_string($val) ? $val : '';
                if (!in_array($tone, ['', 'santai', 'profesional', 'edukatif', 'persuasif'], true)) {
                    ingestApiSend(422, ['ok' => false, 'error' => 'brandvoice_tone tidak valid.']);
                }
                setSetting($key, $tone, 'brandvoice');
                break;

            case 'brandvoice_banned_words':
                // Terima array ATAU string (dipisah baris/koma) → simpan array JSON.
                if (is_array($val)) {
                    $words = $val;
                } else {
                    $words = preg_split('/\r\n|\r|\n|,/', (string) $val) ?: [];
                }
                $words = array_values(array_filter(array_map(
                    fn($w) => mb_substr(trim((string) $w), 0, 60), $words
                ), fn($w) => $w !== ''));
                if (count($words) > 200) {
                    ingestApiSend(422, ['ok' => false, 'error' => 'brandvoice_banned_words terlalu banyak (maks 200).']);
                }
                setSetting($key, json_encode(array_values($words)), 'brandvoice');
                break;

            default:
                // Key teks: harus skalar, batasi panjang wajar per key.
                if (is_array($val)) {
                    ingestApiSend(422, ['ok' => false, 'error' => $key . ' harus berupa teks.']);
                }
                $limits = [
                    'blog_name' => 150, 'blog_tagline' => 255, 'brand_og_default' => 255,
                    'brandvoice_audience' => 190, 'brandvoice_business' => 2000,
                    'brandvoice_style_sample' => 2000,
                ];
                $max = $limits[$key] ?? 500;
                $clean = mb_substr(str_replace(["\r", "\0"], '', trim((string) $val)), 0, $max);
                setSetting($key, $clean, $group($key));
                break;
        }
        $updated[] = $key;
    }
} catch (Throwable $e) {
    error_log('api/settings: ' . $e->getMessage());
    ingestLog('500 settings-gagal: ' . $e->getMessage());
    ingestApiSend(500, ['ok' => false, 'error' => 'Gagal menyimpan setelan.']);
}

// Perubahan brand/SEO memengaruhi halaman publik → flush cache + regen sitemap.
pageCacheFlushAll();
scribeGenerateSitemap();

ingestLog('setelan-diubah keys=' . implode(',', $updated));
ingestApiSend(200, [
    'ok' => true,
    'updated' => $updated,
    'warning' => 'Setelan langsung aktif (LIVE) di situs publik.',
]);
