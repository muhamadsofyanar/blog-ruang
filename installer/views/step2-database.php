<?php
$envDefaults = ['host' => getenv('DB_HOST'), 'port' => getenv('DB_PORT'), 'name' => getenv('DB_NAME'), 'user' => getenv('DB_USER'), 'pass' => getenv('DB_PASS'), 'app_url' => getenv('APP_URL')];
$g = fn($k, $d = '') => e($keep[$k] ?? (($envDefaults[$k] ?? false) ?: $d));
// Deteksi APP_URL default dari request saat ini.
$autoUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
?>
<h2 class="text-lg font-semibold mb-1">Database & URL</h2>
<p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Konfigurasi ini disimpan ke <code class="text-xs">config.php</code>.</p>

<form method="post" action="?step=2" class="space-y-3">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="step" value="2">

  <div class="grid grid-cols-2 gap-3">
    <div><label class="block text-xs font-medium mb-1">DB Host</label>
      <input name="db_host" value="<?= $g('host', 'localhost') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
    <div><label class="block text-xs font-medium mb-1">DB Port</label>
      <input name="db_port" value="<?= $g('port', '3306') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
  </div>
  <div><label class="block text-xs font-medium mb-1">Nama Database</label>
    <input name="db_name" value="<?= $g('name') ?>" required class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
  <div class="grid grid-cols-2 gap-3">
    <div><label class="block text-xs font-medium mb-1">DB User</label>
      <input name="db_user" value="<?= $g('user', 'root') ?>" required class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
    <div><label class="block text-xs font-medium mb-1">DB Password</label>
      <input name="db_pass" type="password" value="<?= $g('pass') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
  </div>

  <div class="border-t border-gray-200 dark:border-gray-800 pt-3">
    <label class="block text-xs font-medium mb-1">APP URL <span class="text-gray-400">(host = domain lisensi)</span></label>
    <input name="app_url" value="<?= $g('app_url', $autoUrl) ?>" required class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
  </div>
  <div><label class="block text-xs font-medium mb-1">License Server URL</label>
    <input name="license_server" value="<?= $g('license_server', 'https://vendor.averion.id/api') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
  <div><label class="block text-xs font-medium mb-1">AI Gateway URL</label>
    <input name="ai_gateway" value="<?= $g('ai_gateway', 'https://ai.averion.id') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>

  <button type="submit" class="w-full rounded-md py-2 text-sm font-semibold text-white hover:opacity-90 mt-2" style="background:var(--accent)">
    Simpan & Jalankan Migrasi
  </button>
</form>
