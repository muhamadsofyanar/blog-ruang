<?php
// ════════════════════════════════════════════════════════════════════════
// Pengelola seksi draft di articles.content. Tiap seksi H2 dibungkus
// <section data-outline-idx="N">. Server = sumber kebenaran urutan & idempotensi
// (retry/regenerasi seksi N menimpa, TIDAK menduplikasi, dan tetap terurut sesuai
// indeks outline). Basis DOMDocument (bukan regex rapuh).
// ════════════════════════════════════════════════════════════════════════

/** Muat content ke DOMDocument dengan root pembungkus. Return [doc, root]. */
function _draftLoad(string $html): array
{
    $prev = libxml_use_internal_errors(true);
    $doc  = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__draftroot__">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return [$doc, $doc->getElementById('__draftroot__')];
}

/** Serialize isi root (tanpa div pembungkus). */
function _draftSerialize(DOMDocument $doc, DOMNode $root): string
{
    $out = '';
    foreach (iterator_to_array($root->childNodes) as $c) {
        $out .= $doc->saveHTML($c);
    }
    return trim($out);
}

/**
 * Sisipkan/timpa seksi indeks $idx dengan $innerHtml (SUDAH tersanitasi server).
 * Idempotent: seksi idx yang sama dihapus dulu. Terurut: disisipkan sebelum seksi
 * pertama ber-idx lebih besar (bila tak ada, di akhir) — urutan DOM selalu sesuai
 * indeks outline meski di-generate/retry acak.
 */
function draftUpsertSection(string $content, int $idx, string $innerHtml): string
{
    [$doc, $root] = _draftLoad($content);
    if (!$root) {
        return $content . '<section data-outline-idx="' . $idx . '">' . $innerHtml . '</section>';
    }

    // Hapus seksi idx yang sudah ada.
    foreach (iterator_to_array($root->getElementsByTagName('section')) as $sec) {
        if ($sec->getAttribute('data-outline-idx') === (string) $idx) {
            $sec->parentNode->removeChild($sec);
        }
    }

    // Bangun seksi baru + impor node dari innerHtml.
    $newSec = $doc->createElement('section');
    $newSec->setAttribute('data-outline-idx', (string) $idx);
    [$fdoc, $froot] = _draftLoad($innerHtml);
    if ($froot) {
        foreach (iterator_to_array($froot->childNodes) as $n) {
            $newSec->appendChild($doc->importNode($n, true));
        }
    }

    // Cari posisi terurut.
    $insertBefore = null;
    foreach (iterator_to_array($root->childNodes) as $node) {
        if ($node instanceof DOMElement && strtolower($node->nodeName) === 'section'
            && $node->hasAttribute('data-outline-idx')
            && (int) $node->getAttribute('data-outline-idx') > $idx) {
            $insertBefore = $node;
            break;
        }
    }
    $insertBefore ? $root->insertBefore($newSec, $insertBefore) : $root->appendChild($newSec);

    return _draftSerialize($doc, $root);
}

/**
 * Sisipkan/refresh blok FAQ di AKHIR konten dalam <section data-faq-block="1">.
 * Idempotent (blok lama dihapus dulu). $innerHtml SUDAH tersanitasi. $innerHtml
 * kosong → blok FAQ dihapus total (semua un-approve).
 */
function faqUpsertBlock(string $content, string $innerHtml): string
{
    [$doc, $root] = _draftLoad($content);
    if (!$root) {
        return $innerHtml === '' ? $content
            : $content . '<section data-faq-block="1">' . $innerHtml . '</section>';
    }
    // Hapus blok FAQ lama.
    foreach (iterator_to_array($root->getElementsByTagName('section')) as $sec) {
        if ($sec->getAttribute('data-faq-block') === '1') {
            $sec->parentNode->removeChild($sec);
        }
    }
    if (trim($innerHtml) !== '') {
        $newSec = $doc->createElement('section');
        $newSec->setAttribute('data-faq-block', '1');
        [$fdoc, $froot] = _draftLoad($innerHtml);
        if ($froot) {
            foreach (iterator_to_array($froot->childNodes) as $n) {
                $newSec->appendChild($doc->importNode($n, true));
            }
        }
        $root->appendChild($newSec); // selalu di akhir konten
    }
    return _draftSerialize($doc, $root);
}

/**
 * Apakah ada konten manual (teks di LUAR seksi draft/FAQ)? Dipakai memutuskan
 * status draft_ai: bila seluruh konten hanya berada di dalam wrapper sistem
 * (<section data-outline-idx> atau <section data-faq-block>), berarti konten
 * awal kosong (tidak ada prosa manual).
 */
function draftHasManualContent(string $content): bool
{
    if (trim($content) === '') return false;
    [$doc, $root] = _draftLoad($content);
    if (!$root) return trim(strip_tags($content)) !== '';
    foreach (iterator_to_array($root->getElementsByTagName('section')) as $sec) {
        if ($sec->hasAttribute('data-outline-idx') || $sec->getAttribute('data-faq-block') === '1') {
            $sec->parentNode->removeChild($sec);
        }
    }
    return trim((string) $root->textContent) !== '';
}
