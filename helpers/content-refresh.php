<?php
// ════════════════════════════════════════════════════════════════════════
// Antrean "Perlu Diperbarui" — gabungkan sinyal GSC (posisi halaman 2, CTR
// rendah, belum dapat klik, posisi TURUN vs snapshot lalu) + umur artikel +
// skor kesehatan menjadi daftar kerja BERPRIORITAS. LOKAL (tanpa kredit).
// Dipakai UI SEO Health + API agar agen (Hermes) mengoptimasi dari data nyata.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/content-health.php';
require_once __DIR__ . '/gsc.php';
require_once __DIR__ . '/frontend.php';

const REFRESH_STALE_DAYS = 180;

/** Label manusiawi untuk kode alasan. */
function contentRefreshReasonLabel(string $code): string
{
    return [
        'page2'   => 'Posisi 11–20 (halaman 2)',
        'dropped' => 'Posisi turun',
        'lowctr'  => 'CTR rendah',
        'zero'    => 'Belum dapat klik',
        'stale'   => 'Lama tidak diperbarui',
        'health'  => 'Skor SEO di bawah 80',
    ][$code] ?? $code;
}

/**
 * Bangun antrean refresh berprioritas.
 * @return array{items:array,total:int,has_gsc:bool,has_prev:bool}
 */
function contentRefreshQueue(PDO $pdo, int $limit = 50, int $offset = 0): array
{
    $ruleset = scribeGetRuleset(false);
    $scan    = contentHealthScan($pdo, ['status' => 'published'], 100000, 0, $ruleset);

    $hasGsc   = gscEnabled() && gscConfigured();
    $cur      = $hasGsc ? gscMetricsByArticlePath() : [];
    $prevRows = $hasGsc ? gscCachePrev() : [];
    $hasPrev  = $hasGsc && !empty($prevRows);
    $prev     = $hasPrev ? gscMetricsByArticlePath($prevRows) : [];

    $now   = time();
    $items = [];
    foreach ($scan['articles'] as $a) {
        $slug = (string) $a['slug'];
        $key  = '/artikel/' . $slug;
        $m    = $cur[$key]  ?? null;
        $pm   = $prev[$key] ?? null;

        $clicks  = $m ? (int) $m['clicks'] : 0;
        $impr    = $m ? (int) $m['impressions'] : 0;
        $ctr     = $m ? (float) $m['ctr'] : 0.0;
        $pos     = $m ? (float) $m['position'] : 0.0;
        $posPrev = $pm ? (float) $pm['position'] : null;
        $delta   = ($posPrev !== null && $pos > 0) ? round($pos - $posPrev, 1) : null; // + = memburuk

        $updated = strtotime((string) ($a['updated_at'] ?? $a['published_at'] ?? ''));
        $ageDays = $updated ? (int) floor(($now - $updated) / 86400) : 9999;
        $score   = (int) $a['score'];

        $reasons  = [];
        $priority = 0;
        if ($impr > 0 && $pos >= 11 && $pos <= 20)                 { $reasons[] = 'page2';   $priority += 40; }
        if ($delta !== null && $delta >= 2 && $impr > 0 && $pos <= 30) { $reasons[] = 'dropped'; $priority += 35; }
        if ($impr >= 50 && $ctr < 0.02)                            { $reasons[] = 'lowctr';  $priority += 25; }
        if ($impr >= 30 && $clicks === 0)                          { $reasons[] = 'zero';    $priority += 20; }
        if ($ageDays > REFRESH_STALE_DAYS)                         { $reasons[] = 'stale';   $priority += 15; }
        if ($score < 80)                                           { $reasons[] = 'health';  $priority += ($score < 60 ? 20 : 12); }

        if (!$reasons) continue; // hanya yang perlu perhatian

        $priority += min(20, (int) floor($impr / 50)); // bobot trafik

        $items[] = [
            'article_id' => (int) $a['id'],
            'title'      => (string) $a['title'],
            'slug'       => $slug,
            'edit_url'   => '/admin/articles/' . (int) $a['id'],
            'public_url' => '/artikel/' . $slug,
            'seo_score'  => $score,
            'age_days'   => $ageDays >= 9999 ? null : $ageDays,
            'gsc'        => $hasGsc ? [
                'clicks'         => $clicks,
                'impressions'    => $impr,
                'ctr'            => round($ctr, 4),
                'position'       => $pos > 0 ? round($pos, 1) : null,
                'position_prev'  => $posPrev !== null ? round($posPrev, 1) : null,
                'position_delta' => $delta,
            ] : null,
            'reasons'  => array_map(static fn(string $c): array => ['code' => $c, 'label' => contentRefreshReasonLabel($c)], $reasons),
            'priority' => $priority,
        ];
    }

    usort($items, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
    $total = count($items);
    return [
        'items'    => array_slice($items, max(0, $offset), max(1, $limit)),
        'total'    => $total,
        'has_gsc'  => $hasGsc,
        'has_prev' => $hasPrev,
    ];
}
