<?php
// Dashboard kesehatan konten. Audit baca-saja berbasis ruleset lokal aktif.
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/content-health.php';
require_once __DIR__ . '/../../helpers/gsc.php';
require_once __DIR__ . '/../../helpers/content-refresh.php';

$view = in_array($_GET['view'] ?? '', ['search', 'refresh'], true) ? (string) $_GET['view'] : 'health';
$pdo = getDB();
$isWriter = isWriter();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$status = trim((string) ($_GET['status'] ?? ''));
$level = trim((string) ($_GET['level'] ?? ''));
$categoryId = max(0, (int) ($_GET['category'] ?? 0));
if (!in_array($status, ['', 'draft', 'draft_ai', 'scheduled', 'published'], true)) $status = '';
if (!in_array($level, ['', 'healthy', 'warning', 'critical'], true)) $level = '';

$scan = [
    'summary' => ['total' => 0, 'healthy' => 0, 'warning' => 0, 'critical' => 0, 'average_score' => 0],
    'articles' => [], 'total' => 0, 'ruleset_version' => 0,
];
$categories = [];
if ($view === 'health') {
    try {
        $scan = contentHealthScan($pdo, [
            'status' => $status,
            'level' => $level,
            'category_id' => $categoryId,
            'author_id' => $isWriter ? currentUserId() : 0,
        ], $perPage, $offset);
    } catch (Throwable $e) {
        error_log('content-health admin: ' . $e->getMessage());
        flash('error', 'Audit kesehatan konten belum dapat dimuat.', 'error');
    }
    try { $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(); }
    catch (Throwable $e) { $categories = []; }
}
$summary = $scan['summary'];
$totalPages = max(1, (int) ceil($scan['total'] / $perPage));

// Antrean "Perlu Diperbarui" (GSC + umur + skor) — dihitung hanya bila tab-nya aktif.
$refresh = ['items' => [], 'total' => 0, 'has_gsc' => false, 'has_prev' => false];
if ($view === 'refresh') {
    try { $refresh = contentRefreshQueue($pdo, $perPage, $offset); }
    catch (Throwable $e) {
        error_log('content-refresh admin: ' . $e->getMessage());
        flash('error', 'Antrean belum dapat dimuat.', 'error');
    }
}
$refreshPages = max(1, (int) ceil(($refresh['total'] ?: 0) / $perPage));
function contentHealthAdminUrl(array $override): string
{
    $params = array_merge([
        'status' => $_GET['status'] ?? '', 'level' => $_GET['level'] ?? '',
        'category' => $_GET['category'] ?? '', 'page' => $_GET['page'] ?? 1,
    ], $override);
    $params = array_filter($params, static fn($v): bool => $v !== '' && $v !== 0 && $v !== '0');
    return url('/admin/content-health') . ($params ? '?' . http_build_query($params) : '');
}

admin_shell_top('Kesehatan Konten', '/admin/content-health');
?>
<!-- Tab: SEO Health (audit lokal) · Search Performance (data Google) -->
<div class="mb-5 border-b border-gray-200 dark:border-gray-800 flex gap-1">
  <a href="<?= e(url('/admin/content-health')) ?>" class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px <?= $view === 'health' ? '' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200' ?>"<?= $view === 'health' ? ' style="border-color:var(--accent);color:var(--accent)"' : '' ?>>SEO Health</a>
  <a href="<?= e(url('/admin/content-health?view=search')) ?>" class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px <?= $view === 'search' ? '' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200' ?>"<?= $view === 'search' ? ' style="border-color:var(--accent);color:var(--accent)"' : '' ?>>Search Performance</a>
  <a href="<?= e(url('/admin/content-health?view=refresh')) ?>" class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px <?= $view === 'refresh' ? '' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200' ?>"<?= $view === 'refresh' ? ' style="border-color:var(--accent);color:var(--accent)"' : '' ?>>Perlu Diperbarui</a>
</div>

