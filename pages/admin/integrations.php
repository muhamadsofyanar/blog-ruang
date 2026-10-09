<?php
// Integrasi (admin) — Ingest API: kirim artikel jadi via HTTP ber-token dari
// sistem eksternal. Ingest awal selalu Draft AI; endpoint manajemen dapat
// mengoptimasi dan publish bila diizinkan. Token hash-only.
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/ingest.php';
require_once __DIR__ . '/../../helpers/meta-pixel.php';
require_once __DIR__ . '/../../helpers/mailketing.php';
require_once __DIR__ . '/../../helpers/indexnow.php';
$metaPixel = metaPixelConfig();

// IndexNow — indeks cepat saat publish/update. Key dibuat sekali (idempoten).
$inEnabled = indexnowEnabled();
$inKey     = indexnowEnsureKey();
$inKeyUrl  = indexnowKeyLocation();
$inLast    = (string) getSetting('indexnow_last', '');
$inLastAt  = (int) getSetting('indexnow_last_at', '0');

$pdo       = getDB();
$enabled   = ingestEnabled();
$manage    = ingestManageEnabled();
$tokenSet  = ingestTokenIsSet();
$staff     = ingestStaffUsers($pdo);
$authorSel = (int) getSetting('ingest_author_id', '0');
$imagesOn  = ingestImagesEnabled();
$pexelsSet = trim((string) getSetting('pexels_api_key', '')) !== '';
$endpoint  = ingestEndpointUrl();
$mailTokenSet = scribeMailketingToken() !== '';
$mailSenderEmail = getSetting('mailketing_sender_email', '');
$mailSenderName = getSetting('mailketing_sender_name', blogName());
$mailList = getSetting('mailketing_subscriber_list_id', '');
$mailEndpoint = getSetting('mailketing_endpoint', 'https://api.mailketing.co.id/api/v1/send');
$mailLists = json_decode((string) getSetting('mailketing_lists_cache', '[]'), true);
$mailLists = is_array($mailLists) ? $mailLists : [];

// Token mentah baru — tampil SEKALI lalu buang dari sesi.
$tokenOnce = $_SESSION['ingest_token_once'] ?? '';
unset($_SESSION['ingest_token_once']);

admin_shell_top('Integrasi', '/admin/integrations');
settings_tabs('/admin/integrations');

$exampleToken = $tokenOnce !== '' ? $tokenOnce : 'TOKEN_ANDA';
$curl = "curl -X POST \"" . $endpoint . "\" \\\n"
      . "  -H \"Authorization: Bearer " . $exampleToken . "\" \\\n"
      . "  -H \"Content-Type: application/json\" \\\n"
      . "  -d '{\"title\":\"Judul contoh\",\"content_html\":\"<p>Isi artikel...</p>\",\"external_ref\":\"ext-123\",\"article_cta\":{\"mode\":\"custom\",\"title\":\"Pelajari lebih lanjut\",\"caption\":\"Buka panduan lengkap kami.\",\"label\":\"Lihat Panduan\",\"url\":\"https://contoh.com/panduan\",\"blank\":true}}'";
