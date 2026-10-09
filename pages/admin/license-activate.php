<?php
// Form aktivasi lisensi (pending_activation). Domain terkunci ke host APP_URL
// (enforcement mengikat domain). Dikecualikan dari enforcement.
require_once __DIR__ . '/_shell.php';
$domain = parseDomainFromAppUrl();
$prefKey = getSetting('license_key', '');
admin_shell_top('Aktivasi Lisensi', '/admin/license');
?>
<div class="max-w-md">
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
    <div class="flex items-center gap-3 mb-4">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-md text-white" style="background:var(--accent)"><?= icon('lock') ?></span>
      <div>
        <h2 class="font-display font-semibold">Aktivasi Lisensi</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">Masukkan license key Averion SEO Engine.</p>
      </div>
    </div>
    <form method="post" action="<?= e(url('/actions/admin/activate-license')) ?>" class="space-y-4">
      <?= csrfField() ?>
      <div>
        <label class="block text-sm font-medium mb-1.5">License Key</label>
        <input type="text" name="license_key" required value="<?= e($prefKey) ?>" placeholder="AVRN-XXXX-XXXX-XXXX-XXXX"
               class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-accent">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5">Domain</label>
        <input type="text" value="<?= e($domain) ?>" readonly
               class="w-full rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 px-3 py-2 text-sm text-gray-500">
        <p class="text-xs text-gray-400 mt-1">Domain terkunci ke host instalasi (APP_URL).</p>
      </div>
      <button type="submit" class="w-full rounded-md py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Aktifkan</button>
    </form>
  </div>
</div>
<?php admin_shell_bottom(); ?>
