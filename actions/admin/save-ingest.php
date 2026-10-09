<?php
// POST /actions/admin/save-ingest — simpan konfigurasi Ingest API (admin):
// toggle aktif + penulis default. Token TIDAK diubah di sini (lihat ingest-token).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/ingest.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/integrations');
}

$enabled = isset($_POST['ingest_enabled']) ? '1' : '0';

// Penulis default: hanya terima id user staf yang aktif.
$pdo      = getDB();
$authorIn = (int) ($_POST['ingest_author_id'] ?? 0);
$authorId = '';
if ($authorIn > 0) {
    $st = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $st->execute([$authorIn]);
    if ($st->fetchColumn() !== false) {
        $authorId = (string) $authorIn;
    }
}

// API manajemen (tulis kategori & setelan — LANGSUNG LIVE). Default mati.
$manage = isset($_POST['ingest_manage_enabled']) ? '1' : '0';

// Cegah mengaktifkan tanpa token — endpoint akan menolak semua request.
if (($enabled === '1' || $manage === '1') && !ingestTokenIsSet()) {
    flash('error', 'Buat token dulu sebelum mengaktifkan Ingest API.', 'error');
    redirect('/admin/integrations');
}

setSetting('ingest_enabled', $enabled, 'ingest');
setSetting('ingest_author_id', $authorId, 'ingest');
setSetting('ingest_manage_enabled', $manage, 'ingest');

// Gambar otomatis (opsional). Kill-switch + API key provider (RAHASIA).
setSetting('ingest_images_enabled', isset($_POST['ingest_images_enabled']) ? '1' : '0', 'ingest');
if (isset($_POST['pexels_clear'])) {
    setSetting('pexels_api_key', '', 'stock');
} else {
    $pexelsKey = trim((string) ($_POST['pexels_api_key'] ?? ''));
    if ($pexelsKey !== '') {
        setSetting('pexels_api_key', $pexelsKey, 'stock'); // hanya ubah bila diisi
    }
}

flash('success', 'Pengaturan Ingest API disimpan.', 'success');
redirect('/admin/integrations');
