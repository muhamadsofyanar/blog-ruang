<?php
// POST /actions/public/lead-magnet — tangkap nama/email + sumber artikel.
// Mendukung dua mode respons TANPA mengubah logic capture:
//  - AJAX (header X-Requested-With: XMLHttpRequest) → JSON {ok,error,success}
//    sehingga frontend bisa mengganti form jadi state sukses tanpa reload/loncat.
//  - Non-AJAX (fallback) → flash + redirect balik ke anchor form seperti semula.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/lead-magnet.php';
require_once __DIR__ . '/../../helpers/subscribers.php';

$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

$returnPath = trim((string) ($_POST['return_path'] ?? ''));
$back = isSafeRedirectPath($returnPath) ? $returnPath : '/';
$anchor = trim((string) ($_POST['return_anchor'] ?? ''));
if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,80}$/', $anchor)) $back .= '#' . $anchor;

/**
 * Balas sesuai mode. $extra untuk payload AJAX (mis. blok success).
 * Non-AJAX tetap flash + redirect (perilaku lama).
 */
function lmRespond(bool $ajax, bool $ok, string $msg, string $back, array $extra = []): void
{
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(array_merge(['ok' => $ok, 'error' => $ok ? null : $msg, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    flash('lead_magnet', $msg, $ok ? 'success' : 'error');
    redirect($back);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    lmRespond($isAjax, false, 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.', $back);
}
// Honeypot: anggap sukses senyap (jangan bocorkan ke bot).
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    $t = scribeLeadMagnetSuccessText(null);
    lmRespond($isAjax, true, 'Pendaftaran berhasil diproses.', $back, $isAjax ? ['success' => ['title' => $t['title'], 'desc' => $t['desc'], 'btn' => $t['btn'], 'download_url' => '']] : []);
}

$magnetId = (int) ($_POST['lead_magnet_id'] ?? 0);
$magnet = scribeLeadMagnetById($magnetId);
if (!$magnet) { lmRespond($isAjax, false, 'Lead magnet sedang tidak tersedia.', $back); }

$now = time();
$hits = array_filter((array) ($_SESSION['lead_magnet_hits'] ?? []), fn($t) => $t > $now - 600);
if (count($hits) >= 5) { lmRespond($isAjax, false, 'Terlalu banyak percobaan. Silakan coba lagi nanti.', $back); }
$hits[] = $now; $_SESSION['lead_magnet_hits'] = array_values($hits);

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
if (mb_strlen($name) < 2 || mb_strlen($name) > 120) { lmRespond($isAjax, false, 'Nama wajib diisi.', $back); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) { lmRespond($isAjax, false, 'Format email tidak valid.', $back); }

try {
    $articleId = (int) ($_POST['source_article_id'] ?? 0);
    if ($articleId > 0) {
        $st = getDB()->prepare("SELECT id FROM articles WHERE id = ? AND status = 'published' LIMIT 1"); $st->execute([$articleId]);
        if (!$st->fetchColumn()) $articleId = 0;
    }
    scribeCaptureSubscriber([
        'name' => $name, 'email' => $email, 'source' => $articleId > 0 ? 'article_lead_magnet' : 'lead_magnet',
        'source_article_id' => $articleId, 'source_url' => trim((string) ($_POST['source_url'] ?? '')),
        'lead_magnet_id' => $magnetId, 'mailketing_list_id' => trim((string) getSetting('mailketing_subscriber_list_id', '')),
    ]);
    $t = scribeLeadMagnetSuccessText($magnet);
    lmRespond($isAjax, true, 'Terima kasih. Lead magnet siap diunduh.', $back, $isAjax ? [
        'success' => ['title' => $t['title'], 'desc' => $t['desc'], 'btn' => $t['btn'], 'download_url' => scribeLeadMagnetUrl($magnet)],
    ] : []);
} catch (Throwable $e) {
    error_log('lead magnet submit: ' . $e->getMessage());
    $msg = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Terjadi kendala. Silakan coba lagi.';
    lmRespond($isAjax, false, $msg, $back);
}
