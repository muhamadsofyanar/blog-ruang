<?php
require_once __DIR__ . '/../../views/theme-default/layout.php';
require_once __DIR__ . '/../../helpers/lead-magnet.php';

$magnet = scribeLeadMagnetBySlug((string) ($_GET['slug'] ?? ''));
if (!$magnet) { http_response_code(404); require __DIR__ . '/../404.php'; return; }
$brand = feBrand();
$flash = getFlash('lead_magnet');
$submitted = isset($_GET['submitted']) || ($flash && ($flash['type'] ?? '') === 'success');
$pageAnchor = 'lead-magnet-page-' . (int) $magnet['id'];
$txt = scribeLeadMagnetSuccessText($magnet);
$dl  = scribeLeadMagnetUrl($magnet);
theme_head(['title' => $magnet['title'] . ' — ' . $brand['name'], 'description' => $magnet['description'] ?? '', 'active' => '']);
?>
<main id="<?= e($pageAnchor) ?>" class="max-w-xl mx-auto px-4 sm:px-6 py-12">
  <div class="fe-card rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 sm:p-8" data-lm-card>
    <?php if ($submitted): ?>
      <div class="text-center">
        <div class="mx-auto w-11 h-11 rounded-md flex items-center justify-center text-white mb-4" style="background:var(--accent)"><?= icon('check-circle', 'w-6 h-6') ?></div>
        <h1 class="font-display font-bold text-2xl"><?= e($txt['title']) ?></h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?= e($txt['desc']) ?></p>
        <?php if ($dl !== ''): ?><a href="<?= e($dl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 mt-5 px-4 py-2.5 rounded-md text-white text-sm font-semibold" style="background:var(--accent)"><?= icon('download', 'w-4 h-4') ?> <?= e($txt['btn']) ?></a><?php endif; ?>
      </div>
    <?php else: ?>
      <p class="text-[11px] uppercase tracking-wider font-semibold text-accent mb-2">Materi Gratis</p>
      <h1 class="font-display font-bold text-2xl sm:text-3xl"><?= e($magnet['title']) ?></h1>
      <?php if (trim((string) ($magnet['description'] ?? '')) !== ''): ?><p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400"><?= e($magnet['description']) ?></p><?php endif; ?>
      <?php if ($flash): ?><div class="mt-4 rounded-md border px-3 py-2 text-sm <?= $flash['type'] === 'error' ? 'border-red-200 text-red-700 bg-red-50' : 'border-emerald-200 text-emerald-700 bg-emerald-50' ?>"><?= e($flash['message']) ?></div><?php endif; ?>
      <form method="post" action="<?= e(url('/actions/public/lead-magnet')) ?>" class="fe-lm-form mt-6 space-y-3">
        <?= csrfField() ?><input type="hidden" name="lead_magnet_id" value="<?= (int) $magnet['id'] ?>"><input type="hidden" name="return_path" value="<?= e('/lead-magnet/' . $magnet['slug']) ?>"><input type="hidden" name="return_anchor" value="<?= e($pageAnchor) ?>"><div class="hidden"><input name="website" tabindex="-1" autocomplete="off"></div>
        <label class="block text-sm font-medium">Nama<input name="name" required maxlength="120" class="mt-1 w-full px-3.5 py-2.5 rounded-md border border-gray-200 dark:border-gray-700 bg-transparent" placeholder="Nama Anda"></label>
        <label class="block text-sm font-medium">Email<input name="email" required type="email" maxlength="190" class="mt-1 w-full px-3.5 py-2.5 rounded-md border border-gray-200 dark:border-gray-700 bg-transparent" placeholder="email@anda.com"></label>
        <button class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-md text-sm font-semibold text-white" style="background:var(--accent)"><?= e($magnet['cta_label'] ?: 'Dapatkan Gratis') ?> <?= icon('arrow-right', 'w-4 h-4') ?></button>
      </form>
      <template data-lm-success>
        <div class="text-center">
          <div class="mx-auto w-11 h-11 rounded-md flex items-center justify-center text-white mb-4" style="background:var(--accent)"><?= icon('check-circle', 'w-6 h-6') ?></div>
          <h1 class="font-display font-bold text-2xl" data-lm-title></h1>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" data-lm-desc></p>
          <a data-lm-dl target="_blank" rel="noopener" class="hidden inline-flex items-center gap-2 mt-5 px-4 py-2.5 rounded-md text-white text-sm font-semibold" style="background:var(--accent)"><?= icon('download', 'w-4 h-4') ?> <span data-lm-dl-label></span></a>
        </div>
      </template>
    <?php endif; ?>
  </div>
</main>
<?php theme_footer(); ?>
