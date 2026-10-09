<?php
// ════════════════════════════════════════════════════════════════════════
// Fallback cover generator (Track B / B-02). SVG deterministik dari slug:
// hash slug → varian gradient (selaras --accent + netral) + pattern geometri +
// overlay judul (serif/Fraunces, wrap 2-3 baris, ellipsis, scrim kontras).
// Vektor → tak perlu srcset. Judul = input user → di-escape XML (ENT_XML1).
// Deterministik: slug sama → SVG identik (byte-for-byte).
// ════════════════════════════════════════════════════════════════════════

/** Path relatif fallback cover untuk sebuah slug (di dalam uploads/). */
function scribeFallbackCoverPath(string $slug): string
{
    return 'covers/' . (slugify($slug) ?: 'cover') . '.svg';
}

/**
 * Palet gradient (from,to) — SATU karakter visual, saturasi rendah/muted agar
 * kalem & editorial (bukan warna mencolok). Deterministik per-slug (hash).
 */
function _scribeCoverPalettes(): array
{
    return [
        ['#2f7d78', '#1a4f57'], // teal muted
        ['#33506e', '#1e3149'], // navy
        ['#4a6c9b', '#324f78'], // muted blue
        ['#655a8c', '#453c63'], // soft purple
        ['#bd6b40', '#8f4b2c'], // warm orange (terakota)
        ['#3d8a68', '#2a5f47'], // emerald low-sat
    ];
}

/**
 * Bangun string SVG fallback cover (tidak menulis file). Deterministik dari slug.
 * @param string $slug  penentu varian (hash)
 * @param string $title judul artikel (di-escape XML)
 */
function scribeBuildFallbackCoverSvg(string $slug, string $title): string
{
    $seed     = crc32($slug !== '' ? $slug : 'cover');
    $palettes = _scribeCoverPalettes();
    [$c1, $c2] = $palettes[$seed % count($palettes)];
    $pattern  = (int) (($seed >> 3) % 3); // 0 lingkaran, 1 garis, 2 grid dots
    $angle    = (($seed >> 5) % 2) ? 135 : 115;

    // Arah gradient dari sudut.
    $rad = deg2rad($angle);
    $x2  = round(50 + 50 * cos($rad), 2);
    $y2  = round(50 + 50 * sin($rad), 2);

    $W = 1600; $H = 900;
    $esc = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');

    // ── Layer pattern (putih transparan) ──
    $shapes = '';
    if ($pattern === 0) {
        // Lingkaran besar, posisi dari seed.
        $cx = 1200 + ($seed % 300); $cy = 200 + (($seed >> 7) % 200);
        $shapes .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="360" fill="#ffffff" opacity="0.07"/>';
        $shapes .= '<circle cx="' . ($cx - 120) . '" cy="' . ($cy + 80) . '" r="220" fill="#ffffff" opacity="0.06"/>';
        $shapes .= '<circle cx="220" cy="760" r="260" fill="#000000" opacity="0.06"/>';
    } elseif ($pattern === 1) {
        // Garis diagonal.
        $shapes .= '<g stroke="#ffffff" stroke-width="14" opacity="0.06">';
        for ($i = -2; $i < 10; $i++) {
            $x = $i * 200 + ($seed % 120);
            $shapes .= '<line x1="' . $x . '" y1="0" x2="' . ($x + 500) . '" y2="' . $H . '"/>';
        }
        $shapes .= '</g>';
    } else {
        // Grid titik (pattern def).
        $shapes .= '<defs><pattern id="dots" width="60" height="60" patternUnits="userSpaceOnUse">'
                 . '<circle cx="6" cy="6" r="6" fill="#ffffff" opacity="0.08"/></pattern></defs>'
                 . '<rect width="' . $W . '" height="' . $H . '" fill="url(#dots)"/>';
    }

    // ── Judul: wrap 2-3 baris + ellipsis ──
    $lines   = _scribeWrapTitle($title, 22, 3);
    $fs      = 104; $lh = 122;
    $blockH  = count($lines) * $lh;
    $startY  = (int) round(($H - $blockH) / 2 + $fs * 0.78) + 30;
    $tspans  = '';
    foreach ($lines as $i => $ln) {
        $tspans .= '<tspan x="110" y="' . ($startY + $i * $lh) . '">' . $esc($ln) . '</tspan>';
    }

    return
'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H . '" width="' . $W . '" height="' . $H . '" role="img">'
. '<defs>'
. '<linearGradient id="bg" x1="0%" y1="0%" x2="' . $x2 . '%" y2="' . $y2 . '%">'
. '<stop offset="0%" stop-color="' . $c1 . '"/><stop offset="100%" stop-color="' . $c2 . '"/></linearGradient>'
. '<linearGradient id="scrim" x1="0" y1="0" x2="0" y2="1">'
. '<stop offset="45%" stop-color="#000000" stop-opacity="0"/><stop offset="100%" stop-color="#000000" stop-opacity="0.42"/></linearGradient>'
. '</defs>'
. '<rect width="' . $W . '" height="' . $H . '" fill="url(#bg)"/>'
. $shapes
. '<rect width="' . $W . '" height="' . $H . '" fill="url(#scrim)"/>'
. '<text font-family="Fraunces, Georgia, ' . "'Times New Roman'" . ', serif" font-size="' . $fs . '" font-weight="700" '
. 'fill="#ffffff" style="paint-order:stroke" stroke="#000000" stroke-opacity="0.16" stroke-width="2">'
. $tspans
. '</text>'
. '</svg>';
}

