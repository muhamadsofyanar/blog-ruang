<?php
require_once __DIR__ . '/media.php';
// ════════════════════════════════════════════════════════════════════════
// Biolink (link-in-bio) untuk homepage theme-default. Profil disimpan di
// settings (grup biolink, pola rebrand); blok tersusun di tabel bio_blocks.
// Mode homepage: blog (default) | biolink | hybrid. Semua output di-escape.
// ════════════════════════════════════════════════════════════════════════

/** Tipe blok yang didukung MVP. */
const BIO_BLOCK_TYPES = ['button', 'social', 'text', 'image', 'divider'];

/** Mode homepage tervalidasi. */
function bioHomeMode(): string
{
    $m = (string) getSetting('home_mode', 'blog');
    return in_array($m, ['blog', 'biolink', 'hybrid'], true) ? $m : 'blog';
}

/** Profil biolink dari settings (fallback ke identitas brand). */
function bioProfile(): array
{
    $style          = (string) getSetting('bio_header_style', 'gradient');
    $introAnimation = (string) getSetting('bio_intro_animation', 'typewriter');
    $introSpeed     = max(10, min(200, (int) getSetting('bio_intro_speed', '35')));
    return [
        'name'         => (getSetting('bio_display_name', '') ?: blogName()),
        'bio'          => (string) getSetting('bio_text', ''),
        'avatar'       => (string) getSetting('bio_avatar', ''),
        'header_style' => in_array($style, ['gradient', 'solid', 'image'], true) ? $style : 'gradient',
        'header_bg'    => (string) getSetting('bio_header_bg', ''),
        'intro'        => [
            'enabled'   => getSetting('bio_intro_enabled', '0') === '1',
            'button'    => trim((string) getSetting('bio_intro_button', '')) ?: 'Kenalan dengan Saya',
            'title'     => trim((string) getSetting('bio_intro_title', '')) ?: 'Perkenalan',
            'text'      => trim((string) getSetting('bio_intro_text', '')),
            'photo'     => (string) getSetting('bio_intro_photo', ''),
            'animation' => in_array($introAnimation, ['typewriter', 'fade', 'normal'], true) ? $introAnimation : 'typewriter',
            'speed'     => $introSpeed,
            'cta_label' => trim((string) getSetting('bio_intro_cta_label', '')),
            'cta_url'   => bioSafeUrl((string) getSetting('bio_intro_cta_url', '')),
            'cta_blank' => getSetting('bio_intro_cta_blank', '0') === '1',
        ],
    ];
}

/**
 * Daftar blok. $activeOnly=true untuk render publik (hanya aktif). config_json
 * di-decode ke $row['cfg'] (array). Urut sort_order lalu id.
 */
function bioBlocks(bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT * FROM bio_blocks' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, id ASC';
        $rows = getDB()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        error_log('bioBlocks: ' . $e->getMessage());
        return [];
    }
    foreach ($rows as &$r) {
        $cfg = json_decode((string) ($r['config_json'] ?? ''), true);
        $r['cfg'] = is_array($cfg) ? $cfg : [];
    }
    return $rows;
}

/** Ada konten biolink untuk ditampilkan? (profil terisi atau ada blok aktif) */
function bioHasContent(): bool
{
    $p = bioProfile();
    if (trim($p['bio']) !== '' || $p['avatar'] !== '') return true;
    $intro = $p['intro'] ?? [];
    if (!empty($intro['enabled']) && trim((string) ($intro['text'] ?? '')) !== '') return true;
    return bioBlocks(true) !== [];
}

/** URL aman untuk link biolink (http/https/mailto/tel saja). */
function bioSafeUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (preg_match('#^(https?:)?//#i', $url) || preg_match('#^(mailto:|tel:)#i', $url)) return $url;
    // Tanpa skema → anggap https eksternal.
    return 'https://' . ltrim($url, '/');
}

