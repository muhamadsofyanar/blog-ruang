<?php
// POST /actions/admin/toggle-pin — sematkan/lepas artikel sebagai featured beranda
// (admin). Satu artikel pinned: pin baru MELEPAS pin lama (atomik).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
requireAdmin();

$back = '/admin/articles';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect($back);
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) { redirect($back); }

try {
    $pdo = getDB();
    $st  = $pdo->prepare("SELECT is_pinned FROM articles WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $cur = $st->fetchColumn();
    if ($cur === false) {
        flash('error', 'Artikel tidak ditemukan.', 'error');
        redirect($back);
    }

    $pdo->beginTransaction();
    // Lepas semua pin dulu (single pinned invariant).
    $pdo->exec("UPDATE articles SET is_pinned = 0 WHERE is_pinned = 1");
    if ((int) $cur === 0) {
        $pdo->prepare("UPDATE articles SET is_pinned = 1 WHERE id = ?")->execute([$id]);
        $msg = 'Artikel disematkan sebagai featured beranda.';
    } else {
        $msg = 'Sematan dilepas.';
    }
    $pdo->commit();
    pageCacheInvalidate(pageCacheHomePaths()); // pin mengubah featured beranda
    flash('success', $msg, 'success');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('toggle-pin: ' . $e->getMessage());
    flash('error', 'Gagal mengubah sematan.', 'error');
}
redirect($back);
