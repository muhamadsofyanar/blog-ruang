<?php
// Optimasi gambar terpusat. Semua upload raster divalidasi, dibatasi dimensinya,
// dikompresi, lalu disimpan sebagai WebP (favicon raster tetap PNG untuk
// kompatibilitas). Varian responsif memakai pola <nama>-480/-960.<ext>.

if (!defined('COVER_VARIANTS')) define('COVER_VARIANTS', [480, 960]);
if (!defined('SCRIBE_IMAGE_MAX_PIXELS')) define('SCRIBE_IMAGE_MAX_PIXELS', 40000000);

/** MIME raster yang didukung GD. */
function _scribeImageExt(string $mime): ?string
{
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ][$mime] ?? null;
}

/** Muat resource GD dari file sesuai MIME. */
function _scribeLoadImage(string $path, string $mime)
{
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png'  => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        'image/gif'  => @imagecreatefromgif($path),
        default      => false,
    };
}

/** Simpan resource GD dengan kualitas web yang seimbang. */
function _scribeSaveImage($img, string $path, string $ext, int $quality = 82): bool
{
    return match ($ext) {
        'jpg'  => imagejpeg($img, $path, $quality),
        'png'  => imagepng($img, $path, 8),
        'webp' => function_exists('imagewebp') ? imagewebp($img, $path, $quality) : imagejpeg($img, $path, $quality),
        'gif'  => imagegif($img, $path),
        default => false,
    };
}

/** Validasi header gambar tanpa mendekode seluruh bitmap. */
function _scribeRasterInfo(string $path): array
{
    $info = @getimagesize($path);
    if (!$info || empty($info[0]) || empty($info[1]) || empty($info['mime'])) {
        return ['error' => 'Gambar tidak valid atau rusak.'];
    }
    $w = (int) $info[0];
    $h = (int) $info[1];
    if ($w * $h > SCRIBE_IMAGE_MAX_PIXELS) {
        return ['error' => 'Resolusi gambar terlalu besar. Maksimal 40 megapiksel.'];
    }
    if (_scribeImageExt((string) $info['mime']) === null) {
        return ['error' => 'Format gambar tidak didukung (gunakan JPG/PNG/WEBP).'];
    }
    return ['width' => $w, 'height' => $h, 'mime' => (string) $info['mime']];
}

/** Deteksi ringan GIF animasi agar animasi lama tidak hilang saat optimasi. */
function _scribeAnimatedGif(string $path): bool
{
    $data = @file_get_contents($path);
    return is_string($data) && preg_match_all('/\x00\x21\xF9\x04/s', $data) > 1;
}

/** Terapkan orientasi EXIF agar foto ponsel tidak terputar setelah dikompresi. */
function _scribeOrientImage($img, string $path, string $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) return $img;
    $exif = @exif_read_data($path);
    $o = (int) ($exif['Orientation'] ?? 1);
    $next = null;
    if (in_array($o, [2, 4, 5, 7], true) && function_exists('imageflip')) {
        imageflip($img, in_array($o, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
    }
    if ($o === 3 || $o === 4) $next = imagerotate($img, 180, 0);
    elseif ($o === 5 || $o === 6) $next = imagerotate($img, -90, 0);
    elseif ($o === 7 || $o === 8) $next = imagerotate($img, 90, 0);
    if ($next) {
        imagedestroy($img);
        return $next;
    }
    return $img;
}

/** Buat salinan resize berkualitas tinggi dan pertahankan kanal alpha. */
function _scribeResizeImage($src, int $targetW, int $targetH)
{
    $dst = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $transparent);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, imagesx($src), imagesy($src));
    return $dst;
}

/** Hitung dimensi tanpa upscale, dibatasi lebar dan tinggi. */
function _scribeFitDimensions(int $w, int $h, int $maxW, int $maxH): array
{
    $ratio = min(1, $maxW / max(1, $w), $maxH / max(1, $h));
    return [max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio))];
}

