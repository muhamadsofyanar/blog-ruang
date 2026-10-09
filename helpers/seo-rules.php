<?php
// ════════════════════════════════════════════════════════════════════════
// SEO score lapis LOKAL (gratis, tanpa gateway/kredit). Ruleset = DATA murni
// (threshold/bobot/label) — TIDAK PERNAH kode. Eksekusi rule via WHITELIST fungsi
// PHP per rule-id; rule-id tak dikenal dilewati (forward-compat, butuh update ZIP
// untuk logika baru). Sumber ruleset berlapis: cache file → remote gateway →
// bundle ter-ship. §7 SEO Intelligence Layer (sisi client).
// ════════════════════════════════════════════════════════════════════════

if (!defined('RULESET_CACHE_TTL')) define('RULESET_CACHE_TTL', 7 * 86400); // 7 hari

/** Path cache ruleset lokal. */
function scribeRulesetCachePath(): string { return __DIR__ . '/../cache/ruleset.json'; }
/** Path bundle default (ter-ship di repo). */
function scribeRulesetBundlePath(): string { return __DIR__ . '/../config/ruleset-default.json'; }

/** Validasi struktur ruleset (DATA murni). version int, rules array id/label/weight. */
function scribeValidateRuleset($data): bool
{
    if (!is_array($data) || !isset($data['version']) || !is_numeric($data['version'])) return false;
    if (!isset($data['rules']) || !is_array($data['rules'])) return false;
    foreach ($data['rules'] as $r) {
        if (!is_array($r) || !isset($r['id'], $r['label'], $r['weight'])) return false;
        if (!is_string($r['id']) || !is_numeric($r['weight'])) return false;
    }
    return true;
}

/** Baca bundle default (fallback terakhir). */
function scribeRulesetFromBundle(): array
{
    $raw = @file_get_contents(scribeRulesetBundlePath());
    $data = $raw !== false ? json_decode($raw, true) : null;
    if (scribeValidateRuleset($data)) {
        $data['source'] = 'bundle';
        return $data;
    }
    // Bundle pun rusak (seharusnya tak terjadi) → ruleset kosong aman.
    return ['version' => 0, 'rules' => [], 'source' => 'bundle'];
}

/**
 * Ambil ruleset aktif. Urutan: cache segar → (stale/force) remote gateway →
 * bundle. Selalu mengembalikan ruleset valid (fallback tanpa fatal).
 *
 * @return array {version:int, rules:array, source:'cache'|'remote'|'bundle', fetched_at?:int}
 */
function scribeGetRuleset(bool $force = false): array
{
    $cachePath = scribeRulesetCachePath();
    $cached = null;
    if (is_file($cachePath)) {
        $raw = @file_get_contents($cachePath);
        $c = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($c) && isset($c['ruleset']) && scribeValidateRuleset($c['ruleset'])) {
            $cached = $c;
        }
    }

    $fresh = $cached && (time() - (int) ($cached['fetched_at'] ?? 0)) < RULESET_CACHE_TTL;
    if (!$force && $fresh) {
        $r = $cached['ruleset'];
        $r['source'] = 'cache';
        $r['fetched_at'] = (int) $cached['fetched_at'];
        return $r;
    }

    // Refresh dari gateway.
    try {
        require_once __DIR__ . '/AverionAiAdapter.php';
        $adapter = new AverionAiAdapter();
        $res = $adapter->getRuleset();
        if ($res['ok']) {
            $ruleset = $res['data']['ruleset'] ?? null;
            if (scribeValidateRuleset($ruleset)) {
                @file_put_contents($cachePath, json_encode([
                    'version'    => $ruleset['version'],
                    'fetched_at' => time(),
                    'ruleset'    => $ruleset,
                ]));
                $ruleset['source'] = 'remote';
                $ruleset['fetched_at'] = time();
                return $ruleset;
            }
        }
    } catch (Throwable $e) {
        error_log('scribeGetRuleset: ' . $e->getMessage());
    }

    // Gagal fetch → pakai cache lama (walau stale) bila ada, else bundle.
    if ($cached && scribeValidateRuleset($cached['ruleset'])) {
        $r = $cached['ruleset'];
        $r['source'] = 'cache';
        $r['fetched_at'] = (int) ($cached['fetched_at'] ?? 0);
        return $r;
    }
    return scribeRulesetFromBundle();
}

// ─── Analisis metrik konten (DOMDocument) ────────────────────────

function scribeAnalyzeContent(string $html): array
{
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $ok = trim($html) !== '' && $doc->loadHTML('<?xml encoding="UTF-8"><div id="__r__">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $m = ['text' => '', 'word_count' => 0, 'h1' => 0, 'h2' => 0, 'h3' => 0,
          'internal_links' => 0, 'external_links' => 0, 'img_total' => 0, 'img_no_alt' => 0, 'has_faq' => false];
    if (!$ok) return $m;

    $root = $doc->getElementById('__r__');
    if (!$root) return $m;

    $m['text'] = trim(preg_replace('/\s+/', ' ', (string) $root->textContent));
    $m['word_count'] = $m['text'] === '' ? 0 : count(preg_split('/\s+/', $m['text']));
    $m['h1'] = $root->getElementsByTagName('h1')->length;
    $m['h2'] = $root->getElementsByTagName('h2')->length;
    $m['h3'] = $root->getElementsByTagName('h3')->length;

    $selfHost = strtolower((string) (parse_url(APP_URL, PHP_URL_HOST) ?? ''));
    foreach ($root->getElementsByTagName('a') as $a) {
        $href = trim($a->getAttribute('href'));
        if ($href === '') continue;
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $h = strtolower((string) (parse_url($href, PHP_URL_HOST) ?? ''));
            ($h !== '' && $h === $selfHost) ? $m['internal_links']++ : $m['external_links']++;
        } elseif (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            $m['internal_links']++;
        }
    }
    foreach ($root->getElementsByTagName('img') as $img) {
        $m['img_total']++;
        if (trim($img->getAttribute('alt')) === '') $m['img_no_alt']++;
    }
    foreach ($root->getElementsByTagName('section') as $s) {
        if ($s->getAttribute('data-faq-block') === '1') { $m['has_faq'] = true; break; }
    }
    return $m;
}

