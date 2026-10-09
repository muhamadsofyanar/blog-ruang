<?php
// Halaman login (publik). Standalone — tidak memakai shell admin.
if (isLoggedIn()) {
    redirect('/admin');
}
$flash = getFlash('error') ?? getFlash('auth');
$blogName = blogName();
$logo     = getSetting('brand_logo', '');
$favicon  = getSetting('brand_favicon', '');
$accent   = getSetting('accent_color', '#6366f1');
$upBase   = rtrim(UPLOAD_URL, '/');
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — <?= e($blogName) ?></title>
<?= faviconLinkTag($favicon, $accent) ?>
<script>
if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
}
</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { accent: 'var(--accent)' }, borderRadius: { DEFAULT:'6px', md:'6px', lg:'8px' } } } };</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--accent: <?= e(getSetting('accent_color', '#6366f1')) ?>;}
body{font-family:'"Plus Jakarta Sans"',system-ui,sans-serif;}
h1{font-family:'Fraunces',serif;}
/* Mesh gradient brand (light + dark) — DNA visual Averion. */
body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:
    radial-gradient(50% 50% at 15% 0%, color-mix(in srgb, var(--accent) 16%, transparent), transparent 68%),
    radial-gradient(46% 46% at 88% 8%, color-mix(in srgb, var(--accent) 11%, transparent), transparent 62%),
    linear-gradient(180deg, color-mix(in srgb, var(--accent) 5%, #ffffff), #ffffff 45%);}
html.dark body::before{
  background:
    radial-gradient(50% 50% at 15% 0%, color-mix(in srgb, var(--accent) 26%, transparent), transparent 66%),
    radial-gradient(46% 46% at 88% 8%, color-mix(in srgb, var(--accent) 16%, transparent), transparent 60%),
    linear-gradient(180deg, color-mix(in srgb, var(--accent) 12%, #0b1220), #030712 55%);}
.glass-card{ background: rgba(255,255,255,0.72); -webkit-backdrop-filter: blur(18px) saturate(180%); backdrop-filter: blur(18px) saturate(180%);
  border:1px solid rgba(255,255,255,0.5); box-shadow: 0 24px 60px -24px rgba(15,23,42,0.35), 0 2px 8px -4px rgba(15,23,42,0.06); }
html.dark .glass-card{ background: rgba(17,24,39,0.6); border-color: rgba(255,255,255,0.08); box-shadow: 0 26px 64px -28px rgba(0,0,0,0.7); }
input:focus{ outline:none; border-color:var(--accent) !important; box-shadow:0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent); }
</style>
</head>
<body class="h-full text-gray-900 dark:text-gray-100">
<div class="min-h-full flex items-center justify-center p-4">
  <div class="w-full max-w-sm">
    <div class="flex items-center justify-center gap-2 mb-6">
      <?php if ($logo): ?>
        <img src="<?= e($upBase . '/' . $logo) ?>" alt="<?= e($blogName) ?>" class="max-h-10 max-w-[200px] object-contain">
      <?php else: ?>
        <span class="inline-flex items-center justify-center w-9 h-9 rounded-md text-white" style="background:var(--accent);box-shadow:0 8px 20px -8px color-mix(in srgb, var(--accent) 70%, transparent)"><?= icon('pen-line') ?></span>
        <span class="font-display text-lg font-semibold"><?= e($blogName) ?></span>
      <?php endif; ?>
    </div>
    <div class="glass-card rounded-lg p-6">
      <h1 class="text-xl font-semibold mb-1">Masuk</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">Kelola konten SEO Anda.</p>
      <?php if ($flash): ?>
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 dark:border-red-800/60 dark:bg-red-950/40 px-3 py-2 text-sm text-red-700 dark:text-red-300"><?= e($flash['message']) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/actions/auth/login')) ?>" class="space-y-4">
        <?= csrfField() ?>
        <div>
          <label class="block text-sm font-medium mb-1.5">Email</label>
          <input type="email" name="email" required autofocus autocomplete="username"
                 class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1.5">Password</label>
          <input type="password" name="password" required autocomplete="current-password"
                 class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
        </div>
        <button type="submit" class="w-full rounded-md py-2 text-sm font-semibold text-white transition hover:opacity-90" style="background:var(--accent)">Masuk</button>
      </form>
    </div>
    <p class="text-center text-xs text-gray-400 mt-6">Averion SEO Engine</p>
  </div>
</div>
</body>
</html>
