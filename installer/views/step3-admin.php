<?php $g = fn($k) => e($keep[$k] ?? ''); ?>
<h2 class="text-lg font-semibold mb-1">Buat Admin Pertama</h2>
<p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Akun ini berperan <strong>admin</strong> (akses penuh).</p>

<form method="post" action="?step=3" class="space-y-3">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="step" value="3">
  <div><label class="block text-xs font-medium mb-1">Nama</label>
    <input name="admin_name" value="<?= $g('admin_name') ?>" required class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
  <div><label class="block text-xs font-medium mb-1">Email</label>
    <input name="admin_email" type="email" value="<?= $g('admin_email') ?>" required class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
  <div><label class="block text-xs font-medium mb-1">Password <span class="text-gray-400">(min. 8 karakter)</span></label>
    <input name="admin_password" type="password" required minlength="8" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
  <button type="submit" class="w-full rounded-md py-2 text-sm font-semibold text-white hover:opacity-90 mt-2" style="background:var(--accent)">Buat Admin & Lanjut</button>
</form>