?>
<div class="max-w-3xl space-y-4">
  <form method="post" action="<?= e(url('/actions/admin/save-meta-pixel')) ?>" class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
    <?= csrfField() ?>
    <div><h2 class="font-display font-semibold">Meta Pixel</h2>
      <p class="text-sm text-gray-500 mt-1">Pasang beberapa Pixel ID untuk mengukur kunjungan Blog dan BioLink.</p></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="meta_pixel_enabled" value="1" <?= $metaPixel['enabled'] ? 'checked' : '' ?>> Aktifkan Meta Pixel</label>
    <div><label for="metaPixelIds" class="block text-sm font-medium mb-1">Pixel ID</label>
      <textarea id="metaPixelIds" name="meta_pixel_ids" rows="4" maxlength="1000" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm" placeholder="123456789012345&#10;987654321098765" aria-describedby="metaPixelHint"><?= e(implode("\n", $metaPixel['ids'])) ?></textarea>
      <p id="metaPixelHint" class="text-xs text-gray-500 mt-1">Satu ID per baris atau pisahkan dengan koma. Maksimal 20 ID. ID duplikat otomatis disatukan. Isi angka saja, bukan kode script.</p></div>
    <div><label for="metaPixelArea" class="block text-sm font-medium mb-1">Area tracking</label>
      <select id="metaPixelArea" name="meta_pixel_area" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
        <?php foreach (['both' => 'Blog dan BioLink', 'blog' => 'Blog saja', 'biolink' => 'BioLink saja'] as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $metaPixel['area'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select><p class="text-xs text-gray-500 mt-1">Homepage Hybrid termasuk Blog dan BioLink. Satu kunjungan tetap dihitung sekali per Pixel ID.</p></div>
    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="meta_pixel_cta" value="1" <?= $metaPixel['cta'] ? 'checked' : '' ?>> Lacak klik tombol BioLink, CTA header, CTA artikel, dan Popup Promo (CTAClick)</label>
    <p class="text-xs text-gray-500 leading-relaxed">PageView untuk kunjungan dan ViewContent untuk artikel. Memerlukan persetujuan pengunjung. Admin, pengguna yang login, dan preview tidak dilacak. URL dengan parameter selain nomor halaman atau fragmen tidak dilacak, termasuk bila berasal dari URL perujuk. Meta dapat menerima URL halaman dan informasi perangkat; nama, email, isi artikel, serta tujuan CTA tidak dikirim manual. Nonaktifkan pemasangan Pixel manual lain agar tidak terjadi tracking ganda.</p>
    <button type="submit" class="rounded-md px-4 py-2 text-sm font-medium text-white" style="background:var(--accent)">Simpan Meta Pixel</button>
  </form>

  <!-- Mailketing -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
    <div class="flex items-center gap-2"><span style="color:var(--accent)"><?= icon('mail', 'w-5 h-5') ?></span><h2 class="font-display font-semibold">Mailketing</h2>
      <?php if ($mailTokenSet): ?><span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Terhubung</span><?php else: ?><span class="text-xs text-gray-500">Belum diatur</span><?php endif; ?>
    </div>
    <p class="text-sm text-gray-500 dark:text-gray-400">Dipakai untuk memasukkan subscriber ke list dan mengirim email sequence terjadwal. Token hanya disimpan di settings lokal, tidak dikirim ke Hermes.</p>
    <form method="post" action="<?= e(url('/actions/admin/save-mailketing')) ?>" class="space-y-4">
      <?= csrfField() ?>
      <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="block text-xs font-medium mb-1">API token</label><input name="mailketing_api_token" type="password" autocomplete="new-password" placeholder="<?= $mailTokenSet ? '•••••••••• tersimpan — isi untuk mengganti' : 'Token Mailketing' ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono"></div>
        <div><label class="block text-xs font-medium mb-1">Email pengirim</label><input name="mailketing_sender_email" type="email" value="<?= e($mailSenderEmail) ?>" placeholder="noreply@domain.com" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Nama pengirim</label><input name="mailketing_sender_name" value="<?= e($mailSenderName) ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
        <div><label class="block text-xs font-medium mb-1">Endpoint API</label><input name="mailketing_endpoint" value="<?= e($mailEndpoint) ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono"></div>
      </div>
      <div class="grid sm:grid-cols-[1fr_auto] gap-2 items-end">
        <div><label class="block text-xs font-medium mb-1">List subscriber / lead magnet</label><select name="mailketing_subscriber_list_id" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"><option value="">— Tidak otomatis masuk list —</option><?php foreach ($mailLists as $ml): ?><option value="<?= e((string) ($ml['list_id'] ?? '')) ?>" <?= $mailList === (string) ($ml['list_id'] ?? '') ? 'selected' : '' ?>><?= e((string) ($ml['list_name'] ?? $ml['list_id'] ?? '')) ?> (<?= e((string) ($ml['list_id'] ?? '')) ?>)</option><?php endforeach; ?></select><p class="text-xs text-gray-400 mt-1">Muat daftar list setelah token disimpan.</p></div>
        <button type="submit" formaction="<?= e(url('/actions/admin/fetch-mailketing-lists')) ?>" class="rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Muat daftar list</button>
      </div>
      <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background:var(--accent)">Simpan Mailketing</button>
    </form>
    <div class="pt-3 border-t border-gray-200 dark:border-gray-800 flex flex-wrap items-end gap-2">
      <div><label class="block text-xs font-medium mb-1">Kirim email tes ke</label><input id="mailTestEmail" type="email" placeholder="email@anda.com" class="rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
      <button type="button" id="mailTestBtn" class="rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Tes kirim</button><span id="mailTestStatus" class="text-xs text-gray-500"></span>
    </div>
  </div>

  <?php if ($tokenOnce !== ''): ?>
  <div class="rounded-lg border-2 p-5" style="border-color:var(--accent);background:color-mix(in srgb, var(--accent) 7%, transparent)">
    <div class="flex items-start gap-2 mb-2">
      <span class="mt-0.5" style="color:var(--accent)"><?= icon('key', 'w-5 h-5') ?></span>
      <div>
        <h2 class="font-display font-semibold">Token baru dibuat</h2>
        <p class="text-sm text-gray-600 dark:text-gray-300">Salin sekarang — token <strong>tidak bisa dilihat ulang</strong>. Simpan di tempat aman (server hanya menyimpan hash-nya).</p>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <input id="tokenValue" readonly value="<?= e($tokenOnce) ?>" class="flex-1 min-w-[260px] rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm font-mono">
      <button type="button" data-copy="#tokenValue" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)"><?= icon('copy', 'w-4 h-4') ?> Salin token</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Ingest API -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
    <div class="flex items-center gap-2 mb-1">
      <span style="color:var(--accent)"><?= icon('link', 'w-5 h-5') ?></span>
      <h2 class="font-display font-semibold">Ingest API</h2>
      <?php if ($enabled): ?>
        <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400"><?= icon('check-circle', 'w-4 h-4') ?> Aktif</span>
      <?php else: ?>
        <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500"><?= icon('lock', 'w-4 h-4') ?> Nonaktif</span>
      <?php endif; ?>
    </div>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Izinkan sistem eksternal (mis. automation/AI Anda) mengirim artikel jadi ke blog. Ingest awal selalu masuk sebagai <strong>Draft AI</strong>. Jika izin manajemen aktif, sistem tepercaya juga dapat mengoptimasi dan menerbitkannya melalui endpoint kesehatan konten.</p>

    <form method="post" action="<?= e(url('/actions/admin/save-ingest')) ?>" class="space-y-4">
      <?= csrfField() ?>

      <label class="flex items-start gap-3 cursor-pointer">
        <input type="checkbox" name="ingest_enabled" value="1" <?= $enabled ? 'checked' : '' ?> <?= $tokenSet ? '' : 'disabled' ?> class="mt-0.5 h-4 w-4 rounded border-gray-300">
        <span>
          <span class="text-sm font-medium">Aktifkan Ingest API</span>
          <span class="block text-xs text-gray-500 dark:text-gray-400"><?= $tokenSet ? 'Endpoint menerima permintaan ber-token.' : 'Buat token dulu untuk bisa mengaktifkan.' ?></span>
        </span>
      </label>

      <div>
        <label class="block text-xs font-medium mb-1">Penulis default artikel masuk</label>
        <select name="ingest_author_id" class="rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm min-w-[240px]">
          <option value="0">— Otomatis (admin pertama) —</option>
          <?php foreach ($staff as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= $authorSel === (int) $u['id'] ? 'selected' : '' ?>>
              <?= e($u['name']) ?> (<?= e($u['role']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="pt-3 border-t border-gray-200 dark:border-gray-800">
        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="ingest_manage_enabled" value="1" <?= $manage ? 'checked' : '' ?> <?= $tokenSet ? '' : 'disabled' ?> class="mt-0.5 h-4 w-4 rounded border-gray-300">
          <span>
            <span class="text-sm font-medium">Izinkan manajemen &amp; publikasi</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400">Membuka endpoint <strong>tulis</strong> untuk kategori, CTA, setelan, optimasi isi, dan publikasi artikel. <strong class="text-amber-600 dark:text-amber-400">Perubahan dapat langsung LIVE</strong> — aktifkan hanya bila sistem eksternal Anda tepercaya. Butuh Ingest API aktif.</span>
          </span>
        </label>
      </div>

      <div class="pt-3 border-t border-gray-200 dark:border-gray-800 space-y-3">
        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="ingest_images_enabled" value="1" <?= $imagesOn ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 rounded border-gray-300">
          <span>
            <span class="text-sm font-medium">Gambar otomatis (cover &amp; inline)</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400">Izinkan sistem eksternal menambahkan cover &amp; gambar inline otomatis via provider legal <strong>Pexels</strong> (aset diunduh, dioptimasi ke WebP, dan disimpan lokal — bukan hotlink). <strong class="text-amber-600 dark:text-amber-400">Satu-satunya fitur yang mengunduh berkas dari internet</strong> — aktifkan hanya bila diperlukan. Gagal unduh tidak mengganggu artikel.</span>
          </span>
        </label>
        <div class="pl-7">
          <label class="block text-xs font-medium mb-1">Pexels API key <?php if ($pexelsSet): ?><span class="text-emerald-600 dark:text-emerald-400 font-semibold">· tersimpan</span><?php endif; ?></label>
          <input name="pexels_api_key" type="password" autocomplete="new-password" placeholder="<?= $pexelsSet ? '•••••••••• tersimpan — isi untuk mengganti' : 'Tempel API key Pexels' ?>" class="w-full max-w-md rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono">
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Dibuat gratis di <a href="https://www.pexels.com/api/" target="_blank" rel="noopener" class="underline" style="color:var(--accent)">pexels.com/api</a>. Disimpan lokal (hanya dipakai server untuk memanggil Pexels), tidak pernah dikirim ke Hermes.<?php if ($pexelsSet): ?> <label class="inline-flex items-center gap-1 ml-1"><input type="checkbox" name="pexels_clear" value="1" class="h-3.5 w-3.5"> Hapus key</label><?php endif; ?></p>
        </div>
      </div>

      <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan</button>
    </form>

    <div class="mt-5 pt-4 border-t border-gray-200 dark:border-gray-800">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-sm font-medium">Token akses</p>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            <?= $tokenSet
                ? 'Token aktif tersimpan (hanya hash — nilai asli tak tersimpan).'
                : 'Belum ada token.' ?>
          </p>
        </div>
        <form method="post" action="<?= e(url('/actions/admin/ingest-token')) ?>"
              <?= $tokenSet ? 'data-confirm="Putar ulang token? Token lama langsung tidak berlaku dan semua integrasi harus diperbarui." data-confirm-danger data-confirm-title="Putar ulang token"' : '' ?>>
          <?= csrfField() ?>
          <button type="submit" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
            <?= icon('key', 'w-4 h-4') ?> <?= $tokenSet ? 'Putar ulang token' : 'Generate token' ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Cara pakai -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
    <h2 class="font-display font-semibold mb-3">Cara pakai</h2>

    <label class="block text-xs font-medium mb-1">URL Endpoint</label>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <input id="endpointUrl" readonly value="<?= e($endpoint) ?>" class="flex-1 min-w-[260px] rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono">
      <button type="button" data-copy="#endpointUrl" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"><?= icon('copy', 'w-4 h-4') ?> Salin</button>
    </div>

    <label class="block text-xs font-medium mb-1">Contoh permintaan (curl)</label>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Contoh ini menyertakan CTA, sehingga toggle manajemen harus aktif.</p>
    <pre id="curlExample" class="rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-3 text-xs font-mono overflow-x-auto whitespace-pre"><?= e($curl) ?></pre>
    <button type="button" data-copy="#curlExample" class="mt-2 inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"><?= icon('copy', 'w-4 h-4') ?> Salin contoh</button>

    <div class="mt-5 text-sm text-gray-600 dark:text-gray-300 space-y-2">
      <p class="font-medium text-gray-800 dark:text-gray-100">Field JSON</p>
      <ul class="list-disc pl-5 space-y-1">
        <li><code>title</code> <span class="text-gray-400">(wajib)</span> — judul artikel.</li>
        <li><code>content_html</code> <span class="text-gray-400">(wajib)</span> — isi artikel (HTML; disanitasi otomatis).</li>
        <li><code>external_ref</code> <span class="text-gray-400">(disarankan)</span> — id unik dari sistem Anda; anti-dobel (kirim ulang = artikel yang sama).</li>
        <li><code>excerpt</code>, <code>focus_keyword</code>, <code>related_keywords</code> (teks atau array), <code>meta_title</code>, <code>meta_description</code>, <code>slug</code>.</li>
        <li><code>search_intent</code> — informational | transactional | navigational.</li>
        <li><code>language</code> — id | en | ms.</li>
        <li><code>category</code> — nama/slug kategori yang sudah ada (jika tak cocok, dikosongkan).</li>
        <li><code>tags</code> — array nama tag (dibuat otomatis bila belum ada).</li>
        <li><code>article_cta</code> — object CTA opsional (<code>mode</code>, <code>title</code>, <code>caption</code>, <code>label</code>, <code>url</code>, <code>blank</code>). <strong>Butuh manajemen aktif.</strong></li>
        <li><code>cover</code> — object gambar sampul opsional: <code>mode</code> (<code>auto</code>|<code>url</code>|<code>none</code>), <code>url</code> (wajib saat mode=url), <code>query</code> (opsional override pencarian). Rasio 1200×630. <strong>Butuh "Gambar otomatis" aktif.</strong></li>
        <li><code>images</code> — object gambar inline opsional: <code>mode</code> (<code>auto</code>|<code>manual</code>|<code>none</code>); <code>count</code> (auto, maks 6) atau <code>items</code> (manual: array <code>{url, after_h2, alt}</code>). Disisipkan setelah H2 relevan, alt otomatis. <strong>Butuh "Gambar otomatis" aktif.</strong></li>
        <li><code>dry_run</code> — boolean. Bila <code>true</code>, kembalikan rencana (query, cover, posisi inline) <strong>tanpa</strong> membuat file atau artikel.</li>
      </ul>
      <p class="text-xs text-gray-500 dark:text-gray-400 pt-1">Respon sukses: <code>{ "ok": true, "article_id", "slug", "status": "draft_ai", "edit_url" }</code>. Semua artikel masuk berstatus Draft AI dan perlu Anda tinjau + terbitkan manual.</p>

      <p class="font-medium text-gray-800 dark:text-gray-100 pt-3">Endpoint manajemen (token sama)</p>
      <ul class="list-disc pl-5 space-y-1">
        <li><code>GET/POST /api/categories.php</code> — daftar / buat / ubah kategori. <span class="text-gray-400">POST butuh manajemen.</span></li>
        <li><code>GET/POST /api/settings.php</code> — baca / ubah setelan SEO &amp; brand (whitelist ketat). <span class="text-gray-400">POST butuh manajemen, LIVE.</span></li>
        <li><code>GET /api/articles.php?limit=&amp;offset=</code> — daftar artikel terbaru (baca).</li>
        <li><code>GET/POST /api/content-health.php</code> — audit, optimasi isi/SEO, FAQ approved, dan publikasi artikel. <span class="text-gray-400">POST butuh manajemen dan skor minimal 80 untuk konten LIVE.</span></li>
        <li><code>GET/POST /api/article-cta.php</code> — baca / ubah CTA global, kategori, atau artikel. <span class="text-gray-400">POST butuh manajemen.</span></li>
        <li><code>GET/POST /api/email-sequences.php</code> — baca atau isi sequence subscriber dan seluruh step email HTML. <span class="text-gray-400">POST butuh manajemen.</span></li>
        <li><code>GET /api/seo-rules.php</code> — ruleset SEO aktif (baca), untuk menyelaraskan artikel ke checklist.</li>
      </ul>
      <p class="text-xs text-gray-500 dark:text-gray-400 pt-1">Alur Hermes: audit artikel → ambil isi lengkap → kirim optimasi ke <code>POST /api/content-health.php</code> dengan <code>expected_updated_at</code>. Field opsional <code>faq</code> menerima maksimal 20 item <code>{"question":"...","answer":"..."}</code>; seluruh item mengganti FAQ lama dan langsung approved. Kirim <code>dry_run: true</code> untuk validasi tanpa menulis. Gunakan <code>publish: true</code> untuk menerbitkan otomatis setelah skor mencapai minimal 80.</p>
      <p class="text-xs text-gray-500 dark:text-gray-400 pt-1">Untuk email sequence, Hermes dapat mengirim <code>name</code>, <code>lead_magnet_id</code>, <code>status</code>, dan array <code>steps</code> berisi <code>delay_days</code>, <code>subject</code>, <code>body</code> HTML, serta <code>body_format: "html"</code>. Placeholder yang tersedia: <code>{{name}}</code>, <code>{{email}}</code>, <code>{{blog_name}}</code>, <code>{{article_title}}</code>, <code>{{article_url}}</code>, <code>{{lead_magnet_title}}</code>, <code>{{lead_magnet_url}}</code>, dan <code>{{unsubscribe_url}}</code>. Pengiriman tetap melalui antrean Mailketing dan subscriber dapat berhenti berlangganan.</p>
      <p class="text-xs text-gray-500 dark:text-gray-400 pt-1">Endpoint baca butuh Ingest API aktif. Endpoint tulis butuh toggle <strong>manajemen &amp; publikasi</strong> di atas — optimasi artikel published dan perintah <code>publish: true</code> dapat langsung tayang.</p>
      <p class="text-xs text-gray-500 dark:text-gray-400">Catatan hosting: sebagian server tidak meneruskan header <code>Authorization</code> ke PHP. Bila selalu mendapat 401 padahal token benar, tambahkan <code>CGIPassAuth On</code> (atau aturan setara) di konfigurasi server.</p>
    </div>
  </div>

  <!-- IndexNow -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
    <div class="flex items-center gap-2 mb-1">
      <span style="color:var(--accent)"><?= icon('send', 'w-5 h-5') ?></span>
      <h2 class="font-display font-semibold">IndexNow (indeks cepat)</h2>
      <?php if ($inEnabled): ?>
        <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400"><?= icon('check-circle', 'w-4 h-4') ?> Aktif</span>
      <?php else: ?>
        <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500"><?= icon('lock', 'w-4 h-4') ?> Nonaktif</span>
      <?php endif; ?>
    </div>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Saat artikel <strong>terbit atau diperbarui</strong>, URL-nya otomatis dikirim ke mesin pencari (Bing, Yandex, Seznam, dll) agar cepat terindeks — tanpa menunggu crawl rutin. Berlaku juga untuk artikel yang diterbitkan via API/Hermes. Google tidak memakai IndexNow, tetapi tetap menemukan artikel lewat sitemap.</p>

    <form method="post" action="<?= e(url('/actions/admin/save-indexnow')) ?>" class="space-y-4">
      <?= csrfField() ?>
      <label class="flex items-start gap-3 cursor-pointer">
        <input type="checkbox" name="indexnow_enabled" value="1" <?= $inEnabled ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 rounded border-gray-300">
        <span>
          <span class="text-sm font-medium">Aktifkan IndexNow</span>
          <span class="block text-xs text-gray-500 dark:text-gray-400">Ping otomatis saat publish/update. Aman: kegagalan ping tidak mengganggu penyimpanan artikel.</span>
        </span>
      </label>
      <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan</button>
    </form>

    <div class="mt-5 pt-4 border-t border-gray-200 dark:border-gray-800 space-y-3">
      <div>
        <label class="block text-xs font-medium mb-1">File verifikasi (disajikan otomatis)</label>
        <div class="flex flex-wrap items-center gap-2">
          <input id="inKeyUrl" readonly value="<?= e($inKeyUrl) ?>" class="flex-1 min-w-[260px] rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono">
          <a href="<?= e($inKeyUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"><?= icon('external-link', 'w-4 h-4') ?> Buka</a>
          <button type="button" data-copy="#inKeyUrl" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"><?= icon('copy', 'w-4 h-4') ?> Salin</button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Situs menyajikan file ini otomatis (tidak perlu upload). Mesin pencari memakainya untuk verifikasi kepemilikan domain.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <form method="post" action="<?= e(url('/actions/admin/indexnow-test')) ?>">
          <?= csrfField() ?>
          <button type="submit" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"><?= icon('refresh-cw', 'w-4 h-4') ?> Tes ping (beranda)</button>
        </form>
        <?php if ($inLastAt > 0): ?>
          <span class="text-xs text-gray-500 dark:text-gray-400">Ping terakhir: <?= e($inLast) ?> · <?= e(formatTanggal(date('Y-m-d H:i:s', $inLastAt), true)) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var el = document.querySelector(btn.getAttribute('data-copy'));
    if (!el) return;
    var text = ('value' in el) ? el.value : el.textContent;
    var done = function () {
      var orig = btn.innerHTML;
      btn.innerHTML = 'Tersalin';
      setTimeout(function () { btn.innerHTML = orig; }, 1400);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {});
    } else if (el.select) {
      el.select(); try { document.execCommand('copy'); done(); } catch (e) {}
    }
  });
});
</script>
<script>
(function(){var b=document.getElementById('mailTestBtn');if(!b)return;b.addEventListener('click',function(){var email=document.getElementById('mailTestEmail').value.trim(),s=document.getElementById('mailTestStatus');if(!email){s.textContent='Isi email tujuan.';return;}b.disabled=true;s.textContent='Mengirim…';var fd=new FormData();fd.append('csrf_token',<?= json_encode(generateCSRF()) ?>);fd.append('email',email);fetch(<?= json_encode(url('/actions/admin/test-mailketing')) ?>,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){s.textContent=d.message||'Selesai.';s.className='text-xs '+(d.ok?'text-emerald-600':'text-red-600')}).catch(function(){s.textContent='Gagal menghubungi server.';s.className='text-xs text-red-600'}).finally(function(){b.disabled=false;});});})();
</script>
<?php admin_shell_bottom(); ?>