<?php if ($view === 'health'): ?>
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
  <div>
    <p class="text-sm text-gray-600 dark:text-gray-300">Temukan artikel yang perlu diperbaiki; Hermes Agent dapat mengoptimasi dan menerbitkan hasilnya melalui Management API.</p>
    <p class="text-xs text-gray-400 mt-1">Audit lokal · tanpa kredit AI · ruleset v<?= (int) $scan['ruleset_version'] ?></p>
  </div>
  <a href="<?= e(url('/admin/integrations')) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-800 shrink-0">
    <?= icon('key', 'w-4 h-4') ?> Akses Hermes Agent
  </a>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
    <div class="text-xs text-gray-500">Rata-rata kesehatan</div>
    <div class="mt-1 flex items-end gap-1"><strong class="font-display text-2xl"><?= (int) $summary['average_score'] ?></strong><span class="text-xs text-gray-400 mb-1">/100</span></div>
    <div class="text-[11px] text-gray-400 mt-1"><?= (int) $summary['total'] ?> artikel diaudit</div>
  </div>
  <a href="<?= e(contentHealthAdminUrl(['level' => 'healthy', 'page' => 1])) ?>" class="rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-white dark:bg-gray-900 p-4 hover:-translate-y-0.5 transition-transform">
    <div class="text-xs text-emerald-700 dark:text-emerald-300">Sehat</div>
    <div class="font-display text-2xl font-semibold mt-1"><?= (int) $summary['healthy'] ?></div>
    <div class="text-[11px] text-gray-400 mt-1">Skor 80–100</div>
  </a>
  <a href="<?= e(contentHealthAdminUrl(['level' => 'warning', 'page' => 1])) ?>" class="rounded-lg border border-amber-200 dark:border-amber-900/60 bg-white dark:bg-gray-900 p-4 hover:-translate-y-0.5 transition-transform">
    <div class="text-xs text-amber-700 dark:text-amber-300">Perlu perhatian</div>
    <div class="font-display text-2xl font-semibold mt-1"><?= (int) $summary['warning'] ?></div>
    <div class="text-[11px] text-gray-400 mt-1">Skor 60–79</div>
  </a>
  <a href="<?= e(contentHealthAdminUrl(['level' => 'critical', 'page' => 1])) ?>" class="rounded-lg border border-red-200 dark:border-red-900/60 bg-white dark:bg-gray-900 p-4 hover:-translate-y-0.5 transition-transform">
    <div class="text-xs text-red-700 dark:text-red-300">Kritis</div>
    <div class="font-display text-2xl font-semibold mt-1"><?= (int) $summary['critical'] ?></div>
    <div class="text-[11px] text-gray-400 mt-1">Skor di bawah 60</div>
  </a>
</div>

<form method="get" action="<?= e(url('/admin/content-health')) ?>" class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 mb-4 flex flex-wrap items-center gap-2">
  <select name="level" class="px-2.5 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
    <option value="">Semua tingkat</option>
    <option value="critical" <?= $level === 'critical' ? 'selected' : '' ?>>Kritis</option>
    <option value="warning" <?= $level === 'warning' ? 'selected' : '' ?>>Perlu perhatian</option>
    <option value="healthy" <?= $level === 'healthy' ? 'selected' : '' ?>>Sehat</option>
  </select>
  <select name="status" class="px-2.5 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
    <option value="">Semua status</option>
    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
    <option value="draft_ai" <?= $status === 'draft_ai' ? 'selected' : '' ?>>Draft AI</option>
    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
    <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Terjadwal</option>
  </select>
  <select name="category" class="px-2.5 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm max-w-[220px]">
    <option value="">Semua kategori</option>
    <?php foreach ($categories as $category): ?>
      <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="px-3 py-2 rounded-md text-sm font-semibold text-white" style="background:var(--accent)">Terapkan</button>
  <?php if ($level !== '' || $status !== '' || $categoryId > 0): ?>
    <a href="<?= e(url('/admin/content-health')) ?>" class="px-2 py-2 text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">Reset</a>
  <?php endif; ?>
</form>

<div class="space-y-3">
<?php if (!$scan['articles']): ?>
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-5 py-14 text-center">
    <?= icon('activity', 'w-9 h-9 mx-auto text-gray-300 dark:text-gray-600 mb-2') ?>
    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada artikel yang cocok dengan filter ini.</p>
  </div>
