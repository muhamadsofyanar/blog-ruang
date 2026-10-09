<?php
// ════════════════════════════════════════════════════════════════════════
// Listing publik (theme-default, B-01) — kategori / tag / hasil pencarian.
// Layout listing sama seperti home TANPA featured. Mode ditentukan dari param
// router: $_GET['cat'] (slug kategori), $_GET['tag'] (slug tag), atau /search?q=.
// Slug tak ditemukan → 404 publik bertema.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../views/theme-default/layout.php';
require_once __DIR__ . '/../../helpers/schedule.php';
require_once __DIR__ . '/../../helpers/page-cache.php';

scribeLazyPublish();

$brand   = feBrand();
$perPage = 9;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$mode    = 'search';
$opts    = [];
$heading = '';
$sub     = '';
$activeCat = '';
$baseUrl = url('/search');
$extraQ  = [];

if (isset($_GET['cat'])) {
    $mode = 'category';
    $slug = slugify((string) $_GET['cat']);
    $cat  = _feFindBySlug('categories', $slug);
    if (!$cat) { _fe404(); return; }
    $opts['category_id'] = (int) $cat['id'];
    $heading   = $cat['name'];
    $sub       = trim((string) ($cat['description'] ?? ''));
    $activeCat = $cat['slug'];
    $baseUrl   = feCategoryUrl($cat['slug']);
} elseif (isset($_GET['tag'])) {
    $mode = 'tag';
    $slug = slugify((string) $_GET['tag']);
    $tag  = _feFindBySlug('tags', $slug);
    if (!$tag) { _fe404(); return; }
    $opts['tag_id'] = (int) $tag['id'];
    $heading = '#' . $tag['name'];
    $baseUrl = feTagUrl($tag['slug']);
} else {
    $mode = 'search';
    $q    = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '') { $opts['search'] = $q; $extraQ['q'] = $q; }
    $heading = 'Pencarian';
    $sub     = $q !== '' ? 'Hasil untuk "' . $q . '"' : 'Ketik kata kunci untuk mencari artikel.';
}

// Cache: kategori/tag page 1 saja (search TIDAK di-cache). HIT → exit di sini.
$cacheable  = ($page === 1 && ($mode === 'category' || $mode === 'tag'));
$cacheKey   = $mode === 'category' ? '/kategori/' . $activeCat : ($mode === 'tag' ? '/tag/' . $tag['slug'] : '');
$cacheState = pageCacheServe($cacheKey, $cacheable);
pageCacheBegin($cacheKey, $cacheState);

$total   = ($mode === 'search' && empty($opts['search'])) ? 0 : feCountArticles($opts);
$totalPg = max(1, (int) ceil($total / $perPage));
if ($page > $totalPg) $page = $totalPg;

$articles = [];
if ($total > 0) {
    $opts['limit']  = $perPage;
    $opts['offset'] = ($page - 1) * $perPage;
    $articles = fePublishedArticles($opts);
}

$titleBits = [$heading, $brand['name']];
theme_head([
    'title'       => implode(' — ', array_filter($titleBits)),
    'description' => $sub !== '' ? $sub : $brand['tagline'],
    'active'      => $activeCat,
    'q'           => $mode === 'search' ? ($q ?? '') : '',
]);
?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 pt-12 pb-8">
  <p class="text-sm font-semibold text-accent mb-1">
    <?= $mode === 'category' ? 'Kategori' : ($mode === 'tag' ? 'Tag' : 'Cari') ?>
  </p>
  <h1 class="font-display font-bold text-3xl sm:text-4xl"><?= e($heading) ?></h1>
  <?php if ($sub !== ''): ?><p class="mt-3 text-gray-500 dark:text-gray-400"><?= e($sub) ?></p><?php endif; ?>
  <?php if ($total > 0): ?><p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?= (int) $total ?> artikel</p><?php endif; ?>
</section>

<?php if ($mode === 'search'): ?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 pb-8">
  <form method="get" action="<?= e(url('/search')) ?>" role="search" class="max-w-xl">
    <div class="relative">
      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><?= icon('search', 'w-5 h-5') ?></span>
      <input name="q" type="search" autofocus value="<?= e($q ?? '') ?>" placeholder="Cari artikel…"
             class="w-full pl-10 pr-4 py-2.5 rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-accent/40">
    </div>
  </form>
</div>
<?php endif; ?>

<section class="max-w-6xl mx-auto px-4 sm:px-6 pb-4">
  <?php if ($articles): ?>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($articles as $a) theme_article_card($a); ?>
    </div>
    <?php theme_pagination($page, $totalPg, $baseUrl, $extraQ); ?>
  <?php else: ?>
    <div class="py-16 text-center text-gray-500 dark:text-gray-400">
      <p class="text-lg"><?= $mode === 'search' && empty($opts['search']) ? 'Masukkan kata kunci pencarian.' : 'Tidak ada artikel yang cocok.' ?></p>
      <a href="<?= e(url('/')) ?>" class="inline-block mt-4 text-accent font-medium">Kembali ke beranda</a>
    </div>
  <?php endif; ?>
</section>

<?php theme_footer(); ?>
<?php pageCacheClose(); ?>
<?php
// ─── Util lokal listing ───────────────────────────────────────────────────
function _feFindBySlug(string $table, string $slug): ?array
{
    $allowed = ['categories' => 'id, name, slug, description', 'tags' => 'id, name, slug'];
    if (!isset($allowed[$table])) return null;
    try {
        $stmt = getDB()->prepare("SELECT {$allowed[$table]} FROM `$table` WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('_feFindBySlug: ' . $e->getMessage());
        return null;
    }
}

function _fe404(): void
{
    http_response_code(404);
    require __DIR__ . '/../404.php';
}
