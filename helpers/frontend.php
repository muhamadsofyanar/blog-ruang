<?php
// ════════════════════════════════════════════════════════════════════════
// Helper frontend publik (Track B, theme-default). Resolusi identitas rebrand,
// URL kanonik, query artikel published (TANPA kolom content), excerpt aman,
// cover + fallback placeholder deterministik. Dipakai HANYA oleh views/theme-default
// dan pages/public/*. Render konten artikel WAJIB lewat sanitizeArticleHtml
// (helpers/sanitize.php) — B-03.
// ════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/cover-fallback.php';
require_once __DIR__ . '/media.php';

/**
 * Identitas brand aktif (setting rebrand + default). Satu titik resolusi supaya
 * layout/partials tidak memanggil getSetting berulang. Halaman enforcement/update
 * TIDAK memakai ini (identitas Averion tetap).
 */
function feBrand(): array
{
    static $b = null;
    if ($b !== null) return $b;
    $b = [
        'name'        => getSetting('blog_name', APP_NAME) ?: APP_NAME,
        'tagline'     => getSetting('blog_tagline', '') ?: '',
        'accent'      => getSetting('accent_color', '#6366f1') ?: '#6366f1',
        'logo'        => getSetting('brand_logo', '') ?: '',
        'favicon'     => getSetting('brand_favicon', '') ?: '',
        'og_default'  => getSetting('brand_og_default', '') ?: '',
        'powered_by'       => getSetting('powered_by_enabled', '1') !== '0',
        'powered_by_text'  => trim((string) getSetting('powered_by_text', 'Powered by Averion SEO Engine')) ?: 'Powered by Averion SEO Engine',
        'powered_by_url'   => trim((string) getSetting('powered_by_url', 'https://averion.id')),
        'powered_by_blank' => getSetting('powered_by_blank', '1') !== '0',
        // CTA header (opsional) — tampil hanya bila label+url terisi.
        'cta_label'   => getSetting('cta_label', '') ?: '',
        'cta_url'     => getSetting('cta_url', '') ?: '',
        // CTA band bawah (opsional) — tampil hanya bila judul terisi.
        // mode: 'off' (default) | 'link' | 'newsletter'.
        'band_mode'   => getSetting('cta_band_mode', 'off') ?: 'off',
        'band_title'  => getSetting('cta_band_title', '') ?: '',
        'band_text'   => getSetting('cta_band_text', '') ?: '',
        'band_btn'    => getSetting('cta_band_btn_label', '') ?: '',
        'band_url'    => getSetting('cta_band_url', '') ?: '',
    ];
    return $b;
}

/** URL absolut aset upload dari path relatif (mis. "covers/foo.jpg"). */
function feUploadUrl(string $rel): string
{
    return rtrim(UPLOAD_URL, '/') . '/' . ltrim($rel, '/');
}

/**
 * Konfigurasi Popup Promo melayang (frontend). Aktif hanya bila toggle ON dan
 * caption terisi. 'sig' = tanda tangan konten → dipakai localStorage agar promo
 * yang diubah tampil lagi. Disimpan di settings grup 'promo'.
 */
function fePromo(): array
{
    $enabled = getSetting('promo_enabled', '0') === '1';
    $caption = trim((string) getSetting('promo_caption', ''));
    $img     = trim((string) getSetting('promo_image', ''));
    $label   = trim((string) getSetting('promo_cta_label', ''));
    $url     = trim((string) getSetting('promo_cta_url', ''));
    $pos     = (string) getSetting('promo_position', 'bottom-left');
    $delay   = (int) getSetting('promo_delay', '3');
    if (!in_array($pos, PROMO_POSITIONS, true)) $pos = 'bottom-left';
    if ($delay < 0 || $delay > 120) $delay = 3;
    return [
        'on'        => $enabled && $caption !== '',
        'caption'   => $caption,
        'image_rel' => $img,
        'image'     => $img !== '' ? rtrim(UPLOAD_URL, '/') . '/' . ltrim($img, '/') : '',
        'cta_label' => $label,
        'cta_url'   => $url,
        'position'  => $pos,
        'delay'     => $delay,
        'sig'       => substr(md5($caption . '|' . $img . '|' . $label . '|' . $url . '|' . $pos . '|' . $delay), 0, 10),
    ];
}

