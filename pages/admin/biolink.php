<?php
// Builder Biolink (admin): profil + mode homepage + blok tersusun (MVP).
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/biolink.php';

$p        = bioProfile();
$intro    = $p['intro'];
$mode     = bioHomeMode();
$blocks   = bioBlocks(false); // termasuk nonaktif untuk admin
$nets     = bioSocialNetworks();
$upBase   = rtrim(UPLOAD_URL, '/');
$csrf     = generateCSRF();
$inputCls = 'w-full px-3 py-2 rounded-md text-sm bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 outline-none focus:border-[var(--accent)] focus:ring-1 focus:ring-[var(--accent)]';

// Field per tipe blok (dipakai form tambah & edit). $cfg = config existing (edit).
$blockFields = function (string $type, array $cfg = []) use ($inputCls, $nets, $upBase): void {
    switch ($type) {
        case 'button': ?>
            <div class="grid sm:grid-cols-2 gap-3">
              <label class="block"><span class="text-xs font-medium text-gray-500">Label</span>
                <input name="label" value="<?= e($cfg['label'] ?? '') ?>" class="<?= $inputCls ?> mt-1" placeholder="Kunjungi Website"></label>
              <label class="block"><span class="text-xs font-medium text-gray-500">URL</span>
                <input name="url" value="<?= e($cfg['url'] ?? '') ?>" class="<?= $inputCls ?> mt-1" placeholder="https://..."></label>
              <label class="block"><span class="text-xs font-medium text-gray-500">Gaya</span>
                <select name="style" class="<?= $inputCls ?> mt-1">
                  <?php foreach (['filled' => 'Solid', 'outline' => 'Garis', 'soft' => 'Lembut'] as $v => $l): ?>
                  <option value="<?= $v ?>" <?= ($cfg['style'] ?? 'filled') === $v ? 'selected' : '' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select></label>
              <label class="block"><span class="text-xs font-medium text-gray-500">Ikon (Lucide, opsional)</span>
                <input name="icon" value="<?= e($cfg['icon'] ?? '') ?>" class="<?= $inputCls ?> mt-1" placeholder="link, download, coins…"></label>
            </div>
            <label class="block mt-3"><span class="text-xs font-medium text-gray-500">Foto tombol (opsional, mengalahkan ikon)</span>
              <input type="file" name="photo" accept="image/*" class="mt-1 w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-xs"></label>
            <?php if (!empty($cfg['photo'])): ?>
              <div class="mt-2 flex items-center gap-2"><img src="<?= e($upBase . '/' . $cfg['photo']) ?>" class="w-10 h-10 rounded-md object-cover" alt="">
              <label class="text-xs text-red-500"><input type="checkbox" name="remove_photo" class="align-middle"> Hapus foto</label></div>
            <?php endif;
            break;

        case 'social': ?>
            <p class="text-xs text-gray-500 mb-2">Isi yang dipakai saja (WhatsApp: nomor 62…, Email: alamat email).</p>
            <div class="grid sm:grid-cols-2 gap-2.5">
              <?php $items = [];
              foreach (($cfg['items'] ?? []) as $it) $items[$it['network'] ?? ''] = $it['url'] ?? '';
              foreach ($nets as $key => $label): ?>
              <label class="block"><span class="text-xs font-medium text-gray-500"><?= e($label) ?></span>
                <input name="social[<?= e($key) ?>]" value="<?= e($items[$key] ?? '') ?>" class="<?= $inputCls ?> mt-1"></label>
              <?php endforeach; ?>
            </div>
            <?php break;

        case 'text': ?>
            <label class="block"><span class="text-xs font-medium text-gray-500">Judul (opsional)</span>
              <input name="heading" value="<?= e($cfg['heading'] ?? '') ?>" class="<?= $inputCls ?> mt-1"></label>
            <label class="block mt-3"><span class="text-xs font-medium text-gray-500">Teks</span>
              <textarea name="body" rows="3" class="<?= $inputCls ?> mt-1"><?= e($cfg['body'] ?? '') ?></textarea></label>
            <?php break;

        case 'image': ?>
            <label class="block"><span class="text-xs font-medium text-gray-500">Gambar</span>
              <input type="file" name="image" accept="image/*" class="mt-1 w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-xs"></label>
            <?php if (!empty($cfg['path'])): ?><img src="<?= e($upBase . '/' . $cfg['path']) ?>" class="mt-2 w-24 rounded-md border border-gray-200 dark:border-gray-800" alt=""><?php endif; ?>
            <div class="grid sm:grid-cols-2 gap-3 mt-3">
              <label class="block"><span class="text-xs font-medium text-gray-500">Teks alt</span>
                <input name="alt" value="<?= e($cfg['alt'] ?? '') ?>" class="<?= $inputCls ?> mt-1"></label>
              <label class="block"><span class="text-xs font-medium text-gray-500">Tautan (opsional)</span>
                <input name="link" value="<?= e($cfg['link'] ?? '') ?>" class="<?= $inputCls ?> mt-1" placeholder="https://..."></label>
            </div>
            <?php break;

        case 'divider': ?>
            <label class="block"><span class="text-xs font-medium text-gray-500">Jenis</span>
              <select name="divider_style" class="<?= $inputCls ?> mt-1">
                <option value="line" <?= ($cfg['style'] ?? 'line') === 'line' ? 'selected' : '' ?>>Garis</option>
                <option value="space" <?= ($cfg['style'] ?? '') === 'space' ? 'selected' : '' ?>>Spasi</option>
              </select></label>
            <?php break;
    }
};

