<?php
// CTA tersimpan sebagai JSON per cakupan di settings; tidak mengubah skema artikel.
function articleCtaKey(string $scope, int $id = 0): string
{
    if (!in_array($scope, ['global', 'category', 'article'], true) || ($scope !== 'global' && $id < 1)) {
        throw new InvalidArgumentException('Cakupan CTA tidak valid.');
    }
    return 'article_cta_' . $scope . ($scope === 'global' ? '' : '_' . $id);
}

function articleCtaValidate(array $input): array
{
    $out = [];
    foreach (['mode' => 10, 'title' => 120, 'caption' => 500, 'label' => 60, 'url' => 2000] as $key => $limit) {
        $value = $input[$key] ?? ($key === 'mode' ? 'inherit' : '');
        if (!is_string($value) || mb_strlen($value) > $limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('Isi CTA tidak valid atau terlalu panjang: ' . $key . '.');
        }
        $out[$key] = trim(strip_tags($value));
    }
    if (!in_array($out['mode'], ['inherit', 'custom', 'off'], true)) throw new InvalidArgumentException('Mode CTA tidak valid.');
    if ($out['url'] !== '' && (!filter_var($out['url'], FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($out['url'], PHP_URL_SCHEME)), ['https', 'http'], true)
        || parse_url($out['url'], PHP_URL_USER) !== null || parse_url($out['url'], PHP_URL_PASS) !== null)) {
        throw new InvalidArgumentException('URL CTA harus berupa URL http/https tanpa kredensial.');
    }
    if ($out['mode'] === 'custom' && ($out['title'] === '' || $out['label'] === '' || $out['url'] === '')) {
        throw new InvalidArgumentException('CTA khusus memerlukan judul, teks tombol, dan URL.');
    }
    $out['blank'] = !empty($input['blank']);
    return $out;
}

/** Payload API lebih ketat agar typo Hermes tidak diam-diam diabaikan. */
function articleCtaValidateApiPayload(mixed $input): array
{
    if (!is_array($input) || !array_key_exists('mode', $input) || ($input !== [] && array_is_list($input))) {
        throw new InvalidArgumentException('article_cta harus berupa object JSON dan memuat mode.');
    }
    $unknown = array_diff(array_keys($input), ['mode', 'title', 'caption', 'label', 'url', 'blank']);
    if ($unknown) {
        throw new InvalidArgumentException('Field article_cta tidak dikenal: ' . implode(', ', $unknown) . '.');
    }
    if (array_key_exists('blank', $input) && !is_bool($input['blank'])) {
        throw new InvalidArgumentException('article_cta.blank harus boolean true/false.');
    }
    return articleCtaValidate($input);
}

function articleCtaGet(string $scope, int $id = 0): array
{
    if ($scope !== 'global' && $id < 1) return articleCtaValidate([]);
    $raw = getSetting(articleCtaKey($scope, $id), '');
    if (!$raw) return articleCtaValidate([]);
    try {
        $data = json_decode($raw, true);
        if (!is_array($data)) throw new InvalidArgumentException();
        return articleCtaValidate($data);
    } catch (InvalidArgumentException $e) { return articleCtaValidate(['mode' => 'off']); }
}

function articleCtaSave(string $scope, int $id, array $cta): void
{
    $cta = articleCtaValidate($cta);
    if (!setSetting(articleCtaKey($scope, $id), json_encode($cta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'article_cta')) {
        throw new RuntimeException('Gagal menyimpan CTA.');
    }
}

function articleCtaResolve(array $article, array $overrides = []): array
{
    foreach (['article' => (int) ($article['id'] ?? 0), 'category' => (int) ($article['category_id'] ?? 0), 'global' => 0] as $scope => $id) {
        $cta = array_key_exists($scope, $overrides)
            ? articleCtaValidate((array) $overrides[$scope])
            : articleCtaGet($scope, $id);
        if ($cta['mode'] !== 'inherit') return $cta + ['source' => $scope];
    }
    // Instalasi lama tetap memakai CTA band (termasuk form newsletter).
    return ['mode' => 'legacy', 'source' => 'global'];
}

/** Bentuk respons API stabil; cegah key internal/tak dikenal ikut keluar. */
function articleCtaApiOutput(array $cta): array
{
    $out = [
        'mode' => (string) ($cta['mode'] ?? 'off'),
        'title' => (string) ($cta['title'] ?? ''),
        'caption' => (string) ($cta['caption'] ?? ''),
        'label' => (string) ($cta['label'] ?? ''),
        'url' => (string) ($cta['url'] ?? ''),
        'blank' => !empty($cta['blank']),
    ];
    if (isset($cta['source'])) $out['source'] = (string) $cta['source'];
    return $out;
}

function articleCtaRender(array $cta): void
{
    if ($cta['mode'] !== 'custom') return;
    ?>
    <section class="fe-card my-8 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 sm:p-6" aria-label="Penawaran terkait artikel">
      <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1 min-w-0 break-words">
          <h2 class="font-display font-semibold text-lg leading-snug"><?= e($cta['title']) ?></h2>
          <?php if ($cta['caption'] !== ''): ?><p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300"><?= nl2br(e($cta['caption'])) ?></p><?php endif; ?>
        </div>
        <a data-meta-cta="article" href="<?= e($cta['url']) ?>"<?= $cta['blank'] ? ' target="_blank" rel="noopener noreferrer"' : '' ?> class="inline-flex justify-center items-center gap-2 shrink-0 max-w-full sm:max-w-[45%] rounded-md px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition-opacity" style="background:var(--accent)">
          <span class="break-words"><?= e($cta['label']) ?></span>
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </a>
      </div>
    </section>
    <?php
}