/** Posisi Popup Promo yang valid (label untuk admin di fePro(). */
const PROMO_POSITIONS = ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'];

/** Peta posisi → label Indonesia (dropdown admin). */
function fePromoPositionLabels(): array
{
    return [
        'top-left' => 'Kiri Atas', 'top-center' => 'Tengah Atas', 'top-right' => 'Kanan Atas',
        'bottom-left' => 'Kiri Bawah', 'bottom-center' => 'Tengah Bawah', 'bottom-right' => 'Kanan Bawah',
    ];
}

/**
 * Thumbnail dekoratif COMPACT (khusus mobile Hybrid): gradient soft + pola SVG
 * transparan (dots/grid/diagonal/lingkaran) TANPA teks judul. Deterministik
 * per-slug (warna & pola konsisten). Ringan (inline SVG kecil). id defs memakai
 * article id agar unik di halaman.
 */
function feCompactThumb(array $a): string
{
    $slug = (string) ($a['slug'] ?? '');
    $id   = (int) ($a['id'] ?? 0);
    $seed = crc32($slug !== '' ? $slug : 'a');
    // Palet muted & soft (tidak terlalu kontras).
    $pal = [
        ['#3a8f88', '#2a6560'], ['#4a6488', '#33496a'], ['#6a5f92', '#473f63'],
        ['#b07a52', '#875637'], ['#4f8f6d', '#376049'], ['#566072', '#3e4550'],
    ];
    [$c1, $c2] = $pal[$seed % count($pal)];
    $type = ($seed >> 3) % 4;
    $gid  = 'cg' . $id . ($seed % 97);
    $pid  = 'cp' . $id . ($seed % 89);

    $defsPat = '';
    if ($type === 0) {          // dots
        $defsPat = '<pattern id="' . $pid . '" width="9" height="9" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.3" fill="#ffffff"/></pattern>';
        $overlay = '<rect width="100" height="100" fill="url(#' . $pid . ')" opacity="0.12"/>';
    } elseif ($type === 1) {    // grid
        $defsPat = '<pattern id="' . $pid . '" width="12" height="12" patternUnits="userSpaceOnUse"><path d="M12 0H0V12" fill="none" stroke="#ffffff" stroke-width="0.8"/></pattern>';
        $overlay = '<rect width="100" height="100" fill="url(#' . $pid . ')" opacity="0.14"/>';
    } elseif ($type === 2) {    // garis diagonal
        $defsPat = '<pattern id="' . $pid . '" width="10" height="10" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="10" stroke="#ffffff" stroke-width="1.4"/></pattern>';
        $overlay = '<rect width="100" height="100" fill="url(#' . $pid . ')" opacity="0.10"/>';
    } else {                    // lingkaran abstrak
        $overlay = '<g fill="#ffffff" opacity="0.10"><circle cx="82" cy="20" r="26"/><circle cx="16" cy="84" r="20"/><circle cx="54" cy="56" r="12"/></g>';
    }

    return '<svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMid slice" class="w-full h-full" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
         . '<defs><linearGradient id="' . $gid . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient>' . $defsPat . '</defs>'
         . '<rect width="100" height="100" fill="url(#' . $gid . ')"/>'
         . $overlay
         . '</svg>';
}

// ─── URL kanonik entitas publik ───────────────────────────────────────────
function feArticleUrl(string $slug): string  { return url('/artikel/' . rawurlencode($slug)); }
function feCategoryUrl(string $slug): string { return url('/kategori/' . rawurlencode($slug)); }
function feTagUrl(string $slug): string      { return url('/tag/' . rawurlencode($slug)); }

/**
 * Excerpt aman (teks polos) untuk card/meta. Pakai kolom excerpt bila ada,
 * jika kosong potong dari content ter-strip. SELALU di-escape saat render.
 */
function feExcerpt(array $a, int $len = 160): string
{
    $src = trim((string) ($a['excerpt'] ?? ''));
    if ($src === '' && isset($a['content'])) $src = (string) $a['content'];
    return truncateText($src, $len);
}

/**
 * Daftar kategori untuk navigasi/pill — hanya yang punya artikel published.
 * Diurut nama. Dipakai header nav + filter pill listing.
 */
