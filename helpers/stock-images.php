<?php
// ════════════════════════════════════════════════════════════════════════
// Stock images (Pexels) — ambil cover & gambar inline otomatis untuk artikel
// yang masuk via Ingest API. Provider legal (Pexels), TANPA hotlink: aset
// diunduh, divalidasi, dioptimasi ke WebP (reuse helpers/media.php), lalu
// disimpan di uploads. Alt-text otomatis (reuse helpers/alt-text.php) tanpa
// menimpa alt yang sudah ada. NON-FATAL: kegagalan provider → artikel tetap
// jalan tanpa gambar + warning.
//
// Settings grup 'stock':
//   pexels_api_key  (RAHASIA, tak pernah di-echo)
//
// KEAMANAN:
//   - API key hanya dikirim ke host Pexels (Authorization header).
//   - Download: skema http(s) saja; TANPA follow-redirect; auto-mode hanya host
//     pexels; url-mode (URL dari Hermes) blokir IP privat/loopback/reserved (SSRF);
//     batas ukuran; validasi header gambar (getimagesize) + re-encode GD (buang
//     payload tersembunyi).
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/media.php';
require_once __DIR__ . '/alt-text.php';

const STOCK_PEXELS_API   = 'https://api.pexels.com/v1/search';
const STOCK_DL_MAX_BYTES = 12 * 1024 * 1024; // batas unduh sebelum re-encode
const STOCK_MAX_INLINE   = 6;
const STOCK_MIN_RELEVANCE = 2.0;             // skor minimal agar aset dipakai

function stockPexelsKey(): string { return trim((string) getSetting('pexels_api_key', '')); }
function stockEnabled(): bool     { return stockPexelsKey() !== ''; }

/** Host milik Pexels (API + CDN gambar). */
function stockIsPexelsHost(string $host): bool
{
    $host = strtolower($host);
    return $host === 'api.pexels.com'
        || $host === 'images.pexels.com'
        || (bool) preg_match('/(^|\.)pexels\.com$/', $host);
}

/**
 * URL aman untuk diunduh? $restrictPexels=true hanya izinkan host Pexels.
 * Selain itu (URL dari Hermes): http(s) + host publik (blokir IP privat/reserved).
 */
function stockUrlDownloadable(string $url, bool $restrictPexels): bool
{
    $p = @parse_url($url);
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) return false;
    $host = strtolower((string) ($p['host'] ?? ''));
    if ($host === '') return false;

    if ($restrictPexels) return stockIsPexelsHost($host);

    // Resolve host → IP, tolak privat/loopback/link-local/reserved (anti-SSRF).
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips = [$host];
    } else {
        foreach ((array) @dns_get_record($host, DNS_A | DNS_AAAA) as $r) {
            if (!empty($r['ip']))   $ips[] = $r['ip'];
            if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
        }
        if (!$ips) { $ip = @gethostbyname($host); if ($ip && $ip !== $host) $ips[] = $ip; }
    }
    if (!$ips) return false;
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    return true;
}

/** cURL GET sederhana. Return [httpCode, body, contentType]. */
function stockHttpGet(string $url, array $headers = [], bool $binary = false): array
{
    if (!function_exists('curl_init')) return [0, '', ''];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $binary ? 25 : 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,            // cegah redirect-based SSRF
        CURLOPT_MAXREDIRS      => 0,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($binary) curl_setopt($ch, CURLOPT_BUFFERSIZE, 65536);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$http, $body === false ? '' : (string) $body, $ctype];
}

/**
 * Cari foto di Pexels. Return ['ok'=>true,'photos'=>[{id,src,width,height,alt}]]
 * atau ['ok'=>false,'error'=>..]. $orientation: landscape|portrait|square.
 */
function stockPexelsSearch(string $query, string $orientation = 'landscape', int $perPage = 12): array
{
    $key = stockPexelsKey();
    if ($key === '') return ['ok' => false, 'error' => 'Pexels API key belum diatur.'];
    $query = trim($query);
    if ($query === '') return ['ok' => false, 'error' => 'Query kosong.'];

    $url = STOCK_PEXELS_API . '?' . http_build_query([
        'query'       => $query,
        'orientation' => in_array($orientation, ['landscape', 'portrait', 'square'], true) ? $orientation : 'landscape',
        'per_page'    => max(1, min(40, $perPage)),
    ]);
    [$http, $body] = stockHttpGet($url, ['Authorization: ' . $key]);
    if ($http !== 200) return ['ok' => false, 'error' => 'Pexels gagal (HTTP ' . $http . ').'];
    $data = json_decode($body, true);
    if (!is_array($data) || !is_array($data['photos'] ?? null)) return ['ok' => false, 'error' => 'Respons Pexels tidak valid.'];

    $photos = [];
    foreach ($data['photos'] as $ph) {
        $src = $ph['src']['large2x'] ?? ($ph['src']['large'] ?? ($ph['src']['original'] ?? ''));
        if (!is_string($src) || $src === '') continue;
        $photos[] = [
            'id'     => (int) ($ph['id'] ?? 0),
            'src'    => $src,
            'width'  => (int) ($ph['width'] ?? 0),
            'height' => (int) ($ph['height'] ?? 0),
            'alt'    => trim((string) ($ph['alt'] ?? '')),
            'url'    => (string) ($ph['url'] ?? ''),
        ];
    }
    return ['ok' => true, 'photos' => $photos];
}

