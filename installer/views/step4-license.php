<?php
// Domain terkunci ke host APP_URL (config sudah tertulis di step 2).
require_once dirname(__DIR__, 2) . '/config.php';
$domain = strtolower((string) (parse_url(APP_URL, PHP_URL_HOST) ?? ''));
?>
<h2 class="text-lg font-semibold mb-1">Aktivasi Lisensi</h2>
<p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Masukkan license key Averion SEO Engine untuk mengaktifkan enforcement.</p>

<form method="post" action="?step=4" class="space-y-3">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="step" value="4">
  <input type="hidden" name="license_action" value="activate">
  <div><label class="block text-xs font-medium mb-1">License Key</label>
    <input name="license_key" placeholder="AVRN-XXXX-XXXX-XXXX-XXXX" required
           class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono"></div>
  <div><label class="block text-xs font-medium mb-1">Domain</label>
    <input value="<?= e($domain) ?>" readonly class="w-full rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 px-3 py-2 text-sm text-gray-500"></div>
  <button type="submit" class="w-full rounded-md py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Aktifkan Lisensi</button>
</form>

<form method="post" action="?step=4" class="mt-3">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="step" value="4">
  <input type="hidden" name="license_action" value="skip">
  <button type="submit" class="w-full rounded-md py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
    Lewati (aktifkan nanti dari admin)
  </button>
</form>