/** Proses satu raster menjadi master teroptimasi dan varian responsif. */
function _scribeProcessRaster(
    string $source,
    string $relativeDir,
    string $baseName,
    int $maxW,
    int $maxH,
    array $variants = [],
    string $forceExt = ''
): array {
    $info = _scribeRasterInfo($source);
    if (isset($info['error'])) return $info;

    // GD hanya membaca frame pertama GIF. Pertahankan GIF animasi apa adanya
    // agar fitur yang sudah berjalan tidak berubah.
    if ($info['mime'] === 'image/gif' && _scribeAnimatedGif($source)) {
        $dir = rtrim(UPLOAD_PATH, '/\\') . '/' . trim($relativeDir, '/\\');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['error' => 'Folder upload tidak bisa dibuat.'];
        $baseName = preg_replace('/[^a-z0-9_-]/', '-', strtolower($baseName)) ?: 'image';
        $target = $dir . '/' . $baseName . '.gif';
        if (!@copy($source, $target)) return ['error' => 'Gagal menyimpan GIF animasi.'];
        return [
            'path' => trim($relativeDir, '/\\') . '/' . $baseName . '.gif',
            'variants' => [], 'width' => $info['width'], 'height' => $info['height'], 'format' => 'gif',
        ];
    }

    $src = _scribeLoadImage($source, $info['mime']);
    if (!$src) return ['error' => 'Gambar tidak bisa diproses oleh server.'];
    $src = _scribeOrientImage($src, $source, $info['mime']);
    $srcW = imagesx($src);
    $srcH = imagesy($src);
    [$outW, $outH] = _scribeFitDimensions($srcW, $srcH, $maxW, $maxH);

    $ext = $forceExt !== '' ? $forceExt : (function_exists('imagewebp') ? 'webp' : (_scribeImageExt($info['mime']) ?: 'jpg'));
    $dir = rtrim(UPLOAD_PATH, '/\\') . '/' . trim($relativeDir, '/\\');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        imagedestroy($src);
        return ['error' => 'Folder upload tidak bisa dibuat.'];
    }

    $baseName = preg_replace('/[^a-z0-9_-]/', '-', strtolower($baseName)) ?: 'image';
    $master = ($outW === $srcW && $outH === $srcH) ? $src : _scribeResizeImage($src, $outW, $outH);
    $masterAbs = $dir . '/' . $baseName . '.' . $ext;
    if (!_scribeSaveImage($master, $masterAbs, $ext)) {
        if ($master !== $src) imagedestroy($master);
        imagedestroy($src);
        return ['error' => 'Gagal menyimpan gambar teroptimasi.'];
    }

    $made = [];
    foreach (array_values(array_unique(array_map('intval', $variants))) as $variantW) {
        if ($variantW < 1 || $outW <= $variantW) continue;
        $variantH = max(1, (int) round($outH * ($variantW / $outW)));
        $dst = _scribeResizeImage($master, $variantW, $variantH);
        $vAbs = $dir . '/' . $baseName . '-' . $variantW . '.' . $ext;
        if (_scribeSaveImage($dst, $vAbs, $ext)) {
            $made[$variantW] = trim($relativeDir, '/\\') . '/' . $baseName . '-' . $variantW . '.' . $ext;
        }
        imagedestroy($dst);
    }

    if ($master !== $src) imagedestroy($master);
    imagedestroy($src);
    return [
        'path' => trim($relativeDir, '/\\') . '/' . $baseName . '.' . $ext,
        'variants' => $made,
        'width' => $outW,
        'height' => $outH,
        'format' => $ext,
    ];
}

/** Profil dimensi per konteks agar file tidak lebih besar dari kebutuhan UI. */
function scribeImageProfile(string $kind): array
{
    return match ($kind) {
        'logo'       => [320, 320, [], ''],
        'favicon'    => [128, 128, [], 'png'],
        'og_default' => [1200, 630, [600], ''],
        'promo'      => [960, 540, [480], ''],
        'avatar'     => [320, 320, [160], ''],
        'header'     => [1600, 600, [480, 960], ''],
        'intro'      => [960, 960, [480], ''],
        'btn'        => [160, 160, [], ''],
        'img'        => [1200, 1200, [480, 960], ''],
        'content'    => [1600, 1600, [480, 960], ''],
        'cover'      => [1600, 1600, COVER_VARIANTS, ''],
        default      => [1200, 1200, [480, 960], ''],
    };
}

