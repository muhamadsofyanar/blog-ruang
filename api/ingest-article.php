<?php
// ════════════════════════════════════════════════════════════════════════
// Ingest API — POST /api/ingest-article.php
// Menerima artikel JADI (JSON) dari sistem eksternal ber-token dan menyimpannya
// sebagai draft AI (draft_ai) untuk REVIEW MANUAL. TIDAK PERNAH publish otomatis.
//
// Endpoint mesin: tanpa sesi/CSRF. Auth via header Authorization: Bearer <token>
// (verifikasi hash-only, timing-safe). Fitur DEFAULT MATI (ingest_enabled).
//
// Diakses sebagai file nyata (.htaccess hanya mem-fallback request non-file ke
// index.php), jadi tidak perlu route di front controller.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/sanitize.php';
require_once __DIR__ . '/../helpers/cover-fallback.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/article-cta.php';
require_once __DIR__ . '/../helpers/content-health.php'; // audit skor untuk publish otomatis

// ─── 1) Metode + Content-Type ────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Gunakan POST.']);
}
$ctype = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($ctype, 'application/json') === false) {
    ingestApiSend(415, ['ok' => false, 'error' => 'Content-Type harus application/json.']);
}

// ─── 2) Rate-limit (lindungi juga jalur auth) ────────────────────
if (!ingestRateLimitOk()) {
    ingestApiSend(429, ['ok' => false, 'error' => 'Terlalu banyak permintaan. Coba lagi sebentar.']);
}

// ─── 3) Fitur aktif? (default mati demi keamanan) ────────────────
if (!ingestEnabled()) {
    ingestApiSend(403, ['ok' => false, 'error' => 'Ingest API nonaktif.']);
}

// ─── 4) Auth: Authorization: Bearer <token> (hash-only, timing-safe) ──
$auth = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']  // beberapa Apache meneruskan lewat rewrite
    ?? '';
if ($auth === '' && function_exists('apache_request_headers')) {
    $h = apache_request_headers();
    $auth = $h['Authorization'] ?? ($h['authorization'] ?? '');
}
if (!preg_match('/^Bearer\s+(.+)$/i', trim((string) $auth), $mm)) {
    ingestApiSend(401, ['ok' => false, 'error' => 'Token tidak ada. Sertakan header Authorization: Bearer <token>.']);
}
$token = trim($mm[1]);
if (!ingestTokenIsSet() || !ingestVerifyToken($token)) {
    ingestLog('401 auth gagal ip=' . ($_SERVER['REMOTE_ADDR'] ?? '-'));
    ingestApiSend(401, ['ok' => false, 'error' => 'Token tidak valid.']);
}

// ─── 5) Body JSON ────────────────────────────────────────────────
$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    ingestApiSend(400, ['ok' => false, 'error' => 'Body JSON tidak valid.']);
}

$title       = trim((string) ($in['title'] ?? ''));
$contentHtml = (string) ($in['content_html'] ?? '');
if ($title === '' || trim($contentHtml) === '') {
    ingestApiSend(422, ['ok' => false, 'error' => 'Field wajib: title dan content_html.']);
}

// CTA opsional adalah aksi manajemen. Kirim artikel tanpa CTA tetap cukup dengan
// Ingest API biasa; menyetel CTA membutuhkan gate manajemen tambahan.
$articleCta = null;
if (array_key_exists('article_cta', $in)) {
    if (!ingestManageEnabled()) {
        ingestApiSend(403, ['ok' => false, 'error' => 'API manajemen nonaktif. Aktifkan untuk mengatur CTA artikel.']);
    }
    try {
        $articleCta = articleCtaValidateApiPayload($in['article_cta']);
    } catch (InvalidArgumentException $e) {
        ingestApiSend(422, ['ok' => false, 'error' => $e->getMessage()]);
    }
}

// Publish otomatis (mode otonom penuh). Aksi LIVE → butuh gate manajemen. Artikel
// tetap DIBUAT sebagai draft_ai lebih dulu; publish hanya bila skor kesehatan >= 80.
$publishRequested = false;
if (array_key_exists('publish', $in)) {
    if (!is_bool($in['publish'])) {
        ingestApiSend(422, ['ok' => false, 'error' => 'publish wajib boolean.']);
    }
    $publishRequested = $in['publish'];
    if ($publishRequested && !ingestManageEnabled()) {
        ingestApiSend(403, ['ok' => false, 'error' => 'API manajemen nonaktif. Aktifkan untuk publish otomatis.']);
    }
}

