<?php
// ════════════════════════════════════════════════════════════════════════
// UI Theme / Appearance. Memengaruhi area ADMIN (scribeThemeComponentCss) DAN
// FRONTEND PUBLIK Blog + BioLink + artikel (scribeThemePublicCss). Halaman
// enforcement/update TIDAK ikut. Tema menambah CSS variable + class kosmetik
// pada markup yang sama; dark/light toggle & --accent (rebrand) tetap independen.
// Sidebar/Topbar style hanya relevan di admin.
// ════════════════════════════════════════════════════════════════════════

/**
 * Preset UI Theme BERKARAKTER. Tiap preset men-set variabel karakter (bukan
 * hanya warna): density (--pad/--gap/--cell-y), radius (4-8px), border
 * (--border-w/--border-color), shadow (--card-shadow/--shadow-hover), surface
 * (--surface-bg + --surface-blur), --font-scale, --topbar-h. 'kpi' & 'chip'
 * adalah flag gaya yang dibaca CSS komponen (scribeThemeComponentCss) — TANPA
 * if-preset di markup. Semua kunci berawalan '--' otomatis di-emit sbg CSS var.
 */
function scribeThemePresets(): array
{
    return [
        'default' => [
            'label' => 'Default', 'description' => 'Clean & netral — depth lembut, spacing standar, baseline profesional.',
            'kpi' => 'hero', 'chip' => 'colored',
            '--bg-canvas' => '#f7f8fb', '--bg-canvas-dark' => '#030712',
            '--card-radius' => '8px', '--control-radius' => '6px',
            '--pad' => '1.25rem', '--gap' => '1rem', '--cell-y' => '0.6rem', '--font-scale' => '1',
            '--topbar-h' => '54px',
            '--border-w' => '1px', '--border-color' => 'rgba(15,23,42,0.08)', '--border-color-dark' => 'rgba(255,255,255,0.08)',
            '--surface-bg' => '#ffffff', '--surface-bg-dark' => '#111827', '--surface-blur' => 'none',
            '--card-shadow' => '0 4px 18px -8px rgba(15,23,42,0.14), 0 1px 3px rgba(15,23,42,0.05)',
            '--shadow-hover' => '0 12px 28px -12px rgba(15,23,42,0.24)',
            // Publik (Blog + BioLink)
            '--fe-canvas' => '#f7f8fb', '--fe-canvas-dark' => '#030712',
            '--fe-lead' => '1.7', '--fe-head-weight' => '700', '--fe-head-tracking' => '-0.01em',
            '--fe-header-bg' => 'rgba(255,255,255,0.72)', '--fe-header-bg-dark' => 'rgba(3,7,18,0.6)', '--fe-header-border' => 'rgba(15,23,42,0.06)',
            '--fe-btn-radius' => '5px', '--fe-dots' => '0.05', '--fe-section' => '3.25rem',
        ],
        'airy' => [
            'label' => 'Airy', 'description' => 'Lapang & elegan — whitespace luas, kartu frosted, heading ringan, border nyaris hilang.',
            'kpi' => 'hero', 'chip' => 'colored',
            '--bg-canvas' => '#eef0f8', '--bg-canvas-dark' => '#0b0b14',
            '--card-radius' => '14px', '--control-radius' => '10px',
            '--pad' => '1.65rem', '--gap' => '1.4rem', '--cell-y' => '0.8rem', '--font-scale' => '1',
            '--topbar-h' => '58px',
            '--border-w' => '1px', '--border-color' => 'rgba(99,102,241,0.10)', '--border-color-dark' => 'rgba(255,255,255,0.05)',
            '--surface-bg' => 'rgba(255,255,255,0.72)', '--surface-bg-dark' => 'rgba(23,26,42,0.55)', '--surface-blur' => 'blur(10px) saturate(140%)',
            '--card-shadow' => '0 16px 40px -18px rgba(99,102,241,0.24), 0 2px 8px rgba(99,102,241,0.05)',
            '--shadow-hover' => '0 26px 56px -22px rgba(99,102,241,0.32)',
            '--fe-canvas' => '#eef1f9', '--fe-canvas-dark' => '#0b0b14',
            '--fe-lead' => '1.9', '--fe-head-weight' => '600', '--fe-head-tracking' => '0em',
            '--fe-header-bg' => 'rgba(255,255,255,0.55)', '--fe-header-bg-dark' => 'rgba(20,20,30,0.5)', '--fe-header-border' => 'transparent',
            '--fe-btn-radius' => '6px', '--fe-dots' => '0.045', '--fe-section' => '5.5rem',
        ],
        'vivid' => [
            'label' => 'Vivid', 'description' => 'Ekspresif — tombol/aksen gradient, chip berwarna, pola & bayangan lebih hidup.',
            'kpi' => 'vivid', 'chip' => 'multi',
            '--bg-canvas' => '#f4f4fb', '--bg-canvas-dark' => '#0c0a14',
            '--card-radius' => '10px', '--control-radius' => '8px',
            '--pad' => '1.25rem', '--gap' => '1rem', '--cell-y' => '0.6rem', '--font-scale' => '1',
            '--topbar-h' => '54px',
            '--border-w' => '1px', '--border-color' => 'rgba(139,92,246,0.16)', '--border-color-dark' => 'rgba(139,92,246,0.22)',
            '--surface-bg' => '#ffffff', '--surface-bg-dark' => '#12101c', '--surface-blur' => 'none',
            '--card-shadow' => '0 12px 34px -14px rgba(139,92,246,0.26), 0 2px 6px rgba(139,92,246,0.07)',
            '--shadow-hover' => '0 20px 44px -16px rgba(139,92,246,0.36)',
            '--fe-canvas' => '#f5f3fd', '--fe-canvas-dark' => '#0c0a14',
            '--fe-lead' => '1.65', '--fe-head-weight' => '700', '--fe-head-tracking' => '-0.015em',
            '--fe-header-bg' => 'rgba(255,255,255,0.7)', '--fe-header-bg-dark' => 'rgba(18,16,28,0.62)', '--fe-header-border' => 'rgba(139,92,246,0.14)',
            '--fe-btn-radius' => '5px', '--fe-dots' => '0.08', '--fe-section' => '3.5rem',
        ],
        'corporate' => [
            'label' => 'Corporate', 'description' => 'Padat & formal — border tegas, spacing rapat, radius kecil, minim dekorasi.',
            'kpi' => 'flat', 'chip' => 'mono',
            '--bg-canvas' => '#eef2f7', '--bg-canvas-dark' => '#020617',
            '--card-radius' => '4px', '--control-radius' => '4px',
            '--pad' => '0.95rem', '--gap' => '0.7rem', '--cell-y' => '0.4rem', '--font-scale' => '0.95',
            '--topbar-h' => '48px',
            '--border-w' => '1px', '--border-color' => '#cbd5e1', '--border-color-dark' => '#334155',
            '--surface-bg' => '#ffffff', '--surface-bg-dark' => '#0b1220', '--surface-blur' => 'none',
            '--card-shadow' => '0 1px 2px rgba(15,23,42,0.10)',
            '--shadow-hover' => '0 3px 8px -2px rgba(15,23,42,0.16)',
            '--fe-canvas' => '#eef2f7', '--fe-canvas-dark' => '#020617',
            '--fe-lead' => '1.55', '--fe-head-weight' => '700', '--fe-head-tracking' => '-0.005em',
            '--fe-header-bg' => '#ffffff', '--fe-header-bg-dark' => '#0b1220', '--fe-header-border' => '#cbd5e1',
            '--fe-btn-radius' => '3px', '--fe-dots' => '0', '--fe-section' => '2.5rem',
        ],
        'minimal' => [
            'label' => 'Minimal', 'description' => 'Sangat bersih — hampir tanpa border/shadow, struktur dari hairline & whitespace.',
            'kpi' => 'flat', 'chip' => 'mono-outline',
            '--bg-canvas' => '#ffffff', '--bg-canvas-dark' => '#0a0a0a',
            '--card-radius' => '10px', '--control-radius' => '8px',
            '--pad' => '1.4rem', '--gap' => '1.15rem', '--cell-y' => '0.6rem', '--font-scale' => '1',
            '--topbar-h' => '54px',
            '--border-w' => '0px', '--border-color' => 'transparent', '--border-color-dark' => 'transparent',
            '--surface-bg' => '#ffffff', '--surface-bg-dark' => '#0f0f0f', '--surface-blur' => 'none',
            '--card-shadow' => 'none',
            '--shadow-hover' => '0 8px 24px -14px rgba(15,23,42,0.16)',
            '--fe-canvas' => '#ffffff', '--fe-canvas-dark' => '#0a0a0a',
            '--fe-lead' => '1.8', '--fe-head-weight' => '600', '--fe-head-tracking' => '0em',
            '--fe-header-bg' => 'rgba(255,255,255,0.85)', '--fe-header-bg-dark' => 'rgba(10,10,10,0.85)', '--fe-header-border' => 'transparent',
            '--fe-btn-radius' => '4px', '--fe-dots' => '0', '--fe-section' => '4.5rem',
        ],
        'noir' => [
            'label' => 'Noir', 'description' => 'Dark premium — charcoal hangat sejak mode terang, kontras terkontrol, aksen halus.',
            'kpi' => 'hero', 'chip' => 'colored',
            '--bg-canvas' => '#1c1712', '--bg-canvas-dark' => '#140f0a',
            '--card-radius' => '8px', '--control-radius' => '6px',
            '--pad' => '1.25rem', '--gap' => '1rem', '--cell-y' => '0.6rem', '--font-scale' => '1',
            '--topbar-h' => '54px',
            '--border-w' => '1px', '--border-color' => 'rgba(255,234,200,0.10)', '--border-color-dark' => 'rgba(255,234,200,0.08)',
            '--surface-bg' => '#251d15', '--surface-bg-dark' => '#1e1811', '--surface-blur' => 'none',
            '--card-shadow' => '0 16px 44px -16px rgba(0,0,0,0.45), 0 2px 6px rgba(0,0,0,0.3)',
            '--shadow-hover' => '0 20px 50px -18px rgba(0,0,0,0.6)',
            '--fe-canvas' => '#17120c', '--fe-canvas-dark' => '#100b06',
            '--fe-lead' => '1.7', '--fe-head-weight' => '700', '--fe-head-tracking' => '-0.01em',
            '--fe-header-bg' => 'rgba(30,24,17,0.82)', '--fe-header-bg-dark' => 'rgba(20,15,10,0.85)', '--fe-header-border' => 'rgba(255,234,200,0.10)',
            '--fe-btn-radius' => '4px', '--fe-dots' => '0.07', '--fe-section' => '3.5rem',
        ],
    ];
}

