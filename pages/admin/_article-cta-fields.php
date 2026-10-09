<?php
// Variabel: $ctaValue, $ctaFallbacks (peta category_id => CTA terpilih), $ctaGlobal.
$ctaFieldClass = 'w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm';
?>
<div data-article-cta class="space-y-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
  <h2 class="font-display font-semibold text-sm">CTA Artikel</h2>
  <p class="text-xs text-gray-500">Prioritas: artikel → kategori → global. Pilih sembunyikan untuk menghentikan pewarisan CTA.</p>
  <label class="block text-xs font-medium">Pilihan CTA
    <select name="article_cta[mode]" data-cta-field="mode" class="<?= $ctaFieldClass ?> mt-1">
      <?php foreach (['inherit' => 'Ikuti pengaturan induk', 'custom' => 'CTA khusus', 'off' => 'Sembunyikan CTA'] as $value => $label): ?>
      <option value="<?= $value ?>" <?= $ctaValue['mode'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div data-cta-custom class="space-y-3" <?= $ctaValue['mode'] !== 'custom' ? 'hidden' : '' ?>>
    <?php foreach (['title' => ['Judul', 120], 'caption' => ['Caption (opsional)', 500], 'label' => ['Teks tombol', 60], 'url' => ['URL tombol (http/https)', 2000]] as $key => [$label, $max]): ?>
    <label class="block text-xs font-medium"><?= e($label) ?>
      <?php if ($key === 'caption'): ?>
      <textarea name="article_cta[caption]" data-cta-field="caption" rows="3" maxlength="500" class="<?= $ctaFieldClass ?> mt-1"><?= e($ctaValue[$key]) ?></textarea>
      <?php else: ?>
      <input name="article_cta[<?= $key ?>]" data-cta-field="<?= $key ?>" type="<?= $key === 'url' ? 'url' : 'text' ?>" maxlength="<?= $max ?>" value="<?= e($ctaValue[$key]) ?>" class="<?= $ctaFieldClass ?> mt-1">
      <?php endif; ?>
    </label>
    <?php endforeach; ?>
    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="article_cta[blank]" data-cta-field="blank" value="1" <?= $ctaValue['blank'] ? 'checked' : '' ?>> Buka di tab baru</label>
  </div>
  <div class="border-t border-gray-200 dark:border-gray-800 pt-3" aria-live="polite">
    <p class="text-xs text-gray-500 mb-2" data-cta-status>Preview CTA</p>
    <div data-cta-preview class="rounded-md border border-gray-200 dark:border-gray-700 p-3 break-words" hidden>
      <p data-cta-title class="font-display font-semibold text-sm"></p>
      <p data-cta-caption class="text-xs text-gray-500 dark:text-gray-400 mt-1 whitespace-pre-line"></p>
      <span data-cta-label class="inline-block rounded-md px-3 py-2 mt-3 text-xs font-semibold text-white" style="background:var(--accent)"></span>
    </div>
  </div>
  <p class="text-xs text-gray-400">Preview mengikuti perubahan form. Simpan untuk menerapkan CTA; pratinjau artikel di tab baru memakai CTA yang sudah tersimpan.</p>
  <script type="application/json" data-cta-config><?= json_encode(['fallbacks' => $ctaFallbacks ?? [], 'global' => $ctaGlobal ?? ['mode' => 'legacy']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</div>
