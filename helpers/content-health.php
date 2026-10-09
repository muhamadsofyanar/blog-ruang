<?php
// Audit kesehatan konten lokal. Tidak menulis database, tidak memakai kredit AI,
// dan tidak mengubah status artikel. Skor menggabungkan ruleset SEO aktif dengan
// kelengkapan editorial yang dapat diperbaiki dari editor/Management API.

require_once __DIR__ . '/seo-rules.php';

/** Metadata label untuk level kesehatan. */
function contentHealthLevelMeta(string $level): array
{
    return [
        'healthy'  => ['label' => 'Sehat', 'color' => 'emerald'],
        'warning'  => ['label' => 'Perlu perhatian', 'color' => 'amber'],
        'critical' => ['label' => 'Kritis', 'color' => 'red'],
    ][$level] ?? ['label' => 'Belum dinilai', 'color' => 'gray'];
}

function contentHealthLevel(int $score): string
{
    if ($score >= 80) return 'healthy';
    if ($score >= 60) return 'warning';
    return 'critical';
}

/**
 * Audit satu artikel. $ruleset dapat diinjeksi agar test dan batch memakai satu
 * snapshot ruleset yang sama.
 */
function contentHealthAnalyzeArticle(array $article, ?array $ruleset = null): array
{
    $ruleset = $ruleset ?? scribeGetRuleset(false);
    $seo = scribeRunRules($ruleset, $article);
    $checks = $seo['checks'];

    $add = static function (string $id, string $label, bool $pass, string $hint, int $weight) use (&$checks): void {
        $checks[] = [
            'id' => $id, 'label' => $label, 'pass' => $pass,
            'hint' => $pass ? '' : $hint, 'weight' => $weight,
        ];
    };

    $add('category_assigned', 'Kategori sudah dipilih', !empty($article['category_id']),
        'Pilih kategori yang paling relevan.', 6);
    $add('excerpt_present', 'Ringkasan artikel tersedia', trim((string) ($article['excerpt'] ?? '')) !== '',
        'Isi ringkasan singkat artikel.', 6);
    $add('cover_present', 'Gambar utama tersedia', trim((string) ($article['cover_image'] ?? '')) !== '',
        'Tambahkan atau regenerasi gambar utama.', 6);
    $add('search_intent_present', 'Search intent sudah ditentukan',
        in_array((string) ($article['search_intent'] ?? ''), ['informational', 'transactional', 'navigational'], true),
        'Pilih search intent artikel.', 4);

    if (($article['status'] ?? '') === 'published') {
        $updated = strtotime((string) ($article['updated_at'] ?? $article['published_at'] ?? ''));
        $days = $updated ? max(0, (int) floor((time() - $updated) / 86400)) : 9999;
        $add('content_freshness', 'Artikel ditinjau dalam 180 hari terakhir', $days <= 180,
            'Tinjau ulang artikel; pembaruan terakhir ' . ($days >= 9999 ? 'tidak diketahui' : $days . ' hari lalu') . '.', 6);
    }

    $totalWeight = 0;
    $passedWeight = 0;
    $passed = 0;
    $issues = [];
    foreach ($checks as $check) {
        $weight = max(0, (int) ($check['weight'] ?? 0));
        $totalWeight += $weight;
        if (!empty($check['pass'])) {
            $passedWeight += $weight;
            $passed++;
            continue;
        }
        $issues[] = [
            'id' => (string) ($check['id'] ?? ''),
            'label' => (string) ($check['label'] ?? ''),
            'hint' => (string) ($check['hint'] ?? ''),
            'weight' => $weight,
            'priority' => $weight >= 10 ? 'high' : ($weight >= 7 ? 'medium' : 'low'),
        ];
    }

    usort($issues, static fn(array $a, array $b): int => $b['weight'] <=> $a['weight']);
    $score = $totalWeight > 0 ? (int) round($passedWeight / $totalWeight * 100) : 0;

    return [
        'id' => (int) ($article['id'] ?? 0),
        'title' => (string) ($article['title'] ?? ''),
        'slug' => (string) ($article['slug'] ?? ''),
        'status' => (string) ($article['status'] ?? ''),
        'category_id' => isset($article['category_id']) ? (int) $article['category_id'] : null,
        'category_name' => $article['category_name'] ?? null,
        'author_id' => isset($article['author_id']) ? (int) $article['author_id'] : null,
        'updated_at' => $article['updated_at'] ?? null,
        'published_at' => $article['published_at'] ?? null,
        'score' => $score,
        'level' => contentHealthLevel($score),
        'word_count' => (int) ($seo['word_count'] ?? 0),
        'passed_checks' => $passed,
        'total_checks' => count($checks),
        'issues' => $issues,
        'checks' => array_map(static fn(array $c): array => [
            'id' => (string) ($c['id'] ?? ''),
            'label' => (string) ($c['label'] ?? ''),
            'pass' => !empty($c['pass']),
            'hint' => (string) ($c['hint'] ?? ''),
            'weight' => (int) ($c['weight'] ?? 0),
        ], $checks),
    ];
}

