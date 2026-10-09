<?php
// ════════════════════════════════════════════════════════════════════════
// SEO head builder (Track B / B-03). Menyusun $ctx untuk theme_head (title/
// description/canonical/og_image/og_type) + head_extra (meta artikel + JSON-LD
// Article + BreadcrumbList + FAQPage). Dipakai halaman artikel; helper JSON-LD
// juga tersedia untuk halaman publik lain. FAQPage HANYA dari seo_ai_faq
// approved (BUKAN parse HTML).
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/frontend.php';
require_once __DIR__ . '/og-image.php';

/**
 * Bangun $ctx SEO untuk halaman artikel.
 * @param array $a       baris artikel penuh
 * @param array $faqs    [['q'=>..,'a'=>..], ...] dari seo_ai_faq approved (boleh kosong)
 */
function seoArticleContext(array $a, array $faqs = []): array
{
    $brand = feBrand();
    $canonical = trim((string) ($a['canonical_url'] ?? '')) !== ''
        ? $a['canonical_url']
        : url('/artikel/' . rawurlencode((string) $a['slug']));

    $title = trim((string) ($a['meta_title'] ?? '')) !== ''
        ? $a['meta_title']
        : ($a['title'] . ' | ' . $brand['name']);

    $descSrc = trim((string) ($a['meta_description'] ?? '')) !== ''
        ? $a['meta_description']
        : (trim((string) ($a['excerpt'] ?? '')) !== '' ? $a['excerpt'] : ($a['content'] ?? ''));
    $desc = truncateText((string) $descSrc, 160);

    $ogImage = scribeArticleOgImage($a);

    // ── head_extra: meta artikel + JSON-LD @graph ──
    $graph = [seoArticleJsonLd($a, $canonical, $desc, $ogImage)];
    $bc = seoBreadcrumbJsonLd($a);
    if ($bc) $graph[] = $bc;
    if ($faqs) $graph[] = seoFaqJsonLd($faqs);

    $extra = '';
    if (!empty($a['published_at'])) {
        $extra .= '<meta property="article:published_time" content="' . e(date('c', strtotime((string) $a['published_at']))) . '">';
    }
    if (!empty($a['updated_at'])) {
        $extra .= '<meta property="article:modified_time" content="' . e(date('c', strtotime((string) $a['updated_at']))) . '">';
    }
    if (!empty($a['cat_name'])) {
        $extra .= '<meta property="article:section" content="' . e((string) $a['cat_name']) . '">';
    }
    $extra .= seoJsonLdScript($graph);

    return [
        'title'       => $title,
        'description' => $desc,
        'canonical'   => $canonical,
        'og_image'    => $ogImage,
        'og_type'     => 'article',
        'active'      => (string) ($a['cat_slug'] ?? ''),
        'head_extra'  => $extra,
    ];
}

/** JSON-LD Article. */
function seoArticleJsonLd(array $a, string $canonical, string $desc, string $ogImage): array
{
    $brand = feBrand();
    $logoUrl = $brand['logo'] !== '' ? rtrim(UPLOAD_URL, '/') . '/' . $brand['logo'] : '';
    $node = [
        '@type'            => 'Article',
        'headline'         => (string) $a['title'],
        'description'      => $desc,
        'mainEntityOfPage' => $canonical,
        'url'              => $canonical,
    ];
    if ($ogImage !== '') $node['image'] = [$ogImage];
    if (!empty($a['published_at'])) $node['datePublished'] = date('c', strtotime((string) $a['published_at']));
    $node['dateModified'] = date('c', strtotime((string) ($a['updated_at'] ?? $a['published_at'] ?? 'now')));
    if (!empty($a['author_name'])) {
        // Tautkan Person ke halaman penulis (E-E-A-T) — perkuat entitas penulis.
        $authorUrl = url('/penulis/' . slugify((string) $a['author_name']));
        $node['author'] = ['@type' => 'Person', 'name' => (string) $a['author_name'], 'url' => $authorUrl];
    }
    $publisher = ['@type' => 'Organization', 'name' => $brand['name']];
    if ($logoUrl !== '') $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $logoUrl];
    $node['publisher'] = $publisher;
    return $node;
}

/** JSON-LD BreadcrumbList (Home > Kategori > Judul). */
function seoBreadcrumbJsonLd(array $a): ?array
{
    $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => url('/')]];
    $pos = 2;
    if (!empty($a['cat_name']) && !empty($a['cat_slug'])) {
        $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => (string) $a['cat_name'],
                    'item' => url('/kategori/' . rawurlencode((string) $a['cat_slug']))];
    }
    $items[] = ['@type' => 'ListItem', 'position' => $pos, 'name' => (string) $a['title'],
                'item' => url('/artikel/' . rawurlencode((string) $a['slug']))];
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

/** JSON-LD FAQPage dari daftar {q,a} (approved). */
function seoFaqJsonLd(array $faqs): array
{
    $q = [];
    foreach ($faqs as $f) {
        $q[] = [
            '@type' => 'Question',
            'name'  => (string) ($f['q'] ?? ''),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) ($f['a'] ?? '')],
        ];
    }
    return ['@type' => 'FAQPage', 'mainEntity' => $q];
}

/** Bungkus @graph JSON-LD ke <script>. HEX_TAG cegah break </script>. */
function seoJsonLdScript(array $graph): string
{
    $data = ['@context' => 'https://schema.org', '@graph' => $graph];
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    return '<script type="application/ld+json">' . $json . '</script>';
}

/** URL profil sosial (sameAs) untuk Organization — satu URL http(s) per baris di setting brand_social. */
function seoBrandSameAs(): array
{
    $raw = (string) getSetting('brand_social', '');
    if (trim($raw) === '') return [];
    $out = [];
    foreach (preg_split('/[\r\n]+/', $raw) as $line) {
        $line = trim((string) $line);
        if ($line !== '' && preg_match('#^https?://#i', $line)) $out[$line] = true;
    }
    return array_values(array_keys($out));
}

/**
 * JSON-LD WebSite + Organization untuk HOMEPAGE. WebSite menyertakan SearchAction
 * (sitelinks searchbox → /search?q=). Organization memuat logo & sameAs bila ada.
 * Kembalikan string <script> (kosong hanya bila nama brand kosong).
 */
function seoHomeJsonLd(): string
{
    $brand = feBrand();
    $name  = trim((string) ($brand['name'] ?? ''));
    if ($name === '') return '';
    $home  = rtrim(url('/'), '/') . '/';

    $org = ['@type' => 'Organization', '@id' => $home . '#organization', 'name' => $name, 'url' => $home];
    $logoRel = trim((string) ($brand['logo'] ?? ''));
    if ($logoRel !== '') {
        $logoUrl = rtrim(UPLOAD_URL, '/') . '/' . ltrim($logoRel, '/');
        $org['logo']  = ['@type' => 'ImageObject', 'url' => $logoUrl];
        $org['image'] = $logoUrl;
    }
    $same = seoBrandSameAs();
    if ($same) $org['sameAs'] = $same;

    $site = [
        '@type'     => 'WebSite',
        '@id'       => $home . '#website',
        'name'      => $name,
        'url'       => $home,
        'publisher' => ['@id' => $home . '#organization'],
        'potentialAction' => [
            '@type'  => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => rtrim(url('/search'), '/') . '?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ];
    if (trim((string) ($brand['tagline'] ?? '')) !== '') $site['description'] = (string) $brand['tagline'];

    return seoJsonLdScript([$org, $site]);
}
