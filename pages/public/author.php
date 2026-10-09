<?php
// ════════════════════════════════════════════════════════════════════════
// Halaman penulis publik (E-E-A-T) — /penulis/{slug}. Profil penulis (avatar,
// jabatan, bio, tautan sosial) + daftar artikel published-nya + JSON-LD Person.
// Slug tak cocok / penulis nonaktif → 404 bertema.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../views/theme-default/layout.php';
require_once __DIR__ . '/../../helpers/schedule.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
require_once __DIR__ . '/../../helpers/author.php';

scribeLazyPublish();

$brand = feBrand();
$pdo   = getDB();
$slug  = slugify((string) ($_GET['slug'] ?? ''));

$authorId = authorIdBySlug($pdo, $slug);
$author   = $authorId > 0 ? authorPublic($pdo, $authorId) : null;
if (!$author) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    return;
}

$perPage = 9;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$cacheKey   = '/penulis/' . $author['slug'];
$cacheState = pageCacheServe($cacheKey, $page === 1);
pageCacheBegin($cacheKey, $cacheState);

$opts    = ['author_id' => $author['id']];
$total   = feCountArticles($opts);
$totalPg = max(1, (int) ceil($total / $perPage));
if ($page > $totalPg) $page = $totalPg;

$articles = [];
if ($total > 0) {
    $opts['limit']  = $perPage;
    $opts['offset'] = ($page - 1) * $perPage;
    $articles = fePublishedArticles($opts);
}

$metaDesc = $author['bio'] !== '' ? $author['bio']
    : ('Artikel oleh ' . $author['name'] . ($brand['name'] !== '' ? ' di ' . $brand['name'] : '') . '.');

theme_head([
    'title'       => $author['name'] . ($author['job_title'] !== '' ? ' — ' . $author['job_title'] : '') . ' — ' . $brand['name'],
    'description' => $metaDesc,
    'active'      => '',
    'head_extra'  => seoPersonJsonLd($author),
]);

// Inisial untuk avatar fallback.
$initials = '';
foreach (preg_split('/\s+/', trim($author['name'])) as $w) { if ($w !== '') { $initials .= mb_strtoupper(mb_substr($w, 0, 1)); } }
$initials = mb_substr($initials, 0, 2) ?: '?';
?>
<section class="max-w-4xl mx-auto px-4 sm:px-6 pt-12 pb-6">
  <div class="flex flex-col sm:flex-row sm:items-center gap-5">
    <?php if ($author['avatar_url'] !== ''): ?>
      <img src="<?= e($author['avatar_url']) ?>" alt="<?= e($author['name']) ?>" width="96" height="96" class="w-24 h-24 rounded-full object-cover border border-gray-200 dark:border-gray-800 shrink-0">
    <?php else: ?>
      <div class="w-24 h-24 rounded-full flex items-center justify-center text-2xl font-display font-bold text-white shrink-0" style="background:var(--accent)"><?= e($initials) ?></div>
    <?php endif; ?>
    <div>
      <p class="text-sm font-semibold text-accent mb-1">Penulis</p>
      <h1 class="font-display font-bold text-3xl sm:text-4xl"><?= e($author['name']) ?></h1>
      <?php if ($author['job_title'] !== ''): ?><p class="mt-1 text-gray-500 dark:text-gray-400"><?= e($author['job_title']) ?></p><?php endif; ?>
      <?php if ($author['social']): ?>
      <div class="mt-3 flex flex-wrap gap-2">
        <?php foreach ($author['social'] as $su): $host = preg_replace('/^www\./', '', (string) parse_url($su, PHP_URL_HOST)); ?>
          <a href="<?= e($su) ?>" target="_blank" rel="noopener nofollow me" class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-md border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:border-gray-300"><?= icon('link', 'w-3.5 h-3.5') ?><?= e($host ?: 'tautan') ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($author['bio'] !== ''): ?>
  <p class="mt-5 text-gray-600 dark:text-gray-300 leading-relaxed max-w-2xl"><?= e($author['bio']) ?></p>
  <?php endif; ?>
</section>

<section class="max-w-6xl mx-auto px-4 sm:px-6 pb-4">
  <h2 class="font-display font-semibold text-lg sm:text-xl mb-4"><?= $total > 0 ? ('Artikel (' . (int) $total . ')') : 'Artikel' ?></h2>
  <?php if ($articles): ?>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($articles as $a) theme_article_card($a); ?>
    </div>
    <?php theme_pagination($page, $totalPg, url('/penulis/' . $author['slug'])); ?>
  <?php else: ?>
    <div class="py-12 text-center text-gray-500 dark:text-gray-400"><p>Belum ada artikel dari penulis ini.</p></div>
  <?php endif; ?>
</section>

<?php theme_footer(); ?>
<?php pageCacheClose(); ?>
