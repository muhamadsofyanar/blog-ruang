<?php
// Redirect manager (admin). Listing + tambah manual + hapus + search + pagination.
require_once __DIR__ . '/_shell.php';
$pdo = getDB();

$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$q       = trim($_GET['q'] ?? '');

$where = ''; $args = [];
if ($q !== '') { $where = 'WHERE old_slug LIKE ? OR new_slug LIKE ?'; $args = ['%' . $q . '%', '%' . $q . '%']; }

try {
    $cst = $pdo->prepare("SELECT COUNT(*) FROM redirects $where");
    $cst->execute($args);
    $total = (int) $cst->fetchColumn();
} catch (Throwable $e) { $total = 0; }
$totalPages = max(1, (int) ceil($total / $perPage));

try {
    $st = $pdo->prepare("SELECT id, old_slug, new_slug, type, source, created_at FROM redirects $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $st->execute($args);
    $rows = $st->fetchAll();
} catch (Throwable $e) { $rows = []; }

admin_shell_top('Redirects', '/admin/redirects');
?>
<div class="grid lg:grid-cols-3 gap-4">
  <!-- Form tambah -->
  <div class="lg:col-span-1">
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 sticky top-20">
      <h2 class="font-display font-semibold text-sm mb-4">Tambah Redirect Manual</h2>
      <form method="post" action="<?= e(url('/actions/admin/save-redirect')) ?>" class="space-y-3">
        <?= csrfField() ?>
        <div>
          <label class="block text-xs font-medium mb-1">Slug Lama (old)</label>
          <input name="old_slug" required placeholder="slug-lama" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono">
          <p class="text-xs text-gray-400 mt-1">Tidak boleh sama dengan slug artikel yang masih hidup.</p>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Tujuan (slug baru atau URL)</label>
          <input name="new_slug" required placeholder="slug-baru atau https://…" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Tipe</label>
          <select name="type" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
            <option value="301">301 (Permanen)</option>
            <option value="302">302 (Sementara)</option>
          </select>
        </div>
        <button type="submit" class="w-full rounded-md py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Tambah</button>
      </form>
    </div>
  </div>

  <!-- Listing -->
  <div class="lg:col-span-2">
    <form method="get" action="<?= e(url('/admin/redirects')) ?>" class="mb-3">
      <div class="relative max-w-xs">
        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"><?= icon('search', 'w-4 h-4') ?></span>
        <input name="q" value="<?= e($q) ?>" placeholder="Cari slug…" class="w-full pl-8 pr-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
      </div>
    </form>
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-x-auto">
      <?php if (!$rows): ?>
        <div class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
          <?= icon('arrow-left-right', 'w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600') ?>
          <?= $q !== '' ? 'Tidak ada redirect yang cocok.' : 'Belum ada redirect. Redirect otomatis muncul saat slug artikel published berubah.' ?>
        </div>
      <?php else: ?>
        <table class="w-full text-sm min-w-[520px]">
          <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400">
            <tr>
              <th class="text-left font-medium px-4 py-2.5">Dari &rarr; Ke</th>
              <th class="text-left font-medium px-4 py-2.5">Tipe</th>
              <th class="text-left font-medium px-4 py-2.5">Sumber</th>
              <th class="text-right font-medium px-4 py-2.5">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <?php foreach ($rows as $r): ?>
            <tr>
              <td class="px-4 py-2.5">
                <span class="font-mono text-xs"><?= e($r['old_slug']) ?></span>
                <span class="text-gray-400 mx-1">&rarr;</span>
                <span class="font-mono text-xs text-gray-600 dark:text-gray-300"><?= e($r['new_slug']) ?></span>
              </td>
              <td class="px-4 py-2.5"><span class="text-xs font-medium"><?= (int) $r['type'] ?></span></td>
              <td class="px-4 py-2.5">
                <?php $sc = $r['source'] === 'manual' ? 'sky' : 'gray'; ?>
                <span class="inline-block text-[11px] font-medium px-2 py-0.5 rounded-md bg-<?= $sc ?>-100 text-<?= $sc ?>-700 dark:bg-<?= $sc ?>-950/50 dark:text-<?= $sc ?>-300"><?= $r['source'] === 'manual' ? 'Manual' : 'Otomatis' ?></span>
              </td>
              <td class="px-4 py-2.5 text-right">
                <form method="post" action="<?= e(url('/actions/admin/delete-redirect')) ?>" data-confirm="Hapus redirect ini?" data-confirm-danger class="inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" title="Hapus"><?= icon('trash', 'w-4 h-4') ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between mt-4 text-sm">
      <span class="text-gray-500">Halaman <?= $page ?> dari <?= $totalPages ?> · <?= $total ?> redirect</span>
      <div class="flex gap-1">
        <?php $qp = $q !== '' ? '&q=' . urlencode($q) : ''; ?>
        <?php if ($page > 1): ?><a href="<?= e(url('/admin/redirects?page=' . ($page - 1) . $qp)) ?>" class="px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Sebelumnya</a><?php endif; ?>
        <?php if ($page < $totalPages): ?><a href="<?= e(url('/admin/redirects?page=' . ($page + 1) . $qp)) ?>" class="px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Berikutnya</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_shell_bottom(); ?>