/** Jaringan sosial yang didukung: key => [label, tipe url]. */
function bioSocialNetworks(): array
{
    return [
        'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube',
        'tiktok'    => 'TikTok',    'x' => 'X (Twitter)',      'linkedin' => 'LinkedIn',
        'whatsapp'  => 'WhatsApp',  'telegram' => 'Telegram',  'website' => 'Website',
        'email'     => 'Email',
    ];
}

/** SVG ikon sosial (24x24). Brand = fill currentColor; website/email = outline. */
function bioSocialIconSvg(string $net): string
{
    $paths = [
        'instagram' => '<path fill="currentColor" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163C8.741 0 8.332.014 7.052.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>',
        'facebook'  => '<path fill="currentColor" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>',
        'youtube'   => '<path fill="currentColor" d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>',
        'tiktok'    => '<path fill="currentColor" d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>',
        'x'         => '<path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
        'linkedin'  => '<path fill="currentColor" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.225 0z"/>',
        'whatsapp'  => '<path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>',
        'telegram'  => '<path fill="currentColor" d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>',
        'website'   => '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20" fill="none" stroke="currentColor" stroke-width="2"/>',
        'email'     => '<rect x="2" y="4" width="20" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="m2 7 10 6 10-6" fill="none" stroke="currentColor" stroke-width="2"/>',
    ];
    return $paths[$net] ?? '<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>';
}

/**
 * URL sosial per jaringan. Menerima URL penuh ATAU handle telanjang:
 *   email → mailto, whatsapp → wa.me (angka), lainnya → base platform + handle.
 * Bila value sudah berupa URL (ada skema/slash/domain) dipakai apa adanya.
 */
function bioSocialUrl(string $net, string $val): string
{
    $val = trim($val);
    if ($val === '') return '';
    if ($net === 'email') {
        return str_contains($val, '@') ? 'mailto:' . $val : bioSafeUrl($val);
    }
    if ($net === 'whatsapp') {
        $digits = preg_replace('/\D/', '', $val);
        if ($digits !== '' && !preg_match('#https?://#i', $val)) return 'https://wa.me/' . $digits;
        return bioSafeUrl($val);
    }
    if ($net === 'website') return bioSafeUrl($val);

    // Handle telanjang (tanpa skema, slash, atau domain) → petakan ke base platform.
    $bases = [
        'instagram' => 'https://instagram.com/', 'tiktok' => 'https://tiktok.com/@',
        'x'         => 'https://x.com/',          'facebook' => 'https://facebook.com/',
        'youtube'   => 'https://youtube.com/@',   'linkedin' => 'https://linkedin.com/in/',
        'telegram'  => 'https://t.me/',
    ];
    $looksLikeUrl = preg_match('#^https?://#i', $val) || str_contains($val, '/') || preg_match('/\.[a-z]{2,}$/i', $val);
    if (isset($bases[$net]) && !$looksLikeUrl) {
        return $bases[$net] . ltrim($val, '@/');
    }
    return bioSafeUrl($val);
}

// ─── RENDER ────────────────────────────────────────────────────────────────

