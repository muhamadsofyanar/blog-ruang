<?php
// ════════════════════════════════════════════════════════════════════════
// Google Search Console (read-only) via SERVICE ACCOUNT — tanpa library Google.
// Alur: JWT RS256 (openssl_sign) -> tukar access token -> searchAnalytics/query.
// Data di-cache sebagai JSON di settings (TANPA tabel & TANPA cron, ikut pola
// cache lain). Rahasia (private key SA) hidup di settings; TIDAK PERNAH di-echo.
// MVP: metrik per-artikel (klik/impresi/CTR/posisi) + filter aksi.
// ════════════════════════════════════════════════════════════════════════

const GSC_SCOPE     = 'https://www.googleapis.com/auth/webmasters.readonly';
const GSC_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GSC_API       = 'https://searchconsole.googleapis.com/webmasters/v3';
const GSC_DEFAULT_DAYS = 28;
const GSC_LAG_DAYS  = 3; // GSC menahan data ~2-3 hari terakhir.

function gscEnabled(): bool  { return getSetting('gsc_enabled', '0') === '1'; }
function gscSiteUrl(): string { return trim((string) getSetting('gsc_site_url', '')); }

/** Service account terdekode, atau null bila belum diatur / tidak valid. */
function gscServiceAccount(): ?array
{
    $raw = (string) getSetting('gsc_sa_json', '');
    if ($raw === '') return null;
    $sa = json_decode($raw, true);
    if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) return null;
    return $sa;
}

function gscConfigured(): bool { return gscServiceAccount() !== null && gscSiteUrl() !== ''; }

/** Email SA (aman ditampilkan — bukan rahasia; dipakai user untuk diberi akses GSC). */
function gscServiceEmail(): string { $sa = gscServiceAccount(); return (string) ($sa['client_email'] ?? ''); }

/** Reset cache token & data (dipanggil saat konfigurasi berubah). */
function gscClearCaches(): void
{
    foreach (['gsc_token', 'gsc_token_exp', 'gsc_cache', 'gsc_cache_at', 'gsc_cache_range', 'gsc_cache_start', 'gsc_cache_end', 'gsc_cache_prev', 'gsc_cache_prev_at'] as $k) {
        setSetting($k, '', 'gsc');
    }
}

function gscB64Url(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }

/** Bangun & tandatangani JWT RS256 (assertion) dari private key SA. '' bila gagal. */
function gscBuildAssertion(array $sa): string
{
    $now = time();
    $header = gscB64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim  = gscB64Url((string) json_encode([
        'iss'   => $sa['client_email'],
        'scope' => GSC_SCOPE,
        'aud'   => GSC_TOKEN_URL,
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));
    $unsigned = $header . '.' . $claim;
    $sig = '';
    if (!openssl_sign($unsigned, $sig, $sa['private_key'], 'SHA256')) return '';
    return $unsigned . '.' . gscB64Url($sig);
}

/** cURL sederhana. Return [httpCode, bodyString]. */
function gscHttp(string $method, string $url, ?string $bearer, ?string $payload, string $contentType = 'application/json'): array
{
    $ch = curl_init($url);
    $headers = [];
    if ($bearer !== null)  $headers[] = 'Authorization: Bearer ' . $bearer;
    if ($payload !== null) $headers[] = 'Content-Type: ' . $contentType;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($body === false) return [0, (string) json_encode(['error' => 'connect', 'error_description' => $err])];
    return [$http, (string) $body];
}

/** Access token (cache ~<expiry> di settings). $force melewati cache. Return [ok,token|error]. */
function gscAccessToken(bool $force = false): array
{
    if (!$force) {
        $tok = (string) getSetting('gsc_token', '');
        $exp = (int) getSetting('gsc_token_exp', '0');
        if ($tok !== '' && $exp > time() + 60) return ['ok' => true, 'token' => $tok];
    }
    $sa = gscServiceAccount();
    if (!$sa) return ['ok' => false, 'error' => 'Service account belum diatur atau tidak valid.'];
    $assertion = gscBuildAssertion($sa);
    if ($assertion === '') return ['ok' => false, 'error' => 'Gagal menandatangani token (private key tidak valid).'];

    [$http, $body] = gscHttp('POST', GSC_TOKEN_URL, null, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $assertion,
    ]), 'application/x-www-form-urlencoded');
    $data = json_decode($body, true);
    if ($http !== 200 || !is_array($data) || empty($data['access_token'])) {
        $msg = is_array($data) ? (string) ($data['error_description'] ?? $data['error'] ?? '') : '';
        return ['ok' => false, 'error' => 'Gagal memperoleh token Google' . ($msg !== '' ? ': ' . $msg : '.')];
    }
    setSetting('gsc_token', (string) $data['access_token'], 'gsc');
    setSetting('gsc_token_exp', (string) (time() + (int) ($data['expires_in'] ?? 3600)), 'gsc');
    return ['ok' => true, 'token' => (string) $data['access_token']];
}

/** POST searchAnalytics/query. Return [ok, rows|error]. */
function gscSearchAnalytics(string $token, array $body): array
{
    $site = gscSiteUrl();
    if ($site === '') return ['ok' => false, 'error' => 'URL properti belum diisi.'];
    $url = GSC_API . '/sites/' . rawurlencode($site) . '/searchAnalytics/query';
    [$http, $resp] = gscHttp('POST', $url, $token, (string) json_encode($body));
    $data = json_decode($resp, true);
    if ($http !== 200 || !is_array($data)) {
        $msg = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
        if ($http === 403) $msg = $msg !== '' ? $msg : 'Akses ditolak. Pastikan email service account ditambahkan sebagai pengguna properti di Search Console.';
        return ['ok' => false, 'error' => 'Query Search Console gagal (HTTP ' . $http . ')' . ($msg !== '' ? ': ' . $msg : '.')];
    }
    return ['ok' => true, 'rows' => is_array($data['rows'] ?? null) ? $data['rows'] : []];
}

