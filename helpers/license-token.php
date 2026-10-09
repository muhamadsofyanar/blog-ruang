<?php
// ════════════════════════════════════════════════════════════════════════
// Helper verifikasi signed token lisensi (Ed25519, JWS compact serialization).
// ────────────────────────────────────────────────────────────────────────
// PURE LOGIC: TIDAK memanggil getDB(), redirect(), flash(), atau session.
// Semua kegagalan dikembalikan sebagai return value (null / state 'invalid'),
// TIDAK melempar exception ke caller → enforcement bisa fail-safe dengan jelas.
// Caller (bootstrap/installer/action) yang bertugas query DB & ambil token.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/license-pubkey.php';

if (!defined('LICENSE_REVALIDATE_DAYS_DEFAULT'))     define('LICENSE_REVALIDATE_DAYS_DEFAULT', 14);
if (!defined('LICENSE_REVALIDATE_DAYS_LIFETIME'))    define('LICENSE_REVALIDATE_DAYS_LIFETIME', 180);
if (!defined('LICENSE_OFFLINE_GRACE_DAYS'))          define('LICENSE_OFFLINE_GRACE_DAYS', 3);
if (!defined('LICENSE_CLOCK_SKEW_TOLERANCE_HOURS'))  define('LICENSE_CLOCK_SKEW_TOLERANCE_HOURS', 6);

/**
 * Decode string base64url (varian '-' '_' tanpa padding '=') menjadi binary.
 *
 * Banyak implementasi PHP salah menangani padding — fungsi ini menambahkan
 * padding '=' yang hilang sebelum melakukan base64_decode standar.
 *
 * @param  string       $data String base64url.
 * @return string|false Binary hasil decode, atau false bila input tidak valid.
 */
function base64UrlDecode(string $data): string|false
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'), true);
}

/**
 * Enforcement lisensi aktif? Default ON bila konstanta TIDAK didefinisikan —
 * instalasi lama (config.php pra-Jul 2026, neverOverwrite saat update) tidak
 * punya define ini dan selama ini lolos enforcement selamanya. Vendor tetap
 * bisa mematikan eksplisit dengan define(..., false) sebagai kill-switch
 * (config dev lokal memang false). Aman untuk pembeli sah: state 'expired'
 * hanya mengunci ADMIN PANEL (publik/member tak tersentuh), dan token terisi
 * otomatis via re-validasi background + re-aktivasi saat login admin.
 */
function licenseEnforcementActive(): bool
{
    return defined('LICENSE_ENFORCEMENT_ENABLED') ? (bool) LICENSE_ENFORCEMENT_ENABLED : true;
}

/**
 * Normalisasi domain untuk perbandingan: host murni lowercase — buang skema
 * (https://), path, port, dan prefix 'www.'. Sehingga 'https://www.toko.com/'
 * dan 'toko.com' dianggap sama. Nilai domain dari server lisensi bisa
 * berformat URL lengkap pada lisensi lama (diketik klien di form agency).
 *
 * @param  string $domain Domain mentah.
 * @return string Domain ter-normalisasi.
 */
function normalizeLicenseDomain(string $domain): string
{
    $domain = strtolower(trim($domain));
    if ($domain === '') return '';
    if (strpos($domain, '://') !== false) {
        $domain = (string) (parse_url($domain, PHP_URL_HOST) ?? '');
    }
    $domain = explode('/', $domain)[0];
    $domain = explode(':', $domain)[0];
    if (str_starts_with($domain, 'www.')) {
        $domain = substr($domain, 4);
    }
    return $domain;
}

/**
 * Memecah dan memverifikasi signature signed token lisensi (Ed25519).
 *
 * Token berformat JWS compact: "<header_b64url>.<payload_b64url>.<signature_b64url>".
 * String yang ditandatangani adalah "<header_b64url>.<payload_b64url>" (representasi
 * SEBELUM di-decode, sesuai standar JWS compact serialization).
 *
 * Fungsi ini TIDAK mengecek masa berlaku (valid_until) — itu dipisah ke
 * evaluateLicenseTokenFreshness() agar caller bisa membedakan "token rusak/
 * signature salah" vs "token sah tapi sudah kedaluwarsa".
 *
 * @param  string      $token      Signed token dari server lisensi.
 * @param  string      $selfDomain Domain instalasi ini (untuk binding domain).
 * @return array|null  Payload (array asosiatif) bila signature valid & domain cocok;
 *                     null bila token rusak / signature salah / domain tidak cocok.
 */