<?php else: ?>
  <?php foreach ($scan['articles'] as $article):
    $meta = contentHealthLevelMeta($article['level']);
    $ringColor = $article['level'] === 'healthy' ? '#10b981' : ($article['level'] === 'warning' ? '#f59e0b' : '#ef4444');
  ?>
  <article class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
    <div class="flex items-start gap-3 sm:gap-4">
      <div class="ring-seo relative w-14 h-14 rounded-full shrink-0" style="--val:<?= (int) $article['score'] ?>;--ring-color:<?= $ringColor ?>;color:<?= $ringColor ?>">
        <span class="absolute inset-[5px] rounded-full bg-white dark:bg-gray-900 flex items-center justify-center font-display font-bold text-sm" style="color:<?= $ringColor ?>"><?= (int) $article['score'] ?></span>
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <a href="<?= e(url('/admin/articles/' . $article['id'])) ?>" class="font-semibold text-sm sm:text-base hover:underline truncate max-w-full"><?= e($article['title']) ?></a>
          <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-<?= e($meta['color']) ?>-100 text-<?= e($meta['color']) ?>-700 dark:bg-<?= e($meta['color']) ?>-950/50 dark:text-<?= e($meta['color']) ?>-300"><?= e($meta['label']) ?></span>
          <?= articleStatusBadge($article['status']) ?>
        </div>
        <div class="text-xs text-gray-400 mt-1 flex flex-wrap gap-x-3 gap-y-1">
          <span><?= number_format($article['word_count'], 0, ',', '.') ?> kata</span>
          <span><?= (int) $article['passed_checks'] ?>/<?= (int) $article['total_checks'] ?> pemeriksaan lolos</span>
          <span><?= e($article['category_name'] ?? 'Tanpa kategori') ?></span>
        </div>
        <?php if ($article['issues']): ?>
        <ul class="mt-3 grid md:grid-cols-2 gap-x-5 gap-y-1.5">
          <?php foreach (array_slice($article['issues'], 0, 4) as $issue): ?>
          <li class="flex items-start gap-1.5 text-xs text-gray-600 dark:text-gray-300">
            <span class="mt-1 w-1.5 h-1.5 rounded-full shrink-0" style="background:<?= $issue['priority'] === 'high' ? '#ef4444' : '#f59e0b' ?>"></span>
            <span><strong class="font-medium"><?= e($issue['label']) ?>.</strong> <?= e($issue['hint']) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <div class="hidden sm:flex items-center gap-1 shrink-0">
        <a href="<?= e(url('/admin/articles/' . $article['id'] . '/preview')) ?>" target="_blank" rel="noopener" class="p-2 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" title="Pratinjau"><?= icon('eye', 'w-4 h-4') ?></a>
        <a href="<?= e(url('/admin/articles/' . $article['id'])) ?>" class="p-2 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" title="Perbaiki artikel"><?= icon('pen', 'w-4 h-4') ?></a>
      </div>
    </div>
    <a href="<?= e(url('/admin/articles/' . $article['id'])) ?>" class="sm:hidden mt-3 inline-flex items-center gap-1 text-xs font-semibold" style="color:var(--accent)"><?= icon('pen', 'w-3.5 h-3.5') ?> Perbaiki artikel</a>
  </article>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-between mt-4 text-sm">
  <span class="text-gray-500">Halaman <?= $page ?> dari <?= $totalPages ?> · <?= (int) $scan['total'] ?> artikel</span>
  <div class="flex gap-1">
    <?php if ($page > 1): ?><a href="<?= e(contentHealthAdminUrl(['page' => $page - 1])) ?>" class="px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-700">Sebelumnya</a><?php endif; ?>
    <?php if ($page < $totalPages): ?><a href="<?= e(contentHealthAdminUrl(['page' => $page + 1])) ?>" class="px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-700">Berikutnya</a><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php elseif ($view === 'search'): /* Search Performance (Google Search Console) */
$gscOk    = gscConfigured();
$gscOn    = gscEnabled();
$gscEmail = gscServiceEmail();
$gscSite  = gscSiteUrl();
$cache    = gscCache();
$f        = in_array($_GET['f'] ?? '', ['zero', 'lowctr', 'page2'], true) ? (string) $_GET['f'] : '';

