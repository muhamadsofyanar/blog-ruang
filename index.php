<?php
// ─── Redirect ke installer bila belum dikonfigurasi ───────────
if (!file_exists(__DIR__ . '/config.php')) {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    header('Location: ' . $base . '/installer/');
    exit;
}

require_once __DIR__ . '/bootstrap.php';

// ─── Hitung base path dari APP_URL ────────────────────────────
$basePath    = rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/');
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
    $requestPath = substr($requestPath, strlen($basePath));
}
$requestPath = rtrim($requestPath, '/') ?: '/';

// ─── Tabel routing ────────────────────────────────────────────
// Format: pattern => [file, role, [param => grup]]
//   role: null (publik) | 'staff' (admin+writer) | 'admin'
$routes = [
    '#^/$#'                              => ['pages/public/home.php',          null, []],
    '#^/blog$#'                          => ['pages/public/blog.php',          null, []],

    // ── Frontend publik (theme-default, Track B) ──────────────
    '#^/artikel/([^/]+)$#'               => ['pages/public/article.php',       null, ['slug' => 1]],
    '#^/kategori/([^/]+)$#'              => ['pages/public/listing.php',       null, ['cat' => 1]],
    '#^/tag/([^/]+)$#'                   => ['pages/public/listing.php',       null, ['tag' => 1]],
    '#^/penulis/([^/]+)$#'              => ['pages/public/author.php',        null, ['slug' => 1]],
    '#^/search$#'                        => ['pages/public/listing.php',       null, []],
    '#^/sitemap\.xml$#'                  => ['pages/public/sitemap.php',       null, []],
    '#^/robots\.txt$#'                   => ['pages/public/robots.php',        null, []],
    '#^/feed$#'                          => ['pages/public/feed.php',          null, []],
    // File verifikasi IndexNow ({key}.txt). Hanya cocok bila = key tersimpan.
    '#^/([A-Za-z0-9\-]{8,128})\.txt$#'   => ['pages/public/indexnow-key.php',  null, ['key' => 1]],
    '#^/actions/public/subscribe$#'      => ['actions/public/subscribe.php',   null, []],
    '#^/actions/public/lead-magnet$#'    => ['actions/public/lead-magnet.php', null, []],
    '#^/unsubscribe/([a-fA-F0-9]{64})$#' => ['pages/public/unsubscribe.php',  null, ['token' => 1]],
    '#^/lead-magnet/([^/]+)$#'            => ['pages/public/lead-magnet.php',  null, ['slug' => 1]],

    // ── Auth ──────────────────────────────────────────────────
    '#^/login$#'                         => ['pages/login.php',                null, []],
    '#^/logout$#'                        => ['actions/auth/logout.php',        null, []],
    '#^/actions/auth/login$#'            => ['actions/auth/login.php',         null, []],

    // ── Enforcement (bypass — identitas Averion) ──────────────
    '#^/license-expired$#'               => ['pages/enforcement/blocked.php',  null, []],

    // ── Admin: area staff (admin + writer) ────────────────────
    '#^/admin$#'                         => ['pages/admin/dashboard.php',      'staff', []],
    '#^/admin/articles$#'                => ['pages/admin/articles.php',       'staff', []],
    '#^/admin/content-health$#'          => ['pages/admin/content-health.php', 'staff', []],
    '#^/admin/articles/new$#'            => ['pages/admin/article-form.php',   'staff', []],
    '#^/admin/articles/(\d+)/preview$#'  => ['pages/admin/article-preview.php', 'staff', ['id' => 1]],
    '#^/admin/articles/(\d+)$#'          => ['pages/admin/article-form.php',   'staff', ['id' => 1]],
    '#^/admin/license$#'                 => ['pages/admin/license.php',        'staff', []],
    '#^/admin/docs$#'                    => ['pages/admin/docs.php',           'staff', []],
    '#^/admin/profile$#'                 => ['pages/admin/profile.php',        'staff', []],

    // ── Admin: admin-only ─────────────────────────────────────
    '#^/admin/users$#'                   => ['pages/admin/users.php',          'admin', []],
    '#^/admin/settings$#'                => ['pages/admin/settings.php',       'admin', []],
    '#^/admin/rebrand$#'                 => ['pages/admin/rebrand.php',        'admin', []],
    '#^/admin/appearance$#'              => ['pages/admin/appearance.php',     'admin', []],
    '#^/admin/integrations$#'            => ['pages/admin/integrations.php',   'admin', []],
    '#^/admin/lead-magnets$#'             => ['pages/admin/lead-magnets.php',    'admin', []],
    '#^/admin/subscribers$#'              => ['pages/admin/subscribers.php',     'admin', []],
    '#^/admin/email-sequences$#'          => ['pages/admin/email-sequences.php', 'admin', []],
    '#^/admin/email-sequence-steps$#'     => ['pages/admin/email-sequence-steps.php', 'admin', []],
    '#^/admin/biolink$#'                 => ['pages/admin/biolink.php',        'admin', []],
    '#^/admin/categories$#'              => ['pages/admin/categories.php',     'admin', []],
    '#^/admin/tags$#'                    => ['pages/admin/tags.php',           'admin', []],
    '#^/admin/redirects$#'               => ['pages/admin/redirects.php',      'admin', []],
    '#^/admin/credits$#'                 => ['pages/admin/credits.php',        'admin', []],
    '#^/admin/update$#'                  => ['pages/admin/update.php',         'admin', []],
    '#^/admin/license-activate$#'        => ['pages/admin/license-activate.php', 'admin', []],

    // ── Actions: artikel (staff — cek kepemilikan di dalam) ───
    '#^/actions/admin/save-article$#'     => ['actions/admin/save-article.php',     'staff', []],
    '#^/actions/admin/delete-article$#'   => ['actions/admin/delete-article.php',   'staff', []],
    '#^/actions/admin/toggle-pin$#'       => ['actions/admin/toggle-pin.php',       'admin', []],
    '#^/actions/admin/autosave-article$#' => ['actions/admin/autosave-article.php', 'staff', []],
    '#^/actions/admin/upload-image$#'     => ['actions/admin/upload-image.php',     'staff', []],
    '#^/actions/admin/tag-suggest$#'      => ['actions/admin/tag-suggest.php',      'staff', []],
    '#^/actions/admin/ai-research$#'      => ['actions/admin/ai-research.php',      'staff', []],
    '#^/actions/admin/ai-outline$#'       => ['actions/admin/ai-outline.php',       'staff', []],
    '#^/actions/admin/ai-draft-section$#' => ['actions/admin/ai-draft-section.php', 'staff', []],
    '#^/actions/admin/ai-meta$#'          => ['actions/admin/ai-meta.php',          'staff', []],
    '#^/actions/admin/ai-check-rules$#'   => ['actions/admin/ai-check-rules.php',   'staff', []],
    '#^/actions/admin/ai-analysis$#'      => ['actions/admin/ai-analysis.php',      'staff', []],
    '#^/actions/admin/ai-faq$#'           => ['actions/admin/ai-faq.php',           'staff', []],
    '#^/actions/admin/faq-save$#'         => ['actions/admin/faq-save.php',         'staff', []],

    // ── Actions: AI / Settings / Kredit (admin) ───────────────
    '#^/actions/admin/save-ai-settings$#'  => ['actions/admin/save-ai-settings.php',  'admin', []],
    '#^/actions/admin/save-rebrand$#'      => ['actions/admin/save-rebrand.php',      'admin', []],
    '#^/actions/admin/regenerate-covers$#' => ['actions/admin/regenerate-covers.php', 'admin', []],
    '#^/actions/admin/optimize-images$#'    => ['actions/admin/optimize-images.php',    'admin', []],
    '#^/actions/admin/save-appearance$#'   => ['actions/admin/save-appearance.php',   'admin', []],
    '#^/actions/admin/save-ingest$#'       => ['actions/admin/save-ingest.php',       'admin', []],
    '#^/actions/admin/save-meta-pixel$#'   => ['actions/admin/save-meta-pixel.php',   'admin', []],
    '#^/actions/admin/ai-internal-links$#' => ['actions/admin/ai-internal-links.php', 'staff', []],
    '#^/actions/admin/ai-alt-text$#'       => ['actions/admin/ai-alt-text.php',       'staff', []],
    '#^/actions/admin/save-profile$#'      => ['actions/admin/save-profile.php',      'staff', []],
    '#^/actions/admin/save-gsc$#'          => ['actions/admin/save-gsc.php',          'admin', []],
    '#^/actions/admin/gsc-refresh$#'       => ['actions/admin/gsc-refresh.php',       'admin', []],
    '#^/actions/admin/save-indexnow$#'     => ['actions/admin/save-indexnow.php',     'admin', []],
    '#^/actions/admin/indexnow-test$#'     => ['actions/admin/indexnow-test.php',     'admin', []],
    '#^/actions/admin/save-mailketing$#'   => ['actions/admin/save-mailketing.php',   'admin', []],
    '#^/actions/admin/fetch-mailketing-lists$#' => ['actions/admin/fetch-mailketing-lists.php', 'admin', []],
    '#^/actions/admin/test-mailketing$#'   => ['actions/admin/test-mailketing.php',   'admin', []],
    '#^/actions/admin/save-lead-magnet$#'  => ['actions/admin/save-lead-magnet.php',  'admin', []],
    '#^/actions/admin/save-lead-success$#' => ['actions/admin/save-lead-success.php', 'admin', []],
    '#^/actions/admin/delete-lead-magnet$#' => ['actions/admin/delete-lead-magnet.php', 'admin', []],
    '#^/actions/admin/save-subscriber$#' => ['actions/admin/save-subscriber.php', 'admin', []],
    '#^/actions/admin/delete-subscriber$#' => ['actions/admin/delete-subscriber.php', 'admin', []],
    '#^/actions/admin/save-email-sequence$#' => ['actions/admin/save-email-sequence.php', 'admin', []],
    '#^/actions/admin/toggle-email-sequence$#' => ['actions/admin/toggle-email-sequence.php', 'admin', []],
    '#^/actions/admin/delete-email-sequence$#' => ['actions/admin/delete-email-sequence.php', 'admin', []],
    '#^/actions/admin/save-email-sequence-step$#' => ['actions/admin/save-email-sequence-step.php', 'admin', []],
    '#^/actions/admin/email-preview$#'     => ['actions/admin/email-preview.php',       'admin', []],
    '#^/actions/admin/delete-email-sequence-step$#' => ['actions/admin/delete-email-sequence-step.php', 'admin', []],
    '#^/actions/admin/process-email-sequences$#' => ['actions/admin/process-email-sequences.php', 'admin', []],
    '#^/actions/admin/save-article-cta$#'  => ['actions/admin/save-article-cta.php',  'admin', []],
    '#^/actions/admin/ingest-token$#'      => ['actions/admin/ingest-token.php',      'admin', []],
    '#^/actions/admin/bio-save-profile$#'  => ['actions/admin/bio-save-profile.php',  'admin', []],
    '#^/actions/admin/bio-save-block$#'    => ['actions/admin/bio-save-block.php',    'admin', []],
    '#^/actions/admin/bio-delete-block$#'  => ['actions/admin/bio-delete-block.php',  'admin', []],
    '#^/actions/admin/bio-reorder$#'       => ['actions/admin/bio-reorder.php',       'admin', []],
    '#^/actions/admin/save-brand-voice$#'  => ['actions/admin/save-brand-voice.php',  'admin', []],
    '#^/actions/admin/save-byok$#'         => ['actions/admin/save-byok.php',         'admin', []],
    '#^/actions/admin/delete-byok$#'       => ['actions/admin/delete-byok.php',       'admin', []],
    '#^/actions/admin/test-gateway$#'      => ['actions/admin/test-gateway.php',      'admin', []],
    '#^/actions/admin/refresh-balance$#'   => ['actions/admin/refresh-balance.php',   'admin', []],

    // ── Actions: taksonomi (admin) ────────────────────────────
    '#^/actions/admin/save-category$#'    => ['actions/admin/save-category.php',    'admin', []],
    '#^/actions/admin/delete-category$#'  => ['actions/admin/delete-category.php',  'admin', []],
    '#^/actions/admin/save-tag$#'         => ['actions/admin/save-tag.php',         'admin', []],
    '#^/actions/admin/delete-tag$#'       => ['actions/admin/delete-tag.php',       'admin', []],

    // ── Actions: self-update (admin) ──────────────────────────
    '#^/actions/admin/update-check$#'     => ['actions/admin/update-check.php',     'admin', []],
    '#^/actions/admin/update-apply$#'     => ['actions/admin/update-apply.php',     'admin', []],

    // ── Actions: redirects + users (admin) ────────────────────
    '#^/actions/admin/save-redirect$#'    => ['actions/admin/save-redirect.php',    'admin', []],
    '#^/actions/admin/delete-redirect$#'  => ['actions/admin/delete-redirect.php',  'admin', []],
    '#^/actions/admin/save-user$#'        => ['actions/admin/save-user.php',        'admin', []],
    '#^/actions/admin/delete-user$#'      => ['actions/admin/delete-user.php',      'admin', []],
    '#^/actions/admin/toggle-user$#'      => ['actions/admin/toggle-user.php',      'admin', []],

    // ── Actions: admin-only (lisensi) ─────────────────────────
    '#^/actions/admin/activate-license$#'   => ['actions/admin/activate-license.php',   'admin', []],
    '#^/actions/admin/revalidate-license$#' => ['actions/admin/revalidate-license.php', 'admin', []],
];

foreach ($routes as $pattern => [$file, $role, $params]) {
    if (!preg_match($pattern, $requestPath, $m)) {
        continue;
    }

    // ── Middleware role guard per-route (front controller) ─────
    if ($role === 'staff') {
        requireStaff();
    } elseif ($role === 'admin') {
        requireAdmin();
    }

    foreach ($params as $key => $idx) {
        $_GET[$key] = $m[$idx];
    }

    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        require $fullPath;
    } else {
        http_response_code(404);
        echo '404 — Halaman tidak ditemukan.';
    }
    exit;
}

// ─── Tidak ada route cocok → 404 ──────────────────────────────
http_response_code(404);
$page404 = __DIR__ . '/pages/404.php';
file_exists($page404) ? require $page404 : print('404 — Halaman tidak ditemukan.');