/** Ambil satu artikel lengkap untuk audit. */
function contentHealthFindArticle(PDO $pdo, int $id, ?int $authorId = null): ?array
{
    $sql = "SELECT a.id, a.title, a.slug, a.status, a.content, a.excerpt, a.cover_image,
                   a.category_id, a.author_id, a.focus_keyword, a.search_intent,
                   a.meta_title, a.meta_description, a.published_at, a.updated_at,
                   c.name AS category_name,
                   (SELECT COUNT(*) FROM seo_ai_faq f WHERE f.article_id = a.id AND f.approved = 1) AS faq_approved_count
            FROM articles a LEFT JOIN categories c ON c.id = a.category_id
            WHERE a.id = ?" . ($authorId !== null ? ' AND a.author_id = ?' : '') . ' LIMIT 1';
    $st = $pdo->prepare($sql);
    $args = [$id];
    if ($authorId !== null) $args[] = $authorId;
    $st->execute($args);
    $row = $st->fetch();
    return $row ?: null;
}

function contentHealthCachePath(): string
{
    return defined('CONTENT_HEALTH_CACHE_PATH')
        ? (string) CONTENT_HEALTH_CACHE_PATH
        : __DIR__ . '/../cache/content-health.php';
}

function contentHealthCacheLoad(): array
{
    $raw = @file_get_contents(contentHealthCachePath());
    if (is_string($raw) && str_starts_with($raw, '<?php')) {
        $lineEnd = strpos($raw, "\n");
        $raw = $lineEnd === false ? '' : substr($raw, $lineEnd + 1);
    }
    $data = $raw !== false ? json_decode($raw, true) : null;
    return is_array($data) && is_array($data['items'] ?? null) ? $data['items'] : [];
}