/** Label sidebar style (Classic/Midnight Rail/Brand Panel). */
function scribeSidebarStyles(): array
{
    return ['classic' => 'Classic', 'midnight' => 'Midnight Rail', 'brand' => 'Brand Panel'];
}

/** Label topbar style. */
function scribeTopbarStyles(): array
{
    return ['default' => 'Default', 'brand' => 'Brand', 'dark' => 'Command Dark'];
}

/** Konfigurasi tema aktif (tervalidasi). */
function scribeThemeConfig(): array
{
    $presets = scribeThemePresets();
    $theme   = getSetting('ui_theme', 'default');
    if (!isset($presets[$theme])) $theme = 'default';

    $sidebar = getSetting('ui_sidebar_style', 'classic');
    if (!isset(scribeSidebarStyles()[$sidebar])) $sidebar = 'classic';

    $topbar = getSetting('ui_topbar_style', 'default');
    if (!isset(scribeTopbarStyles()[$topbar])) $topbar = 'default';

    $cfg = $presets[$theme];
    $cfg['key']     = $theme;
    $cfg['sidebar'] = $sidebar;
    $cfg['topbar']  = $topbar;
    return $cfg;
}

/** CSS variable :root — emit SEMUA kunci preset berawalan '--' + accent. */
function scribeThemeCss(array $cfg, string $accent): string
{
    $out = ':root{--accent:' . $accent . ';--fe-accent-text:color-mix(in srgb,var(--accent) 55%,#111827);';
    foreach ($cfg as $k => $v) {
        if (is_string($k) && str_starts_with($k, '--')) {
            $out .= $k . ':' . $v . ';';
        }
    }
    return $out . '}html.dark{--fe-accent-text:color-mix(in srgb,var(--accent) 72%,#ffffff);}';
}

