<?php
// Management API kesehatan konten.
// GET  : audit/ringkasan artikel (cukup ingest_enabled).
// POST : terapkan hasil optimasi dan opsional publish (wajib ingest_manage_enabled).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/ingest.php';
require_once __DIR__ . '/../helpers/content-health.php';
require_once __DIR__ . '/../helpers/sanitize.php';
require_once __DIR__ . '/../helpers/draft.php';
require_once __DIR__ . '/../helpers/page-cache.php';
require_once __DIR__ . '/../helpers/sitemap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    ingestApiSend(405, ['ok' => false, 'error' => 'Metode tidak diizinkan. Gunakan GET atau POST.']);
}
if ($method === 'POST') {
    $ctype = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    if (stripos($ctype, 'application/json') === false) {
        ingestApiSend(415, ['ok' => false, 'error' => 'Content-Type harus application/json.']);
    }
}
ingestApiAuthorize($method === 'POST');
$pdo = getDB();

// ─── POST: optimasi + publish opsional ──────────────────────────
if ($method === 'POST') {
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) ingestApiSend(400, ['ok' => false, 'error' => 'Body JSON tidak valid.']);

    $allowed = [
        'article_id', 'expected_updated_at', 'title', 'content_html', 'excerpt',
        'focus_keyword', 'related_keywords', 'search_intent', 'meta_title',
        'meta_description', 'category_id', 'tags', 'faq', 'publish', 'dry_run',
    ];
    $unknown = array_values(array_diff(array_keys($in), $allowed));
    if ($unknown) {
        ingestApiSend(422, ['ok' => false, 'error' => 'Field tidak diizinkan: ' . implode(', ', $unknown) . '.']);
    }

    $aid = 0;
    if (is_int($in['article_id'] ?? null)) $aid = $in['article_id'];
    elseif (is_string($in['article_id'] ?? null) && ctype_digit($in['article_id'])) $aid = (int) $in['article_id'];
    if ($aid < 1) ingestApiSend(422, ['ok' => false, 'error' => 'article_id wajib integer > 0.']);

    $expectedUpdatedAt = is_string($in['expected_updated_at'] ?? null) ? trim($in['expected_updated_at']) : '';
    if ($expectedUpdatedAt === '') {
        ingestApiSend(422, ['ok' => false, 'error' => 'expected_updated_at wajib diisi dari respons GET terbaru.']);
    }
    if (array_key_exists('publish', $in) && !is_bool($in['publish'])) {
        ingestApiSend(422, ['ok' => false, 'error' => 'publish wajib boolean.']);
    }
    if (array_key_exists('dry_run', $in) && !is_bool($in['dry_run'])) {
        ingestApiSend(422, ['ok' => false, 'error' => 'dry_run wajib boolean.']);
    }
    $publish = $in['publish'] ?? false;
    $dryRun = $in['dry_run'] ?? false;

    $st = $pdo->prepare(
        "SELECT a.id, a.title, a.slug, a.status, a.content, a.excerpt, a.cover_image,
                a.category_id, a.author_id, a.focus_keyword, a.related_keywords,
                a.search_intent, a.meta_title, a.meta_description, a.published_at,
                a.updated_at, c.name AS category_name,
                (SELECT COUNT(*) FROM seo_ai_faq f WHERE f.article_id = a.id AND f.approved = 1) AS faq_approved_count
         FROM articles a LEFT JOIN categories c ON c.id = a.category_id
         WHERE a.id = ? LIMIT 1"
    );
    $st->execute([$aid]);
    $article = $st->fetch();
    if (!$article) ingestApiSend(404, ['ok' => false, 'error' => 'Artikel tidak ditemukan.']);
    if (!hash_equals((string) $article['updated_at'], $expectedUpdatedAt)) {
        ingestApiSend(409, [
            'ok' => false, 'error' => 'Artikel telah berubah. Ambil ulang data sebelum mengirim optimasi.',
            'current_updated_at' => $article['updated_at'],
        ]);
    }

    $clean = static function ($value): string {
        if (!is_scalar($value) && $value !== null) {
            ingestApiSend(422, ['ok' => false, 'error' => 'Field teks wajib berupa string.']);
        }
        return trim((string) preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', strip_tags((string) $value)
        ));
    };
    $set = [];
    $changed = [];
    $candidate = $article;
    $apply = static function (string $column, string $responseField, $value) use (&$set, &$changed, &$candidate, $article): void {
        if ((string) $value === (string) ($article[$column] ?? '')) return;
        $set[$column] = $value;
        $candidate[$column] = $value;
        $changed[] = $responseField;
    };

    if (array_key_exists('title', $in)) {
        $value = mb_substr($clean($in['title']), 0, 255);
        if ($value === '') ingestApiSend(422, ['ok' => false, 'error' => 'title tidak boleh kosong.']);
        $apply('title', 'title', $value);
    }
    if (array_key_exists('content_html', $in)) {
        if (!is_string($in['content_html'])) ingestApiSend(422, ['ok' => false, 'error' => 'content_html wajib string.']);
        if (strlen($in['content_html']) > 5 * 1024 * 1024) {
            ingestApiSend(413, ['ok' => false, 'error' => 'content_html terlalu besar (maksimal 5 MB).']);
        }
        $value = sanitizeArticleHtml($in['content_html']);
        if (trim(strip_tags($value)) === '') {
            ingestApiSend(422, ['ok' => false, 'error' => 'content_html kosong setelah disanitasi.']);
        }
        if ($value !== (string) $article['content']) {
            $set['content'] = $value;
            $set['content_snapshot'] = $article['content'];
            $candidate['content'] = $value;
            $changed[] = 'content_html';
        }
    }
    if (array_key_exists('excerpt', $in)) {
        $apply('excerpt', 'excerpt', mb_substr($clean($in['excerpt']), 0, 500));
    }
    if (array_key_exists('focus_keyword', $in)) {
        $value = mb_substr($clean($in['focus_keyword']), 0, 190);
        if ($value === '') ingestApiSend(422, ['ok' => false, 'error' => 'focus_keyword tidak boleh kosong.']);
        $apply('focus_keyword', 'focus_keyword', $value);
    }
    if (array_key_exists('related_keywords', $in)) {
        $value = $in['related_keywords'];
        if (is_array($value)) {
            foreach ($value as $keyword) {
                if (!is_scalar($keyword)) ingestApiSend(422, ['ok' => false, 'error' => 'related_keywords wajib array teks.']);
            }
            $value = implode(', ', array_map('strval', $value));
        }
        $apply('related_keywords', 'related_keywords', mb_substr($clean($value), 0, 1000));
    }
    if (array_key_exists('search_intent', $in)) {
        $value = is_string($in['search_intent']) ? trim($in['search_intent']) : '';
        if (!in_array($value, ['informational', 'transactional', 'navigational'], true)) {
            ingestApiSend(422, ['ok' => false, 'error' => 'search_intent tidak valid.']);
        }
        $apply('search_intent', 'search_intent', $value);
    }
    if (array_key_exists('meta_title', $in)) {
        $value = $clean($in['meta_title']);
        $length = mb_strlen($value);
        if ($length < 45 || $length > 60) {
            ingestApiSend(422, ['ok' => false, 'error' => "meta_title harus 45-60 karakter (kirim {$length})."]);
        }
        $apply('meta_title', 'meta_title', $value);
    }
    if (array_key_exists('meta_description', $in)) {
        $value = $clean($in['meta_description']);
        $length = mb_strlen($value);
        if ($length < 120 || $length > 160) {
            ingestApiSend(422, ['ok' => false, 'error' => "meta_description harus 120-160 karakter (kirim {$length})."]);
        }
        $apply('meta_description', 'meta_description', $value);
    }
    if (array_key_exists('category_id', $in)) {
        $categoryId = is_int($in['category_id']) ? $in['category_id']
            : (is_string($in['category_id']) && ctype_digit($in['category_id']) ? (int) $in['category_id'] : 0);
        if ($categoryId < 1) ingestApiSend(422, ['ok' => false, 'error' => 'category_id wajib integer > 0.']);
        $cs = $pdo->prepare('SELECT id, name FROM categories WHERE id = ? LIMIT 1');
        $cs->execute([$categoryId]);
        $category = $cs->fetch();
        if (!$category) ingestApiSend(422, ['ok' => false, 'error' => 'category_id tidak ditemukan.']);
        $apply('category_id', 'category_id', $categoryId);
        $candidate['category_name'] = $category['name'];
    }

    $tagsChanged = false;
    $afterTags = [];
    if (array_key_exists('tags', $in)) {
        if (!is_array($in['tags'])) ingestApiSend(422, ['ok' => false, 'error' => 'tags wajib array.']);
        if (count($in['tags']) > 30) ingestApiSend(422, ['ok' => false, 'error' => 'tags maksimal 30 item.']);
        foreach ($in['tags'] as $tag) {
            if (!is_scalar($tag)) ingestApiSend(422, ['ok' => false, 'error' => 'Setiap tag wajib teks.']);
            $name = mb_substr($clean($tag), 0, 80);
            if ($name !== '') $afterTags[mb_strtolower($name)] = $name;
        }
        $afterTags = array_values($afterTags);
        $ts = $pdo->prepare('SELECT t.name FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ? ORDER BY t.name');
        $ts->execute([$aid]);
        $beforeTags = $ts->fetchAll(PDO::FETCH_COLUMN);
        $beforeNormalized = array_map('mb_strtolower', $beforeTags); sort($beforeNormalized);
        $afterNormalized = array_map('mb_strtolower', $afterTags); sort($afterNormalized);
        $tagsChanged = $beforeNormalized !== $afterNormalized;
        if ($tagsChanged) $changed[] = 'tags';
    }

    // FAQ dari Management API memakai tabel dan blok terkelola yang sama dengan
    // editor admin. Seluruh item API selalu approved; array kosong menghapus FAQ.
    $faqRowsChanged = false;
    $afterFaq = [];
    if (array_key_exists('faq', $in)) {
        if (!is_array($in['faq']) || !array_is_list($in['faq'])) {
            ingestApiSend(422, ['ok' => false, 'error' => 'faq wajib berupa array.']);
        }
        if (count($in['faq']) > 20) {
            ingestApiSend(422, ['ok' => false, 'error' => 'faq maksimal 20 item.']);
        }
        $seenQuestions = [];
        foreach ($in['faq'] as $index => $item) {
            if (!is_array($item)) {
                ingestApiSend(422, ['ok' => false, 'error' => 'faq item ke-' . ($index + 1) . ' wajib object.']);
            }
            $itemUnknown = array_values(array_diff(array_keys($item), ['question', 'answer', 'q', 'a']));
            if ($itemUnknown) {
                ingestApiSend(422, ['ok' => false, 'error' => 'Field FAQ tidak diizinkan: ' . implode(', ', $itemUnknown) . '.']);
            }
            if ((array_key_exists('question', $item) && array_key_exists('q', $item))
                || (array_key_exists('answer', $item) && array_key_exists('a', $item))) {
                ingestApiSend(422, ['ok' => false, 'error' => 'Gunakan question/answer atau q/a, jangan keduanya pada item FAQ yang sama.']);
            }
            $questionRaw = $item['question'] ?? ($item['q'] ?? null);
            $answerRaw = $item['answer'] ?? ($item['a'] ?? null);
            $question = $clean($questionRaw);
            $answer = $clean($answerRaw);
            if ($question === '' || $answer === '') {
                ingestApiSend(422, ['ok' => false, 'error' => 'FAQ item ke-' . ($index + 1) . ' wajib memiliki question dan answer.']);
            }
            if (mb_strlen($question) > 255) {
                ingestApiSend(422, ['ok' => false, 'error' => 'FAQ question item ke-' . ($index + 1) . ' maksimal 255 karakter.']);
            }
            if (mb_strlen($answer) > 5000) {
                ingestApiSend(422, ['ok' => false, 'error' => 'FAQ answer item ke-' . ($index + 1) . ' maksimal 5.000 karakter.']);
            }
            $questionKey = mb_strtolower($question);
            if (isset($seenQuestions[$questionKey])) {
                ingestApiSend(422, ['ok' => false, 'error' => 'FAQ memiliki question duplikat: ' . $question . '.']);
            }
            $seenQuestions[$questionKey] = true;
            $afterFaq[] = ['question' => $question, 'answer' => $answer, 'approved' => 1, 'sort_order' => $index];
        }

        $faqQuery = $pdo->prepare(
            'SELECT question, answer, approved, sort_order FROM seo_ai_faq WHERE article_id = ? ORDER BY sort_order, id'
        );
        $faqQuery->execute([$aid]);
        $beforeFaq = array_map(static fn(array $faq): array => [
            'question' => (string) $faq['question'], 'answer' => (string) $faq['answer'],
            'approved' => (int) $faq['approved'], 'sort_order' => (int) $faq['sort_order'],
        ], $faqQuery->fetchAll());
        $faqRowsChanged = $beforeFaq !== $afterFaq;

        $approvedHtml = '';
        foreach ($afterFaq as $faq) {
            $approvedHtml .= '<h3>' . htmlspecialchars($faq['question'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</h3><p>' . htmlspecialchars($faq['answer'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
        $faqInner = $approvedHtml !== '' ? sanitizeArticleHtml('<h2>FAQ</h2>' . $approvedHtml) : '';
        $faqContent = faqUpsertBlock((string) $candidate['content'], $faqInner);
        $faqContentChanged = $faqContent !== (string) $candidate['content'];
        if ($faqContentChanged) {
            $set['content'] = $faqContent;
            $candidate['content'] = $faqContent;
        }
        $candidate['faq_approved_count'] = count($afterFaq);
        if ($faqRowsChanged || $faqContentChanged) $changed[] = 'faq';
    }

    $publishAt = date('Y-m-d H:i:s');
    if ($publish) {
        if ($article['status'] !== 'published') {
            $set['status'] = 'published';
            $candidate['status'] = 'published';
            $changed[] = 'status';
        }
        $publishedTimestamp = strtotime((string) ($article['published_at'] ?? ''));
        if ($article['status'] !== 'published' || !$publishedTimestamp || $publishedTimestamp > time()) {
            $set['published_at'] = $publishAt;
            $candidate['published_at'] = $publishAt;
            $changed[] = 'published_at';
        }
    }

    $newUpdatedAt = date('Y-m-d H:i:s');
    if ($newUpdatedAt <= $expectedUpdatedAt) {
        $newUpdatedAt = date('Y-m-d H:i:s', strtotime($expectedUpdatedAt) + 1);
    }
    $candidate['updated_at'] = $newUpdatedAt;

    $ruleset = scribeGetRuleset(false);
    if (!$changed) {
        $health = contentHealthAnalyzeArticle($article, $ruleset);
        ingestApiSend(200, [
            'ok' => true, 'dry_run' => $dryRun, 'article_id' => $aid,
            'changed' => [], 'status' => $article['status'], 'published' => $article['status'] === 'published',
            'updated_at' => $article['updated_at'], 'health_before' => $health, 'health_after' => $health,
            'faq_count' => (int) ($article['faq_approved_count'] ?? 0),
            'edit_url' => '/admin/articles/' . $aid,
            'public_url' => $article['status'] === 'published' ? '/artikel/' . $article['slug'] : null,
        ]);
    }

    $healthBefore = contentHealthAnalyzeArticle($article, $ruleset);
    $healthAfter = contentHealthAnalyzeArticle($candidate, $ruleset);
    if ($candidate['status'] === 'published' && $healthAfter['score'] < 80) {
        ingestApiSend(422, [
            'ok' => false,
            'error' => 'Artikel published wajib memiliki skor kesehatan minimal 80 sebelum perubahan diterapkan.',
            'required_score' => 80,
            'health_after' => $healthAfter,
        ]);
    }

    if (!$dryRun) {
        try {
            $pdo->beginTransaction();
            $set['updated_at'] = $newUpdatedAt;
            $columns = implode(', ', array_map(static fn(string $column): string => $column . ' = ?', array_keys($set)));
            $values = array_values($set);
            $values[] = $aid;
            $values[] = $expectedUpdatedAt;
            $update = $pdo->prepare("UPDATE articles SET {$columns} WHERE id = ? AND updated_at = ?");
            $update->execute($values);
            if ($update->rowCount() !== 1) throw new RuntimeException('__content_health_conflict__');
            if ($tagsChanged) {
                $pdo->prepare('DELETE FROM article_tags WHERE article_id = ?')->execute([$aid]);
                if ($afterTags) ingestSyncTags($pdo, $aid, $afterTags);
            }
            if ($faqRowsChanged) {
                $pdo->prepare('DELETE FROM seo_ai_faq WHERE article_id = ?')->execute([$aid]);
                if ($afterFaq) {
                    $faqInsert = $pdo->prepare(
                        'INSERT INTO seo_ai_faq (article_id, question, answer, approved, sort_order) VALUES (?, ?, ?, 1, ?)'
                    );
                    foreach ($afterFaq as $faq) {
                        $faqInsert->execute([$aid, $faq['question'], $faq['answer'], $faq['sort_order']]);
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getMessage() === '__content_health_conflict__') {
                ingestApiSend(409, ['ok' => false, 'error' => 'Artikel berubah saat optimasi diproses. Ambil ulang data dan coba lagi.']);
            }
            error_log('api/content-health optimize: ' . $e->getMessage());
            ingestLog('500 optimasi-konten-gagal id=' . $aid);
            ingestApiSend(500, ['ok' => false, 'error' => 'Gagal menyimpan hasil optimasi artikel.']);
        }

        try {
            if ($article['status'] === 'published' || $candidate['status'] === 'published') {
                scribeGenerateSitemap();
            }
            // Optimasi dapat mengubah isi, kategori, dan tag sekaligus. Flush
            // menyeluruh mencegah arsip kategori/tag lama tertinggal di cache.
            pageCacheFlushAll();
            // IndexNow: beri tahu mesin pencari saat artikel live (non-fatal).
            if ($candidate['status'] === 'published') {
                require_once __DIR__ . '/../helpers/indexnow.php';
                indexnowPingArticle((string) $article['slug']);
            }
        } catch (Throwable $e) {
            error_log('api/content-health post-hook: ' . $e->getMessage());
        }
        ingestLog('konten-dioptimasi id=' . $aid . ' fields=' . implode(',', $changed) . ' publish=' . ($publish ? '1' : '0'));

        $saved = contentHealthFindArticle($pdo, $aid);
        if ($saved) {
            $candidate = $saved;
            $healthAfter = contentHealthAnalyzeArticle($saved, $ruleset);
            $newUpdatedAt = (string) $saved['updated_at'];
        }
    }

    ingestApiSend(200, [
        'ok' => true, 'dry_run' => $dryRun, 'article_id' => $aid,
        'changed' => $changed, 'status' => $candidate['status'],
        'published' => $candidate['status'] === 'published',
        'updated_at' => $dryRun ? $article['updated_at'] : $newUpdatedAt,
        'faq_count' => (int) ($candidate['faq_approved_count'] ?? 0),
        'health_before' => $healthBefore,
        'health_after' => $healthAfter,
        'edit_url' => '/admin/articles/' . $aid,
        'public_url' => $candidate['status'] === 'published' ? '/artikel/' . $article['slug'] : null,
        'warning' => $dryRun
            ? 'Dry-run: tidak ada perubahan yang disimpan atau diterbitkan.'
            : ($candidate['status'] === 'published'
                ? 'Hasil optimasi tersimpan dan artikel aktif di halaman publik.'
                : 'Hasil optimasi tersimpan; status artikel tidak diubah.'),
    ]);
}

// ─── GET: audit detail atau daftar ──────────────────────────────
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (isset($_GET['id']) && $id < 1) ingestApiSend(422, ['ok' => false, 'error' => 'id harus integer > 0.']);
if ($id > 0) {
    $article = contentHealthFindArticle($pdo, $id);
    if (!$article) ingestApiSend(404, ['ok' => false, 'error' => 'Artikel tidak ditemukan.']);
    $ruleset = scribeGetRuleset(false);
    ingestApiSend(200, [
        'ok' => true, 'article' => contentHealthAnalyzeArticle($article, $ruleset),
        'ruleset_version' => (int) ($ruleset['version'] ?? 0),
        'can_optimize' => ingestManageEnabled(),
    ]);
}

$status = trim((string) ($_GET['status'] ?? ''));
$level = trim((string) ($_GET['level'] ?? ''));
if ($status !== '' && !in_array($status, ['draft', 'draft_ai', 'scheduled', 'published'], true)) {
    ingestApiSend(422, ['ok' => false, 'error' => 'status tidak valid.']);
}
if ($level !== '' && !in_array($level, ['healthy', 'warning', 'critical'], true)) {
    ingestApiSend(422, ['ok' => false, 'error' => 'level tidak valid. Gunakan healthy, warning, atau critical.']);
}
$categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
if (isset($_GET['category_id']) && $categoryId < 1) {
    ingestApiSend(422, ['ok' => false, 'error' => 'category_id harus integer > 0.']);
}
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));

try {
    $ruleset = scribeGetRuleset(false);
    $scan = contentHealthScan($pdo, [
        'status' => $status, 'level' => $level, 'category_id' => $categoryId,
    ], $limit, $offset, $ruleset);
} catch (Throwable $e) {
    error_log('api/content-health: ' . $e->getMessage());
    ingestApiSend(500, ['ok' => false, 'error' => 'Gagal memeriksa kesehatan konten.']);
}

$articles = array_map(static function (array $article): array {
    unset($article['checks']);
    $article['edit_url'] = '/admin/articles/' . $article['id'];
    return $article;
}, $scan['articles']);

ingestApiSend(200, [
    'ok' => true, 'summary' => $scan['summary'], 'articles' => $articles,
    'limit' => $limit, 'offset' => $offset, 'total' => $scan['total'],
    'ruleset_version' => $scan['ruleset_version'],
    'can_optimize' => ingestManageEnabled(),
    'filters' => [
        'status' => $status !== '' ? $status : null,
        'level' => $level !== '' ? $level : null,
        'category_id' => $categoryId > 0 ? $categoryId : null,
    ],
]);