/** Tes koneksi: token + query minimal. Return [ok, message|error]. */
function gscTestConnection(): array
{
    $t = gscAccessToken(true);
    if (!$t['ok']) return $t;
    $end   = date('Y-m-d', strtotime('-' . GSC_LAG_DAYS . ' days'));
    $start = date('Y-m-d', strtotime('-30 days'));
    $r = gscSearchAnalytics($t['token'], ['startDate' => $start, 'endDate' => $end, 'dimensions' => ['date'], 'rowLimit' => 1]);
    if (!$r['ok']) return $r;
    return ['ok' => true, 'message' => 'Koneksi Search Console berhasil.'];
}

/** Tarik metrik per-halaman & simpan cache JSON. Return [ok, count|error]. */
function gscRefreshCache(int $days = GSC_DEFAULT_DAYS): array
{
    $t = gscAccessToken();
    if (!$t['ok']) return $t;
    $end   = date('Y-m-d', strtotime('-' . GSC_LAG_DAYS . ' days'));
    $start = date('Y-m-d', strtotime('-' . ($days + GSC_LAG_DAYS) . ' days'));
    $r = gscSearchAnalytics($t['token'], [
        'startDate'  => $start,
        'endDate'    => $end,
        'dimensions' => ['page'],
        'rowLimit'   => 25000,
    ]);
    if (!$r['ok']) return $r;

    $rows = [];
    foreach ($r['rows'] as $row) {
        $rows[] = [
            'url'         => (string) ($row['keys'][0] ?? ''),
            'clicks'      => (int) round((float) ($row['clicks'] ?? 0)),
            'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
            'ctr'         => (float) ($row['ctr'] ?? 0),
            'position'    => (float) ($row['position'] ?? 0),
        ];
    }
    // Snapshot cache sebelumnya → deteksi tren (posisi turun) di antrean refresh.
    $prev = (string) getSetting('gsc_cache', '');
    if ($prev !== '') {
        setSetting('gsc_cache_prev', $prev, 'gsc');
        setSetting('gsc_cache_prev_at', (string) getSetting('gsc_cache_at', '0'), 'gsc');
    }
    setSetting('gsc_cache', (string) json_encode($rows), 'gsc');
    setSetting('gsc_cache_at', (string) time(), 'gsc');
    setSetting('gsc_cache_range', (string) $days, 'gsc');
    setSetting('gsc_cache_start', $start, 'gsc');
    setSetting('gsc_cache_end', $end, 'gsc');
    return ['ok' => true, 'count' => count($rows), 'start' => $start, 'end' => $end];
}

/** Cache mentah GSC. */
function gscCache(): array
{
    $raw  = (string) getSetting('gsc_cache', '');
    $rows = $raw !== '' ? json_decode($raw, true) : [];
    return [
        'rows'  => is_array($rows) ? $rows : [],
        'at'    => (int) getSetting('gsc_cache_at', '0'),
        'range' => (int) getSetting('gsc_cache_range', (string) GSC_DEFAULT_DAYS),
        'start' => (string) getSetting('gsc_cache_start', ''),
        'end'   => (string) getSetting('gsc_cache_end', ''),
    ];
}

/** Baris cache snapshot SEBELUMNYA (untuk deteksi tren). */
function gscCachePrev(): array
{
    $raw  = (string) getSetting('gsc_cache_prev', '');
    $rows = $raw !== '' ? json_decode($raw, true) : [];
    return is_array($rows) ? $rows : [];
}

/**
 * Peta metrik ter-agregasi per path artikel ('/artikel/{slug}'). Menjumlahkan
 * klik/impresi bila satu artikel muncul dalam beberapa varian URL; CTR dihitung
 * ulang dari total, posisi = rata-rata tertimbang impresi (fallback mean).
 * $rows null = pakai cache terkini; berikan gscCachePrev() untuk snapshot lama.
 */
function gscMetricsByArticlePath(?array $rows = null): array
{
    $map = [];
    $source = $rows ?? gscCache()['rows'];
    foreach ($source as $r) {
        $path = parse_url((string) ($r['url'] ?? ''), PHP_URL_PATH);
        if (!is_string($path)) continue;
        $path = rtrim(rawurldecode($path), '/');
        $pos  = strpos($path, '/artikel/');
        if ($pos === false) continue;
        $key = substr($path, $pos); // '/artikel/{slug}' (abaikan prefix subfolder)
        if (!isset($map[$key])) $map[$key] = ['clicks' => 0, 'impressions' => 0, 'position' => 0.0, '_pw' => 0.0];
        $imp = (int) $r['impressions'];
        $map[$key]['clicks']      += (int) $r['clicks'];
        $map[$key]['impressions'] += $imp;
        // posisi tertimbang impresi (bila impresi 0 pakai bobot 1 agar tetap terhitung).
        $w = $imp > 0 ? $imp : 1;
        $map[$key]['position'] += ((float) $r['position']) * $w;
        $map[$key]['_pw']      += $w;
    }
    foreach ($map as &$m) {
        $m['position'] = $m['_pw'] > 0 ? $m['position'] / $m['_pw'] : 0.0;
        $m['ctr']      = $m['impressions'] > 0 ? $m['clicks'] / $m['impressions'] : 0.0;
        unset($m['_pw']);
    }
    return $map;
}
