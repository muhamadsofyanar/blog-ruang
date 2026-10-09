<?php
// POST /actions/admin/save-redirect — tambah redirect manual (admin). Validasi
// old_slug bukan slug artikel hidup + ringkas rantai (luruskan ke tujuan akhir).
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/redirects');
}

$pdo  = getDB();
$old  = slugify(trim($_POST['old_slug'] ?? ''));
$type = (int) ($_POST['type'] ?? 301);
if (!in_array($type, [301, 302], true)) $type = 301;

// Tujuan: URL penuh disimpan apa adanya, selain itu diperlakukan slug.
$newRaw = trim($_POST['new_slug'] ?? '');
$isUrl  = (bool) preg_match('#^https?://#i', $newRaw);
$new    = $isUrl ? $newRaw : slugify($newRaw);

if ($old === '' || $new === '') {
    flash('error', 'Slug lama dan tujuan wajib diisi.', 'error');
    redirect('/admin/redirects');
}
if ($old === $new) {
    flash('error', 'Slug lama dan tujuan tidak boleh sama.', 'error');
    redirect('/admin/redirects');
}

// old_slug tidak boleh = slug artikel yang masih hidup.
$chk = $pdo->prepare("SELECT id FROM articles WHERE slug = ? LIMIT 1");
$chk->execute([$old]);
if ($chk->fetchColumn() !== false) {
    flash('error', 'Slug lama masih dipakai artikel yang hidup — tidak bisa dijadikan redirect.', 'error');
    redirect('/admin/redirects');
}

// Ringkas rantai (hanya untuk tujuan slug internal): bila tujuan sendiri sudah
// ter-redirect, luruskan ke tujuan akhir (cegah rantai + loop, batasi iterasi).
if (!$isUrl) {
    $seen = [$old => true];
    for ($i = 0; $i < 20; $i++) {
        $r = $pdo->prepare("SELECT new_slug FROM redirects WHERE old_slug = ? LIMIT 1");
        $r->execute([$new]);
        $next = $r->fetchColumn();
        if ($next === false || isset($seen[$next]) || preg_match('#^https?://#i', (string) $next)) break;
        $seen[$new] = true;
        $new = (string) $next;
    }
    if ($old === $new) {
        flash('error', 'Redirect akan membentuk loop — dibatalkan.', 'error');
        redirect('/admin/redirects');
    }
}

try {
    $pdo->prepare("INSERT INTO redirects (old_slug, new_slug, type, source) VALUES (?, ?, ?, 'manual')
                   ON DUPLICATE KEY UPDATE new_slug = VALUES(new_slug), type = VALUES(type), source = 'manual'")
        ->execute([$old, $new, $type]);
    flash('success', 'Redirect disimpan.', 'success');
} catch (Throwable $e) {
    error_log('save-redirect: ' . $e->getMessage());
    flash('error', 'Gagal menyimpan redirect.', 'error');
}
redirect('/admin/redirects');