// Gambar otomatis (opsional). Backward-compat: hanya diproses bila payload memuat
// cover/images/dry_run. Validasi struktur dulu (422 bila salah bentuk).
$imgOpts = null;
if (isset($in['cover']) || isset($in['images']) || array_key_exists('dry_run', $in)) {
    try {
        $imgOpts = ingestValidateImageOpts($in);
    } catch (InvalidArgumentException $e) {
        ingestApiSend(422, ['ok' => false, 'error' => $e->getMessage()]);
    }
}

$pdo = getDB();

// ─── 6) Idempotensi: external_ref sudah ada → kembalikan yang lama ──
$externalRef = trim((string) ($in['external_ref'] ?? ''));
if ($externalRef !== '') {
    $externalRef = mb_substr($externalRef, 0, 190);
    $st = $pdo->prepare("SELECT id, slug, status, category_id FROM articles WHERE external_ref = ? LIMIT 1");
    $st->execute([$externalRef]);
    $ex = $st->fetch();
    if ($ex) {
        ingestLog('idempotent-hit ref=' . $externalRef . ' id=' . $ex['id']);
        ingestApiSend(200, [
            'ok' => true, 'article_id' => (int) $ex['id'], 'slug' => $ex['slug'],
            'status' => $ex['status'], 'edit_url' => '/admin/articles/' . (int) $ex['id'],
            'article_cta' => articleCtaApiOutput(articleCtaGet('article', (int) $ex['id'])),
            'effective_article_cta' => articleCtaApiOutput(articleCtaResolve($ex)),
            'idempotent' => true,
        ]);
    }
}

// ─── 7) Normalisasi field (batasi panjang sesuai skema) ──────────
$content   = sanitizeArticleHtml($contentHtml);
$slugIn    = trim((string) ($in['slug'] ?? ''));
$slug      = uniqueSlug($pdo, 'articles', $slugIn !== '' ? $slugIn : $title);

$excerpt   = mb_substr(trim((string) ($in['excerpt'] ?? '')), 0, 500);
$title      = mb_substr($title, 0, 255);
$focusKw   = mb_substr(trim((string) ($in['focus_keyword'] ?? '')), 0, 190);

// Auto alt-text: isi alt gambar yang kosong (gratis, lokal) agar artikel masuk
// lebih sehat — penting untuk publish otomatis Hermes (aturan images_have_alt).
require_once __DIR__ . '/../helpers/alt-text.php';
$content = altTextFill($content, ['focus_keyword' => $focusKw, 'title' => $title])['html'];

$relatedKw = $in['related_keywords'] ?? '';
if (is_array($relatedKw)) {
    $relatedKw = implode(', ', array_map('strval', $relatedKw));
}
$relatedKw = trim((string) $relatedKw);

$intent    = in_array($in['search_intent'] ?? '', INGEST_SEARCH_INTENTS, true) ? $in['search_intent'] : null;
$language  = isValidLanguage($in['language'] ?? '') ? $in['language'] : getSetting('default_language', 'id');
$metaTitle = mb_substr(trim((string) ($in['meta_title'] ?? '')), 0, 255);
$metaDesc  = mb_substr(trim((string) ($in['meta_description'] ?? '')), 0, 320);

// Kategori: resolve ke id bila cocok slug/nama, else null (jangan buat baru di v1).
$categoryId = null;
$catIn = trim((string) ($in['category'] ?? ''));
if ($catIn !== '') {
    $st = $pdo->prepare("SELECT id FROM categories WHERE slug = ? OR name = ? LIMIT 1");
    $st->execute([slugify($catIn), $catIn]);
    $cid = $st->fetchColumn();
    if ($cid !== false) {
        $categoryId = (int) $cid;
    }
}

$authorId = ingestResolveAuthorId($pdo);

