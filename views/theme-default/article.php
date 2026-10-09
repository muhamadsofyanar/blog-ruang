<?php
// ════════════════════════════════════════════════════════════════════════
// View halaman artikel (theme-default / B-03). Dipanggil pages/public/article.php
// dengan: $article, $rendered{html,toc,minutes}, $faqs, $related, $nav{prev,next},
// $ctx (SEO), $tags. Bahasa visual meneruskan DNA B-02.5.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../helpers/article-cta.php';
require_once __DIR__ . '/../../helpers/lead-magnet.php';
$articleCta = articleCtaResolve($article);
$brand    = feBrand();
$toc      = $rendered['toc'] ?? [];
$showToc  = count($toc) >= 3;
$shareUrl = (string) ($ctx['canonical'] ?? url('/artikel/' . rawurlencode((string) $article['slug'])));
$shareTt  = (string) $article['title'];
$pubDate  = formatTanggal($article['published_at'] ?? null, false);
$updRaw   = $article['updated_at'] ?? null;
$showUpd  = $updRaw && !empty($article['published_at'])
    && date('Y-m-d', strtotime((string) $updRaw)) !== date('Y-m-d', strtotime((string) $article['published_at']));

// SVG share (inline, tanpa SDK / emoji).
$svgWa = '<svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M17.5 14.4c-.3-.2-1.7-.9-2-1-.3-.1-.5-.1-.6.1-.2.3-.6 1-.8 1.1-.1.2-.3.2-.5.1-.8-.3-1.5-.7-2.2-1.5-.5-.6-.9-1.3-1-1.5-.1-.3 0-.4.1-.5l.4-.5c.1-.1.1-.3.2-.4 0-.2 0-.3 0-.4 0-.1-.6-1.5-.9-2-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.7.7-.9 1.6-.7 2.6.4 1.6 1.4 2.9 1.6 3.1.2.2 2.6 4 6.3 5.4.9.3 1.5.5 2.1.4.6-.1 1.7-.7 2-1.4.2-.6.2-1.2.2-1.3-.1-.1-.3-.2-.5-.3zM12 2C6.5 2 2 6.5 2 12c0 1.8.5 3.4 1.3 4.9L2 22l5.3-1.4c1.4.8 3 1.2 4.7 1.2 5.5 0 10-4.5 10-10S17.5 2 12 2z"/></svg>';
$svgFb = '<svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z"/></svg>';
$svgX  = '<svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M18.9 2H22l-7.4 8.5L23.3 22h-6.8l-5.3-7-6.1 7H2l7.9-9.1L1.7 2h7l4.8 6.3L18.9 2zm-1.2 18h1.9L7.4 4H5.4l12.3 16z"/></svg>';

theme_head($ctx);
?>
<div class="max-w-3xl mx-auto px-4 sm:px-6 pt-8">
  <!-- Breadcrumb -->
  <nav class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1.5 flex-wrap" aria-label="Breadcrumb">
    <a href="<?= e(url('/')) ?>" class="hover:text-accent">Beranda</a>
    <?php if (!empty($article['cat_name'])): ?>
      <span aria-hidden="true">/</span>
      <a href="<?= e(feCategoryUrl($article['cat_slug'])) ?>" class="hover:text-accent"><?= e($article['cat_name']) ?></a>
    <?php endif; ?>
    <span aria-hidden="true">/</span>
    <span class="text-gray-700 dark:text-gray-300 truncate max-w-[60vw]"><?= e($article['title']) ?></span>
  </nav>
</div>

<header class="max-w-3xl mx-auto px-4 sm:px-6 pt-6">
  <?php if (!empty($article['cat_name'])): ?>
  <a href="<?= e(feCategoryUrl($article['cat_slug'])) ?>" class="fe-badge inline-block text-[11px] font-semibold px-2.5 py-1 rounded-md mb-3"><?= e($article['cat_name']) ?></a>
  <?php endif; ?>
  <h1 class="font-display font-bold text-3xl sm:text-4xl lg:text-[2.75rem] leading-tight"><?= e($article['title']) ?></h1>
  <div class="mt-4 flex items-center flex-wrap gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
    <?php if (!empty($article['author_name'])): ?><a href="<?= e(url('/penulis/' . slugify((string) $article['author_name']))) ?>" class="font-medium text-gray-700 dark:text-gray-300 hover:text-accent hover:underline"><?= e($article['author_name']) ?></a><span aria-hidden="true">·</span><?php endif; ?>
    <time datetime="<?= e($article['published_at'] ?? '') ?>"><?= e($pubDate) ?></time>
    <?php if ($showUpd): ?><span aria-hidden="true">·</span><span>diperbarui <?= e(formatTanggal($updRaw, false)) ?></span><?php endif; ?>
    <span aria-hidden="true">·</span>
    <span><?= (int) $rendered['minutes'] ?> menit baca</span>
  </div>
</header>

<!-- Cover — lebar sejajar area konten (max-w-3xl), proporsi lebih rendah & elegan -->
<div class="max-w-3xl mx-auto px-4 sm:px-6 mt-6 sm:mt-8">
  <div class="aspect-[16/9] sm:aspect-[21/9] overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800 shadow-sm ring-1 ring-black/5 dark:ring-white/10">
    <?= feCoverHtml($article, 'w-full h-full object-cover', '(min-width:768px) 720px, 100vw', true) ?>
  </div>
</div>

