<?php
// ════════════════════════════════════════════════════════════════════════
// Sisi-client lisensi: aktivasi/revalidasi ke license server + resolusi state.
// Sumber kebenaran lokal = tabel `settings` (bukan tabel terpisah) — menjaga
// skema client tepat 12 tabel domain (§6.2). Enforcement machine identik pola
// Averion (grace/expired/suspended), hanya storage yang beradaptasi.
// ════════════════════════════════════════════════════════════════════════

/** Domain instalasi dari APP_URL (bukan HTTP_HOST yang bisa dipalsukan). */
function parseDomainFromAppUrl(): string
{
    $host = parse_url(APP_URL, PHP_URL_HOST);
    return is_string($host) ? strtolower($host) : '';
}

/**
 * Resolusi state lisensi request ini (cache per-request). MURNI resolusi —
 * tidak redirect. Halaman /admin/license bisa menampilkan state tanpa terblokir.
 *
 * @return array{state:string,suspended:bool,payload:?array,days_until_expiry:?int}
 *   state: 'valid'|'grace'|'expired'|'pending_activation'
 */
function resolveCurrentLicenseState(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (!licenseEnforcementActive()) {
        return $cached = ['state' => 'valid', 'suspended' => false, 'payload' => null, 'days_until_expiry' => null];
    }

    $key = (string) getSetting('license_key', '');
    if ($key === '' || (string) getSetting('license_activation_pending', '') !== '') {
        return $cached = ['state' => 'pending_activation', 'suspended' => false, 'payload' => null, 'days_until_expiry' => null];
    }

    $token = getSetting('license_signed_token', null);
    $res   = resolveLicenseState($token, parseDomainFromAppUrl());
    $state = $res['state']; // valid|grace|expired|invalid
    $payload   = $res['payload'];
    $suspended = is_array($payload) && (($payload['status'] ?? '') === 'suspended');

    // 'invalid' (token rusak / domain mismatch) → fail aman = perlakukan 'expired'.
    if ($state === 'invalid') {
        $state = 'expired';
    }

    return $cached = [
        'state'             => $state,
        'suspended'         => $suspended,
        'payload'           => $payload,
        'days_until_expiry' => $res['days_until_expiry'],
    ];
}

/**
 * Aktivasi/revalidasi SINKRON ke license server (POST /validate). Dipakai
 * installer, revalidasi saat login admin, dan tombol re-validasi. Menyimpan
 * hasil (status + signed_token) ke settings. Fail-open: gagal hubungi server
 * TIDAK menghapus token lama.
 *
 * @return array{ok:bool,status:string,message:string}
 */
function refreshLicenseFromServer(string $licenseKey, string $domain): array
{
    $licenseKey = strtoupper(trim($licenseKey));
    $domain     = trim($domain);
    $validateUrl = rtrim(LICENSE_SERVER_URL, '/') . '/validate';

    $payload = json_encode([
        'license_key' => $licenseKey,
        'domain'      => $domain,
        'instance_id' => substr(hash('sha256', $domain . '|' . APP_URL), 0, 32),
        'app_version' => defined('APP_VERSION') ? APP_VERSION : '',
        'enforcement' => licenseEnforcementActive() ? 1 : 0,
    ]);

    $ch = curl_init($validateUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'User-Agent: AverionScribe/' . (defined('APP_VERSION') ? APP_VERSION : '1.0')],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $result   = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($result === false || $curlErr !== '') {
        error_log('[license] refresh gagal hubungi server: ' . $curlErr);
        return ['ok' => false, 'status' => 'unknown', 'message' => 'Tidak bisa menghubungi license server. Coba lagi.'];
    }

    $data = json_decode((string) $result, true);
    if (!is_array($data)) {
        error_log('[license] respons validate tidak valid (HTTP ' . $httpCode . ')');
        return ['ok' => false, 'status' => 'unknown', 'message' => 'Respons license server tidak valid.'];
    }

    return storeLicenseResponse($licenseKey, $domain, $data);
}

/**
 * Petakan respons /validate → settings. Mengembalikan hasil ringkas.
 *
 * @return array{ok:bool,status:string,message:string}
 */
function storeLicenseResponse(string $licenseKey, string $domain, array $data): array
{
    // Kontak vendor (halaman enforcement — identitas Averion).
    if (array_key_exists('support_email', $data))    setSetting('vendor_support_email', (string) $data['support_email'], 'license');
    if (array_key_exists('support_whatsapp', $data)) setSetting('vendor_support_whatsapp', (string) $data['support_whatsapp'], 'license');
    if (array_key_exists('renew_url', $data))        setSetting('vendor_renew_url', (string) $data['renew_url'], 'license');
    if (array_key_exists('source', $data))           setSetting('license_source', (string) $data['source'], 'license');

    // Map status.
    $status = 'unknown';
    if (isset($data['valid'])) {
        if ($data['valid']) {
            $status = 'active';
        } else {
            $status = (string) ($data['status'] ?? 'unknown');
            if (!in_array($status, ['active', 'expired', 'suspended', 'unknown'], true)) {
                // domain_mismatch/invalid/cancelled → tidak aktif.
                $status = 'unknown';
            }
        }
    }

    $signedToken = isset($data['signed_token']) ? (string) $data['signed_token'] : '';
    $validUntil  = isset($data['valid_until']) ? (string) $data['valid_until'] : '';

    setSetting('license_key', $licenseKey, 'license');
    setSetting('license_domain', $domain, 'license');
    setSetting('license_status', $status, 'license');
    setSetting('license_last_checked_at', date('Y-m-d H:i:s'), 'license');
    if (isset($data['plan']))       setSetting('license_plan', (string) $data['plan'], 'license');
    if (isset($data['expires_at'])) setSetting('license_expires_at', (string) $data['expires_at'], 'license');

    if ($signedToken !== '') {
        setSetting('license_signed_token', $signedToken, 'license');
        if ($validUntil !== '') setSetting('license_token_valid_until', $validUntil, 'license');
    }

    // Aktivasi tidak lagi pending begitu ada respons yang bisa disimpan.
    setSetting('license_activation_pending', '', 'license');

    $ok = in_array($status, ['active'], true) || ($data['valid'] ?? false) === true;
    $msg = (string) ($data['message'] ?? ($ok ? 'Lisensi aktif.' : 'Lisensi tidak aktif.'));

    return ['ok' => $ok, 'status' => $status, 'message' => $msg];
}
