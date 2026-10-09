<?php
// ════════════════════════════════════════════════════════════════════════
// Render konten artikel publik (Track B / B-03). Pertahanan kedua: konten
// SELALU dilewatkan sanitizeArticleHtml SAAT RENDER (meski DB sudah bersih),
// lalu inject anchor id ke H2/H3 via DOMDocument (BUKAN regex), kumpulkan TOC,
// dan hitung word count untuk estimasi baca. Heading di dalam blok FAQ
// (section[data-faq-block]) DIKECUALIKAN dari TOC.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/media.php';

/**
 * @return array{html:string, toc:array<array{level:int,id:string,text:string}>, words:int, minutes:int}
 */
function scribeRenderArticle(?string $rawHtml): array
{
    $clean = sanitizeArticleHtml($rawHtml);
    $words = str_word_count(trim(preg_replace('/\s+/', ' ', strip_tags($clean))), 0, '0123456789');
    $minutes = max(1, (int) ceil($words / 200)); // ~200 kata/menit

    if ($clean === '') {
        return ['html' => '', 'toc' => [], 'words' => 0, 'minutes' => 1];
    }

    $prev = libxml_use_internal_errors(true);
    $doc  = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__scribe_art__">' . $clean . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementById('__scribe_art__');
    if (!$root) {
        return ['html' => $clean, 'toc' => [], 'words' => $words, 'minutes' => $minutes];
    }

    $toc  = [];
    $seen = [];

    // Gambar isi artikel dimuat malas dan diberi dimensi/srcset bila berasal
    // dari uploads lokal. Ini mencegah CLS serta menghemat transfer di mobile.
    foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
        if (!($img instanceof DOMElement)) continue;
        $img->setAttribute('loading', 'lazy');
        $img->setAttribute('decoding', 'async');
        $rel = _scribeLocalUploadRel($img->getAttribute('src'));
        if ($rel === '') continue;
        $meta = scribeImageMeta($rel);
        if ($meta) {
            $img->setAttribute('width', (string) $meta['width']);
            $img->setAttribute('height', (string) $meta['height']);
        }
        $dot = strrpos($rel, '.');
        if ($dot === false) continue;
        $stem = substr($rel, 0, $dot);
        $ext = substr($rel, $dot);
        $parts = [];
        foreach ([480, 960] as $w) {
            $variant = $stem . '-' . $w . $ext;
            $vm = scribeImageMeta($variant);
            if ($vm) $parts[$vm['width']] = rtrim(UPLOAD_URL, '/') . '/' . $variant . ' ' . $vm['width'] . 'w';
        }
        if ($meta) $parts[$meta['width']] = rtrim(UPLOAD_URL, '/') . '/' . $rel . ' ' . $meta['width'] . 'w';
        if (count($parts) > 1) {
            ksort($parts);
            $img->setAttribute('srcset', implode(', ', $parts));
            $img->setAttribute('sizes', '(min-width:768px) 720px, 100vw');
        }
    }
    foreach (['h2', 'h3'] as $tag) {
        foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $h) {
            // Lewati heading di dalam blok FAQ (section[data-faq-block]).
            if (_scribeInsideFaqBlock($h)) continue;
            $text = trim($h->textContent);
            if ($text === '') continue;
            $base = slugify($text);
            $id   = $base;
            $i = 2;
            while (isset($seen[$id])) { $id = $base . '-' . $i; $i++; }
            $seen[$id] = true;
            $h->setAttribute('id', $id);
        }
    }
    // Kumpulkan TOC dalam URUTAN DOM (h2 & h3 tercampur sesuai posisi).
    $xp = new DOMXPath($doc);
    foreach ($xp->query('//h2[@id] | //h3[@id]') as $h) {
        if (_scribeInsideFaqBlock($h)) continue;
        $toc[] = [
            'level' => (int) substr($h->nodeName, 1),
            'id'    => $h->getAttribute('id'),
            'text'  => trim($h->textContent),
        ];
    }

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }

    return ['html' => trim($out), 'toc' => $toc, 'words' => $words, 'minutes' => $minutes];
}

/** Ambil path relatif uploads dari URL lokal; URL eksternal dikembalikan kosong. */
function _scribeLocalUploadRel(string $src): string
{
    $src = trim($src);
    if ($src === '') return '';
    $base = rtrim(UPLOAD_URL, '/');
    if (str_starts_with($src, $base . '/')) return ltrim(substr($src, strlen($base)), '/');
    $srcPath = (string) parse_url($src, PHP_URL_PATH);
    $basePath = rtrim((string) parse_url($base, PHP_URL_PATH), '/');
    if ($basePath !== '' && str_starts_with($srcPath, $basePath . '/')) {
        return ltrim(substr($srcPath, strlen($basePath)), '/');
    }
    return '';
}

/** True bila node berada di dalam section[data-faq-block]. */
function _scribeInsideFaqBlock(DOMNode $n): bool
{
    for ($p = $n->parentNode; $p !== null; $p = $p->parentNode) {
        if ($p instanceof DOMElement && strtolower($p->nodeName) === 'section'
            && $p->getAttribute('data-faq-block') === '1') {
            return true;
        }
    }
    return false;
}
