<?php
// ════════════════════════════════════════════════════════════════════════
// Lazy scheduled publish (Track B / B-04). TANPA cron: pada request publik,
// maks 1x / 60 detik (gate flag file cache/last-schedule-check), promosikan
// artikel scheduled yang published_at<=NOW menjadi published. Bila ada yang
// berubah → regenerate sitemap + flush page cache.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/sitemap.php';
require_once __DIR__ . '/page-cache.php';

/** Jalankan lazy publish (gated 60 dtk). Return jumlah artikel yang di-publish. */
function scribeLazyPublish(): int
{
    $flag = dirname(__DIR__) . '/cache/last-schedule-check';
    $now  = time();
    // Gate: bila cek terakhir < 60 dtk lalu, lewati (hemat query tiap request).
    if (is_file($flag) && ($now - filemtime($flag)) < 60) return 0;
    // Set flag DULU (anti double-run request beruntun/konkuren).
    if (!is_dir(dirname($flag))) @mkdir(dirname($flag), 0775, true);
    @touch($flag);

    try {
        $pdo = getDB();
        // Tangkap slug yang akan live DULU (untuk ping IndexNow setelah update).
        $due = $pdo->query("SELECT slug FROM articles
                            WHERE status='scheduled' AND published_at <= NOW()")->fetchAll(PDO::FETCH_COLUMN);
        $st  = $pdo->query("UPDATE articles SET status='published'
                            WHERE status='scheduled' AND published_at <= NOW()");
        $n = $st ? $st->rowCount() : 0;
    } catch (Throwable $e) {
        error_log('lazyPublish: ' . $e->getMessage());
        return 0;
    }

    if ($n > 0) {
        scribeGenerateSitemap();
        pageCacheFlushAll(); // artikel baru live → home/kategori/sitemap segar
        // IndexNow: beri tahu mesin pencari artikel terjadwal yang kini live (non-fatal).
        require_once __DIR__ . '/indexnow.php';
        foreach ($due as $slug) indexnowPingArticle((string) $slug);
    }
    return $n;
}
