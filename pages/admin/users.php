<?php
// Kelola pengguna (admin). Listing + tambah/edit + reset password + aktif/nonaktif.
require_once __DIR__ . '/_shell.php';
$pdo = getDB();

try {
    $users = $pdo->query(
        "SELECT u.id, u.name, u.email, u.role, u.is_active, u.last_login_at,
                (SELECT COUNT(*) FROM articles a WHERE a.author_id = u.id) AS article_count
         FROM users u ORDER BY u.role ASC, u.name ASC"
    )->fetchAll();
} catch (Throwable $e) { $users = []; }

$activeAdmins = 0;
foreach ($users as $u) { if ($u['role'] === 'admin' && (int) $u['is_active'] === 1) $activeAdmins++; }

admin_shell_top('Pengguna', '/admin/users');
?>
<div class="grid lg:grid-cols-3 gap-4">
  <!-- Form -->
  <div class="lg:col-span-1">
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 sticky top-20">
      <h2 id="userFormTitle" class="font-display font-semibold text-sm mb-4">Tambah Pengguna</h2>
      <form method="post" action="<?= e(url('/actions/admin/save-user')) ?>" class="space-y-3" id="userForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="userId" value="">
        <div>
          <label class="block text-xs font-medium mb-1">Nama</label>
          <input name="name" id="userName" required class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Email</label>
          <input name="email" id="userEmail" type="email" required class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Password <span id="pwHint" class="text-gray-400">(min. 8 karakter)</span></label>
          <input name="password" id="userPass" type="password" minlength="8" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm" autocomplete="new-password">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Peran</label>
          <select name="role" id="userRole" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
            <option value="writer">Writer</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="flex gap-2 pt-1">
          <button type="submit" class="flex-1 rounded-md py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan</button>
          <button type="button" onclick="resetUserForm()" class="rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Reset</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Listing -->
  <div class="lg:col-span-2">
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-x-auto">
      <table class="w-full text-sm min-w-[600px]">
        <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400">
          <tr>
            <th class="text-left font-medium px-4 py-2.5">Pengguna</th>
            <th class="text-left font-medium px-4 py-2.5">Peran</th>
            <th class="text-center font-medium px-4 py-2.5">Artikel</th>
            <th class="text-left font-medium px-4 py-2.5">Login Terakhir</th>
            <th class="text-right font-medium px-4 py-2.5">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <?php foreach ($users as $u):
            $isLastAdmin = $u['role'] === 'admin' && (int) $u['is_active'] === 1 && $activeAdmins <= 1; ?>
          <tr class="<?= (int) $u['is_active'] === 0 ? 'opacity-60' : '' ?>">
            <td class="px-4 py-2.5">
              <div class="font-medium"><?= e($u['name']) ?> <?php if ((int) $u['is_active'] === 0): ?><span class="text-[10px] uppercase text-gray-400">(nonaktif)</span><?php endif; ?></div>
              <div class="text-xs text-gray-400"><?= e($u['email']) ?></div>
            </td>
            <td class="px-4 py-2.5">
              <?php $rc = $u['role'] === 'admin' ? 'violet' : 'gray'; ?>
              <span class="inline-block text-[11px] font-medium px-2 py-0.5 rounded-md bg-<?= $rc ?>-100 text-<?= $rc ?>-700 dark:bg-<?= $rc ?>-950/50 dark:text-<?= $rc ?>-300"><?= e(ucfirst($u['role'])) ?></span>
            </td>
            <td class="px-4 py-2.5 text-center"><?= (int) $u['article_count'] ?></td>
            <td class="px-4 py-2.5 text-xs text-gray-500"><?= e($u['last_login_at'] ? formatTanggal($u['last_login_at']) : 'Belum pernah') ?></td>
            <td class="px-4 py-2.5">
              <div class="flex items-center justify-end gap-1">
                <button type="button" onclick='editUser(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" title="Edit"><?= icon('pen', 'w-4 h-4') ?></button>
                <?php if (!$isLastAdmin): ?>
                <form method="post" action="<?= e(url('/actions/admin/toggle-user')) ?>" class="inline">
                  <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button type="submit" class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" title="<?= (int) $u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>"><?= icon((int) $u['is_active'] === 1 ? 'lock' : 'check-circle', 'w-4 h-4') ?></button>
                </form>
                <form method="post" action="<?= e(url('/actions/admin/delete-user')) ?>" data-confirm="Hapus pengguna &quot;<?= e($u['name']) ?>&quot;?" data-confirm-danger class="inline">
                  <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button type="submit" class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" title="Hapus"><?= icon('trash', 'w-4 h-4') ?></button>
                </form>
                <?php else: ?>
                <span class="text-[10px] text-gray-400 px-1" title="Admin aktif terakhir tidak bisa dinonaktifkan/dihapus">terkunci</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function editUser(u) {
  document.getElementById('userId').value = u.id;
  document.getElementById('userName').value = u.name;
  document.getElementById('userEmail').value = u.email;
  document.getElementById('userRole').value = u.role;
  document.getElementById('userPass').value = '';
  document.getElementById('pwHint').textContent = '(kosongkan bila tidak diubah)';
  document.getElementById('userFormTitle').textContent = 'Edit Pengguna';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetUserForm() {
  document.getElementById('userForm').reset();
  document.getElementById('userId').value = '';
  document.getElementById('pwHint').textContent = '(min. 8 karakter)';
  document.getElementById('userFormTitle').textContent = 'Tambah Pengguna';
}
</script>
<?php admin_shell_bottom(); ?>
