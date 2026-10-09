<?php
// Meta Pixel: konfigurasi global, tanpa skrip mentah dari pengaturan.
function metaPixelIds(string $input): array
{
    if (strlen($input) > 1000) throw new InvalidArgumentException('Daftar Pixel ID terlalu panjang (maksimal 1.000 karakter).');
    $ids = preg_split('/[\s,;]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($ids as $id) {
        if (!preg_match('/^[1-9][0-9]{4,29}$/D', $id)) {
            throw new InvalidArgumentException('Pixel ID harus berupa 5–30 digit angka, tanpa kode script.');
        }
    }
    $ids = array_values(array_unique($ids));
    if (count($ids) > 20) throw new InvalidArgumentException('Maksimal 20 Pixel ID.');
    return $ids;
}

function metaPixelConfig(): array
{
    try { $ids = metaPixelIds((string) getSetting('meta_pixel_ids', '')); }
    catch (InvalidArgumentException $e) { $ids = []; }
    return [
        'enabled' => getSetting('meta_pixel_enabled', '0') === '1',
        'ids' => $ids,
        'area' => (string) getSetting('meta_pixel_area', 'both'),
        'cta' => getSetting('meta_pixel_cta', '0') === '1',
    ];
}

function metaPixelPage(array $config): ?string
{
    if (!$config['enabled'] || !$config['ids'] || isLoggedIn() || isset($_GET['preview'])
        || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
        || (http_response_code() && http_response_code() !== 200)) return null;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $base = rtrim((string) parse_url(APP_URL, PHP_URL_PATH), '/');
    if ($base !== '' && !str_starts_with($path, $base . '/')) return null;
    $path = '/' . ltrim(substr($path, strlen($base)), '/');
    if (!preg_match('#^/(?:blog/?|artikel/[^/]+/?|kategori/[^/]+/?|tag/[^/]+/?|search/?)?$#D', $path)) return null;
    $area = $config['area'];
    if (!in_array($area, ['blog', 'biolink', 'both'], true)) return null;
    $mode = $path === '/' ? getSetting('home_mode', 'blog') : 'blog';
    if ($area !== 'both' && $mode !== 'hybrid' && $mode !== $area) return null;
    return str_starts_with($path, '/artikel/') ? 'article' : 'page';
}

function metaPixelRender(): void
{
    $config = metaPixelConfig();
    $page = metaPixelPage($config);
    if ($page === null) return;
    $config['page'] = $page;
    $config['storageKey'] = 'scribe_meta_consent_' . substr(hash('sha256', APP_URL), 0, 12);
    $sorted = $config['ids']; sort($sorted, SORT_STRING);
    $config['signature'] = hash('sha256', implode(',', $sorted) . '|' . $config['area'] . '|' . (int) $config['cta']);
    ?>
<style>
.meta-consent[hidden],.meta-preferences[hidden]{display:none!important}
.meta-preferences{cursor:pointer;border:1px solid transparent;border-radius:6px;padding:.3rem .45rem;margin:-.3rem -.45rem;background:transparent;transition:color .16s ease,background .16s ease,border-color .16s ease}
.meta-preferences:hover{color:var(--accent,#2563eb);background:color-mix(in srgb,var(--accent,#2563eb) 7%,transparent);border-color:color-mix(in srgb,var(--accent,#2563eb) 18%,transparent)}
.meta-consent{position:fixed;z-index:10000;bottom:max(16px,env(safe-area-inset-bottom));left:16px;width:min(390px,calc(100vw - 32px));overflow:hidden;padding:18px;border:1px solid color-mix(in srgb,var(--accent,#2563eb) 18%,#d1d5db);border-radius:8px;background:color-mix(in srgb,var(--accent,#2563eb) 2%,#fff);color:#1f2937;box-shadow:0 22px 55px -24px rgba(15,23,42,.45),0 5px 18px rgba(15,23,42,.08);font-size:13px;line-height:1.6;-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px)}
.meta-consent::before{content:"";position:absolute;inset:0 0 auto;height:2px;background:linear-gradient(90deg,var(--accent,#2563eb),color-mix(in srgb,var(--accent,#2563eb) 35%,transparent),transparent)}
.dark .meta-consent,body[data-ui-theme="noir"] .meta-consent{background:color-mix(in srgb,var(--accent,#2563eb) 5%,#111827);color:#e5e7eb;border-color:color-mix(in srgb,var(--accent,#2563eb) 30%,#374151)}
.meta-consent-head{display:flex;align-items:center;gap:9px;margin:0 0 7px}.meta-consent-icon{display:inline-flex;width:28px;height:28px;flex:none;align-items:center;justify-content:center;border-radius:6px;background:color-mix(in srgb,var(--accent,#2563eb) 10%,transparent);color:var(--accent,#2563eb)}.meta-consent-icon svg{width:15px;height:15px}.meta-consent h2{font-size:15px;font-weight:650;letter-spacing:-.01em;margin:0}.meta-consent p{margin:0 0 14px;color:#6b7280}.dark .meta-consent p,body[data-ui-theme="noir"] .meta-consent p{color:#9ca3af}
.meta-consent-actions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}.meta-consent button{padding:7px 13px;border:1px solid #d1d5db;border-radius:6px;background:transparent;color:inherit;font:inherit;font-weight:600;cursor:pointer;transition:background .16s ease,border-color .16s ease,transform .16s ease}.meta-consent button:hover{background:color-mix(in srgb,var(--accent,#2563eb) 7%,transparent);border-color:color-mix(in srgb,var(--accent,#2563eb) 36%,#d1d5db);transform:translateY(-1px)}.meta-consent button:last-child{background:var(--accent,#2563eb);color:white;border-color:transparent}.meta-consent button:last-child:hover{background:color-mix(in srgb,var(--accent,#2563eb) 88%,#000)}.meta-consent button:focus-visible,.meta-preferences:focus-visible{outline:2px solid var(--accent,#2563eb);outline-offset:3px}
@media (prefers-reduced-motion:reduce){.meta-consent button,.meta-preferences{transition:none}.meta-consent button:hover{transform:none}}
</style>
<section id="metaConsent" class="meta-consent" role="region" aria-labelledby="metaConsentTitle" aria-describedby="metaConsentText" hidden>
 <div class="meta-consent-head"><span class="meta-consent-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg></span><h2 id="metaConsentTitle">Preferensi privasi</h2></div>
 <p id="metaConsentText">Situs ini menggunakan Meta Pixel untuk mengukur kunjungan dan interaksi iklan. Jika Anda setuju, informasi kunjungan dikirim ke Meta. Anda dapat mengubah pilihan kapan saja.</p>
 <div class="meta-consent-actions"><button type="button" id="metaReject">Tolak</button><button type="button" id="metaAccept">Setuju</button></div>
</section>
<script type="application/json" id="metaPixelConfig"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(url('/assets/js/meta-pixel.js')) ?>" defer></script>
    <?php
}
