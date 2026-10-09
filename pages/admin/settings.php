<?php
// Pengaturan (admin). A-03: AI + Brand Voice + BYOK. (Rebrand/Appearance → A-09.)
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/AverionAiAdapter.php';
require_once __DIR__ . '/../../helpers/ai-balance.php';

$adapter = new AverionAiAdapter();
// Status BYOK dari saldo (cache) — jangan blokir halaman bila gateway mati.
$bal = $adapter->getBalanceCached();
$byokActive = $bal['ok'] ? !empty($bal['data']['byok_active']) : false;
$byokHint   = $bal['ok'] ? ($bal['data']['byok_hint'] ?? '') : '';
$byokProvider = $bal['ok'] ? (string) ($bal['data']['byok_provider'] ?? '') : '';
$byokProviderLabel = $byokProvider === 'gemini' ? 'Google Gemini' : ($byokProvider === 'anthropic' ? 'Anthropic' : '');

$defaultLang = getSetting('default_language', 'id');
$bvBusiness  = getSetting('brandvoice_business', '');
$bvAudience  = getSetting('brandvoice_audience', '');
$bvTone      = getSetting('brandvoice_tone', '');
$bvBanned    = json_decode((string) getSetting('brandvoice_banned_words', '[]'), true) ?: [];
$bvStyle     = getSetting('brandvoice_style_sample', '');

admin_shell_top('Pengaturan', '/admin/settings');
settings_tabs('/admin/settings');
?>
<div class="max-w-3xl space-y-4">

  <!-- AI -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
    <h2 class="font-display font-semibold mb-1">AI &amp; Gateway</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Bahasa default output dan status koneksi ke layanan AI.</p>
    <form method="post" action="<?= e(url('/actions/admin/save-ai-settings')) ?>" class="flex flex-wrap items-end gap-3">
      <?= csrfField() ?>
      <div>
        <label class="block text-xs font-medium mb-1">Bahasa Default Output</label>
        <select name="default_language" class="rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
          <?php foreach (languageOptions() as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= $defaultLang === $code ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan</button>
    </form>
    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 flex items-center gap-3">
      <button type="button" onclick="testGateway(this)" class="rounded-md px-4 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Tes Koneksi</button>
      <span id="gwStatus" class="text-sm"></span>
    </div>
  </div>

  <!-- Brand Voice -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
    <h2 class="font-display font-semibold mb-1">Brand Voice</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">AI menulis dengan gaya brand Anda — bukan gaya generik. Dikirim di setiap permintaan generate.</p>
    <form method="post" action="<?= e(url('/actions/admin/save-brand-voice')) ?>" class="space-y-3">
      <?= csrfField() ?>
      <div>
        <label class="block text-xs font-medium mb-1">Deskripsi Bisnis</label>
        <textarea name="business" rows="2" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><?= e($bvBusiness) ?></textarea>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium mb-1">Target Pembaca</label>
          <input name="audience" value="<?= e($bvAudience) ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Tone</label>
          <select name="tone" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
            <option value="">— Pilih —</option>
            <?php foreach (['santai' => 'Santai', 'profesional' => 'Profesional', 'edukatif' => 'Edukatif', 'persuasif' => 'Persuasif'] as $k => $l): ?>
              <option value="<?= $k ?>" <?= $bvTone === $k ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Kata/Frasa Terlarang <span class="text-gray-400">(satu per baris)</span></label>
        <textarea name="banned_words" rows="3" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono"><?= e(implode("\n", $bvBanned)) ?></textarea>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Contoh Gaya Penulisan</label>
        <textarea name="style_sample" rows="3" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><?= e($bvStyle) ?></textarea>
      </div>
      <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan Brand Voice</button>
    </form>
  </div>

  <!-- BYOK -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
    <h2 class="font-display font-semibold mb-1">BYOK — API Key Sendiri</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Pakai API key Anda sendiri: kredit tidak dipotong. Key dititipkan <strong>terenkripsi di server Averion</strong> (tidak disimpan di situs Anda).</p>
    <?php if ($byokActive): ?>
      <div class="flex flex-wrap items-center gap-3">
        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400"><?= icon('check-circle', 'w-5 h-5') ?> BYOK aktif</span>
        <?php if ($byokProviderLabel): ?><span class="rounded-md border border-gray-200 dark:border-gray-700 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-300"><?= e($byokProviderLabel) ?></span><?php endif; ?>
        <?php if ($byokHint): ?><span class="text-sm font-mono text-gray-500"><?= e($byokHint) ?></span><?php endif; ?>
        <form method="post" action="<?= e(url('/actions/admin/delete-byok')) ?>" data-confirm="Hapus BYOK? Kredit akan dipotong kembali." data-confirm-danger data-confirm-title="Hapus BYOK">
          <?= csrfField() ?>
          <button type="submit" class="rounded-md px-3 py-1.5 text-sm font-medium border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-950/40">Hapus BYOK</button>
        </form>
      </div>
    <?php else: ?>
      <form method="post" action="<?= e(url('/actions/admin/save-byok')) ?>" class="flex flex-wrap items-end gap-3">
        <?= csrfField() ?>
        <div>
          <label class="block text-xs font-medium mb-1">Provider</label>
          <select name="provider" id="byokProvider" class="rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
            <option value="anthropic">Anthropic</option>
            <option value="gemini">Google Gemini</option>
          </select>
        </div>
        <div class="flex-1 min-w-[220px]">
          <label class="block text-xs font-medium mb-1">API Key</label>
          <input name="api_key" id="byokApiKey" type="password" required maxlength="512" autocomplete="off" spellcheck="false" placeholder="sk-ant-…" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono">
          <p id="byokProviderHelp" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Gunakan API key Anthropic Console.</p>
        </div>
        <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Aktifkan BYOK</button>
      </form>
    <?php endif; ?>
  </div>

</div>

<script>
const TEST_URL = <?= json_encode(url('/actions/admin/test-gateway')) ?>;
const byokProvider = document.getElementById('byokProvider');
const byokApiKey = document.getElementById('byokApiKey');
const byokProviderHelp = document.getElementById('byokProviderHelp');
if (byokProvider && byokApiKey && byokProviderHelp) {
  const syncByokProvider = () => {
    const gemini = byokProvider.value === 'gemini';
    byokApiKey.placeholder = gemini ? 'AQ.…' : 'sk-ant-…';
    byokProviderHelp.textContent = gemini
      ? 'Gunakan auth key Google AI Studio terbaru (AQ.…). Key format lama tetap didukung.'
      : 'Gunakan API key Anthropic Console.';
  };
  byokProvider.addEventListener('change', syncByokProvider);
  syncByokProvider();
}
function testGateway(btn) {
  const s = document.getElementById('gwStatus');
  btn.disabled = true; s.textContent = 'Menghubungi gateway…'; s.className = 'text-sm text-gray-400';
  fetch(TEST_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json()).then(d => {
      s.textContent = d.message + (d.plan ? ' (plan: ' + d.plan + ')' : '');
      s.className = 'text-sm ' + (d.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400');
    })
    .catch(() => { s.textContent = 'Gagal menghubungi gateway.'; s.className = 'text-sm text-red-600'; })
    .finally(() => btn.disabled = false);
}
</script>
<?php admin_shell_bottom(); ?>