/** Proses upload cover menjadi WebP + varian 480/960. */
function saveCoverImage(array $file, string $slug): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload cover gagal (kode ' . ($file['error'] ?? '?') . ').'];
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        return ['error' => 'Ukuran cover melebihi batas ' . round(MAX_UPLOAD_SIZE / 1048576, 1) . 'MB.'];
    }
    [$maxW, $maxH, $variants, $forceExt] = scribeImageProfile('cover');
    $base = (slugify($slug) ?: 'cover') . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    return _scribeProcessRaster($file['tmp_name'], 'covers', $base, $maxW, $maxH, $variants, $forceExt);
}

/**
 * Hapus berkas cover ASLI (master + varian responsif -480/-960) dari uploads/covers.
 * Guard: hanya path di covers/ dan BUKAN .svg (fallback SVG ditangani
 * scribeDeleteFallbackCover). Aman dipanggil berkali-kali.
 */
function scribeDeleteCoverFile(?string $rel): void
{
    $rel = ltrim((string) $rel, '/');
    if ($rel === '' || !str_starts_with($rel, 'covers/') || str_contains($rel, '..')) return;
    if (str_ends_with(strtolower($rel), '.svg')) return; // fallback SVG pakai helper lain
    $dot = strrpos($rel, '.');
    if ($dot === false) return;
    $base = rtrim(UPLOAD_PATH, '/\\') . '/';
    $stem = substr($rel, 0, $dot);   // covers/xxx
    $ext  = substr($rel, $dot);      // .webp
    @unlink($base . str_replace('/', DIRECTORY_SEPARATOR, $rel)); // master
    foreach (COVER_VARIANTS as $vw) {
        @unlink($base . str_replace('/', DIRECTORY_SEPARATOR, $stem . '-' . $vw . $ext)); // varian
    }
}

/** Simpan branding; raster dioptimalkan, SVG/ICO dipertahankan. */
function saveBrandingImage(array $file, string $key): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['error' => 'Upload gagal.'];
    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        return ['error' => 'Ukuran file melebihi batas ' . round(MAX_UPLOAD_SIZE / 1048576, 1) . 'MB.'];
    }
    $key = preg_replace('/[^a-z0-9_-]/', '', strtolower($key)) ?: 'brand';
    $originalExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($originalExt, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico'], true)) {
        return ['error' => 'Format tidak didukung (png/jpg/webp/gif/svg/ico).'];
    }
    $dir = rtrim(UPLOAD_PATH, '/\\') . '/branding';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['error' => 'Folder uploads/branding tidak bisa dibuat.'];
    $base = $key . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

    if (in_array($originalExt, ['svg', 'ico'], true)) {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($originalExt === 'svg') {
            $svg = (string) @file_get_contents($file['tmp_name']);
            if (!preg_match('/<svg\b/i', $svg) || preg_match('/<(script|iframe|object|embed)\b|\bon[a-z]+\s*=|javascript:/i', $svg)) {
                return ['error' => 'SVG tidak valid atau mengandung elemen berbahaya.'];
            }
        }
        $name = $base . '.' . $originalExt;
        if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $name) && !@copy($file['tmp_name'], $dir . '/' . $name)) {
            return ['error' => 'Gagal menyimpan file.'];
        }
        return ['path' => 'branding/' . $name];
    }

    [$maxW, $maxH, $variants, $forceExt] = scribeImageProfile($key);
    return _scribeProcessRaster($file['tmp_name'], 'branding', $base, $maxW, $maxH, $variants, $forceExt);
}