/**
 * Unduh gambar ke file temporer dengan guard SSRF + validasi. Return path temp
 * atau null (+$err terisi). $restrictPexels membatasi ke host Pexels.
 */
function stockDownloadToTemp(string $url, bool $restrictPexels, ?string &$err = null): ?string
{
    $err = null;
    if (!stockUrlDownloadable($url, $restrictPexels)) { $err = 'URL tidak diizinkan.'; return null; }
    [$http, $body, $ctype] = stockHttpGet($url, [], true);
    if ($http !== 200 || $body === '') { $err = 'Unduh gagal (HTTP ' . $http . ').'; return null; }
    if (strlen($body) > STOCK_DL_MAX_BYTES) { $err = 'File terlalu besar.'; return null; }
    if ($ctype !== '' && stripos($ctype, 'image/') !== 0) { $err = 'Bukan gambar (' . $ctype . ').'; return null; }

    $tmp = tempnam(sys_get_temp_dir(), 'stk');
    if ($tmp === false || @file_put_contents($tmp, $body) === false) { $err = 'Gagal menulis temp.'; return null; }
    // Validasi header gambar (bukan hanya content-type).
    $info = @getimagesize($tmp);
    if (!$info || empty($info[0]) || _scribeImageExt((string) ($info['mime'] ?? '')) === null) {
        @unlink($tmp); $err = 'Berkas bukan gambar valid.'; return null;
    }
    return $tmp;
}

/**
 * Center-crop + resize ke 1200x630 (rasio OG), simpan WebP + varian di 'covers'.
 * Return {path,variants,width,height,format} atau {error}.
 */
function stockSaveCover(string $tmp, string $slug): array
{
    $info = _scribeRasterInfo($tmp);
    if (isset($info['error'])) return $info;
    $src = _scribeLoadImage($tmp, $info['mime']);
    if (!$src) return ['error' => 'Gambar cover tidak bisa diproses.'];
    $src = _scribeOrientImage($src, $tmp, $info['mime']);

    $targetW = 1200; $targetH = 630;
    $sw = imagesx($src); $sh = imagesy($src);
    $targetRatio = $targetW / $targetH;
    $srcRatio = $sw / max(1, $sh);
    // Rect sumber ter-crop tengah agar rasio = target.
    if ($srcRatio > $targetRatio) { $cw = (int) round($sh * $targetRatio); $ch = $sh; }
    else                          { $cw = $sw; $ch = (int) round($sw / $targetRatio); }
    $cx = (int) floor(($sw - $cw) / 2); $cy = (int) floor(($sh - $ch) / 2);

    $dst = imagecreatetruecolor($targetW, $targetH);
    imagecopyresampled($dst, $src, 0, 0, $cx, $cy, $targetW, $targetH, $cw, $ch);
    imagedestroy($src);

    $dir = rtrim(UPLOAD_PATH, '/\\') . '/covers';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { imagedestroy($dst); return ['error' => 'Folder covers tidak bisa dibuat.']; }
    $base = (slugify($slug) ?: 'cover') . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
    $masterAbs = $dir . '/' . $base . '.' . $ext;
    if (!_scribeSaveImage($dst, $masterAbs, $ext)) { imagedestroy($dst); return ['error' => 'Gagal menyimpan cover.']; }

    $variants = [];
    foreach (COVER_VARIANTS as $vw) {
        if ($vw >= $targetW) continue;
        $vh = max(1, (int) round($targetH * ($vw / $targetW)));
        $v = _scribeResizeImage($dst, $vw, $vh);
        $vAbs = $dir . '/' . $base . '-' . $vw . '.' . $ext;
        if (_scribeSaveImage($v, $vAbs, $ext)) $variants[$vw] = 'covers/' . $base . '-' . $vw . '.' . $ext;
        imagedestroy($v);
    }
    imagedestroy($dst);
    return ['path' => 'covers/' . $base . '.' . $ext, 'variants' => $variants, 'width' => $targetW, 'height' => $targetH, 'format' => $ext];
}

/** Simpan gambar inline (profil 'content') dari file temp. Return {path,url} atau {error}. */
function stockSaveInline(string $tmp): array
{
    [$maxW, $maxH, $variants, $forceExt] = scribeImageProfile('content');
    $base = date('Ym') . '-' . bin2hex(random_bytes(6));
    $res = _scribeProcessRaster($tmp, 'content', $base, $maxW, $maxH, $variants, $forceExt);
    if (isset($res['error'])) return $res;
    $res['url'] = rtrim(UPLOAD_URL, '/') . '/' . $res['path'];
    return $res;
}

/**
 * Susun daftar query pencarian dari artikel (prioritas → cadangan). Bias ke
 * aset profesional tanpa manusia (append modifier netral). Dedup.
 */
