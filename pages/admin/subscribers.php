<?php
require_once __DIR__ . '/_shell.php';
requireAdmin();
require_once __DIR__ . '/../../helpers/email-sequence.php';
require_once __DIR__ . '/../../helpers/mailketing.php';
scribeKickEmailAutomation(); // proses antrean setelah respons (non-blokir, tak memblokir halaman)
$pdo = getDB();
$q = trim((string) ($_GET['q'] ?? ''));
$where = ''; $params = [];
if ($q !== '') { $where = 'WHERE s.email LIKE ? OR s.name LIKE ?'; $params = ['%' . $q . '%', '%' . $q . '%']; }
$st = $pdo->prepare("SELECT s.*, a.title AS article_title, lm.title AS lead_magnet_title,
    (SELECT q.response FROM scribe_mailketing_queue q WHERE q.subscriber_id=s.id ORDER BY q.id DESC LIMIT 1) AS mk_response,
    (SELECT ess.status FROM email_sequence_sends ess JOIN email_sequence_enrollments e ON e.id=ess.enrollment_id WHERE e.subscriber_id=s.id ORDER BY ess.id DESC LIMIT 1) AS seq_status,
    (SELECT ess.response FROM email_sequence_sends ess JOIN email_sequence_enrollments e ON e.id=ess.enrollment_id WHERE e.subscriber_id=s.id ORDER BY ess.id DESC LIMIT 1) AS seq_response
    FROM subscribers s LEFT JOIN articles a ON a.id=s.source_article_id LEFT JOIN lead_magnets lm ON lm.id=s.lead_magnet_id {$where} ORDER BY s.created_at DESC LIMIT 100"); $st->execute($params); $rows = $st->fetchAll();

// ── Diagnosa pengiriman (observability: kenapa 'pending'?) ──
$diag = ['token' => scribeMailketingToken() !== ''];
$diag['sender'] = trim((string) (getSetting('mailketing_from_email', '') ?: getSetting('mailketing_sender_email', '')));
$diag['endpoint'] = scribeMailketingEndpoint();
$diag['list'] = trim((string) getSetting('mailketing_subscriber_list_id', ''));
try {
    $diag['queue'] = $pdo->query("SELECT status, COUNT(*) c FROM scribe_mailketing_queue GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $diag['queue_err'] = (string) ($pdo->query("SELECT response FROM scribe_mailketing_queue WHERE status IN ('pending','failed') AND response IS NOT NULL AND response<>'' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '');
    $diag['seq_active'] = (int) $pdo->query("SELECT COUNT(*) FROM email_sequences WHERE status='active'")->fetchColumn();
    $diag['send_due'] = (int) $pdo->query("SELECT COUNT(*) FROM email_sequence_sends WHERE status='pending' AND scheduled_at<=NOW()")->fetchColumn();
    $diag['send_err'] = (string) ($pdo->query("SELECT response FROM email_sequence_sends WHERE status='failed' AND response IS NOT NULL AND response<>'' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '');
} catch (Throwable $e) { $diag['queue'] = []; }
// Log worker (bukti pemrosesan: percobaan, status, respons provider, durasi).
$diag['worker_log'] = '';
$diag['worker_log_mtime'] = 0;
$logFile = dirname(__DIR__, 2) . '/cache/logs/email-worker.log';
if (is_file($logFile)) {
    $diag['worker_log_mtime'] = (int) @filemtime($logFile);
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $diag['worker_log'] = implode("\n", array_slice($lines, -25));
}
$editId = (int) ($_GET['id'] ?? 0);
$edit = null;
if ($editId > 0) { $editSt = $pdo->prepare('SELECT * FROM subscribers WHERE id = ? LIMIT 1'); $editSt->execute([$editId]); $edit = $editSt->fetch() ?: null; }
$total = (int) $pdo->query('SELECT COUNT(*) FROM subscribers')->fetchColumn(); $active = (int) $pdo->query('SELECT COUNT(*) FROM subscribers WHERE unsubscribed_at IS NULL')->fetchColumn();
admin_shell_top('Subscribers', '/admin/subscribers');
?>
<div class="max-w-6xl space-y-4">
  <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="font-display font-semibold text-xl">Subscribers</h1><p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Daftar lead dari newsletter dan lead magnet beserta sumber artikelnya.</p></div><div class="flex gap-2 text-xs"><span class="px-3 py-2 rounded-md border border-gray-200 dark:border-gray-800">Total <?= $total ?></span><span class="px-3 py-2 rounded-md border border-emerald-200 text-emerald-700">Aktif <?= $active ?></span></div></div>
  <form method="get" class="flex gap-2"><input name="q" value="<?= e($q) ?>" placeholder="Cari nama atau email…" class="flex-1 max-w-sm rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><button class="rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700">Cari</button></form>

  <?php
    $qPending = (int) ($diag['queue']['pending'] ?? 0);
    $qFailed  = (int) ($diag['queue']['failed'] ?? 0);
    $hasIssue = !$diag['token'] || $diag['sender'] === '' || $qPending > 0 || $qFailed > 0 || ($diag['send_due'] ?? 0) > 0 || ($diag['send_err'] ?? '') !== '';
  ?>
  <?php if ($hasIssue): ?>
  <div class="rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/30 p-4 text-sm">
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-start gap-2"><span class="text-amber-600 dark:text-amber-400 mt-0.5"><?= icon('alert-triangle', 'w-4 h-4') ?></span>
        <div><p class="font-semibold text-amber-800 dark:text-amber-300">Diagnosa pengiriman email</p>
          <ul class="mt-2 space-y-1 text-xs text-gray-700 dark:text-gray-300">
            <li>Token Mailketing: <strong class="<?= $diag['token'] ? 'text-emerald-600' : 'text-red-600' ?>"><?= $diag['token'] ? 'terisi' : 'BELUM DIISI' ?></strong></li>
            <li>Email pengirim: <strong class="<?= $diag['sender'] !== '' ? 'text-emerald-600' : 'text-red-600' ?>"><?= $diag['sender'] !== '' ? e($diag['sender']) : 'BELUM DIISI' ?></strong></li>
            <li>List auto-add: <strong><?= $diag['list'] !== '' ? e($diag['list']) : 'tidak diatur (subscriber tidak ditambahkan ke list)' ?></strong></li>
            <li>Antrean list-add: <strong><?= $qPending ?> pending</strong>, <?= (int) ($diag['queue']['sent'] ?? 0) ?> terkirim, <?= $qFailed ?> gagal<?php if (($diag['queue_err'] ?? '') !== ''): ?> · <span class="text-red-600">respon: “<?= e(mb_substr($diag['queue_err'], 0, 160)) ?>”</span><?php endif; ?></li>
            <li>Email sequence: <strong><?= (int) ($diag['seq_active'] ?? 0) ?> aktif</strong>, <strong><?= (int) ($diag['send_due'] ?? 0) ?> email jatuh tempo belum terkirim</strong><?php if (($diag['send_err'] ?? '') !== ''): ?> · <span class="text-red-600">respon: “<?= e(mb_substr($diag['send_err'], 0, 160)) ?>”</span><?php endif; ?></li>
          </ul>
          <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">Pending diproses otomatis di latar belakang setelah pendaftaran baru atau kunjungan halaman publik/admin (maks. percobaan <?= SCRIBE_EMAIL_MAX_ATTEMPTS ?>×). Klik tombol untuk memproses sekarang.</p>
          <?php if (($diag['worker_log'] ?? '') !== ''): ?>
          <details class="mt-3"<?= (($diag['send_due'] ?? 0) > 0 || $qPending > 0) ? ' open' : '' ?>>
            <summary class="cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-300">Log worker (25 baris terakhir<?= $diag['worker_log_mtime'] ? ' · ' . e(formatTanggal(date('Y-m-d H:i:s', $diag['worker_log_mtime']), true)) : '' ?>)</summary>
            <pre class="mt-2 max-h-56 overflow-auto rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-2 text-[11px] leading-relaxed font-mono whitespace-pre-wrap"><?= e($diag['worker_log']) ?></pre>
          </details>
          <?php else: ?>
          <p class="mt-2 text-[11px] text-gray-400">Log worker belum ada — klik "Proses antrean sekarang" untuk menjalankan dan mencatat hasilnya.</p>
          <?php endif; ?>
        </div>
      </div>
      <form method="post" action="<?= e(url('/actions/admin/process-email-sequences')) ?>" class="shrink-0"><?= csrfField() ?><button type="submit" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-xs font-semibold text-white" style="background:var(--accent)"><?= icon('refresh-cw', 'w-3.5 h-3.5') ?> Proses antrean sekarang</button></form>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($edit): ?>
  <form method="post" action="<?= e(url('/actions/admin/save-subscriber')) ?>" class="max-w-xl rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-3">
    <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
    <div class="flex items-center justify-between gap-3"><div><h2 class="font-display font-semibold">Edit subscriber</h2><p class="text-xs text-gray-500 mt-1">Sumber artikel dan riwayat pendaftaran tetap dipertahankan.</p></div><a href="<?= e(url('/admin/subscribers' . ($q !== '' ? '?q=' . rawurlencode($q) : ''))) ?>" class="text-xs font-medium text-gray-500 hover:text-accent">Batal</a></div>
    <div><label class="block text-xs font-medium mb-1">Nama</label><input name="name" maxlength="120" value="<?= e($edit['name'] ?? '') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
    <div><label class="block text-xs font-medium mb-1">Email</label><input name="email" type="email" required maxlength="190" value="<?= e($edit['email'] ?? '') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"></div>
    <button class="inline-flex items-center gap-1.5 rounded-md px-4 py-2 text-sm font-semibold text-white" style="background:var(--accent)"><?= icon('save', 'w-4 h-4') ?> Simpan perubahan</button>
  </form>
  <?php endif; ?>
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-x-auto"><table class="w-full text-sm"><thead class="text-left text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800"><tr><th class="px-4 py-3">Subscriber</th><th class="px-4 py-3">Sumber</th><th class="px-4 py-3">Mailketing</th><th class="px-4 py-3">Daftar</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-800"><?php foreach ($rows as $s): ?><tr><td class="px-4 py-3"><div class="font-medium"><?= e($s['name'] ?: '—') ?></div><div class="text-xs text-gray-500"><?= e($s['email']) ?></div></td><td class="px-4 py-3 text-xs text-gray-500"><?= e($s['article_title'] ?: ($s['lead_magnet_title'] ?: ($s['source'] ?: '—'))) ?></td><td class="px-4 py-3 text-xs">
  <?php $mkCls = $s['mailketing_status'] === 'sent' ? 'text-emerald-600' : ($s['mailketing_status'] === 'failed' ? 'text-red-600' : ($s['mailketing_status'] === 'skipped' ? 'text-gray-400' : 'text-gray-500')); ?>
  <div class="<?= $mkCls ?>"<?= !empty($s['mk_response']) ? ' title="' . e((string) $s['mk_response']) . '"' : '' ?>>list: <?= e($s['mailketing_status'] ?: '—') ?><?= !empty($s['mk_response']) ? ' <span class="text-gray-400">ⓘ</span>' : '' ?></div>
  <?php if (!empty($s['seq_status'])): $sqCls = $s['seq_status'] === 'sent' ? 'text-emerald-600' : ($s['seq_status'] === 'failed' ? 'text-red-600' : 'text-gray-500'); ?>
  <div class="<?= $sqCls ?> mt-0.5"<?= !empty($s['seq_response']) ? ' title="' . e((string) $s['seq_response']) . '"' : '' ?>>email: <?= e($s['seq_status']) ?><?= !empty($s['seq_response']) ? ' <span class="text-gray-400">ⓘ</span>' : '' ?></div>
  <?php endif; ?>
</td><td class="px-4 py-3 text-xs text-gray-500"><?= e((string) $s['created_at']) ?></td><td class="px-4 py-3 text-xs <?= $s['unsubscribed_at'] ? 'text-gray-500' : 'text-emerald-600' ?>"><?= $s['unsubscribed_at'] ? 'Unsubscribe' : 'Aktif' ?></td><td class="px-4 py-3"><div class="flex justify-end items-center gap-2"><a href="<?= e(url('/admin/subscribers?id=' . (int) $s['id'] . ($q !== '' ? '&q=' . rawurlencode($q) : ''))) ?>" class="inline-flex items-center gap-1 text-xs font-medium text-accent"><?= icon('pen-line', 'w-3.5 h-3.5') ?> Edit</a><form method="post" action="<?= e(url('/actions/admin/delete-subscriber')) ?>" data-confirm="Hapus subscriber ini dari Scribe? Riwayat sequence dan antrean lokalnya akan ikut dihapus, tetapi data di list Mailketing tetap ada." data-confirm-danger data-confirm-title="Hapus subscriber"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button type="submit" class="inline-flex items-center gap-1 text-xs font-medium text-red-600"><?= icon('trash', 'w-3.5 h-3.5') ?> Hapus</button></form></div></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada subscriber.</td></tr><?php endif; ?></tbody></table></div>
</div>
<?php admin_shell_bottom(); ?>
