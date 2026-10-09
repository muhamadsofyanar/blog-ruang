<?php
// ════════════════════════════════════════════════════════════════════════
// AverionAiAdapter — SATU-SATUNYA jalur panggilan AI dari client ke gateway
// ai.averion.id. Client TIDAK PERNAH memanggil provider AI langsung (BYOK pun
// lewat gateway). Diskriminator respons gateway: ok:true|false (BUKAN success).
// Kontrak gateway: helpers/generate.php + support-endpoints.php (averion-ai).
// ════════════════════════════════════════════════════════════════════════

class AverionAiAdapter
{
    private string $baseUrl;
    private string $licenseKey;
    private string $domain;
    private string $clientVersion;

    /** Task yang menerima variabel Brand Voice (dilampirkan otomatis). */
    private const BRAND_VOICE_TASKS = ['keyword_research', 'outline', 'draft_section', 'faq'];
    private const TASK_TYPES = ['keyword_research', 'outline', 'draft_section', 'meta', 'faq', 'analysis'];

    public function __construct()
    {
        $this->baseUrl       = rtrim(defined('AI_GATEWAY_URL') ? AI_GATEWAY_URL : 'https://ai.averion.id', '/');
        $this->licenseKey    = (string) getSetting('license_key', '');
        // X-Domain: domain aktivasi lisensi (fallback host APP_URL).
        $this->domain        = (string) getSetting('license_domain', '') ?: (function_exists('parseDomainFromAppUrl') ? parseDomainFromAppUrl() : '');
        $this->clientVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
    }

    // ─── API publik ─────────────────────────────────────────────

    /** Uji koneksi + auth. Return array standar (lihat request()). */
    public function ping(): array
    {
        return $this->request('GET', '/api/ping');
    }

    /**
     * Semua task AI. $taskType salah satu dari 6 jenis. $payload = data task
     * (keyword, outline, dst). Brand Voice dilampirkan otomatis bila relevan.
     */
    public function generate(string $taskType, array $payload): array
    {
        if (!in_array($taskType, self::TASK_TYPES, true)) {
            return $this->localError('Task AI tidak dikenal: ' . $taskType);
        }
        // Lampirkan Brand Voice (variabel milik customer) untuk task yang butuh.
        if (in_array($taskType, self::BRAND_VOICE_TASKS, true) && !isset($payload['brand_voice'])) {
            $bv = $this->brandVoice();
            if ($bv !== null) $payload['brand_voice'] = $bv;
        }
        $language = $payload['language'] ?? getSetting('default_language', 'id');
        return $this->request('POST', '/api/generate', [
            'task_type' => $taskType,
            'payload'   => $payload,
            'language'  => $language,
        ]);
    }

    public function getBalance(): array
    {
        return $this->request('GET', '/api/balance');
    }

    public function getUsage(int $page = 1, int $perPage = 20): array
    {
        return $this->request('GET', '/api/usage?page=' . max(1, $page) . '&per_page=' . $perPage);
    }

    /** TTL cache saldo (detik). */
    private const BALANCE_TTL = 300;

    /**
     * Saldo dengan cache transient 5 menit di settings. $force melewati cache.
     * Menambah field 'cached' (bool) + 'age' (detik) pada hasil sukses.
     */
    public function getBalanceCached(bool $force = false): array
    {
        $at     = (int) getSetting('ai_balance_cache_at', '0');
        $cached = (string) getSetting('ai_balance_cache', '');
        if (!$force && $cached !== '' && (time() - $at) < self::BALANCE_TTL) {
            $d = json_decode($cached, true);
            if (is_array($d)) {
                return ['ok' => true, 'http' => 200, 'data' => $d, 'code' => null, 'message' => '',
                        'flags' => [], 'balance' => null, 'raw' => [], 'cached' => true, 'age' => time() - $at];
            }
        }
        $res = $this->getBalance();
        if ($res['ok'] && is_array($res['data'])) {
            setSetting('ai_balance_cache', json_encode($res['data']), 'ai');
            setSetting('ai_balance_cache_at', (string) time(), 'ai');
        }
        $res['cached'] = false;
        $res['age']    = 0;
        return $res;
    }

    /** Paksa saldo diambil ulang pada permintaan berikutnya (dipanggil pasca-generate). */
    public function invalidateBalanceCache(): void
    {
        setSetting('ai_balance_cache_at', '0', 'ai');
    }

    public function getRuleset(): array
    {
        return $this->request('GET', '/api/ruleset');
    }

    public function saveByok(string $provider, string $apiKey): array
    {
        return $this->request('POST', '/api/byok', ['provider' => $provider, 'api_key' => $apiKey]);
    }

    public function deleteByok(): array
    {
        return $this->request('DELETE', '/api/byok');
    }

    // ─── Brand Voice (dari settings client) ─────────────────────

