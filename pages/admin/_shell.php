<?php
// Shell admin: layout sidebar + topbar. Konvensi keras: Tailwind CDN, Vanilla JS,
// Plus Jakarta Sans + Fraunces, ikon inline SVG Lucide (tanpa emoji), radius 6-8px
// squared, dark/light + localStorage. Panggil admin_shell_top() lalu isi konten,
// tutup dengan admin_shell_bottom().
require_once __DIR__ . '/../../helpers/theme-config.php';

// $vendorIdentity=true → halaman komunikasi vendor→customer (update/enforcement):
// TIDAK membaca setting rebrand, selalu identitas Averion (tanpa logo/nama customer).
function admin_shell_top(string $title, string $active = '', bool $vendorIdentity = false): void
{
    $user = $_SESSION['user_name'] ?? 'Pengguna';
    $role = currentRole();
    $accent   = getSetting('accent_color', '#6366f1');
    $blogName = $vendorIdentity ? 'Averion SEO Engine' : blogName();
    $logo     = $vendorIdentity ? '' : getSetting('brand_logo', '');
    $favicon  = $vendorIdentity ? '' : getSetting('brand_favicon', '');
    $theme    = scribeThemeConfig();
    $upBase   = rtrim(UPLOAD_URL, '/');

    // Menu: [path, label, ikon, role-min]. 'admin' hanya untuk admin.
    $nav = [
        ['/admin',           'Dashboard', 'layout-dashboard', 'staff'],
        ['/admin/articles',  'Artikel',   'file-text',        'staff'],
        ['/admin/content-health', 'SEO Health', 'activity', 'staff'],
        ['/admin/biolink',   'Biolink',   'link',             'admin'],
        ['/admin/lead-magnets', 'Lead Magnet', 'download',      'admin'],
        ['/admin/subscribers',  'Subscribers', 'mail',          'admin'],
        ['/admin/email-sequences', 'Email Sequence', 'send',    'admin'],
        ['/admin/redirects', 'Redirects', 'arrow-left-right', 'admin'],
        ['/admin/credits',   'Kredit AI', 'coins',            'admin'],
        ['/admin/users',     'Pengguna',  'users',            'admin'],
        ['/admin/settings',  'Pengaturan','settings',         'admin'],
        ['/admin/update',    'Pembaruan', 'download',         'admin'],
        ['/admin/profile',   'Profil',    'user',             'staff'],
        ['/admin/license',   'Lisensi',   'shield-check',     'staff'],
    ];
    ?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — <?= e($blogName) ?></title>
<?= faviconLinkTag($favicon, $accent) ?>
<script>
// Terapkan tema sebelum paint (anti-flash).
if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
}
</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    darkMode: 'class',
    theme: { extend: {
        fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            display: ['Fraunces', 'ui-serif', 'Georgia', 'serif'],
        },
        colors: { accent: 'var(--accent)' },
        borderRadius: { DEFAULT: '6px', md: '6px', lg: '8px', xl: '8px' },
    } },
};
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
<?= scribeThemeCss($theme, $accent) ?>
body { font-family: '"Plus Jakarta Sans"', ui-sans-serif, system-ui, sans-serif; background: var(--bg-canvas); }
html.dark body { background: var(--bg-canvas-dark); }
/* Kontrol native (scrollbar, date picker, dll) ikut gelap di mode gelap. */
html.dark { color-scheme: dark; }
/* Popup <select> gelap & terbaca — styling <option> langsung (andal di Chrome/Firefox). */
html.dark select option, html.dark select optgroup { background-color: #1f2937; color: #f3f4f6; }
h1,h2,h3,.font-display { font-family: 'Fraunces', ui-serif, Georgia, serif; }
/* Preset UI Theme: radius kartu/kontrol + shadow (porting CSS-var Averion). */
[data-ui-theme] .rounded-lg { border-radius: var(--card-radius); }
[data-ui-theme] .rounded-md { border-radius: var(--control-radius); }
[data-ui-theme] .shadow-sm  { box-shadow: var(--card-shadow); }
/* Sidebar Style */
[data-sidebar="midnight"] #sidebar { background:#0f172a; border-color:#1e293b; }
[data-sidebar="midnight"] #sidebar .side-brand-name { color:#f1f5f9; }
[data-sidebar="midnight"] #sidebar a:not(.side-active) { color:#cbd5e1; }
[data-sidebar="midnight"] #sidebar a:not(.side-active):hover { background:#1e293b; }
[data-sidebar="brand"] #sidebar { background:var(--accent); border-color:transparent; }
[data-sidebar="brand"] #sidebar .side-brand-name { color:#fff; }
[data-sidebar="brand"] #sidebar a:not(.side-active) { color:rgba(255,255,255,.85); }
[data-sidebar="brand"] #sidebar a:not(.side-active):hover { background:rgba(255,255,255,.14); }
/* Topbar Style */
[data-topbar="brand"] #topbar { background:var(--accent); color:#fff; border-color:transparent; }
[data-topbar="brand"] #topbar .topbar-title { color:#fff; }
[data-topbar="dark"] #topbar { background:#0f172a; color:#f1f5f9; border-color:#1e293b; }
[data-topbar="dark"] #topbar .topbar-title { color:#f1f5f9; }
/* Kontras kontrol topbar saat Brand/Command Dark (tombol tema + nama/role user). */
[data-topbar="brand"] #topbar .tb-toggle,[data-topbar="dark"] #topbar .tb-toggle{ color:#fff; }
[data-topbar="brand"] #topbar .tb-toggle:hover,[data-topbar="dark"] #topbar .tb-toggle:hover{ background:rgba(255,255,255,0.16); }
[data-topbar="brand"] #topbar .tb-user-name,[data-topbar="dark"] #topbar .tb-user-name{ color:#fff; }
[data-topbar="brand"] #topbar .tb-user-role{ color:rgba(255,255,255,0.80); }
[data-topbar="dark"] #topbar .tb-user-role{ color:#94a3b8; }
[data-topbar="brand"] #topbar .tb-divider,[data-topbar="dark"] #topbar .tb-divider{ border-color:rgba(255,255,255,0.24); }
[data-topbar="brand"] #topbar .icon-chip{ background:rgba(255,255,255,0.22); color:#fff; }
/* Switch dark/light — identik dengan toggle di frontend (track + knob geser + ikon). */
.theme-switch{ position:relative; display:inline-flex; width:3.25rem; height:1.75rem; flex:none; align-items:center; border:1px solid color-mix(in srgb, var(--accent) 30%, rgba(255,255,255,.42)); border-radius:999px; background:color-mix(in srgb, var(--accent) 24%, #0f172a); box-shadow:inset 0 1px 2px rgba(2,6,23,.24), 0 1px 2px rgba(15,23,42,.08); transition:border-color .18s ease, background .18s ease, box-shadow .18s ease; cursor:pointer; }
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
/* Pada topbar Brand/Command Dark, beri sedikit kontras tepi agar track terbaca. */
[data-topbar="brand"] #topbar .theme-switch,[data-topbar="dark"] #topbar .theme-switch{ border-color:rgba(255,255,255,.55); }
/* Kartu "Dokumentasi" di kaki sidebar — glass transparan ber-aksen (di atas Keluar).
   border-radius eksplisit (menang atas override tema .rounded-lg lewat spesifisitas #sidebar). */
#sidebar .doc-card{ border-radius:6px; border:1px solid color-mix(in srgb, var(--accent) 14%, transparent); background:color-mix(in srgb, var(--accent) 4%, transparent); color:var(--accent); }
#sidebar .doc-card:hover{ background:color-mix(in srgb, var(--accent) 8%, transparent); border-color:color-mix(in srgb, var(--accent) 20%, transparent); }
#sidebar .doc-card.doc-card-active{ background:color-mix(in srgb, var(--accent) 9%, transparent); border-color:color-mix(in srgb, var(--accent) 22%, transparent); }
#sidebar .doc-card .doc-card-ico{ border-radius:5px; background:color-mix(in srgb, var(--accent) 10%, transparent); color:var(--accent); }
#sidebar .doc-card .doc-card-sub{ opacity:.6; font-weight:400; }
[data-sidebar="brand"] #sidebar .doc-card,[data-sidebar="midnight"] #sidebar .doc-card{ border-color:rgba(255,255,255,.18); background:rgba(255,255,255,.05); color:#fff; }
[data-sidebar="brand"] #sidebar .doc-card:hover,[data-sidebar="midnight"] #sidebar .doc-card:hover{ background:rgba(255,255,255,.11); }
[data-sidebar="brand"] #sidebar .doc-card .doc-card-ico,[data-sidebar="midnight"] #sidebar .doc-card .doc-card-ico{ background:rgba(255,255,255,.13); color:#fff; }

/* ═══ PREMIUM PASS — adopsi DNA visual Averion (mesh, depth, glass, ikon soft) ═══ */
/* Mesh brand halus di canvas (light + dark) — subtle, di belakang konten. */
body[data-ui-theme]::before{
  content:""; position:fixed; inset:0; z-index:-1; pointer-events:none;
  background:
    radial-gradient(46rem 20rem at 14% -4rem, color-mix(in srgb, var(--accent) 8%, transparent), transparent 60%),
    radial-gradient(40rem 20rem at 94% -2rem, color-mix(in srgb, var(--accent) 5%, transparent), transparent 62%);
}
html.dark body[data-ui-theme]::before{
  background:
    radial-gradient(46rem 22rem at 14% -4rem, color-mix(in srgb, var(--accent) 16%, transparent), transparent 60%),
    radial-gradient(40rem 22rem at 94% -2rem, color-mix(in srgb, var(--accent) 10%, transparent), transparent 62%);
}
/* Glass topbar lebih kaya + saturasi. */
#topbar{ -webkit-backdrop-filter: blur(14px) saturate(180%); backdrop-filter: blur(14px) saturate(180%); }
/* Ikon chip soft berwarna accent (base — karakter per-preset di komponen). */
.icon-chip{ display:inline-flex; align-items:center; justify-content:center; width:2.5rem; height:2.5rem; border-radius:var(--control-radius,6px); background:color-mix(in srgb, var(--accent) 12%, transparent); color:var(--accent); }
html.dark .icon-chip{ background:color-mix(in srgb, var(--accent) 22%, transparent); }
/* Surface/depth/KPI/hover kartu → scribeThemeComponentCss() (baca variabel). */
/* Sidebar: brand mark + active glow. */
#sidebar .side-active{ box-shadow:0 8px 20px -8px color-mix(in srgb, var(--accent) 70%, transparent); }
[data-sidebar="classic"] #sidebar a.side-active svg{ color:#fff; }
/* Table row hover. */
main table tbody tr{ transition:background .12s ease; }
main table tbody tr:hover{ background:color-mix(in srgb, var(--accent) 5%, transparent); }
html.dark main table tbody tr:hover{ background:color-mix(in srgb, var(--accent) 12%, transparent); }
/* Input focus ring accent (global admin). */
main input:not([type=checkbox]):not([type=radio]):not([type=color]):focus,
main select:focus, main textarea:focus{ outline:none; border-color:var(--accent);
  box-shadow:0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent); }
/* Progres ring SEO (conic accent) — dipakai dashboard. */
.ring-seo{ background:conic-gradient(var(--ring-color) calc(var(--val)*1%), color-mix(in srgb, currentColor 12%, transparent) 0); }
<?= scribeThemeComponentCss() ?>
</style>
</head>
<body data-ui-theme="<?= e($theme['key']) ?>" data-sidebar="<?= e($theme['sidebar']) ?>" data-topbar="<?= e($theme['topbar']) ?>" class="h-full text-gray-900 dark:text-gray-100 antialiased">
<div class="min-h-full flex">
  <!-- Sidebar -->
  <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-60 -translate-x-full lg:translate-x-0 transition-transform bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col">
    <a href="<?= e(url('/admin')) ?>" class="side-brand-row flex items-center gap-2 px-5 border-b border-gray-200 dark:border-gray-800 min-w-0 hover:opacity-90">
      <?php if ($logo): ?>
        <img src="<?= e($upBase . '/' . $logo) ?>" alt="" class="max-h-8 max-w-[104px] w-auto object-contain shrink-0">
      <?php else: ?>
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-md text-white shrink-0" style="background:var(--accent)"><?= icon('pen-line', 'w-5 h-5') ?></span>
      <?php endif; ?>
      <span class="side-brand-name font-display font-semibold text-[15px] leading-tight truncate min-w-0"><?= e($blogName) ?></span>
    </a>
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
      <?php foreach ($nav as [$path, $label, $ico, $need]):
          if ($need === 'admin' && $role !== 'admin') continue;
          $isActive = ($active === $path);
      ?>
      <a href="<?= e(url($path)) ?>" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition
          <?= $isActive
              ? 'side-active text-white'
              : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' ?>"
          <?= $isActive ? 'style="background:var(--accent)"' : '' ?>>
        <?= icon($ico, 'w-[18px] h-[18px] shrink-0') ?>
        <span><?= e($label) ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="p-3 border-t border-gray-200 dark:border-gray-800 space-y-2">
      <a href="<?= e(url('/admin/docs')) ?>" class="doc-card flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition<?= $active === '/admin/docs' ? ' doc-card-active' : '' ?>">
        <span class="doc-card-ico inline-flex items-center justify-center w-8 h-8 rounded-md shrink-0"><?= icon('book-open', 'w-[18px] h-[18px]') ?></span>
        <span class="min-w-0 leading-tight">
          <span class="block">Dokumentasi</span>
          <span class="doc-card-sub block text-[11px] truncate">Panduan pemakaian</span>
        </span>
      </a>
      <a href="<?= e(url('/logout')) ?>" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
        <?= icon('log-out', 'w-[18px] h-[18px]') ?><span>Keluar</span>
      </a>
    </div>
  </aside>

  <!-- Main -->
  <div class="flex-1 lg:pl-60 min-w-0"<?= $active === '/admin' ? ' style="display:flex;flex-direction:column;min-height:100vh;min-height:100svh"' : '' ?>>
    <header id="topbar" class="sticky top-0 z-30 flex items-center gap-3 px-4 sm:px-6 bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b border-gray-200 dark:border-gray-800">
      <button onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full')" class="tb-toggle lg:hidden p-2 -ml-2 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><?= icon('menu', 'w-5 h-5') ?></button>
      <h1 class="topbar-title font-display text-base font-semibold truncate"><?= e($title) ?></h1>
      <div class="ml-auto flex items-center gap-3">
        <button type="button" id="themeToggle" onclick="toggleTheme()" role="switch" aria-checked="false" aria-label="Gunakan tema gelap" class="theme-switch" title="Ganti tema">
          <span class="theme-switch-knob" aria-hidden="true"></span>
          <span class="theme-switch-icon theme-switch-sun" aria-hidden="true"><?= icon('sun', 'w-3 h-3') ?></span>
          <span class="theme-switch-icon theme-switch-moon" aria-hidden="true"><?= icon('moon', 'w-3 h-3') ?></span>
        </button>
        <div class="tb-divider flex items-center gap-2 pl-3 border-l border-gray-200 dark:border-gray-800">
          <div class="text-right leading-tight hidden sm:block">
            <div class="tb-user-name text-sm font-medium"><?= e($user) ?></div>
            <div class="tb-user-role text-[11px] uppercase tracking-wide text-gray-400"><?= e($role) ?></div>
          </div>
          <span class="icon-chip w-8 h-8 text-xs font-semibold" style="border-radius:var(--control-radius,6px)"><?= e(strtoupper(substr($user, 0, 2))) ?></span>
        </div>
      </div>
    </header>

    <main class="p-4 sm:p-6 max-w-6xl mx-auto"<?= $active === '/admin' ? ' style="flex:1;width:100%"' : '' ?>>
      <?php
      // Banner peringatan grace (bila ada).
      if (defined('LICENSE_GRACE_WARNING')): ?>
        <div class="mb-4 flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
          <?= icon('alert-triangle', 'w-5 h-5 shrink-0') ?><span><?= e(LICENSE_GRACE_WARNING) ?></span>
        </div>
      <?php endif;
      // Flash messages.
      foreach (takeAllFlash() as $f):
          $color = ['success' => 'emerald', 'error' => 'red', 'info' => 'sky'][$f['type']] ?? 'gray'; ?>
        <div class="mb-4 rounded-md border px-4 py-3 text-sm
            border-<?= $color ?>-300 bg-<?= $color ?>-50 text-<?= $color ?>-800
            dark:border-<?= $color ?>-800/60 dark:bg-<?= $color ?>-950/40 dark:text-<?= $color ?>-200">
          <?= e($f['message']) ?>
        </div>
      <?php endforeach; ?>
<?php }

/** Tab navigasi antar-halaman Settings (AI / Rebrand / Appearance). */
function settings_tabs(string $active): void
{
    $tabs = [
        '/admin/settings'     => 'AI & Brand Voice',
        '/admin/rebrand'      => 'Rebrand',
        '/admin/appearance'   => 'Appearance',
        '/admin/integrations' => 'Integrasi',
    ];
    echo '<div class="flex flex-wrap gap-1 mb-4 border-b border-gray-200 dark:border-gray-800">';
    foreach ($tabs as $path => $label) {
        $on = $active === $path;
        echo '<a href="' . e(url($path)) . '" class="px-3 py-2 text-sm font-medium border-b-2 -mb-px '
           . ($on ? 'text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200') . '"'
           . ($on ? ' style="border-color:var(--accent)"' : '') . '>' . e($label) . '</a>';
    }
    echo '</div>';
}

function admin_shell_bottom(bool $dashboardBrand = false): void
{ ?>
    </main>
    <?php if ($dashboardBrand) require __DIR__ . '/_dashboard-footer.php'; ?>
  </div>
</div>

<!-- Modal global (pengganti confirm()/alert() native — konsisten semua halaman) -->
<div id="scribeModal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4" aria-hidden="true">
  <div class="sm-overlay absolute inset-0 bg-gray-900/50" style="-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px)"></div>
  <div class="sm-box relative w-full max-w-sm rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl p-5" role="dialog" aria-modal="true" aria-labelledby="smTitle">
    <div class="flex items-start gap-3">
      <span id="smIcon" class="inline-flex items-center justify-center w-9 h-9 rounded-md shrink-0"></span>
      <div class="flex-1 min-w-0 pt-0.5">
        <h3 id="smTitle" class="font-display font-semibold text-sm leading-snug"></h3>
        <p id="smMsg" class="text-sm text-gray-500 dark:text-gray-400 mt-1"></p>
      </div>
    </div>
    <input id="smInput" type="text" class="hidden w-full mt-4 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2" style="--tw-ring-color:color-mix(in srgb, var(--accent) 40%, transparent)">
    <div class="mt-5 flex justify-end gap-2">
      <button type="button" id="smCancel" class="rounded-md px-3.5 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Batal</button>
      <button type="button" id="smOk" class="rounded-md px-3.5 py-2 text-sm font-semibold text-white hover:opacity-90">OK</button>
    </div>
  </div>
</div>

<script>
function toggleTheme() {
    const root = document.documentElement;
    const dark = root.classList.toggle('dark');
    localStorage.theme = dark ? 'dark' : 'light';
    syncThemeToggle();
}
function syncThemeToggle() {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    var dark = document.documentElement.classList.contains('dark');
    btn.setAttribute('aria-checked', dark ? 'true' : 'false');
    btn.setAttribute('aria-label', dark ? 'Gunakan tema terang' : 'Gunakan tema gelap');
}
syncThemeToggle();

// ── Modal konfirmasi/peringatan (Promise) — ganti confirm()/alert() ──
(function () {
  var m = document.getElementById('scribeModal');
  if (!m) return;
  var box = m.querySelector('.sm-box'), overlay = m.querySelector('.sm-overlay');
  var iconEl = document.getElementById('smIcon'), titleEl = document.getElementById('smTitle'), msgEl = document.getElementById('smMsg');
  var okBtn = document.getElementById('smOk'), cancelBtn = document.getElementById('smCancel');
  var inputEl = document.getElementById('smInput');
  var resolver = null, lastFocus = null, promptMode = false;
  var SVG = {
    danger: '<svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
    warn:   '<svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
    info:   '<svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>'
  };
  function done(val) {
    m.classList.add('hidden'); m.classList.remove('flex'); m.setAttribute('aria-hidden', 'true');
    document.removeEventListener('keydown', onKey, true);
    var r = resolver; resolver = null;
    var out = promptMode ? (val ? inputEl.value : null) : val;
    inputEl.classList.add('hidden'); promptMode = false;
    if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
    if (r) r(out);
  }
  function onKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); done(false); }
    else if (e.key === 'Enter') { e.preventDefault(); done(true); }
    else if (e.key === 'Tab') { // fokus terperangkap di kontrol modal
      var f = (promptMode ? [inputEl, cancelBtn, okBtn] : [cancelBtn, okBtn]).filter(function (b) { return b.style.display !== 'none'; });
      var i = f.indexOf(document.activeElement); e.preventDefault();
      f[(i + (e.shiftKey ? -1 : 1) + f.length) % f.length].focus();
    }
  }
  okBtn.addEventListener('click', function () { done(true); });
  cancelBtn.addEventListener('click', function () { done(false); });
  overlay.addEventListener('click', function () { done(false); });
  function open(opts) {
    return new Promise(function (res) {
      resolver = res; lastFocus = document.activeElement;
      var alert = !!opts.alert, danger = !!opts.danger;
      titleEl.textContent = opts.title || (alert ? 'Perhatian' : 'Konfirmasi');
      msgEl.textContent = opts.message || ''; msgEl.style.display = opts.message ? '' : 'none';
      okBtn.textContent = opts.okText || (alert ? 'Mengerti' : (danger ? 'Hapus' : 'Lanjutkan'));
      cancelBtn.style.display = alert ? 'none' : '';
      okBtn.style.background = danger ? '#dc2626' : 'var(--accent)';
      iconEl.style.background = ''; iconEl.className = 'inline-flex items-center justify-center w-9 h-9 rounded-md shrink-0';
      if (danger) { iconEl.className += ' bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-400'; iconEl.innerHTML = SVG.danger; }
      else if (alert) { iconEl.className += ' bg-amber-100 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400'; iconEl.innerHTML = SVG.warn; }
      else { iconEl.className += ' text-white'; iconEl.style.background = 'var(--accent)'; iconEl.innerHTML = SVG.info; }
      // Mode prompt: tampilkan input teks.
      promptMode = !!opts.prompt;
      if (promptMode) {
        inputEl.classList.remove('hidden');
        inputEl.value = opts.value || '';
        inputEl.placeholder = opts.placeholder || '';
      } else { inputEl.classList.add('hidden'); }
      m.classList.remove('hidden'); m.classList.add('flex'); m.setAttribute('aria-hidden', 'false');
      setTimeout(function () { if (promptMode) { inputEl.focus(); inputEl.select(); } else { okBtn.focus(); } }, 20);
      document.addEventListener('keydown', onKey, true);
    });
  }
  window.scribeConfirm = function (message, opts) { opts = opts || {}; opts.message = message; return open(opts); };
  window.scribeAlert   = function (message, opts) { opts = opts || {}; opts.message = message; opts.alert = true; return open(opts); };
  // Prompt input teks (ganti prompt()). Resolve string (OK/Enter) atau null (Batal/Esc).
  window.scribePrompt  = function (message, opts) { opts = opts || {}; opts.message = message; opts.prompt = true; return open(opts); };
  // Form dengan data-confirm → intercept submit, tampilkan modal dulu.
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || !f.matches || !f.matches('form[data-confirm]') || f.dataset.smOk === '1') return;
    e.preventDefault();
    scribeConfirm(f.getAttribute('data-confirm'), {
      danger:  f.hasAttribute('data-confirm-danger'),
      title:   f.getAttribute('data-confirm-title') || undefined,
      okText:  f.getAttribute('data-confirm-ok') || undefined
    }).then(function (ok) { if (ok) { f.dataset.smOk = '1'; f.submit(); } });
  }, true);
})();
</script>
</body>
</html>
<?php }
