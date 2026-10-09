<?php
// POST /actions/public/subscribe — langganan newsletter dari CTA beranda (publik).
// Pertahanan: CSRF + honeypot (field 'website' harus kosong) + rate-limit per-sesi.
// PRG: selalu kembali ke beranda dengan flash 'subscribe' (sukses/error inline).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/subscribers.php';

$returnPath = trim((string) ($_POST['return_path'] ?? ''));
$back = isSafeRedirectPath($returnPath) ? $returnPath : '/';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('subscribe', 'Permintaan tidak valid. Coba lagi.', 'error');
    redirect($back);
}

// Honeypot: bot mengisi field tersembunyi → tolak diam (pura-pura sukses).
if (trim($_POST['website'] ?? '') !== '') {
    flash('subscribe', 'Terima kasih! Email Anda telah didaftarkan.', 'success');
    redirect($back);
}

// Rate-limit sederhana per-sesi: maks 5 kiriman / 10 menit.
$now  = time();
$hits = array_filter((array) ($_SESSION['sub_hits'] ?? []), fn($t) => $t > $now - 600);
if (count($hits) >= 5) {
    flash('subscribe', 'Terlalu banyak percobaan. Silakan coba lagi nanti.', 'error');
    redirect($back);
}
$hits[] = $now;
$_SESSION['sub_hits'] = array_values($hits);

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    flash('subscribe', 'Format email tidak valid. Periksa kembali.', 'error');
    redirect($back);
}
$email = mb_strtolower($email);

try {
    $articleId = (int) ($_POST['source_article_id'] ?? 0);
    if ($articleId > 0) {
        $articleCheck = getDB()->prepare("SELECT id FROM articles WHERE id = ? AND status = 'published' LIMIT 1");
        $articleCheck->execute([$articleId]);
        if (!$articleCheck->fetchColumn()) $articleId = 0;
    }
    scribeCaptureSubscriber([
        'name' => trim((string) ($_POST['name'] ?? '')),
        'email' => $email,
        'source' => $articleId > 0 ? 'article_cta' : 'home_cta',
        'source_article_id' => $articleId,
        'source_url' => $back,
        'mailketing_list_id' => trim((string) getSetting('mailketing_subscriber_list_id', '')),
    ]);
    flash('subscribe', 'Berhasil! Terima kasih sudah berlangganan.', 'success');
} catch (Throwable $e) {
    // Duplikat lomba-balap (unique) → tetap ramah.
    if (str_contains($e->getMessage(), '1062') || str_contains(strtolower($e->getMessage()), 'duplicate')) {
        flash('subscribe', 'Email ini sudah terdaftar. Terima kasih!', 'success');
    } else {
        error_log('subscribe: ' . $e->getMessage());
        flash('subscribe', 'Terjadi kendala. Silakan coba lagi nanti.', 'error');
    }
}
redirect($back);
