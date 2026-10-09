<?php
// Lead magnet publik: satu form dapat dipasang di halaman artikel dan dibuka
// lewat URL /lead-magnet/{slug}. Pemilihan magnet aktif disimpan di settings.

function scribeLeadMagnetUrl(array $magnet): string
{
    $raw = trim((string) ($magnet['delivery_url'] ?? ''));
    if ($raw === '') return '';
    if (preg_match('#^https?://#i', $raw)) return $raw;
    return url('/' . ltrim($raw, '/'));
}

/**
 * Teks tampilan "sukses" Lead Magnet. Dapat diatur admin (settings grup
 * 'lead_magnet'); kosong/belum pernah diatur → default. Teks tombol default
 * mengikuti label tombol submit magnet (cta_label), lalu 'Unduh Materi'.
 */
function scribeLeadMagnetSuccessText(?array $magnet = null): array
{
    $title = trim((string) getSetting('lead_success_title', ''));
    $desc  = trim((string) getSetting('lead_success_desc', ''));
    $btn   = trim((string) getSetting('lead_success_btn', ''));
    $submitLabel = trim((string) ($magnet['cta_label'] ?? ''));
    return [
        'title' => $title !== '' ? $title : 'Pendaftaran berhasil',
        'desc'  => $desc !== '' ? $desc : 'Terima kasih. Materi Anda sudah siap diunduh.',
        'btn'   => $btn !== '' ? $btn : ($submitLabel !== '' ? $submitLabel : 'Unduh Materi'),
    ];
}

function scribeLeadMagnetById(int $id, bool $publishedOnly = true): ?array
{
    if ($id < 1) return null;
    try {
        $where = $publishedOnly ? " AND status = 'published'" : '';
        $st = getDB()->prepare("SELECT * FROM lead_magnets WHERE id = ?{$where} LIMIT 1");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { error_log('lead magnet by id: ' . $e->getMessage()); return null; }
}

function scribeLeadMagnetBySlug(string $slug, bool $publishedOnly = true): ?array
{
    $slug = slugify($slug);
    if ($slug === '') return null;
    try {
        $where = $publishedOnly ? " AND status = 'published'" : '';
        $st = getDB()->prepare("SELECT * FROM lead_magnets WHERE slug = ?{$where} LIMIT 1");
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { error_log('lead magnet by slug: ' . $e->getMessage()); return null; }
}

function scribeDefaultLeadMagnet(): ?array
{
    $id = (int) getSetting('lead_magnet_default_id', '0');
    if ($id > 0 && ($m = scribeLeadMagnetById($id))) return $m;
    try {
        return getDB()->query("SELECT * FROM lead_magnets WHERE status = 'published' ORDER BY id ASC LIMIT 1")->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

function scribeLeadMagnetForArticle(array $article): ?array
{
    return scribeDefaultLeadMagnet();
}

function scribeLeadMagnetRender(array $article): void
{
    $magnet = scribeLeadMagnetForArticle($article);
    if (!$magnet) return;
    $flash = getFlash('lead_magnet');
    $formId = 'lead-magnet-' . (int) ($article['id'] ?? 0);
    $action = url('/actions/public/lead-magnet');
    $sourceUrl = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $txt = scribeLeadMagnetSuccessText($magnet);
    $dl  = scribeLeadMagnetUrl($magnet);
    ?>
<section class="fe-lead-magnet w-full mt-8" aria-labelledby="<?= e($formId) ?>-title">
  <div class="relative overflow-hidden rounded-lg border border-gray-200/90 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 sm:p-5 shadow-sm">
    <div class="relative z-10" data-lm-card>
    <?php if ($flash): ?>
      <div class="text-center">
        <div class="mx-auto w-10 h-10 rounded-md flex items-center justify-center text-white mb-3" style="background:var(--accent)"><?= icon('check-circle', 'w-5 h-5') ?></div>
        <h2 id="<?= e($formId) ?>-title" class="font-display font-semibold text-xl"><?= e($flash['type'] === 'success' ? $txt['title'] : 'Form belum terkirim') ?></h2>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?= e($flash['type'] === 'success' ? $txt['desc'] : $flash['message']) ?></p>
        <?php if ($flash['type'] === 'success' && $dl !== ''): ?>
        <a href="<?= e($dl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 mt-4 px-4 py-2.5 rounded-md text-sm font-semibold text-white" style="background:var(--accent)"><?= icon('download', 'w-4 h-4') ?> <?= e($txt['btn']) ?></a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div>
        <p class="text-[11px] uppercase tracking-wider font-semibold text-accent mb-2">Gratis untuk pembaca</p>
        <h2 id="<?= e($formId) ?>-title" class="font-display font-semibold text-xl sm:text-2xl"><?= e($magnet['title']) ?></h2>
        <?php if (trim((string) ($magnet['description'] ?? '')) !== ''): ?><p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400"><?= e($magnet['description']) ?></p><?php endif; ?>
        <form method="post" action="<?= e($action) ?>" class="fe-lm-form mt-5 grid sm:grid-cols-2 gap-2">
          <?= csrfField() ?>
          <input type="hidden" name="lead_magnet_id" value="<?= (int) $magnet['id'] ?>">
          <input type="hidden" name="source_article_id" value="<?= (int) ($article['id'] ?? 0) ?>">
          <input type="hidden" name="source_url" value="<?= e($sourceUrl) ?>">
          <input type="hidden" name="return_path" value="<?= e($sourceUrl) ?>">
          <input type="hidden" name="return_anchor" value="<?= e($formId) ?>">
          <div class="hidden" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
          <label class="sr-only" for="<?= e($formId) ?>-name">Nama</label>
          <input id="<?= e($formId) ?>-name" name="name" type="text" maxlength="120" required placeholder="Nama Anda" class="px-3.5 py-2.5 rounded-md border border-gray-200 dark:border-gray-700 bg-transparent text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
          <label class="sr-only" for="<?= e($formId) ?>-email">Email</label>
          <input id="<?= e($formId) ?>-email" name="email" type="email" maxlength="190" required placeholder="email@anda.com" class="px-3.5 py-2.5 rounded-md border border-gray-200 dark:border-gray-700 bg-transparent text-sm focus:outline-none focus:ring-2 focus:ring-accent/30">
          <button type="submit" class="sm:col-span-2 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-md text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)"><?= e($magnet['cta_label'] ?: 'Dapatkan Gratis') ?> <?= icon('arrow-right', 'w-4 h-4') ?></button>
        </form>
      </div>
      <template data-lm-success>
        <div class="text-center">
          <div class="mx-auto w-10 h-10 rounded-md flex items-center justify-center text-white mb-3" style="background:var(--accent)"><?= icon('check-circle', 'w-5 h-5') ?></div>
          <h2 class="font-display font-semibold text-xl" data-lm-title></h2>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" data-lm-desc></p>
          <a data-lm-dl target="_blank" rel="noopener" class="hidden inline-flex items-center gap-2 mt-4 px-4 py-2.5 rounded-md text-sm font-semibold text-white" style="background:var(--accent)"><?= icon('download', 'w-4 h-4') ?> <span data-lm-dl-label></span></a>
        </div>
      </template>
    <?php endif; ?>
    </div>
  </div>
</section>
    <?php
}
