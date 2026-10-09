<?php
// ════════════════════════════════════════════════════════════════════════
// Helper tampilan saldo AI. Satuan customer-facing = "artikel"; backend gateway
// menghitung kredit granular (SEO_CREDITS_PER_ARTICLE = 100). Konversi tampilan
// dilakukan di client (kontrak §2.1). Widget dipakai di halaman Kredit + dashboard.
// ════════════════════════════════════════════════════════════════════════

if (!defined('SCRIBE_CREDITS_PER_ARTICLE')) {
    define('SCRIBE_CREDITS_PER_ARTICLE', 100); // WAJIB sinkron dgn gateway SEO_CREDITS_PER_ARTICLE
}

// Estimasi biaya kredit per task — MIRROR SEO_TASK_COSTS di averion-ai/config.php.
// Dipakai untuk menampilkan "±N kredit" SEBELUM klik. Angka final tetap ditentukan
// gateway (sumber kebenaran); ini hanya estimasi UI. Jika gateway berubah, samakan.
if (!defined('SCRIBE_TASK_COSTS')) {
    define('SCRIBE_TASK_COSTS', [
        'keyword_research' => 7,
        'outline'          => 9,
        'draft_section'    => 11,
        'meta'             => 3,
        'faq'              => 5,
        'analysis'         => 10,
    ]);
}

/** Estimasi biaya kredit granular sebuah task (0 bila tak dikenal). */
function aiTaskCost(string $task): int
{
    return (int) (SCRIBE_TASK_COSTS[$task] ?? 0);
}

/** Kredit granular → jumlah artikel (string rapi: bulat atau 1 desimal). */
function creditsToArticles(int $credits): string
{
    $a = $credits / SCRIBE_CREDITS_PER_ARTICLE;
    return (floor($a) == $a) ? (string) (int) $a : number_format($a, 1, ',', '.');
}

/** Label task Indonesia untuk riwayat pemakaian. */
function aiTaskLabel(string $task): string
{
    return [
        'keyword_research' => 'Riset Keyword',
        'outline'          => 'Outline',
        'draft_section'    => 'Draft Seksi',
        'meta'             => 'Meta Tag',
        'faq'              => 'FAQ',
        'analysis'         => 'Analisis SEO',
        'ping'             => 'Tes Koneksi',
    ][$task] ?? ucfirst(str_replace('_', ' ', $task));
}

/**
 * Rincian komposisi "1 artikel = apa saja" — SATU SUMBER, dari konstanta
 * (SCRIBE_TASK_COSTS + SCRIBE_CREDITS_PER_ARTICLE). TIDAK ada angka hardcode:
 * jumlah seksi draft dihitung agar workflow lengkap pas ke 1 artikel, sehingga
 * bila tarif berubah infobox ikut benar. Dipakai halaman Kredit + editor.
 *
 * @return array{rows:array<array{task:string,label:string,qty:int,unit:int,subtotal:int}>, total:int, per_article:int}
 */
function scribeCreditBreakdown(): array
{
    $per = SCRIBE_CREDITS_PER_ARTICLE;
    // Task sekali-jalan dalam workflow artikel penuh.
    $singles = ['keyword_research', 'outline', 'meta', 'faq', 'analysis'];
    $singlesCost = 0;
    foreach ($singles as $t) $singlesCost += aiTaskCost($t);

    // Sisa kredit dialokasikan ke draft per-seksi → berapa seksi tipikal.
    $draftUnit     = aiTaskCost('draft_section');
    $draftSections = $draftUnit > 0 ? max(0, intdiv($per - $singlesCost, $draftUnit)) : 0;

    // Urutan sesuai alur kerja: riset → outline → draft → meta → faq → analisis.
    $plan = [
        ['keyword_research', 1],
        ['outline',          1],
        ['draft_section',    $draftSections],
        ['meta',             1],
        ['faq',              1],
        ['analysis',         1],
    ];

    $rows  = [];
    $total = 0;
    foreach ($plan as [$task, $qty]) {
        $unit = aiTaskCost($task);
        $sub  = $unit * $qty;
        $total += $sub;
        $rows[] = ['task' => $task, 'label' => aiTaskLabel($task), 'qty' => (int) $qty, 'unit' => $unit, 'subtotal' => $sub];
    }

    return ['rows' => $rows, 'total' => $total, 'per_article' => $per];
}

/**
 * Render infobox rincian kredit (satu sumber render). $compact=true → versi
 * ringkas untuk popover editor; false → tabel penuh untuk halaman Kredit.
 */