admin_shell_top('Biolink', '/admin/biolink');
?>
<div class="max-w-3xl space-y-6">
  <div class="flex items-start justify-between gap-3 flex-wrap">
    <div>
      <h2 class="text-xl font-bold">Biolink</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Profil + tautan yang tampil di homepage. Atur mode homepage di bawah.</p>
    </div>
    <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
      <?= icon('external-link', 'w-4 h-4') ?> Lihat homepage
    </a>
  </div>

  <!-- ── Profil & Mode ── -->
  <form method="post" action="<?= e(url('/actions/admin/bio-save-profile')) ?>" enctype="multipart/form-data"
        class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <h3 class="font-display font-semibold">Profil &amp; Mode Homepage</h3>

    <label class="block"><span class="text-xs font-medium text-gray-500">Mode homepage</span>
      <select name="home_mode" id="homeModeSelect" class="<?= $inputCls ?> mt-1">
        <option value="blog"    <?= $mode === 'blog' ? 'selected' : '' ?>>Blog — hero + daftar artikel (default)</option>
        <option value="biolink" <?= $mode === 'biolink' ? 'selected' : '' ?>>Biolink — profil + tautan saja</option>
        <option value="hybrid"  <?= $mode === 'hybrid' ? 'selected' : '' ?>>Hybrid — biolink di atas, artikel di bawah</option>
      </select></label>

    <?php
      $homeUrl = e(url('/'));
      $blogUrl = e(url('/blog'));
      $hmBtn   = 'inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800';
    ?>
    <div class="mt-2 rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-3">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">URL homepage</p>
      <div class="space-y-1.5">
        <div class="flex flex-wrap items-center gap-2">
          <span id="hmHomeLabel" class="w-16 shrink-0 text-xs font-medium text-gray-500">BioLink</span>
          <code class="flex-1 min-w-[160px] font-mono text-xs text-gray-700 dark:text-gray-200 truncate"><?= $homeUrl ?></code>
          <button type="button" data-hmcopy="<?= $homeUrl ?>" class="<?= $hmBtn ?>"><?= icon('copy', 'w-3.5 h-3.5') ?> Salin</button>
          <a href="<?= $homeUrl ?>" target="_blank" rel="noopener" class="<?= $hmBtn ?>"><?= icon('external-link', 'w-3.5 h-3.5') ?> Buka</a>
        </div>
        <div id="hmBlogRow" class="flex flex-wrap items-center gap-2">
          <span class="w-16 shrink-0 text-xs font-medium text-gray-500">Blog</span>
          <code class="flex-1 min-w-[160px] font-mono text-xs text-gray-700 dark:text-gray-200 truncate"><?= $blogUrl ?></code>
          <button type="button" data-hmcopy="<?= $blogUrl ?>" class="<?= $hmBtn ?>"><?= icon('copy', 'w-3.5 h-3.5') ?> Salin</button>
          <a href="<?= $blogUrl ?>" target="_blank" rel="noopener" class="<?= $hmBtn ?>"><?= icon('external-link', 'w-3.5 h-3.5') ?> Buka</a>
        </div>
      </div>
      <p id="hmNote" class="mt-2 text-xs text-gray-400"></p>
    </div>
    <script>
    (function () {
      var sel = document.getElementById('homeModeSelect');
      if (!sel) return;
      var homeLabel = document.getElementById('hmHomeLabel'),
          blogRow   = document.getElementById('hmBlogRow'),
          note      = document.getElementById('hmNote');
      function apply() {
        var m = sel.value;
        if (m === 'biolink') {
          homeLabel.textContent = 'BioLink'; blogRow.style.display = '';
          note.textContent = 'Homepage (/) menampilkan BioLink. Blog tetap bisa diakses di /blog.';
        } else if (m === 'hybrid') {
          homeLabel.textContent = 'Homepage'; blogRow.style.display = '';
          note.textContent = 'Homepage (/) menampilkan BioLink + artikel terbaru. Blog lengkap di /blog.';
        } else {
          homeLabel.textContent = 'Homepage'; blogRow.style.display = 'none';
          note.textContent = 'Homepage (/) menampilkan blog. BioLink tidak aktif.';
        }
      }
      sel.addEventListener('change', apply); apply();
      document.querySelectorAll('[data-hmcopy]').forEach(function (b) {
        b.addEventListener('click', function () {
          var t = b.getAttribute('data-hmcopy'), o = b.innerHTML;
          var ok = function () { b.innerHTML = 'Tersalin'; setTimeout(function () { b.innerHTML = o; }, 1200); };
          if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(ok).catch(function () {});
        });
      });
    })();
    </script>

    <div class="grid sm:grid-cols-2 gap-3">
      <label class="block"><span class="text-xs font-medium text-gray-500">Nama tampil</span>
        <input name="bio_display_name" value="<?= e(getSetting('bio_display_name', '')) ?>" class="<?= $inputCls ?> mt-1" placeholder="<?= e(blogName()) ?>"></label>
      <label class="block"><span class="text-xs font-medium text-gray-500">Gaya header</span>
        <select name="bio_header_style" id="bioHeaderStyle" class="<?= $inputCls ?> mt-1">
          <?php foreach (['gradient' => 'Gradien aksen', 'solid' => 'Warna solid', 'image' => 'Gambar'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= $p['header_style'] === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select></label>
    </div>

    <label class="block"><span class="text-xs font-medium text-gray-500">Bio singkat</span>
      <textarea name="bio_text" rows="2" class="<?= $inputCls ?> mt-1" placeholder="Ceritakan brand/kamu dalam 1–2 kalimat."><?= e($p['bio']) ?></textarea></label>

    <div id="bioHeaderColor" class="<?= $p['header_style'] === 'solid' ? '' : 'hidden' ?>">
      <label class="block"><span class="text-xs font-medium text-gray-500">Warna header</span>
        <input type="color" name="header_color" value="<?= e(preg_match('/^#[0-9a-fA-F]{6}$/', $p['header_bg']) ? $p['header_bg'] : '#6366f1') ?>" class="mt-1 h-10 w-20 rounded-md border border-gray-200 dark:border-gray-700 bg-transparent"></label>
    </div>
    <div id="bioHeaderImage" class="<?= $p['header_style'] === 'image' ? '' : 'hidden' ?>">
      <label class="block"><span class="text-xs font-medium text-gray-500">Gambar header</span>
        <input type="file" name="header_image" accept="image/*" class="mt-1 w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-xs"></label>
      <?php if ($p['header_style'] === 'image' && $p['header_bg'] !== ''): ?><img src="<?= e($upBase . '/' . $p['header_bg']) ?>" class="mt-2 w-full max-w-xs rounded-md border border-gray-200 dark:border-gray-800" alt=""><?php endif; ?>
    </div>

    <div>
      <span class="text-xs font-medium text-gray-500">Avatar</span>
      <div class="mt-1 flex items-center gap-3">
        <div class="w-14 h-14 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0">
          <?php if ($p['avatar'] !== ''): ?><img src="<?= e($upBase . '/' . $p['avatar']) ?>" class="w-full h-full object-cover" alt=""><?php else: ?><span class="text-gray-400 text-xs">—</span><?php endif; ?>
        </div>
        <input type="file" name="avatar" accept="image/*" class="text-xs file:mr-2 file:rounded-md file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-xs">
        <?php if ($p['avatar'] !== ''): ?><label class="text-xs text-red-500"><input type="checkbox" name="remove_avatar" class="align-middle"> Hapus</label><?php endif; ?>
      </div>
    </div>

    <section class="rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/30 p-4 space-y-4" aria-labelledby="bioIntroAdminTitle">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h4 id="bioIntroAdminTitle" class="font-display font-semibold text-sm">Perkenalan</h4>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tampilkan tombol compact di area profil yang membuka modal desktop atau bottom sheet mobile.</p>
        </div>
        <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-200 shrink-0">
          <input type="checkbox" name="bio_intro_enabled" value="1" <?= !empty($intro['enabled']) ? 'checked' : '' ?> class="rounded border-gray-300 text-[var(--accent)] focus:ring-[var(--accent)]">
          Aktif
        </label>
      </div>

      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block"><span class="text-xs font-medium text-gray-500">Teks tombol</span>
          <input name="bio_intro_button" maxlength="80" value="<?= e($intro['button']) ?>" class="<?= $inputCls ?> mt-1" placeholder="Kenalan dengan Saya"></label>
        <label class="block"><span class="text-xs font-medium text-gray-500">Judul perkenalan</span>
          <input name="bio_intro_title" maxlength="160" value="<?= e($intro['title']) ?>" class="<?= $inputCls ?> mt-1" placeholder="Perkenalan"></label>
      </div>

      <label class="block"><span class="text-xs font-medium text-gray-500">Isi perkenalan</span>
        <textarea name="bio_intro_text" maxlength="5000" rows="5" class="<?= $inputCls ?> mt-1" placeholder="Ceritakan siapa Anda, fokus utama, dan bagaimana pengunjung dapat terhubung."><?= e($intro['text']) ?></textarea>
        <span class="mt-1 block text-[11px] text-gray-400">Teks tampil aman tanpa HTML dan animasi hanya berjalan saat panel dibuka.</span>
      </label>

      <div>
        <span class="text-xs font-medium text-gray-500">Foto perkenalan (opsional)</span>
        <div class="mt-1 flex flex-wrap items-center gap-3">
          <?php if ($intro['photo'] !== ''): ?>
            <img src="<?= e($upBase . '/' . $intro['photo']) ?>" class="w-16 h-16 rounded-md border border-gray-200 dark:border-gray-700 object-cover" alt="Preview foto perkenalan">
          <?php endif; ?>
          <input type="file" name="bio_intro_photo" accept="image/png,image/jpeg,image/webp,image/gif" class="text-xs file:mr-2 file:rounded-md file:border-0 file:bg-white dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-xs">
          <?php if ($intro['photo'] !== ''): ?><label class="text-xs text-red-500"><input type="checkbox" name="remove_intro_photo" class="align-middle"> Hapus</label><?php endif; ?>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block"><span class="text-xs font-medium text-gray-500">Animasi teks</span>
          <select name="bio_intro_animation" class="<?= $inputCls ?> mt-1">
            <?php foreach (['typewriter' => 'Typewriter', 'fade' => 'Fade', 'normal' => 'Normal'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= $intro['animation'] === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select></label>
        <label class="block"><span class="text-xs font-medium text-gray-500">Kecepatan animasi</span>
          <div class="relative mt-1"><input type="number" name="bio_intro_speed" min="10" max="200" step="5" value="<?= (int) $intro['speed'] ?>" class="<?= $inputCls ?> pr-24"><span class="absolute right-3 top-2.5 text-[11px] text-gray-400">ms / karakter</span></div>
          <span class="mt-1 block text-[11px] text-gray-400">Digunakan pada animasi Typewriter.</span>
        </label>
      </div>

      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block"><span class="text-xs font-medium text-gray-500">Teks CTA (opsional)</span>
          <input name="bio_intro_cta_label" maxlength="100" value="<?= e($intro['cta_label']) ?>" class="<?= $inputCls ?> mt-1" placeholder="Hubungi Saya"></label>
        <label class="block"><span class="text-xs font-medium text-gray-500">URL CTA</span>
          <input type="url" name="bio_intro_cta_url" maxlength="500" value="<?= e($intro['cta_url']) ?>" class="<?= $inputCls ?> mt-1" placeholder="https://..."></label>
      </div>
      <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
        <input type="checkbox" name="bio_intro_cta_blank" value="1" <?= !empty($intro['cta_blank']) ? 'checked' : '' ?> class="rounded border-gray-300 text-[var(--accent)] focus:ring-[var(--accent)]">
        Buka CTA di tab baru
      </label>
    </section>

    <div class="pt-2 flex items-center gap-3 flex-wrap">
      <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-md text-sm font-semibold text-white shadow-sm hover:opacity-90" style="background:var(--accent)">
        <?= icon('save', 'w-4 h-4') ?> Simpan Biolink
      </button>
      <span data-profile-dirty class="hidden text-xs font-medium text-amber-600 dark:text-amber-400">Perubahan belum disimpan — klik Simpan Biolink.</span>
    </div>
  </form>

  <!-- ── Blok ── -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="font-display font-semibold">Blok Tautan</h3>
      <span class="text-xs text-gray-400"><?= count($blocks) ?> blok</span>
    </div>

    <!-- Tambah blok -->
    <div class="flex flex-wrap gap-2">
      <?php foreach (BIO_BLOCK_TYPES as $t): ?>
      <details class="bio-add">
        <summary class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 select-none">
          <?= icon('plus', 'w-4 h-4') ?> <?= e(bioBlockTypeLabel($t)) ?>
        </summary>
        <form method="post" action="<?= e(url('/actions/admin/bio-save-block')) ?>" enctype="multipart/form-data"
              class="mt-3 w-full rounded-md border border-gray-200 dark:border-gray-800 p-4 space-y-3 bg-gray-50/60 dark:bg-gray-800/30">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="type" value="<?= e($t) ?>">
          <?php $blockFields($t); ?>
          <div><button type="submit" class="px-3.5 py-1.5 rounded-md text-sm font-semibold text-white" style="background:var(--accent)">Tambah <?= e(bioBlockTypeLabel($t)) ?></button></div>
        </form>
      </details>
      <?php endforeach; ?>
    </div>

    <!-- Daftar blok -->
    <?php if (!$blocks): ?>
      <p class="text-sm text-gray-400 py-6 text-center">Belum ada blok. Tambahkan lewat tombol di atas.</p>
    <?php else: ?>
      <ul class="space-y-2">
        <?php foreach ($blocks as $i => $b): ?>
        <li class="rounded-md border border-gray-200 dark:border-gray-800 <?= $b['is_active'] ? '' : 'opacity-60' ?>">
          <div class="flex items-center gap-2 px-3 py-2.5">
            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300"><?= e(bioBlockTypeLabel($b['type'])) ?></span>
            <span class="text-sm truncate flex-1"><?= e(bioBlockSummary($b)) ?></span>
            <?php if (!$b['is_active']): ?><span class="text-[11px] text-amber-500">nonaktif</span><?php endif; ?>
            <!-- reorder -->
            <form method="post" action="<?= e(url('/actions/admin/bio-reorder')) ?>" class="inline">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="block_id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="direction" value="up">
              <button class="p-1.5 rounded text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30" <?= $i === 0 ? 'disabled' : '' ?> aria-label="Naik"><?= icon('chevron-down', 'w-4 h-4 rotate-180') ?></button>
            </form>
            <form method="post" action="<?= e(url('/actions/admin/bio-reorder')) ?>" class="inline">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="block_id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="direction" value="down">
              <button class="p-1.5 rounded text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30" <?= $i === count($blocks) - 1 ? 'disabled' : '' ?> aria-label="Turun"><?= icon('chevron-down', 'w-4 h-4') ?></button>
            </form>
            <!-- hapus -->
            <form method="post" action="<?= e(url('/actions/admin/bio-delete-block')) ?>" class="inline" data-confirm="Hapus blok ini?" data-confirm-danger data-confirm-ok="Hapus">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="block_id" value="<?= (int) $b['id'] ?>">
              <button class="p-1.5 rounded text-gray-400 hover:text-red-600" aria-label="Hapus"><?= icon('trash', 'w-4 h-4') ?></button>
            </form>
          </div>
          <details class="border-t border-gray-100 dark:border-gray-800">
            <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-[var(--accent)] select-none">Edit</summary>
            <form method="post" action="<?= e(url('/actions/admin/bio-save-block')) ?>" enctype="multipart/form-data" class="px-3 pb-3 space-y-3">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="block_id" value="<?= (int) $b['id'] ?>">
              <input type="hidden" name="type" value="<?= e($b['type']) ?>">
              <?php $blockFields($b['type'], $b['cfg']); ?>
              <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300"><input type="checkbox" name="is_active" <?= $b['is_active'] ? 'checked' : '' ?>> Aktif (tampil di homepage)</label>
              <div><button type="submit" class="px-3.5 py-1.5 rounded-md text-sm font-semibold text-white" style="background:var(--accent)">Simpan</button></div>
            </form>
          </details>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<script>
// Toggle field header (warna vs gambar) mengikuti gaya header.
(function () {
  var sel = document.getElementById('bioHeaderStyle');
  if (!sel) return;
  function sync() {
    document.getElementById('bioHeaderColor').classList.toggle('hidden', sel.value !== 'solid');
    document.getElementById('bioHeaderImage').classList.toggle('hidden', sel.value !== 'image');
  }
  sel.addEventListener('change', sync); sync();
  // Hanya satu <details> tambah-blok terbuka pada satu waktu.
  document.querySelectorAll('details.bio-add').forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (d.open) document.querySelectorAll('details.bio-add').forEach(function (o) { if (o !== d) o.open = false; });
    });
  });
  // Penjaga perubahan profil belum disimpan: form profil & form blok terpisah,
  // jadi mengetik nama/bio lalu melakukan aksi lain bisa membuang perubahan.
  var pform = document.querySelector('form[action$="bio-save-profile"]');
  if (pform) {
    var dirty = false;
    var badge = pform.querySelector('[data-profile-dirty]');
    pform.addEventListener('input', function () { dirty = true; if (badge) badge.classList.remove('hidden'); });
    pform.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  }
})();
</script>
<?php admin_shell_bottom(); ?>
