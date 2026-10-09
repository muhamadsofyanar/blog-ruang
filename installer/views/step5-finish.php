<?php
$migrated = $_SESSION['install_migrated'] ?? [];
$licenseOk = !empty($_SESSION['install_license_ok']);
?>
<div class="text-center">
  <span class="inline-flex items-center justify-center w-14 h-14 rounded-lg bg-emerald-100 dark:bg-emerald-950/50 text-emerald-500 mb-4">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
  </span>
  <h2 class="text-xl font-semibold mb-1">Instalasi Selesai</h2>
  <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">Averion SEO Engine siap digunakan.</p>
</div>

<ul class="text-sm space-y-2 mb-5">
  <li class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><span>&#10003;</span> Konfigurasi tersimpan</li>
  <li class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><span>&#10003;</span> Migrasi <?= e(implode(', ', $migrated) ?: '1.0.0') ?> diterapkan</li>
  <li class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><span>&#10003;</span> Admin pertama dibuat</li>
  <li class="flex items-center gap-2 <?= $licenseOk ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' ?>">
    <span><?= $licenseOk ? '&#10003;' : '!' ?></span> <?= $licenseOk ? 'Lisensi aktif' : 'Lisensi belum diaktifkan (aktifkan dari admin)' ?>
  </li>
</ul>

<div class="rounded-md border border-amber-300 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-950/40 px-3 py-2.5 text-xs text-amber-800 dark:text-amber-200 mb-5">
  <strong>Keamanan:</strong> hapus folder <code>installer/</code> dari server setelah instalasi.
</div>

<a href="../login" class="block text-center w-full rounded-md py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Masuk ke Admin</a>