/** Header profil biolink (band bg + avatar overlap + nama + bio). */
function bioRenderHeader(array $p): void
{
    $up = rtrim(UPLOAD_URL, '/');
    $style = $p['header_style'];
    $hasImage = $style === 'image' && $p['header_bg'] !== '';
    if ($style === 'solid' && preg_match('/^#[0-9a-fA-F]{6}$/', $p['header_bg'])) {
        $bg = 'background:' . e($p['header_bg']);
    } elseif ($hasImage) {
        $bg = 'background:color-mix(in srgb, var(--accent) 20%, #111827)';
    } else {
        $bg = "background:radial-gradient(60% 90% at 80% 0%, color-mix(in srgb, var(--accent) 45%, transparent), transparent 60%), linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 55%, #000))";
    }
    $initial = strtoupper(mb_substr($p['name'], 0, 1));
    ?>
  <section class="relative">
    <div class="h-40 sm:h-48 w-full relative overflow-hidden" style="<?= $bg ?>">
      <?php if ($hasImage): ?>
        <?= scribeUploadImageHtml($p['header_bg'], '', 'absolute inset-0 w-full h-full object-cover', '100vw', true) ?>
        <div class="absolute inset-0 bg-black/30"></div>
      <?php endif; ?>
    </div>
    <div class="relative z-10 max-w-xl mx-auto px-4">
      <div class="-mt-14 flex flex-col items-center text-center">
        <div class="w-28 h-28 rounded-full ring-4 ring-white dark:ring-gray-950 overflow-hidden bg-gray-100 dark:bg-gray-800 shadow-lg flex items-center justify-center shrink-0">
          <?php if ($p['avatar'] !== ''): ?>
            <?= scribeUploadImageHtml($p['avatar'], $p['name'], 'w-full h-full object-cover', '112px', true) ?>
          <?php else: ?>
            <span class="font-display font-bold text-3xl text-white" style="background:var(--accent);width:100%;height:100%;display:flex;align-items:center;justify-content:center"><?= e($initial) ?></span>
          <?php endif; ?>
        </div>
        <h1 class="mt-4 font-display font-bold text-2xl sm:text-3xl leading-tight"><?= e($p['name']) ?></h1>
        <?php if (trim($p['bio']) !== ''): ?>
        <p class="mt-2 text-[15px] text-gray-600 dark:text-gray-300 max-w-md leading-relaxed"><?= nl2br(e($p['bio'])) ?></p>
        <?php endif; ?>
        <?php $intro = $p['intro'] ?? []; if (!empty($intro['enabled']) && trim((string) ($intro['text'] ?? '')) !== ''): ?>
        <button type="button" data-bio-intro-open aria-haspopup="dialog" aria-controls="bioIntroDialog"
                class="bio-intro-trigger mt-4 inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold transition">
          <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
          <span><?= e((string) $intro['button']) ?></span>
        </button>
        <?php endif; ?>
      </div>
    </div>
  </section>
    <?php
}

