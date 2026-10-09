<?php
// ════════════════════════════════════════════════════════════════════════
// Cron endpoint — GET/POST /api/cron.php?key=<CRON_KEY>&task=email
// Memproses antrean email (list-add Mailketing + email sequence jatuh tempo)
// secara andal TANPA bergantung pada kunjungan halaman. Tempel URL-nya ke
// penjadwal hosting (cPanel Cron Jobs / crontab), mis. tiap 5 menit.
//
// Endpoint mesin: tanpa sesi/CSRF. Otorisasi via kunci rahasia (query ?key= atau
// header Authorization: Bearer <key>). Aman dipanggil berkali-kali (idempoten):
// antrean memakai klaim atomik + retry terbatas sehingga tidak mengirim ganda.
// Diakses sebagai file nyata (tidak perlu route di front controller).
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/mailketing.php';
require_once __DIR__ . '/../helpers/email-sequence.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Kunci dari query, body, atau header Authorization: Bearer.
$key = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
if ($key === '') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($auth === '' && function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        $auth = $h['Authorization'] ?? ($h['authorization'] ?? '');
    }
    if (preg_match('/^Bearer\s+(.+)$/i', trim((string) $auth), $m)) $key = trim($m[1]);
}

if (!scribeCronVerify($key)) {
    http_response_code(403);
    scribeEmailWorkerLog('cron DITOLAK (kunci salah) ip=' . ($_SERVER['REMOTE_ADDR'] ?? '-'));
    echo json_encode(['ok' => false, 'error' => 'Kunci cron tidak valid.']);
    exit;
}

// Cron punya anggaran waktu lebih longgar dari request halaman.
@set_time_limit(120);
ignore_user_abort(true);

$t0 = microtime(true);
scribeEmailWorkerLog('cron MULAI');
$mk  = scribeProcessMailketingQueue(50);
$seq = scribeProcessDueEmailSequenceSends(50);
$ms  = (int) round((microtime(true) - $t0) * 1000);
scribeEmailWorkerLog('cron SELESAI ' . $ms . 'ms list-add(sent=' . (int) $mk['sent'] . ' failed=' . (int) $mk['failed'] . ') email(sent=' . (int) $seq['sent'] . ' failed=' . (int) $seq['failed'] . ')');

echo json_encode([
    'ok'       => true,
    'at'       => date('c'),
    'duration_ms' => $ms,
    'list_add' => $mk,
    'email'    => $seq,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
