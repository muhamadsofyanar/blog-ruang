<?php
$req = scribe_required_checks();
$rec = scribe_recommended_checks();
$allPass = scribe_required_all_pass();
?>
<h2 class="text-lg font-semibold mb-1">Kebutuhan Server</h2>
<p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Pastikan semua kebutuhan wajib terpenuhi sebelum lanjut.</p>

<ul class="space-y-1.5 mb-4">
  <?php foreach ($req as $c): ?>
  <li class="flex items-center justify-between text-sm py-1.5 px-2 rounded-md <?= $c['pass'] ? '' : 'bg-red-50 dark:bg-red-950/30' ?>">
    <span><?= e($c['label']) ?> <span class="text-gray-400 text-xs"><?= e($c['detail']) ?></span></span>
    <span class="<?= $c['pass'] ? 'text-emerald-500' : 'text-red-500' ?> font-semibold text-xs"><?= $c['pass'] ? 'OK' : 'GAGAL' ?></span>
  </li>
  <?php endforeach; ?>
</ul>

<div class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Direkomendasikan</div>
<ul class="space-y-1.5 mb-5">
  <?php foreach ($rec as $c): ?>
  <li class="flex items-center justify-between text-sm py-1 px-2">
    <span><?= e($c['label']) ?> <span class="text-gray-400 text-xs"><?= e($c['detail']) ?></span></span>
    <span class="<?= $c['pass'] ? 'text-emerald-500' : 'text-amber-500' ?> text-xs font-semibold"><?= $c['pass'] ? 'OK' : 'Opsional' ?></span>
  </li>
  <?php endforeach; ?>
</ul>

<form method="post" action="?step=1">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="step" value="1">
  <button type="submit" <?= $allPass ? '' : 'disabled' ?>
    class="w-full rounded-md py-2 text-sm font-semibold text-white transition <?= $allPass ? 'hover:opacity-90' : 'opacity-40 cursor-not-allowed' ?>"
    style="background:var(--accent)">
    <?= $allPass ? 'Lanjut ke Database' : 'Penuhi kebutuhan wajib dulu' ?>
  </button>
</form>
