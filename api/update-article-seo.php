<?php
// ════════════════════════════════════════════════════════════════════════
// Management API — /api/update-article-seo.php  (TULIS, butuh ingest_manage)
// Update PARSIAL metadata SEO artikel (v1: TANPA ubah isi/content). Hanya field
// yang dikirim yang diubah. TIDAK mengubah status/slug/published_at/content.
//   Body: { "article_id":3, "meta_title":"...", "meta_description":"...",
//           "excerpt":"...", "focus_keyword":"...", "related_keywords":"a, b",
//           "tags":["x","y"], "title":"(opsional)", "dry_run":true }
// Keamanan: semua field teks = PLAIN TEXT (strip_tags + buang kontrol) → <script>,
// iframe, event handler ternetralkan. (Update content kelak WAJIB lewat
// sanitizeArticleHtml().) Idempoten per article_id.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/page-cache.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Gunakan POST.']);
}
$ctype = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($ctype, 'application/json') === false) {
    ingestApiSend(415, ['ok' => false, 'error' => 'Content-Type harus application/json.']);
}
ingestApiAuthorize(true);

$pdo = getDB();
$in  = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    ingestApiSend(400, ['ok' => false, 'error' => 'Body JSON tidak valid.']);
}
$dryRun = !empty($in['dry_run']);

$aid = 0;
if (is_int($in['article_id'] ?? null)) $aid = (int) $in['article_id'];
elseif (is_string($in['article_id'] ?? null) && ctype_digit($in['article_id'])) $aid = (int) $in['article_id'];
if ($aid <= 0) {
    ingestApiSend(422, ['ok' => false, 'error' => 'article_id wajib integer > 0.']);
}

$st = $pdo->prepare(
    "SELECT id, title, slug, meta_title, meta_description, excerpt, focus_keyword, related_keywords, status
     FROM articles WHERE id = ? LIMIT 1"
);
$st->execute([$aid]);
$art = $st->fetch();
if (!$art) {
    ingestApiSend(404, ['ok' => false, 'error' => 'Artikel tidak ditemukan.']);
}

/** Plain text: buang tag HTML + karakter kontrol. */
$clean = static fn($v): string => trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', strip_tags((string) $v)));

$set = [];      // kolom => nilai baru (untuk UPDATE)
$changed = [];  // daftar field berubah
$before = [];
$after  = [];

// Helper: catat perubahan bila beda dari nilai lama.
$apply = static function (string $col, $newVal, $oldVal) use (&$set, &$changed, &$before, &$after) {
    if ((string) $newVal !== (string) ($oldVal ?? '')) {
        $set[$col] = $newVal;
        $changed[] = $col;
        $before[$col] = $oldVal;
        $after[$col] = $newVal;
    }
};

// ─── Validasi + siapkan field yang dikirim ───────────────────────
if (array_key_exists('meta_title', $in)) {
    $v = $clean($in['meta_title']);
    $len = mb_strlen($v);
    if ($len < 45 || $len > 60) {
        ingestApiSend(422, ['ok' => false, 'error' => "meta_title harus 45-60 karakter (kirim $len)."]);
    }
    $apply('meta_title', $v, $art['meta_title']);
}
if (array_key_exists('meta_description', $in)) {
    $v = $clean($in['meta_description']);
    $len = mb_strlen($v);
    if ($len < 120 || $len > 160) {
        ingestApiSend(422, ['ok' => false, 'error' => "meta_description harus 120-160 karakter (kirim $len)."]);
    }
    $apply('meta_description', $v, $art['meta_description']);
}
if (array_key_exists('focus_keyword', $in)) {
    $v = $clean($in['focus_keyword']);
    if ($v === '') {
        ingestApiSend(422, ['ok' => false, 'error' => 'focus_keyword tidak boleh kosong bila dikirim.']);
    }
    $apply('focus_keyword', mb_substr($v, 0, 190), $art['focus_keyword']);
}
if (array_key_exists('excerpt', $in)) {
    $apply('excerpt', mb_substr($clean($in['excerpt']), 0, 500), $art['excerpt']);
}
if (array_key_exists('related_keywords', $in)) {
    $rk = $in['related_keywords'];
    if (is_array($rk)) $rk = implode(', ', array_map('strval', $rk));
    $apply('related_keywords', mb_substr($clean($rk), 0, 1000), $art['related_keywords']);
}
if (array_key_exists('title', $in)) {
    $v = mb_substr($clean($in['title']), 0, 255);
    if ($v === '') {
        ingestApiSend(422, ['ok' => false, 'error' => 'title tidak boleh kosong bila dikirim.']);
    }
    // Judul boleh berubah TAPI slug TIDAK diregenerasi (dipertahankan).
    $apply('title', $v, $art['title']);
}

// ─── Tags (set ulang relasi): hapus lama → sync baru ─────────────
$tagsProvided = array_key_exists('tags', $in) && is_array($in['tags']);
$beforeTags = [];
$afterTags  = [];
if ($tagsProvided) {
    $bt = $pdo->prepare("SELECT t.name FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ? ORDER BY t.name");
    $bt->execute([$aid]);
    $beforeTags = $bt->fetchAll(PDO::FETCH_COLUMN);
    $afterTags = array_values(array_unique(array_filter(array_map(fn($t) => $clean($t), $in['tags']), fn($t) => $t !== '')));
    // Bandingkan sebagai himpunan (case-insensitive) untuk idempotensi.
    $bn = array_map('mb_strtolower', $beforeTags); sort($bn);
    $an = array_map('mb_strtolower', $afterTags);  sort($an);
    if ($bn !== $an) {
        $changed[] = 'tags';
        $before['tags'] = $beforeTags;
        $after['tags']  = $afterTags;
    } else {
        $tagsProvided = false; // tak ada perubahan tag → idempoten
    }
}

// ─── Terapkan ────────────────────────────────────────────────────
if (!$dryRun && ($set || $tagsProvided)) {
    try {
        $pdo->beginTransaction();
        if ($set) {
            $cols = implode(', ', array_map(fn($c) => "$c = ?", array_keys($set)));
            $vals = array_values($set);
            $vals[] = $aid;
            $pdo->prepare("UPDATE articles SET $cols WHERE id = ?")->execute($vals);
        }
        if ($tagsProvided) {
            $pdo->prepare("DELETE FROM article_tags WHERE article_id = ?")->execute([$aid]);
            if ($afterTags) ingestSyncTags($pdo, $aid, $afterTags);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('update-article-seo: ' . $e->getMessage());
        ingestLog('500 seo-artikel-gagal id=' . $aid . ': ' . $e->getMessage());
        ingestApiSend(500, ['ok' => false, 'error' => 'Gagal memperbarui SEO artikel.']);
    }
    try {
        pageCacheInvalidate(['/artikel/' . (string) $art['slug']]);
    } catch (Throwable $e) {
        error_log('update-article-seo post-hook: ' . $e->getMessage());
    }
    ingestLog('seo-artikel-diubah id=' . $aid . ' fields=' . implode(',', $changed));
}

ingestApiSend(200, [
    'ok'         => true,
    'dry_run'    => $dryRun,
    'article_id' => $aid,
    'changed'    => $changed,
    'before'     => $before,
    'after'      => $after,
]);