function feNavCategories(): array
{
    static $rows = null;
    if ($rows !== null) return $rows;
    try {
        $rows = getDB()->query(
            "SELECT c.id, c.name, c.slug
             FROM categories c
             JOIN articles a ON a.category_id = c.id
                AND a.status = 'published' AND a.published_at <= NOW()
             GROUP BY c.id, c.name, c.slug
             ORDER BY c.name"
        )->fetchAll();
    } catch (Throwable $e) {
        error_log('feNavCategories: ' . $e->getMessage());
        $rows = [];
    }
    return $rows;
}

/**
 * Query artikel published (list). TANPA kolom content (pakai index status,published_at).
 * $o: category_id, tag_id, search, limit, offset, exclude_id.
 * Mengembalikan baris {id,title,slug,excerpt,cover_image,cover_is_fallback,
 * published_at, cat_name, cat_slug, author_name}.
 */
function fePublishedArticles(array $o = []): array
{
    [$sqlFrom, $sqlWhere, $params] = _feListParts($o);
    $limit  = max(1, (int) ($o['limit'] ?? 12));
    $offset = max(0, (int) ($o['offset'] ?? 0));
    $sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.cover_image, a.cover_is_fallback,
                   a.published_at, c.name AS cat_name, c.slug AS cat_slug, u.name AS author_name
            $sqlFrom
            $sqlWhere
            ORDER BY a.published_at DESC
            LIMIT $limit OFFSET $offset";
    try {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('fePublishedArticles: ' . $e->getMessage());
        return [];
    }
}

