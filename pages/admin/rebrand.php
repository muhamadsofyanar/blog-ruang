<?php
// Settings > Rebrand (admin). Identitas customer: nama/tagline/logo/favicon/OG/
// email sender + accent + toggle Powered by. Dipakai admin sekarang + frontend
// Track B. BATAS: halaman enforcement/update TIDAK membaca setting ini.
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/frontend.php';
require_once __DIR__ . '/../../helpers/article-cta.php';

$blogName  = getSetting('blog_name', '');
$tagline   = getSetting('blog_tagline', '');
$accent    = getSetting('accent_color', '#6366f1');
$logo      = getSetting('brand_logo', '');
$favicon   = getSetting('brand_favicon', '');
$ogDefault = getSetting('brand_og_default', '');
$social    = getSetting('brand_social', '');
$emailFrom = getSetting('email_sender_name', '');
$poweredBy = getSetting('powered_by_enabled', '1') !== '0';
$poweredText  = getSetting('powered_by_text', 'Powered by Averion SEO Engine');
$poweredUrl   = getSetting('powered_by_url', 'https://averion.id');
$poweredBlank = getSetting('powered_by_blank', '1') !== '0';
$ctaLabel  = getSetting('cta_label', '');
$ctaUrl    = getSetting('cta_url', '');
$bandMode  = getSetting('cta_band_mode', 'off');
$bandTitle = getSetting('cta_band_title', '');
$bandText  = getSetting('cta_band_text', '');
$bandBtn   = getSetting('cta_band_btn_label', '');
$bandUrl   = getSetting('cta_band_url', '');
$promoEnabled  = getSetting('promo_enabled', '0') === '1';
$promoCaption  = getSetting('promo_caption', '');
$promoImage    = getSetting('promo_image', '');
$promoCtaLabel = getSetting('promo_cta_label', '');
$promoCtaUrl   = getSetting('promo_cta_url', '');
$promoPosition = getSetting('promo_position', 'bottom-left');
$promoDelay    = (int) getSetting('promo_delay', '3');
if ($promoDelay < 0 || $promoDelay > 120) $promoDelay = 3;
$upBase    = rtrim(UPLOAD_URL, '/');

admin_shell_top('Rebrand', '/admin/settings');
settings_tabs('/admin/rebrand');

