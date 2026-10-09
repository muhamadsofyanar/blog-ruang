<?php
// ════════════════════════════════════════════════════════════════════════
// Saran & sisip internal link — LOKAL & GRATIS (tanpa kredit/gateway).
// Prinsip: internal link terbaik = menautkan frasa yang MEMANG muncul di konten
// saat ini ke artikel published lain yang relevan. Jadi kita cari artikel lain
// yang focus_keyword/judulnya disebut di teks → sarankan sebagai anchor.
// Membantu memenuhi aturan SEO `internal_link_min` tanpa biaya AI.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/frontend.php'; // feArticleUrl()

/** Teks terlihat (lowercase, rapat) dari HTML untuk pencocokan frasa. */
function ilPlainText(string $html): string
{
    // Ganti tag dengan SPASI (bukan strip_tags) agar kata antar-blok tidak menempel.
    $t = (string) preg_replace('/<[^>]+>/', ' ', $html);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $t)));
}

/** Slug artikel yang SUDAH tertaut di konten (agar tidak disarankan ulang). */
function ilLinkedSlugs(string $html): array
{
    $slugs = [];
    if (preg_match_all('~/artikel/([^"\'\s/#?]+)~i', $html, $mm)) {
        foreach ($mm[1] as $s) $slugs[strtolower(rawurldecode($s))] = true;
    }
    return $slugs;
}

/** Frasa (lowercase) muncul di teks (lowercase) dengan batas kata (unicode). */
function ilPhraseInText(string $phraseLower, string $textLower): bool
{
    if (mb_strlen($phraseLower) < 4) return false;
    $q = preg_quote($phraseLower, '/');
    return (bool) preg_match('/(?<![\p{L}\p{N}])' . $q . '(?![\p{L}\p{N}])/u', $textLower);
}

/**
 * Saran internal link untuk sebuah artikel.
 * @param array $article ['id','content'(html),'focus_keyword','title','category_id']
 * @return array<int,array{article_id:int,title:string,slug:string,url:string,anchor:string,score:int}>
 */
function internalLinkSuggest(PDO $pdo, array $article, int $max = 5): array
{
    $html = (string) ($article['content'] ?? '');
    $text = ilPlainText($html);
    if ($text === '') return [];
    $selfId = (int) ($article['id'] ?? 0);
    $linked = ilLinkedSlugs($html);
    $catId  = (int) ($article['category_id'] ?? 0);

    // Kandidat: artikel published lain (dibatasi agar efisien).
    $rows = $pdo->query(
        "SELECT id, title, slug, focus_keyword, category_id
         FROM articles WHERE status = 'published' ORDER BY id DESC LIMIT 1000"
    )->fetchAll();

    $cands = [];
    foreach ($rows as $r) {
        if ((int) $r['id'] === $selfId) continue;
        if (isset($linked[strtolower((string) $r['slug'])])) continue;

        // Frasa target: focus_keyword (prioritas) lalu judul — yang muncul di teks.
        $bestAnchor = '';
        foreach ([trim((string) ($r['focus_keyword'] ?? '')), trim((string) $r['title'])] as $p) {
            if ($p === '') continue;
            if (ilPhraseInText(mb_strtolower($p), $text)) { $bestAnchor = $p; break; }
        }
        if ($bestAnchor === '') continue; // hanya sarankan bila ada anchor nyata

        $score = mb_strlen($bestAnchor); // frasa lebih panjang = lebih spesifik
        if ($catId > 0 && (int) $r['category_id'] === $catId) $score += 30; // kategori sama

        $cands[] = [
            'article_id' => (int) $r['id'],
            'title'      => (string) $r['title'],
            'slug'       => (string) $r['slug'],
            'url'        => feArticleUrl((string) $r['slug']),
            'anchor'     => $bestAnchor,
            'score'      => $score,
        ];
    }

    usort($cands, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

    // Dedup anchor (jangan tautkan frasa sama ke beberapa target).
    $seen = [];
    $out  = [];
    foreach ($cands as $c) {
        $k = mb_strtolower($c['anchor']);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $c;
        if (count($out) >= $max) break;
    }
    return $out;
}

/**
 * Sisipkan link ke konten pada KEMUNCULAN PERTAMA tiap anchor di text node yang
 * belum tertaut (lewati di dalam a/heading/code/pre). Satu link per target.
 * @param array $items daftar {anchor,url}
 * @return array{html:string,inserted:int,used:array<int,string>}
 */
function internalLinkInsert(string $html, array $items, int $max = 5): array
{
    $fail = ['html' => $html, 'inserted' => 0, 'used' => []];
    if (trim($html) === '' || !$items) return $fail;

    $doc = new DOMDocument('1.0', 'UTF-8');
    $loaded = $doc->loadHTML('<?xml encoding="UTF-8"><div id="__il__">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
    if (!$loaded) return $fail;
    $root = $doc->getElementById('__il__');
    if (!$root) return $fail;

    $xpath = new DOMXPath($doc);
    $skip  = ['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'code', 'pre'];

    $inserted = 0;
    $used     = [];
    $seen     = [];
    foreach ($items as $it) {
        if ($inserted >= $max) break;
        $anchor = trim((string) ($it['anchor'] ?? ''));
        $url    = trim((string) ($it['url'] ?? ''));
        if ($anchor === '' || $url === '') continue;
        $k = mb_strtolower($anchor);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;

        foreach ($xpath->query('.//text()', $root) as $node) {
            // Lewati bila berada di dalam elemen yang dikecualikan.
            $p = $node->parentNode;
            $bad = false;
            while ($p && $p !== $root) {
                if (in_array(strtolower($p->nodeName), $skip, true)) { $bad = true; break; }
                $p = $p->parentNode;
            }
            if ($bad) continue;

            $txt = (string) $node->nodeValue;
            if (!preg_match('/(?<![\p{L}\p{N}])(' . preg_quote($anchor, '/') . ')(?![\p{L}\p{N}])/ui', $txt, $mm, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $off   = $mm[1][1];
            $match = $mm[1][0];
            $before = substr($txt, 0, $off);
            $after  = substr($txt, $off + strlen($match));

            $a = $doc->createElement('a');
            $a->setAttribute('href', $url);
            $a->appendChild($doc->createTextNode($match));

            $frag = $doc->createDocumentFragment();
            if ($before !== '') $frag->appendChild($doc->createTextNode($before));
            $frag->appendChild($a);
            if ($after !== '') $frag->appendChild($doc->createTextNode($after));
            $node->parentNode->replaceChild($frag, $node);

            $inserted++;
            $used[] = $anchor;
            break; // satu link per target
        }
    }

    $out = '';
    foreach ($root->childNodes as $child) $out .= $doc->saveHTML($child);
    return ['html' => $out, 'inserted' => $inserted, 'used' => $used];
}
