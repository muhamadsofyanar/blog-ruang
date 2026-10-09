<?php
// Layout installer. Variabel dari index.php: $step, $views, $errors, $_POST_KEEP.
$csrf = $_SESSION['installer_csrf'];
$keep = $_POST_KEEP ?? [];
$steps = [1 => 'Kebutuhan', 2 => 'Database', 3 => 'Admin', 4 => 'Lisensi', 5 => 'Selesai'];
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installer — Averion SEO Engine</title>
<script>if(localStorage.theme==='dark'||(!('theme' in localStorage)&&matchMedia('(prefers-color-scheme:dark)').matches)){document.documentElement.classList.add('dark')}</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class',theme:{extend:{borderRadius:{DEFAULT:'6px',md:'6px',lg:'8px'}}}};</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>:root{--accent:#6366f1}body{font-family:'"Plus Jakarta Sans"',system-ui,sans-serif}h1,h2{font-family:'Fraunces',serif}</style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
<div class="min-h-full flex items-center justify-center p-4">
  <div class="w-full max-w-lg">
    <div class="flex items-center justify-center gap-2 mb-6">
      <span class="inline-flex items-center justify-center w-9 h-9 rounded-md text-white" style="background:var(--accent)">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"/></svg>
      </span>
      <span class="font-display text-lg font-semibold">Averion SEO Engine</span>
    </div>

    <!-- Step indicator -->
    <div class="flex items-center justify-between mb-6 px-1">
      <?php foreach ($steps as $n => $label): ?>
        <div class="flex flex-col items-center flex-1">
          <div class="w-7 h-7 rounded-md flex items-center justify-center text-xs font-semibold
              <?= $n < $step ? 'bg-emerald-500 text-white' : ($n === $step ? 'text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-400') ?>"
              <?= $n === $step ? 'style="background:var(--accent)"' : '' ?>>
            <?= $n < $step ? '&#10003;' : $n ?>
          </div>
          <span class="mt-1 text-[10px] <?= $n === $step ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-400' ?>"><?= e($label) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-6 shadow-sm">
      <?php if (!empty($errors)): ?>
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 dark:border-red-800/60 dark:bg-red-950/40 px-3 py-2 text-sm text-red-700 dark:text-red-300">
          <?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php require __DIR__ . '/' . $views[$step]; ?>
    </div>
    <p class="text-center text-xs text-gray-400 mt-6">Averion SEO Engine · Installer</p>
  </div>
</div>
</body>
</html>
