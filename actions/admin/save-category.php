<?php
// POST /actions/admin/save-category — buat/ubah kategori (admin).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/sitemap.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
require_once __DIR__ . '/../../helpers/article-cta.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/categories');
}

$id     = (int) ($_POST['id'] ?? 0);
$name   = trim($_POST['name'] ?? '');
$slugIn = trim($_POST['slug'] ?? '');
$parent = (int) ($_POST['parent_id'] ?? 0) ?: null;
$desc   = trim($_POST['description'] ?? '');

if ($name === '') {
    flash('error', 'Nama kategori wajib diisi.', 'error');
    redirect('/admin/categories');
}

$pdo = getDB();
$categoryCta = null;
try {
    if (array_key_exists('article_cta', $_POST)) {
        if (!is_array($_POST['article_cta'])) throw new InvalidArgumentException('Format CTA tidak valid.');
        $categoryCta = articleCtaValidate($_POST['article_cta']);
    }
} catch (InvalidArgumentException $e) {
    flash('error', $e->getMessage(), 'error'); redirect('/admin/categories');
}
$slug = uniqueSlug($pdo, 'categories', $slugIn !== '' ? $slugIn : $name, $id ?: null);

// Cegah kategori jadi induk dirinya sendiri.
if ($parent !== null && $parent === $id) {
    $parent = null;
}

try {
    $pdo->beginTransaction();
    if ($id > 0) {
        $checkCategory = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $checkCategory->execute([$id]);
        if (!$checkCategory->fetchColumn()) throw new RuntimeException('Kategori tidak ditemukan.');
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $desc !== '' ? $desc : null, $parent, $id]);
        flash('success', 'Kategori diperbarui.', 'success');
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $desc !== '' ? $desc : null, $parent]);
        $id = (int) $pdo->lastInsertId();
        flash('success', 'Kategori ditambahkan.', 'success');
    }
    if ($categoryCta !== null) articleCtaSave('category', $id, $categoryCta);
    $pdo->commit();
    pageCacheFlushAll(); // CTA kategori memengaruhi semua artikel di kategori ini.
    scribeGenerateSitemap();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    unset($_SESSION['flash']['success']);
    error_log('save-category: ' . $e->getMessage());
    flash('error', 'Gagal menyimpan kategori.', 'error');
}
redirect('/admin/categories');
