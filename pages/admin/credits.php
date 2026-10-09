<?php
// Halaman Kredit (admin): widget saldo (cache 5 menit) + beli kredit + riwayat pemakaian.
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/AverionAiAdapter.php';
require_once __DIR__ . '/../../helpers/ai-balance.php';

$adapter = new AverionAiAdapter();
$force   = isset($_GET['refresh']);
$bal     = $adapter->getBalanceCached($force);
$balData = $bal['ok'] ? $bal['data'] : null;
$cached  = $bal['cached'] ?? false;
$age     = (int) ($bal['age'] ?? 0);

// Storefront base diturunkan dari license server (lokal maupun produksi).
$storefront = preg_replace('#/api/?$#', '/storefront', LICENSE_SERVER_URL);
$licKey     = (string) getSetting('license_key', '');
$topupUrl   = fn(string $p) => $storefront . '?product=' . $p . '&license=' . rawurlencode($licKey);

// Riwayat pemakaian.
$page  = max(1, (int) ($_GET['upage'] ?? 1));
$usage = $adapter->getUsage($page, 15);
$rows  = $usage['ok'] ? ($usage['data']['rows'] ?? []) : [];
$totalPages = $usage['ok'] ? (int) ($usage['data']['total_pages'] ?? 1) : 1;

$statusBadge = ['success' => ['Sukses', 'emerald'], 'error' => ['Gagal', 'red'], 'rejected' => ['Ditolak', 'amber']];

admin_shell_top('Kredit AI', '/admin/credits');
?>
<!-- Widget saldo -->
<div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 mb-4">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h2 class="font-display font-semibold">Saldo Kredit AI</h2>
      <p class="text-xs text-gray-400 mt-0.5">
        <?php if (!$bal['ok']): ?>Saldo gagal dimuat — <?= e($bal['message']) ?>
        <?php elseif ($cached): ?>Data cache (<?= $age ?> dtk lalu)
        <?php else: ?>Baru diperbarui<?php endif; ?>
      </p>
    </div>
    <a href="<?= e(url('/admin/credits?refresh=1')) ?>" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Refresh</a>
  </div>
  <?= balanceWidgetHtml($balData, 'full') ?>

  <div class="mt-5 pt-4 border-t border-gray-200 dark:border-gray-800 flex flex-wrap items-center gap-2">
    <span class="text-sm text-gray-500 dark:text-gray-400 mr-1">Beli kredit:</span>
    <a href="<?= e($topupUrl('seo-topup-10')) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)"><?= icon('coins', 'w-4 h-4') ?> +10 artikel</a>
    <a href="<?= e($topupUrl('seo-topup-30')) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-semibold border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"><?= icon('coins', 'w-4 h-4') ?> +30 artikel</a>
  </div>
</div>

<!-- Edukasi komposisi kredit (collapsible, default tertutup) -->
<?php $__bd = scribeCreditBreakdown(); $__sec = (int) ($__bd['rows'][2]['qty'] ?? 0); ?>
<details class="group rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 mb-4">
  <summary class="flex items-center justify-between gap-2 px-5 py-4 cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
    <span class="flex items-center gap-2 font-display font-semibold text-sm"><?= icon('coins', 'w-4 h-4 text-gray-400') ?> Bagaimana kredit dihitung?</span>
    <?= icon('chevron-down', 'w-4 h-4 text-gray-400 transition-transform group-open:rotate-180') ?>
  </summary>
  <div class="px-5 pb-5 pt-4 border-t border-gray-100 dark:border-gray-800">
    <?= scribeCreditBreakdownHtml(false) ?>
    <ul class="mt-4 space-y-1.5 text-xs text-gray-500 dark:text-gray-400 list-disc pl-4">
      <li><strong>&ldquo;1 artikel&rdquo;</strong> = estimasi workflow lengkap (±<?= $__sec ?> seksi).</li>
      <li>Bisa lebih hemat: cek SEO real-time &amp; editing <strong>GRATIS</strong>; analisis AI dan regenerate bersifat opsional per-langkah.</li>
      <li>Generate ulang (outline/seksi/analisis) memotong kredit lagi; kegagalan sistem <strong>tidak</strong> memotong (auto-refund).</li>
    </ul>
  </div>
</details>

<!-- Riwayat pemakaian -->
<div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
  <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800">
    <h2 class="font-display font-semibold text-sm">Riwayat Pemakaian AI</h2>
  </div>
  <?php if (!$usage['ok']): ?>
    <div class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Riwayat gagal dimuat — <?= e($usage['message']) ?></div>
  <?php elseif (!$rows): ?>
    <div class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada pemakaian AI.</div>
  <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400">
        <tr>
          <th class="text-left font-medium px-4 py-2.5">Waktu</th>
          <th class="text-left font-medium px-4 py-2.5">Tugas</th>
          <th class="text-right font-medium px-4 py-2.5">Kredit</th>
          <th class="text-left font-medium px-4 py-2.5">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        <?php foreach ($rows as $r): [$lbl, $col] = $statusBadge[$r['status']] ?? [$r['status'], 'gray']; ?>
        <tr>
          <td class="px-4 py-2.5 text-gray-500"><?= e(formatTanggal($r['created_at'])) ?></td>
          <td class="px-4 py-2.5 font-medium"><?= e(aiTaskLabel($r['task_type'])) ?></td>
          <td class="px-4 py-2.5 text-right"><?= (int) $r['credits_charged'] > 0 ? e(creditsToArticles((int) $r['credits_charged'])) . ' art.' : '—' ?></td>
          <td class="px-4 py-2.5"><span class="inline-block text-[11px] font-medium px-2 py-0.5 rounded-md bg-<?= $col ?>-100 text-<?= $col ?>-700 dark:bg-<?= $col ?>-950/50 dark:text-<?= $col ?>-300"><?= e($lbl) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-between mt-4 text-sm">
  <span class="text-gray-500">Halaman <?= $page ?> dari <?= $totalPages ?></span>
  <div class="flex gap-1">
    <?php if ($page > 1): ?><a href="<?= e(url('/admin/credits?upage=' . ($page - 1))) ?>" class="px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Sebelumnya</a><?php endif; ?>
    <?php if ($page < $totalPages): ?><a href="<?= e(url('/admin/credits?upage=' . ($page + 1))) ?>" class="px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Berikutnya</a><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php admin_shell_bottom(); ?>
