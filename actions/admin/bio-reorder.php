<?php
// POST /actions/admin/bio-reorder — geser urutan satu blok naik/turun (admin).
// Menormalkan ulang sort_order seluruh blok agar rapat (0..n).
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/biolink');
}

$id  = (int) ($_POST['block_id'] ?? 0);
$dir = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
if ($id <= 0) redirect('/admin/biolink');

try {
    $pdo = getDB();
    $ids = array_map('intval', $pdo->query('SELECT id FROM bio_blocks ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_COLUMN));
    $i   = array_search($id, $ids, true);
    if ($i !== false) {
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < count($ids)) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; // tukar posisi
            $upd = $pdo->prepare('UPDATE bio_blocks SET sort_order = ? WHERE id = ?');
            foreach ($ids as $order => $bid) $upd->execute([$order, $bid]);
            pageCacheFlushAll();
        }
    }
} catch (Throwable $e) {
    error_log('bio-reorder: ' . $e->getMessage());
}
redirect('/admin/biolink');
