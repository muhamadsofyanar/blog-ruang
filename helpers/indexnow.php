<?php
// ════════════════════════════════════════════════════════════════════════
// IndexNow — beri tahu mesin pencari (Bing, Yandex, Seznam, dll lewat SATU
// endpoint) saat URL berubah agar cepat terindeks. Event-driven saat
// publish/update, TANPA cron. NON-FATAL: kegagalan ping tidak pernah
// menggagalkan simpan/terbit artikel. Verifikasi kepemilikan via key statis
// yang di-host di {APP_URL}/{key}.txt.
//
// Catatan: "ping sitemap" klasik ke Google/Bing sudah DIHENTIKAN vendornya
// (2023). IndexNow adalah mekanisme pengganti yang masih hidup.
//
// Settings grup 'indexnow':
//   indexnow_enabled '0'|'1'   (default '0')
//   indexnow_key     hex 32    (dibuat sekali)
//   indexnow_last_at unix      (waktu submit terakhir)
//   indexnow_last    ringkas   (http + jumlah URL terakhir)
// ════════════════════════════════════════════════════════════════════════

const INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';
const INDEXNOW_MAX_URLS = 10000; // batas protokol per request.

function indexnowEnabled(): bool { return getSetting('indexnow_enabled', '0') === '1'; }

/** Key tersimpan (kosong bila belum dibuat). */
function indexnowKey(): string { return trim((string) getSetting('indexnow_key', '')); }

/** Pastikan key ada (buat 32-hex sekali). Return key. */
function indexnowEnsureKey(): string
{
    $k = indexnowKey();
    if ($k === '') {
        $k = bin2hex(random_bytes(16)); // 32 char [0-9a-f] — valid IndexNow
        setSetting('indexnow_key', $k, 'indexnow');
    }
    return $k;
}

/** URL file verifikasi publik ({APP_URL}/{key}.txt). Kosong bila key belum ada. */
function indexnowKeyLocation(): string
{
    $k = indexnowKey();
    return $k === '' ? '' : rtrim(APP_URL, '/') . '/' . $k . '.txt';
}

/** Host situs (dari APP_URL). */
function indexnowHost(): string { return (string) parse_url(APP_URL, PHP_URL_HOST); }

/**
 * Bangun payload IndexNow dari daftar URL. PURE (tanpa jaringan) → mudah diuji.
 * KEAMANAN: hanya terima URL http(s) di HOST SENDIRI (cegah submit URL asing),
 * dedup, batasi jumlah. Return null bila tak ada URL valid / key belum dibuat.
 */
function indexnowBuildPayload(array $urls): ?array
{
    $key = indexnowKey();
    if ($key === '') return null;
    $host = indexnowHost();
    $seen = [];
    foreach ($urls as $u) {
        $u = trim((string) $u);
        if ($u === '' || !preg_match('#^https?://#i', $u)) continue;
        if (strcasecmp((string) parse_url($u, PHP_URL_HOST), $host) !== 0) continue;
        $seen[$u] = true;
    }
    $list = array_slice(array_keys($seen), 0, INDEXNOW_MAX_URLS);
    if (!$list) return null;
    return [
        'host'        => $host,
        'key'         => $key,
        'keyLocation' => indexnowKeyLocation(),
        'urlList'     => $list,
    ];
}

/**
 * Kirim daftar URL ke IndexNow. NON-FATAL. $force melewati cek enabled (tombol Tes).
 * Return [ok=>true,count,http] atau [ok=>false,error,http?].
 */
function indexnowSubmit(array $urls, bool $force = false): array
{
    if (!$force && !indexnowEnabled()) return ['ok' => false, 'error' => 'IndexNow nonaktif.'];
    $payload = indexnowBuildPayload($urls);
    if ($payload === null) return ['ok' => false, 'error' => 'Tidak ada URL valid atau key belum dibuat.'];
    if (!function_exists('curl_init')) {
        indexnowLog('skip: ekstensi curl tidak tersedia');
        return ['ok' => false, 'error' => 'Ekstensi curl tidak tersedia.'];
    }

    $ch = curl_init(INDEXNOW_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $count = count($payload['urlList']);
    setSetting('indexnow_last_at', (string) time(), 'indexnow');
    setSetting('indexnow_last', ($http ?: 'ERR') . ' · ' . $count . ' URL', 'indexnow');
    // Observability granular (dibaca API capabilities agar agen bisa verifikasi).
    setSetting('indexnow_last_status', (string) $http, 'indexnow');
    setSetting('indexnow_last_url', mb_substr(implode(' ', array_slice($payload['urlList'], 0, 20)), 0, 1000), 'indexnow');

    // IndexNow: 200 OK, 202 Accepted = diterima (verifikasi key dilakukan async).
    if ($http === 200 || $http === 202) {
        indexnowLog("ok http=$http urls=$count");
        return ['ok' => true, 'count' => $count, 'http' => $http];
    }
    indexnowLog("gagal http=$http urls=$count err=$err");
    return ['ok' => false, 'error' => 'HTTP ' . ($http ?: '-') . ($err !== '' ? ' (' . $err . ')' : ''), 'http' => $http];
}

/** Ping satu artikel (slug) — aman dipanggil di hook publish/update. NON-FATAL. */
function indexnowPingArticle(string $slug): void
{
    $slug = trim($slug);
    if ($slug === '' || !indexnowEnabled()) return;
    try {
        require_once __DIR__ . '/frontend.php';
        indexnowSubmit([feArticleUrl($slug)]);
    } catch (Throwable $e) {
        indexnowLog('exc: ' . $e->getMessage());
    }
}

/** Status observability (untuk API capabilities / UI) — agen bisa verifikasi mandiri. */
function indexnowStatus(): array
{
    $at     = (int) getSetting('indexnow_last_at', '0');
    $status = (string) getSetting('indexnow_last_status', '');
    $url    = (string) getSetting('indexnow_last_url', '');
    return [
        'enabled'      => indexnowEnabled(),
        'configured'   => indexnowKey() !== '',
        'key_location' => indexnowKeyLocation() ?: null,
        'last_ping_at' => $at > 0 ? date('c', $at) : null,
        'last_status'  => ($status === '' ? null : (int) $status),
        'last_url'     => ($url !== '' ? $url : null),
    ];
}

/** Log satu baris (TANPA key) ke cache/logs/indexnow.log. Non-fatal. */
function indexnowLog(string $line): void
{
    $dir = __DIR__ . '/../cache/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) return;
    @file_put_contents($dir . '/indexnow.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
}