$rows = [];
$tot  = ['clicks' => 0, 'impressions' => 0];
if ($gscOk && $cache['rows']) {
    $metrics = gscMetricsByArticlePath();
    try { $pub = $pdo->query("SELECT id, title, slug FROM articles WHERE status = 'published' ORDER BY published_at DESC")->fetchAll(); }
    catch (Throwable $e) { $pub = []; }
    foreach ($pub as $a) {
        $m = $metrics['/artikel/' . $a['slug']] ?? ['clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'position' => 0.0];
        $rows[] = ['id' => (int) $a['id'], 'title' => (string) $a['title']] + $m;
        $tot['clicks'] += (int) $m['clicks'];
        $tot['impressions'] += (int) $m['impressions'];
    }
    if ($f === 'zero')       $rows = array_values(array_filter($rows, static fn($r) => $r['clicks'] === 0));
    elseif ($f === 'lowctr') $rows = array_values(array_filter($rows, static fn($r) => $r['impressions'] >= 50 && $r['ctr'] < 0.02));
    elseif ($f === 'page2')  $rows = array_values(array_filter($rows, static fn($r) => $r['position'] > 10 && $r['position'] <= 20));
    usort($rows, static fn($a, $b) => [$b['clicks'], $b['impressions']] <=> [$a['clicks'], $a['impressions']]);
}
$fUrl = static fn(string $ff): string => url('/admin/content-health?view=search' . ($ff !== '' ? '&f=' . $ff : ''));

// Form konfigurasi (dipakai di kartu setup & panel "Ubah pengaturan"). Admin saja.
$gscFormHtml = '';
if (!$isWriter) {
    ob_start(); ?>
    <form method="post" action="<?= e(url('/actions/admin/save-gsc')) ?>" class="space-y-4">
      <?= csrfField() ?>
      <div>
        <label class="block text-sm font-medium mb-1">URL properti Search Console</label>
        <input type="text" name="gsc_site_url" value="<?= e($gscSite) ?>" placeholder="sc-domain:contoh.com   atau   https://contoh.com/" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        <p class="text-xs text-gray-500 mt-1">Domain property: <code>sc-domain:contoh.com</code>. URL-prefix: <code>https://contoh.com/</code> (persis seperti di Search Console).</p>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Kunci Service Account (JSON)</label>
        <textarea name="gsc_sa_json" rows="4" placeholder="<?= $gscOk ? 'Tersimpan — isi hanya bila ingin mengganti.' : 'Tempel seluruh isi file JSON service account di sini' ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono"></textarea>
        <p class="text-xs text-gray-500 mt-1">Rahasia — disimpan di server, tidak pernah ditampilkan kembali.<?php if ($gscEmail !== ''): ?> Email SA saat ini: <code><?= e($gscEmail) ?></code><?php endif; ?></p>
      </div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="gsc_enabled" value="1" <?= $gscOn ? 'checked' : '' ?>> Aktifkan Search Performance</label>
      <button type="submit" class="px-4 py-2 rounded-md text-sm font-semibold text-white" style="background:var(--accent)">Simpan &amp; Tes Koneksi</button>
    </form>
    <?php $gscFormHtml = (string) ob_get_clean();
}
?>

<?php if (!$gscOk): ?>
  <!-- Belum tersambung -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
    <div class="flex items-start gap-3">
      <span class="inline-flex w-10 h-10 rounded-lg items-center justify-center shrink-0" style="background:color-mix(in srgb, var(--accent) 12%, transparent); color:var(--accent)"><?= icon('search', 'w-5 h-5') ?></span>
      <div>
        <h2 class="font-display font-semibold text-lg">Hubungkan Google Search Console</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Tampilkan klik, impresi, CTR, dan posisi nyata dari Google per artikel.</p>
      </div>
    </div>
    <?php if ($isWriter): ?>
      <p class="mt-5 text-sm text-amber-600 dark:text-amber-400">Search Console belum dikonfigurasi. Hubungi administrator untuk mengaktifkannya.</p>
    <?php else: ?>
      <ol class="mt-5 mb-3 space-y-1.5 text-sm text-gray-600 dark:text-gray-300 list-decimal pl-5">
        <li>Buat <strong>service account</strong> di <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" rel="noopener" class="underline" style="color:var(--accent)">Google Cloud</a>, <a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener" class="underline" style="color:var(--accent)">aktifkan Search Console API</a>, lalu unduh kunci <strong>JSON</strong>.</li>
        <li>Di <a href="https://search.google.com/search-console/users" target="_blank" rel="noopener" class="underline" style="color:var(--accent)">Search Console → Users and permissions</a>, tambahkan email service account sebagai pengguna.</li>
        <li>Tempel isi JSON + URL properti di bawah, lalu simpan &amp; tes koneksi.</li>
      </ol>
      <p class="mb-5 text-xs text-gray-500">Butuh langkah lengkap? Lihat <a href="<?= e(url('/admin/docs') . '#search-performance') ?>" class="underline" style="color:var(--accent)">Dokumentasi → Search Performance</a>.</p>
      <?= $gscFormHtml ?>
    <?php endif; ?>
  </div>

<?php else: ?>
  <!-- Tersambung -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 mb-4 flex flex-wrap items-center gap-x-4 gap-y-2">
    <span class="inline-flex items-center gap-1.5 text-sm font-medium" style="color:var(--accent)"><?= icon('check-circle', 'w-4 h-4') ?> Tersambung</span>
    <span class="text-xs text-gray-500 truncate">Properti: <code><?= e($gscSite) ?></code></span>
    <span class="text-xs text-gray-400">Data: <?= $cache['at'] > 0 ? e(date('d M Y H:i', $cache['at'])) . ' · ' . e($cache['start']) . ' s/d ' . e($cache['end']) : 'belum ditarik' ?></span>
    <form method="post" action="<?= e(url('/actions/admin/gsc-refresh')) ?>" class="ml-auto">
      <?= csrfField() ?>
      <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-800"><?= icon('refresh-cw', 'w-4 h-4') ?> Perbarui data</button>
    </form>
  </div>

  <?php if (!$cache['rows']): ?>
    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-5 py-14 text-center">
      <?= icon('search', 'w-9 h-9 mx-auto text-gray-300 dark:text-gray-600 mb-2') ?>
      <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data. Klik <strong>Perbarui data</strong> untuk menarik dari Search Console.</p>
      <p class="text-xs text-gray-400 mt-1">Google menahan data ~2–3 hari terakhir; artikel yang baru tayang mungkin belum muncul.</p>
    </div>
  <?php else: ?>
    <!-- Ringkasan + filter -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4"><div class="text-xs text-gray-500">Total klik</div><div class="font-display text-2xl font-semibold mt-1"><?= number_format($tot['clicks'], 0, ',', '.') ?></div></div>
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4"><div class="text-xs text-gray-500">Total impresi</div><div class="font-display text-2xl font-semibold mt-1"><?= number_format($tot['impressions'], 0, ',', '.') ?></div></div>
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4"><div class="text-xs text-gray-500">CTR rata-rata</div><div class="font-display text-2xl font-semibold mt-1"><?= $tot['impressions'] > 0 ? number_format($tot['clicks'] / $tot['impressions'] * 100, 1, ',', '.') . '%' : '—' ?></div></div>
    </div>

    <?php
    $chips = ['' => 'Semua', 'zero' => 'Belum dapat klik', 'lowctr' => 'CTR rendah', 'page2' => 'Posisi 11–20 (halaman 2)'];
    ?>
    <div class="flex flex-wrap gap-2 mb-4">
      <?php foreach ($chips as $key => $label): $on = $f === $key; ?>
        <a href="<?= e($fUrl($key)) ?>" class="px-3 py-1.5 rounded-md text-xs font-medium border <?= $on ? 'text-white' : 'border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' ?>"<?= $on ? ' style="background:var(--accent);border-color:var(--accent)"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-5 py-14 text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada artikel yang cocok dengan filter ini.</p>
      </div>
    <?php else: ?>
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-left text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800">
              <tr>
                <th class="px-4 py-3 font-medium">Artikel</th>
                <th class="px-4 py-3 font-medium text-right">Klik</th>
                <th class="px-4 py-3 font-medium text-right">Impresi</th>
                <th class="px-4 py-3 font-medium text-right">CTR</th>
                <th class="px-4 py-3 font-medium text-right">Posisi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              <?php foreach ($rows as $r): ?>
              <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="px-4 py-3 max-w-[360px]"><a href="<?= e(url('/admin/articles/' . $r['id'])) ?>" class="font-medium hover:underline line-clamp-1"><?= e($r['title']) ?></a></td>
                <td class="px-4 py-3 text-right tabular-nums <?= $r['clicks'] === 0 ? 'text-gray-400' : 'font-semibold' ?>"><?= number_format($r['clicks'], 0, ',', '.') ?></td>
                <td class="px-4 py-3 text-right tabular-nums"><?= number_format($r['impressions'], 0, ',', '.') ?></td>
                <td class="px-4 py-3 text-right tabular-nums"><?= $r['impressions'] > 0 ? number_format($r['ctr'] * 100, 1, ',', '.') . '%' : '—' ?></td>
                <td class="px-4 py-3 text-right tabular-nums"><?= $r['position'] > 0 ? number_format($r['position'], 1, ',', '.') : '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <p class="text-xs text-gray-400 mt-2">Menampilkan <?= count($rows) ?> artikel published · rentang <?= (int) $cache['range'] ?> hari · sumber: Google Search Console.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!$isWriter): ?>
  <details class="mt-5 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
    <summary class="cursor-pointer select-none px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">Ubah pengaturan koneksi</summary>
    <div class="px-4 pb-4 pt-1"><?= $gscFormHtml ?></div>
  </details>
  <?php endif; ?>
<?php endif; ?>

<?php else: /* refresh — Antrean "Perlu Diperbarui" (GSC + umur + skor) */ ?>
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
  <div>
    <h1 class="font-display text-xl font-semibold">Perlu Diperbarui</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Artikel published berprioritas untuk dioptimasi — gabungan sinyal Search Console, umur, dan skor SEO.</p>
  </div>
  <a href="<?= e(url('/admin/content-health?view=search')) ?>" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 shrink-0"><?= icon('refresh-cw', 'w-4 h-4') ?> Perbarui data GSC</a>
</div>

<?php if (!$refresh['has_gsc']): ?>
<div class="rounded-lg border p-3.5 mb-4 text-sm" style="border-color:color-mix(in srgb, var(--accent) 26%, transparent); background:color-mix(in srgb, var(--accent) 7%, transparent)">
  Sambungkan <a href="<?= e(url('/admin/content-health?view=search')) ?>" class="underline" style="color:var(--accent)">Search Performance</a> agar antrean memakai data klik &amp; posisi nyata. Sementara ini antrean memakai skor SEO &amp; umur artikel.
</div>
<?php endif; ?>

<?php if (!$refresh['items']): ?>
<div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 text-center text-sm text-gray-500">Tidak ada artikel yang perlu diperbarui — semua sehat.</div>
<?php else:
$rchip = static function (string $code): string {
    $tone = $code === 'zero' ? 'red' : (in_array($code, ['page2', 'dropped', 'lowctr'], true) ? 'amber' : 'gray');
    $cls = [
        'red'   => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
        'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
        'gray'  => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    ][$tone];
    return '<span class="inline-block rounded px-1.5 py-0.5 text-[11px] font-medium ' . $cls . '">' . e(contentRefreshReasonLabel($code)) . '</span>';
};
?>
<div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
      <tr>
        <th class="text-left font-medium px-4 py-3">Artikel</th>
        <th class="text-left font-medium px-4 py-3">Alasan</th>
        <th class="text-right font-medium px-4 py-3">Skor</th>
        <th class="text-right font-medium px-4 py-3">Posisi</th>
        <th class="text-right font-medium px-4 py-3">Klik/Impr</th>
        <th class="text-right font-medium px-4 py-3">Umur</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($refresh['items'] as $it): $g = $it['gsc']; ?>
      <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
        <td class="px-4 py-3"><a href="<?= e(url($it['edit_url'])) ?>" class="font-medium hover:underline" style="color:var(--accent)"><?= e(mb_strimwidth((string) $it['title'], 0, 60, '…')) ?></a></td>
        <td class="px-4 py-3"><div class="flex flex-wrap gap-1"><?php foreach ($it['reasons'] as $rr) echo $rchip($rr['code']); ?></div></td>
        <td class="px-4 py-3 text-right tabular-nums"><?= (int) $it['seo_score'] ?></td>
        <td class="px-4 py-3 text-right tabular-nums">
          <?php if ($g && $g['position'] !== null): ?>
            <?= number_format($g['position'], 1, ',', '.') ?><?php if ($g['position_delta'] !== null && $g['position_delta'] != 0): ?><span class="text-[11px] <?= $g['position_delta'] > 0 ? 'text-red-500' : 'text-emerald-500' ?>"> <?= ($g['position_delta'] > 0 ? '+' : '−') . number_format(abs($g['position_delta']), 1, ',', '.') ?></span><?php endif; ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td class="px-4 py-3 text-right tabular-nums text-gray-500"><?= $g ? ((int) $g['clicks'] . '/' . (int) $g['impressions']) : '—' ?></td>
        <td class="px-4 py-3 text-right tabular-nums text-gray-500"><?= $it['age_days'] !== null ? ((int) $it['age_days'] . 'h') : '—' ?></td>
        <td class="px-4 py-3 text-right"><a href="<?= e(url($it['edit_url'])) ?>" class="text-xs font-medium underline" style="color:var(--accent)">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($refreshPages > 1): ?>
<div class="mt-4 flex items-center justify-center gap-2 text-sm">
  <?php for ($p = 1; $p <= $refreshPages; $p++): ?>
    <a href="<?= e(url('/admin/content-health?view=refresh' . ($p > 1 ? '&page=' . $p : ''))) ?>" class="px-3 py-1.5 rounded-md border <?= $p === $page ? 'text-white' : 'border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800' ?>"<?= $p === $page ? ' style="background:var(--accent);border-color:var(--accent)"' : '' ?>><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php endif; ?>
<?php admin_shell_bottom(); ?>
