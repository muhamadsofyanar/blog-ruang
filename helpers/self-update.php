<?php
// ════════════════════════════════════════════════════════════════════════
// Self-update client (pola Averion). Kontrak license server per-produk 'seo':
//   POST {LICENSE_SERVER_URL}/seo/check-update  (header X-License-Key)
//   GET  {LICENSE_SERVER_URL}/seo/download-update?token=...
// Langkah bertahap (anti-timeout): download → backup → ekstrak → migrasi.
// EXCLUDE saat ekstrak/backup: config.php, uploads/, cache/, storage/,
// installer/install.lock, .dev-mode (marker dev).
// ════════════════════════════════════════════════════════════════════════

if (!defined('SCRIBE_UPDATE_TMP'))    define('SCRIBE_UPDATE_TMP', __DIR__ . '/../storage/update-tmp');
if (!defined('SCRIBE_UPDATE_BACKUP')) define('SCRIBE_UPDATE_BACKUP', __DIR__ . '/../storage/update-backups');

/**
 * URL endpoint update produk seo. Endpoint SEO update di license server berada
 * di ROOT ({vendor}/seo/...), BUKAN di bawah /api — sedangkan LICENSE_SERVER_URL
 * memuat suffix /api (dipakai validasi lisensi /api/validate dll). Jadi buang
 * '/api' di ujung sebelum menambahkan '/seo/'. (Fix B-05: sebelumnya menghasilkan
 * /api/seo/ → 404.)
 */
function scribeUpdateEndpoint(string $path): string
{
    $root = rtrim(LICENSE_SERVER_URL, '/');
    $root = preg_replace('#/api$#', '', $root);
    return $root . '/seo/' . ltrim($path, '/');
}

/** Path relatif yang TIDAK boleh ditimpa/di-backup saat update. */
function scribeUpdateExcluded(string $rel): bool
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '') return true;
    $exact = ['config.php', '.dev-mode', 'installer/install.lock'];
    if (in_array($rel, $exact, true)) return true;
    foreach (['uploads/', 'cache/', 'storage/'] as $p) {
        if (str_starts_with($rel, $p)) return true;
    }
    return false;
}

/** Root instalasi (folder di atas helpers/). */
function scribeAppRoot(): string { return dirname(__DIR__); }

/**
 * Cek pembaruan ke license server. Return array respons (has_update, ...) atau
 * ['error'=>msg].
 */
function scribeUpdateCheck(): array
{
    $key = (string) getSetting('license_key', '');
    if ($key === '') return ['error' => 'Lisensi belum aktif.'];

    // Server update butuh format x.y.z. Instalasi dev memakai APP_VERSION
    // "x.y.z-dev" → buang suffix pra-rilis agar diterima (kirim versi basis).
    $ver = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
    $ver = preg_replace('/-.*$/', '', $ver);
    $body = json_encode([
        'current_version' => $ver,
        'domain'          => function_exists('parseDomainFromAppUrl') ? parseDomainFromAppUrl() : '',
    ]);
    $ch = curl_init(scribeUpdateEndpoint('check-update'));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-License-Key: ' . $key],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $err !== '') return ['error' => 'Tidak bisa menghubungi server pembaruan.'];

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) return ['error' => 'Respons server pembaruan tidak valid.'];
    return $data;
}

/**
 * Unduh ZIP paket via download_token → simpan ke storage/update-tmp. Verifikasi
 * checksum MD5 bila disediakan. Return ['ok'=>true,'zip'=>path] atau ['error'].
 */
function scribeUpdateDownload(string $token, string $md5 = ''): array
{
    if (!is_dir(SCRIBE_UPDATE_TMP) && !@mkdir(SCRIBE_UPDATE_TMP, 0750, true)) {
        return ['error' => 'Folder update-tmp tidak bisa dibuat.'];
    }
    $zip = rtrim(SCRIBE_UPDATE_TMP, '/\\') . '/update-' . date('YmdHis') . '.zip';
    $fp  = @fopen($zip, 'wb');
    if (!$fp) return ['error' => 'Tidak bisa menulis file sementara.'];

    $ch = curl_init(scribeUpdateEndpoint('download-update') . '?token=' . rawurlencode($token));
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $ok   = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $code !== 200) { @unlink($zip); return ['error' => 'Unduhan gagal (HTTP ' . $code . ').']; }
    if ($md5 !== '' && strtolower(md5_file($zip)) !== strtolower($md5)) {
        @unlink($zip);
        return ['error' => 'Checksum tidak cocok — unduhan rusak.'];
    }
    // Pastikan ZIP valid.
    $za = new ZipArchive();
    if ($za->open($zip) !== true) { @unlink($zip); return ['error' => 'File update bukan ZIP valid.']; }
    $za->close();

    return ['ok' => true, 'zip' => $zip];
}

/**
 * Backup ringan: file yang AKAN ditimpa (ada di ZIP dan ada di instalasi, tidak
 * di-exclude) disalin ke storage/update-backups/<ts>. Return ['ok','dir','count'].
 */
function scribeUpdateBackup(string $zipPath): array
{
    $za = new ZipArchive();
    if ($za->open($zipPath) !== true) return ['error' => 'Gagal membuka ZIP untuk backup.'];

    $dir = rtrim(SCRIBE_UPDATE_BACKUP, '/\\') . '/' . date('YmdHis');
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) { $za->close(); return ['error' => 'Folder backup tidak bisa dibuat.']; }

    $root = scribeAppRoot();
    $count = 0;
    for ($i = 0; $i < $za->numFiles; $i++) {
        $name = $za->getNameIndex($i);
        if ($name === false || str_ends_with($name, '/')) continue;
        if (scribeUpdateExcluded($name)) continue;
        $target = $root . '/' . $name;
        if (is_file($target)) {
            $dest = $dir . '/' . $name;
            @mkdir(dirname($dest), 0750, true);
            if (@copy($target, $dest)) $count++;
        }
    }
    $za->close();
    return ['ok' => true, 'dir' => $dir, 'count' => $count];
}

/**
 * Ekstrak ZIP ke root instalasi dengan EXCLUDE. Return ['ok','written'] / ['error'].
 */
function scribeUpdateExtract(string $zipPath): array
{
    $za = new ZipArchive();
    if ($za->open($zipPath) !== true) return ['error' => 'Gagal membuka ZIP untuk ekstrak.'];

    $root = rtrim(str_replace('\\', '/', scribeAppRoot()), '/');
    $written = 0;
    for ($i = 0; $i < $za->numFiles; $i++) {
        $name = $za->getNameIndex($i);
        if ($name === false) continue;
        $rel = ltrim(str_replace('\\', '/', $name), '/');
        if ($rel === '' || str_contains($rel, '..')) continue; // cegah traversal
        if (str_ends_with($rel, '/')) continue;
        if (scribeUpdateExcluded($rel)) continue;

        $target = $root . '/' . $rel;
        // Pastikan target tetap di dalam root.
        $targetReal = str_replace('\\', '/', $target);
        if (!str_starts_with($targetReal, $root . '/')) continue;

        @mkdir(dirname($target), 0750, true);
        $stream = $za->getStream($name);
        if (!$stream) continue;
        $out = @fopen($target, 'wb');
        if ($out) {
            stream_copy_to_stream($stream, $out);
            fclose($out);
            $written++;
        }
        fclose($stream);
    }
    $za->close();
    return ['ok' => true, 'written' => $written];
}
