<?php
// ════════════════════════════════════════════════════════════════════════
// OG image resolver (Track B / B-03). Crawler FB/Twitter TIDAK andal merender
// SVG → untuk artikel ber-cover FALLBACK (SVG) og:image TIDAK boleh SVG.
// Urutan pilih: (1) cover asli raster → dipakai; (2) brand_og_default (rebrand)
// bila ada; (3) generate PNG sederhana via GD (accent + judul) di uploads/og/,
// ter-cache. Semua URL absolut (UPLOAD_URL berbasis APP_URL).
// ════════════════════════════════════════════════════════════════════════

/** URL absolut OG image untuk sebuah artikel (selalu raster / non-SVG). */
function scribeArticleOgImage(array $a): string
{
    $base   = rtrim(UPLOAD_URL, '/');
    $absDir = rtrim(UPLOAD_PATH, '/\\');
    $cover  = (string) ($a['cover_image'] ?? '');
    $isFb   = !empty($a['cover_is_fallback']);

    // 1) Cover asli raster (bukan fallback SVG) + file ada.
    if ($cover !== '' && !$isFb && !str_ends_with(strtolower($cover), '.svg')
        && is_file($absDir . '/' . $cover)) {
        return $base . '/' . $cover;
    }
    // 2) OG default dari rebrand (bila ada filenya).
    $ogDef = (string) getSetting('brand_og_default', '');
    if ($ogDef !== '' && is_file($absDir . '/' . $ogDef)) {
        return $base . '/' . $ogDef;
    }
    // 3) Generate PNG per-artikel (accent + judul), cache.
    $png = scribeEnsureOgPng((string) ($a['slug'] ?? 'artikel'), (string) ($a['title'] ?? ''));
    if ($png !== null) return $base . '/' . $png;

    // Terakhir: kalau GD gagal, pakai cover apa adanya (mungkin svg) atau kosong.
    return $cover !== '' ? $base . '/' . $cover : '';
}

/** OG image untuk halaman non-artikel (home/kategori/tag): brand_og_default bila ada. */
function scribeSiteOgImage(): string
{
    $ogDef = (string) getSetting('brand_og_default', '');
    if ($ogDef !== '' && is_file(rtrim(UPLOAD_PATH, '/\\') . '/' . $ogDef)) {
        return rtrim(UPLOAD_URL, '/') . '/' . $ogDef;
    }
    return '';
}

/** Pastikan PNG OG ada (buat bila belum). Return path relatif atau null. */
function scribeEnsureOgPng(string $slug, string $title): ?string
{
    if (!function_exists('imagecreatetruecolor')) return null;
    $rel = 'og/' . (slugify($slug) ?: 'artikel') . '.png';
    $abs = rtrim(UPLOAD_PATH, '/\\') . '/' . $rel;
    if (is_file($abs)) return $rel;

    $dir = dirname($abs);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return null;

    $accent = (string) getSetting('accent_color', '#6366f1');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) $accent = '#6366f1';
    [$ar, $ag, $ab] = _scribeHex2Rgb($accent);

    // Gambar di kanvas kecil lalu perbesar (teks bawaan GD → besar & terbaca).
    $sw = 400; $sh = 210;
    $im = imagecreatetruecolor($sw, $sh);
    $bg = imagecolorallocate($im, $ar, $ag, $ab);
    imagefilledrectangle($im, 0, 0, $sw, $sh, $bg);
    // Band gelap diagonal (kedalaman). Signature 3-arg (PHP 8.1+, tanpa num_points).
    $dk = imagecolorallocatealpha($im, 0, 0, 0, 96);
    imagefilledpolygon($im, [0, $sh, $sw, $sh - 70, $sw, $sh, 0, $sh], $dk);
    // Aksen terang pojok.
    $lt = imagecolorallocatealpha($im, 255, 255, 255, 110);
    imagefilledellipse($im, $sw - 30, 20, 220, 220, $lt);

    $white = imagecolorallocate($im, 255, 255, 255);
    $soft  = imagecolorallocatealpha($im, 255, 255, 255, 40);

    // Judul: wrap ~30 char, maks 4 baris, font bawaan terbesar (5 = 9x15px).
    $font = 5; $cw = imagefontwidth($font); $ch = imagefontheight($font);
    $lines = _scribeWrapWords(trim($title) !== '' ? $title : 'Artikel', 30, 4);
    $blockH = count($lines) * ($ch + 5);
    $y = (int) (($sh - $blockH) / 2) - 6;
    foreach ($lines as $ln) {
        $x = (int) (($sw - strlen($ln) * $cw) / 2);
        imagestring($im, $font, max(12, $x), $y, $ln, $white);
        $y += $ch + 5;
    }
    // Nama blog kecil di bawah.
    $bn = function_exists('blogName') ? blogName() : (string) getSetting('blog_name', APP_NAME);
    $bn = mb_substr($bn, 0, 40);
    imagestring($im, 3, 14, $sh - 20, strtoupper($bn), $soft);

    // Perbesar ke 1200x630 (bilinear halus).
    $big = imagescale($im, 1200, 630, IMG_BILINEAR_FIXED);
    imagedestroy($im);
    $ok = $big ? imagepng($big, $abs, 6) : false;
    if ($big) imagedestroy($big);

    return $ok ? $rel : null;
}

/** Wrap teks per kata → array baris (maks $maxLines, kata panjang dipenggal). */
function _scribeWrapWords(string $text, int $maxChars, int $maxLines): array
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    $words = $text === '' ? [] : explode(' ', $text);
    $lines = []; $cur = '';
    foreach ($words as $w) {
        if (strlen($w) > $maxChars) $w = substr($w, 0, $maxChars - 1) . '…';
        $try = $cur === '' ? $w : $cur . ' ' . $w;
        if (strlen($try) <= $maxChars) { $cur = $try; }
        else {
            if ($cur !== '') $lines[] = $cur;
            $cur = $w;
            if (count($lines) >= $maxLines) break;
        }
    }
    if ($cur !== '' && count($lines) < $maxLines) $lines[] = $cur;
    if (count($lines) === $maxLines && $lines) {
        $lines[$maxLines - 1] = rtrim(mb_substr($lines[$maxLines - 1], 0, $maxChars - 1)) . '…';
    }
    return $lines ?: ['Artikel'];
}

function _scribeHex2Rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
