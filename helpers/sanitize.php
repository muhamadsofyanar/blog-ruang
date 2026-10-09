<?php
// ════════════════════════════════════════════════════════════════════════
// Sanitasi HTML konten artikel (anti-XSS). Whitelist tag + atribut sesuai
// KONSEP ARSITEKTUR section 6.4. Dipakai GANDA: saat SIMPAN (actions) dan saat
// RENDER publik (Track B). Basis DOMDocument — parsing HTML sungguhan, bukan regex.
// Produk pertama Averion dengan frontend publik yang merender HTML user.
// ════════════════════════════════════════════════════════════════════════

/**
 * Bersihkan HTML konten artikel ke whitelist aman. Tag di luar whitelist:
 *  - script/style/iframe/object/embed/form → dibuang total (beserta isinya)
 *  - lainnya (span/div/font/dst) → di-unwrap (teks & anak dipertahankan)
 * Atribut: hanya href, src, alt, title. href/src wajib http(s)/relatif uploads.
 */
function sanitizeArticleHtml(?string $html): string
{
    $html = (string) $html;
    if (trim($html) === '') return '';

    $allowed = [
        'p', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a', 'img',
        'strong', 'em', 'br', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'code', 'pre',
        // section = wrapper struktural (blok FAQ / seksi draft) — dipertahankan
        // agar hook data-faq-block / data-outline-idx lolos sanitasi (render B-03).
        'section',
    ];
    // Tag berbahaya → buang node + isinya.
    $strip = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'noscript', 'svg', 'link', 'meta'];
    // Atribut aman + hook struktural non-eksekusi (data-*) untuk blok FAQ/seksi.
    $allowedAttr = ['href', 'src', 'alt', 'title', 'data-faq-block', 'data-outline-idx'];

    $prev = libxml_use_internal_errors(true);
    $doc  = new DOMDocument('1.0', 'UTF-8');
    // Bungkus supaya encoding UTF-8 dihormati + punya root tunggal.
    $wrapped = '<?xml encoding="UTF-8"><div id="__scribe_root__">' . $html . '</div>';
    $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementById('__scribe_root__');
    if (!$root) {
        // Fallback: kalau parsing gagal total, kembalikan teks polos ter-escape.
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }

    // 1) Buang tag berbahaya beserta isinya (iterasi snapshot).
    foreach ($strip as $tag) {
        $nodes = iterator_to_array($root->getElementsByTagName($tag));
        foreach ($nodes as $n) {
            if ($n->parentNode) $n->parentNode->removeChild($n);
        }
    }

    // 2) Bersihkan elemen: unwrap non-whitelist, saring atribut.
    _scribeCleanChildren($root, $allowed, $allowedAttr);

    // 3) Serialisasi isi root (tanpa div pembungkus).
    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

/** Rekursif: proses anak sebuah node — unwrap tag terlarang, saring atribut. */
function _scribeCleanChildren(DOMNode $parent, array $allowed, array $allowedAttr): void
{
    foreach (iterator_to_array($parent->childNodes) as $node) {
        if (!($node instanceof DOMElement)) {
            continue; // teks / komentar → biarkan (komentar akan diabaikan render)
        }
        $tag = strtolower($node->nodeName);

        // Proses anak dulu (depth-first) supaya unwrap berlapis benar.
        _scribeCleanChildren($node, $allowed, $allowedAttr);

        if (!in_array($tag, $allowed, true)) {
            // Unwrap: pindahkan semua anak ke posisi node lalu hapus node.
            while ($node->firstChild) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);
            continue;
        }

        // Saring atribut.
        if ($node->hasAttributes()) {
            foreach (iterator_to_array($node->attributes) as $attr) {
                $an = strtolower($attr->nodeName);
                if (!in_array($an, $allowedAttr, true)) {
                    $node->removeAttribute($attr->nodeName);
                    continue;
                }
                $av = trim($attr->nodeValue);
                if ($an === 'href' && !_scribeSafeUrl($av, true)) {
                    $node->removeAttribute('href');
                } elseif ($an === 'src' && !_scribeSafeUrl($av, false)) {
                    // src tidak aman → buang seluruh elemen (mis. img jahat).
                    $node->parentNode->removeChild($node);
                    break;
                }
            }
        }

        // Aturan khusus per tag.
        if ($tag === 'a' && $node->getAttribute('href') !== '') {
            $node->setAttribute('rel', 'noopener');
        }
        if ($tag === 'img') {
            if ($node->getAttribute('src') === '') {
                $node->parentNode->removeChild($node);
                continue;
            }
            if (!$node->hasAttribute('alt')) {
                $node->setAttribute('alt', ''); // alt wajib ada (boleh kosong)
            }
        }
    }
}

/**
 * URL aman? $allowRelative untuk href (boleh diawali '/'). Skema javascript:,
 * data:, vbscript: ditolak. img/src hanya http(s) atau path relatif /uploads.
 */
function _scribeSafeUrl(string $url, bool $isHref): bool
{
    if ($url === '') return false;
    $u = trim($url);
    // Tolak skema berbahaya (case-insensitive, abaikan whitespace/entity).
    $lower = strtolower(preg_replace('/\s+/', '', $u));
    foreach (['javascript:', 'data:', 'vbscript:'] as $bad) {
        if (str_starts_with($lower, $bad)) return false;
    }
    if (str_starts_with($u, 'http://') || str_starts_with($u, 'https://')) return true;
    if ($isHref) {
        if (str_starts_with($u, '/')) return true;      // relatif internal
        if (str_starts_with($lower, 'mailto:')) return true;
        if (str_starts_with($lower, '#')) return true;  // anchor TOC
        return false;
    }
    // src gambar: hanya path relatif diawali '/' (uploads).
    return str_starts_with($u, '/');
}