/** Simpan gambar BioLink sesuai konteks avatar/header/tombol/gambar. */
function saveBioImage(array $file, string $kind): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['error' => 'Upload gagal.'];
    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        return ['error' => 'Ukuran file melebihi batas ' . round(MAX_UPLOAD_SIZE / 1048576, 1) . 'MB.'];
    }
    $kind = preg_replace('/[^a-z0-9_-]/', '', strtolower($kind)) ?: 'img';
    [$maxW, $maxH, $variants, $forceExt] = scribeImageProfile($kind);
    $base = $kind . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    return _scribeProcessRaster($file['tmp_name'], 'bio', $base, $maxW, $maxH, $variants, $forceExt);
}

/** Hapus aset BioLink beserta varian responsifnya (best effort). */
function scribeDeleteBioUpload(?string $rel): void
{
    $rel = trim((string) $rel);
    if ($rel === '' || !str_starts_with($rel, 'bio/') || str_contains($rel, '..')) return;
    $abs = rtrim(UPLOAD_PATH, '/\\') . '/' . $rel;
    $dot = strrpos($abs, '.');
    if ($dot !== false) {
        $stem = substr($abs, 0, $dot);
        $ext = substr($abs, $dot);
        foreach ([160, 480, 960] as $w) if (is_file($stem . '-' . $w . $ext)) @unlink($stem . '-' . $w . $ext);
    }
    if (is_file($abs)) @unlink($abs);
}

/** Simpan gambar inline editor sebagai WebP terkompresi + varian responsif. */
function saveEditorImage(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['error' => 'Upload gambar gagal.'];
    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) return ['error' => 'Ukuran gambar melebihi batas.'];
    [$maxW, $maxH, $variants, $forceExt] = scribeImageProfile('content');
    $base = date('Ym') . '-' . bin2hex(random_bytes(6));
    $res = _scribeProcessRaster($file['tmp_name'], 'content', $base, $maxW, $maxH, $variants, $forceExt);
    if (isset($res['error'])) return $res;
    $res['rel'] = $res['path'];
    $res['url'] = rtrim(UPLOAD_URL, '/') . '/' . $res['path'];
    return $res;
}

/** Metadata lokal untuk atribut width/height; cache per-request. */
function scribeImageMeta(string $rel): ?array
{
    static $cache = [];
    $rel = ltrim(trim($rel), '/\\');
    if ($rel === '' || str_contains($rel, '..')) return null;
    if (array_key_exists($rel, $cache)) return $cache[$rel];
    $abs = rtrim(UPLOAD_PATH, '/\\') . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $info = is_file($abs) ? @getimagesize($abs) : false;
    return $cache[$rel] = $info ? ['width' => (int) $info[0], 'height' => (int) $info[1], 'mime' => (string) ($info['mime'] ?? '')] : null;
}

/** Bangun <img> upload responsif dengan dimensi aktual, lazy/eager, dan srcset. */
function scribeUploadImageHtml(string $rel, string $alt, string $class, string $sizes = '100vw', bool $eager = false): string
{
    $rel = ltrim($rel, '/\\');
    $meta = scribeImageMeta($rel);
    $url = rtrim(UPLOAD_URL, '/') . '/' . $rel;
    $attrs = '';
    if ($meta) $attrs .= ' width="' . $meta['width'] . '" height="' . $meta['height'] . '"';

    $parts = [];
    $dot = strrpos($rel, '.');
    if ($dot !== false) {
        $stem = substr($rel, 0, $dot);
        $ext = substr($rel, $dot);
        foreach ([160, 480, 600, 960] as $w) {
            $variant = $stem . '-' . $w . $ext;
            $vm = scribeImageMeta($variant);
            if ($vm) $parts[$vm['width']] = rtrim(UPLOAD_URL, '/') . '/' . $variant . ' ' . $vm['width'] . 'w';
        }
        if ($meta) $parts[$meta['width']] = $url . ' ' . $meta['width'] . 'w';
    }
    if (count($parts) > 1) {
        ksort($parts);
        $attrs .= ' srcset="' . e(implode(', ', $parts)) . '" sizes="' . e($sizes) . '"';
    }
    $attrs .= ' loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async"';
    if ($eager) $attrs .= ' fetchpriority="high"';
    return '<img src="' . e($url) . '" alt="' . e($alt) . '"' . $attrs . ' class="' . e($class) . '">';
}