/**
 * Wrap judul jadi array baris (greedy), maks $maxLines. Baris melebihi →
 * baris terakhir dipotong + ellipsis. Kata sangat panjang dipenggal keras.
 */
function _scribeWrapTitle(string $title, int $maxChars, int $maxLines): array
{
    $title = trim(preg_replace('/\s+/', ' ', $title));
    if ($title === '') return ['Tanpa Judul'];
    $words = explode(' ', $title);
    $lines = [];
    $cur   = '';
    foreach ($words as $w) {
        // Kata lebih panjang dari lebar baris → penggal keras.
        while (mb_strlen($w) > $maxChars) {
            if ($cur !== '') { $lines[] = $cur; $cur = ''; }
            if (count($lines) >= $maxLines) break 2;
            $lines[] = mb_substr($w, 0, $maxChars - 1) . '-';
            $w = mb_substr($w, $maxChars - 1);
        }
        $try = $cur === '' ? $w : $cur . ' ' . $w;
        if (mb_strlen($try) <= $maxChars) {
            $cur = $try;
        } else {
            if ($cur !== '') $lines[] = $cur;
            $cur = $w;
            if (count($lines) >= $maxLines) break;
        }
    }
    if ($cur !== '' && count($lines) < $maxLines) $lines[] = $cur;

    // Bila masih ada sisa (judul lebih panjang dari kapasitas) → ellipsis.
    $joined = implode(' ', $lines);
    if (mb_strlen($joined) < mb_strlen($title) && $lines) {
        $last = rtrim($lines[count($lines) - 1]);
        if (mb_strlen($last) > $maxChars - 1) $last = mb_substr($last, 0, $maxChars - 1);
        $lines[count($lines) - 1] = rtrim($last) . '…';
    }
    return $lines;
}

/**
 * Tulis file SVG fallback ke uploads/covers/<slug>.svg. Return path relatif
 * (mis. "covers/foo.svg") atau null bila gagal tulis.
 */
function scribeGenerateFallbackCover(string $slug, string $title): ?string
{
    $rel = scribeFallbackCoverPath($slug);
    $dir = rtrim(UPLOAD_PATH, '/\\') . '/covers';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        error_log('cover-fallback: gagal buat folder covers');
        return null;
    }
    $svg = scribeBuildFallbackCoverSvg($slug, $title);
    $abs = rtrim(UPLOAD_PATH, '/\\') . '/' . $rel;
    if (@file_put_contents($abs, $svg) === false) {
        error_log('cover-fallback: gagal tulis ' . $rel);
        return null;
    }
    return $rel;
}

/**
 * Pastikan file fallback ada (buat bila belum). Dipakai render publik sebagai
 * jaring pengaman self-heal (tanpa tulis DB). Return path relatif atau null.
 */
function scribeEnsureFallbackCover(string $slug, string $title): ?string
{
    $rel = scribeFallbackCoverPath($slug);
    $abs = rtrim(UPLOAD_PATH, '/\\') . '/' . $rel;
    if (is_file($abs)) return $rel;
    return scribeGenerateFallbackCover($slug, $title);
}

/** Hapus file fallback SVG (aman: hanya .svg di dalam covers/). */
function scribeDeleteFallbackCover(?string $rel): void
{
    if (!$rel) return;
    $rel = ltrim($rel, '/');
    if (!str_starts_with($rel, 'covers/') || !str_ends_with(strtolower($rel), '.svg')) return;
    $abs = rtrim(UPLOAD_PATH, '/\\') . '/' . $rel;
    if (is_file($abs)) @unlink($abs);
}

/**
 * Backfill: artikel tanpa cover_image (atau fallback yang filenya hilang) →
 * generate SVG + set cover_image/cover_is_fallback. Untuk instalasi customer
 * existing (jalan sekali saat update) dan seed lama. Return jumlah diproses.
 *
 * $force = true → REGENERASI ulang semua cover fallback walau filenya sudah ada
 * (mis. setelah palet warna berubah). Cover asli (upload user) TETAP tidak
 * disentuh. Dipakai tombol admin "Regenerasi cover".
 */
function scribeBackfillFallbackCovers(PDO $pdo, ?callable $log = null, bool $force = false): int
{
    $rows = $pdo->query("SELECT id, slug, title, cover_image, cover_is_fallback FROM articles")->fetchAll();
    $n = 0;
    $upd = $pdo->prepare("UPDATE articles SET cover_image = ?, cover_is_fallback = 1 WHERE id = ?");
    foreach ($rows as $a) {
        $hasReal = !empty($a['cover_image']) && (int) $a['cover_is_fallback'] === 0;
        if ($hasReal) continue; // JANGAN sentuh cover asli upload user
        $abs = rtrim(UPLOAD_PATH, '/\\') . '/' . scribeFallbackCoverPath($a['slug']);
        $needsRow = empty($a['cover_image']);
        if (!$force && !$needsRow && is_file($abs)) continue; // sudah fallback + file ada
        $rel = scribeGenerateFallbackCover((string) $a['slug'], (string) $a['title']);
        if ($rel !== null) {
            $upd->execute([$rel, (int) $a['id']]);
            $n++;
            if ($log) $log($a['id'] . ' ' . $rel);
        }
    }
    return $n;
}
