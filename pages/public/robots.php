<?php
// ════════════════════════════════════════════════════════════════════════
// Route /robots.txt — robots.txt DINAMIS. Dibangun dari APP_URL sehingga:
//   - menunjuk ke sitemap absolut yang benar,
//   - melarang area privat (admin/aksi/API/login/pencarian/unsubscribe),
//   - subfolder-safe (prefix base path bila instalasi di subfolder).
// Diakses via route (bootstrap sudah dimuat index.php). Tanpa DB.
// ════════════════════════════════════════════════════════════════════════

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex'); // berkas robots.txt sendiri tak perlu diindeks

// Base path instalasi ('' untuk root domain, '/sub' untuk subfolder).
$base = rtrim((string) (parse_url(APP_URL, PHP_URL_PATH) ?? ''), '/');

$disallow = ['/admin', '/actions', '/api/', '/login', '/logout', '/search', '/unsubscribe'];

$lines   = ['User-agent: *'];
foreach ($disallow as $path) {
    $lines[] = 'Disallow: ' . $base . $path;
}
$lines[] = '';
$lines[] = 'Sitemap: ' . rtrim(APP_URL, '/') . '/sitemap.xml';

echo implode("\n", $lines) . "\n";
