<?php
// POST /actions/admin/save-tag — buat/ubah tag (admin).
require_once __DIR__ . '/../../bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/tags');
}

$id     = (int) ($_POST['id'] ?? 0);
$name   = trim($_POST['name'] ?? '');
$slugIn = trim($_POST['slug'] ?? '');

if ($name === '') {
    flash('error', 'Nama tag wajib diisi.', 'error');
    redirect('/admin/tags');
}

$pdo = getDB();
$slug = uniqueSlug($pdo, 'tags', $slugIn !== '' ? $slugIn : $name, $id ?: null);

try {
    if ($id > 0) {
        $pdo->prepare("UPDATE tags SET name = ?, slug = ? WHERE id = ?")->execute([$name, $slug, $id]);
        flash('success', 'Tag diperbarui.', 'success');
    } else {
        $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
        flash('success', 'Tag ditambahkan.', 'success');
    }
} catch (Throwable $e) {
    error_log('save-tag: ' . $e->getMessage());
    flash('error', 'Gagal menyimpan tag.', 'error');
}
redirect('/admin/tags');
