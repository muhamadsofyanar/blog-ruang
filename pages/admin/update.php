<?php
// Pembaruan sistem (admin). Self-update pola Averion: cek → unduh → backup →
// ekstrak → migrasi (langkah AJAX bertahap, anti-timeout).
require_once __DIR__ . '/_shell.php';
// Identitas vendor (Averion) — halaman pembaruan TIDAK ikut rebrand customer.
admin_shell_top('Pembaruan Sistem', '/admin/update', true);
?>
<div class="max-w-xl">
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
    <div class="flex items-center gap-3 mb-4">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-md text-white" style="background:var(--accent)"><?= icon('download', 'w-5 h-5') ?></span>
      <div>
        <h2 class="font-display font-semibold">Pembaruan Sistem</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">Averion SEO Engine</p>
      </div>
    </div>
    <dl class="grid grid-cols-2 gap-4 text-sm mb-5">
      <div><dt class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Versi Terpasang</dt><dd class="font-mono" id="curVer"><?= e(defined('APP_VERSION') ? APP_VERSION : '-') ?></dd></div>
      <div><dt class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Status</dt><dd id="updStatus">Belum dicek</dd></div>
    </dl>

    <div class="flex flex-wrap gap-2">
      <button type="button" id="btnCheck" onclick="updateCheck()" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Cek Pembaruan</button>
      <button type="button" id="btnApply" onclick="updateApply()" class="rounded-md px-4 py-2 text-sm font-semibold border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 hidden">Update Sekarang</button>
    </div>

    <div id="updMsg" class="mt-4 text-sm hidden"></div>

    <!-- Changelog -->
    <div id="updChangelog" class="mt-4 hidden">
      <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Catatan Rilis</div>
      <pre id="updChangelogBody" class="text-xs bg-gray-50 dark:bg-gray-800/50 rounded-md p-3 whitespace-pre-wrap"></pre>
    </div>

    <!-- Progress langkah -->
    <div id="updProgress" class="mt-4 space-y-1 hidden"></div>
  </div>
</div>

<script>
const UPD = <?= json_encode(['csrf' => generateCSRF(), 'checkUrl' => url('/actions/admin/update-check'), 'applyUrl' => url('/actions/admin/update-apply')]) ?>;

function setStatus(text, cls) { const s = document.getElementById('updStatus'); s.textContent = text; s.className = cls || ''; }
function msg(text, cls) { const m = document.getElementById('updMsg'); m.textContent = text; m.className = 'mt-4 text-sm ' + (cls || ''); m.classList.remove('hidden'); }

function updateCheck() {
  const btn = document.getElementById('btnCheck');
  btn.disabled = true; setStatus('Mengecek…', 'text-gray-400');
  document.getElementById('updMsg').classList.add('hidden');
  document.getElementById('btnApply').classList.add('hidden');
  document.getElementById('updChangelog').classList.add('hidden');
  const fd = new FormData(); fd.append('csrf_token', UPD.csrf);
  fetch(UPD.checkUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => {
      if (!d.ok) { setStatus('Gagal cek', 'text-red-600'); msg(d.message, 'text-red-600'); return; }
      if (d.has_update) {
        setStatus('Update v' + d.version + ' tersedia', 'text-amber-600 dark:text-amber-400 font-medium');
        document.getElementById('btnApply').classList.remove('hidden');
        if (d.changelog) {
          document.getElementById('updChangelogBody').textContent = d.changelog; // DATA (textContent)
          document.getElementById('updChangelog').classList.remove('hidden');
        }
      } else {
        setStatus('Terkini', 'text-emerald-600 dark:text-emerald-400');
        msg(d.message || 'Sudah versi terbaru.', 'text-gray-500');
      }
    })
    .catch(() => { setStatus('Gagal', 'text-red-600'); msg('Tidak bisa menghubungi server pembaruan.', 'text-red-600'); })
    .finally(() => btn.disabled = false);
}

async function updateApply() {
  if (!(await scribeConfirm('Situs sebaiknya tidak diedit selama proses.', { title: 'Terapkan pembaruan sekarang?', okText: 'Terapkan' }))) return;
  document.getElementById('btnApply').disabled = true;
  document.getElementById('btnCheck').disabled = true;
  const prog = document.getElementById('updProgress');
  prog.innerHTML = ''; prog.classList.remove('hidden');
  document.getElementById('updMsg').classList.add('hidden');

  const steps = ['download', 'backup', 'extract', 'migrate'];
  const labels = { download:'Mengunduh paket', backup:'Membackup file', extract:'Mengekstrak', migrate:'Menjalankan migrasi' };
  for (const step of steps) {
    const row = document.createElement('div'); row.className = 'flex items-center gap-2 text-xs';
    row.innerHTML = '<span class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-600 inline-flex items-center justify-center text-[9px]">\u00B7</span>';
    const t = document.createElement('span'); t.textContent = labels[step] + '…'; row.appendChild(t);
    prog.appendChild(row);
    const ic = row.querySelector('span');

    const fd = new FormData(); fd.append('csrf_token', UPD.csrf); fd.append('step', step);
    let d;
    try { d = await (await fetch(UPD.applyUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })).json(); }
    catch (e) { d = { ok:false, message:'Koneksi terputus saat ' + step + '.' }; }

    if (!d.ok) {
      ic.className = 'w-4 h-4 rounded-full bg-red-500 text-white inline-flex items-center justify-center text-[9px]'; ic.textContent = '!';
      msg(d.message || ('Gagal pada langkah ' + step), 'text-red-600');
      document.getElementById('btnCheck').disabled = false;
      return;
    }
    ic.className = 'w-4 h-4 rounded-full bg-emerald-500 text-white inline-flex items-center justify-center text-[9px]'; ic.textContent = '\u2713';
    t.textContent = d.label || labels[step];
    if (d.next === 'done') {
      setStatus('Terpasang v' + (d.version || ''), 'text-emerald-600 dark:text-emerald-400 font-medium');
      msg('Pembaruan selesai ke v' + (d.version || '') + '. Muat ulang halaman untuk memakai versi baru.', 'text-emerald-600');
    }
  }
}
</script>
<?php admin_shell_bottom(); ?>
