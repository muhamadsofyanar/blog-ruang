<?php
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) { flash('error', 'Permintaan tidak valid.', 'error'); redirect('/admin/email-sequences'); }
$pdo = getDB(); $id = (int) ($_POST['id'] ?? 0); $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 160); $magnet = max(0, (int) ($_POST['lead_magnet_id'] ?? 0)); $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';
if ($name === '') { flash('error', 'Nama sequence wajib diisi.', 'error'); redirect('/admin/email-sequences'); }
try {
    if ($magnet > 0) {
        $magnetCheck = $pdo->prepare('SELECT id FROM lead_magnets WHERE id = ? LIMIT 1');
        $magnetCheck->execute([$magnet]);
        if (!$magnetCheck->fetchColumn()) throw new RuntimeException('Lead magnet tidak ditemukan.');
    }
    if ($id > 0) {
        $exists = $pdo->prepare('SELECT id FROM email_sequences WHERE id = ? LIMIT 1');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) throw new RuntimeException('Sequence tidak ditemukan.');
        $pdo->prepare("UPDATE email_sequences SET name=?, lead_magnet_id=NULLIF(?,0), status=?, updated_at=NOW() WHERE id=?")->execute([$name, $magnet, $status, $id]);
    } else {
        $pdo->prepare("INSERT INTO email_sequences (name, trigger_event, lead_magnet_id, provider, status) VALUES (?, 'subscriber_created', NULLIF(?,0), 'mailketing', ?)")->execute([$name, $magnet, $status]);
        $id = (int) $pdo->lastInsertId();
    }
    flash('success', 'Sequence disimpan.', 'success');
} catch (Throwable $e) { error_log('save email sequence: ' . $e->getMessage()); flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Sequence belum dapat disimpan.', 'error'); }
redirect('/admin/email-sequences?id=' . $id);
