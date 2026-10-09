<?php
// 404 publik bertema (theme-default, B-01). Dipakai router fallback + listing
// (slug kategori/tag tak ditemukan). Identitas customer (rebrand), bukan Averion.
require_once __DIR__ . '/../views/theme-default/layout.php';

http_response_code(404);
theme_head(['title' => '404 — ' . feBrand()['name'], 'description' => 'Halaman tidak ditemukan.']);
?>
<section class="fe-not-found" aria-labelledby="notFoundTitle">
  <div class="fe-not-found-glow" aria-hidden="true"></div>
  <div class="fe-not-found-card">
    <div class="fe-not-found-mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path><path d="M8.8 8.8 13.2 13.2M13.2 8.8 8.8 13.2"></path>
      </svg>
    </div>
    <p class="fe-not-found-code">404</p>
    <h1 id="notFoundTitle" class="fe-not-found-title">Halaman tidak ditemukan</h1>
    <p class="fe-not-found-copy">Maaf, halaman yang Anda cari tidak tersedia atau telah dipindahkan.</p>
    <div class="fe-not-found-actions">
      <a href="<?= e(url('/')) ?>" class="fe-not-found-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 9-9 9 9"></path><path d="M5 10v10a2 2 0 0 0 2 2h14V10"></path><path d="M9 22V12h6v10"></path></svg>
        <span>Kembali ke beranda</span>
      </a>
      <a href="<?= e(url('/search')) ?>" class="fe-not-found-secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
        <span>Cari artikel</span>
      </a>
    </div>
  </div>
</section>
<style>
#main{min-height:0}
#main + footer{margin-top:0!important}
.fe-not-found{position:relative;isolation:isolate;display:grid;place-items:center;overflow:hidden;padding:clamp(3.5rem,8vw,6rem) 1rem clamp(4rem,9vw,6.5rem)}
.fe-not-found-glow{position:absolute;z-index:-1;inset:8% 8% auto;height:72%;pointer-events:none;background:radial-gradient(circle at 50% 42%,color-mix(in srgb,var(--accent) 11%,transparent),transparent 66%);filter:blur(18px);opacity:.8}
.fe-not-found-card{position:relative;width:min(100%,44rem);padding:clamp(1.6rem,5vw,3rem);overflow:hidden;text-align:center;border:var(--border-w,1px) solid var(--border-color,rgba(15,23,42,.1));border-radius:var(--card-radius,8px);background:var(--surface-bg,#fff);box-shadow:0 28px 80px -52px rgba(15,23,42,.55);-webkit-backdrop-filter:var(--surface-blur,none);backdrop-filter:var(--surface-blur,none)}
.fe-not-found-card::before{content:"";position:absolute;inset:0 0 auto;height:3px;background:linear-gradient(90deg,transparent,var(--accent),transparent);opacity:.72}
.fe-not-found-mark{display:grid;place-items:center;width:2.75rem;height:2.75rem;margin:0 auto .85rem;color:var(--fe-accent-text);border:1px solid color-mix(in srgb,var(--accent) 30%,transparent);border-radius:var(--fe-btn-radius,5px);background:color-mix(in srgb,var(--accent) 7%,transparent)}
.fe-not-found-mark svg{width:1.3rem;height:1.3rem}
.fe-not-found-code{color:var(--fe-accent-text);font-size:clamp(2.6rem,8vw,4.5rem);font-weight:800;line-height:1;letter-spacing:-.055em}
.fe-not-found-title{margin-top:.8rem;font-family:var(--font-display,ui-serif,Georgia,serif);font-size:clamp(1.55rem,4vw,2.25rem);font-weight:var(--fe-head-weight,700);line-height:1.18;letter-spacing:var(--fe-head-tracking,-.01em)}
.fe-not-found-copy{max-width:34rem;margin:.85rem auto 0;color:#64748b;font-size:clamp(.925rem,2vw,1rem);line-height:1.7}
.fe-not-found-actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.75rem;margin-top:1.75rem}
.fe-not-found-primary,.fe-not-found-secondary{display:inline-flex;align-items:center;justify-content:center;gap:.55rem;min-height:2.7rem;padding:.7rem 1rem;border-radius:var(--fe-btn-radius,5px);font-size:.875rem;font-weight:700;line-height:1.2;transition:transform .18s ease,border-color .18s ease,background .18s ease,box-shadow .18s ease,color .18s ease}
.fe-not-found-primary{color:#fff;border:1px solid var(--accent);background:var(--accent);box-shadow:0 10px 24px -16px color-mix(in srgb,var(--accent) 85%,#000)}
.fe-not-found-secondary{color:#334155;border:1px solid var(--border-color,rgba(15,23,42,.12));background:color-mix(in srgb,var(--surface-bg,#fff) 80%,transparent)}
.fe-not-found-primary:hover,.fe-not-found-secondary:hover{transform:translateY(-1px)}
.fe-not-found-primary:hover{box-shadow:0 14px 30px -18px color-mix(in srgb,var(--accent) 90%,#000)}
.fe-not-found-secondary:hover{color:var(--fe-accent-text);border-color:color-mix(in srgb,var(--accent) 42%,transparent);background:color-mix(in srgb,var(--accent) 6%,var(--surface-bg,#fff))}
.fe-not-found-primary:focus-visible,.fe-not-found-secondary:focus-visible{outline:2px solid var(--accent);outline-offset:3px}
.fe-not-found-primary svg,.fe-not-found-secondary svg{width:1rem;height:1rem;flex:0 0 auto}
html.dark .fe-not-found-card{color:#f8fafc;border-color:var(--border-color-dark,rgba(255,255,255,.1));background:var(--surface-bg-dark,#111827);box-shadow:0 30px 86px -50px rgba(0,0,0,.9)}
html.dark .fe-not-found-copy{color:#94a3b8}
html.dark .fe-not-found-secondary{color:#cbd5e1;border-color:var(--border-color-dark,rgba(255,255,255,.12));background:color-mix(in srgb,var(--surface-bg-dark,#111827) 84%,transparent)}
html.dark .fe-not-found-secondary:hover{color:#fff;background:color-mix(in srgb,var(--accent) 9%,var(--surface-bg-dark,#111827))}
@media(max-width:559px){.fe-not-found{padding:2.75rem .9rem 3.5rem}.fe-not-found-card{padding:1.6rem 1.1rem}.fe-not-found-actions{display:grid;grid-template-columns:1fr;margin-top:1.5rem}.fe-not-found-primary,.fe-not-found-secondary{width:100%}}
@media(prefers-reduced-motion:reduce){.fe-not-found-primary,.fe-not-found-secondary{transition:none}}
</style>
<?php theme_footer(); ?>