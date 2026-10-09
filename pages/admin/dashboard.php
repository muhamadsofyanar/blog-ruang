<?php
// Dashboard admin — statistik ringan (nol rapi saat fresh). Query murah.
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/AverionAiAdapter.php';
require_once __DIR__ . '/../../helpers/ai-balance.php';
require_once __DIR__ . '/../../helpers/content-health.php';

$pdo = getDB();
$role = currentRole();

function scribeCount(PDO $pdo, string $sql, array $args = []): int
{
    try { $s = $pdo->prepare($sql); $s->execute($args); return (int) $s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

$isWriter = $role === 'writer';
$authorClause = $isWriter ? ' AND author_id = ' . currentUserId() : '';

$published = scribeCount($pdo, "SELECT COUNT(*) FROM articles WHERE status = 'published'" . $authorClause);
$draft     = scribeCount($pdo, "SELECT COUNT(*) FROM articles WHERE status = 'draft'" . $authorClause);
$draftAi   = scribeCount($pdo, "SELECT COUNT(*) FROM articles WHERE status = 'draft_ai'" . $authorClause);
$scheduled = scribeCount($pdo, "SELECT COUNT(*) FROM articles WHERE status = 'scheduled'" . $authorClause);
$totalArticles = scribeCount($pdo, "SELECT COUNT(*) FROM articles" . ($isWriter ? " WHERE author_id = " . currentUserId() : ""));
$healthQuick = contentHealthQuickSummary($pdo, $isWriter ? currentUserId() : null);

// Skor SEO rata-rata artikel published (analisis terbaru per artikel — query ringan).
$avgSeo = null;
try {
    $avgSql = "SELECT AVG(latest.score) FROM (
                 SELECT (SELECT s.score FROM seo_ai_analysis s WHERE s.article_id = a.id ORDER BY s.id DESC LIMIT 1) AS score
                 FROM articles a WHERE a.status = 'published'" . $authorClause . "
               ) latest WHERE latest.score IS NOT NULL";
    $v = $pdo->query($avgSql)->fetchColumn();
    if ($v !== null && $v !== false) $avgSeo = (int) round((float) $v);
} catch (Throwable $e) { $avgSeo = null; }

// 5 artikel terbaru (tanpa kolom content — pola query terkunci).
try {
    $recentSql = "SELECT id, title, status, updated_at FROM articles"
        . ($isWriter ? " WHERE author_id = " . currentUserId() : "")
        . " ORDER BY updated_at DESC LIMIT 5";
    $recent = $pdo->query($recentSql)->fetchAll();
} catch (Throwable $e) { $recent = []; }

$licStatus = getSetting('license_status', 'unknown');

// Saldo AI (cache 5 menit) untuk widget mini.
$__adapter = new AverionAiAdapter();
$__bal = $__adapter->getBalanceCached();
$balData = $__bal['ok'] ? $__bal['data'] : null;

admin_shell_top('Dashboard', '/admin');

$stats = [
    ['Published', $published, 'check-circle'],
    ['Draft', $draft, 'pen-line'],
    ['Draft AI', $draftAi, 'file-text'],
    ['Terjadwal', $scheduled, 'file-text'],
];
?>
<?php
// Kartu statistik — kartu pertama HERO bergradien brand, sisanya netral + ikon soft.
// Layout HORIZONTAL compact: ikon chip (40px fixed) di kiri, angka besar +
// label di kanan. Padding lewat kelas .p-4 (density-driven var(--pad)).
$renderStat = function (array $s, bool $hero) {
    [$label, $val, $ico] = $s;
    echo '<div class="stat-card ' . ($hero ? 'stat-hero ' : '') . 'rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 flex items-center gap-3">';
    echo '<span class="icon-chip shrink-0">' . icon($ico, 'w-5 h-5') . '</span>';
    echo '<div class="min-w-0 leading-tight">';
    echo '<div class="stat-num text-2xl font-semibold font-display leading-none">' . number_format($val, 0, ',', '.') . '</div>';
    echo '<div class="stat-label mt-1 text-xs font-medium text-gray-500 dark:text-gray-400 truncate">' . e($label) . '</div>';
    echo '</div>';
    echo '</div>';
};
?>
<?php if ($totalArticles === 0): ?>
<!-- Mulai cepat (instalasi kosong) -->
<div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 mb-4 flex flex-col sm:flex-row items-start sm:items-center gap-4">
  <span class="inline-flex items-center justify-center w-12 h-12 rounded-lg text-white shrink-0" style="background:var(--accent)"><?= icon('pen-line', 'w-6 h-6') ?></span>
  <div class="flex-1">
    <h2 class="font-display font-semibold">Selamat datang di Averion SEO Engine</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Mulai dengan menulis artikel pertama — riset keyword, outline, dan draft dibantu AI.</p>
  </div>
  <a href="<?= e(url('/admin/articles/new')) ?>" class="inline-flex items-center gap-1.5 rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90 shrink-0" style="background:var(--accent)"><?= icon('plus', 'w-4 h-4') ?> Buat Artikel Pertama</a>
</div>
<?php endif; ?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
  <?php foreach ($stats as $i => $s) $renderStat($s, $i === 0); ?>
</div>

<?php if ($avgSeo !== null): ?>
<?php $ringColor = $avgSeo >= 80 ? '#10b981' : ($avgSeo >= 50 ? '#f59e0b' : '#ef4444'); ?>
<div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 flex items-center gap-4">
  <div class="ring-seo relative w-16 h-16 rounded-full shrink-0" style="--val:<?= (int) $avgSeo ?>;--ring-color:<?= $ringColor ?>;color:<?= $ringColor ?>">
    <span class="absolute inset-[5px] rounded-full bg-white dark:bg-gray-900 flex items-center justify-center font-display font-bold text-lg" style="color:<?= $ringColor ?>"><?= $avgSeo ?></span>
  </div>
  <div>
    <div class="font-display font-semibold">Skor SEO Rata-rata</div>
    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Dari analisis AI artikel published</div>
  </div>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4 mt-4">
  <div class="lg:col-span-2 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
      <h2 class="font-display font-semibold text-sm">Artikel Terbaru</h2>
      <a href="<?= e(url('/admin/articles')) ?>" class="text-xs font-medium text-accent" style="color:var(--accent)">Lihat semua</a>
    </div>
    <?php if (!$recent): ?>
      <div class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
        <?= icon('file-text', 'w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600') ?>
        Belum ada artikel. Mulai tulis artikel pertama Anda.
      </div>
    <?php else: ?>
      <ul class="divide-y divide-gray-100 dark:divide-gray-800">
        <?php foreach ($recent as $a): ?>
        <li class="px-4 py-3 flex items-center justify-between gap-3">
          <span class="text-sm font-medium truncate"><?= e($a['title']) ?></span>
          <?= articleStatusBadge($a['status']) ?>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="space-y-4">
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
    <div class="flex items-center justify-between mb-2">
      <div class="flex items-center gap-2"><?= icon('activity', 'w-5 h-5 text-gray-400') ?><h2 class="font-display font-semibold text-sm">Kesehatan Konten</h2></div>
      <a href="<?= e(url('/admin/content-health')) ?>" class="text-xs font-medium" style="color:var(--accent)">Audit</a>
    </div>
    <div class="flex items-end gap-1.5">
      <strong class="font-display text-2xl"><?= (int) $healthQuick['needs_review'] ?></strong>
      <span class="text-xs text-gray-500 mb-1">perlu diperiksa</span>
    </div>
    <p class="text-[11px] text-gray-400 mt-1">Dari <?= (int) $healthQuick['total'] ?> artikel · audit lengkap tanpa kredit AI</p>
  </div>

  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2"><?= icon('coins', 'w-5 h-5 text-gray-400') ?><h2 class="font-display font-semibold text-sm">Saldo AI</h2></div>
      <a href="<?= e(url('/admin/credits')) ?>" class="text-xs font-medium" style="color:var(--accent)">Detail</a>
    </div>
    <?= balanceWidgetHtml($balData, 'mini') ?>
  </div>

  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
    <div class="flex items-center gap-2 mb-3">
      <?= icon('shield-check', 'w-5 h-5 text-gray-400') ?>
      <h2 class="font-display font-semibold text-sm">Status Lisensi</h2>
    </div>
    <?php
      $badge = ['active' => 'emerald', 'expired' => 'red', 'suspended' => 'red', 'unknown' => 'gray'][$licStatus] ?? 'gray';
    ?>
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-md bg-<?= $badge ?>-100 text-<?= $badge ?>-700 dark:bg-<?= $badge ?>-950/50 dark:text-<?= $badge ?>-300">
      <?= e(ucfirst($licStatus)) ?>
    </span>
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">Plan: <?= e(getSetting('license_plan', '-')) ?></p>
    <a href="<?= e(url('/admin/license')) ?>" class="mt-3 inline-block text-xs font-medium" style="color:var(--accent)">Kelola lisensi &rarr;</a>
  </div>
  </div>
</div>
<?php admin_shell_bottom(true); ?>
