<?php
// Kelola kategori (admin). Listing + form tambah/edit + hapus (guard artikel).
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/article-cta.php';
$pdo = getDB();

// Kategori + jumlah artikel + nama parent.
try {
    $cats = $pdo->query(
        "SELECT c.id, c.name, c.slug, c.description, c.parent_id, p.name AS parent_name,
                (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id) AS article_count
         FROM categories c
         LEFT JOIN categories p ON p.id = c.parent_id
         ORDER BY c.name ASC"
    )->fetchAll();
} catch (Throwable $e) { $cats = []; }

// Kandidat parent (semua kategori — 1 level di UI, tapi izinkan pilih apa pun kecuali diri sendiri).
$parents = array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $cats);
foreach ($cats as &$ctaCat) $ctaCat['article_cta'] = articleCtaGet('category', (int) $ctaCat['id']);
unset($ctaCat);

admin_shell_top('Kategori', '/admin/categories');
?>
<div class="grid lg:grid-cols-3 gap-4">
  <!-- Form -->
  <div class="lg:col-span-1">
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 sticky top-20">
      <h2 id="formTitle" class="font-display font-semibold text-sm mb-4">Tambah Kategori</h2>
      <form method="post" action="<?= e(url('/actions/admin/save-category')) ?>" class="space-y-3" id="catForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="catId" value="">
        <div>
          <label class="block text-xs font-medium mb-1">Nama</label>
          <input name="name" id="catName" required class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Slug <span class="text-gray-400">(otomatis, boleh diubah)</span></label>
          <input name="slug" id="catSlug" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Induk (opsional)</label>
          <select name="parent_id" id="catParent" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
            <option value="">— Tanpa induk —</option>
            <?php foreach ($parents as $p): ?>
              <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Deskripsi</label>
          <textarea name="description" id="catDesc" rows="3" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></textarea>
        </div>
        <?php
        $ctaValue = articleCtaValidate([]);
        $ctaGlobal = articleCtaResolve([]);
        require __DIR__ . '/_article-cta-fields.php';
        ?>
        <div class="flex gap-2 pt-1">
          <button type="submit" class="flex-1 rounded-md py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan</button>
          <button type="button" onclick="resetCatForm()" class="rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Reset</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Listing -->
  <div class="lg:col-span-2">
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
      <?php if (!$cats): ?>
        <div class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
          <?= icon('folder', 'w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600') ?>
          Belum ada kategori. Tambahkan lewat form di samping.
        </div>
      <?php else: ?>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400">
            <tr>
              <th class="text-left font-medium px-4 py-2.5">Nama</th>
              <th class="text-left font-medium px-4 py-2.5 hidden sm:table-cell">Slug</th>
              <th class="text-center font-medium px-4 py-2.5">Artikel</th>
              <th class="text-right font-medium px-4 py-2.5">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <?php foreach ($cats as $c): ?>
            <tr>
              <td class="px-4 py-2.5">
                <div class="font-medium"><?= e($c['name']) ?></div>
                <?php if ($c['parent_name']): ?><div class="text-xs text-gray-400">&#8627; <?= e($c['parent_name']) ?></div><?php endif; ?>
              </td>
              <td class="px-4 py-2.5 hidden sm:table-cell font-mono text-xs text-gray-500"><?= e($c['slug']) ?></td>
              <td class="px-4 py-2.5 text-center"><span class="inline-flex items-center justify-center min-w-[24px] px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-xs"><?= (int) $c['article_count'] ?></span></td>
              <td class="px-4 py-2.5">
                <div class="flex items-center justify-end gap-1">
                  <button type="button" onclick='editCat(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" title="Edit"><?= icon('pen', 'w-4 h-4') ?></button>
                  <form method="post" action="<?= e(url('/actions/admin/delete-category')) ?>" data-confirm="Hapus kategori &quot;<?= e($c['name']) ?>&quot;?" data-confirm-danger class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" title="Hapus"><?= icon('trash', 'w-4 h-4') ?></button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const catName = document.getElementById('catName');
const catSlug = document.getElementById('catSlug');
let slugTouched = false;
catSlug.addEventListener('input', () => slugTouched = true);
catName.addEventListener('input', () => { if (!slugTouched) catSlug.value = slugify(catName.value); });
function slugify(s) {
  return s.toString().toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-').replace(/-+/g, '-');
}
function editCat(c) {
  document.getElementById('catId').value = c.id;
  catName.value = c.name;
  catSlug.value = c.slug;
  document.getElementById('catParent').value = c.parent_id || '';
  document.getElementById('catDesc').value = c.description || '';
  document.querySelector('[data-article-cta]').dispatchEvent(new CustomEvent('cta:load', { detail: c.article_cta }));
  document.getElementById('formTitle').textContent = 'Edit Kategori';
  slugTouched = true;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetCatForm() {
  document.getElementById('catForm').reset();
  document.getElementById('catId').value = '';
  document.getElementById('formTitle').textContent = 'Tambah Kategori';
  slugTouched = false;
}
</script>
<script src="<?= e(url('/assets/js/article-cta.js')) ?>" defer></script>
<?php admin_shell_bottom(); ?>
