<?php
// POST /actions/admin/ingest-token — generate/putar ulang token Ingest API.
// Simpan HANYA hash-nya; token mentah ditaruh sekali di session flash lalu
// ditampilkan SEKALI di halaman Integrasi (tak bisa dilihat ulang).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/ingest.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/integrations');
}

$raw = ingestGenerateToken();               // simpan hash, kembalikan token mentah
$_SESSION['ingest_token_once'] = $raw;       // tampil sekali di halaman berikutnya

flash('success', 'Token baru dibuat. Salin sekarang — tidak bisa dilihat ulang.', 'success');
redirect('/admin/integrations');
