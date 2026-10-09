<?php
// Route /feed — RSS 2.0, 20 artikel terbaru (excerpt, BUKAN konten penuh).
require_once __DIR__ . '/../../helpers/frontend.php';
require_once __DIR__ . '/../../helpers/schedule.php';

scribeLazyPublish();

$brand = feBrand();
$esc = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
$cdata = fn($s) => '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string) $s) . ']]>';

try {
    $rows = getDB()->query(
        "SELECT a.title, a.slug, a.excerpt, a.content, a.published_at,
                c.name AS cat_name
         FROM articles a LEFT JOIN categories c ON c.id = a.category_id
         WHERE a.status='published' AND a.published_at<=NOW()
         ORDER BY a.published_at DESC LIMIT 20"
    )->fetchAll();
} catch (Throwable $e) {
    error_log('feed: ' . $e->getMessage());
    $rows = [];
}

$selfUrl = url('/feed');
header('Content-Type: application/rss+xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= $esc($brand['name']) ?></title>
  <link><?= $esc(url('/')) ?></link>
  <atom:link href="<?= $esc($selfUrl) ?>" rel="self" type="application/rss+xml"/>
  <description><?= $esc($brand['tagline'] !== '' ? $brand['tagline'] : $brand['name']) ?></description>
  <language>id</language>
  <?php if ($rows): ?><lastBuildDate><?= $esc(date('r', strtotime((string) $rows[0]['published_at']))) ?></lastBuildDate><?php endif; ?>
<?php foreach ($rows as $a):
    $link = url('/artikel/' . rawurlencode($a['slug']));
    $desc = trim((string) ($a['excerpt'] ?? '')) !== '' ? $a['excerpt'] : truncateText((string) $a['content'], 300);
?>
  <item>
    <title><?= $esc($a['title']) ?></title>
    <link><?= $esc($link) ?></link>
    <guid isPermaLink="true"><?= $esc($link) ?></guid>
    <?php if (!empty($a['cat_name'])): ?><category><?= $esc($a['cat_name']) ?></category><?php endif; ?>
    <pubDate><?= $esc(date('r', strtotime((string) $a['published_at']))) ?></pubDate>
    <description><?= $cdata(truncateText($desc, 300)) ?></description>
  </item>
<?php endforeach; ?>
</channel>
</rss>