/** Optimalkan satu gambar tersimpan ke file baru, tanpa menghapus sumber. */
function scribeOptimizeStoredImage(string $rel, string $kind): array
{
    $rel = ltrim(trim($rel), '/\\');
    if ($rel === '' || str_contains($rel, '..') || preg_match('/\.(svg|ico)$/i', $rel)) return ['path' => $rel, 'changed' => false];
    $source = rtrim(UPLOAD_PATH, '/\\') . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($source)) return ['error' => 'File tidak ditemukan: ' . $rel];
    [$maxW, $maxH, $variants, $forceExt] = scribeImageProfile($kind);
    $dir = trim(str_replace('\\', '/', dirname($rel)), './');
    $stem = pathinfo($rel, PATHINFO_FILENAME);
    if (str_ends_with($stem, '-optimized')) return ['path' => $rel, 'changed' => false];
    $res = _scribeProcessRaster($source, $dir, $stem . '-optimized', $maxW, $maxH, $variants, $forceExt);
    if (isset($res['error'])) return $res;
    $res['changed'] = $res['path'] !== $rel;
    return $res;
}

/**
 * Optimasi aset kritis yang tampil di homepage: branding, popup, profil, serta
 * blok BioLink. Aman dipanggil saat update karena file sumber tidak dihapus.
 */
function scribeOptimizeCriticalImages(PDO $pdo): array
{
    $changed = 0;
    $errors = [];
    $optSetting = static function (string $setting, string $kind, string $group) use (&$changed, &$errors): void {
        $old = trim((string) getSetting($setting, ''));
        if ($old === '' || str_starts_with($old, '#')) return;
        $res = scribeOptimizeStoredImage($old, $kind);
        if (isset($res['error'])) { $errors[] = $res['error']; return; }
        if (!empty($res['changed'])) {
            setSetting($setting, $res['path'], $group);
            $changed++;
        }
    };
    foreach ([
        ['brand_logo', 'logo', 'rebrand'],
        ['brand_favicon', 'favicon', 'rebrand'],
        ['brand_og_default', 'og_default', 'rebrand'],
        ['promo_image', 'promo', 'promo'],
        ['bio_avatar', 'avatar', 'biolink'],
        ['bio_header_bg', 'header', 'biolink'],
        ['bio_intro_photo', 'intro', 'biolink'],
    ] as [$setting, $kind, $group]) {
        $optSetting($setting, $kind, $group);
    }

    try {
        $rows = $pdo->query('SELECT id, type, config_json FROM bio_blocks')->fetchAll();
        $update = $pdo->prepare('UPDATE bio_blocks SET config_json = ?, updated_at = NOW() WHERE id = ?');
        foreach ($rows as $row) {
            $cfg = json_decode((string) $row['config_json'], true);
            if (!is_array($cfg)) continue;
            $key = $row['type'] === 'button' ? 'photo' : ($row['type'] === 'image' ? 'path' : '');
            if ($key === '' || trim((string) ($cfg[$key] ?? '')) === '') continue;
            $res = scribeOptimizeStoredImage((string) $cfg[$key], $row['type'] === 'button' ? 'btn' : 'img');
            if (isset($res['error'])) { $errors[] = $res['error']; continue; }
            if (!empty($res['changed'])) {
                $cfg[$key] = $res['path'];
                $update->execute([json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $row['id']]);
                $changed++;
            }
        }
    } catch (Throwable $e) {
        error_log('optimize critical biolink: ' . $e->getMessage());
        $errors[] = 'Sebagian gambar BioLink tidak dapat diproses.';
    }
    return ['changed' => $changed, 'errors' => $errors];
}
