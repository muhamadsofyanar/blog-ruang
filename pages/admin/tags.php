<?php
// Kelola tag (admin). CRUD ringan. Attach tag ke artikel dilakukan di editor.
require_once __DIR__ . '/_shell.php';
$pdo = getDB();
try {
    $tags = $pdo->query(
        "SELECT t.id, t.name, t.slug,
                (SELECT COUNT(*) FROM article_tags at WHERE at.tag_id = t.id) AS usage_count
         FROM tags t ORDER BY t.name ASC"
    )->fetchAll();
} catch (Throwable $e) { $tags = []; }

admin_shell_top('Tag', '/admin/tags');
?>
<div class="grid lg:grid-cols-3 gap-4">
  <div class="lg:col-span-1">
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 sticky top-20">
      <h2 id="tagFormTitle" class="font-display font-semibold text-sm mb-4">Tambah Tag</h2>
      <form method="post" action="<?= e(url('/actions/admin/save-tag')) ?>" class="space-y-3" id="tagForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="tagId" value="">
        <div>
          <label class="block text-xs font-medium mb-1">Nama</label>
          <input name="name" id="tagName" required class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Slug <span class="text-gray-400">(otomatis)</span></label>
          <input name="slug" id="tagSlug" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono">
        </div>
        <div class="flex gap-2 pt-1">
          <button type="submit" class="flex-1 rounded-md py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan</button>
          <button type="button" onclick="resetTagForm()" class="rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Reset</button>
        </div>
      </form>
    </div>
  </div>

  <div class="lg:col-span-2">
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
      <?php if (!$tags): ?>
        <div class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
          <?= icon('tag', 'w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600') ?>
          Belum ada tag. Tag juga bisa dibuat otomatis dari editor artikel.
        </div>
      <?php else: ?>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400">
            <tr>
              <th class="text-left font-medium px-4 py-2.5">Nama</th>
              <th class="text-left font-medium px-4 py-2.5 hidden sm:table-cell">Slug</th>
              <th class="text-center font-medium px-4 py-2.5">Dipakai</th>
              <th class="text-right font-medium px-4 py-2.5">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <?php foreach ($tags as $t): ?>
            <tr>
              <td class="px-4 py-2.5 font-medium"><?= e($t['name']) ?></td>
              <td class="px-4 py-2.5 hidden sm:table-cell font-mono text-xs text-gray-500"><?= e($t['slug']) ?></td>
              <td class="px-4 py-2.5 text-center"><span class="inline-flex items-center justify-center min-w-[24px] px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-xs"><?= (int) $t['usage_count'] ?></span></td>
              <td class="px-4 py-2.5">
                <div class="flex items-center justify-end gap-1">
                  <button type="button" onclick='editTag(<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" title="Edit"><?= icon('pen', 'w-4 h-4') ?></button>
                  <form method="post" action="<?= e(url('/actions/admin/delete-tag')) ?>" data-confirm="Hapus tag &quot;<?= e($t['name']) ?>&quot;?" data-confirm-danger class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
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
const tagName = document.getElementById('tagName');
const tagSlug = document.getElementById('tagSlug');
let tagSlugTouched = false;
tagSlug.addEventListener('input', () => tagSlugTouched = true);
tagName.addEventListener('input', () => { if (!tagSlugTouched) tagSlug.value = tagSlugify(tagName.value); });
function tagSlugify(s){return s.toString().toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g,'').trim().replace(/[\s_]+/g,'-').replace(/-+/g,'-');}
function editTag(t){document.getElementById('tagId').value=t.id;tagName.value=t.name;tagSlug.value=t.slug;document.getElementById('tagFormTitle').textContent='Edit Tag';tagSlugTouched=true;window.scrollTo({top:0,behavior:'smooth'});}
function resetTagForm(){document.getElementById('tagForm').reset();document.getElementById('tagId').value='';document.getElementById('tagFormTitle').textContent='Tambah Tag';tagSlugTouched=false;}
</script>
<?php admin_shell_bottom(); ?>