<div class="relative max-w-3xl mx-auto px-4 sm:px-6 mt-10">
  <?php if ($showToc): ?>
  <!-- Daftar Isi — mobile & tablet (collapsible, rapi) -->
  <details class="xl:hidden group mb-8 rounded-lg border border-gray-200 dark:border-gray-800 bg-white/70 dark:bg-gray-900/60">
    <summary class="flex items-center justify-between gap-2 px-4 py-3 font-display font-semibold text-sm cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
      <span>Daftar Isi</span>
      <?= icon('chevron-down', 'w-4 h-4 text-gray-400 transition-transform group-open:rotate-180') ?>
    </summary>
    <nav class="px-3 pb-3 pt-0.5">
      <?php foreach ($toc as $t): ?>
      <a href="#<?= e($t['id']) ?>" class="toc-link lvl-<?= (int) $t['level'] ?>"><?= e($t['text']) ?></a>
      <?php endforeach; ?>
    </nav>
  </details>

  <!-- Daftar Isi — desktop (xl+): sticky di margin kanan, tidak menggeser konten -->
  <aside class="hidden xl:block absolute top-0 left-full ml-8 w-52 h-full" aria-label="Daftar isi">
    <div class="toc-box sticky top-24 rounded-lg bg-white/70 dark:bg-gray-900/60 backdrop-blur p-4">
      <p class="font-display font-semibold text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Daftar Isi</p>
      <nav id="tocDesktop">
        <?php foreach ($toc as $t): ?>
        <a href="#<?= e($t['id']) ?>" class="toc-link lvl-<?= (int) $t['level'] ?>"><?= e($t['text']) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>
  <?php endif; ?>

  <!-- Konten -->
  <article class="min-w-0">
    <?php // Konten SUDAH melewati sanitizeArticleHtml (scribeRenderArticle) — output aman. ?>
    <div class="prose-art"><?= $rendered['html'] ?></div>
    <?php articleCtaRender($articleCta); ?>

    <?php scribeLeadMagnetRender($article); ?>

    <!-- Share -->
    <div class="mt-10 flex items-center gap-3 border-t border-gray-200 dark:border-gray-800 pt-6">
      <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Bagikan:</span>
      <a class="share-btn" target="_blank" rel="noopener nofollow" aria-label="Bagikan ke WhatsApp"
         href="https://wa.me/?text=<?= rawurlencode($shareTt . ' ' . $shareUrl) ?>"><?= $svgWa ?></a>
      <a class="share-btn" target="_blank" rel="noopener nofollow" aria-label="Bagikan ke Facebook"
         href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>"><?= $svgFb ?></a>
      <a class="share-btn" target="_blank" rel="noopener nofollow" aria-label="Bagikan ke X"
         href="https://twitter.com/intent/tweet?url=<?= rawurlencode($shareUrl) ?>&text=<?= rawurlencode($shareTt) ?>"><?= $svgX ?></a>
    </div>

    <!-- Tags -->
    <?php if ($tags): ?>
    <div class="mt-6 flex items-center flex-wrap gap-2">
      <?php foreach ($tags as $tg): ?>
      <a href="<?= e(feTagUrl($tg['slug'])) ?>" class="fe-pill px-3 py-1 rounded-md text-xs font-medium text-gray-700 dark:text-gray-200">#<?= e($tg['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Prev / Next -->
    <?php if (!empty($nav['prev']) || !empty($nav['next'])): ?>
    <nav class="mt-10 grid sm:grid-cols-2 gap-3" aria-label="Artikel lain">
      <?php if (!empty($nav['prev'])): ?>
      <a href="<?= e(feArticleUrl($nav['prev']['slug'])) ?>" class="fe-card block rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
        <span class="text-xs text-gray-500 dark:text-gray-400">← Sebelumnya</span>
        <span class="block font-display font-semibold mt-1 line-clamp-2 hover:text-accent"><?= e($nav['prev']['title']) ?></span>
      </a>
      <?php else: ?><span class="hidden sm:block"></span><?php endif; ?>
      <?php if (!empty($nav['next'])): ?>
      <a href="<?= e(feArticleUrl($nav['next']['slug'])) ?>" class="fe-card block rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 sm:text-right">
        <span class="text-xs text-gray-500 dark:text-gray-400">Selanjutnya →</span>
        <span class="block font-display font-semibold mt-1 line-clamp-2 hover:text-accent"><?= e($nav['next']['title']) ?></span>
      </a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>
  </article>
</div>

<?php if ($showToc): ?>
<!-- Scrollspy Daftar Isi (progresif — sorot bagian yang sedang dibaca) -->
<script>
(function () {
  var links = document.querySelectorAll('#tocDesktop .toc-link');
  if (!links.length || !('IntersectionObserver' in window)) return;
  var map = {};
  links.forEach(function (a) {
    var id = decodeURIComponent((a.getAttribute('href') || '').slice(1));
    if (id) { var el = document.getElementById(id); if (el) map[id] = a; }
  });
  var current = null;
  var obs = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) {
        if (current) current.classList.remove('active');
        current = map[en.target.id];
        if (current) current.classList.add('active');
      }
    });
  }, { rootMargin: '-88px 0px -70% 0px', threshold: 0 });
  Object.keys(map).forEach(function (id) { var el = document.getElementById(id); if (el) obs.observe(el); });
})();
</script>
<?php endif; ?>

<!-- Related -->
<?php if ($related): ?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 mt-16">
  <h2 class="font-display font-bold text-2xl mb-6">Artikel Terkait</h2>
  <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($related as $r) theme_article_card($r); ?>
  </div>
</section>
<?php endif; ?>

<?php if ($articleCta['mode'] === 'legacy') theme_cta_band('article', (int) $article['id']); ?>
<?php theme_footer(); ?>
