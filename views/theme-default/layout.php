<?php
// ════════════════════════════════════════════════════════════════════════
// Layout theme-default (Track B). SATU-SATUNYA markup publik hidup di folder
// views/theme-default. CSS utility dibundel lokal, Vanilla JS, font sistem,
// ikon inline SVG Lucide (tanpa emoji), radius 6-8px, dark/light
// + localStorage (key 'theme', sama dengan admin), --accent dari setting rebrand.
//
// Pola pakai:
//   theme_head(['title'=>..., 'description'=>..., 'active'=>'home'|catSlug]);
//   ...konten...
//   theme_footer();
//
// SEO head lengkap (canonical/OG/Twitter/JSON-LD) diperkaya di B-03 via
// helpers/seo-head.php — di sini disediakan dasar + hook 'head_extra'.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../helpers/frontend.php';
require_once __DIR__ . '/../../helpers/theme-config.php';
require_once __DIR__ . '/../../helpers/meta-pixel.php';

/**
 * Buka dokumen + header situs.
 * $ctx: title, description, active (slug kategori aktif | 'home' | ''),
 *       canonical (URL absolut, default URL saat ini), og_image (URL absolut),
 *       head_extra (string HTML mentah untuk <head>, mis. JSON-LD dari B-03).
 */
function theme_head(array $ctx = []): void
{
    $brand   = feBrand();
    $title   = trim((string) ($ctx['title'] ?? '')) !== '' ? $ctx['title'] : $brand['name'];
    $desc    = trim((string) ($ctx['description'] ?? '')) !== '' ? $ctx['description'] : $brand['tagline'];
    $active  = (string) ($ctx['active'] ?? '');
    $upBase  = rtrim(UPLOAD_URL, '/');
    $canon   = (string) ($ctx['canonical'] ?? _feCurrentUrl());
    $ogImg   = (string) ($ctx['og_image'] ?? ($brand['og_default'] !== '' ? $upBase . '/' . $brand['og_default'] : ''));
    $cats    = feNavCategories();
    $theme   = scribeThemeConfig();
    ?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<?php if ($desc !== ''): ?><meta name="description" content="<?= e(truncateText($desc, 160)) ?>"><?php endif; ?>
<link rel="canonical" href="<?= e($canon) ?>">
<meta property="og:type" content="<?= e($ctx['og_type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($title) ?>">
<?php if ($desc !== ''): ?><meta property="og:description" content="<?= e(truncateText($desc, 160)) ?>"><?php endif; ?>
<meta property="og:url" content="<?= e($canon) ?>">
<meta property="og:site_name" content="<?= e($brand['name']) ?>">
<?php if ($ogImg !== ''): ?><meta property="og:image" content="<?= e($ogImg) ?>">
<meta name="twitter:card" content="summary_large_image"><?php else: ?><meta name="twitter:card" content="summary"><?php endif; ?>
<?= faviconLinkTag($brand['favicon'], $brand['accent']) ?>
<script>
// Anti-flash tema (key 'theme' — konsisten dengan admin).
if (localStorage.theme === 'dark' || (!('theme' in localStorage) && matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
}
</script>
<link rel="stylesheet" href="<?= e(url('/assets/css/frontend.min.css?v=' . rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1'))) ?>">
<style>
<?= scribeThemeCss($theme, $brand['accent']) ?>
html { scroll-behavior: smooth; }
body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 17px; line-height: 1.6; background: var(--fe-canvas); }
html.dark body { background: var(--fe-canvas-dark); }
html.dark { color-scheme: dark; }
html.dark select option, html.dark select optgroup { background-color: #1f2937; color: #f3f4f6; }
h1,h2,h3,.font-display { font-family: ui-serif, Georgia, Cambria, "Times New Roman", serif; }
.fe-accent-text{ color:var(--fe-accent-text); }
.line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.line-clamp-3 { display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }

/* ═══ PREMIUM PASS — DNA visual Averion (mesh, glass, depth) untuk theme publik ═══ */
/* Fixed gradient mesh — permukaan publik ber-tint brand halus (light + dark). */
body::before{
  content:""; position:fixed; inset:0; z-index:-1; pointer-events:none;
  background:
    radial-gradient(52% 48% at 12% 0%, color-mix(in srgb, var(--accent) 8%, transparent), transparent 70%),
    radial-gradient(46% 46% at 90% 6%, color-mix(in srgb, var(--accent) 6%, transparent), transparent 66%),
    linear-gradient(180deg, color-mix(in srgb, var(--accent) 3%, var(--fe-canvas)), var(--fe-canvas) 40%);
}
html.dark body::before{
  background:
    radial-gradient(52% 48% at 12% 0%, color-mix(in srgb, var(--accent) 16%, transparent), transparent 68%),
    radial-gradient(46% 46% at 90% 6%, color-mix(in srgb, var(--accent) 11%, transparent), transparent 62%),
    linear-gradient(180deg, color-mix(in srgb, var(--accent) 8%, var(--fe-canvas-dark)), var(--fe-canvas-dark) 55%);
}
/* Pola titik halus (dekoratif) — dipakai di hero blog, opacity rendah, ikut tema. */
.fe-dots{ background-image: radial-gradient(currentColor 1px, transparent 1.5px); background-size: 22px 22px; color: var(--accent); opacity:.05;
  -webkit-mask-image: radial-gradient(120% 82% at 50% 0%, #000 28%, transparent 76%); mask-image: radial-gradient(120% 82% at 50% 0%, #000 28%, transparent 76%); }
html.dark .fe-dots{ opacity:.08; }
/* Header glass: shadow/border muncul saat scroll. */
#site-header{ transition: box-shadow .22s ease, border-color .22s ease, background .22s ease; border-color: transparent; }
#site-header.scrolled{ border-color: rgba(15,23,42,0.08); box-shadow: 0 10px 30px -18px rgba(15,23,42,0.28); }
html.dark #site-header.scrolled{ border-color: rgba(255,255,255,0.08); box-shadow: 0 12px 34px -18px rgba(0,0,0,0.6); }
/* Popup pencarian header: satu dialog untuk desktop, tablet, dan mobile. */
.fe-search-trigger{display:inline-flex;align-items:center;gap:.45rem;height:2rem;padding:0 .55rem;border:1px solid rgba(148,163,184,.28);border-radius:6px;background:rgba(248,250,252,.72);color:#64748b;font-size:.8125rem;line-height:1;box-shadow:0 1px 2px rgba(15,23,42,.03);transition:border-color .16s ease,background .16s ease,color .16s ease,box-shadow .16s ease}
.fe-search-trigger:hover{border-color:color-mix(in srgb,var(--accent) 40%,transparent);background:color-mix(in srgb,var(--accent) 6%,transparent);color:var(--fe-accent-text);box-shadow:0 6px 16px -12px rgba(15,23,42,.32)}
.fe-search-trigger:focus-visible,.fe-search-mobile:focus-visible,.fe-search-esc:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.fe-search-trigger kbd{margin-left:.2rem;padding:.18rem .32rem;border:1px solid rgba(148,163,184,.3);border-radius:4px;background:rgba(255,255,255,.72);color:#94a3b8;font:600 .625rem/1 ui-sans-serif,system-ui,sans-serif;box-shadow:0 1px 1px rgba(15,23,42,.04)}
html.dark .fe-search-trigger{background:rgba(15,23,42,.54);border-color:rgba(148,163,184,.18);color:#94a3b8}
html.dark .fe-search-trigger kbd{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.1);color:#94a3b8}
.fe-search-mobile{display:flex;width:100%;align-items:center;gap:.65rem;padding:.65rem .75rem;border:1px solid rgba(148,163,184,.26);border-radius:6px;background:rgba(248,250,252,.72);color:#64748b;font-size:.875rem;font-weight:500;text-align:left}
.fe-search-mobile .fe-search-mobile-key{margin-left:auto;color:#94a3b8;font-size:.6875rem}
html.dark .fe-search-mobile{background:rgba(15,23,42,.5);border-color:rgba(148,163,184,.16);color:#cbd5e1}
.fe-search-dialog[hidden]{display:none}
.fe-search-dialog{position:fixed;inset:0;z-index:80;display:flex;align-items:flex-start;justify-content:center;padding:clamp(5rem,14vh,8rem) 1rem 1rem}
.fe-search-backdrop{position:absolute;inset:0;border:0;background:rgba(15,23,42,.52);-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px);cursor:default}
.fe-search-panel{position:relative;width:min(40rem,100%);overflow:hidden;border:1px solid var(--border-color,rgba(15,23,42,.1));border-radius:8px;background:var(--surface-bg,#fff);box-shadow:0 28px 70px -28px rgba(2,6,23,.55);-webkit-backdrop-filter:var(--surface-blur,none);backdrop-filter:var(--surface-blur,none);animation:fe-search-pop .18s cubic-bezier(.2,.8,.2,1)}
.fe-search-form-row{display:flex;align-items:center;gap:.7rem;min-height:3.65rem;padding:0 .9rem;border-bottom:1px solid var(--border-color,rgba(15,23,42,.08))}
.fe-search-form-icon{display:grid;place-items:center;flex:0 0 auto;color:#94a3b8}
.fe-search-input{min-width:0;flex:1;border:0!important;background:transparent!important;padding:.9rem 0!important;color:inherit;font-size:.9375rem;line-height:1.4;box-shadow:none!important}
.fe-search-input:focus{outline:none!important;box-shadow:none!important}
.fe-search-input::placeholder{color:#94a3b8;opacity:1}
.fe-search-esc{flex:0 0 auto;padding:.28rem .42rem;border:1px solid rgba(148,163,184,.32);border-radius:4px;background:rgba(248,250,252,.8);color:#94a3b8;font-size:.6875rem;font-weight:600;line-height:1}
.fe-search-esc:hover{border-color:color-mix(in srgb,var(--accent) 38%,transparent);color:var(--fe-accent-text)}
.fe-search-hint{margin:0;padding:1.75rem 1rem;text-align:center;color:#94a3b8;font-size:.8125rem;line-height:1.45}
.fe-search-hint.is-error{color:#dc2626}
html.dark .fe-search-panel{background:var(--surface-bg-dark,#111827);border-color:var(--border-color-dark,rgba(255,255,255,.1));box-shadow:0 28px 80px -24px rgba(0,0,0,.8)}
html.dark .fe-search-form-row{border-color:var(--border-color-dark,rgba(255,255,255,.08))}
html.dark .fe-search-esc{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.12);color:#94a3b8}
body[data-ui-theme="noir"] .fe-search-panel{background:var(--surface-bg,#251d15);border-color:var(--border-color,rgba(255,234,200,.1));color:#f4ecdc}
body[data-ui-theme="noir"] .fe-search-input{background:transparent!important;color:#f4ecdc}
body[data-ui-theme="noir"] .fe-search-form-row{border-color:rgba(255,234,200,.1)}
body[data-ui-theme="noir"] .fe-search-trigger,body[data-ui-theme="noir"] .fe-search-mobile{background:rgba(255,234,200,.05);border-color:rgba(255,234,200,.12);color:#d9cbb5}
body[data-ui-theme="noir"] .fe-search-trigger kbd{background:rgba(255,255,255,.04);border-color:rgba(255,234,200,.12);color:#b6a892}
html.dark body[data-ui-theme="noir"] .fe-search-panel{background:var(--surface-bg-dark,#1e1811)}
html.search-open,html.search-open body{overflow:hidden}
@keyframes fe-search-pop{from{opacity:0;transform:translateY(-8px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}
@media(max-width:1023px){.fe-search-trigger{width:2rem;padding:0;justify-content:center}.fe-search-trigger-label,.fe-search-trigger kbd{display:none}}
@media(max-width:640px){.fe-search-dialog{padding:4.5rem .75rem .75rem}.fe-search-panel{width:100%}.fe-search-hint{padding:1.35rem .75rem}}
@media(prefers-reduced-motion:reduce){.fe-search-panel{animation:none}.fe-search-trigger{transition:none}}
/* CTA header outline: compact, tetap jelas di seluruh theme dan dark mode. */
.fe-header-cta{ color:var(--fe-accent-text); border-color:color-mix(in srgb, var(--accent) 48%, transparent); background:color-mix(in srgb, var(--accent) 4%, transparent); box-shadow:0 1px 2px rgba(15,23,42,.04); transition:background .18s ease, border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
.fe-header-cta:hover{ background:color-mix(in srgb, var(--accent) 10%, transparent); border-color:var(--accent); box-shadow:0 7px 18px -12px color-mix(in srgb, var(--accent) 70%, transparent); transform:translateY(-1px); }
.fe-header-cta svg{ transition:transform .18s ease; }
.fe-header-cta:hover svg{ transform:translateX(2px); }
html.dark .fe-header-cta{ background:color-mix(in srgb, var(--accent) 8%, transparent); border-color:color-mix(in srgb, var(--accent) 62%, transparent); }
/* Switch dark/light compact seperti toggle native, dengan ikon di kedua state. */
.theme-switch{ position:relative; display:inline-flex; width:3.25rem; height:1.75rem; flex:none; align-items:center; border:1px solid color-mix(in srgb, var(--accent) 30%, rgba(255,255,255,.42)); border-radius:999px; background:color-mix(in srgb, var(--accent) 24%, #0f172a); box-shadow:inset 0 1px 2px rgba(2,6,23,.24), 0 1px 2px rgba(15,23,42,.08); transition:border-color .18s ease, background .18s ease, box-shadow .18s ease; }
.theme-switch:hover{ border-color:color-mix(in srgb, var(--accent) 55%, rgba(255,255,255,.65)); box-shadow:inset 0 1px 2px rgba(2,6,23,.22), 0 5px 14px -10px rgba(15,23,42,.65); }
.theme-switch:focus-visible{ outline:2px solid var(--accent); outline-offset:2px; }
.theme-switch-knob{ position:absolute; z-index:1; left:.1875rem; top:.1875rem; width:1.25rem; height:1.25rem; border-radius:999px; background:#fff; box-shadow:0 2px 7px rgba(2,6,23,.28); transition:transform .2s cubic-bezier(.2,.8,.2,1); }
.theme-switch-icon{ position:absolute; z-index:2; top:50%; display:grid; place-items:center; width:.75rem; height:.75rem; transform:translateY(-50%); pointer-events:none; transition:color .18s ease, opacity .18s ease; }
.theme-switch-sun{ left:.4375rem; color:color-mix(in srgb, var(--accent) 75%, #92400e); }
.theme-switch-moon{ right:.4375rem; color:#e2e8f0; }
html.dark .theme-switch{ background:color-mix(in srgb, var(--accent) 18%, #020617); border-color:color-mix(in srgb, var(--accent) 42%, rgba(255,255,255,.48)); }
html.dark .theme-switch-knob{ transform:translateX(1.625rem); }
html.dark .theme-switch-sun{ color:#fbbf24; }
html.dark .theme-switch-moon{ color:color-mix(in srgb, var(--accent) 78%, #0f172a); }
/* Card lift bertingkat rest→hover. */
.fe-card{ transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; box-shadow: 0 1px 2px rgba(15,23,42,0.04); }
.fe-card:hover{ transform: translateY(-2px); box-shadow: 0 10px 24px -16px rgba(15,23,42,0.20); border-color: color-mix(in srgb, var(--accent) 22%, transparent); }
html.dark .fe-card{ box-shadow: 0 1px 2px rgba(0,0,0,0.35); }
html.dark .fe-card:hover{ box-shadow: 0 14px 30px -18px rgba(0,0,0,0.6); }
.fe-card .fe-cover{ transition: transform .3s ease; }
.fe-card:hover .fe-cover{ transform: scale(1.025); }
/* Featured: shadow lembut + lift ringan. */
.fe-featured{ transition: transform .2s ease, box-shadow .2s ease; box-shadow: 0 6px 20px -16px rgba(15,23,42,0.18); }
.fe-featured:hover{ transform: translateY(-2px); box-shadow: 0 14px 32px -20px rgba(15,23,42,0.24); }
/* Pill kategori idle = glass/outline; aktif = accent solid. */
.fe-pill{ background: color-mix(in srgb, var(--accent) 6%, transparent); border:1px solid color-mix(in srgb, var(--accent) 16%, transparent); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); transition: background .15s, border-color .15s; }
.fe-pill:hover{ background: color-mix(in srgb, var(--accent) 12%, transparent); border-color: color-mix(in srgb, var(--accent) 30%, transparent); }
/* Badge kategori accent soft. */
.fe-badge{ background: color-mix(in srgb, var(--accent) 12%, transparent); color: var(--fe-accent-text); }
/* CTA band glass + gradient accent tipis. */
.fe-band{ position:relative; overflow:hidden; background: linear-gradient(135deg, var(--accent) 0%, color-mix(in srgb, var(--accent) 70%, #000) 100%); box-shadow: 0 24px 60px -24px color-mix(in srgb, var(--accent) 55%, transparent); }
.fe-band::before{ content:""; position:absolute; inset:0; background: radial-gradient(60% 90% at 85% -10%, rgba(255,255,255,0.18), transparent 60%); pointer-events:none; }
.fe-band > *{ position:relative; }

/* ═══ Halaman artikel (B-03): typography prose + TOC + FAQ + share ═══ */
.prose-art{ max-width: 68ch; font-size:1.075rem; line-height:1.8; }
.prose-art > * + *{ margin-top: 1.4rem; }
.prose-art h2{ font-family:ui-serif,Georgia,serif; font-weight:600; font-size:1.72rem; line-height:1.25; margin-top:2.8rem; margin-bottom:.1rem; scroll-margin-top:96px; letter-spacing:-.01em; }
.prose-art h3{ font-family:ui-serif,Georgia,serif; font-weight:600; font-size:1.34rem; line-height:1.3; margin-top:2rem; scroll-margin-top:96px; }
.prose-art p{ color:#374151; }
html.dark .prose-art p{ color:#cbd5e1; }
.prose-art a{ color:var(--fe-accent-text); text-decoration:underline; text-underline-offset:2px; }
.prose-art ul,.prose-art ol{ padding-left:1.5rem; }
.prose-art ul{ list-style:disc; } .prose-art ol{ list-style:decimal; }
.prose-art li{ margin-top:.5rem; line-height:1.75; }
.prose-art li::marker{ color:color-mix(in srgb, var(--accent) 70%, transparent); }
.prose-art blockquote{ border-left:3px solid var(--accent); padding:.5rem 0 .5rem 1.15rem; color:#4b5563; font-style:italic; }
html.dark .prose-art blockquote{ color:#94a3b8; }
.prose-art img{ max-width:100%; height:auto; border-radius:8px; margin-top:1.2rem; }
.prose-art figure{ margin-top:1.4rem; } .prose-art figcaption{ font-size:.85rem; color:#6b7280; text-align:center; margin-top:.4rem; }
.prose-art pre{ background:#0f172a; color:#e2e8f0; padding:1rem; border-radius:8px; overflow-x:auto; font-size:.9rem; }
.prose-art :not(pre) > code{ background:color-mix(in srgb, var(--accent) 10%, transparent); color:var(--fe-accent-text); padding:.1rem .35rem; border-radius:4px; font-size:.9em; }
.prose-art table{ width:100%; border-collapse:collapse; font-size:.95rem; display:block; overflow-x:auto; }
.prose-art th,.prose-art td{ border:1px solid #e5e7eb; padding:.5rem .7rem; text-align:left; }
html.dark .prose-art th,html.dark .prose-art td{ border-color:#1f2937; }
.prose-art th{ background:color-mix(in srgb, var(--accent) 8%, transparent); font-weight:600; }
/* Blok FAQ dari editor (section[data-faq-block]). */
.prose-art section[data-faq-block]{ margin-top:2.4rem; border-top:1px solid #e5e7eb; padding-top:1.5rem; }
html.dark .prose-art section[data-faq-block]{ border-color:#1f2937; }
.prose-art section[data-faq-block] h3{ font-size:1.1rem; margin-top:1.3rem; }
.prose-art section[data-faq-block] p{ margin-top:.4rem; }
/* TOC sticky (desktop) / collapsible (mobile). */
.toc-box{ border:1px solid var(--border-color, rgba(15,23,42,0.08)); }
.toc-link{ display:block; padding:.3rem .55rem; margin:.1rem 0; border-radius:6px; color:#6b7280; font-size:.875rem; line-height:1.4; border-left:2px solid transparent; transition:color .12s, background .12s, border-color .12s; }
.toc-link:hover{ color:var(--fe-accent-text); background:color-mix(in srgb, var(--accent) 7%, transparent); }
.toc-link.lvl-3{ padding-left:1.35rem; font-size:.82rem; }
.toc-link.active{ color:var(--fe-accent-text); font-weight:600; border-left-color:var(--accent); background:color-mix(in srgb, var(--accent) 9%, transparent); }
html.dark .toc-link{ color:#94a3b8; }
/* Tombol share. */
.share-btn{ display:inline-flex; align-items:center; justify-content:center; width:2.5rem; height:2.5rem; border-radius:var(--control-radius,6px); border:1px solid rgba(15,23,42,0.10); color:#4b5563; transition:all .15s; }
.share-btn:hover{ color:#fff; background:var(--accent); border-color:transparent; transform:translateY(-1px); }
html.dark .share-btn{ border-color:rgba(255,255,255,0.12); color:#cbd5e1; }
/* Popup Promo melayang (compact, mengikuti tema aktif). Posisi via [data-pos]. */
#promoPop{ position:fixed; z-index:50; width:min(20rem, calc(100vw - 2rem));
  opacity:0; pointer-events:none; transition:opacity .35s ease; }
#promoPop.show{ opacity:1; pointer-events:auto; }
/* Posisi (jarak aman dari tepi; top-* menghindari header sticky). */
#promoPop[data-pos="bottom-left"]{ bottom:1rem; left:1rem; }
#promoPop[data-pos="bottom-right"]{ bottom:1rem; right:1rem; }
#promoPop[data-pos="bottom-center"]{ bottom:1rem; left:50%; transform:translateX(-50%); }
#promoPop[data-pos="top-left"]{ top:4.75rem; left:1rem; }
#promoPop[data-pos="top-right"]{ top:4.75rem; right:1rem; }
#promoPop[data-pos="top-center"]{ top:4.75rem; left:50%; transform:translateX(-50%); }
/* Animasi masuk di kartu (agar tak bentrok translateX centering). */
#promoPop .promo-card{ position:relative; overflow:hidden; border-radius:var(--card-radius,10px);
  transform:translateY(14px); transition:transform .35s ease;
  background:var(--surface-bg,#fff); border:1px solid var(--border-color, rgba(15,23,42,0.10)); box-shadow:0 22px 50px -20px rgba(15,23,42,0.42); }
#promoPop[data-pos^="top"] .promo-card{ transform:translateY(-14px); }
#promoPop.show .promo-card{ transform:translateY(0); }
html.dark #promoPop .promo-card{ background:var(--surface-bg-dark,#111827); border-color:var(--border-color-dark, rgba(255,255,255,0.10)); }
#promoPop .promo-close{ position:absolute; top:.45rem; right:.45rem; width:1.75rem; height:1.75rem; z-index:2;
  display:inline-flex; align-items:center; justify-content:center; border-radius:6px; background:rgba(15,23,42,0.4); color:#fff; -webkit-backdrop-filter:blur(2px); backdrop-filter:blur(2px); }
#promoPop .promo-close:hover{ background:rgba(15,23,42,0.6); }
#promoPop .promo-img{ display:block; width:100%; height:8.5rem; object-fit:cover; }
#promoPop .promo-body{ padding:.85rem 1rem 1rem; }
#promoPop .promo-cap{ font-size:.9rem; line-height:1.5; color:inherit; }
#promoPop .promo-cta{ display:block; text-align:center; margin-top:.75rem; padding:.55rem .75rem; font-size:.85rem; font-weight:700;
  color:#fff; background:var(--accent); border-radius:var(--fe-btn-radius,6px); }
#promoPop .promo-cta:hover{ opacity:.92; }
@media (max-width:640px){
  /* Kiri/kanan → membentang aman dari tepi; tengah tetap compact; top hindari header. */
  #promoPop[data-pos$="left"],#promoPop[data-pos$="right"]{ left:.75rem; right:.75rem; width:auto; }
  #promoPop[data-pos$="center"]{ width:min(20rem, calc(100vw - 1.5rem)); }
  #promoPop[data-pos^="top"]{ top:4.5rem; }
}
@media (prefers-reduced-motion:reduce){
  #promoPop .promo-card{ transition:none; transform:none; }
  .fe-header-cta,.fe-header-cta svg{ transition:none; }
  .fe-header-cta:hover,.fe-header-cta:hover svg{ transform:none; }
  .theme-switch,.theme-switch-knob,.theme-switch-icon{ transition:none; }
}
/* Navbar link hover premium (aksen halus). */
.fe-navlink{ transition: background .15s ease, color .15s ease; }
.fe-navlink:hover{ background: color-mix(in srgb, var(--accent) 10%, transparent); color: var(--fe-accent-text); }
html.dark .fe-navlink:hover{ background: color-mix(in srgb, var(--accent) 18%, transparent); color: var(--fe-accent-text); }
/* Lockup brand: logo tidak ikut baseline inline dan nama selalu dimulai dari
   titik yang sama di header maupun footer. */
.fe-site-brand{ min-width:0; line-height:0; }
.fe-site-brand{ gap:.5rem !important; }
.fe-brand-logo{ display:flex; align-items:center; justify-content:center; flex:0 0 auto; overflow:hidden; }
.fe-brand-logo-header{ width:1.75rem; height:1.75rem; }
.fe-brand-logo-footer{ width:2.25rem; height:2.25rem; }
.fe-brand-logo > img{ display:block; width:100%; height:100%; max-width:none; max-height:none; object-fit:contain; }
.fe-site-brand-name{ display:block; line-height:1.1; }
.fe-header-shell,.fe-footer-shell{ width:100%; max-width:90rem; }
<?= scribeThemePublicCss() ?>
</style>
<?= (string) ($ctx['head_extra'] ?? '') ?>
</head>
<body class="h-full text-gray-900 dark:text-gray-100 antialiased" data-ui-theme="<?= e($theme['key']) ?>">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:px-3 focus:py-2 focus:rounded-md focus:bg-accent focus:text-white">Lompat ke konten</a>
<header id="site-header" class="sticky top-0 z-30 border-b bg-white/70 dark:bg-gray-950/60 backdrop-blur-md" style="-webkit-backdrop-filter:blur(14px) saturate(180%);backdrop-filter:blur(14px) saturate(180%)">
  <div class="fe-header-shell max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center gap-3">
    <a href="<?= e(url('/')) ?>" class="fe-site-brand flex items-center gap-2 shrink-0" aria-label="<?= e($brand['name']) ?> — Beranda">
      <?php if ($brand['logo'] !== ''): ?>
        <span class="fe-brand-logo fe-brand-logo-header"><?= scribeUploadImageHtml($brand['logo'], $brand['name'], 'object-contain', '28px', true) ?></span>
      <?php else: ?>
        <span class="inline-flex items-center justify-center w-7 h-7 rounded-md text-white" style="background:var(--accent)"><?= icon('pen-line', 'w-[18px] h-[18px]') ?></span>
      <?php endif; ?>
      <span class="fe-site-brand-name font-display font-semibold text-base leading-none whitespace-nowrap"><?= e($brand['name']) ?></span>
    </a>

    <nav class="hidden lg:flex items-center gap-0.5 xl:gap-1.5 ml-4 xl:ml-6 min-w-0" aria-label="Navigasi utama">
      <a href="<?= e(url('/blog')) ?>"
         class="px-2.5 xl:px-3 py-1.5 rounded-md text-[14px] font-medium leading-none whitespace-nowrap <?= $active === 'blog' ? 'text-white' : 'fe-navlink text-gray-600 dark:text-gray-300' ?>"
         <?= $active === 'blog' ? 'style="background:var(--accent)" aria-current="page"' : '' ?>>Blog</a>
      <?php foreach (array_slice($cats, 0, 6) as $c):
          $on = ($active === $c['slug']); ?>
      <a href="<?= e(feCategoryUrl($c['slug'])) ?>"
         class="px-2.5 xl:px-3 py-1.5 rounded-md text-[14px] font-medium leading-none whitespace-nowrap <?= $on ? 'text-white' : 'fe-navlink text-gray-600 dark:text-gray-300' ?>"
         <?= $on ? 'style="background:var(--accent)" aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="ml-auto flex items-center gap-1.5 sm:gap-2 shrink-0">
      <button type="button" class="fe-search-trigger" data-search-open aria-haspopup="dialog" aria-controls="searchDialog">
        <?= icon('search', 'w-4 h-4 shrink-0') ?>
        <span class="fe-search-trigger-label">Cari artikel</span>
        <kbd aria-hidden="true">Ctrl K</kbd>
      </button>

      <?php if ($brand['cta_label'] !== '' && $brand['cta_url'] !== ''): ?>
      <a data-meta-cta="header" href="<?= e($brand['cta_url']) ?>" class="fe-header-cta hidden lg:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border text-[13px] font-semibold whitespace-nowrap shrink-0">
        <span><?= e($brand['cta_label']) ?></span>
        <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
      </a>
      <?php endif; ?>

      <button type="button" id="themeToggle" role="switch" aria-checked="false" aria-label="Gunakan tema gelap" class="theme-switch">
        <span class="theme-switch-knob" aria-hidden="true"></span>
        <span class="theme-switch-icon theme-switch-sun" aria-hidden="true"><?= icon('sun', 'w-3 h-3') ?></span>
        <span class="theme-switch-icon theme-switch-moon" aria-hidden="true"><?= icon('moon', 'w-3 h-3') ?></span>
      </button>

      <button type="button" id="mobileMenuBtn" aria-label="Menu" aria-expanded="false" aria-controls="mobileMenu" class="lg:hidden p-1.5 rounded-md text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 transition">
        <span class="mm-open"><?= icon('menu', 'w-5 h-5') ?></span>
        <span class="mm-close hidden"><?= icon('x', 'w-5 h-5') ?></span>
      </button>
    </div>
  </div>

  <!-- Menu mobile/tablet: cari + kategori (muncul < lg via tombol hamburger) -->
  <div id="mobileMenu" class="lg:hidden hidden border-t border-gray-200 dark:border-gray-800 bg-white/95 dark:bg-gray-950/95">
    <div class="max-w-6xl mx-auto px-4 py-4 space-y-4">
      <button type="button" class="fe-search-mobile" data-search-open aria-haspopup="dialog" aria-controls="searchDialog">
        <?= icon('search', 'w-4 h-4 shrink-0') ?>
        <span>Cari artikel</span>
        <span class="fe-search-mobile-key" aria-hidden="true">Ctrl K</span>
      </button>
      <a href="<?= e(url('/blog')) ?>" class="block px-3 py-2 rounded-md text-sm font-medium <?= $active === 'blog' ? 'text-white' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800' ?>" <?= $active === 'blog' ? 'style="background:var(--accent)" aria-current="page"' : '' ?>>Blog</a>
      <?php if ($cats): ?>
      <nav aria-label="Kategori" class="grid grid-cols-2 gap-1.5">
        <?php foreach (array_slice($cats, 0, 8) as $c):
            $on = ($active === $c['slug']); ?>
        <a href="<?= e(feCategoryUrl($c['slug'])) ?>"
           class="px-3 py-2 rounded-md text-sm font-medium <?= $on ? 'text-white' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800' ?>"
           <?= $on ? 'style="background:var(--accent)" aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>
      <?php if ($brand['cta_label'] !== '' && $brand['cta_url'] !== ''): ?>
      <a data-meta-cta="header" href="<?= e($brand['cta_url']) ?>" class="fe-header-cta flex w-full items-center justify-center gap-2 px-4 py-2 rounded-md border text-sm font-semibold">
        <span><?= e($brand['cta_label']) ?></span>
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
      </a>
      <?php endif; ?>
    </div>
  </div>
</header>
<div id="searchDialog" class="fe-search-dialog" role="dialog" aria-modal="true" aria-label="Cari artikel" hidden>
  <button type="button" class="fe-search-backdrop" data-search-close tabindex="-1" aria-label="Tutup pencarian"></button>
  <div class="fe-search-panel">
    <form id="headerSearchForm" method="get" action="<?= e(url('/search')) ?>" role="search" novalidate>
      <div class="fe-search-form-row">
        <span class="fe-search-form-icon" aria-hidden="true"><?= icon('search', 'w-5 h-5') ?></span>
        <label for="headerSearchInput" class="sr-only">Cari artikel</label>
        <input id="headerSearchInput" class="fe-search-input" name="q" type="search" minlength="2" maxlength="120" autocomplete="off" placeholder="Cari judul, topik, atau kata kunci…" value="<?= e($ctx['q'] ?? '') ?>" aria-describedby="searchDialogHint">
        <button type="button" class="fe-search-esc" data-search-close aria-label="Tutup pencarian">Esc</button>
      </div>
      <p id="searchDialogHint" class="fe-search-hint" aria-live="polite">Ketik minimal 2 huruf untuk mencari.</p>
    </form>
  </div>
</div>
<?php if (!empty($GLOBALS['scribe_preview'])):
    $pv = $GLOBALS['scribe_preview'];
    $statusLabel = [
        'draft' => 'Draft', 'draft_ai' => 'Draft AI', 'scheduled' => 'Terjadwal', 'published' => 'Published',
    ][$pv['status'] ?? ''] ?? ($pv['status'] ?? '');
?>
<div style="background:#0f172a;color:#fff" class="text-sm">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-2.5 flex flex-wrap items-center gap-x-4 gap-y-1.5">
    <span class="inline-flex items-center gap-1.5 font-semibold">
      <?= icon('eye', 'w-4 h-4') ?> Mode Pratinjau
    </span>
    <span class="inline-flex items-center gap-1.5 text-white/80">
      Status: <span class="px-1.5 py-0.5 rounded text-xs font-semibold" style="background:var(--accent)"><?= e($statusLabel) ?></span>
      <?php if (!empty($pv['is_draft'])): ?><span class="text-white/60 text-xs">· menampilkan draft (autosave terbaru)</span><?php endif; ?>
    </span>
    <span class="text-white/60 text-xs">Halaman ini tidak terindeks &amp; tidak tampil publik.</span>
    <?php if (!empty($pv['edit_url'])): ?>
    <a href="<?= e($pv['edit_url']) ?>" class="ml-auto inline-flex items-center gap-1.5 font-medium underline decoration-white/40 hover:decoration-white">Kembali ke editor</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<main id="main" class="min-h-[60vh]">
    <?php
}

/**
 * Tutup dokumen + footer. Powered by kondisional (setting rebrand):
 * ON → link dofollow ke averion.id; OFF → tanpa credit.
 */
function theme_footer(): void
{
    $brand = feBrand();
    $cats  = feNavCategories();
    $year  = date('Y');
    ?>
</main>
<footer class="relative mt-24 border-t border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/40">
  <!-- Hairline aksen gradien di tepi atas -->
  <div aria-hidden="true" class="absolute inset-x-0 -top-px h-px" style="background:linear-gradient(90deg, transparent, color-mix(in srgb, var(--accent) 60%, transparent), transparent)"></div>
  <!-- Glow aksen halus -->
  <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
    <div class="absolute -top-20 left-1/2 -translate-x-1/2 w-[44rem] max-w-full h-40 rounded-full blur-3xl opacity-60" style="background:radial-gradient(closest-side, color-mix(in srgb, var(--accent) 13%, transparent), transparent)"></div>
  </div>

  <div class="fe-footer-shell relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="grid grid-cols-2 gap-x-6 gap-y-10 md:grid-cols-12 md:gap-8">
      <!-- Brand -->
      <div class="col-span-2 md:col-span-5">
        <a href="<?= e(url('/')) ?>" class="fe-site-brand inline-flex items-center gap-2.5" aria-label="<?= e($brand['name']) ?> — Beranda">
          <?php if ($brand['logo'] !== ''): ?>
            <span class="fe-brand-logo fe-brand-logo-footer"><?= scribeUploadImageHtml($brand['logo'], $brand['name'], 'object-contain', '36px') ?></span>
          <?php else: ?>
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-white shadow-sm" style="background:linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 66%, #000))"><?= icon('pen-line', 'w-5 h-5') ?></span>
          <?php endif; ?>
          <span class="fe-site-brand-name font-display font-semibold text-lg"><?= e($brand['name']) ?></span>
        </a>
        <?php if ($brand['tagline'] !== ''): ?>
        <p class="mt-4 max-w-sm text-sm leading-relaxed text-gray-500 dark:text-gray-400"><?= e($brand['tagline']) ?></p>
        <?php endif; ?>
        <?php if ($brand['cta_label'] !== '' && $brand['cta_url'] !== ''): ?>
        <div class="mt-5">
          <a data-meta-cta="header" href="<?= e($brand['cta_url']) ?>" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:text-accent hover:border-current transition-colors">
            <?= icon('external-link', 'w-3.5 h-3.5') ?>
            <?= e($brand['cta_label']) ?>
          </a>
        </div>
        <?php endif; ?>
      </div>

      <!-- Jelajah -->
      <nav class="md:col-span-3" aria-label="Navigasi situs">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Jelajah</p>
        <ul class="space-y-3 text-sm">
          <li>
            <a href="<?= e(url('/')) ?>" class="group inline-flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:text-accent transition-colors">
              <svg class="w-4 h-4 shrink-0 opacity-60 group-hover:opacity-100 transition-opacity" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 9-9 9 9"/><path d="M5 10v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V10"/><path d="M9 22V12h6v10"/></svg>
              <span>Beranda</span>
            </a>
          </li>
          <li>
            <a href="<?= e(url('/blog')) ?>" class="group inline-flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:text-accent transition-colors">
              <?= icon('file-text', 'w-4 h-4 shrink-0 opacity-60 group-hover:opacity-100 transition-opacity') ?>
              <span>Semua Artikel</span>
            </a>
          </li>
          <li>
            <a href="<?= e(url('/search')) ?>" class="group inline-flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:text-accent transition-colors">
              <?= icon('search', 'w-4 h-4 shrink-0 opacity-60 group-hover:opacity-100 transition-opacity') ?>
              <span>Cari Artikel</span>
            </a>
          </li>
        </ul>
      </nav>

      <!-- Kategori -->
      <?php if ($cats): ?>
      <nav class="md:col-span-4" aria-label="Kategori">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Kategori</p>
        <ul class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-3 text-sm">
          <?php foreach (array_slice($cats, 0, 8) as $c): ?>
          <li>
            <a href="<?= e(feCategoryUrl($c['slug'])) ?>" class="group inline-flex items-center gap-1.5 text-gray-500 dark:text-gray-400 hover:text-accent transition-colors">
              <svg class="w-3 h-3 shrink-0 -ml-1 opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
              <span class="truncate"><?= e($c['name']) ?></span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <?php endif; ?>
    </div>

    <div class="mt-12 pt-6 border-t border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-center sm:text-left text-gray-500 dark:text-gray-400">
      <p>&copy; <?= e($year) ?> <?= e($brand['name']) ?>. Seluruh hak cipta dilindungi.</p>
      <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
        <a href="<?= e(url('/login')) ?>" class="inline-flex items-center gap-1.5 font-medium text-gray-500 dark:text-gray-400 hover:text-accent transition-colors" rel="nofollow">
          <?= icon('lock', 'w-3.5 h-3.5') ?>
          Login
        </a>
        <button type="button" id="metaPreferences" class="meta-preferences group inline-flex items-center gap-1.5 font-medium text-gray-500 dark:text-gray-400" hidden>
          <?= icon('shield-check', 'w-3.5 h-3.5 opacity-75 group-hover:opacity-100 transition-opacity') ?>
          <span>Preferensi privasi Meta</span>
        </button>
        <?php if ($brand['powered_by'] && $brand['powered_by_text'] !== ''): ?>
          <?php if ($brand['powered_by_url'] !== ''): ?>
          <p><a href="<?= e($brand['powered_by_url']) ?>"<?= $brand['powered_by_blank'] ? ' target="_blank" rel="noopener"' : '' ?> class="font-medium text-gray-600 dark:text-gray-300 hover:text-accent transition-colors"><?= e($brand['powered_by_text']) ?></a></p>
          <?php else: ?>
          <p><?= e($brand['powered_by_text']) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</footer>

<!-- Tombol "Ke atas" melayang (ikon saja, muncul saat scroll) -->
<button type="button" data-scroll-top aria-label="Ke atas" title="Ke atas"
        class="fixed bottom-5 right-5 z-40 inline-flex items-center justify-center w-11 h-11 rounded-lg text-white shadow-lg opacity-0 translate-y-2 pointer-events-none transition-all duration-200 hover:opacity-90"
        style="background:var(--accent)">
  <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>
</button>

<?php $promo = fePromo(); if ($promo['on']): ?>
<!-- Popup Promo melayang (pojok kiri-bawah, bisa ditutup) -->
<div id="promoPop" data-sig="<?= e($promo['sig']) ?>" data-pos="<?= e($promo['position']) ?>" data-delay-ms="<?= e((string) ($promo['delay'] * 1000)) ?>" role="dialog" aria-label="Promo">
  <div class="promo-card">
    <button type="button" class="promo-close" aria-label="Tutup"><?= icon('x', 'w-4 h-4') ?></button>
    <?php if ($promo['image_rel'] !== ''): ?><?= scribeUploadImageHtml($promo['image_rel'], '', 'promo-img', '(max-width:640px) calc(100vw - 1.5rem), 320px') ?><?php endif; ?>
    <div class="promo-body">
      <p class="promo-cap"><?= nl2br(e($promo['caption'])) ?></p>
      <?php if ($promo['cta_url'] !== ''): ?>
      <a data-meta-cta="promo" href="<?= e($promo['cta_url']) ?>" target="_blank" rel="noopener nofollow" class="promo-cta"><?= e($promo['cta_label'] !== '' ? $promo['cta_label'] : 'Lihat') ?></a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>
<script>
(function () {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    var sync = function () {
        var dark = document.documentElement.classList.contains('dark');
        btn.setAttribute('aria-checked', dark ? 'true' : 'false');
        btn.setAttribute('aria-label', dark ? 'Gunakan tema terang' : 'Gunakan tema gelap');
        btn.title = dark ? 'Gunakan tema terang' : 'Gunakan tema gelap';
    };
    sync();
    btn.addEventListener('click', function () {
        var dark = document.documentElement.classList.toggle('dark');
        localStorage.theme = dark ? 'dark' : 'light';
        sync();
    });
})();
// Efek scroll digabung dalam satu requestAnimationFrame agar tidak memicu
// pengukuran/layout berulang pada setiap event scroll.
(function () {
    var h = document.getElementById('site-header');
    var btn = document.querySelector('[data-scroll-top]');
    if (!h && !btn) return;
    if (btn) btn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    var ticking = false;
    var update = function () {
        var y = window.scrollY;
        if (h) h.classList.toggle('scrolled', y > 8);
        if (btn) {
            var show = y > 400;
            btn.classList.toggle('opacity-0', !show);
            btn.classList.toggle('translate-y-2', !show);
            btn.classList.toggle('pointer-events-none', !show);
            btn.classList.toggle('opacity-100', show);
            btn.classList.toggle('translate-y-0', show);
        }
        ticking = false;
    };
    var onScroll = function () {
        if (!ticking) { ticking = true; requestAnimationFrame(update); }
    };
    update();
    window.addEventListener('scroll', onScroll, { passive: true });
})();
// Menu mobile (hamburger) toggle.
(function () {
    var btn = document.getElementById('mobileMenuBtn');
    var menu = document.getElementById('mobileMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', function () {
        var open = !menu.classList.toggle('hidden'); // true bila menu kini tampil
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.querySelector('.mm-open')?.classList.toggle('hidden', open);
        btn.querySelector('.mm-close')?.classList.toggle('hidden', !open);
    });
})();
// Popup pencarian header: klik, Ctrl/Cmd+K, Escape, validasi inline, dan fokus terjaga.
(function () {
    var dialog = document.getElementById('searchDialog');
    var form = document.getElementById('headerSearchForm');
    var input = document.getElementById('headerSearchInput');
    var hint = document.getElementById('searchDialogHint');
    var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-search-open]'));
    if (!dialog || !form || !input || !hint || !triggers.length) return;
    var returnFocus = null;
    var defaultHint = 'Ketik minimal 2 huruf untuk mencari.';
    var setTriggerState = function (open) { triggers.forEach(function (el) { el.setAttribute('aria-expanded', open ? 'true' : 'false'); }); };
    var resetMobileMenu = function () {
        var menu = document.getElementById('mobileMenu');
        var menuBtn = document.getElementById('mobileMenuBtn');
        if (!menu || !menuBtn || menu.classList.contains('hidden')) return;
        menu.classList.add('hidden');
        menuBtn.setAttribute('aria-expanded', 'false');
        menuBtn.querySelector('.mm-open')?.classList.remove('hidden');
        menuBtn.querySelector('.mm-close')?.classList.add('hidden');
    };
    var openSearch = function (source) {
        if (!dialog.hidden) { input.focus(); input.select(); return; }
        returnFocus = source || document.activeElement;
        resetMobileMenu();
        dialog.hidden = false;
        document.documentElement.classList.add('search-open');
        setTriggerState(true);
        hint.textContent = defaultHint;
        hint.classList.remove('is-error');
        requestAnimationFrame(function () { input.focus(); input.select(); });
    };
    var closeSearch = function () {
        if (dialog.hidden) return;
        dialog.hidden = true;
        document.documentElement.classList.remove('search-open');
        setTriggerState(false);
        if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
    };
    triggers.forEach(function (el) { el.setAttribute('aria-expanded', 'false'); el.addEventListener('click', function () { openSearch(el); }); });
    dialog.querySelectorAll('[data-search-close]').forEach(function (el) { el.addEventListener('click', closeSearch); });
    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); openSearch(document.activeElement); return; }
        if (dialog.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); closeSearch(); return; }
        if (event.key === 'Tab') {
            var esc = dialog.querySelector('.fe-search-esc');
            if (event.shiftKey && document.activeElement === input) { event.preventDefault(); esc.focus(); }
            else if (!event.shiftKey && document.activeElement === esc) { event.preventDefault(); input.focus(); }
        }
    });
    form.addEventListener('submit', function (event) {
        var value = input.value.trim();
        if (value.length >= 2) { input.value = value; return; }
        event.preventDefault();
        hint.textContent = 'Masukkan minimal 2 huruf agar pencarian lebih akurat.';
        hint.classList.add('is-error');
        input.focus();
    });
    input.addEventListener('input', function () { if (input.value.trim().length >= 2) { hint.textContent = defaultHint; hint.classList.remove('is-error'); } });
})();
// Popup Promo: tampil setelah jeda; tombol close menyimpan sig (promo baru → tampil lagi).
(function () {
    var el = document.getElementById('promoPop');
    if (!el) return;
    var sig = el.getAttribute('data-sig');
    try { if (localStorage.getItem('scribe_promo_dismiss') === sig) { el.remove(); return; } } catch (e) {}
    var delayMs = parseInt(el.getAttribute('data-delay-ms') || '3000', 10);
    if (!Number.isFinite(delayMs) || delayMs < 0 || delayMs > 120000) delayMs = 3000;
    setTimeout(function () { el.classList.add('show'); }, delayMs);
    el.querySelector('.promo-close')?.addEventListener('click', function () {
        el.classList.remove('show');
        try { localStorage.setItem('scribe_promo_dismiss', sig); } catch (e) {}
        setTimeout(function () { el.remove(); }, 350);
    });
})();

// ── Lead Magnet: submit tanpa reload/loncat (ganti form jadi state sukses di tempat) ──
(function () {
    function showError(form, msg) {
        var box = form.querySelector('[data-lm-error]');
        if (!box) {
            box = document.createElement('div');
            box.setAttribute('data-lm-error', '');
            box.className = 'rounded-md border border-red-200 text-red-700 bg-red-50 dark:border-red-900 dark:text-red-300 dark:bg-red-950/40 px-3 py-2 text-sm';
            form.insertBefore(box, form.firstChild);
        }
        box.textContent = msg;
    }
    function showSuccess(card, s) {
        var tpl = card.querySelector('template[data-lm-success]');
        if (!tpl) { return false; }
        var node = tpl.content.cloneNode(true);
        var t = node.querySelector('[data-lm-title]'); if (t) t.textContent = s.title || 'Pendaftaran berhasil';
        var d = node.querySelector('[data-lm-desc]'); if (d) d.textContent = s.desc || '';
        var a = node.querySelector('[data-lm-dl]'), lbl = node.querySelector('[data-lm-dl-label]');
        if (a) {
            if (s.download_url) { a.setAttribute('href', s.download_url); a.classList.remove('hidden'); if (lbl) lbl.textContent = s.btn || 'Unduh Materi'; }
            else { a.parentNode && a.parentNode.removeChild(a); }
        }
        card.innerHTML = '';
        card.appendChild(node);
        // Scroll halus HANYA bila card tak sepenuhnya terlihat — jangan ke atas halaman.
        var r = card.getBoundingClientRect();
        if (r.top < 0 || r.bottom > (window.innerHeight || document.documentElement.clientHeight)) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return true;
    }
    document.querySelectorAll('form.fe-lm-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var card = form.closest('[data-lm-card]');
            if (!card || !window.fetch || !('content' in document.createElement('template'))) return; // fallback: submit biasa
            e.preventDefault();
            var btn = form.querySelector('button[type="submit"], button:not([type])');
            var orig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.textContent = 'Mengirim…'; }
            var done = function () { if (btn) { btn.disabled = false; btn.innerHTML = orig; } };
            fetch(form.getAttribute('action'), {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data && data.ok) { if (!showSuccess(card, data.success || {})) { form.submit(); } }
                else { showError(form, (data && data.error) || 'Terjadi kendala. Silakan coba lagi.'); done(); }
            }).catch(function () { showError(form, 'Gagal terhubung. Silakan coba lagi.'); done(); });
        });
    });
})();
</script>
<?php metaPixelRender(); ?>
</body>
</html>
    <?php
}

/**
 * CTA band beranda/artikel (reusable). Mode dari setting rebrand: off | link |
 * newsletter (form + honeypot + flash inline 'subscribe'). Render hanya bila
 * mode != off dan judul terisi.
 */
function theme_cta_band(string $trackingPlacement = '', int $sourceArticleId = 0): void
{
    $brand = feBrand();
    if ($brand['band_mode'] === 'off' || $brand['band_title'] === '') return;
    $subFlash = getFlash('subscribe');
    ?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 my-16">
  <div class="fe-band rounded-lg px-6 py-10 sm:px-12 sm:py-14 text-center text-white">
    <h2 class="font-display font-bold text-2xl sm:text-3xl"><?= e($brand['band_title']) ?></h2>
    <?php if ($brand['band_text'] !== ''): ?><p class="mt-3 text-white/90 max-w-xl mx-auto"><?= e($brand['band_text']) ?></p><?php endif; ?>
    <?php if ($brand['band_mode'] === 'newsletter'): ?>
      <?php if ($subFlash): ?>
      <p class="mt-6 inline-block px-4 py-2 rounded-md bg-white/15 text-white text-sm font-medium"><?= e($subFlash['message']) ?></p>
      <?php else: ?>
      <form method="post" action="<?= e(url('/actions/public/subscribe')) ?>" class="mt-6 flex flex-col sm:flex-row gap-2 max-w-md mx-auto">
        <?= csrfField() ?>
        <input type="hidden" name="source_article_id" value="<?= $sourceArticleId ?>">
        <input type="hidden" name="return_path" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
        <div class="hidden" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <label for="sub_email" class="sr-only">Alamat email</label>
        <input id="sub_email" name="email" type="email" required placeholder="email@anda.com"
               class="flex-1 px-4 py-2.5 rounded-md text-gray-900 bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-white/60">
        <button type="submit" class="px-5 py-2.5 rounded-md bg-gray-900 text-white font-semibold hover:bg-gray-800 whitespace-nowrap"><?= e($brand['band_btn'] !== '' ? $brand['band_btn'] : 'Berlangganan') ?></button>
      </form>
      <?php endif; ?>
    <?php elseif ($brand['band_mode'] === 'link' && $brand['band_btn'] !== '' && $brand['band_url'] !== ''): ?>
      <a<?= $trackingPlacement === 'article' ? ' data-meta-cta="article"' : '' ?> href="<?= e($brand['band_url']) ?>" class="inline-flex items-center mt-6 px-5 py-2.5 rounded-md bg-white text-gray-900 font-semibold hover:bg-gray-100"><?= e($brand['band_btn']) ?></a>
    <?php endif; ?>
  </div>
</section>
    <?php
}

/** URL absolut request saat ini (untuk canonical default), tanpa query kotor. */
function _feCurrentUrl(): string
{
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    $q    = $_SERVER['QUERY_STRING'] ?? '';
    // Ambil host+scheme dari APP_URL agar konsisten dengan konfigurasi.
    $root  = rtrim(APP_URL, '/');
    $bpath = rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/');
    if ($bpath !== '' && str_starts_with($path, $bpath)) {
        $path = substr($path, strlen($bpath));
    }
    $url = $root . ($path === '' ? '/' : $path);
    if ($q !== '' && str_contains($path, 'search')) $url .= '?' . $q; // hanya search yang kanonik berparam
    return $url;
}

// ─── Partial render: card, featured, pagination, section head ─────────────

/** Kartu artikel (grid). Cover 16:9 + judul + excerpt 2 baris + author/tanggal. */
function theme_article_card(array $a): void
{
    ?>
  <article class="fe-card group flex flex-col rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden bg-white dark:bg-gray-900">
    <a href="<?= e(feArticleUrl($a['slug'])) ?>" class="block aspect-[16/9] overflow-hidden bg-gray-100 dark:bg-gray-800">
      <?= feCoverHtml($a, 'fe-cover w-full h-full object-cover', '(min-width:1024px) 30vw, (min-width:640px) 45vw, 100vw') ?>
    </a>
    <div class="flex flex-col flex-1 p-4">
      <?php if (!empty($a['cat_name'])): ?>
      <a href="<?= e(feCategoryUrl($a['cat_slug'])) ?>" class="fe-badge self-start text-[11px] font-semibold px-2 py-0.5 rounded-md mb-2"><?= e($a['cat_name']) ?></a>
      <?php endif; ?>
      <h3 class="font-display font-semibold text-lg leading-snug mb-1.5">
        <a href="<?= e(feArticleUrl($a['slug'])) ?>" class="hover:text-accent"><?= e($a['title']) ?></a>
      </h3>
      <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 mb-3"><?= e(feExcerpt($a, 140)) ?></p>
      <div class="mt-auto flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
        <?php if (!empty($a['author_name'])): ?><span><?= e($a['author_name']) ?></span><span aria-hidden="true">·</span><?php endif; ?>
        <time datetime="<?= e($a['published_at'] ?? '') ?>"><?= e(formatTanggal($a['published_at'] ?? null, false)) ?></time>
      </div>
    </div>
  </article>
    <?php
}

/**
 * Kartu artikel COMPACT (khusus feed "Artikel Terbaru" mode Hybrid). Mobile =
 * horizontal (thumb kiri + konten kanan); desktop (sm+) = vertikal thumb 16:9 +
 * judul 2 baris + kategori/tanggal kecil. Lebih ringan dari theme_article_card.
 * Ikut warna aksen + dark mode. Tidak dipakai di homepage blog /blog.
 */
function theme_article_card_compact(array $a): void
{
    ?>
  <a href="<?= e(feArticleUrl($a['slug'])) ?>" class="group flex sm:block overflow-hidden rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 transition hover:-translate-y-0.5 hover:shadow-sm">
    <!-- Mobile: thumbnail dekoratif (gradient + pola SVG halus, TANPA teks judul). -->
    <div class="sm:hidden w-24 shrink-0 aspect-square overflow-hidden bg-gray-100 dark:bg-gray-800"><?= feCompactThumb($a) ?></div>
    <!-- Desktop (sm+): cover artikel asli, tidak diubah. -->
    <div class="hidden sm:block w-full aspect-[16/9] overflow-hidden bg-gray-100 dark:bg-gray-800">
      <?= feCoverHtml($a, 'w-full h-full object-cover transition duration-300 group-hover:scale-[1.03]', '(min-width:1024px) 24vw, 45vw') ?>
    </div>
    <div class="min-w-0 flex-1 p-2.5 sm:p-3">
      <?php if (!empty($a['cat_name'])): ?>
      <span class="fe-accent-text block text-[10px] font-semibold uppercase tracking-wide truncate"><?= e($a['cat_name']) ?></span>
      <?php endif; ?>
      <h3 class="font-display font-semibold text-sm leading-snug line-clamp-2 mt-0.5 group-hover:text-accent"><?= e($a['title']) ?></h3>
      <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"><time datetime="<?= e($a['published_at'] ?? '') ?>"><?= e(formatTanggal($a['published_at'] ?? null, false)) ?></time></div>
    </div>
  </a>
    <?php
}

/** Featured (artikel terbaru): gambar kiri, judul besar + excerpt + meta kanan. */
function theme_featured(array $a): void
{
    ?>
  <article class="fe-featured group grid md:grid-cols-2 gap-6 lg:gap-10 items-center rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden bg-white dark:bg-gray-900">
    <a href="<?= e(feArticleUrl($a['slug'])) ?>" class="block aspect-[16/9] md:aspect-auto md:h-full overflow-hidden bg-gray-100 dark:bg-gray-800">
      <?= feCoverHtml($a, 'fe-cover w-full h-full object-cover', '(min-width:768px) 50vw, 100vw', true) ?>
    </a>
    <div class="p-5 lg:p-7">
      <?php if (!empty($a['cat_name'])): ?>
      <a href="<?= e(feCategoryUrl($a['cat_slug'])) ?>" class="fe-badge inline-block text-[11px] font-semibold px-2.5 py-1 rounded-md mb-2.5"><?= e($a['cat_name']) ?></a>
      <?php endif; ?>
      <h2 class="font-display font-bold text-xl sm:text-2xl lg:text-[1.85rem] leading-tight mb-2.5">
        <a href="<?= e(feArticleUrl($a['slug'])) ?>" class="hover:text-accent"><?= e($a['title']) ?></a>
      </h2>
      <p class="text-[15px] text-gray-600 dark:text-gray-300 line-clamp-2 mb-4"><?= e(feExcerpt($a, 180)) ?></p>
      <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <?php if (!empty($a['author_name'])): ?><span><?= e($a['author_name']) ?></span><span aria-hidden="true">·</span><?php endif; ?>
        <time datetime="<?= e($a['published_at'] ?? '') ?>"><?= e(formatTanggal($a['published_at'] ?? null, false)) ?></time>
      </div>
    </div>
  </article>
    <?php
}

/**
 * Pagination. $baseUrl = URL dasar (tanpa query page), $extraQuery = query lain
 * yang harus dipertahankan (mis. ['q'=>'...']). Halaman terakhir tak melewati batas.
 */
function theme_pagination(int $page, int $totalPages, string $baseUrl, array $extraQuery = []): void
{
    if ($totalPages <= 1) return;
    $link = function (int $p) use ($baseUrl, $extraQuery): string {
        $q = $extraQuery;
        if ($p > 1) $q['page'] = $p;
        return $baseUrl . ($q ? ('?' . http_build_query($q)) : '');
    };
    $btn = 'inline-flex items-center justify-center min-w-9 h-9 px-3 rounded-md text-sm font-medium border';
    $on  = ' text-white border-transparent';
    $off = ' border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800';
    ?>
  <nav class="mt-10 flex items-center justify-center gap-1.5" aria-label="Halaman">
    <?php if ($page > 1): ?>
    <a href="<?= e($link($page - 1)) ?>" class="<?= $btn . $off ?>" rel="prev">Sebelumnya</a>
    <?php endif; ?>
    <?php
    // Jendela ringkas: pertama, sekitar aktif, terakhir.
    $show = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || ($i >= $page - 1 && $i <= $page + 1)) $show[] = $i;
    }
    $prev = 0;
    foreach ($show as $i):
        if ($prev && $i - $prev > 1) echo '<span class="px-1 text-gray-400">…</span>';
        $prev = $i;
        if ($i === $page): ?>
      <span class="<?= $btn . $on ?>" style="background:var(--accent)" aria-current="page"><?= $i ?></span>
    <?php else: ?>
      <a href="<?= e($link($i)) ?>" class="<?= $btn . $off ?>"><?= $i ?></a>
    <?php endif;
    endforeach; ?>
    <?php if ($page < $totalPages): ?>
    <a href="<?= e($link($page + 1)) ?>" class="<?= $btn . $off ?>" rel="next">Berikutnya</a>
    <?php endif; ?>
  </nav>
    <?php
}
