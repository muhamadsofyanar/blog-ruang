<?php
// ════════════════════════════════════════════════════════════════════════
// Sitemap XML (Track B / B-04). File statis cache/sitemap.xml, regenerate
// EVENT-DRIVEN (hook publish/update/delete artikel & kategori) — BUKAN cron.
// Isi: home + kategori (yang punya artikel published) + artikel published
// (loc, lastmod=updated_at, image:image). Cover FALLBACK (SVG) TIDAK dipakai
// sebagai image — pakai og:image PNG (scribeArticleOgImage jalur B-03).
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/og-image.php';

function scribeSitemapPath(): string
{
    return dirname(__DIR__) . '/cache/sitemap.xml';
}

/** Bangun + tulis cache/sitemap.xml (atomik). Return true bila sukses. */
function scribeGenerateSitemap(): bool
{
    $esc = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
          . 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

    // Home.
    $xml .= "  <url><loc>" . $esc(url('/')) . "</loc><changefreq>daily</changefreq><priority>1.0</priority></url>\n";

    try {
        $pdo = getDB();
        // Kategori yang punya artikel published.
        $cats = $pdo->query(
            "SELECT c.slug, MAX(a.updated_at) AS lastmod
             FROM categories c JOIN articles a ON a.category_id = c.id
             WHERE a.status='published' AND a.published_at<=NOW()
             GROUP BY c.id, c.slug ORDER BY c.slug"
        )->fetchAll();
        foreach ($cats as $c) {
            $xml .= "  <url><loc>" . $esc(url('/kategori/' . rawurlencode($c['slug']))) . "</loc>"
                  . "<lastmod>" . $esc(date('c', strtotime((string) $c['lastmod']))) . "</lastmod>"
                  . "<changefreq>weekly</changefreq></url>\n";
        }

        // Artikel published.
        $arts = $pdo->query(
            "SELECT slug, title, updated_at, cover_image, cover_is_fallback
             FROM articles WHERE status='published' AND published_at<=NOW()
             ORDER BY updated_at DESC"
        )->fetchAll();
        foreach ($arts as $a) {
            $loc = url('/artikel/' . rawurlencode($a['slug']));
            $img = scribeArticleOgImage($a); // real cover | og_default | PNG (bukan SVG)
            $xml .= "  <url><loc>" . $esc($loc) . "</loc>"
                  . "<lastmod>" . $esc(date('c', strtotime((string) $a['updated_at']))) . "</lastmod>"
                  . "<changefreq>weekly</changefreq>";
            if ($img !== '' && !str_ends_with(strtolower($img), '.svg')) {
                $xml .= "<image:image><image:loc>" . $esc($img) . "</image:loc>"
                      . "<image:title>" . $esc($a['title']) . "</image:title></image:image>";
            }
            $xml .= "</url>\n";
        }
    } catch (Throwable $e) {
        error_log('sitemap: ' . $e->getMessage());
    }

    $xml .= "</urlset>\n";

    $path = scribeSitemapPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $tmp = $path . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $xml) === false) return false;
    return @rename($tmp, $path);
}

/** Serve sitemap; generate on-demand sekali bila file belum ada. */
function scribeServeSitemap(): void
{
    $path = scribeSitemapPath();
    if (!is_file($path)) scribeGenerateSitemap();
    header('Content-Type: application/xml; charset=UTF-8');
    if (is_file($path)) { readfile($path); }
    else { echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>'; }
}
