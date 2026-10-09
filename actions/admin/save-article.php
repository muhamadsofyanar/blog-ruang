<?php
// POST /actions/admin/save-article — buat/ubah artikel (staff). Menangani:
// sanitasi konten, upload cover + varian, sinkron tag, snapshot pra-simpan,
// dan redirect 301 otomatis saat slug artikel published berubah.
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/sanitize.php';
require_once __DIR__ . '/../../helpers/media.php';
require_once __DIR__ . '/../../helpers/cover-fallback.php';
require_once __DIR__ . '/../../helpers/sitemap.php';
require_once __DIR__ . '/../../helpers/page-cache.php';
require_once __DIR__ . '/../../helpers/article-cta.php';
requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRF($_POST['csrf_token'] ?? '')) {
    flash('error', 'Permintaan tidak valid.', 'error');
    redirect('/admin/articles');
}

$pdo = getDB();
$id  = (int) ($_POST['id'] ?? 0);

// ─── Muat artikel lama (edit) + cek kepemilikan ──────────────────
$existing = null;
if ($id > 0) {
    $st = $pdo->prepare("SELECT id, slug, content, status, published_at, author_id, cover_image, cover_is_fallback FROM articles WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $existing = $st->fetch();
    if (!$existing) {
        flash('error', 'Artikel tidak ditemukan.', 'error');
        redirect('/admin/articles');
    }
    if (isWriter() && (int) $existing['author_id'] !== currentUserId()) {
        denyAccess(); // writer tak boleh edit artikel orang lain
    }
}

// CTA divalidasi sebelum upload atau perubahan artikel. Form lama/API tetap aman.
$articleCta = null;
try {
    if (array_key_exists('article_cta', $_POST)) {
        if (!is_array($_POST['article_cta'])) throw new InvalidArgumentException('Format CTA tidak valid.');
        $articleCta = articleCtaValidate($_POST['article_cta']);
    }
} catch (InvalidArgumentException $e) {
    flash('error', $e->getMessage(), 'error');
    redirect($id > 0 ? '/admin/articles/' . $id : '/admin/articles/new');
}

// ─── Ambil & validasi input ──────────────────────────────────────
$title = trim($_POST['title'] ?? '');
if ($title === '') {
    flash('error', 'Judul artikel wajib diisi.', 'error');
    redirect($id > 0 ? '/admin/articles/' . $id : '/admin/articles/new');
}

$slugIn        = trim($_POST['slug'] ?? '');
$excerpt       = trim($_POST['excerpt'] ?? '');
$categoryId    = (int) ($_POST['category_id'] ?? 0) ?: null;
$focusKeyword  = trim($_POST['focus_keyword'] ?? '');
$relatedKw     = trim($_POST['related_keywords'] ?? '');
$searchIntent  = $_POST['search_intent'] ?? '';
$language      = isValidLanguage($_POST['language'] ?? '') ? $_POST['language'] : (getSetting('default_language', 'id'));
$metaTitle     = trim($_POST['meta_title'] ?? '');
$metaDesc      = trim($_POST['meta_description'] ?? '');
$canonical     = trim($_POST['canonical_url'] ?? '');
$status        = in_array($_POST['status'] ?? '', ['draft', 'draft_ai', 'scheduled', 'published'], true) ? $_POST['status'] : 'draft';
// Simpan manual = sinyal human-touched: status draft_ai turun ke draft biasa
// (draft_ai hanya di-set oleh alur drafting AI, tak pernah dipilih manual).
if ($status === 'draft_ai') $status = 'draft';
$coverAlt      = trim($_POST['cover_alt'] ?? '');
$publishedAtIn = trim($_POST['published_at'] ?? '');

if (!in_array($searchIntent, ['informational', 'transactional', 'navigational'], true)) {
    $searchIntent = null;
}

// Sanitasi konten SAAT SIMPAN (bukan hanya render).
$content = sanitizeArticleHtml($_POST['content'] ?? '');

// Outline (JSON editable) — validasi sebagai JSON, simpan apa adanya (DATA murni).
$outlineJson = null;
if (isset($_POST['outline_json']) && trim($_POST['outline_json']) !== '') {
    $dec = json_decode((string) $_POST['outline_json'], true);
    if (is_array($dec)) $outlineJson = json_encode($dec);
}

// published_at: normalisasi dari datetime-local.
$publishedAt = null;
if ($publishedAtIn !== '') {
    $ts = strtotime(str_replace('T', ' ', $publishedAtIn));
    if ($ts !== false) $publishedAt = date('Y-m-d H:i:s', $ts);
}
if ($status === 'scheduled' && $publishedAt === null) {
    flash('error', 'Jadwal terbit (published_at) wajib diisi untuk status Terjadwal.', 'error');
    redirect($id > 0 ? '/admin/articles/' . $id : '/admin/articles/new');
}
if ($status === 'published' && $publishedAt === null) {
    $publishedAt = date('Y-m-d H:i:s'); // publish tanpa tanggal → sekarang
}

// ─── Slug final (unik) ───────────────────────────────────────────
$slug = uniqueSlug($pdo, 'articles', $slugIn !== '' ? $slugIn : $title, $id ?: null);

// ─── Cover (upload asli ATAU fallback SVG deterministik) ─────────
// Aturan: upload asli menang (flag=0, hapus fallback lama). Tanpa cover asli →
// generate fallback SVG dari slug+judul (flag=1) → SATU jalur: artikel PASTI
// punya cover_image. helpers/cover-fallback.php.
$existingCover = $existing['cover_image'] ?? null;
$existingIsFb  = (int) ($existing['cover_is_fallback'] ?? 0);
$hasRealCover  = $existingCover && $existingIsFb === 0;
$coverImage    = $existingCover;
$coverFallback = $existingIsFb;

$coverRemove = ($_POST['cover_remove'] ?? '') === '1';

if (isset($_FILES['cover']) && ($_FILES['cover']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    if ($coverAlt === '') {
        flash('error', 'Alt text cover wajib diisi saat mengunggah gambar cover.', 'error');
        redirect($id > 0 ? '/admin/articles/' . $id : '/admin/articles/new');
    }
    $res = saveCoverImage($_FILES['cover'], $slug);
    if (isset($res['error'])) {
        flash('error', $res['error'], 'error');
        redirect($id > 0 ? '/admin/articles/' . $id : '/admin/articles/new');
    }
    // Ganti fallback lama (bila ada) dengan cover asli.
    if ($existingIsFb === 1 && $existingCover) scribeDeleteFallbackCover($existingCover);
    $coverImage    = $res['path'];
    $coverFallback = 0;
} elseif ($coverRemove && $hasRealCover) {
    // Hapus cover asli → kembali ke fallback SVG (invarian: artikel selalu bercover).
    scribeDeleteCoverFile($existingCover);
    $coverAlt = '';
    $fb = scribeGenerateFallbackCover($slug, $title);
    if ($fb !== null) {
        $coverImage    = $fb;
        $coverFallback = 1;
    } else {
        $coverImage    = null;
        $coverFallback = 0;
    }
} elseif (!$hasRealCover) {
    // Tidak ada cover asli → (re)generate fallback. Bila slug berubah, hapus SVG lama.
    if ($existingIsFb === 1 && $existingCover && $existingCover !== scribeFallbackCoverPath($slug)) {
        scribeDeleteFallbackCover($existingCover);
    }
    $fb = scribeGenerateFallbackCover($slug, $title);
    if ($fb !== null) {
        $coverImage    = $fb;
        $coverFallback = 1;
    }
}

$authorId = $existing['author_id'] ?? currentUserId();

// ─── Simpan (transaksi) ──────────────────────────────────────────
try {
    $pdo->beginTransaction();

    if ($id > 0) {
        // Snapshot konten lama (satu snapshot) bila konten berubah.
        $snapshot = ($existing['content'] !== $content) ? $existing['content'] : null;

        $sql = "UPDATE articles SET title=?, slug=?, content=?, content_snapshot=COALESCE(?, content_snapshot),
                    draft_content=NULL, excerpt=?, cover_image=?, cover_alt=?, cover_is_fallback=?,
                    category_id=?, focus_keyword=?, related_keywords=?, outline_json=COALESCE(?, outline_json),
                    search_intent=?, language=?, meta_title=?, meta_description=?, canonical_url=?, status=?, published_at=?
                WHERE id=?";
        $pdo->prepare($sql)->execute([
            $title, $slug, $content, $snapshot, $excerpt !== '' ? $excerpt : null,
            $coverImage, $coverAlt !== '' ? $coverAlt : null, $coverFallback,
            $categoryId, $focusKeyword !== '' ? $focusKeyword : null, $relatedKw !== '' ? $relatedKw : null,
            $outlineJson, $searchIntent, $language, $metaTitle !== '' ? $metaTitle : null, $metaDesc !== '' ? $metaDesc : null,
            $canonical !== '' ? $canonical : null, $status, $publishedAt, $id,
        ]);
    } else {
        $sql = "INSERT INTO articles (title, slug, content, excerpt, cover_image, cover_alt, cover_is_fallback,
                    category_id, author_id, focus_keyword, related_keywords, outline_json, search_intent, language,
                    meta_title, meta_description, canonical_url, status, published_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sql)->execute([
            $title, $slug, $content, $excerpt !== '' ? $excerpt : null, $coverImage,
            $coverAlt !== '' ? $coverAlt : null, $coverFallback, $categoryId, $authorId,
            $focusKeyword !== '' ? $focusKeyword : null, $relatedKw !== '' ? $relatedKw : null,
            $outlineJson, $searchIntent, $language, $metaTitle !== '' ? $metaTitle : null, $metaDesc !== '' ? $metaDesc : null,
            $canonical !== '' ? $canonical : null, $status, $publishedAt,
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    if ($articleCta !== null) articleCtaSave('article', $id, $articleCta);

    // ─── Sinkron tag (buat otomatis bila belum ada) ──────────────
    syncArticleTags($pdo, $id, $_POST['tags'] ?? '');

    // ─── Redirect otomatis (slug hidup + chain diringkas) ────────
    // Slug ini kini dipakai artikel → hapus redirect yang old_slug-nya = slug
    // (slug "hidup lagi", tidak boleh diarahkan ke tempat lain).
    $pdo->prepare("DELETE FROM redirects WHERE old_slug = ?")->execute([$slug]);

    if ($existing && $existing['slug'] !== $slug && $existing['status'] === 'published') {
        $oldSlug = $existing['slug'];
        // Ringkas rantai: semua redirect yang menunjuk slug lama → arahkan ke slug baru.
        $pdo->prepare("UPDATE redirects SET new_slug = ? WHERE new_slug = ?")->execute([$slug, $oldSlug]);
        // Buat redirect slug lama → slug baru (301, otomatis dari perubahan slug).
        $pdo->prepare("INSERT INTO redirects (old_slug, new_slug, type, source) VALUES (?, ?, 301, 'auto')
                       ON DUPLICATE KEY UPDATE new_slug = VALUES(new_slug), source = 'auto'")->execute([$oldSlug, $slug]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('save-article: ' . $e->getMessage());
    flash('error', 'Gagal menyimpan artikel: ' . $e->getMessage(), 'error');
    redirect($id > 0 ? '/admin/articles/' . $id : '/admin/articles/new');
}

// ─── Event-driven: regen sitemap + invalidate page cache (targeted) ──────
try {
    $catSlug = null;
    if ($categoryId) {
        $cs = $pdo->prepare("SELECT slug FROM categories WHERE id = ?");
        $cs->execute([$categoryId]);
        $catSlug = $cs->fetchColumn() ?: null;
    }
    $ts = $pdo->prepare("SELECT t.slug FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ?");
    $ts->execute([$id]);
    $tagSlugs = $ts->fetchAll(PDO::FETCH_COLUMN);

    scribeGenerateSitemap();
    pageCacheInvalidateArticle($slug, $catSlug, $tagSlugs);
    // Slug berubah → bersihkan juga cache URL lama.
    if ($existing && $existing['slug'] !== $slug) pageCacheInvalidate(['/artikel/' . $existing['slug']]);
    // IndexNow: beri tahu mesin pencari saat artikel live (non-fatal).
    if ($status === 'published') {
        require_once __DIR__ . '/../../helpers/indexnow.php';
        indexnowPingArticle($slug);
    }
} catch (Throwable $e) {
    error_log('save-article post-hook: ' . $e->getMessage());
}

flash('success', 'Artikel tersimpan.', 'success');
redirect('/admin/articles/' . $id);


/** Sinkron tag artikel dari string koma. Buat tag baru bila belum ada. */
function syncArticleTags(PDO $pdo, int $articleId, string $tagStr): void
{
    $names = array_filter(array_map('trim', explode(',', $tagStr)), fn($n) => $n !== '');
    $names = array_values(array_unique($names));

    $tagIds = [];
    foreach ($names as $name) {
        $st = $pdo->prepare("SELECT id FROM tags WHERE name = ? LIMIT 1");
        $st->execute([$name]);
        $tid = $st->fetchColumn();
        if ($tid === false) {
            $slug = uniqueSlug($pdo, 'tags', $name);
            $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
            $tid = (int) $pdo->lastInsertId();
        }
        $tagIds[] = (int) $tid;
    }

    // Reset relasi lalu masukkan set baru.
    $pdo->prepare("DELETE FROM article_tags WHERE article_id = ?")->execute([$articleId]);
    if ($tagIds) {
        $ins = $pdo->prepare("INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (?, ?)");
        foreach ($tagIds as $tid) {
            $ins->execute([$articleId, $tid]);
        }
    }
}