/** Panel perkenalan: modal desktop, bottom sheet mobile. */
function bioRenderIntroduction(array $p): void
{
    $intro = $p['intro'] ?? [];
    if (empty($intro['enabled']) || trim((string) ($intro['text'] ?? '')) === '') return;

    $photo     = trim((string) ($intro['photo'] ?? ''));
    $ctaLabel  = trim((string) ($intro['cta_label'] ?? ''));
    $ctaUrl    = bioSafeUrl((string) ($intro['cta_url'] ?? ''));
    $ctaBlank  = !empty($intro['cta_blank']);
    $animation = in_array($intro['animation'] ?? '', ['typewriter', 'fade', 'normal'], true) ? $intro['animation'] : 'typewriter';
    $speed     = max(10, min(200, (int) ($intro['speed'] ?? 35)));
    ?>
  <div id="bioIntroDialog" class="bio-intro-root" hidden aria-hidden="true">
    <button type="button" class="bio-intro-backdrop" data-bio-intro-close tabindex="-1" aria-label="Tutup perkenalan"></button>
    <section class="bio-intro-panel" role="dialog" aria-modal="true" aria-labelledby="bioIntroTitle" data-bio-intro-panel>
      <div class="bio-intro-accent" aria-hidden="true"></div>
      <button type="button" class="bio-intro-close" data-bio-intro-close aria-label="Tutup perkenalan">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
      <div class="bio-intro-content<?= $photo !== '' ? ' has-photo' : '' ?>">
        <?php if ($photo !== ''): ?>
        <div class="bio-intro-photo-wrap">
          <?= scribeUploadImageHtml($photo, '', 'bio-intro-photo', '(max-width: 639px) 100vw, 240px') ?>
        </div>
        <?php endif; ?>
        <div class="bio-intro-copy">
          <span class="bio-intro-kicker">Tentang</span>
          <h2 id="bioIntroTitle" class="bio-intro-title"><?= e((string) ($intro['title'] ?? 'Perkenalan')) ?></h2>
          <p class="sr-only"><?= nl2br(e((string) $intro['text'])) ?></p>
          <p class="bio-intro-text" data-bio-intro-text aria-hidden="true"></p>
          <div hidden data-bio-intro-source><?= e((string) $intro['text']) ?></div>
          <?php if ($ctaLabel !== '' && $ctaUrl !== ''): ?>
          <a href="<?= e($ctaUrl) ?>" class="bio-intro-cta"<?= $ctaBlank ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
            <span><?= e($ctaLabel) ?></span>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>
  <style>
  .bio-intro-trigger{border:1px solid color-mix(in srgb,var(--accent) 38%,transparent);border-radius:var(--fe-btn-radius,6px);color:var(--fe-accent-text);background:color-mix(in srgb,var(--accent) 5%,transparent);box-shadow:0 5px 18px -14px color-mix(in srgb,var(--accent) 70%,transparent)}
  .bio-intro-trigger:hover{background:color-mix(in srgb,var(--accent) 11%,transparent);border-color:color-mix(in srgb,var(--accent) 58%,transparent);transform:translateY(-1px)}
  .bio-intro-trigger:focus-visible,.bio-intro-close:focus-visible,.bio-intro-cta:focus-visible{outline:2px solid var(--accent);outline-offset:3px}
  .bio-intro-root{position:fixed;inset:0;z-index:90;display:none;align-items:center;justify-content:center;padding:1.25rem}
  .bio-intro-root.is-open{display:flex}
  .bio-intro-backdrop{position:absolute;inset:0;border:0;background:rgba(2,6,23,.62);-webkit-backdrop-filter:blur(5px);backdrop-filter:blur(5px)}
  .bio-intro-panel{position:relative;width:min(42rem,100%);max-height:min(84dvh,46rem);overflow:auto;color:inherit;background:var(--surface-bg,#fff);border:var(--border-w,1px) solid var(--border-color,rgba(15,23,42,.1));border-radius:var(--card-radius,8px);box-shadow:0 30px 90px -28px rgba(2,6,23,.65);-webkit-backdrop-filter:var(--surface-blur,none);backdrop-filter:var(--surface-blur,none);animation:bio-intro-pop .22s cubic-bezier(.2,.8,.2,1)}
  html.dark .bio-intro-panel{background:var(--surface-bg-dark,#111827);border-color:var(--border-color-dark,rgba(255,255,255,.1))}
  .bio-intro-panel{scrollbar-width:thin;scrollbar-color:color-mix(in srgb,var(--accent) 16%,#94a3b8) transparent}
  .bio-intro-panel::-webkit-scrollbar{width:7px;height:7px}
  .bio-intro-panel::-webkit-scrollbar-track{background:transparent;border-radius:8px}
  .bio-intro-panel::-webkit-scrollbar-thumb{min-height:42px;background:color-mix(in srgb,var(--accent) 16%,#94a3b8);background-clip:padding-box;border:2px solid transparent;border-radius:8px;transition:background-color .18s ease}
  .bio-intro-panel::-webkit-scrollbar-thumb:hover{background:color-mix(in srgb,var(--accent) 30%,#64748b);background-clip:padding-box}
  html.dark .bio-intro-panel{scrollbar-color:color-mix(in srgb,var(--accent) 24%,#64748b) transparent}
  html.dark .bio-intro-panel::-webkit-scrollbar-track{background:transparent}
  html.dark .bio-intro-panel::-webkit-scrollbar-thumb{background:color-mix(in srgb,var(--accent) 24%,#64748b);background-clip:padding-box}
  html.dark .bio-intro-panel::-webkit-scrollbar-thumb:hover{background:color-mix(in srgb,var(--accent) 38%,#94a3b8);background-clip:padding-box}
  .bio-intro-accent{height:3px;background:linear-gradient(90deg,var(--accent),color-mix(in srgb,var(--accent) 34%,transparent))}
  .bio-intro-close{position:absolute;z-index:2;top:.8rem;right:.8rem;display:grid;place-items:center;width:2rem;height:2rem;border:1px solid var(--border-color,rgba(15,23,42,.1));border-radius:var(--fe-btn-radius,6px);color:#64748b;background:color-mix(in srgb,var(--surface-bg,#fff) 88%,transparent);transition:color .18s ease,border-color .18s ease,background .18s ease}
  .bio-intro-close:hover{color:var(--fe-accent-text);border-color:color-mix(in srgb,var(--accent) 38%,transparent);background:color-mix(in srgb,var(--accent) 7%,var(--surface-bg,#fff))}
  html.dark .bio-intro-close{color:#cbd5e1;background:color-mix(in srgb,var(--surface-bg-dark,#111827) 88%,transparent);border-color:var(--border-color-dark,rgba(255,255,255,.1))}
  .bio-intro-content{display:grid;padding:1.65rem}
  .bio-intro-content.has-photo{grid-template-columns:minmax(0,13rem) minmax(0,1fr);gap:1.5rem;align-items:start}
  .bio-intro-photo-wrap{overflow:hidden;aspect-ratio:4/5;border-radius:calc(var(--card-radius,8px) - 2px);background:color-mix(in srgb,var(--accent) 8%,#e2e8f0)}
  .bio-intro-photo{width:100%;height:100%;object-fit:cover}
  .bio-intro-copy{min-width:0;padding:.2rem .15rem}
  .bio-intro-kicker{display:block;margin-bottom:.45rem;color:var(--fe-accent-text);font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase}
  .bio-intro-title{padding-right:2.4rem;font-family:var(--font-display,inherit);font-size:clamp(1.35rem,3vw,1.75rem);font-weight:var(--fe-head-weight,700);line-height:1.2;letter-spacing:var(--fe-head-tracking,-.01em)}
  .bio-intro-text{min-height:4.5rem;margin-top:.85rem;color:#475569;font-size:.95rem;line-height:1.75;white-space:pre-line}
  html.dark .bio-intro-text{color:#cbd5e1}
  body[data-ui-theme="noir"] .bio-intro-text{color:#d8cbb9}
  .bio-intro-text.is-fading{animation:bio-intro-fade .42s ease both}
  .bio-intro-cta{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;margin-top:1.2rem;padding:.66rem .9rem;border-radius:var(--fe-btn-radius,6px);color:#fff;background:var(--accent);font-size:.875rem;font-weight:700;line-height:1.2;transition:opacity .18s ease,transform .18s ease}
  .bio-intro-cta:hover{opacity:.9;transform:translateY(-1px)}
  html.bio-intro-open{overflow:hidden}
  @keyframes bio-intro-pop{from{opacity:0;transform:translateY(10px) scale(.985)}to{opacity:1;transform:none}}
  @keyframes bio-intro-fade{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}
  @media(max-width:639px){
    .bio-intro-root{align-items:flex-end;padding:0}
    .bio-intro-panel{width:100%;max-height:88dvh;border-radius:10px 10px 0 0;animation-name:bio-intro-sheet}
    .bio-intro-panel::-webkit-scrollbar{width:5px;height:5px}
    .bio-intro-panel::-webkit-scrollbar-thumb{border-width:1px}
    .bio-intro-content,.bio-intro-content.has-photo{grid-template-columns:1fr;gap:1rem;padding:1.25rem 1.1rem calc(1.35rem + env(safe-area-inset-bottom))}
    .bio-intro-photo-wrap{width:100%;max-height:13rem;aspect-ratio:16/9}
    .bio-intro-title{font-size:1.35rem}
    .bio-intro-text{min-height:3.8rem;font-size:.925rem;line-height:1.7}
    .bio-intro-cta{width:100%}
  }
  @keyframes bio-intro-sheet{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:none}}
  @media(prefers-reduced-motion:reduce){.bio-intro-panel,.bio-intro-text.is-fading{animation:none!important}.bio-intro-trigger,.bio-intro-cta{transition:none!important}}
  </style>
  <script>
  (function () {
    var root = document.getElementById('bioIntroDialog');
    if (!root) return;
    var panel = root.querySelector('[data-bio-intro-panel]');
    var output = root.querySelector('[data-bio-intro-text]');
    var source = root.querySelector('[data-bio-intro-source]').textContent || '';
    var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-bio-intro-open]'));
    var animation = <?= json_encode($animation, JSON_UNESCAPED_SLASHES) ?>;
    var speed = <?= $speed ?>;
    var timer = 0, lastFocus = null;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function revealText() {
      window.clearTimeout(timer);
      output.classList.remove('is-fading');
      if (reduced || animation === 'normal') { output.textContent = source; return; }
      if (animation === 'fade') {
        output.textContent = source;
        void output.offsetWidth;
        output.classList.add('is-fading');
        return;
      }
      var chars = Array.from(source), index = 0;
      output.textContent = '';
      function tick() {
        if (!root.classList.contains('is-open')) return;
        output.textContent += chars[index++] || '';
        if (index < chars.length) timer = window.setTimeout(tick, speed);
      }
      tick();
    }

    function openIntro(trigger) {
      lastFocus = trigger || document.activeElement;
      root.hidden = false;
      root.setAttribute('aria-hidden', 'false');
      root.classList.add('is-open');
      document.documentElement.classList.add('bio-intro-open');
      revealText();
      var close = root.querySelector('.bio-intro-close');
      if (close) close.focus();
    }

    function closeIntro() {
      window.clearTimeout(timer);
      root.classList.remove('is-open');
      root.setAttribute('aria-hidden', 'true');
      root.hidden = true;
      output.textContent = '';
      document.documentElement.classList.remove('bio-intro-open');
      if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }

    triggers.forEach(function (trigger) { trigger.addEventListener('click', function () { openIntro(trigger); }); });
    root.querySelectorAll('[data-bio-intro-close]').forEach(function (button) { button.addEventListener('click', closeIntro); });
    document.addEventListener('keydown', function (event) {
      if (!root.classList.contains('is-open')) return;
      if (event.key === 'Escape') { event.preventDefault(); closeIntro(); return; }
      if (event.key !== 'Tab') return;
      var focusable = Array.prototype.slice.call(panel.querySelectorAll('a[href],button:not([disabled])'));
      if (!focusable.length) return;
      var first = focusable[0], last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
  })();
  </script>
    <?php
}

/** Render seluruh blok aktif dalam kolom biolink terpusat. */
function bioRenderBlocks(array $blocks): void
{
    if (!$blocks) return;
    echo '<div class="max-w-xl mx-auto px-4 pt-6 space-y-3">';
    foreach ($blocks as $b) {
        $type = $b['type'] ?? '';
        $cfg  = $b['cfg'] ?? [];
        switch ($type) {
            case 'button':   bioRenderButton($cfg); break;
            case 'social':   bioRenderSocial($cfg); break;
            case 'text':     bioRenderText($cfg); break;
            case 'image':    bioRenderImage($cfg); break;
            case 'divider':  bioRenderDivider($cfg); break;
        }
    }
    echo '</div>';
}

function bioRenderButton(array $cfg): void
{
    $url = bioSafeUrl((string) ($cfg['url'] ?? ''));
    if ($url === '') return;
    $label = (string) ($cfg['label'] ?? 'Tautan');
    $style = in_array($cfg['style'] ?? 'filled', ['filled', 'outline', 'soft'], true) ? $cfg['style'] : 'filled';
    $cls = match ($style) {
        'outline' => 'fe-accent-text border-2 border-[var(--accent)] hover:bg-[color-mix(in_srgb,var(--accent)_10%,transparent)]',
        'soft'    => 'fe-accent-text hover:brightness-95',
        default   => 'text-white hover:opacity-90',
    };
    $inlineBg = $style === 'filled'
        ? 'style="background:var(--accent);box-shadow:0 10px 24px -12px color-mix(in srgb, var(--accent) 70%, transparent)"'
        : ($style === 'soft' ? 'style="background:color-mix(in srgb, var(--accent) 12%, transparent)"' : '');
    $up = rtrim(UPLOAD_URL, '/');
    $photo = trim((string) ($cfg['photo'] ?? ''));
    $icon  = trim((string) ($cfg['icon'] ?? ''));
    ?>
    <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="bio-btn relative flex items-center justify-center gap-2.5 w-full px-4 py-3.5 rounded-lg font-semibold text-[15px] transition <?= $cls ?>" <?= $inlineBg ?>>
      <?php if ($photo !== ''): ?>
        <?= scribeUploadImageHtml($photo, '', 'absolute left-2 w-9 h-9 rounded-md object-cover', '36px') ?>
      <?php elseif ($icon !== ''): ?>
        <span class="absolute left-3.5"><?= icon($icon, 'w-5 h-5') ?></span>
      <?php endif; ?>
      <span class="truncate"><?= e($label) ?></span>
    </a>
    <?php
}

function bioRenderSocial(array $cfg): void
{
    $items = is_array($cfg['items'] ?? null) ? $cfg['items'] : [];
    $links = [];
    foreach ($items as $it) {
        $net = (string) ($it['network'] ?? '');
        $url = bioSocialUrl($net, (string) ($it['url'] ?? ''));
        if ($net !== '' && $url !== '') $links[] = [$net, $url];
    }
    if (!$links) return;
    ?>
    <div class="bio-social flex flex-wrap items-center justify-center gap-3 py-1">
      <?php foreach ($links as [$net, $url]): ?>
      <a href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="<?= e(bioSocialNetworks()[$net] ?? $net) ?>"
         class="inline-flex items-center justify-center w-10 h-10 rounded-full text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:text-white hover:bg-[var(--accent)] transition-colors">
        <svg class="w-5 h-5" viewBox="0 0 24 24"><?= bioSocialIconSvg($net) ?></svg>
      </a>
      <?php endforeach; ?>
    </div>
    <?php
}

function bioRenderText(array $cfg): void
{
    $heading = trim((string) ($cfg['heading'] ?? ''));
    $body    = trim((string) ($cfg['body'] ?? ''));
    if ($heading === '' && $body === '') return;
    ?>
    <div class="text-center py-1">
      <?php if ($heading !== ''): ?><h2 class="font-display font-semibold text-lg mb-1"><?= e($heading) ?></h2><?php endif; ?>
      <?php if ($body !== ''): ?><p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed"><?= nl2br(e($body)) ?></p><?php endif; ?>
    </div>
    <?php
}

function bioRenderImage(array $cfg): void
{
    $path = trim((string) ($cfg['path'] ?? ''));
    if ($path === '') return;
    $alt = (string) ($cfg['alt'] ?? '');
    $link = bioSafeUrl((string) ($cfg['link'] ?? ''));
    $img = scribeUploadImageHtml($path, $alt, 'bio-img w-full rounded-lg border border-gray-200 dark:border-gray-800', '(min-width:640px) 576px, 100vw');
    if ($link !== '') {
        echo '<a href="' . e($link) . '" target="_blank" rel="noopener" class="block">' . $img . '</a>';
    } else {
        echo '<div>' . $img . '</div>';
    }
}

function bioRenderDivider(array $cfg): void
{
    $style = ($cfg['style'] ?? 'line') === 'space' ? 'space' : 'line';
    echo $style === 'space'
        ? '<div class="h-4"></div>'
        : '<hr class="my-2 border-gray-200 dark:border-gray-800">';
}

/** Ringkasan blok untuk daftar di admin builder. */
function bioBlockSummary(array $b): string
{
    $cfg = $b['cfg'] ?? [];
    return match ($b['type'] ?? '') {
        'button'  => (string) ($cfg['label'] ?? '(tanpa label)'),
        'social'  => count($cfg['items'] ?? []) . ' tautan sosial',
        'text'    => (string) ($cfg['heading'] ?? mb_substr((string) ($cfg['body'] ?? ''), 0, 40)) ?: '(teks)',
        'image'   => (string) ($cfg['alt'] ?? 'Gambar'),
        'divider' => 'Pembatas (' . (($cfg['style'] ?? 'line') === 'space' ? 'spasi' : 'garis') . ')',
        default   => $b['type'] ?? '?',
    };
}

/** Label tipe blok (Indonesia). */
function bioBlockTypeLabel(string $type): string
{
    return ['button' => 'Tombol', 'social' => 'Sosial', 'text' => 'Teks', 'image' => 'Gambar', 'divider' => 'Pembatas'][$type] ?? $type;
}
