<?php
// Settings > Appearance (admin). UI Theme preset + Sidebar/Topbar style +
// preview langsung. HANYA memengaruhi dashboard admin (bukan frontend publik).
require_once __DIR__ . '/_shell.php';

$presets  = scribeThemePresets();
$sidebars = scribeSidebarStyles();
$topbars  = scribeTopbarStyles();

$curTheme   = getSetting('ui_theme', 'default');
$curSidebar = getSetting('ui_sidebar_style', 'classic');
$curTopbar  = getSetting('ui_topbar_style', 'default');
$accent     = getSetting('accent_color', '#6366f1');
if (!isset($presets[$curTheme])) $curTheme = 'default';

admin_shell_top('Appearance', '/admin/settings');
settings_tabs('/admin/appearance');
?>
<p class="text-sm text-gray-500 dark:text-gray-400 mb-4 max-w-3xl">UI Theme kini diterapkan ke <strong>dashboard admin</strong> dan <strong>frontend publik</strong> (Homepage Blog, halaman artikel, &amp; BioLink) — tiap tema punya karakter berbeda (tipografi, spacing, radius, border, shadow, kartu, tombol, header, latar). Sidebar &amp; Topbar Style hanya memengaruhi admin.</p>
<form method="post" action="<?= e(url('/actions/admin/save-appearance')) ?>" class="grid lg:grid-cols-3 gap-4">
  <?= csrfField() ?>

  <div class="lg:col-span-2 space-y-4">
    <!-- UI Theme preset -->
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
      <h2 class="font-display font-semibold text-sm mb-3">UI Theme</h2>
      <div class="grid sm:grid-cols-2 gap-2">
        <?php foreach ($presets as $key => $p): ?>
        <label class="flex items-start gap-2 rounded-md border p-3 cursor-pointer transition <?= $curTheme === $key ? 'border-transparent ring-2' : 'border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50' ?>" <?= $curTheme === $key ? 'style="--tw-ring-color:var(--accent)"' : '' ?>>
          <input type="radio" name="ui_theme" value="<?= e($key) ?>" class="mt-0.5 ap-theme" <?= $curTheme === $key ? 'checked' : '' ?>>
          <span>
            <span class="text-sm font-medium block"><?= e($p['label']) ?></span>
            <span class="text-xs text-gray-500 dark:text-gray-400"><?= e($p['description']) ?></span>
          </span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Sidebar + Topbar -->
    <div class="grid sm:grid-cols-2 gap-4">
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
        <h2 class="font-display font-semibold text-sm mb-3">Sidebar Style</h2>
        <div class="space-y-2">
          <?php foreach ($sidebars as $key => $label): ?>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" name="ui_sidebar_style" value="<?= e($key) ?>" class="ap-sidebar" <?= $curSidebar === $key ? 'checked' : '' ?>> <?= e($label) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
        <h2 class="font-display font-semibold text-sm mb-3">Topbar Style</h2>
        <div class="space-y-2">
          <?php foreach ($topbars as $key => $label): ?>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" name="ui_topbar_style" value="<?= e($key) ?>" class="ap-topbar" <?= $curTopbar === $key ? 'checked' : '' ?>> <?= e($label) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan Appearance</button>
  </div>

  <!-- Preview langsung -->
  <div class="lg:col-span-1">
    <div class="sticky top-20">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Pratinjau</span>
        <label class="flex items-center gap-1.5 text-xs cursor-pointer"><input type="checkbox" id="pvDark"> Mode gelap</label>
      </div>
      <div id="previewRoot" class="rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden" data-ui-theme="<?= e($curTheme) ?>" data-sidebar="<?= e($curSidebar) ?>" data-topbar="<?= e($curTopbar) ?>" data-kpi="hero">
        <div class="flex h-52">
          <div class="pv-side w-20 shrink-0 p-2 space-y-1 text-[10px]">
            <div class="pv-brand font-semibold mb-2">Blog</div>
            <div class="pv-nav-active rounded px-1.5 py-1">Dashboard</div>
            <div class="px-1.5 py-1 opacity-70">Artikel</div>
            <div class="px-1.5 py-1 opacity-70">Kredit</div>
          </div>
          <div class="flex-1 flex flex-col min-w-0">
            <div class="pv-top flex items-center px-2 text-[10px] font-medium">Dashboard</div>
            <div class="pv-canvas flex-1 p-2 grid grid-cols-2 pv-grid">
              <div class="pv-card pv-hero"><div class="pv-chip"></div><div class="text-sm font-bold leading-none mt-1">12</div><div class="text-[9px] opacity-70 mt-0.5">Published</div></div>
              <div class="pv-card"><div class="pv-chip"></div><div class="text-sm font-bold leading-none mt-1">3</div><div class="text-[9px] opacity-60 mt-0.5">Draft</div></div>
            </div>
          </div>
        </div>
      </div>
      <div class="mt-2 flex items-center gap-2 text-xs text-gray-500"><span class="w-4 h-4 rounded" style="background:var(--accent)"></span> Warna aksen aktif</div>

      <!-- Pratinjau publik (Blog) -->
      <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mt-4 mb-2">Pratinjau publik (Blog)</p>
      <div id="pvPublic" data-ui-theme="<?= e($curTheme) ?>">
        <div class="pp-head"><span class="pp-logo"></span> <?= e(blogName()) ?> <span class="pp-chip">Blog</span></div>
        <div class="pp-body">
          <div class="pp-title">Blog</div>
          <div class="pp-card">
            <div class="pp-thumb"></div>
            <div class="pp-cardbody">
              <span class="pp-badge">Kategori</span>
              <div class="pp-h">Judul Artikel Contoh</div>
              <div class="pp-ex">Ringkasan singkat artikel sebagai contoh tampilan kartu.</div>
            </div>
          </div>
          <span class="pp-btn">Tombol Aksen</span>
        </div>
      </div>
    </div>
  </div>