    /** Rakit objek Brand Voice dari settings (null bila kosong semua). */
    public function brandVoice(): ?array
    {
        $business    = trim((string) getSetting('brandvoice_business', ''));
        $audience    = trim((string) getSetting('brandvoice_audience', ''));
        $tone        = trim((string) getSetting('brandvoice_tone', ''));
        $bannedRaw   = (string) getSetting('brandvoice_banned_words', '');
        $styleSample = trim((string) getSetting('brandvoice_style_sample', ''));

        $banned = json_decode($bannedRaw, true);
        if (!is_array($banned)) $banned = [];

        if ($business === '' && $audience === '' && $tone === '' && !$banned && $styleSample === '') {
            return null;
        }
        return [
            'business'      => $business,
            'audience'      => $audience,
            'tone'          => $tone,
            'banned_words'  => array_values($banned),
            'style_sample'  => $styleSample,
        ];
    }

    // ─── Inti request + normalisasi ─────────────────────────────

    private function localError(string $msg): array
    {
        return ['ok' => false, 'http' => 0, 'data' => null, 'code' => 'CLIENT_ERROR',
                'message' => $msg, 'flags' => [], 'balance' => null, 'raw' => []];
    }

    /**
     * Request ke gateway + normalisasi respons ke bentuk standar:
     *   ['ok'=>bool,'http'=>int,'data'=>?array,'code'=>?string,'message'=>string,
     *    'flags'=>array,'balance'=>?array,'raw'=>array]
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        if ($this->licenseKey === '') {
            return $this->localError('Lisensi belum diaktifkan. Aktifkan lisensi terlebih dahulu.');
        }

        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'X-License-Key: ' . $this->licenseKey,
            'X-Domain: ' . $this->domain,
            'X-Client-Version: ' . $this->clientVersion,
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_CUSTOMREQUEST   => $method,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 130, // di atas timeout gateway (120)
            CURLOPT_CONNECTTIMEOUT  => 10,
        ];
        if ($body !== null) {
            $json = json_encode($body);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = $json;
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $raw   = curl_exec($ch);
        $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $cerr !== '') {
            error_log('[adapter] ' . $method . ' ' . $path . ' gagal: ' . $cerr);
            return [
                'ok' => false, 'http' => 0, 'data' => null, 'code' => 'GATEWAY_UNREACHABLE',
                'message' => 'Tidak bisa terhubung ke layanan AI. Periksa koneksi lalu coba lagi.',
                'flags' => ['retry' => true], 'balance' => null, 'raw' => [],
            ];
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return [
                'ok' => false, 'http' => $http, 'data' => null, 'code' => 'BAD_RESPONSE',
                'message' => 'Respons layanan AI tidak valid.', 'flags' => ['retry' => true],
                'balance' => null, 'raw' => [],
            ];
        }

        $ok = ($data['ok'] ?? false) === true;
        if ($ok) {
            // generate membungkus di 'data'; endpoint lain menaruh payload di top-level.
            $payload = array_key_exists('data', $data) ? $data['data'] : $data;
            unset($payload['ok']);
            return [
                'ok' => true, 'http' => $http, 'data' => $payload, 'code' => null,
                'message' => '', 'flags' => [], 'balance' => $data['balance'] ?? null, 'raw' => $data,
            ];
        }

        // Error → mapping pesan + flag UI.
        $code = (string) ($data['code'] ?? 'UNKNOWN');
        [$msg, $flags] = $this->mapError($code, (string) ($data['message'] ?? ''));
        return [
            'ok' => false, 'http' => $http, 'data' => null, 'code' => $code,
            'message' => $msg, 'flags' => $flags, 'balance' => $data['balance'] ?? null, 'raw' => $data,
        ];
    }

    /** Kode error gateway → [pesan Indonesia ramah, flags UI]. */
    private function mapError(string $code, string $serverMsg): array
    {
        switch ($code) {
            case 'CLIENT_OUTDATED':
                return ['Versi aplikasi terlalu lama untuk memakai layanan AI. Perbarui aplikasi terlebih dahulu.',
                        ['outdated' => true, 'update_url' => '/admin/update']];
            case 'INSUFFICIENT_CREDITS':
            case 'NO_CREDIT_ACCOUNT':
                return ['Kredit AI tidak cukup untuk menjalankan tugas ini. Beli kredit untuk melanjutkan.',
                        ['show_topup' => true]];
            case 'RATE_LIMITED':
            case 'PROVIDER_ERROR':
                return ['Gateway sedang sibuk, coba lagi sebentar.', ['retry' => true]];
            case 'DOMAIN_MISMATCH':
            case 'LICENSE_INVALID':
            case 'LICENSE_INACTIVE':
                return ['Ada masalah dengan lisensi Anda untuk layanan AI. Periksa status lisensi.',
                        ['relicense' => true, 'license_url' => '/admin/license']];
            case 'BYOK_DECRYPT_FAILED':
                return ['Gagal memakai API key Anda. Simpan ulang API key di pengaturan BYOK.', []];
            case 'BYOK_KEY_INVALID':
                return ['API key tidak valid atau tidak dapat diverifikasi oleh provider.', []];
            case 'UNSUPPORTED_PROVIDER':
            case 'BYOK_PROVIDER_UNSUPPORTED':
                return ['Provider BYOK belum didukung oleh layanan AI.', []];
            default:
                return [$serverMsg !== '' ? $serverMsg : 'Terjadi kesalahan pada layanan AI. Coba lagi.', []];
        }
    }
}