/** Simpan cache atomik. Kegagalan cache tidak boleh menggagalkan dashboard/API. */
function contentHealthCacheSave(array $items): void
{
    $path = contentHealthCachePath();
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) return;
    try { $suffix = bin2hex(random_bytes(4)); }
    catch (Throwable $e) { return; }
    $tmp = $path . '.tmp.' . $suffix;
    $json = json_encode(['saved_at' => time(), 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $protected = "<?php http_response_code(404); exit; ?>\n" . $json;
    if ($json === false || @file_put_contents($tmp, $protected, LOCK_EX) === false) return;
    if (!@rename($tmp, $path)) @unlink($tmp);
}

/**
 * Scan artikel dengan filter, hitung ringkasan seluruh hasil, lalu paginasi di
 * PHP karena filter level bergantung pada hasil audit konten.
 */
function contentHealthScan(PDO $pdo, array $filters = [], int $limit = 20, int $offset = 0, ?array $ruleset = null, bool $useCache = true): array
{
    $where = [];
    $args = [];
    $status = trim((string) ($filters['status'] ?? ''));
    $categoryId = (int) ($filters['category_id'] ?? 0);
    $authorId = isset($filters['author_id']) ? (int) $filters['author_id'] : 0;
    if ($status !== '') { $where[] = 'a.status = ?'; $args[] = $status; }
    if ($categoryId > 0) { $where[] = 'a.category_id = ?'; $args[] = $categoryId; }
    if ($authorId > 0) { $where[] = 'a.author_id = ?'; $args[] = $authorId; }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $st = $pdo->prepare(
        "SELECT a.id, a.title, a.slug, a.status, a.content, a.excerpt, a.cover_image,
                a.category_id, a.author_id, a.focus_keyword, a.search_intent,
                a.meta_title, a.meta_description, a.published_at, a.updated_at,
                c.name AS category_name,
                (SELECT COUNT(*) FROM seo_ai_faq f WHERE f.article_id = a.id AND f.approved = 1) AS faq_approved_count
         FROM articles a LEFT JOIN categories c ON c.id = a.category_id" . $whereSql
    );
    $st->execute($args);

    $ruleset = $ruleset ?? scribeGetRuleset(false);
    $rulesetVersion = (int) ($ruleset['version'] ?? 0);
    $cache = $useCache ? contentHealthCacheLoad() : [];
    $cacheChanged = false;
    $all = [];
    $summary = ['total' => 0, 'healthy' => 0, 'warning' => 0, 'critical' => 0, 'average_score' => 0];
    $scoreTotal = 0;
    while ($row = $st->fetch()) {
        $cacheKey = (string) (int) $row['id'];
        $signature = hash('sha256', implode('|', [
            (string) ($row['updated_at'] ?? ''), (string) ($row['category_name'] ?? ''),
            (string) ($row['faq_approved_count'] ?? 0), (string) $rulesetVersion, date('Y-m-d'),
        ]));
        $cached = $cache[$cacheKey] ?? null;
        if (is_array($cached) && hash_equals((string) ($cached['signature'] ?? ''), $signature)
            && is_array($cached['audit'] ?? null)) {
            $audit = $cached['audit'];
        } else {
            $audit = contentHealthAnalyzeArticle($row, $ruleset);
            if ($useCache) {
                $cache[$cacheKey] = ['signature' => $signature, 'audit' => $audit];
                $cacheChanged = true;
            }
        }
        $summary['total']++;
        $summary[$audit['level']]++;
        $scoreTotal += $audit['score'];
        $all[] = $audit;
    }
    if ($useCache && $cacheChanged) contentHealthCacheSave($cache);
    $summary['average_score'] = $summary['total'] > 0 ? (int) round($scoreTotal / $summary['total']) : 0;

    usort($all, static function (array $a, array $b): int {
        $byScore = $a['score'] <=> $b['score'];
        if ($byScore !== 0) return $byScore;
        return strcmp((string) $b['updated_at'], (string) $a['updated_at']);
    });

    $level = trim((string) ($filters['level'] ?? ''));
    $matched = $level === '' ? $all : array_values(array_filter($all, static fn(array $a): bool => $a['level'] === $level));
    return [
        'summary' => $summary,
        'articles' => array_slice($matched, max(0, $offset), max(1, $limit)),
        'total' => count($matched),
        'ruleset_version' => $rulesetVersion,
    ];
}

/** Ringkasan metadata murah untuk kartu pada dashboard utama. */
function contentHealthQuickSummary(PDO $pdo, ?int $authorId = null): array
{
    try {
        $where = $authorId !== null ? ' WHERE author_id = ?' : '';
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN category_id IS NULL
                                  OR TRIM(COALESCE(focus_keyword, '')) = ''
                                  OR TRIM(COALESCE(meta_title, '')) = ''
                                  OR TRIM(COALESCE(meta_description, '')) = ''
                                  OR TRIM(COALESCE(excerpt, '')) = ''
                                  OR TRIM(COALESCE(cover_image, '')) = ''
                                THEN 1 ELSE 0 END) AS needs_review
                FROM articles" . $where;
        $st = $pdo->prepare($sql);
        $st->execute($authorId !== null ? [$authorId] : []);
        $row = $st->fetch() ?: [];
        return ['total' => (int) ($row['total'] ?? 0), 'needs_review' => (int) ($row['needs_review'] ?? 0)];
    } catch (Throwable $e) {
        return ['total' => 0, 'needs_review' => 0];
    }
}