</form>

<style>
/* Preview merefleksikan KARAKTER: density(--pv-pad/--pv-gap), border(--pv-bw/--pv-bc),
   shadow(--pv-shadow), radius, surface, topbar-h, KPI hero/flat. */
#previewRoot .pv-side { background:#fff; color:#374151; }
#previewRoot .pv-top  { background: var(--pv-surface,#fff); color:#111827; border-bottom:1px solid #e5e7eb; height: var(--pv-topbar-h, 26px); }
#previewRoot .pv-canvas { background: var(--pv-canvas, #f9fafb); }
#previewRoot .pv-grid { gap: var(--pv-gap, 6px); }
#previewRoot .pv-card { background: var(--pv-surface,#fff); border: var(--pv-bw,1px) solid var(--pv-bc,#e5e7eb); border-radius: var(--pv-card-radius, 8px); box-shadow: var(--pv-shadow, none); padding: var(--pv-pad, 6px); color:#111827; }
#previewRoot .pv-chip { width:12px; height:12px; border-radius:4px; background: color-mix(in srgb, var(--pv-accent) 16%, transparent); }
#previewRoot[data-kpi="hero"] .pv-hero { background: linear-gradient(135deg, var(--pv-accent), color-mix(in srgb, var(--pv-accent) 62%, #000)); border-color:transparent; color:#fff; }
#previewRoot[data-kpi="hero"] .pv-hero .pv-chip { background: rgba(255,255,255,.28); }
#previewRoot[data-kpi="flat"] .pv-hero { background: var(--pv-surface,#fff); }
#previewRoot .pv-nav-active { background: var(--pv-accent); color:#fff; border-radius: var(--pv-card-radius,6px); }
#previewRoot[data-sidebar="midnight"] .pv-side { background:#0f172a; color:#cbd5e1; }
#previewRoot[data-sidebar="brand"] .pv-side { background: var(--pv-accent); color:rgba(255,255,255,.9); }
#previewRoot[data-topbar="brand"] .pv-top { background: var(--pv-accent); color:#fff; border-color:transparent; }
#previewRoot[data-topbar="dark"] .pv-top { background:#0f172a; color:#f1f5f9; border-color:#1e293b; }
#previewRoot.pv-dark .pv-canvas { background: var(--pv-canvas-dark, #0b0b14); }
#previewRoot.pv-dark .pv-card { background: var(--pv-surface-dark,#111827); border-color: var(--pv-bc-dark,#1f2937); color:#e5e7eb; }
#previewRoot.pv-dark[data-kpi="hero"] .pv-hero { color:#fff; }
#previewRoot.pv-dark .pv-side { background:#111827; color:#cbd5e1; }
#previewRoot.pv-dark .pv-top { background:#111827; color:#e5e7eb; border-color:#1f2937; }
#previewRoot.pv-dark[data-sidebar="brand"] .pv-side { background: var(--pv-accent); }
#previewRoot.pv-dark[data-topbar="brand"] .pv-top { background: var(--pv-accent); }

