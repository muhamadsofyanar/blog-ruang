<?php
// Kelola lisensi (staff bisa lihat, admin bisa re-validasi). Halaman ini
// dikecualikan dari enforcement (bisa diakses saat expired untuk recovery).
require_once __DIR__ . '/_shell.php';

$ls        = resolveCurrentLicenseState();
$status    = getSetting('license_status', 'unknown');
$key       = getSetting('license_key', '');
$plan      = getSetting('license_plan', '-');
$expires   = getSetting('license_expires_at', '');
$lastCheck = getSetting('license_last_checked_at', '');
$domain    = getSetting('license_domain', parseDomainFromAppUrl());
$maskedKey = $key !== '' ? substr($key, 0, 9) . str_repeat('•', 6) . substr($key, -4) : '(belum ada)';

admin_shell_top('Lisensi', '/admin/license');

$badge = ['valid' => 'emerald', 'grace' => 'amber', 'expired' => 'red', 'pending_activation' => 'sky'][$ls['state']] ?? 'gray';
?>
<div class="max-w-2xl">
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
    <div class="flex items-start justify-between gap-4 mb-5">
      <div class="flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-md text-white" style="background:var(--accent)"><?= icon('shield-check') ?></span>
        <div>
          <h2 class="font-display font-semibold">Lisensi Averion SEO Engine</h2>
          <p class="text-xs text-gray-500 dark:text-gray-400">Enforcement Ed25519</p>
        </div>
      </div>
      <span class="text-xs font-semibold px-2.5 py-1 rounded-md bg-<?= $badge ?>-100 text-<?= $badge ?>-700 dark:bg-<?= $badge ?>-950/50 dark:text-<?= $badge ?>-300">
        <?= e(ucfirst(str_replace('_', ' ', $ls['state']))) ?>
      </span>
    </div>

    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
      <div><dt class="text-gray-500 dark:text-gray-400 text-xs mb-0.5">License Key</dt><dd class="font-mono"><?= e($maskedKey) ?></dd></div>
      <div><dt class="text-gray-500 dark:text-gray-400 text-xs mb-0.5">Domain</dt><dd><?= e($domain) ?></dd></div>
      <div><dt class="text-gray-500 dark:text-gray-400 text-xs mb-0.5">Plan</dt><dd><?= e($plan) ?></dd></div>
      <div><dt class="text-gray-500 dark:text-gray-400 text-xs mb-0.5">Status Server</dt><dd><?= e(ucfirst($status)) ?></dd></div>
      <div><dt class="text-gray-500 dark:text-gray-400 text-xs mb-0.5">Kedaluwarsa</dt><dd><?= e($expires !== '' ? $expires : 'Tidak ada (lifetime)') ?></dd></div>
      <div><dt class="text-gray-500 dark:text-gray-400 text-xs mb-0.5">Cek Terakhir</dt><dd><?= e($lastCheck !== '' ? $lastCheck : '-') ?></dd></div>
    </dl>

    <?php if (isAdmin()): ?>
    <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-200 dark:border-gray-800 pt-5">
      <form method="post" action="<?= e(url('/actions/admin/revalidate-license')) ?>">
        <?= csrfField() ?>
        <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Re-validasi Sekarang</button>
      </form>
      <?php if ($key === '' || $ls['state'] === 'pending_activation'): ?>
        <a href="<?= e(url('/admin/license-activate')) ?>" class="rounded-md px-4 py-2 text-sm font-semibold border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Aktivasi Lisensi</a>
      <?php endif; ?>
    </div>
    <?php else: ?>
      <p class="mt-6 text-xs text-gray-400 border-t border-gray-200 dark:border-gray-800 pt-4">Hanya administrator yang dapat me-revalidasi lisensi.</p>
    <?php endif; ?>
  </div>
</div>
<?php admin_shell_bottom(); ?>