$imgRow = function (string $name, string $label, string $cur, string $hint) use ($upBase) {
    ?>
    <div class="flex items-center gap-4">
      <div class="w-16 h-16 rounded-md border border-gray-200 dark:border-gray-800 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-800/50 shrink-0">
        <?php if ($cur): ?><img src="<?= e($upBase . '/' . $cur) ?>" alt="" class="max-w-full max-h-full object-contain"><?php else: ?><span class="text-xs text-gray-400">—</span><?php endif; ?>
      </div>
      <div class="flex-1">
        <label class="block text-sm font-medium mb-1"><?= e($label) ?></label>
        <input type="file" name="<?= e($name) ?>" accept="image/*,.ico,.svg" class="w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-xs file:font-medium">
        <p class="text-xs text-gray-400 mt-1"><?= e($hint) ?></p>
      </div>
    </div>
    <?php
};
?>
<div class="max-w-2xl">
  <form method="post" action="<?= e(url('/actions/admin/save-rebrand')) ?>" enctype="multipart/form-data" class="space-y-5">
    <?= csrfField() ?>

    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
      <h2 class="font-display font-semibold text-sm">Identitas</h2>
      <div>
        <label class="block text-sm font-medium mb-1">Nama Blog</label>
        <input name="blog_name" value="<?= e($blogName) ?>" placeholder="Averion SEO Engine" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Tagline</label>
        <input name="blog_tagline" value="<?= e($tagline) ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Nama Pengirim Email <span class="text-gray-400">(newsletter Track B)</span></label>
        <input name="email_sender_name" value="<?= e($emailFrom) ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Profil Sosial <span class="text-gray-400">(schema Organization / sameAs)</span></label>
        <textarea name="brand_social" rows="3" placeholder="https://facebook.com/brand&#10;https://instagram.com/brand&#10;https://x.com/brand" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono"><?= e($social) ?></textarea>
        <p class="text-xs text-gray-500 mt-1">Satu URL per baris (maks 10). Memperkuat pengenalan brand di hasil pencarian. Muncul di schema homepage.</p>
      </div>
    </div>

    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
      <h2 class="font-display font-semibold text-sm">Aset Visual</h2>
      <?php $imgRow('logo', 'Logo', $logo, 'Tampil di header frontend, halaman login, dan sidebar admin.'); ?>
      <?php $imgRow('favicon', 'Favicon', $favicon, 'Tampil di tab browser (admin + frontend). PNG/ICO/SVG.'); ?>
      <?php $imgRow('og_default', 'OG Default Image', $ogDefault, 'Gambar share default halaman non-artikel (Track B).'); ?>
    </div>

    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
      <h2 class="font-display font-semibold text-sm">Warna & Footer</h2>
      <div class="flex items-center gap-3">
        <label class="text-sm font-medium">Warna Aksen</label>
        <input type="color" name="accent_color" value="<?= e($accent) ?>" class="w-10 h-10 rounded-md border border-gray-300 dark:border-gray-700 bg-transparent cursor-pointer">
        <span class="text-xs font-mono text-gray-500"><?= e($accent) ?></span>
      </div>
      <div class="pt-2 border-t border-gray-200 dark:border-gray-800 space-y-3">
        <label class="flex items-start gap-2 cursor-pointer">
          <input type="checkbox" name="powered_by_enabled" value="1" <?= $poweredBy ? 'checked' : '' ?> class="mt-0.5">
          <span class="text-sm">Tampilkan "Powered by" di footer frontend
            <span class="block text-xs text-gray-400">Default aktif. Bila dimatikan, seluruh teks Powered by disembunyikan.</span>
          </span>
        </label>
        <div>
          <label class="block text-sm font-medium mb-1">Teks Powered by</label>
          <input name="powered_by_text" value="<?= e($poweredText) ?>" placeholder="Powered by Averion SEO Engine" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">URL Powered by <span class="text-gray-400">(opsional)</span></label>
            <input name="powered_by_url" value="<?= e($poweredUrl) ?>" placeholder="https://…" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
            <p class="text-xs text-gray-400 mt-1">Kosongkan agar teks tampil tanpa link.</p>
          </div>
          <div class="flex items-end pb-1">
            <label class="flex items-center gap-2 cursor-pointer text-sm">
              <input type="checkbox" name="powered_by_blank" value="1" <?= $poweredBlank ? 'checked' : '' ?>> Buka link di tab baru
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
      <h2 class="font-display font-semibold text-sm">Tombol CTA Header</h2>
      <p class="text-xs text-gray-400 -mt-2">Tombol ajakan di kanan header frontend. Kosongkan keduanya untuk menyembunyikan.</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Label</label>
          <input name="cta_label" value="<?= e($ctaLabel) ?>" placeholder="Hubungi Kami" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">URL</label>
          <input name="cta_url" value="<?= e($ctaUrl) ?>" placeholder="https://…" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
      </div>
    </div>

    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
      <h2 class="font-display font-semibold text-sm">CTA Beranda (Band Bawah)</h2>
      <div>
        <label class="block text-sm font-medium mb-1">Mode</label>
        <select name="cta_band_mode" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
          <?php foreach (['off' => 'Nonaktif', 'newsletter' => 'Newsletter (form email)', 'link' => 'Link Produk (tombol)'] as $mk => $ml): ?>
          <option value="<?= $mk ?>" <?= $bandMode === $mk ? 'selected' : '' ?>><?= e($ml) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-xs text-gray-400 mt-1">Newsletter menyimpan email ke daftar pelanggan. Link menampilkan tombol menuju URL.</p>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Judul</label>
        <input name="cta_band_title" value="<?= e($bandTitle) ?>" placeholder="Dapatkan artikel terbaru" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Teks Pendukung</label>
        <input name="cta_band_text" value="<?= e($bandText) ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Label Tombol</label>
          <input name="cta_band_btn_label" value="<?= e($bandBtn) ?>" placeholder="Berlangganan" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">URL Tombol <span class="text-gray-400">(mode Link)</span></label>
          <input name="cta_band_url" value="<?= e($bandUrl) ?>" placeholder="https://…" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
      </div>
    </div>

    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
      <h2 class="font-display font-semibold text-sm">Popup Promo (frontend)</h2>
      <p class="text-xs text-gray-400 -mt-2">Popup melayang di pojok kiri-bawah halaman blog. Pengunjung bisa menutupnya; hanya tampil bila caption terisi.</p>
      <label class="flex items-start gap-2 cursor-pointer">
        <input type="checkbox" name="promo_enabled" value="1" <?= $promoEnabled ? 'checked' : '' ?> class="mt-0.5">
        <span class="text-sm">Aktifkan Popup Promo</span>
      </label>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Caption</label>
          <textarea name="promo_caption" rows="2" placeholder="Promo spesial minggu ini — diskon 30%!" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><?= e($promoCaption) ?></textarea>
        </div>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium mb-1">Posisi</label>
            <select name="promo_position" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
              <?php foreach (fePromoPositionLabels() as $val => $lab): ?>
              <option value="<?= e($val) ?>" <?= $promoPosition === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Jeda tampil <span class="text-gray-400">(detik)</span></label>
            <input type="number" name="promo_delay" min="0" max="120" step="1" value="<?= e((string) $promoDelay) ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
            <p class="text-xs text-gray-400 mt-1">Popup muncul setelah jeda ini, maksimal 120 detik. Default 3 detik.</p>
          </div>
        </div>
      </div>
      <?php $imgRow('promo_image', 'Gambar Promo (opsional)', $promoImage, 'Tampil di atas caption. Kosongkan untuk popup teks saja.'); ?>
      <?php if ($promoImage): ?>
      <label class="flex items-center gap-2 text-xs text-red-600 dark:text-red-400 cursor-pointer"><input type="checkbox" name="promo_image_remove" value="1"> Hapus gambar promo</label>
      <?php endif; ?>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Teks Tombol CTA</label>
          <input name="promo_cta_label" value="<?= e($promoCtaLabel) ?>" placeholder="Lihat Promo" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Link CTA <span class="text-gray-400">(buka tab baru)</span></label>
          <input name="promo_cta_url" value="<?= e($promoCtaUrl) ?>" placeholder="https://…" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan Rebrand</button>
    </div>
  </form>

  <form method="post" action="<?= e(url('/actions/admin/save-article-cta')) ?>" class="mt-4 space-y-3">
    <?= csrfField() ?>
    <div><h2 class="font-display font-semibold text-sm">CTA Artikel Global</h2>
      <p class="text-xs text-gray-500 mt-1">Default seluruh artikel. Pilihan ikuti induk mempertahankan CTA Beranda (Band Bawah). Pengaturan ini tidak mengubah beranda.</p></div>
    <?php
    $ctaValue = articleCtaGet('global');
    $ctaGlobal = ['mode' => 'legacy'];
    require __DIR__ . '/_article-cta-fields.php';
    ?>
    <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan CTA Artikel Global</button>
  </form>
  <script src="<?= e(url('/assets/js/article-cta.js')) ?>" defer></script>

  <!-- Regenerasi cover fallback (mis. setelah palet/tema cover berubah) -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 mt-4">
    <h2 class="font-display font-semibold text-sm mb-1">Cover artikel (fallback)</h2>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Buat ulang semua cover otomatis (SVG) dengan gaya/palet terkini. Berguna setelah pembaruan mengubah tampilan cover — file gambar tidak ikut terbawa saat update sistem, jadi cover lama perlu diregenerasi. <strong>Cover yang kamu unggah sendiri tidak diubah.</strong></p>
    <form method="post" action="<?= e(url('/actions/admin/regenerate-covers')) ?>"
          data-confirm="Regenerasi semua cover fallback dengan gaya terkini? Cover unggahan sendiri tidak tersentuh." data-confirm-title="Regenerasi cover">
      <?= csrfField() ?>
      <button type="submit" class="inline-flex items-center gap-1.5 rounded-md px-4 py-2 text-sm font-semibold border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"><?= icon('image', 'w-4 h-4') ?> Regenerasi cover</button>
    </form>
  </div>

  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 mt-4">
    <h2 class="font-display font-semibold text-sm mb-1">Optimasi gambar</h2>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Unggahan baru otomatis di-resize, dikompresi, dan dibuat WebP. Jalankan sekali untuk mengoptimalkan logo, promo, BioLink, serta cover unggahan lama. Berkas sumber tidak dihapus.</p>
    <form method="post" action="<?= e(url('/actions/admin/optimize-images')) ?>"
          data-confirm="Optimalkan seluruh gambar lama sekarang? Berkas sumber tetap disimpan sebagai cadangan." data-confirm-title="Optimasi gambar">
      <?= csrfField() ?>
      <button type="submit" class="inline-flex items-center gap-1.5 rounded-md px-4 py-2 text-sm font-semibold border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
        <?= icon('zap', 'w-4 h-4') ?> Optimalkan gambar lama
      </button>
    </form>
  </div>

  <p class="text-xs text-gray-400 mt-4">Catatan: halaman enforcement lisensi &amp; pembaruan sistem tetap identitas Averion (tidak ikut rebrand).</p>
</div>
<?php admin_shell_bottom(); ?>