/* Pratinjau publik (Blog) — merefleksikan karakter tema di frontend. */
#pvPublic { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; background: var(--pv-canvas-fe,#f7f8fb); color:#111827; }
#pvPublic .pp-head { display:flex; align-items:center; gap:5px; padding:6px 8px; font-size:10px; font-weight:700; background: var(--pv-header,#fff); border-bottom:1px solid var(--pv-header-border,#e5e7eb); }
#pvPublic .pp-logo { width:11px; height:11px; border-radius:3px; background: var(--pv-accent); }
#pvPublic .pp-chip { font-size:8px; color:#fff; background: var(--pv-accent); padding:1px 5px; border-radius:var(--pv-ctl-radius,6px); }
#pvPublic .pp-body { padding:9px; }
#pvPublic .pp-title { font-family:Fraunces,serif; font-weight:var(--pv-head-weight,700); letter-spacing:var(--pv-head-tracking,-.01em); font-size:16px; text-align:center; margin-bottom:8px; }
#pvPublic .pp-card { background: var(--pv-surface,#fff); border: var(--pv-bw,1px) solid var(--pv-bc,#e5e7eb); border-radius: var(--pv-card-radius,8px); box-shadow: var(--pv-shadow,none); overflow:hidden; }
#pvPublic .pp-thumb { height:36px; background:linear-gradient(135deg,#2f7d78,#1a4f57); }
#pvPublic .pp-cardbody { padding:7px; }
#pvPublic .pp-badge { display:inline-block; font-size:8px; font-weight:700; padding:1px 5px; border-radius:var(--pv-ctl-radius,6px); background: color-mix(in srgb, var(--pv-accent) 14%, transparent); color: var(--pv-accent); }
#pvPublic .pp-h { font-family:Fraunces,serif; font-weight:600; font-size:11px; margin-top:3px; line-height:1.2; }
#pvPublic .pp-ex { font-size:9px; color:#6b7280; margin-top:2px; line-height:1.3; }
#pvPublic .pp-btn { display:block; text-align:center; margin-top:8px; font-size:10px; font-weight:700; color:#fff; padding:5px; border-radius:var(--pv-btn-radius,6px); background: var(--pv-accent); }
/* Karakter per-tema di pratinjau publik. */
#pvPublic[data-ui-theme="vivid"] .pp-btn { background:linear-gradient(135deg, var(--pv-accent), color-mix(in srgb, var(--pv-accent) 55%, #000)); }
#pvPublic[data-ui-theme="corporate"] .pp-badge { text-transform:uppercase; letter-spacing:.04em; border-radius:3px; }
#pvPublic[data-ui-theme="minimal"] .pp-card { box-shadow:none; border-width:0; }
#pvPublic[data-ui-theme="minimal"] .pp-badge { background:transparent; border:1px solid color-mix(in srgb, var(--pv-accent) 30%, transparent); }
#pvPublic[data-ui-theme="noir"] { background:#17120c; color:#efe6d6; }
#pvPublic[data-ui-theme="noir"] .pp-card { background:#251d15; }
#pvPublic[data-ui-theme="noir"] .pp-ex { color:#b6a892; }
#pvPublic[data-ui-theme="noir"] .pp-head { background:rgba(30,24,17,.85); border-color:rgba(255,234,200,.12); color:#f4ecdc; }
</style>
<script>
const AP = { presets: <?= json_encode($presets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, accent: <?= json_encode($accent) ?> };
const pv = document.getElementById('previewRoot');
// Skala rem preset → px kecil untuk mini-preview.
function remToPx(v, k){ const n = parseFloat(v); return isNaN(n) ? '6px' : (n * k).toFixed(1) + 'px'; }
function applyPreview() {
  const theme = document.querySelector('.ap-theme:checked').value;
  const side  = document.querySelector('.ap-sidebar:checked').value;
  const top   = document.querySelector('.ap-topbar:checked').value;
  const p = AP.presets[theme] || {};
  pv.dataset.uiTheme = theme; pv.dataset.sidebar = side; pv.dataset.topbar = top;
  pv.dataset.kpi = (p.kpi === 'flat') ? 'flat' : 'hero';
  const set = (k, v) => pv.style.setProperty(k, v);
  set('--pv-accent', AP.accent);
  set('--pv-canvas', p['--bg-canvas'] || '#f9fafb');
  set('--pv-canvas-dark', p['--bg-canvas-dark'] || '#0b0b14');
  set('--pv-card-radius', p['--card-radius'] || '8px');
  set('--pv-shadow', p['--card-shadow'] || 'none');
  set('--pv-pad', remToPx(p['--pad'], 5));      // 1.25rem → ~6px
  set('--pv-gap', remToPx(p['--gap'], 5));
  set('--pv-bw', p['--border-w'] || '1px');
  set('--pv-bc', p['--border-color'] || '#e5e7eb');
  set('--pv-bc-dark', p['--border-color-dark'] || '#1f2937');
  set('--pv-surface', (p['--surface-bg'] || '#fff').replace(/rgba?\([^)]*\)/, '#ffffff')); // solid utk preview
  set('--pv-surface-dark', (p['--surface-bg-dark'] || '#111827').replace(/rgba?\([^)]*\)/, '#111827'));
  set('--pv-topbar-h', (parseFloat(p['--topbar-h'] || '54') * 0.5).toFixed(0) + 'px'); // 54px → 27px
  // Pratinjau publik (Blog)
  set('--pv-ctl-radius', p['--control-radius'] || '6px');
  set('--pv-btn-radius', p['--fe-btn-radius'] || '6px');
  set('--pv-head-weight', p['--fe-head-weight'] || '700');
  set('--pv-head-tracking', p['--fe-head-tracking'] || '-0.01em');
  set('--pv-canvas-fe', p['--fe-canvas'] || '#f7f8fb');
  set('--pv-header', (p['--fe-header-bg'] || '#ffffff').replace(/rgba?\([^)]*\)/, '#ffffff'));
  set('--pv-header-border', p['--fe-header-border'] || '#e5e7eb');
  const pub = document.getElementById('pvPublic'); if (pub) pub.dataset.uiTheme = theme;
}
document.querySelectorAll('.ap-theme, .ap-sidebar, .ap-topbar').forEach(el => el.addEventListener('change', applyPreview));
document.getElementById('pvDark').addEventListener('change', e => pv.classList.toggle('pv-dark', e.target.checked));
applyPreview();
</script>
<?php admin_shell_bottom(); ?>
