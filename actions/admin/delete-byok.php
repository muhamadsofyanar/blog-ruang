<?php
// POST /actions/admin/delete-byok — hapus BYOK di gateway (kembali potong kredit).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/AverionAiAdapter.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/settings');
}
$adapter = new AverionAiAdapter();
$res = $adapter->deleteByok();
$adapter->invalidateBalanceCache();
flash($res['ok'] ? 'success' : 'error',
      $res['ok'] ? 'BYOK dihapus. Kredit akan dipotong kembali seperti biasa.' : 'Gagal menghapus BYOK: ' . $res['message'],
      $res['ok'] ? 'success' : 'error');
redirect('/admin/settings');
