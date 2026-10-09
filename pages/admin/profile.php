<?php
// Profil Penulis (staff) — bio/jabatan/avatar/tautan sosial untuk halaman publik
// /penulis dan schema Person (E-E-A-T). Setiap pengguna menyunting profilnya sendiri.
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/author.php';

$uid = currentUserId();
$st  = getDB()->prepare("SELECT name, email, role FROM users WHERE id = ? LIMIT 1");
$st->execute([$uid]);
$me = $st->fetch() ?: ['name' => '', 'email' => '', 'role' => ''];

$p         = authorProfileRaw($uid);
$bio       = (string) ($p['bio'] ?? '');
$job       = (string) ($p['job_title'] ?? '');
$avatar    = (string) ($p['avatar'] ?? '');
$social    = is_array($p['social'] ?? null) ? implode("\n", $p['social']) : '';
$avatarUrl = $avatar !== '' ? rtrim(UPLOAD_URL, '/') . '/' . ltrim($avatar, '/') : '';
$publicUrl = url('/penulis/' . slugify((string) $me['name']));

admin_shell_top('Profil Penulis', '/admin/profile');
?>
<div class="max-w-2xl space-y-4">
  <form method="post" action="<?= e(url('/actions/admin/save-profile')) ?>" enctype="multipart/form-data" class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4">
    <?= csrfField() ?>
    <div>
      <h2 class="font-display font-semibold">Profil Penulis</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Tampil di halaman publik <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener" class="underline" style="color:var(--accent)">/penulis/<?= e(slugify((string) $me['name'])) ?></a> dan di schema artikel (sinyal <strong>E-E-A-T</strong>).</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Nama</label>
      <input value="<?= e($me['name']) ?>" disabled class="w-full rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-3 py-2 text-sm text-gray-500">
      <p class="text-xs text-gray-400 mt-1">Nama diubah di <a href="<?= e(url('/admin/users')) ?>" class="underline">Pengguna</a> (admin). Slug penulis mengikuti nama.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Jabatan / peran</label>
      <input name="job_title" value="<?= e($job) ?>" maxlength="120" placeholder="mis. Editor SEO, Praktisi Digital Marketing" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Bio</label>
      <textarea name="bio" rows="4" maxlength="1000" placeholder="Ceritakan keahlian & pengalaman Anda (memperkuat kepercayaan/E-E-A-T)." class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><?= e($bio) ?></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Foto profil (avatar)</label>
      <div class="flex items-center gap-3">
        <?php if ($avatarUrl !== ''): ?>
          <img src="<?= e($avatarUrl) ?>" alt="Avatar" class="w-14 h-14 rounded-full object-cover border border-gray-200 dark:border-gray-800">
        <?php else: ?>
          <div class="w-14 h-14 rounded-full flex items-center justify-center text-white text-lg font-bold" style="background:var(--accent)"><?= e(mb_strtoupper(mb_substr((string) $me['name'], 0, 1)) ?: '?') ?></div>
        <?php endif; ?>
        <input type="file" name="avatar" accept="image/*" class="text-sm">
      </div>
      <p class="text-xs text-gray-400 mt-1">PNG/JPG/WebP. Kosongkan untuk mempertahankan yang ada.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Tautan sosial <span class="text-gray-400">(sameAs)</span></label>
      <textarea name="social" rows="3" placeholder="https://x.com/akun&#10;https://linkedin.com/in/akun" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono"><?= e($social) ?></textarea>
      <p class="text-xs text-gray-400 mt-1">Satu URL per baris (maks 8). Tampil di halaman penulis &amp; schema Person.</p>
    </div>

    <div class="flex items-center gap-3">
      <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan Profil</button>
      <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener" class="text-sm font-medium underline" style="color:var(--accent)">Lihat halaman penulis</a>
    </div>
  </form>
</div>
<?php admin_shell_bottom(); ?>