// ─── Gambar otomatis (cover + inline) — opsional, NON-FATAL ──────
// Provider legal (Pexels), TANPA hotlink: aset diunduh, divalidasi, dioptimasi
// ke WebP, lalu disimpan lokal. Kegagalan apa pun → artikel tetap diproses +
// warning. dry_run → tampilkan rencana TANPA membuat file/artikel.
$imgBlock       = null; // blok 'images' untuk respons sukses
$coverFromStock = null; // path cover hasil unduhan (bukan fallback SVG)
$inlineAdded    = 0;
if ($imgOpts !== null) {
    require_once __DIR__ . '/../helpers/stock-images.php';
    $imagesEnabled = ingestImagesEnabled();

    // DRY RUN — selalu balas rencana, TIDAK PERNAH membuat file/artikel.
    if ($imgOpts['dry_run']) {
        if (!$imagesEnabled) {
            ingestApiSend(200, [
                'ok' => true, 'dry_run' => true, 'images_enabled' => false,
                'plan' => [
                    'queries' => stockBuildQueries([
                        'title' => $title, 'focus_keyword' => $focusKw,
                        'category' => $catIn, 'content' => $content,
                    ]),
                    'cover' => null, 'inline' => [],
                ],
                'warnings' => ['Fitur gambar otomatis nonaktif di Pengaturan → Integrasi.'],
            ]);
        }
        $plan = stockGenerateForArticle([
            'title' => $title, 'focus_keyword' => $focusKw, 'category' => $catIn,
            'slug' => $slug, 'content_html' => $content,
            'cover' => $imgOpts['cover'], 'images' => $imgOpts['images'], 'dry_run' => true,
        ]);
        ingestApiSend(200, array_merge(['ok' => true, 'images_enabled' => true], $plan));
    }

    // RUN NYATA — terapkan ke konten + cover (non-fatal).
    if ($imgOpts['requested']) {
        if (!$imagesEnabled) {
            $imgBlock = ['enabled' => false, 'cover_set' => false, 'inline_added' => 0,
                'warnings' => ['Fitur gambar otomatis nonaktif — dilewati.']];
        } else {
            try {
                $gen = stockGenerateForArticle([
                    'title' => $title, 'focus_keyword' => $focusKw, 'category' => $catIn,
                    'slug' => $slug, 'content_html' => $content,
                    'cover' => $imgOpts['cover'], 'images' => $imgOpts['images'], 'dry_run' => false,
                ]);
                $content     = (string) ($gen['content_html'] ?? $content);
                $inlineAdded = (int) ($gen['inline_added'] ?? 0);
                if (!empty($gen['cover']['path'])) $coverFromStock = (string) $gen['cover']['path'];
                $imgBlock = ['enabled' => true, 'cover_set' => $coverFromStock !== null,
                    'inline_added' => $inlineAdded, 'warnings' => $gen['warnings'] ?? []];
            } catch (Throwable $e) {
                error_log('ingest stock-images: ' . $e->getMessage());
                $imgBlock = ['enabled' => true, 'cover_set' => false, 'inline_added' => 0,
                    'warnings' => ['Gagal memproses gambar otomatis.']];
            }
        }
    }
}

// Cover: hasil unduhan (bila ada) atau fallback SVG deterministik (artikel selalu bercover).
$coverImage = null;
$coverFb    = 0;
if ($coverFromStock !== null) {
    $coverImage = $coverFromStock;
    $coverFb    = 0;
} else {
    $fb = scribeGenerateFallbackCover($slug, $title);
    if ($fb !== null) {
        $coverImage = $fb;
        $coverFb    = 1;
    }
}

// ─── 8) Simpan (status SELALU draft_ai) ──────────────────────────
try {
    $pdo->beginTransaction();
    $sql = "INSERT INTO articles
                (title, slug, content, excerpt, cover_image, cover_is_fallback, category_id, author_id,
                 focus_keyword, related_keywords, search_intent, language, meta_title, meta_description,
                 status, external_ref)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'draft_ai', ?)";
    $pdo->prepare($sql)->execute([
        $title, $slug, $content, $excerpt !== '' ? $excerpt : null, $coverImage, $coverFb,
        $categoryId, $authorId, $focusKw !== '' ? $focusKw : null, $relatedKw !== '' ? $relatedKw : null,
        $intent, $language, $metaTitle !== '' ? $metaTitle : null, $metaDesc !== '' ? $metaDesc : null,
        $externalRef !== '' ? $externalRef : null,
    ]);
    $id = (int) $pdo->lastInsertId();

    if ($articleCta !== null) articleCtaSave('article', $id, $articleCta);

    $tags = $in['tags'] ?? [];
    if (is_array($tags) && $tags) {
        ingestSyncTags($pdo, $id, $tags);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Balapan idempotensi: external_ref UNIQUE bentrok karena request paralel →
    // ambil baris yang menang, kembalikan sebagai idempoten (bukan error).
    if ($externalRef !== '') {
        $st = $pdo->prepare("SELECT id, slug, status, category_id FROM articles WHERE external_ref = ? LIMIT 1");
        $st->execute([$externalRef]);
        if ($row = $st->fetch()) {
            ingestApiSend(200, [
                'ok' => true, 'article_id' => (int) $row['id'], 'slug' => $row['slug'],
                'status' => $row['status'], 'edit_url' => '/admin/articles/' . (int) $row['id'],
                'article_cta' => articleCtaApiOutput(articleCtaGet('article', (int) $row['id'])),
                'effective_article_cta' => articleCtaApiOutput(articleCtaResolve($row)),
                'idempotent' => true,
            ]);
        }
    }
    scribeDeleteFallbackCover($coverImage); // bersihkan SVG yatim
    error_log('ingest-article: ' . $ex->getMessage());
    ingestLog('500 insert-gagal: ' . $ex->getMessage());
    ingestApiSend(500, ['ok' => false, 'error' => 'Gagal menyimpan artikel.']);
}