/**
 * CSS komponen dashboard yang MEMBACA variabel karakter + aturan per-preset
 * (keyed [data-ui-theme="..."]). Dipanggil sekali di <style> shell. Komponen
 * membaca var → density/border/shadow/surface/font berbeda otomatis tiap preset,
 * tanpa if-preset di markup. Radius dijaga 4-8px oleh nilai preset.
 */
function scribeThemeComponentCss(): string
{
    return <<<'CSS'
/* ═══ B-02.6 — PRESET BERKARAKTER (komponen membaca variabel) ═══ */
/* Density: padding & gap kartu/kontainer dari --pad/--gap (scope main). */
[data-ui-theme] main .p-6{ padding: calc(var(--pad) + 0.35rem) !important; }
[data-ui-theme] main .p-5,[data-ui-theme] main .sm\:p-5{ padding: var(--pad) !important; }
[data-ui-theme] main .p-4{ padding: max(0.6rem, calc(var(--pad) - 0.25rem)) !important; }
[data-ui-theme] main .p-3{ padding: max(0.45rem, calc(var(--pad) - 0.45rem)) !important; }
[data-ui-theme] main .gap-4{ gap: var(--gap) !important; }
[data-ui-theme] main .gap-3{ gap: calc(var(--gap) * 0.8) !important; }
[data-ui-theme] main .sm\:gap-4{ gap: var(--gap) !important; }
/* Tabel dense/lapang dari --cell-y. */
[data-ui-theme] main table th,[data-ui-theme] main table td{ padding-top: var(--cell-y) !important; padding-bottom: var(--cell-y) !important; }
/* Skala font (Corporate 0.95) — scope main, hanya utility umum. */
[data-ui-theme] main .text-xs{ font-size: calc(0.75rem * var(--font-scale)); }
[data-ui-theme] main .text-sm{ font-size: calc(0.875rem * var(--font-scale)); }
[data-ui-theme] main .text-lg{ font-size: calc(1.125rem * var(--font-scale)); }
[data-ui-theme] main .text-2xl{ font-size: calc(1.5rem * var(--font-scale)); }
[data-ui-theme] main .text-3xl{ font-size: calc(1.875rem * var(--font-scale)); }

/* Kartu (surface): background/border/radius/shadow/blur dari variabel.
   Kartu KPI (.stat-card) dikecualikan → ditangani blok KPI di bawah. */
[data-ui-theme] main .rounded-lg.border:not(.stat-card){
  background: var(--surface-bg) !important;
  border-width: var(--border-w) !important;
  border-color: var(--border-color) !important;
  border-radius: var(--card-radius) !important;
  box-shadow: var(--card-shadow) !important;
  -webkit-backdrop-filter: var(--surface-blur); backdrop-filter: var(--surface-blur);
}
html.dark [data-ui-theme] main .rounded-lg.border:not(.stat-card){
  background: var(--surface-bg-dark) !important;
  border-color: var(--border-color-dark) !important;
}
/* Kartu KPI dasar (netral) — surface + border + shadow variabel. */
[data-ui-theme] main .stat-card{
  background: var(--surface-bg) !important; border-width: var(--border-w) !important;
  border-color: var(--border-color) !important; border-radius: var(--card-radius) !important;
  box-shadow: var(--card-shadow) !important;
}
html.dark [data-ui-theme] main .stat-card{ background: var(--surface-bg-dark) !important; border-color: var(--border-color-dark) !important; }
/* Topbar tinggi ringkas dari --topbar-h; sidebar brand ikut selaras. */
#topbar{ height: var(--topbar-h) !important; }
#sidebar .side-brand-row{ height: var(--topbar-h) !important; }

/* ── KPI hero (preset hero/vivid): kartu pertama gradien brand ── */
[data-ui-theme] main .stat-card.stat-hero{
  background: linear-gradient(135deg, var(--accent) 0%, color-mix(in srgb, var(--accent) 66%, #000) 100%) !important;
  border-color: transparent !important;
  box-shadow: 0 16px 40px -14px color-mix(in srgb, var(--accent) 55%, transparent) !important;
}
[data-ui-theme] main .stat-card.stat-hero .stat-num,
[data-ui-theme] main .stat-card.stat-hero .stat-label{ color:#fff !important; }
[data-ui-theme] main .stat-card.stat-hero .icon-chip{ background: rgba(255,255,255,0.20); color:#fff; }
/* Flat (Corporate/Minimal): netralkan hero → kartu netral biasa. */
[data-ui-theme="corporate"] main .stat-card.stat-hero,
[data-ui-theme="minimal"] main .stat-card.stat-hero{
  background: var(--surface-bg) !important; border-color: var(--border-color) !important;
  box-shadow: var(--card-shadow) !important;
}
html.dark [data-ui-theme="corporate"] main .stat-card.stat-hero,
html.dark [data-ui-theme="minimal"] main .stat-card.stat-hero{ background: var(--surface-bg-dark) !important; }
[data-ui-theme="corporate"] main .stat-card.stat-hero .stat-num,[data-ui-theme="minimal"] main .stat-card.stat-hero .stat-num,
[data-ui-theme="corporate"] main .stat-card.stat-hero .stat-label,[data-ui-theme="minimal"] main .stat-card.stat-hero .stat-label{ color: inherit !important; }
[data-ui-theme="corporate"] main .stat-card.stat-hero .icon-chip,[data-ui-theme="minimal"] main .stat-card.stat-hero .icon-chip{ background: color-mix(in srgb, currentColor 8%, transparent) !important; color: inherit !important; }

/* ── Gaya chip ikon ── */
/* mono (Corporate): abu netral, tanpa warna accent. */
[data-ui-theme="corporate"] .icon-chip{ background: color-mix(in srgb, currentColor 8%, transparent); color: inherit; }
/* mono-outline (Minimal): garis tipis, transparan. */
[data-ui-theme="minimal"] .icon-chip{ background: transparent; border: 1px solid color-mix(in srgb, var(--accent) 32%, transparent); color: var(--accent); }
/* multi (Vivid): kartu KPI ke-2/3/4 dapat hue soft berbeda. */
[data-ui-theme="vivid"] .stat-card:nth-child(2) .icon-chip{ background: rgba(59,130,246,0.16); color:#2563eb; }
[data-ui-theme="vivid"] .stat-card:nth-child(3) .icon-chip{ background: rgba(236,72,153,0.16); color:#db2777; }
[data-ui-theme="vivid"] .stat-card:nth-child(4) .icon-chip{ background: rgba(16,185,129,0.16); color:#059669; }
html.dark [data-ui-theme="vivid"] .stat-card:nth-child(2) .icon-chip{ background: rgba(59,130,246,0.26); color:#60a5fa; }
html.dark [data-ui-theme="vivid"] .stat-card:nth-child(3) .icon-chip{ background: rgba(236,72,153,0.26); color:#f472b6; }
html.dark [data-ui-theme="vivid"] .stat-card:nth-child(4) .icon-chip{ background: rgba(16,185,129,0.26); color:#34d399; }
/* Vivid: tombol/link primer accent → gradient (tanpa ubah markup). */
[data-ui-theme="vivid"] main button[style*="background:var(--accent)"],
[data-ui-theme="vivid"] main a[style*="background:var(--accent)"]{
  background-image: linear-gradient(135deg, var(--accent) 0%, color-mix(in srgb, var(--accent) 58%, #000) 100%) !important;
}

/* Hover lift memakai --shadow-hover. */
.stat-card{ transition: transform .18s ease, box-shadow .18s ease; }
.stat-card:hover{ transform: translateY(-2px); box-shadow: var(--shadow-hover) !important; }

/* ── NOIR dark-first: charcoal hangat + teks terang SEJAK light mode ── */
body[data-ui-theme="noir"]{ color: #efe6d6; }
body[data-ui-theme="noir"] .text-gray-900,body[data-ui-theme="noir"] .text-gray-800,body[data-ui-theme="noir"] .text-gray-700{ color:#f4ecdc !important; }
body[data-ui-theme="noir"] .text-gray-600,body[data-ui-theme="noir"] .text-gray-500,body[data-ui-theme="noir"] .text-gray-400{ color:#b6a892 !important; }
body[data-ui-theme="noir"] main .border-gray-200,body[data-ui-theme="noir"] main .border-gray-100{ border-color: rgba(255,234,200,0.10) !important; }
body[data-ui-theme="noir"] main .divide-gray-100 > * + *,body[data-ui-theme="noir"] main .divide-gray-800 > * + *{ border-color: rgba(255,234,200,0.08) !important; }
body[data-ui-theme="noir"] #topbar{ background: rgba(30,24,17,0.85) !important; border-color: rgba(255,234,200,0.10) !important; }
body[data-ui-theme="noir"] #topbar .topbar-title{ color:#f4ecdc; }
body[data-ui-theme="noir"] #sidebar{ background:#1a140d !important; border-color: rgba(255,234,200,0.10) !important; }
body[data-ui-theme="noir"] #sidebar .side-brand-name{ color:#f4ecdc; }
body[data-ui-theme="noir"] #sidebar a:not(.side-active){ color:#b6a892; }
body[data-ui-theme="noir"] #sidebar a:not(.side-active):hover{ background: rgba(255,234,200,0.06); }
body[data-ui-theme="noir"] input,body[data-ui-theme="noir"] select,body[data-ui-theme="noir"] textarea{ background: rgba(255,255,255,0.03) !important; color:#f4ecdc; }
/* Noir glow aksen di kartu KPI hero + active. */
body[data-ui-theme="noir"] .stat-hero{ box-shadow: 0 18px 46px -16px color-mix(in srgb, var(--accent) 55%, transparent) !important; }
CSS;
}

/**
 * CSS tema untuk FRONTEND PUBLIK (Blog + BioLink + artikel). Membaca variabel
 * karakter (radius/border/shadow/surface/spacing) + variabel publik --fe-* dan
 * memberi tiap preset SISTEM VISUAL berbeda (tipografi, header, kartu, tombol,
 * badge, pola, section, biolink) — pada MARKUP yang sama. Scope [data-ui-theme]
 * (dipasang di <body> publik). Dipanggil sekali di <style> layout theme-default.
 */
function scribeThemePublicCss(): string
{
    return <<<'CSS'
/* ═══ Tema publik: variabel karakter → komponen (Blog + BioLink) ═══ */
body[data-ui-theme]{ line-height: var(--fe-lead, 1.65); }
[data-ui-theme] h1,[data-ui-theme] h2,[data-ui-theme] h3,[data-ui-theme] .font-display{
  font-weight: var(--fe-head-weight, 700); letter-spacing: var(--fe-head-tracking, -0.01em);
}
/* Kartu artikel & featured: surface/border/radius/shadow/blur dari variabel. */
[data-ui-theme] .fe-card{
  background: var(--surface-bg) !important; border-width: var(--border-w) !important;
  border-color: var(--border-color) !important; border-radius: var(--card-radius) !important;
  box-shadow: var(--card-shadow) !important;
  -webkit-backdrop-filter: var(--surface-blur); backdrop-filter: var(--surface-blur);
}
html.dark [data-ui-theme] .fe-card{ background: var(--surface-bg-dark) !important; border-color: var(--border-color-dark) !important; }
[data-ui-theme] .fe-card:hover{ box-shadow: var(--shadow-hover) !important; }
[data-ui-theme] .fe-card > div{ padding: var(--pad); }
[data-ui-theme] .fe-featured{ border-width: var(--border-w) !important; border-color: var(--border-color) !important; border-radius: var(--card-radius) !important; box-shadow: var(--card-shadow) !important; }
html.dark [data-ui-theme] .fe-featured{ border-color: var(--border-color-dark) !important; }
[data-ui-theme] .fe-featured:hover{ box-shadow: var(--shadow-hover) !important; }
/* Pill/badge + tombol aksen: radius per tema. */
[data-ui-theme] .fe-pill{ border-radius: var(--control-radius); }
[data-ui-theme] .fe-badge{ border-radius: var(--control-radius); }
[data-ui-theme] [style*="background:var(--accent)"]{ border-radius: var(--fe-btn-radius); }
/* Tombol frontend semi-kotak. Kontrol bulat yang punya fungsi khusus tetap dikecualikan. */
[data-ui-theme] button:not(.theme-switch):not(.bio-intro-backdrop):not(.fe-search-backdrop),
[data-ui-theme] :where(.fe-header-cta,.bio-btn,.bio-intro-trigger,.bio-intro-close,.bio-intro-cta,.promo-cta,.promo-close,.share-btn,a[data-meta-cta],.fe-lead-magnet a.inline-flex,.fe-band a.inline-flex,nav[aria-label="Halaman"] a){
  border-radius: var(--fe-btn-radius, 5px) !important;
}
/* Header situs: latar/border per tema. */
[data-ui-theme] #site-header{ background: var(--fe-header-bg) !important; border-color: var(--fe-header-border) !important; }
html.dark [data-ui-theme] #site-header{ background: var(--fe-header-bg-dark) !important; }
[data-ui-theme] #site-header.scrolled{ border-color: var(--fe-header-border) !important; }
/* Pola titik hero + rhythm section (hero) + gap grid. */
[data-ui-theme] .fe-dots{ opacity: var(--fe-dots, 0.05); }
[data-ui-theme] .fe-hero{ padding-top: var(--fe-section) !important; padding-bottom: calc(var(--fe-section) * 0.55) !important; }
[data-ui-theme] main .grid.gap-5{ gap: var(--gap); }
/* Prose artikel: leading per tema. */
[data-ui-theme] .prose-art{ line-height: var(--fe-lead, 1.75); }
/* BioLink: tombol + kartu + avatar mengikuti karakter. */
[data-ui-theme] .bio-btn{ border-radius: var(--fe-btn-radius) !important; }
[data-ui-theme] .bio-img{ border-radius: var(--card-radius) !important; border-color: var(--border-color) !important; }

/* ── AIRY: heading lega + tombol lebih besar radius (var handle) ── */
[data-ui-theme="airy"] .fe-badge{ background: color-mix(in srgb, var(--accent) 10%, transparent); }

/* ── VIVID: tombol/aksen gradient + badge gradient + hover border kuat ── */
[data-ui-theme="vivid"] [style*="background:var(--accent)"]{ background-image: linear-gradient(135deg, var(--accent) 0%, color-mix(in srgb, var(--accent) 55%, #000) 100%) !important; }
[data-ui-theme="vivid"] .fe-badge{ background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 20%, transparent), color-mix(in srgb, var(--accent) 7%, transparent)); }
[data-ui-theme="vivid"] .fe-card:hover{ border-color: color-mix(in srgb, var(--accent) 45%, transparent) !important; }

/* ── CORPORATE: border tegas, tanpa lift, badge kotak uppercase ── */
[data-ui-theme="corporate"] .fe-card:hover,[data-ui-theme="corporate"] .fe-featured:hover{ transform: none !important; }
[data-ui-theme="corporate"] .fe-card:hover .fe-cover{ transform: none; }
[data-ui-theme="corporate"] .fe-badge{ text-transform: uppercase; letter-spacing: .04em; border-radius: 3px; }
[data-ui-theme="corporate"] #site-header{ border-bottom-width: 2px !important; }

/* ── MINIMAL: borderless & flat, hover halus tanpa lift ── */
[data-ui-theme="minimal"] .fe-card,[data-ui-theme="minimal"] .fe-featured{ box-shadow: none !important; border-width: 0 !important; }
[data-ui-theme="minimal"] .fe-card:hover{ transform: none !important; box-shadow: var(--shadow-hover) !important; }
[data-ui-theme="minimal"] .fe-featured:hover{ transform: none !important; }
[data-ui-theme="minimal"] .fe-pill{ background: transparent; }
[data-ui-theme="minimal"] .fe-badge{ background: transparent; border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent); }

/* ── NOIR: charcoal hangat + teks krem, sejak mode terang ── */
body[data-ui-theme="noir"]{ color: #efe6d6; }
body[data-ui-theme="noir"] .text-gray-900,body[data-ui-theme="noir"] .text-gray-800,body[data-ui-theme="noir"] .text-gray-700{ color:#f4ecdc !important; }
body[data-ui-theme="noir"] .text-gray-600,body[data-ui-theme="noir"] .text-gray-500,body[data-ui-theme="noir"] .text-gray-400{ color:#b6a892 !important; }
body[data-ui-theme="noir"] .border-gray-200,body[data-ui-theme="noir"] .border-gray-100,body[data-ui-theme="noir"] .border-gray-800{ border-color: rgba(255,234,200,0.10) !important; }
body[data-ui-theme="noir"] .fe-card,body[data-ui-theme="noir"] .fe-featured{ background:#251d15 !important; }
html.dark body[data-ui-theme="noir"] .fe-card,html.dark body[data-ui-theme="noir"] .fe-featured{ background:#1e1811 !important; }
body[data-ui-theme="noir"] input,body[data-ui-theme="noir"] select,body[data-ui-theme="noir"] textarea{ background: rgba(255,255,255,0.04) !important; color:#f4ecdc; border-color: rgba(255,234,200,0.12); }
body[data-ui-theme="noir"] .bio-social a{ background: rgba(255,234,200,0.08) !important; color:#e9dcc5 !important; }
/* Noir: footer & menu mobile ikut gelap (jangan biarkan bg-gray-50 terang). */
body[data-ui-theme="noir"] footer{ background:#181009 !important; border-color: rgba(255,234,200,0.10) !important; }
body[data-ui-theme="noir"] #mobileMenu{ background: rgba(24,16,9,0.97) !important; border-color: rgba(255,234,200,0.10) !important; }
CSS;
}