function stockBuildQueries(array $article): array
{
    $q = [];
    $fk = trim((string) ($article['focus_keyword'] ?? ''));
    $title = trim((string) ($article['title'] ?? ''));
    $cat = trim((string) ($article['category'] ?? ''));
    if ($fk !== '')    $q[] = $fk;
    if ($title !== '') $q[] = $title;
    // Heading H2 sebagai sumber tema tambahan.
    foreach (stockExtractH2($article['content'] ?? '') as $h2) { if (mb_strlen($h2) >= 4) $q[] = $h2; }
    if ($cat !== '')   $q[] = $cat;
    // Modifier netral (profesional, tanpa orang) untuk cadangan paling akhir.
    $seed = $fk !== '' ? $fk : $title;
    foreach (['workspace', 'technology', 'business', 'abstract'] as $m) {
        if ($seed !== '') $q[] = trim($seed . ' ' . $m);
    }
    // Normalisasi + dedup (case-insensitive), buang yang terlalu pendek.
    $out = [];
    foreach ($q as $s) {
        $s = trim((string) preg_replace('/\s+/', ' ', $s));
        if (mb_strlen($s) < 3) continue;
        $out[mb_strtolower($s)] = $s;
    }
    return array_values($out);
}

/** Ambil teks heading H2 dari HTML (untuk tema query + posisi inline). */
function stockExtractH2(string $html): array
{
    if (stripos($html, '<h2') === false) return [];
    $doc = new DOMDocument('1.0', 'UTF-8');
    if (!@$doc->loadHTML('<?xml encoding="UTF-8"><div id="__s__">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING)) return [];
    $out = [];
    foreach ($doc->getElementsByTagName('h2') as $h) {
        $t = trim($h->textContent);
        if ($t !== '') $out[] = $t;
    }
    return $out;
}

/**
 * Sisipkan <figure><img></figure> SETELAH elemen H2 ke-$index (0-based) dalam
 * konten. $alt dipakai bila tak kosong. Return HTML baru (atau asli bila gagal).
 */
function stockInsertImageAfterH2(string $html, int $h2Index, string $localUrl, string $alt): string
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    if (!@$doc->loadHTML('<?xml encoding="UTF-8"><div id="__ins__">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING)) return $html;
    $root = $doc->getElementById('__ins__');
    if (!$root) return $html;
    $h2s = $root->getElementsByTagName('h2');
    if ($h2Index < 0 || $h2Index >= $h2s->length) return $html;
    $h2 = $h2s->item($h2Index);

    $fig = $doc->createElement('figure');
    $img = $doc->createElement('img');
    $img->setAttribute('src', $localUrl);
    $img->setAttribute('alt', mb_substr($alt, 0, 125));
    $fig->appendChild($img);
    // Sisipkan setelah H2 (sebelum sibling berikutnya).
    if ($h2->nextSibling) $h2->parentNode->insertBefore($fig, $h2->nextSibling);
    else $h2->parentNode->appendChild($fig);

    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return $out;
}

/**
 * ORCHESTRATOR dipakai Ingest. $opts:
 *   title, focus_keyword, category, slug, content_html,
 *   cover:  ['mode'=>'auto'|'url'|'none', 'url'=>?, 'query'=>?]
 *   images: ['mode'=>'auto'|'manual'|'none', 'count'=>N, 'items'=>[{url,after_h2?,alt?}]]
 *   dry_run: bool
 *   search_fn: callable|null  (INJEKSI untuk test; default stockPexelsSearch)
 * Return:
 *   dry_run → ['dry_run'=>true,'plan'=>[...],'warnings'=>[...]]
 *   else    → ['cover'=>['path','variants','is_fallback'=>0]|null,'content_html'=>..,
 *              'inline_added'=>N,'warnings'=>[...]]
 */
function stockGenerateForArticle(array $opts): array
{
    $searchFn   = $opts['search_fn'] ?? 'stockPexelsSearch';
    $downloadFn = $opts['download_fn'] ?? 'stockDownloadToTemp'; // seam uji (default: unduh nyata)
    $dry = !empty($opts['dry_run']);
    $warnings = [];
    $content = (string) ($opts['content_html'] ?? '');
    $article = [
        'title' => (string) ($opts['title'] ?? ''),
        'focus_keyword' => (string) ($opts['focus_keyword'] ?? ''),
        'category' => (string) ($opts['category'] ?? ''),
        'content' => $content,
    ];
    $slug = (string) ($opts['slug'] ?? 'img');

    $coverOpt  = is_array($opts['cover'] ?? null) ? $opts['cover'] : [];
    $imagesOpt = is_array($opts['images'] ?? null) ? $opts['images'] : [];
    $coverMode  = $coverOpt['mode'] ?? 'none';
    $imagesMode = $imagesOpt['mode'] ?? 'none';

    if (($coverMode !== 'none' || $imagesMode !== 'none') && !stockEnabled() && $coverMode !== 'url' && $imagesMode !== 'manual') {
        $warnings[] = 'Pexels belum dikonfigurasi — gambar otomatis dilewati.';
    }

    $plan = ['cover' => null, 'inline' => []];
    $usedUrls = [];   // dedup sumber
    $coverResult = null;
    $inlineAdded = 0;
    $fallbackQueries = stockFallbackQueries($article);

    // ── COVER ───────────────────────────────────────────────────
    if ($coverMode === 'url') {
        $coverSrc = trim((string) ($coverOpt['url'] ?? ''));
        $plan['cover'] = ['mode' => 'url', 'source' => $coverSrc ?: null, 'reason' => 'URL dari pemanggil'];
        if ($coverSrc !== '' && !$dry) {
            $usedUrls[$coverSrc] = true;
            $err = null;
            $tmp = $downloadFn($coverSrc, false, $err);
            if ($tmp === null) { $warnings[] = 'Cover gagal diunduh: ' . $err; }
            else {
                $saved = stockSaveCover($tmp, $slug);
                @unlink($tmp);
                if (isset($saved['error'])) $warnings[] = 'Cover gagal diproses: ' . $saved['error'];
                else $coverResult = ['path' => $saved['path'], 'variants' => $saved['variants'], 'is_fallback' => 0];
            }
        } elseif ($coverSrc !== '' && $dry) {
            $usedUrls[$coverSrc] = true;
        }
    } elseif ($coverMode === 'auto') {
        $rawQ = trim((string) ($coverOpt['query'] ?? '')) ?: ($article['focus_keyword'] !== '' ? $article['focus_keyword'] : $article['title']);
        if (!stockEnabled()) {
            $plan['cover'] = ['mode' => 'auto', 'source' => null, 'reason' => 'Pexels belum dikonfigurasi'];
        } else {
            $sel = stockSelectAsset($searchFn, $rawQ, $fallbackQueries, $usedUrls, 'landscape');
            $plan['cover'] = [
                'mode' => 'auto', 'original_query' => $sel['original_query'],
                'normalized_query' => $sel['normalized_query'], 'used_query' => $sel['used_query'],
                'source' => $sel['photo']['src'] ?? null, 'reason' => $sel['reason'],
                'rejected' => $sel['rejected'],
            ];
            if (!$sel['photo']) {
                $warnings[] = 'Cover otomatis dilewati — ' . $sel['reason'];
            } else {
                $coverSrc = $sel['photo']['src'];
                $usedUrls[$coverSrc] = true;
                if (!$dry) {
                    $err = null;
                    $tmp = $downloadFn($coverSrc, true, $err);
                    if ($tmp === null) { $warnings[] = 'Cover gagal diunduh: ' . $err; }
                    else {
                        $saved = stockSaveCover($tmp, $slug);
                        @unlink($tmp);
                        if (isset($saved['error'])) $warnings[] = 'Cover gagal diproses: ' . $saved['error'];
                        else $coverResult = ['path' => $saved['path'], 'variants' => $saved['variants'], 'is_fallback' => 0];
                    }
                }
            }
        }
    }

    // ── INLINE ──────────────────────────────────────────────────
    $h2s = stockExtractH2($content);
    if ($imagesMode === 'auto') {
        $count = max(0, min(STOCK_MAX_INLINE, (int) ($imagesOpt['count'] ?? 0)));
        if ($count > 0 && $h2s) {
            // Pilih posisi H2 ter-distribusi (lewati H2 pertama bila memungkinkan).
            $positions = stockPickH2Positions(count($h2s), $count);
            foreach ($positions as $pi) {
                $heading = $h2s[$pi] ?? '';
                $rawQ = ($heading !== '' && mb_strlen($heading) >= 4) ? $heading
                        : ($article['focus_keyword'] !== '' ? $article['focus_keyword'] : $article['title']);
                if (!stockEnabled()) {
                    $plan['inline'][] = ['after_h2' => $heading, 'source' => null, 'reason' => 'Pexels belum dikonfigurasi'];
                    continue;
                }
                $sel = stockSelectAsset($searchFn, $rawQ, $fallbackQueries, $usedUrls, 'landscape');
                $plan['inline'][] = [
                    'after_h2' => $heading, 'original_query' => $sel['original_query'],
                    'normalized_query' => $sel['normalized_query'], 'used_query' => $sel['used_query'],
                    'source' => $sel['photo']['src'] ?? null, 'reason' => $sel['reason'],
                    'rejected' => $sel['rejected'],
                ];
                if (!$sel['photo']) { $warnings[] = 'Gambar inline dilewati (' . $heading . ') — ' . $sel['reason']; continue; }
                $src = $sel['photo']['src'];
                $alt = trim((string) ($sel['photo']['alt'] ?? '')) !== '' ? (string) $sel['photo']['alt'] : $heading;
                $usedUrls[$src] = true;
                if ($dry) continue;
                $err = null;
                $tmp = $downloadFn($src, true, $err);
                if ($tmp === null) { $warnings[] = 'Gambar inline gagal diunduh: ' . $err; continue; }
                $saved = stockSaveInline($tmp);
                @unlink($tmp);
                if (isset($saved['error'])) { $warnings[] = 'Gambar inline gagal diproses: ' . $saved['error']; continue; }
                $content = stockInsertImageAfterH2($content, $pi, $saved['url'], $alt);
                $inlineAdded++;
            }
        }
    } elseif ($imagesMode === 'manual') {
        $items = is_array($imagesOpt['items'] ?? null) ? $imagesOpt['items'] : [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $url = trim((string) ($it['url'] ?? ''));
            $pi  = (int) ($it['after_h2'] ?? 0);
            $alt = trim((string) ($it['alt'] ?? ''));
            if ($url === '' || isset($usedUrls[$url])) continue;
            $plan['inline'][] = ['after_h2' => $h2s[$pi] ?? ('#' . $pi), 'source' => $url, 'reason' => 'URL manual dari pemanggil'];
            if ($dry) { $usedUrls[$url] = true; continue; }
            $usedUrls[$url] = true;
            $err = null;
            $tmp = $downloadFn($url, false, $err);
            if ($tmp === null) { $warnings[] = 'Gambar inline gagal diunduh: ' . $err; continue; }
            $saved = stockSaveInline($tmp);
            @unlink($tmp);
            if (isset($saved['error'])) { $warnings[] = 'Gambar inline gagal diproses: ' . $saved['error']; continue; }
            $content = stockInsertImageAfterH2($content, $pi, $saved['url'], $alt);
            $inlineAdded++;
        }
    }

    if ($dry) {
        return ['dry_run' => true, 'plan' => $plan, 'warnings' => $warnings];
    }

    // Auto alt-text untuk gambar yang alt-nya masih kosong (tak menimpa yang ada).
    if ($inlineAdded > 0) {
        $content = altTextFill($content, ['focus_keyword' => $article['focus_keyword'], 'title' => $article['title']])['html'];
    }

    return [
        'cover'        => $coverResult,
        'content_html' => $content,
        'inline_added' => $inlineAdded,
        'warnings'     => $warnings,
    ];
}

/** Pilih indeks H2 ter-distribusi untuk $count gambar (lewati H2 pertama bila bisa). */
function stockPickH2Positions(int $h2Count, int $count): array
{
    if ($h2Count <= 0 || $count <= 0) return [];
    $count = min($count, $h2Count);
    $start = $h2Count > $count ? 1 : 0; // lewati heading pembuka bila ada ruang
    $avail = $h2Count - $start;
    $pos = [];
    for ($i = 0; $i < $count; $i++) {
        $idx = $start + (int) floor($i * $avail / $count);
        $idx = min($h2Count - 1, $idx);
        if (!in_array($idx, $pos, true)) $pos[] = $idx;
    }
    return $pos;
}

// ════════════════════════════════════════════════════════════════════════
// SELEKSI CERDAS — normalisasi query ID→EN, filter TANPA-manusia/logo, dan
// relevance scoring. Pexels tak punya filter "tanpa orang"; kita saring berbasis
// teks `alt` dan nilai relevansi dari irisan token (tanpa model vision/LLM).
// ════════════════════════════════════════════════════════════════════════

/** Peta kata Indonesia→Inggris (domain blog bisnis/tech/SEO). '' = buang. */
function stockIdEnMap(): array
{
    static $m = [
        'harga' => 'price', 'produk' => 'product', 'digital' => 'digital', 'biaya' => 'cost',
        'produksi' => 'production', 'penjualan' => 'sales', 'keuangan' => 'finance', 'uang' => 'money',
        'pemasaran' => 'marketing', 'strategi' => 'strategy', 'bisnis' => 'business', 'usaha' => 'business',
        'teknologi' => 'technology', 'aplikasi' => 'app', 'perangkat' => 'device', 'gawai' => 'gadget',
        'data' => 'data', 'analitik' => 'analytics', 'analisis' => 'analysis', 'laporan' => 'report',
        'grafik' => 'chart', 'diagram' => 'diagram', 'tabel' => 'table', 'kalkulator' => 'calculator',
        'pelanggan' => 'customer', 'konsumen' => 'customer', 'layanan' => 'service', 'pasar' => 'market',
        'toko' => 'store', 'belanja' => 'shopping', 'jual' => 'sell', 'beli' => 'buy',
        'artikel' => 'article', 'tulisan' => 'writing', 'menulis' => 'writing', 'konten' => 'content',
        'situs' => 'website', 'web' => 'website', 'halaman' => 'page', 'mesin' => 'engine',
        'pencarian' => 'search', 'peringkat' => 'ranking', 'kunci' => 'key',
        'lintas' => 'traffic', 'pengunjung' => 'visitor', 'kampanye' => 'campaign',
        'email' => 'email', 'surel' => 'email', 'kirim' => 'send', 'otomatis' => 'automation',
        'jaringan' => 'network', 'server' => 'server', 'basis' => 'database', 'kode' => 'code',
        'keamanan' => 'security', 'kecepatan' => 'speed', 'performa' => 'performance',
        'desain' => 'design', 'tampilan' => 'interface', 'antarmuka' => 'interface',
        'pertumbuhan' => 'growth', 'investasi' => 'investment', 'modal' => 'capital',
        'pajak' => 'tax', 'gaji' => 'salary', 'anggaran' => 'budget', 'tagihan' => 'invoice',
        'faktur' => 'invoice', 'pembayaran' => 'payment', 'bayar' => 'payment', 'transaksi' => 'transaction',
        'dokumen' => 'document', 'berkas' => 'file', 'arsip' => 'archive',
        'waktu' => 'time', 'jadwal' => 'schedule', 'kalender' => 'calendar',
        'tujuan' => 'goal', 'rencana' => 'plan', 'proyek' => 'project', 'tim' => '',
        'kantor' => 'office', 'meja' => 'desk', 'kerja' => 'work', 'ruang' => 'workspace',
        'panduan' => 'guide', 'langkah' => 'step', 'contoh' => '', 'manfaat' => 'benefit',
        'fungsi' => 'function', 'fitur' => 'feature', 'tarif' => 'price', 'langganan' => 'subscription',
        'penawaran' => 'offer', 'struktur' => 'structure', 'barang' => 'product', 'buat' => '',
        'mudah' => '', 'membuat' => '', 'terbaik' => '', 'lengkap' => '', 'pemula' => '',
    ];
    return $m;
}

/** Himpunan token Inggris yang boleh lolos ke query (terjemahan + good-words + umum). */
function stockEnglishSafe(): array
{
    static $s = null;
    if ($s === null) {
        $s = [];
        foreach (stockIdEnMap() as $v) if ($v !== '') $s[$v] = true;
        foreach (stockGoodWords() as $g) $s[$g] = true;
        foreach ([
            'seo', 'email', 'app', 'online', 'blog', 'digital', 'content', 'website', 'web',
            'software', 'ecommerce', 'marketing', 'finance', 'business', 'technology', 'data',
            'analytics', 'dashboard', 'product', 'service', 'brand', 'social', 'media', 'mobile',
            'cloud', 'api', 'automation', 'funnel', 'conversion', 'traffic', 'backlink', 'sitemap',
            'newsletter', 'landing', 'page', 'template', 'workflow', 'growth', 'startup', 'invoice',
            'budget', 'tax', 'payment', 'pricing', 'subscription', 'offer', 'structure', 'design',
        ] as $e) $s[$e] = true;
    }
    return $s;
}

/** Kata henti (ID+EN) yang dibuang dari query. */
function stockStopwords(): array
{
    static $s = null;
    if ($s === null) $s = array_flip([
        'yang', 'untuk', 'dan', 'di', 'ke', 'dari', 'dengan', 'pada', 'atau', 'ini', 'itu',
        'adalah', 'akan', 'agar', 'bisa', 'dapat', 'cara', 'apa', 'bagaimana', 'kenapa',
        'mengapa', 'saat', 'ketika', 'juga', 'lebih', 'paling', 'secara', 'kata', 'tips',
        'the', 'a', 'an', 'to', 'of', 'for', 'and', 'or', 'in', 'on', 'with', 'how',
        'what', 'why', 'your', 'you', 'is', 'are', 'best', 'guide', 'cara',
    ]);
    return $s;
}

/** Kata penanda MANUSIA pada alt (penolakan keras). */
function stockPeopleWords(): array
{
    static $p = null;
    if ($p === null) $p = array_flip([
        'person', 'people', 'man', 'woman', 'men', 'women', 'boy', 'girl', 'child', 'children',
        'kid', 'kids', 'baby', 'human', 'humans', 'face', 'faces', 'hand', 'hands', 'handed',
        'handing', 'handshake', 'finger', 'fingers', 'fingernail', 'thumb', 'palm', 'wrist',
        'arm', 'arms', 'elbow', 'shoulder', 'shoulders', 'leg', 'legs', 'knee', 'foot', 'feet',
        'barefoot', 'lap', 'neck', 'skin', 'hair', 'beard', 'lips', 'portrait', 'selfie',
        'crowd', 'worker', 'workers', 'businessman', 'businesswoman', 'businesspeople', 'model',
        'lady', 'ladies', 'guy', 'guys', 'gentleman', 'family', 'couple', 'teenager', 'adult',
        'adults', 'silhouette', 'silhouettes', 'bodies', 'smile', 'smiling', 'holding', 'wearing',
        'sitting', 'seated', 'walking', 'typing', 'writes', 'gripping', 'grabbing',
        'gesture', 'gesturing', 'posing', 'manicure', 'employee', 'employees', 'staff',
        'team', 'teams', 'group', 'groups', 'colleague', 'colleagues', 'coworker', 'coworkers',
        'he', 'she', 'his', 'her', 'him', 'male', 'female', 'someone', 'everybody', 'crowded',
    ]);
    return $p;
}

/** Kata penanda LOGO/MEREK/TEKS dominan (penolakan keras). */
function stockBrandTextWords(): array
{
    static $b = null;
    if ($b === null) $b = array_flip([
        'logo', 'brand', 'branding', 'signage', 'billboard', 'watermark', 'trademark', 'poster',
    ]);
    return $b;
}

/** Objek "bagus" (profesional, tanpa manusia) → bonus skor. */
function stockGoodWords(): array
{
    static $g = null;
    if ($g === null) $g = [
        'dashboard', 'chart', 'graph', 'data', 'analytics', 'computer', 'laptop', 'screen',
        'monitor', 'desk', 'office', 'workspace', 'abstract', 'technology', 'diagram',
        'calculator', 'money', 'coin', 'coins', 'finance', 'document', 'report', 'network',
        'server', 'code', 'gear', 'pattern', 'geometric', 'minimal', 'device', 'keyboard',
        'notebook', 'spreadsheet', 'table', 'pricing', 'growth', 'digital', 'circuit', 'lock',
        'shield', 'cart', 'box', 'package', 'envelope', 'pen', 'paper',
    ];
    return $g;
}

/** Deteksi konsep dominan dari token (ID/EN) → query visual kurasi + good words. */
function stockConcept(array $tokens): ?array
{
    $has = function (array $ws) use ($tokens): bool { return (bool) array_intersect($ws, $tokens); };
    // Production dicek lebih dulu: "biaya produksi" → industrial (bukan pricing).
    if ($has(['production', 'produksi', 'manufacture', 'manufaktur', 'factory', 'pabrik']))
        return ['query' => 'industrial machinery production line equipment', 'good' => ['machine', 'machinery', 'factory', 'industrial', 'equipment', 'gear', 'production']];
    if ($has(['price', 'pricing', 'harga', 'tarif', 'biaya', 'subscription', 'langganan', 'cost', 'penawaran', 'offer']))
        return ['query' => 'pricing table price tag calculator', 'good' => ['price', 'calculator', 'money', 'coin', 'chart', 'table', 'tag']];
    if ($has(['finance', 'money', 'keuangan', 'uang', 'investment', 'investasi', 'budget', 'anggaran', 'payment', 'pembayaran', 'invoice', 'tax', 'pajak', 'capital', 'modal']))
        return ['query' => 'finance chart money coins graph', 'good' => ['money', 'coin', 'coins', 'chart', 'graph', 'finance', 'calculator']];
    if ($has(['analytics', 'analitik', 'data', 'statistik', 'statistics', 'report', 'laporan', 'metric', 'metrik']))
        return ['query' => 'analytics dashboard data chart screen', 'good' => ['dashboard', 'chart', 'data', 'graph', 'screen', 'analytics']];
    if ($has(['seo', 'search', 'pencarian', 'ranking', 'peringkat', 'keyword', 'traffic', 'trafik']))
        return ['query' => 'search engine optimization chart screen', 'good' => ['chart', 'graph', 'screen', 'data', 'website']];
    if ($has(['marketing', 'pemasaran', 'campaign', 'kampanye', 'advertising', 'iklan']))
        return ['query' => 'marketing strategy chart target abstract', 'good' => ['chart', 'target', 'graph', 'strategy']];
    if ($has(['writing', 'menulis', 'tulisan', 'content', 'konten', 'article', 'artikel', 'blog', 'copywriting']))
        return ['query' => 'open notebook pen on desk', 'good' => ['notebook', 'pen', 'desk', 'paper', 'keyboard', 'workspace']];
    if ($has(['email', 'surel', 'newsletter']))
        return ['query' => 'email envelope laptop screen', 'good' => ['envelope', 'laptop', 'screen', 'mail']];
    if ($has(['ecommerce', 'toko', 'store', 'shop', 'belanja', 'shopping', 'product', 'produk', 'sell', 'jual', 'cart']))
        return ['query' => 'online shopping cart boxes laptop', 'good' => ['cart', 'box', 'package', 'laptop', 'bag', 'parcel']];
    if ($has(['technology', 'teknologi', 'app', 'aplikasi', 'software', 'code', 'coding', 'developer', 'server', 'network']))
        return ['query' => 'technology circuit abstract network server', 'good' => ['circuit', 'server', 'network', 'code', 'screen', 'abstract']];
    if ($has(['security', 'keamanan', 'privacy', 'privasi']))
        return ['query' => 'cyber security lock shield abstract', 'good' => ['lock', 'shield', 'security', 'network', 'abstract']];
    if ($has(['growth', 'pertumbuhan', 'strategy', 'strategi', 'business', 'bisnis', 'success', 'usaha']))
        return ['query' => 'business growth chart arrow abstract', 'good' => ['chart', 'arrow', 'graph', 'growth', 'abstract']];
    return null;
}

/**
 * Normalisasi judul/heading (ID) → query visual Inggris konkret.
 * Return ['original','normalized','tokens'[],'good'[],'concept'?].
 */
function stockNormalizeQuery(string $raw): array
{
    $orig = trim($raw);
    $low = (string) preg_replace('/[^a-z0-9\s]+/u', ' ', mb_strtolower($orig));
    $rawTokens = array_values(array_filter(preg_split('/\s+/', $low)));
    $map = stockIdEnMap();
    $stop = stockStopwords();
    $safe = stockEnglishSafe();
    $tokens = []; // HANYA token Inggris: hasil terjemahan atau kata Inggris dikenal
    foreach ($rawTokens as $t) {
        if (isset($stop[$t])) continue;
        if (array_key_exists($t, $map)) { if ($map[$t] !== '') $tokens[] = $map[$t]; continue; }
        if (isset($safe[$t])) $tokens[] = $t;
        // Token lain (Indonesia tak terpeta) DIBUANG — tak boleh bocor ke query Pexels.
    }
    $tokens = array_values(array_unique($tokens));
    $concept = stockConcept(array_merge($tokens, $rawTokens)); // deteksi tetap lihat token mentah ID
    if ($concept) {
        $normalized = $concept['query'];
        $good = array_values(array_unique(array_merge($concept['good'], ['abstract'])));
    } else {
        $base = trim(implode(' ', array_slice($tokens, 0, 4)));
        $normalized = $base !== '' ? ($base . ' concept abstract background') : 'abstract technology background';
        $good = stockGoodWords();
    }
    // Token relevansi = token Inggris sumber + kata dalam query ternormalisasi (untuk
    // mencocokkan alt foto dengan maksud pencarian, mis. "pricing"/"table"/"calculator").
    $normTokens = array_values(array_filter(
        preg_split('/\s+/', $normalized),
        fn($w) => mb_strlen($w) >= 3 && !isset($stop[$w])
    ));
    $relTokens = array_values(array_unique(array_merge($tokens, $normTokens)));
    return [
        'original' => $orig, 'normalized' => $normalized, 'tokens' => $tokens,
        'rel_tokens' => $relTokens, 'good' => $good, 'concept' => $concept['query'] ?? null,
    ];
}

/** Token teks alt sebuah foto (lower, alnum). */
function stockPhotoTokens(array $photo): array
{
    $alt = mb_strtolower((string) ($photo['alt'] ?? ''));
    $alt = (string) preg_replace('/[^a-z0-9\s]+/', ' ', $alt);
    return array_values(array_filter(preg_split('/\s+/', $alt)));
}

/** Foto mengandung manusia? (berbasis teks alt Pexels). */
function stockPhotoHasPeople(array $photo): bool
{
    $people = stockPeopleWords();
    foreach (stockPhotoTokens($photo) as $t) if (isset($people[$t])) return true;
    return false;
}

/** Foto mengandung logo/merek/teks dominan? */
function stockPhotoHasBrandText(array $photo): bool
{
    $brand = stockBrandTextWords();
    foreach (stockPhotoTokens($photo) as $t) if (isset($brand[$t])) return true;
    return false;
}

/**
 * Nilai kelayakan aset untuk query. Return ['ok','score','reason'].
 * Tolak keras: alt kosong (tak terverifikasi), manusia, logo/merek. Nilai
 * relevansi = irisan token query + bonus objek "bagus".
 */
function stockScorePhoto(array $photo, array $relTokens, array $goodWords): array
{
    $ptoks = stockPhotoTokens($photo);
    if (!$ptoks) return ['ok' => false, 'score' => 0.0, 'reason' => 'ditolak: alt kosong (tak bisa diverifikasi)'];
    if (stockPhotoHasPeople($photo)) return ['ok' => false, 'score' => 0.0, 'reason' => 'ditolak: mengandung manusia/anggota tubuh'];
    if (stockPhotoHasBrandText($photo)) return ['ok' => false, 'score' => 0.0, 'reason' => 'ditolak: mengandung logo/merek/teks'];
    $pset = array_flip($ptoks);
    $rel = 0; foreach (array_unique($relTokens) as $qt) if (isset($pset[$qt])) $rel++;
    // Relevansi WAJIB: minimal satu kata kunci topik muncul di alt. relevansi=0 → tolak.
    if ($rel < 1) return ['ok' => false, 'score' => 0.0, 'reason' => 'ditolak: relevansi=0 (tak cocok topik)'];
    $goodHit = 0; foreach (array_unique($goodWords) as $gw) if (isset($pset[$gw])) $goodHit++;
    $score = $rel * 2.0 + min($goodHit, 3) * 1.0;
    return ['ok' => $score >= STOCK_MIN_RELEVANCE, 'score' => $score, 'reason' => "relevansi=$rel, objek_bagus=$goodHit"];
}

/** Query fallback berbasis konsep focus keyword/kategori + generik netral. */
function stockFallbackQueries(array $article): array
{
    $out = [];
    foreach (['focus_keyword', 'category', 'title'] as $k) {
        $n = stockNormalizeQuery((string) ($article[$k] ?? ''));
        if ($n['concept']) $out[] = $n['concept'];
    }
    $out[] = 'abstract data technology background';
    $out[] = 'minimal geometric background';
    return array_values(array_unique(array_filter($out)));
}

/**
 * Pilih aset terbaik untuk sebuah topik: normalisasi → cari (primary + fallback)
 * → saring tanpa-manusia/logo → skor relevansi → pilih tertinggi di atas ambang.
 * Return rencana lengkap (termasuk alasan) + foto terpilih (atau null).
 */
function stockSelectAsset(callable $searchFn, string $rawQuery, array $fallbackQueries, array $usedUrls, string $orientation = 'landscape'): array
{
    $norm = stockNormalizeQuery($rawQuery);
    $queries = array_values(array_unique(array_filter(array_merge([$norm['normalized']], $fallbackQueries))));
    $best = null; $bestScore = -1.0; $bestReason = ''; $usedQuery = ''; $considered = 0;
    $rejected = []; // alasan penolakan kandidat (transparansi dry_run)

    foreach ($queries as $q) {
        $r = $searchFn($q, $orientation, 15);
        if (empty($r['ok']) || empty($r['photos'])) continue;
        foreach ($r['photos'] as $ph) {
            if (!is_array($ph) || empty($ph['src']) || isset($usedUrls[$ph['src']])) continue;
            $considered++;
            $sc = stockScorePhoto($ph, $norm['rel_tokens'], $norm['good']); // validasi SAMA utk dry & nyata
            if ($sc['ok']) {
                if ($sc['score'] > $bestScore) { $best = $ph; $bestScore = $sc['score']; $bestReason = $sc['reason']; $usedQuery = $q; }
            } elseif (count($rejected) < 6) {
                $rejected[] = 'foto#' . ($ph['id'] ?? '?') . ' — ' . $sc['reason'];
            }
        }
        if ($best !== null) break; // cukup dari query ini (primary diutamakan)
    }

    return [
        'original_query'   => $norm['original'],
        'normalized_query' => $norm['normalized'],
        'used_query'       => $usedQuery,
        'photo'            => $best,
        'score'            => $best ? $bestScore : 0.0,
        'considered'       => $considered,
        'rejected'         => $rejected,
        'reason'           => $best
            ? ("dipilih (skor $bestScore: $bestReason) via query \"$usedQuery\"")
            : 'tak ada aset layak: semua kandidat gagal filter tanpa-manusia/logo atau relevansi=0',
    ];
}
