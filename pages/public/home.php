<?php
// ════════════════════════════════════════════════════════════════════════
// Home publik (theme-default). Mode homepage (setting home_mode):
//   blog    → hero + featured + grid artikel (default, perilaku lama)
//   biolink → header profil biolink + blok tautan (tanpa artikel)
//   hybrid  → header biolink + blok, LALU grid artikel terbaru di bawah
// Query listing TANPA kolom content (helpers/frontend.php).
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../views/theme-default/layout.php';
require_once __DIR__ . '/../../helpers/schedule.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
require_once __DIR__ . '/../../helpers/biolink.php';
require_once __DIR__ . '/../../helpers/email-sequence.php';
require_once __DIR__ . '/../../helpers/seo-head.php';

scribeLazyPublish();
// Worker ringan berbasis traffic: antrean Mailketing dan email sequence yang
// sudah jatuh tempo diproses SETELAH respons dikirim (non-blokir), maksimal
// sekali per menit, agar halaman tidak menunggu panggilan provider.
scribeKickEmailAutomation();

$brand     = feBrand();
$blogRoute = !empty($GLOBALS['scribe_blog_route']); // route /blog → paksa tampilan blog
$homeMode  = $blogRoute ? 'blog' : bioHomeMode();
$routeBase = $blogRoute ? '/blog' : '/';
$perPage   = 9;
$page      = max(1, (int) ($_GET['page'] ?? 1));

// Cache halaman (hanya page 1-2). HIT → exit di sini.
$cacheKey   = $routeBase . ($page > 1 ? '?page=' . $page : '');
$cacheState = pageCacheServe($cacheKey, $page <= 2);
pageCacheBegin($cacheKey, $cacheState);

$showArticles = ($homeMode !== 'biolink'); // biolink murni tak menampilkan artikel

// Featured hanya di mode blog (di hybrid, header biolink sudah jadi hero).
$featured = ($homeMode === 'blog') ? feFeaturedArticle() : null;
$featuredId = $featured ? (int) $featured['id'] : 0;

$articles = []; $total = 0; $totalPg = 1; $anyPublished = false;
if ($showArticles) {
    $gridOpts = $featuredId ? ['exclude_id' => $featuredId] : [];
    $total    = feCountArticles($gridOpts);
    $totalPg  = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPg) $page = $totalPg;
    $articles = fePublishedArticles($gridOpts + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage]);
    $anyPublished = $featured !== null || $total > 0;
}

$cats = feNavCategories();
$bio  = bioProfile();

theme_head([
    'title'       => ($homeMode !== 'blog' && trim($bio['name']) !== '' ? $bio['name'] : $brand['name'])
                     . ($brand['tagline'] !== '' ? ' — ' . $brand['tagline'] : ''),
    'description' => $homeMode !== 'blog' && trim($bio['bio']) !== '' ? $bio['bio'] : $brand['tagline'],
    'active'      => $blogRoute ? 'blog' : 'home',
    // Schema WebSite + Organization hanya di homepage kanonik '/', bukan /blog.
    'head_extra'  => $blogRoute ? '' : seoHomeJsonLd(),
]);
?>
<?php if ($homeMode === 'blog'): ?>

  <section class="relative overflow-hidden">
    <div aria-hidden="true" class="fe-dots pointer-events-none absolute inset-0"></div>
    <div class="fe-hero relative max-w-6xl mx-auto px-4 sm:px-6 pt-9 sm:pt-12 pb-6 text-center">
      <h1 class="font-display font-bold text-3xl sm:text-4xl lg:text-5xl leading-tight"><?= e($brand['name']) ?></h1>
      <?php if ($brand['tagline'] !== ''): ?>
      <p class="mt-3 text-base sm:text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto"><?= e($brand['tagline']) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($cats): ?>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 pb-7">
    <div class="flex flex-wrap justify-center gap-2">
      <span class="px-3.5 py-1.5 rounded-md text-sm font-semibold text-white" style="background:var(--accent);box-shadow:0 6px 16px -8px color-mix(in srgb, var(--accent) 70%, transparent)">Semua</span>
      <?php foreach ($cats as $c): ?>
      <a href="<?= e(feCategoryUrl($c['slug'])) ?>" class="fe-pill px-3.5 py-1.5 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$anyPublished): ?>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-20 text-center text-gray-500 dark:text-gray-400"><p class="text-lg">Belum ada artikel yang dipublikasikan.</p></div>
  <?php else: ?>
    <?php if ($featured && $page === 1): ?>
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-8"><?php theme_featured($featured); ?></section>
    <?php endif; ?>
    <?php if ($articles): ?>
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-4">
      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($articles as $a) theme_article_card($a); ?></div>
      <?php theme_pagination($page, $totalPg, url($routeBase)); ?>
    </section>
    <?php endif; ?>
  <?php endif; ?>

<?php else: ?>

  <!-- Mode biolink / hybrid: header profil + blok tautan -->
  <?php bioRenderHeader($bio); ?>
  <?php bioRenderIntroduction($bio); ?>
  <?php bioRenderBlocks(bioBlocks(true)); ?>

  <?php if ($homeMode === 'hybrid' && $anyPublished): ?>
  <!-- Feed compact (khusus hybrid): tidak sedominan homepage blog; selengkapnya di /blog -->
  <section class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 pb-4">
    <div class="flex items-center justify-between gap-3 mb-4">
      <h2 class="font-display font-semibold text-lg sm:text-xl">Artikel Terbaru</h2>
      <a href="<?= e(url('/blog')) ?>" class="fe-accent-text inline-flex items-center gap-1 text-sm font-semibold hover:opacity-80">Lihat Semua Artikel <?= icon('chevron-down', 'w-4 h-4 -rotate-90') ?></a>
    </div>
    <?php if ($articles): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      <?php foreach (array_slice($articles, 0, 6) as $a) theme_article_card_compact($a); ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

<?php endif; ?>

<?php theme_cta_band('home'); ?>
<?php theme_footer(); ?>
<?php pageCacheClose(); ?>