function scribeCreditBreakdownHtml(bool $compact = false): string
{
    $bd  = scribeCreditBreakdown();
    $per = $bd['per_article'];

    $rowsHtml = '';
    foreach ($bd['rows'] as $r) {
        $name = e($r['label']) . ($r['qty'] > 1 ? ' <span class="text-gray-400">× ' . (int) $r['qty'] . '</span>' : '');
        $calc = $r['qty'] > 1 ? (int) $r['unit'] . ' × ' . (int) $r['qty'] : (string) (int) $r['unit'];
        $rowsHtml .= '<tr class="border-t border-gray-100 dark:border-gray-800">'
            . '<td class="py-1.5 pr-2">' . $name . '</td>'
            . '<td class="py-1.5 px-2 text-right text-gray-400 whitespace-nowrap">' . e($calc) . '</td>'
            . '<td class="py-1.5 pl-2 text-right font-medium tabular-nums">' . (int) $r['subtotal'] . '</td></tr>';
    }

    $table = '<table class="w-full text-sm">'
        . '<thead><tr class="text-xs text-gray-500 dark:text-gray-400">'
        . '<th class="text-left font-medium pb-1">Langkah</th>'
        . '<th class="text-right font-medium pb-1">Kredit</th>'
        . '<th class="text-right font-medium pb-1">Subtotal</th></tr></thead>'
        . '<tbody>' . $rowsHtml . '</tbody>'
        . '<tfoot><tr class="border-t-2 border-gray-200 dark:border-gray-700 font-semibold">'
        . '<td class="pt-1.5">1 artikel</td><td></td>'
        . '<td class="pt-1.5 text-right tabular-nums">' . (int) $bd['total'] . '</td></tr></tfoot>'
        . '</table>';

    if ($compact) {
        return '<div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Estimasi workflow artikel penuh (±' . (int) $bd['rows'][2]['qty'] . ' seksi) = <strong>' . (int) $per . ' kredit</strong> = 1 artikel.</div>'
            . $table
            . '<p class="text-xs text-gray-400 mt-2">Cek SEO real-time &amp; editing GRATIS. Generate ulang memotong kredit lagi; kegagalan sistem otomatis di-refund.</p>';
    }

    return $table;
}

/**
 * HTML widget saldo. $b = data getBalance (atau null bila gagal). $variant:
 * 'full' (halaman Kredit) atau 'mini' (dashboard).
 */
function balanceWidgetHtml(?array $b, string $variant = 'full'): string
{
    if (!is_array($b)) {
        return '<div class="text-sm text-gray-500 dark:text-gray-400">Saldo tidak tersedia. '
             . '<button onclick="refreshBalance()" class="underline" style="color:var(--accent)">Coba muat ulang</button>.</div>';
    }

    $quota      = (int) ($b['monthly_quota'] ?? 0);
    $remaining  = (int) ($b['monthly_remaining'] ?? 0);
    $topup      = (int) ($b['topup_active_total'] ?? 0);
    $byok       = !empty($b['byok_active']);
    $nearestExp = $b['nearest_topup_expiry'] ?? null;
    $plan       = $b['plan'] ?? '-';

    $quotaArt  = creditsToArticles($quota);
    $remArt    = creditsToArticles($remaining);
    $topupArt  = creditsToArticles($topup);
    $pct       = $quota > 0 ? max(0, min(100, round($remaining / $quota * 100))) : 0;

    if ($variant === 'mini') {
        $h  = '<div class="space-y-2">';
        if ($byok) {
            $h .= '<div class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">'
                . icon('check-circle', 'w-4 h-4') . 'BYOK aktif — tanpa potong kredit</div>';
        }
        if ($quota > 0) {
            $h .= '<div><div class="flex justify-between text-xs mb-1"><span class="text-gray-500 dark:text-gray-400">Kuota bulan ini</span>'
                . '<span class="font-semibold">' . e($remArt) . '/' . e($quotaArt) . ' artikel</span></div>'
                . '<div class="h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden"><div class="h-full rounded-full" style="width:' . $pct . '%;background:var(--accent)"></div></div></div>';
        }
        $h .= '<div class="flex justify-between text-xs"><span class="text-gray-500 dark:text-gray-400">Saldo top-up</span><span class="font-semibold">' . e($topupArt) . ' artikel</span></div>';
        $h .= '</div>';
        return $h;
    }

    // Full
    $h  = '<div class="grid sm:grid-cols-3 gap-4">';
    // Kuota bulanan
    $h .= '<div class="rounded-md border border-gray-200 dark:border-gray-800 p-4">';
    $h .= '<div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Kuota Bulan Ini</div>';
    if ($quota > 0) {
        $h .= '<div class="text-2xl font-semibold font-display">' . e($remArt) . ' <span class="text-sm font-normal text-gray-400">/ ' . e($quotaArt) . ' artikel</span></div>';
        $h .= '<div class="mt-2 h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden"><div class="h-full rounded-full" style="width:' . $pct . '%;background:var(--accent)"></div></div>';
    } else {
        $h .= '<div class="text-2xl font-semibold font-display">—</div><div class="text-xs text-gray-400 mt-1">Plan ' . e($plan) . ' tanpa kuota bulanan</div>';
    }
    $h .= '</div>';
    // Top-up
    $h .= '<div class="rounded-md border border-gray-200 dark:border-gray-800 p-4">';
    $h .= '<div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Saldo Top-up</div>';
    $h .= '<div class="text-2xl font-semibold font-display">' . e($topupArt) . ' <span class="text-sm font-normal text-gray-400">artikel</span></div>';
    if ($nearestExp) {
        $h .= '<div class="text-xs text-gray-400 mt-1">Kedaluwarsa terdekat: ' . e(formatTanggal($nearestExp . ' 00:00:00', false)) . '</div>';
    }
    $h .= '</div>';
    // BYOK
    $h .= '<div class="rounded-md border border-gray-200 dark:border-gray-800 p-4">';
    $h .= '<div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Status BYOK</div>';
    if ($byok) {
        $h .= '<div class="text-lg font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">' . icon('check-circle', 'w-5 h-5') . 'Aktif</div>';
        if (!empty($b['byok_hint'])) $h .= '<div class="text-xs text-gray-400 mt-1 font-mono">' . e($b['byok_hint']) . '</div>';
        $h .= '<div class="text-xs text-gray-400 mt-1">Kredit tidak dipotong</div>';
    } else {
        $h .= '<div class="text-lg font-semibold text-gray-400">Nonaktif</div><div class="text-xs text-gray-400 mt-1">Pakai kredit kuota/top-up</div>';
    }
    $h .= '</div></div>';
    return $h;
}