function verifyLicenseTokenSignature(string $token, string $selfDomain): ?array
{
    // 1) Split jadi tepat 3 bagian.
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$headerPart, $payloadPart, $sigPart] = $parts;
    if ($headerPart === '' || $payloadPart === '' || $sigPart === '') {
        return null;
    }

    // 2) Decode header & payload (JSON) + signature (binary).
    $headerRaw  = base64UrlDecode($headerPart);
    $payloadRaw = base64UrlDecode($payloadPart);
    $sigBin     = base64UrlDecode($sigPart);
    if ($headerRaw === false || $payloadRaw === false || $sigBin === false) {
        return null;
    }

    $header  = json_decode($headerRaw, true);
    $payload = json_decode($payloadRaw, true);
    if (!is_array($header) || !is_array($payload)) {
        return null;
    }

    // 3) Ambil key id (kid) → cari public key Ed25519.
    $kid = $header['kid'] ?? '';
    if ($kid === '' || empty(LICENSE_PUBKEYS[$kid])) {
        return null;
    }
    $pubKeyB64 = LICENSE_PUBKEYS[$kid];

    // 5) Verifikasi signature. String yang ditandatangani = header.payload (b64url).
    $signingInput = $headerPart . '.' . $payloadPart;

    if (function_exists('sodium_crypto_sign_verify_detached')) {
        // 4) Public key Ed25519 disimpan sebagai base64 STANDAR → binary 32 byte.
        $pubBin = base64_decode($pubKeyB64, true);
        if ($pubBin === false || strlen($pubBin) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            // Placeholder belum diganti / key tidak valid → fail-safe.
            return null;
        }
        try {
            $ok = sodium_crypto_sign_verify_detached($sigBin, $signingInput, $pubBin);
        } catch (\Throwable $e) {
            error_log('license-token: sodium verify error: ' . $e->getMessage());
            return null;
        }
        if (!$ok) {
            return null;
        }
    } else {
        // Fallback RSA-SHA256 bila sodium tidak tersedia di hosting pembeli.
        // TODO: implementasi penuh saat dibutuhkan. Butuh public key RSA (PEM)
        //       di LICENSE_PUBKEYS_RSA[$kid].
        //   $pem = LICENSE_PUBKEYS_RSA[$kid] ?? '';
        //   if ($pem === '') return null;
        //   $ok = openssl_verify($signingInput, $sigBin, $pem, OPENSSL_ALGO_SHA256);
        //   if ($ok !== 1) return null;
        // Selama belum diimplementasi: tanpa sodium → tidak bisa verifikasi → null.
        error_log('license-token: ekstensi sodium tidak tersedia; RSA fallback belum diimplementasi.');
        return null;
    }

    // 6) Binding domain: payload.domain harus cocok dengan domain instalasi.
    $payloadDomain = isset($payload['domain']) ? (string) $payload['domain'] : '';
    if (normalizeLicenseDomain($payloadDomain) !== normalizeLicenseDomain($selfDomain)) {
        return null;
    }

    // 7) Signature valid & domain cocok → kembalikan payload.
    return $payload;
}

/**
 * Evaluasi masa berlaku payload token yang signature-nya SUDAH valid.
 *
 * Menerapkan toleransi clock skew (hanya untuk meringankan false-positive
 * "sudah lewat padahal belum", bukan memperpanjang masa berlaku) dan grace
 * period offline yang sama untuk semua plan.
 *
 * @param  array $payload Payload hasil verifyLicenseTokenSignature().
 * @return array {
 *     @type string   $state            'valid' | 'grace' | 'expired'
 *     @type int|null $days_until_expiry Sisa hari sampai valid_until (bisa negatif
 *                                       bila sudah lewat); null bila valid_until tak ada.
 * }
 */
function evaluateLicenseTokenFreshness(array $payload): array
{
    $validUntilRaw = $payload['valid_until'] ?? '';
    $validUntilTs  = is_string($validUntilRaw) && $validUntilRaw !== ''
        ? strtotime($validUntilRaw)
        : false;

    // Tanpa valid_until yang valid → tidak bisa dipercaya → anggap expired.
    if ($validUntilTs === false) {
        return ['state' => 'expired', 'days_until_expiry' => null];
    }

    $now = time();

    // Skew hanya dipakai untuk meringankan: mundurkan "now" efektif sedikit,
    // sehingga jam server pembeli yang sedikit maju tidak salah memblokir.
    $effectiveNow = $now - (LICENSE_CLOCK_SKEW_TOLERANCE_HOURS * 3600);
    $graceEndTs   = $validUntilTs + (LICENSE_OFFLINE_GRACE_DAYS * 86400);

    if ($effectiveNow <= $validUntilTs) {
        $state = 'valid';
    } elseif ($effectiveNow <= $graceEndTs) {
        $state = 'grace';
    } else {
        $state = 'expired';
    }

    $daysUntilExpiry = (int) ceil(($validUntilTs - $now) / 86400);

    return ['state' => $state, 'days_until_expiry' => $daysUntilExpiry];
}

/**
 * Fungsi gabungan untuk dipanggil dari enforcement (bootstrap/installer/action).
 *
 * Menerima string signed_token (caller yang query dari DB), memverifikasi
 * signature, lalu mengevaluasi masa berlakunya. Tidak pernah melempar exception.
 *
 * @param  string|null $signedToken Token dari kolom license_info.signed_token.
 * @param  string      $selfDomain  Domain instalasi ini.
 * @return array {
 *     @type bool        $valid_signature  true bila signature valid & domain cocok.
 *     @type string      $state            'valid' | 'grace' | 'expired' | 'invalid'
 *                                         ('invalid' = signature gagal / token rusak).
 *     @type array|null  $payload          Payload token bila valid, selain itu null.
 *     @type int|null    $days_until_expiry Sisa hari sampai valid_until (UI warning).
 * }
 */
function resolveLicenseState(?string $signedToken, string $selfDomain): array
{
    $invalid = [
        'valid_signature'   => false,
        'state'             => 'invalid',
        'payload'           => null,
        'days_until_expiry' => null,
    ];

    if ($signedToken === null || trim($signedToken) === '') {
        return $invalid;
    }

    $payload = verifyLicenseTokenSignature($signedToken, $selfDomain);
    if ($payload === null) {
        return $invalid;
    }

    $fresh = evaluateLicenseTokenFreshness($payload);

    return [
        'valid_signature'   => true,
        'state'             => $fresh['state'],
        'payload'           => $payload,
        'days_until_expiry' => $fresh['days_until_expiry'],
    ];
}