ingestLog('201 dibuat id=' . $id . ' slug=' . $slug . ($externalRef !== '' ? ' ref=' . $externalRef : '') . ($articleCta !== null ? ' cta=1' : ''));

// ─── 9) Publish otomatis (mode otonom): hanya bila skor kesehatan >= 80 ──
$published = false;
$health    = null;
if ($publishRequested) {
    $now = date('Y-m-d H:i:s');
    $candidate = [
        'id' => $id, 'title' => $title, 'slug' => $slug, 'status' => 'published',
        'content' => $content, 'excerpt' => $excerpt, 'cover_image' => $coverImage,
        'category_id' => $categoryId, 'author_id' => $authorId,
        'focus_keyword' => $focusKw !== '' ? $focusKw : null,
        'related_keywords' => $relatedKw !== '' ? $relatedKw : null,
        'search_intent' => $intent,
        'meta_title' => $metaTitle !== '' ? $metaTitle : null,
        'meta_description' => $metaDesc !== '' ? $metaDesc : null,
        'published_at' => $now, 'updated_at' => $now, 'faq_approved_count' => 0,
    ];
    $health = contentHealthAnalyzeArticle($candidate);
    if ($health['score'] >= 80) {
        try {
            $pdo->prepare(
                "UPDATE articles SET status = 'published', published_at = ?, updated_at = ?
                 WHERE id = ? AND status = 'draft_ai'"
            )->execute([$now, $now, $id]);
            $published = true;
            require_once __DIR__ . '/../helpers/page-cache.php';
            require_once __DIR__ . '/../helpers/sitemap.php';
            try {
                scribeGenerateSitemap();
                pageCacheFlushAll();
                require_once __DIR__ . '/../helpers/indexnow.php';
                indexnowPingArticle($slug); // beri tahu mesin pencari (non-fatal)
            } catch (Throwable $e) {
                error_log('ingest publish post-hook: ' . $e->getMessage());
            }
            ingestLog('auto-publish id=' . $id . ' score=' . $health['score']);
        } catch (Throwable $e) {
            error_log('ingest auto-publish: ' . $e->getMessage());
            $published = false;
        }
    } else {
        ingestLog('auto-publish ditahan id=' . $id . ' score=' . $health['score'] . ' (<80)');
    }
}

$directCta = $articleCta ?? articleCtaGet('article', $id);
ingestApiSend(201, array_merge([
    'ok' => true, 'article_id' => $id, 'slug' => $slug,
    'status' => $published ? 'published' : 'draft_ai',
    'published' => $published,
    'edit_url' => '/admin/articles/' . $id,
    'public_url' => $published ? '/artikel/' . $slug : null,
    'article_cta' => articleCtaApiOutput($directCta),
    'effective_article_cta' => articleCtaApiOutput(articleCtaResolve(
        ['id' => $id, 'category_id' => $categoryId],
        $articleCta !== null ? ['article' => $articleCta] : []
    )),
], $imgBlock !== null ? ['images' => $imgBlock] : [], $publishRequested ? [
    'publish_requested' => true,
    'seo_score' => $health['score'],
    'seo_level' => $health['level'],
    'issues' => $health['issues'],
    'publish_note' => $published
        ? 'Artikel diterbitkan otomatis (skor >= 80).'
        : 'Publish ditahan: skor < 80. Perbaiki isi lalu terbitkan via Management API (content-health), atau tinjau manual.',
] : []));
