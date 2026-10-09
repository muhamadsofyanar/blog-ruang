<?php
// ════════════════════════════════════════════════════════════════════════
// Auto alt-text gambar — LOKAL & GRATIS (tanpa kredit/gateway). Mengisi atribut
// alt yang KOSONG pada <img> dari sumber paling bermakna: figcaption/title
// terdekat → nama file yang dibersihkan → fallback focus keyword / judul artikel.
// Memenuhi aturan SEO `images_have_alt`. Alt yang sudah ada TIDAK ditimpa.
// (Catatan: deskripsi berbasis "melihat" gambar butuh model vision — di luar
//  lingkup gateway teks saat ini; ini pendekatan kontekstual standar.)
// ════════════════════════════════════════════════════════════════════════

/** Ubah nama file jadi frasa alt yang bermakna, atau '' bila tak berguna. */
function altFromFilename(string $src): string
{
    $path = parse_url($src, PHP_URL_PATH);
    $name = pathinfo((string) ($path !== false && $path !== null ? $path : $src), PATHINFO_FILENAME);
    $name = urldecode((string) $name);
    $name = (string) preg_replace('/[-_]+/', ' ', $name);
    $name = (string) preg_replace('/\b\d{3,}\b/', ' ', $name);        // id/timestamp panjang
    $name = (string) preg_replace('/\b[0-9a-f]{8,}\b/i', ' ', $name); // hash hex
    $name = trim((string) preg_replace('/\s+/', ' ', $name));
    if (mb_strlen($name) < 3) return '';
    if (preg_match('/^(img|image|photo|dsc|screenshot|untitled|gambar|foto|picture|wa|screen shot)$/i', $name)) return '';
    return mb_substr(ucfirst($name), 0, 125);
}

/**
 * Isi alt kosong pada semua <img>. $ctx: ['focus_keyword'=>.., 'title'=>..].
 * Fallback keyword/judul dibatasi agar tidak berulang (hindari keyword stuffing).
 * @return array{html:string, filled:int}
 */
function altTextFill(string $html, array $ctx = []): array
{
    if (stripos($html, '<img') === false) return ['html' => $html, 'filled' => 0];

    $doc = new DOMDocument('1.0', 'UTF-8');
    $ok = $doc->loadHTML('<?xml encoding="UTF-8"><div id="__alt__">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
    if (!$ok) return ['html' => $html, 'filled' => 0];
    $root = $doc->getElementById('__alt__');
    if (!$root) return ['html' => $html, 'filled' => 0];

    $kw    = trim((string) ($ctx['focus_keyword'] ?? ''));
    $title = trim((string) ($ctx['title'] ?? ''));
    $usedKw = false;
    $usedTitle = false;

    $filled = 0;
    foreach (iterator_to_array($root->getElementsByTagName('img')) as $img) {
        if (trim($img->getAttribute('alt')) !== '') continue; // jangan timpa alt yang ada

        $alt = '';
        // 1) figcaption bila <img> dalam <figure>
        $fig = $img->parentNode;
        while ($fig && $fig !== $root && strtolower($fig->nodeName) !== 'figure') $fig = $fig->parentNode;
        if ($fig && strtolower($fig->nodeName) === 'figure') {
            foreach ($fig->getElementsByTagName('figcaption') as $cap) { $alt = trim($cap->textContent); break; }
        }
        // 2) atribut title
        if ($alt === '') $alt = trim($img->getAttribute('title'));
        // 3) nama file
        if ($alt === '') $alt = altFromFilename($img->getAttribute('src'));
        // 4) fallback kontekstual — dibatasi agar tidak berulang
        if ($alt === '') {
            if ($kw !== '' && !$usedKw) { $alt = $kw; $usedKw = true; }
            elseif ($title !== '' && !$usedTitle) { $alt = $title; $usedTitle = true; }
        }
        if ($alt === '') continue; // benar-benar tak ada info → biarkan kosong

        $img->setAttribute('alt', mb_substr($alt, 0, 125));
        $filled++;
    }

    if ($filled === 0) return ['html' => $html, 'filled' => 0];

    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return ['html' => $out, 'filled' => $filled];
}
