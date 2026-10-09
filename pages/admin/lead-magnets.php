<?php
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/lead-magnet.php';
requireAdmin();
$pdo = getDB();
$editId = (int) ($_GET['id'] ?? 0);
$edit = $editId > 0 ? scribeLeadMagnetById($editId, false) : null;
$rows = $pdo->query("SELECT lm.*, (SELECT COUNT(*) FROM subscribers s WHERE s.lead_magnet_id = lm.id) AS subscriber_count FROM lead_magnets lm ORDER BY lm.updated_at DESC, lm.id DESC")->fetchAll();
$defaultId = (int) getSetting('lead_magnet_default_id', '0');
$lmSuccessTitle = (string) getSetting('lead_success_title', '');
$lmSuccessDesc  = (string) getSetting('lead_success_desc', '');
$lmSuccessBtn   = (string) getSetting('lead_success_btn', '');
admin_shell_top('Lead Magnet', '/admin/lead-magnets');
?>
<div class="max-w-5xl space-y-4">
  <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="font-display font-semibold text-xl">Lead Magnet</h1><p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Buat materi gratis, tampilkan form di artikel, dan catat sumber subscriber.</p></div><a href="<?= e(url('/admin/lead-magnets')) ?>" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-semibold text-white" style="background:var(--accent)"><?= icon('plus', 'w-4 h-4') ?> Lead magnet baru</a></div>
  <div class="grid lg:grid-cols-[1fr_360px] gap-4">
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800"><h2 class="font-display font-semibold">Materi tersedia</h2></div>
      <?php if (!$rows): ?><div class="p-8 text-center text-sm text-gray-500">Belum ada lead magnet.</div><?php else: ?>
      <div class="divide-y divide-gray-200 dark:divide-gray-800"><?php foreach ($rows as $m): ?><div class="px-5 py-4 flex items-start justify-between gap-3"><div class="min-w-0"><div class="flex items-center gap-2"><a href="<?= e(url('/admin/lead-magnets?id=' . (int) $m['id'])) ?>" class="font-semibold text-sm hover:text-accent truncate"><?= e($m['title']) ?></a><?php if ($defaultId === (int) $m['id']): ?><span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-600">Aktif di artikel</span><?php endif; ?></div><p class="text-xs text-gray-500 mt-1">/lead-magnet/<?= e($m['slug']) ?> · <?= (int) $m['subscriber_count'] ?> subscriber</p></div><div class="flex items-center gap-2 shrink-0"><span class="text-xs <?= $m['status'] === 'published' ? 'text-emerald-600' : ($m['status'] === 'paused' ? 'text-amber-600' : 'text-gray-500') ?>"><?= e(ucfirst($m['status'])) ?></span><a href="<?= e(url('/admin/lead-magnets?id=' . (int) $m['id'])) ?>" class="inline-flex items-center gap-1 text-xs font-medium text-accent"><?= icon('pen-line', 'w-3.5 h-3.5') ?> Edit</a><form method="post" action="<?= e(url('/actions/admin/delete-lead-magnet')) ?>" data-confirm="Hapus lead magnet ini? Penghapusan hanya diizinkan bila belum dipakai subscriber atau email sequence." data-confirm-danger data-confirm-title="Hapus lead magnet"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button type="submit" class="inline-flex items-center gap-1 text-xs font-medium text-red-600"><?= icon('trash', 'w-3.5 h-3.5') ?> Hapus</button></form></div></div><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
    <form method="post" action="<?= e(url('/actions/admin/save-lead-magnet')) ?>" class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-3">
      <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <h2 class="font-display font-semibold"><?= $edit ? 'Edit lead magnet' : 'Buat lead magnet' ?></h2>
      <div><label class="block text-xs font-medium mb-1">Judul</label><input name="title" required maxlength="190" value="<?= e($edit['title'] ?? '') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
      <div><label class="block text-xs font-medium mb-1">Slug</label><input name="slug" maxlength="190" value="<?= e($edit['slug'] ?? '') ?>" placeholder="otomatis-dari-judul" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
      <div><label class="block text-xs font-medium mb-1">Deskripsi</label><textarea name="description" rows="3" maxlength="1000" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><?= e($edit['description'] ?? '') ?></textarea></div>
      <div><label class="block text-xs font-medium mb-1">URL materi / file</label><input name="delivery_url" maxlength="500" value="<?= e($edit['delivery_url'] ?? '') ?>" placeholder="https://… atau uploads/lead-magnets/materi.pdf" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><p class="text-[11px] text-gray-400 mt-1">Unggah file ke folder upload hosting atau gunakan URL dari storage yang Anda kontrol.</p></div>
      <div><label class="block text-xs font-medium mb-1">Label tombol</label><input name="cta_label" maxlength="80" value="<?= e($edit['cta_label'] ?? 'Dapatkan Gratis') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
      <div><label class="block text-xs font-medium mb-1">Status</label><select name="status" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"><?php foreach (['draft' => 'Draft', 'published' => 'Published', 'paused' => 'Paused'] as $v => $l): ?><option value="<?= $v ?>" <?= ($edit['status'] ?? 'draft') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="make_default" value="1" <?= $edit && $defaultId === (int) $edit['id'] ? 'checked' : '' ?> class="mt-0.5"><span>Jadikan lead magnet utama di halaman artikel<span class="block text-xs text-gray-400">Hanya satu magnet utama yang ditampilkan inline.</span></span></label>
      <button class="w-full rounded-md px-4 py-2 text-sm font-semibold text-white" style="background:var(--accent)">Simpan Lead Magnet</button>
    </form>
  </div>

  <form method="post" action="<?= e(url('/actions/admin/save-lead-success')) ?>" class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-3">
    <?= csrfField() ?>
    <div><h2 class="font-display font-semibold">Teks tampilan sukses</h2><p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Ditampilkan setelah pengunjung mendaftar (berlaku untuk semua lead magnet). Kosongkan untuk memakai teks bawaan.</p></div>
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="block text-xs font-medium mb-1">Judul sukses</label><input name="lead_success_title" maxlength="190" value="<?= e($lmSuccessTitle) ?>" placeholder="Pendaftaran berhasil" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
      <div><label class="block text-xs font-medium mb-1">Teks tombol setelah submit</label><input name="lead_success_btn" maxlength="80" value="<?= e($lmSuccessBtn) ?>" placeholder="Kosong = ikut label tombol form" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
    </div>
    <div><label class="block text-xs font-medium mb-1">Deskripsi sukses</label><textarea name="lead_success_desc" rows="2" maxlength="500" placeholder="Terima kasih. Materi Anda sudah siap diunduh." class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><?= e($lmSuccessDesc) ?></textarea></div>
    <button class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background:var(--accent)">Simpan teks sukses</button>
  </form>
</div>
<?php admin_shell_bottom(); ?>
