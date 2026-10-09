<?php
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) { flash('error', 'Permintaan tidak valid.', 'error'); redirect('/admin/lead-magnets'); }
$pdo = getDB();
$id = (int) ($_POST['id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
$slugBase = trim((string) ($_POST['slug'] ?? '')) ?: $title;
$description = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 1000);
$delivery = trim((string) ($_POST['delivery_url'] ?? ''));
$label = mb_substr(trim((string) ($_POST['cta_label'] ?? 'Dapatkan Gratis')) ?: 'Dapatkan Gratis', 0, 80);
$status = in_array($_POST['status'] ?? '', ['draft', 'published', 'paused'], true) ? $_POST['status'] : 'draft';
if ($title === '' || mb_strlen($title) > 190) { flash('error', 'Judul lead magnet wajib diisi.', 'error'); redirect('/admin/lead-magnets' . ($id ? '?id=' . $id : '')); }
if ($delivery !== '' && !preg_match('#^(https?://|[A-Za-z0-9_./-]+$)#i', $delivery)) { flash('error', 'URL materi tidak valid.', 'error'); redirect('/admin/lead-magnets' . ($id ? '?id=' . $id : '')); }
$base = slugify($slugBase); $slug = $base; $n = 2;
while (true) { $st = $pdo->prepare('SELECT id FROM lead_magnets WHERE slug = ?' . ($id ? ' AND id <> ?' : '') . ' LIMIT 1'); $st->execute($id ? [$slug, $id] : [$slug]); if (!$st->fetchColumn()) break; $slug = $base . '-' . $n++; }
try {
    if ($id > 0) $pdo->prepare('UPDATE lead_magnets SET title=?, slug=?, description=?, delivery_url=?, cta_label=?, status=?, updated_at=NOW() WHERE id=?')->execute([$title, $slug, $description ?: null, $delivery ?: null, $label, $status, $id]);
    else { $pdo->prepare('INSERT INTO lead_magnets (title, slug, description, delivery_url, cta_label, status) VALUES (?, ?, ?, ?, ?, ?)')->execute([$title, $slug, $description ?: null, $delivery ?: null, $label, $status]); $id = (int) $pdo->lastInsertId(); }
    if (isset($_POST['make_default'])) setSetting('lead_magnet_default_id', (string) $id, 'lead_magnet');
    elseif ((int) getSetting('lead_magnet_default_id', '0') === $id) setSetting('lead_magnet_default_id', '0', 'lead_magnet');
    require_once __DIR__ . '/../../helpers/page-cache.php'; pageCacheFlushAll();
    flash('success', 'Lead magnet disimpan.', 'success');
} catch (Throwable $e) { error_log('save lead magnet: ' . $e->getMessage()); flash('error', 'Lead magnet belum dapat disimpan.', 'error'); }
redirect('/admin/lead-magnets?id=' . $id);
