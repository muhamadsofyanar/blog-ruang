<?php
// Listing artikel (staff). Writer hanya melihat miliknya. Query TANPA kolom
// content/draft_content (aturan query terkunci section 6.2). Filter + search + paginate.
require_once __DIR__ . '/_shell.php';
$pdo = getDB();

$isWriter = isWriter();
$perPage  = 15;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$fStatus = trim($_GET['status'] ?? '');
$fCat    = (int) ($_GET['category'] ?? 0);
$fAuthor = (int) ($_GET['author'] ?? 0);
$q       = trim($_GET['q'] ?? '');

// Bangun WHERE dinamis (prepared).
$where = [];
$args  = [];
if ($isWriter) { $where[] = 'a.author_id = ?'; $args[] = currentUserId(); }
if ($fStatus !== '' && in_array($fStatus, ['draft', 'draft_ai', 'scheduled', 'published'], true)) {
    $where[] = 'a.status = ?'; $args[] = $fStatus;
}
if ($fCat > 0)    { $where[] = 'a.category_id = ?'; $args[] = $fCat; }
if (!$isWriter && $fAuthor > 0) { $where[] = 'a.author_id = ?'; $args[] = $fAuthor; }
if ($q !== '')    { $where[] = 'a.title LIKE ?'; $args[] = '%' . $q . '%'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Total untuk pagination.
try {
    $cst = $pdo->prepare("SELECT COUNT(*) FROM articles a $whereSql");
    $cst->execute($args);
    $total = (int) $cst->fetchColumn();
} catch (Throwable $e) { $total = 0; }
$totalPages = max(1, (int) ceil($total / $perPage));

// Listing — NO content/draft_content.
try {
    $sql = "SELECT a.id, a.title, a.slug, a.status, a.published_at, a.updated_at, a.is_pinned,
                   c.name AS category_name, u.name AS author_name
            FROM articles a
            LEFT JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            $whereSql
            ORDER BY a.updated_at DESC
            LIMIT $perPage OFFSET $offset";
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();
} catch (Throwable $e) { $rows = []; error_log('articles listing: ' . $e->getMessage()); }

// Data filter dropdown.
try { $cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(); } catch (Throwable $e) { $cats = []; }
$authors = [];
if (!$isWriter) {
    try { $authors = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll(); } catch (Throwable $e) { $authors = []; }
}

$statusBadge = [
    'draft'     => ['Draft', 'gray'],
    'draft_ai'  => ['Draft AI', 'violet'],
    'scheduled' => ['Terjadwal', 'amber'],
    'published' => ['Published', 'emerald'],
];

// Helper URL filter (pertahankan param lain).
function articlesFilterUrl(array $override): string {
    $params = array_merge(['status' => $_GET['status'] ?? '', 'category' => $_GET['category'] ?? '',
        'author' => $_GET['author'] ?? '', 'q' => $_GET['q'] ?? '', 'page' => $_GET['page'] ?? 1], $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== '0');
    return url('/admin/articles') . ($params ? '?' . http_build_query($params) : '');
}

admin_shell_top('Artikel', '/admin/articles');
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
  <form method="get" action="<?= e(url('/admin/articles')) ?>" class="flex flex-wrap items-center gap-2">
    <div class="relative">
      <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"><?= icon('search', 'w-4 h-4') ?></span>
      <input name="q" value="<?= e($q) ?>" placeholder="Cari judul…" class="pl-8 pr-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm w-44">
    </div>
    <select name="status" class="py-2 px-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
      <option value="">Semua status</option>
      <?php foreach ($statusBadge as $k => $v): ?>
        <option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="category" class="py-2 px-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
      <option value="">Semua kategori</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $fCat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (!$isWriter): ?>
    <select name="author" class="py-2 px-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
      <option value="">Semua penulis</option>
      <?php foreach ($authors as $au): ?>
        <option value="<?= (int) $au['id'] ?>" <?= $fAuthor === (int) $au['id'] ? 'selected' : '' ?>><?= e($au['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button type="submit" class="py-2 px-3 rounded-md border border-gray-300 dark:border-gray-700 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">Filter</button>
  </form>
  <a href="<?= e(url('/admin/articles/new')) ?>" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">
    <?= icon('plus', 'w-4 h-4') ?> Artikel Baru
  </a>
</div>

<div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
  <?php if (!$rows): ?>
    <div class="px-4 py-14 text-center text-sm text-gray-500 dark:text-gray-400">
      <?= icon('file-text', 'w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600') ?>
      <?= $q !== '' || $fStatus !== '' || $fCat ? 'Tidak ada artikel yang cocok dengan filter.' : 'Belum ada artikel. Buat artikel pertama Anda.' ?>
    </div>
  <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400">
        <tr>
          <th class="text-left font-medium px-4 py-2.5">Judul</th>
          <th class="text-left font-medium px-4 py-2.5 hidden md:table-cell">Kategori</th>
          <?php if (!$isWriter): ?><th class="text-left font-medium px-4 py-2.5 hidden lg:table-cell">Penulis</th><?php endif; ?>
          <th class="text-left font-medium px-4 py-2.5">Status</th>
          <th class="text-left font-medium px-4 py-2.5 hidden sm:table-cell">Diperbarui</th>
          <th class="text-right font-medium px-4 py-2.5">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        <?php foreach ($rows as $a): ?>
        <tr>
          <td class="px-4 py-2.5">
            <div class="flex items-center gap-1.5">
              <?php if (!empty($a['is_pinned'])): ?><span class="text-accent" title="Featured beranda" style="color:var(--accent)"><?= icon('pin', 'w-3.5 h-3.5') ?></span><?php endif; ?>
              <a href="<?= e(url('/admin/articles/' . $a['id'])) ?>" class="font-medium hover:underline"><?= e($a['title']) ?></a>
            </div>
            <div class="text-xs text-gray-400 font-mono truncate max-w-[220px]"><?= e($a['slug']) ?></div>
          </td>
          <td class="px-4 py-2.5 hidden md:table-cell text-gray-500"><?= e($a['category_name'] ?? '—') ?></td>
          <?php if (!$isWriter): ?><td class="px-4 py-2.5 hidden lg:table-cell text-gray-500"><?= e($a['author_name'] ?? '—') ?></td><?php endif; ?>
          <td class="px-4 py-2.5"><?= articleStatusBadge($a['status']) ?></td>
          <td class="px-4 py-2.5 hidden sm:table-cell text-xs text-gray-500"><?= e(formatTanggal($a['updated_at'])) ?></td>
          <td class="px-4 py-2.5">
            <div class="flex items-center justify-end gap-1">
              <?php if (!$isWriter): ?>
              <form method="post" action="<?= e(url('/actions/admin/toggle-pin')) ?>" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button type="submit" class="p-1.5 rounded-md <?= !empty($a['is_pinned']) ? 'text-white' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800' ?>" <?= !empty($a['is_pinned']) ? 'style="background:var(--accent)"' : '' ?> title="<?= !empty($a['is_pinned']) ? 'Lepas sematan' : 'Sematkan sebagai featured' ?>"><?= icon('pin', 'w-4 h-4') ?></button>
              </form>
              <?php endif; ?>
              <a href="<?= e(url('/admin/articles/' . $a['id'] . '/preview')) ?>" target="_blank" rel="noopener" class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" title="Pratinjau"><?= icon('eye', 'w-4 h-4') ?></a>
              <a href="<?= e(url('/admin/articles/' . $a['id'])) ?>" class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" title="Edit"><?= icon('pen', 'w-4 h-4') ?></a>
              <form method="post" action="<?= e(url('/actions/admin/delete-article')) ?>" data-confirm="Hapus artikel ini?" data-confirm-danger class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
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

<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-between mt-4 text-sm">
  <span class="text-gray-500">Halaman <?= $page ?> dari <?= $totalPages ?> · <?= $total ?> artikel</span>
  <div class="flex gap-1">
    <?php if ($page > 1): ?><a href="<?= e(articlesFilterUrl(['page' => $page - 1])) ?>" class="px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Sebelumnya</a><?php endif; ?>
    <?php if ($page < $totalPages): ?><a href="<?= e(articlesFilterUrl(['page' => $page + 1])) ?>" class="px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Berikutnya</a><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php admin_shell_bottom(); ?>