/** Jumlah artikel published sesuai filter (untuk pagination). */
function feCountArticles(array $o = []): int
{
    [$sqlFrom, $sqlWhere, $params] = _feListParts($o);
    try {
        $stmt = getDB()->prepare("SELECT COUNT(DISTINCT a.id) $sqlFrom $sqlWhere");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('feCountArticles: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Artikel featured beranda: yang di-PIN (is_pinned=1) bila ada, else terbaru.
 * Satu baris atau null. Kolom sama dengan fePublishedArticles.
 */
function feFeaturedArticle(): ?array
{
    $sel = "SELECT a.id, a.title, a.slug, a.excerpt, a.cover_image, a.cover_is_fallback,
                   a.published_at, c.name AS cat_name, c.slug AS cat_slug, u.name AS author_name
            FROM articles a
            LEFT JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE a.status = 'published' AND a.published_at <= NOW()";
    try {
        $pdo = getDB();
        // Prioritas pinned.
        $row = $pdo->query($sel . " AND a.is_pinned = 1 ORDER BY a.published_at DESC LIMIT 1")->fetch();
        if ($row) return $row;
        $row = $pdo->query($sel . " ORDER BY a.published_at DESC LIMIT 1")->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('feFeaturedArticle: ' . $e->getMessage());
        return null;
    }
}

/** Bangun FROM/WHERE/params bersama untuk list + count (prepared). */
function _feListParts(array $o): array
{
    $from = "FROM articles a
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN users u ON u.id = a.author_id";
    $where  = ["a.status = 'published'", "a.published_at <= NOW()"];
    $params = [];

    if (!empty($o['tag_id'])) {
        $from .= " JOIN article_tags at ON at.article_id = a.id AND at.tag_id = ?";
        $params[] = (int) $o['tag_id'];
    }
    if (!empty($o['category_id'])) {
        $where[] = "a.category_id = ?";
        $params[] = (int) $o['category_id'];
    }
    if (!empty($o['search'])) {
        $where[] = "(a.title LIKE ? OR a.excerpt LIKE ? OR a.focus_keyword LIKE ?)";
        $like = '%' . $o['search'] . '%';
        array_push($params, $like, $like, $like);
    }
    if (!empty($o['exclude_id'])) {
        $where[] = "a.id <> ?";
        $params[] = (int) $o['exclude_id'];
    }
    if (!empty($o['author_id'])) {
        $where[] = "a.author_id = ?";
        $params[] = (int) $o['author_id'];
    }
    return [$from, 'WHERE ' . implode(' AND ', $where), $params];
}

/**
 * HTML cover untuk card/featured. Raster memakai dimensi aktual + srcset.
 *  - Cover asli (raster, cover_is_fallback=0, file ada) → <img> + srcset 480/960.
 *  - Selain itu → fallback SVG deterministik (helpers/cover-fallback.php),
 *    file dipastikan ada (self-heal, tanpa tulis DB). Vektor → tanpa srcset.
 *  - Jaring terakhir (generate gagal) → gradient inline.
 *
 * @param string $sizes atribut sizes untuk srcset raster
 * @param bool   $eager true → loading eager + fetchpriority (featured above-fold)
 */
function feCoverHtml(array $a, string $imgClass = '', string $sizes = '100vw', bool $eager = false): string
{
    $slug    = (string) ($a['slug'] ?? '');
    $title   = (string) ($a['title'] ?? '');
    $cover   = (string) ($a['cover_image'] ?? '');
    $isFb    = !empty($a['cover_is_fallback']);
    $dims    = ' width="1600" height="900"';
    $absOf   = fn(string $rel) => rtrim(UPLOAD_PATH, '/\\') . '/' . $rel;

    // 1) Cover asli raster.
    if ($cover !== '' && !$isFb && !str_ends_with(strtolower($cover), '.svg') && is_file($absOf($cover))) {
        return scribeUploadImageHtml($cover, $title, $imgClass, $sizes, $eager);
    }

    // 2) Fallback SVG deterministik (pastikan file ada — self-heal).
    $rel = ($isFb && $cover !== '' && str_ends_with(strtolower($cover), '.svg'))
        ? $cover
        : scribeFallbackCoverPath($slug);
    if (!is_file($absOf($rel))) {
        $gen = scribeEnsureFallbackCover($slug, $title);
        if ($gen !== null) $rel = $gen;
    }
    if (is_file($absOf($rel))) {
        return '<img src="' . e(feUploadUrl($rel)) . '" alt="' . e($title) . '"' . $dims
             . ' loading="' . ($eager ? 'eager' : 'lazy') . '"' . ($eager ? ' fetchpriority="high"' : '')
             . ' decoding="async" class="' . e($imgClass) . '">';
    }

    // 3) Jaring terakhir: gradient inline (seharusnya tak pernah terlihat).
    [$c1, $c2] = feGradientForSlug($slug);
    $initial = mb_strtoupper(mb_substr(trim($title) !== '' ? $title : '·', 0, 1));
    return '<div class="' . e($imgClass) . ' w-full h-full flex items-center justify-center select-none" '
         . 'style="background:linear-gradient(135deg,' . $c1 . ',' . $c2 . ')" aria-hidden="true">'
         . '<span class="font-display font-semibold text-white/90 text-4xl drop-shadow-sm">' . e($initial) . '</span>'
         . '</div>';
}

/** Pasangan warna gradient deterministik dari slug (jaring terakhir feCoverHtml). */
function feGradientForSlug(string $slug): array
{
    $palette = [
        ['#6366f1', '#8b5cf6'], ['#0ea5e9', '#6366f1'], ['#10b981', '#0ea5e9'],
        ['#f59e0b', '#ef4444'], ['#ec4899', '#8b5cf6'], ['#14b8a6', '#22c55e'],
        ['#f43f5e', '#f59e0b'], ['#3b82f6', '#06b6d4'],
    ];
    $h = crc32($slug !== '' ? $slug : 'x');
    return $palette[$h % count($palette)];
}

// ─── Halaman artikel (B-03) ───────────────────────────────────────────────

/** Kolom penuh 1 artikel published + kategori + author. Null bila tak ada. */
function feFindPublishedArticle(string $slug): ?array
{
    $sql = "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug, u.name AS author_name
            FROM articles a
            LEFT JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE a.slug = ? AND a.status = 'published' AND a.published_at <= NOW()
            LIMIT 1";
    try {
        $st = getDB()->prepare($sql);
        $st->execute([$slug]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('feFindPublishedArticle: ' . $e->getMessage());
        return null;
    }
}

/**
 * Ambil artikel by ID untuk PRATINJAU admin (status apa pun, termasuk draft).
 * Sama SELECT-nya dengan feFindPublishedArticle (join kategori+penulis) tetapi
 * TANPA filter published — hanya dipakai jalur admin ber-guard. Null bila tak ada.
 */
function feFindArticleForPreview(int $id): ?array
{
    $sql = "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug, u.name AS author_name
            FROM articles a
            LEFT JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE a.id = ? LIMIT 1";
    try {
        $st = getDB()->prepare($sql);
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('feFindArticleForPreview: ' . $e->getMessage());
        return null;
    }
}

/** Target redirect untuk old_slug (cek sebelum 404). Null bila tak ada. */
function feRedirectTarget(string $slug): ?array
{
    try {
        $st = getDB()->prepare("SELECT new_slug, type FROM redirects WHERE old_slug = ? LIMIT 1");
        $st->execute([$slug]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('feRedirectTarget: ' . $e->getMessage());
        return null;
    }
}

/** Artikel terkait: kategori sama (exclude diri), fallback berbagi tag. Maks $limit. */
function feRelatedArticles(array $a, int $limit = 3): array
{
    $selfId = (int) ($a['id'] ?? 0);
    $out = [];
    if (!empty($a['category_id'])) {
        $out = fePublishedArticles(['category_id' => (int) $a['category_id'], 'exclude_id' => $selfId, 'limit' => $limit]);
    }
    if (count($out) < $limit) {
        // Fallback: artikel berbagi tag dengan artikel ini.
        $have = array_map(fn($r) => (int) $r['id'], $out);
        $exclude = array_merge([$selfId], $have);
        $ph = implode(',', array_fill(0, count($exclude), '?'));
        $need = $limit - count($out);
        try {
            $sql = "SELECT DISTINCT a.id, a.title, a.slug, a.excerpt, a.cover_image, a.cover_is_fallback,
                           a.published_at, c.name AS cat_name, c.slug AS cat_slug, u.name AS author_name
                    FROM articles a
                    JOIN article_tags t1 ON t1.article_id = a.id
                    JOIN article_tags t2 ON t2.tag_id = t1.tag_id AND t2.article_id = ?
                    LEFT JOIN categories c ON c.id = a.category_id
                    LEFT JOIN users u ON u.id = a.author_id
                    WHERE a.status = 'published' AND a.published_at <= NOW() AND a.id NOT IN ($ph)
                    ORDER BY a.published_at DESC LIMIT " . (int) $need;
            $st = getDB()->prepare($sql);
            $st->execute(array_merge([$selfId], $exclude));
            foreach ($st->fetchAll() as $r) $out[] = $r;
        } catch (Throwable $e) {
            error_log('feRelatedArticles tag: ' . $e->getMessage());
        }
    }
    return $out;
}

/** Prev (lebih lama) & next (lebih baru) berdasar published_at. */
function fePrevNextArticles(array $a): array
{
    $res = ['prev' => null, 'next' => null];
    $pub = (string) ($a['published_at'] ?? '');
    $id  = (int) ($a['id'] ?? 0);
    if ($pub === '') return $res;
    try {
        $pdo = getDB();
        $p = $pdo->prepare("SELECT title, slug FROM articles WHERE status='published' AND published_at <= NOW()
                            AND (published_at < ? OR (published_at = ? AND id < ?)) ORDER BY published_at DESC, id DESC LIMIT 1");
        $p->execute([$pub, $pub, $id]);
        $res['prev'] = $p->fetch() ?: null;
        $n = $pdo->prepare("SELECT title, slug FROM articles WHERE status='published' AND published_at <= NOW()
                            AND (published_at > ? OR (published_at = ? AND id > ?)) ORDER BY published_at ASC, id ASC LIMIT 1");
        $n->execute([$pub, $pub, $id]);
        $res['next'] = $n->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('fePrevNextArticles: ' . $e->getMessage());
    }
    return $res;
}

/** FAQ approved [{q,a}] untuk JSON-LD FAQPage (sumber seo_ai_faq, BUKAN parse HTML). */
function feApprovedFaqs(int $articleId): array
{
    try {
        $st = getDB()->prepare("SELECT question, answer FROM seo_ai_faq WHERE article_id = ? AND approved = 1 ORDER BY sort_order, id");
        $st->execute([$articleId]);
        $out = [];
        foreach ($st->fetchAll() as $r) $out[] = ['q' => $r['question'], 'a' => $r['answer']];
        return $out;
    } catch (Throwable $e) {
        error_log('feApprovedFaqs: ' . $e->getMessage());
        return [];
    }
}