// ─── WHITELIST rule-id → fungsi eksekusi ─────────────────────────
// Tiap fungsi: (metrics, ctx, threshold) → [bool pass, string hint].

function scribeRuleWhitelist(): array
{
    return [
        'keyword_in_first_100_words' => function ($m, $ctx, $t) {
            $kw = trim(mb_strtolower((string) ($ctx['focus_keyword'] ?? '')));
            if ($kw === '') return [false, 'Isi focus keyword.'];
            $first = implode(' ', array_slice(preg_split('/\s+/', mb_strtolower($m['text'])), 0, 100));
            return [str_contains($first, $kw), 'Sisipkan kata kunci di 100 kata pertama.'];
        },
        'single_h1' => function ($m, $ctx, $t) {
            $titleFilled = trim((string) ($ctx['title'] ?? '')) !== '';
            if (!$titleFilled) return [false, 'Judul artikel kosong.'];
            return [$m['h1'] === 0, 'Konten memuat H1 — hapus (judul sudah jadi H1).'];
        },
        'has_h2_h3' => function ($m, $ctx, $t) {
            $pass = $m['h2'] >= 1 && $m['h3'] >= 1;
            $hint = $m['h2'] < 1 ? 'Tambahkan subjudul H2.' : 'Tambahkan sub-bagian H3.';
            return [$pass, $hint];
        },
        'internal_link_min' => function ($m, $ctx, $t) {
            $min = (int) ($t ?? 1);
            return [$m['internal_links'] >= $min, 'Tambahkan minimal ' . $min . ' tautan internal.'];
        },
        'external_link_min' => function ($m, $ctx, $t) {
            $min = (int) ($t ?? 1);
            return [$m['external_links'] >= $min, 'Tambahkan minimal ' . $min . ' tautan eksternal.'];
        },
        'meta_title_length' => function ($m, $ctx, $t) {
            $len = mb_strlen(trim((string) ($ctx['meta_title'] ?? '')));
            $min = (int) ($t['min'] ?? 45); $max = (int) ($t['max'] ?? 60);
            return [$len >= $min && $len <= $max, 'Meta title ' . $len . ' char (ideal ' . $min . '–' . $max . ').'];
        },
        'meta_desc_length' => function ($m, $ctx, $t) {
            $len = mb_strlen(trim((string) ($ctx['meta_description'] ?? '')));
            $min = (int) ($t['min'] ?? 120); $max = (int) ($t['max'] ?? 160);
            return [$len >= $min && $len <= $max, 'Meta description ' . $len . ' char (ideal ' . $min . '–' . $max . ').'];
        },
        'images_have_alt' => function ($m, $ctx, $t) {
            return [$m['img_no_alt'] === 0, $m['img_no_alt'] . ' gambar tanpa alt text.'];
        },
        'word_count_min' => function ($m, $ctx, $t) {
            $min = (int) ($t ?? 800);
            return [$m['word_count'] >= $min, 'Baru ' . $m['word_count'] . ' kata (target ' . $min . ').'];
        },
        'faq_present' => function ($m, $ctx, $t) {
            // Format lama/inline: section[data-faq-block] di content.
            // Format terstruktur baru: sumber resmi JSON-LD di seo_ai_faq.
            $hasStructuredFaq = (int) ($ctx['faq_approved_count'] ?? 0) > 0;
            return [$m['has_faq'] || $hasStructuredFaq, 'Tambahkan dan setujui minimal satu FAQ.'];
        },
    ];
}

/**
 * Jalankan ruleset terhadap konteks artikel. Skor = bobot lolos / bobot total
 * (rule dikenal) x 100. Rule-id tak dikenal dilewati diam-diam.
 *
 * @return array {score:int, checks:array<{id,label,pass,hint,weight}>}
 */
function scribeRunRules(array $ruleset, array $ctx): array
{
    $wl = scribeRuleWhitelist();
    $m  = scribeAnalyzeContent((string) ($ctx['content'] ?? ''));
    $checks = []; $totalW = 0; $passW = 0;

    foreach ($ruleset['rules'] ?? [] as $rule) {
        $id = $rule['id'] ?? '';
        if (!isset($wl[$id])) continue; // rule-id tak dikenal → lewati (forward-compat)
        $w = (int) ($rule['weight'] ?? 0);
        [$pass, $hint] = $wl[$id]($m, $ctx, $rule['threshold'] ?? null);
        $totalW += $w;
        if ($pass) $passW += $w;
        $checks[] = ['id' => $id, 'label' => (string) ($rule['label'] ?? $id),
                     'pass' => (bool) $pass, 'hint' => $pass ? '' : (string) $hint, 'weight' => $w];
    }
    $score = $totalW > 0 ? (int) round($passW / $totalW * 100) : 0;
    return ['score' => $score, 'checks' => $checks, 'word_count' => $m['word_count']];
}
