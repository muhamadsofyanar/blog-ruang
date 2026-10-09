<?php
// POST /actions/admin/save-lead-success — simpan teks tampilan sukses Lead Magnet
// (global, settings grup 'lead_magnet'). Kosong = pakai default di frontend.
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/lead-magnets');
}

setSetting('lead_success_title', mb_substr(trim((string) ($_POST['lead_success_title'] ?? '')), 0, 190), 'lead_magnet');
setSetting('lead_success_desc', mb_substr(trim((string) ($_POST['lead_success_desc'] ?? '')), 0, 500), 'lead_magnet');
setSetting('lead_success_btn', mb_substr(trim((string) ($_POST['lead_success_btn'] ?? '')), 0, 80), 'lead_magnet');

flash('success', 'Teks tampilan sukses disimpan.', 'success');
redirect('/admin/lead-magnets');
