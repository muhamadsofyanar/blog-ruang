<?php
// Editor artikel (staff). Buat/edit. Kiri: judul + editor konten. Kanan: meta.
// Autosave draft, token tag, recover draft, counter judul/meta.
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/ai-balance.php';
require_once __DIR__ . '/../../helpers/article-cta.php';
$pdo = getDB();

$id       = (int) ($_GET['id'] ?? 0);
$isEdit   = $id > 0;
$article  = null;
$artTags  = [];

if ($isEdit) {
    $st = $pdo->prepare("SELECT * FROM articles WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $article = $st->fetch();
    if (!$article) {
        http_response_code(404);
        admin_shell_top('Artikel', '/admin/articles');
        echo '<div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-10 text-center text-sm text-gray-500">Artikel tidak ditemukan.</div>';
        admin_shell_bottom();
        exit;
    }
    // Kepemilikan: writer tak boleh membuka artikel orang lain.
    if (isWriter() && (int) $article['author_id'] !== currentUserId()) {
        denyAccess();
    }
    $ts = $pdo->prepare("SELECT t.name FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ? ORDER BY t.name");
    $ts->execute([$id]);
    $artTags = $ts->fetchAll(PDO::FETCH_COLUMN);
}

$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$defaultLang = getSetting('default_language', 'id');

// Nilai form (edit vs baru).
$v = fn($k, $d = '') => e($article[$k] ?? $d);
$content     = $article['content'] ?? '';
$draft       = $article['draft_content'] ?? null;
$hasDraft    = $isEdit && $draft !== null && $draft !== '' && $draft !== $content;
$curStatus   = $article['status'] ?? 'draft';
$curLang     = $article['language'] ?? $defaultLang;
$curIntent   = $article['search_intent'] ?? '';
$pubAtVal    = !empty($article['published_at']) ? date('Y-m-d\TH:i', strtotime($article['published_at'])) : '';
$coverImage  = $article['cover_image'] ?? '';
$coverIsFb   = (int) ($article['cover_is_fallback'] ?? 0); // cover asli (0) vs placeholder SVG (1)
$tagsCsv     = implode(', ', $artTags);

// Outline tersimpan (sumber kebenaran) + hasil riset terakhir (untuk render ulang).
$outlineData = null;
if ($isEdit && !empty($article['outline_json'])) {
    $dec = json_decode((string) $article['outline_json'], true);
    if (is_array($dec)) $outlineData = $dec;
}
$lastResearch = null;
$lastAnalysis = null;
$savedFaq     = [];
if ($isEdit) {
    $rs = $pdo->prepare("SELECT result FROM seo_ai_runs WHERE article_id = ? AND task_type = 'keyword_research' AND status = 'success' ORDER BY id DESC LIMIT 1");
    $rs->execute([$id]);
    $rr = $rs->fetchColumn();
    if ($rr) { $dec = json_decode((string) $rr, true); if (is_array($dec)) $lastResearch = $dec; }

    // Analisis AI terakhir (untuk render ulang saat halaman dibuka).
    $an = $pdo->prepare("SELECT score, issues, suggestions, keyword_coverage, ruleset_version, created_at FROM seo_ai_analysis WHERE article_id = ? ORDER BY id DESC LIMIT 1");
    $an->execute([$id]);
    $ar = $an->fetch();
    if ($ar) {
        $cov = json_decode((string) $ar['keyword_coverage'], true) ?: [];
        $lastAnalysis = [
            'score'           => $ar['score'] !== null ? (int) $ar['score'] : null,
            'issues'          => json_decode((string) $ar['issues'], true) ?: [],
            'suggestions'     => json_decode((string) $ar['suggestions'], true) ?: [],
            'keywords'        => $cov['keywords'] ?? [],
            'word_count'      => $cov['word_count'] ?? null,
            'ruleset_version' => (int) $ar['ruleset_version'],
            'created_at'      => $ar['created_at'],
        ];
    }

    // FAQ tersimpan.
    $fq = $pdo->prepare("SELECT question, answer, approved FROM seo_ai_faq WHERE article_id = ? ORDER BY sort_order ASC, id ASC");
    $fq->execute([$id]);
    foreach ($fq->fetchAll() as $f) {
        $savedFaq[] = ['q' => $f['question'], 'a' => $f['answer'], 'approved' => (int) $f['approved'] === 1];
    }
}

admin_shell_top($isEdit ? 'Edit Artikel' : 'Artikel Baru', '/admin/articles');
?>
<?php if ($hasDraft): ?>
<div id="draftBanner" class="mb-4 flex flex-wrap items-center gap-3 rounded-md border border-amber-300 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
  <?= icon('alert-triangle', 'w-5 h-5 shrink-0') ?>
  <span class="flex-1">Ada draft yang belum tersimpan dari sesi sebelumnya.</span>
  <button type="button" onclick="recoverDraft()" class="rounded-md px-3 py-1.5 text-xs font-semibold text-white" style="background:var(--accent)">Pulihkan</button>
  <button type="button" onclick="discardDraft()" class="rounded-md px-3 py-1.5 text-xs font-medium border border-amber-400 dark:border-amber-700">Buang</button>
</div>
<textarea id="draftData" class="hidden"><?= e($draft) ?></textarea>
<?php endif; ?>

<form method="post" action="<?= e(url('/actions/admin/save-article')) ?>" enctype="multipart/form-data" id="articleForm">
  <?= csrfField() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <input type="hidden" name="content" id="contentField">
  <input type="hidden" name="tags" id="tagsField" value="<?= e($tagsCsv) ?>">
  <input type="hidden" name="outline_json" id="outlineField">

  <!-- ═══ AI Assist ═══ -->
  <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 mb-4">
    <button type="button" onclick="document.getElementById('aiBody').classList.toggle('hidden')" class="w-full flex items-center justify-between px-4 py-3 text-left">
      <span class="flex items-center gap-2 font-display font-semibold text-sm"><span class="inline-flex items-center justify-center w-6 h-6 rounded-md text-white" style="background:var(--accent)"><?= icon('pen-line', 'w-4 h-4') ?></span> AI Assist</span>
      <?= icon('chevron-down', 'w-4 h-4 text-gray-400') ?>
    </button>
    <div id="aiBody" class="px-4 pb-4 border-t border-gray-200 dark:border-gray-800 pt-4">
      <?php if (!$isEdit): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">Simpan artikel terlebih dahulu untuk memakai fitur AI (riset keyword, outline, draft).</p>
      <?php else: ?>
      <div class="relative mb-3 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
        <span>Tiap langkah memotong kredit sesuai estimasi.</span>
        <button type="button" onclick="toggleCostInfo(event)" class="inline-flex items-center gap-1 font-medium hover:underline" style="color:var(--accent)" aria-expanded="false" aria-label="Rincian biaya kredit"><?= icon('coins', 'w-3.5 h-3.5') ?> Rincian biaya</button>
        <div id="costInfoPop" class="hidden absolute z-30 top-6 left-0 w-72 max-w-[88vw] rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-lg p-4"><?= scribeCreditBreakdownHtml(true) ?></div>
      </div>
      <div class="grid md:grid-cols-2 gap-4">
        <!-- Step 1: Riset -->
        <div class="rounded-md border border-gray-200 dark:border-gray-800 p-3">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold flex items-center gap-1.5"><span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-gray-100 dark:bg-gray-800 text-xs font-bold">1</span> Riset Keyword</h3>
            <span class="text-xs text-gray-400">±<?= aiTaskCost('keyword_research') ?> kredit</span>
          </div>
          <button type="button" id="btnResearch" onclick="runResearch()" class="w-full rounded-md py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed" style="background:var(--accent)">Riset Keyword</button>
          <p id="researchHint" class="text-xs text-amber-600 dark:text-amber-400 mt-1.5 hidden">Isi Focus Keyword dulu di panel SEO.</p>
          <div id="researchMsg" class="text-xs mt-2 hidden"></div>
          <div id="researchResult" class="mt-3 hidden space-y-3"></div>
        </div>
        <!-- Step 2: Outline -->
        <div class="rounded-md border border-gray-200 dark:border-gray-800 p-3">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold flex items-center gap-1.5"><span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-gray-100 dark:bg-gray-800 text-xs font-bold">2</span> Outline</h3>
            <span class="text-xs text-gray-400">±<?= aiTaskCost('outline') ?> kredit</span>
          </div>
          <button type="button" id="btnOutline" onclick="runOutline()" class="w-full rounded-md py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-40" style="background:var(--accent)">Generate Outline</button>
          <div id="outlineMsg" class="text-xs mt-2 hidden"></div>
        </div>
      </div>
      <!-- Outline editor (full width) -->
      <div id="outlineEditor" class="mt-4 hidden"></div>

      <!-- Step 3: Tulis Draft per-seksi -->
      <div class="rounded-md border border-gray-200 dark:border-gray-800 p-3 mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
          <h3 class="text-sm font-semibold flex items-center gap-1.5"><span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-gray-100 dark:bg-gray-800 text-xs font-bold">3</span> Tulis Draft <span id="draftCostNote" class="text-xs font-normal text-gray-400"></span></h3>
          <div class="flex items-center gap-2">
            <span id="aiSaldo" class="text-xs text-gray-400"></span>
            <button type="button" id="btnDraft" onclick="runDraft()" class="rounded-md px-3 py-1.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-40" style="background:var(--accent)">Tulis Draft</button>
            <button type="button" id="btnDraftCancel" onclick="cancelDraft()" class="rounded-md px-3 py-1.5 text-sm font-medium border border-gray-300 dark:border-gray-700 hidden">Batal</button>
          </div>
        </div>
        <p id="draftHint" class="text-xs text-amber-600 dark:text-amber-400 hidden">Buat outline dengan minimal satu seksi terlebih dahulu.</p>
        <div id="draftMsg" class="text-xs mt-1 hidden"></div>
        <div id="draftProgress" class="mt-2 space-y-1 hidden"></div>
      </div>

      <!-- Generate Meta -->
      <div class="rounded-md border border-gray-200 dark:border-gray-800 p-3 mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold flex items-center gap-1.5"><?= icon('pen', 'w-4 h-4 text-gray-400') ?> Generate Meta <span class="text-xs font-normal text-gray-400">±<?= aiTaskCost('meta') ?> kredit</span></h3>
          <button type="button" id="btnMeta" onclick="runMeta()" class="rounded-md px-3 py-1.5 text-sm font-semibold border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Generate Meta</button>
        </div>
        <div id="metaMsg" class="text-xs mt-2 hidden"></div>
      </div>

      <!-- Step 4: Analisis AI -->
      <div class="rounded-md border border-gray-200 dark:border-gray-800 p-3 mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold flex items-center gap-1.5"><span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-gray-100 dark:bg-gray-800 text-xs font-bold">4</span> Analisis AI <span class="text-xs font-normal text-gray-400">±<?= aiTaskCost('analysis') ?> kredit</span></h3>
          <button type="button" id="btnAnalysis" onclick="runAnalysis()" class="rounded-md px-3 py-1.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-40" style="background:var(--accent)">Analisis AI</button>
        </div>
        <div id="analysisMsg" class="text-xs mt-2 hidden"></div>
        <div id="analysisResult" class="mt-3 hidden"></div>
      </div>

      <!-- Step 5: FAQ -->
      <div class="rounded-md border border-gray-200 dark:border-gray-800 p-3 mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold flex items-center gap-1.5"><span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-gray-100 dark:bg-gray-800 text-xs font-bold">5</span> FAQ <span class="text-xs font-normal text-gray-400">±<?= aiTaskCost('faq') ?> kredit</span></h3>
          <div class="flex items-center gap-2">
            <button type="button" id="btnFaq" onclick="runFaq()" class="rounded-md px-3 py-1.5 text-sm font-semibold border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Generate FAQ</button>
            <button type="button" id="btnFaqSave" onclick="saveFaq()" class="rounded-md px-3 py-1.5 text-sm font-semibold text-white hover:opacity-90 hidden" style="background:var(--accent)">Simpan FAQ</button>
          </div>
        </div>
        <p class="text-xs text-gray-400 mt-1">Approve item untuk menyisipkan blok FAQ di akhir artikel.</p>
        <div id="faqMsg" class="text-xs mt-2 hidden"></div>
        <div id="faqList" class="mt-3 space-y-2"></div>
      </div>

      <!-- Internal Link (lokal, gratis) -->
      <div class="rounded-md border border-gray-200 dark:border-gray-800 p-3 mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold flex items-center gap-1.5"><?= icon('link', 'w-4 h-4 text-gray-400') ?> Internal Link <span class="text-xs font-normal text-gray-400">gratis</span></h3>
          <button type="button" id="btnIlinks" onclick="runInternalLinks()" class="rounded-md px-3 py-1.5 text-sm font-semibold border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Cari saran</button>
        </div>
        <p class="text-xs text-gray-400 mt-1">Menautkan frasa yang sudah ada di tulisan ke artikel lain yang relevan. Tanpa kredit.</p>
        <div id="ilinksMsg" class="text-xs mt-2 hidden"></div>
        <div id="ilinksList" class="mt-3 space-y-2 hidden"></div>
        <button type="button" id="btnIlinksInsert" onclick="insertInternalLinks()" class="mt-3 rounded-md px-3 py-1.5 text-sm font-semibold text-white hover:opacity-90 hidden" style="background:var(--accent)">Sisipkan terpilih</button>
      </div>

      <!-- Alt Text Gambar (lokal, gratis) -->
      <div class="rounded-md border border-gray-200 dark:border-gray-800 p-3 mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold flex items-center gap-1.5"><?= icon('image', 'w-4 h-4 text-gray-400') ?> Alt Text Gambar <span class="text-xs font-normal text-gray-400">gratis</span></h3>
          <button type="button" id="btnAltText" onclick="runAltText()" class="rounded-md px-3 py-1.5 text-sm font-semibold border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Isi alt text</button>
        </div>
        <p class="text-xs text-gray-400 mt-1">Mengisi alt gambar yang kosong (dari caption/nama file/kata kunci). Alt yang sudah ada tidak diubah.</p>
        <div id="altMsg" class="text-xs mt-2 hidden"></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid lg:grid-cols-3 gap-4">
    <!-- Kolom konten -->
    <div class="lg:col-span-2 space-y-4">
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
        <input name="title" id="titleInput" value="<?= $v('title') ?>" required placeholder="Judul artikel…"
               class="w-full bg-transparent text-2xl font-display font-semibold focus:outline-none placeholder:text-gray-300 dark:placeholder:text-gray-600">
        <div class="mt-1 text-xs"><span id="titleCounter" class="font-medium">0</span> <span class="text-gray-400">karakter (ideal 55–60)</span></div>
      </div>

      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-0.5 p-2 border-b border-gray-200 dark:border-gray-800">
          <?php
          $tb = [
            ['h2', 'H2', 'heading'], ['h3', 'H3', 'heading'], ['bold', 'Bold', 'bold'], ['italic', 'Italic', 'italic'],
            ['ul', 'Bullet', 'list'], ['ol', 'Nomor', 'list-ordered'], ['quote', 'Kutipan', 'quote'],
            ['link', 'Link', 'link'], ['unlink', 'Hapus link', 'unlink'], ['image', 'Gambar', 'image'],
          ];
          foreach ($tb as [$cmd, $label, $ico]): ?>
            <button type="button" data-cmd="<?= $cmd ?>" title="<?= e($label) ?>" class="tb-btn p-2 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
              <?php if ($cmd === 'h2'): ?><span class="text-xs font-bold px-0.5">H2</span>
              <?php elseif ($cmd === 'h3'): ?><span class="text-xs font-bold px-0.5">H3</span>
              <?php else: ?><?= icon($ico, 'w-4 h-4') ?><?php endif; ?>
            </button>
          <?php endforeach; ?>
          <?php if ($isEdit): ?>
          <button type="button" onclick="previewArticle(this)" title="Pratinjau hasil di tab baru" class="ml-auto inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800"><?= icon('eye', 'w-4 h-4') ?> Pratinjau</button>
          <?php else: ?>
          <span class="ml-auto text-xs text-gray-400" title="Simpan artikel dulu untuk pratinjau">Pratinjau tersedia setelah disimpan</span>
          <?php endif; ?>
        </div>
        <div id="editor" contenteditable="true" class="prose-editor min-h-[420px] p-5 focus:outline-none text-[15px] leading-relaxed"><?= $content ?></div>
      </div>
      <p id="autosaveStatus" class="text-xs text-gray-400 h-4"></p>
      <!-- Tombol hapus gambar inline (mengambang; muncul saat gambar di editor diklik) -->
      <button type="button" id="imgDelBtn" class="hidden fixed z-40 inline-flex items-center gap-1 rounded-md bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-2 py-1 text-xs font-medium text-red-600 dark:text-red-400 shadow-lg hover:bg-red-50 dark:hover:bg-red-950/40"><?= icon('trash', 'w-3.5 h-3.5') ?> Hapus gambar</button>
    </div>

    <!-- Kolom meta -->
    <div class="lg:col-span-1 space-y-4">
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-3 sticky top-20">
        <div class="flex gap-2">
          <button type="submit" class="flex-1 rounded-md py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)"><?= icon('save', 'w-4 h-4 inline -mt-0.5') ?> Simpan</button>
          <a href="<?= e(url('/admin/articles')) ?>" class="rounded-md px-3 py-2 text-sm font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Batal</a>
        </div>

        <div>
          <label class="block text-xs font-medium mb-1">Status</label>
          <select name="status" id="statusSelect" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
            <?php foreach (['draft' => 'Draft', 'draft_ai' => 'Draft AI', 'scheduled' => 'Terjadwal', 'published' => 'Published'] as $k => $l): ?>
              <option value="<?= $k ?>" <?= $curStatus === $k ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="pubAtWrap" class="<?= in_array($curStatus, ['scheduled', 'published'], true) ? '' : 'hidden' ?>">
          <label class="block text-xs font-medium mb-1">Jadwal / Waktu Terbit</label>
          <input type="datetime-local" name="published_at" value="<?= e($pubAtVal) ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
      </div>

      <?php if ($isEdit): ?>
      <!-- Checklist SEO real-time (lokal, tanpa kredit) -->
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-display font-semibold text-sm">SEO Score</h3>
          <button type="button" onclick="refreshRuleset()" class="text-xs font-medium" style="color:var(--accent)" title="Ambil aturan terbaru">Perbarui aturan</button>
        </div>
        <div class="flex items-center gap-3 mb-3">
          <div id="seoRing" class="w-14 h-14 rounded-full border-4 border-gray-200 dark:border-gray-700 flex items-center justify-center shrink-0">
            <span id="seoScoreNum" class="text-lg font-bold">–</span>
          </div>
          <div><div class="text-xs text-gray-500 dark:text-gray-400">Skor checklist lokal</div><div id="seoWord" class="text-xs text-gray-400"></div></div>
        </div>
        <div id="seoChecks" class="space-y-1 text-xs"></div>
        <div id="rulesetInfo" class="text-[11px] text-gray-400 mt-2"></div>
      </div>
      <?php endif; ?>

      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-3">
        <div>
          <label class="block text-xs font-medium mb-1">Slug</label>
          <input name="slug" id="slugInput" value="<?= $v('slug') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Excerpt</label>
          <textarea name="excerpt" rows="2" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><?= $v('excerpt') ?></textarea>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Cover</label>
          <?php if ($coverImage): ?>
            <div id="coverPreviewWrap" class="relative mb-2 transition-opacity">
              <img src="<?= e(rtrim(UPLOAD_URL, '/') . '/' . $coverImage) ?>" alt="" class="w-full aspect-video object-cover rounded-md border border-gray-200 dark:border-gray-800">
              <?php if ($coverIsFb === 0): ?>
                <button type="button" id="coverRemoveBtn" class="absolute top-2 right-2 inline-flex items-center gap-1 rounded-md bg-white/90 dark:bg-gray-900/90 border border-gray-300 dark:border-gray-700 px-2 py-1 text-xs font-medium text-red-600 dark:text-red-400 shadow-sm hover:bg-red-50 dark:hover:bg-red-950/40"><?= icon('trash', 'w-3.5 h-3.5') ?> Hapus cover</button>
              <?php endif; ?>
            </div>
            <?php if ($coverIsFb === 0): ?>
              <p id="coverRemoveNote" class="hidden text-xs text-amber-600 dark:text-amber-400 mb-2">Cover akan dihapus saat disimpan; sistem memakai placeholder otomatis. <button type="button" id="coverRemoveUndo" class="underline font-medium">Batalkan</button></p>
            <?php endif; ?>
          <?php endif; ?>
          <input type="hidden" name="cover_remove" id="coverRemove" value="0">
          <input type="file" name="cover" id="coverFile" accept="image/*" class="w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-xs file:font-medium">
          <input name="cover_alt" value="<?= $v('cover_alt') ?>" placeholder="Alt text cover (wajib bila upload)" class="w-full mt-2 rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Kategori</label>
          <select name="category_id" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm">
            <option value="">— Tanpa kategori —</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) ($article['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Tags</label>
          <div id="tagBox" class="flex flex-wrap gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 px-2 py-2 min-h-[40px]">
            <input id="tagInput" type="text" placeholder="ketik lalu Enter…" class="flex-1 min-w-[80px] bg-transparent text-sm focus:outline-none" autocomplete="off">
          </div>
          <div id="tagSuggest" class="mt-1 hidden rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-sm overflow-hidden"></div>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-3">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">SEO</h3>
        <div>
          <label class="block text-xs font-medium mb-1">Focus Keyword</label>
          <input name="focus_keyword" id="focusKeyword" value="<?= $v('focus_keyword') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Related Keywords</label>
          <textarea name="related_keywords" id="relatedKeywords" rows="2" placeholder="pisah dengan koma" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><?= $v('related_keywords') ?></textarea>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-xs font-medium mb-1">Intent</label>
            <select name="search_intent" id="searchIntent" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-2 py-2 text-sm">
              <option value="">—</option>
              <?php foreach (['informational' => 'Informational', 'transactional' => 'Transactional', 'navigational' => 'Navigational'] as $k => $l): ?>
                <option value="<?= $k ?>" <?= $curIntent === $k ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Bahasa</label>
            <select name="language" id="langSelect" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-2 py-2 text-sm">
              <?php foreach (languageOptions() as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $curLang === $code ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Meta Title</label>
          <input name="meta_title" id="metaTitle" value="<?= $v('meta_title') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm">
          <div class="mt-1 text-xs"><span id="metaTitleCounter" class="font-medium">0</span> <span class="text-gray-400">/ 55–60</span></div>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Meta Description</label>
          <textarea name="meta_description" id="metaDesc" rows="3" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm"><?= $v('meta_description') ?></textarea>
          <div class="mt-1 text-xs"><span id="metaDescCounter" class="font-medium">0</span> <span class="text-gray-400">/ 120–160</span></div>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Canonical URL <span class="text-gray-400">(opsional)</span></label>
          <input name="canonical_url" value="<?= $v('canonical_url') ?>" class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono">
        </div>
      </div>
    </div>
  </div>
  <section class="mt-5 md:mt-7" aria-label="CTA Artikel">
    <?php
    $ctaValue = articleCtaGet('article', $id);
    $ctaGlobal = articleCtaResolve([]);
    $ctaFallbacks = [];
    foreach ($cats as $ctaCat) $ctaFallbacks[(int) $ctaCat['id']] = articleCtaResolve(['category_id' => $ctaCat['id']]);
    require __DIR__ . '/_article-cta-fields.php';
    ?>
  </section>
  <div class="mt-3 flex items-center gap-3">
    <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90" style="background:var(--accent)">Simpan artikel &amp; CTA</button>
    <span class="text-xs text-gray-500">Menyimpan seluruh perubahan artikel.</span>
  </div>
</form>
<script src="<?= e(url('/assets/js/article-cta.js')) ?>" defer></script>

<style>
.prose-editor h2{font-family:'Fraunces',serif;font-size:1.4rem;font-weight:600;margin:1rem 0 .5rem}
.prose-editor h3{font-family:'Fraunces',serif;font-size:1.15rem;font-weight:600;margin:.85rem 0 .4rem}
.prose-editor p{margin:.6rem 0}
.prose-editor ul{list-style:disc;padding-left:1.4rem;margin:.6rem 0}
.prose-editor ol{list-style:decimal;padding-left:1.4rem;margin:.6rem 0}
.prose-editor blockquote{border-left:3px solid var(--accent);padding-left:1rem;color:#6b7280;margin:.75rem 0}
.prose-editor a{color:var(--accent);text-decoration:underline}
.prose-editor img{max-width:100%;height:auto;border-radius:6px;margin:.5rem 0;cursor:pointer}
.prose-editor img.img-selected{outline:2px solid var(--accent);outline-offset:2px}
.prose-editor:empty:before{content:'Tulis konten artikel di sini…';color:#9ca3af}
.tag-chip{display:inline-flex;align-items:center;gap:4px;background:rgba(99,102,241,.12);color:var(--accent);font-size:.75rem;font-weight:600;padding:2px 8px;border-radius:6px}
</style>

<script>
const APP = <?= json_encode([
    'csrf' => generateCSRF(), 'id' => $id,
    'uploadUrl' => url('/actions/admin/upload-image'),
    'autosaveUrl' => url('/actions/admin/autosave-article'),
    'tagSuggestUrl' => url('/actions/admin/tag-suggest'),
    'researchUrl' => url('/actions/admin/ai-research'),
    'outlineUrl' => url('/actions/admin/ai-outline'),
    'draftUrl' => url('/actions/admin/ai-draft-section'),
    'metaUrl' => url('/actions/admin/ai-meta'),
    'internalLinksUrl' => url('/actions/admin/ai-internal-links'),
    'altTextUrl' => url('/actions/admin/ai-alt-text'),
    'creditsUrl' => url('/admin/credits'),
    'draftCost' => aiTaskCost('draft_section'),
    'metaCost' => aiTaskCost('meta'),
    'checkRulesUrl' => url('/actions/admin/ai-check-rules'),
    'analysisUrl' => url('/actions/admin/ai-analysis'),
    'faqUrl' => url('/actions/admin/ai-faq'),
    'faqSaveUrl' => url('/actions/admin/faq-save'),
    'previewUrl' => $isEdit ? url('/admin/articles/' . $id . '/preview') : '',
    'analysisCost' => aiTaskCost('analysis'),
    'faqCost' => aiTaskCost('faq'),
    'initialResearch' => $lastResearch,
    'initialOutline' => $outlineData,
    'initialAnalysis' => $lastAnalysis,
    'initialFaq' => $savedFaq,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const editor = document.getElementById('editor');
const form = document.getElementById('articleForm');

// Lacak posisi kursor terakhir di editor agar execCommand tetap tepat setelah
// fokus pindah ke modal (scribePrompt untuk tautan/gambar).
let lastEditorRange = null;
document.addEventListener('selectionchange', function(){
  const s = window.getSelection();
  if (s && s.rangeCount && editor.contains(s.anchorNode)) lastEditorRange = s.getRangeAt(0).cloneRange();
});
function restoreEditorSel(){
  editor.focus();
  if (lastEditorRange){ const s = window.getSelection(); s.removeAllRanges(); s.addRange(lastEditorRange); }
}
let dirty = false;

// ── Counter judul & meta ─────────────────────────────────────
function counterColor(el, len, min, max) {
  el.classList.remove('text-emerald-500','text-amber-500','text-red-500');
  if (len === 0) return;
  if (len < min) el.classList.add('text-amber-500');
  else if (len <= max) el.classList.add('text-emerald-500');
  else el.classList.add('text-red-500');
}
function bindCounter(inputId, counterId, min, max) {
  const inp = document.getElementById(inputId), cnt = document.getElementById(counterId);
  if (!inp || !cnt) return;
  const upd = () => { const l = inp.value.length; cnt.textContent = l; counterColor(cnt, l, min, max); };
  inp.addEventListener('input', upd); upd();
}
bindCounter('titleInput', 'titleCounter', 55, 60);
bindCounter('metaTitle', 'metaTitleCounter', 55, 60);
bindCounter('metaDesc', 'metaDescCounter', 120, 160);

// ── Slug auto dari judul (hanya bila slug kosong) ────────────
const slugInput = document.getElementById('slugInput');
const titleInput = document.getElementById('titleInput');
let slugTouched = slugInput.value.trim() !== '';
slugInput.addEventListener('input', () => slugTouched = true);
titleInput.addEventListener('input', () => { if (!slugTouched) slugInput.value = jsSlugify(titleInput.value); markDirty(); });
function jsSlugify(s){return s.toString().toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g,'').trim().replace(/[\s_]+/g,'-').replace(/-+/g,'-');}

// ── Status → tampil field published_at ───────────────────────
document.getElementById('statusSelect').addEventListener('change', function(){
  document.getElementById('pubAtWrap').classList.toggle('hidden', !['scheduled','published'].includes(this.value));
});

// ── Toolbar ──────────────────────────────────────────────────
document.querySelectorAll('.tb-btn').forEach(btn => {
  btn.addEventListener('mousedown', e => e.preventDefault()); // jangan hilang fokus editor
  btn.addEventListener('click', () => runCmd(btn.dataset.cmd));
});
function runCmd(cmd) {
  editor.focus();
  switch (cmd) {
    case 'h2': document.execCommand('formatBlock', false, 'h2'); break;
    case 'h3': document.execCommand('formatBlock', false, 'h3'); break;
    case 'bold': document.execCommand('bold'); break;
    case 'italic': document.execCommand('italic'); break;
    case 'ul': document.execCommand('insertUnorderedList'); break;
    case 'ol': document.execCommand('insertOrderedList'); break;
    case 'quote': document.execCommand('formatBlock', false, 'blockquote'); break;
    case 'link': {
      scribePrompt('Masukkan URL tautan.', { title:'Sisipkan tautan', placeholder:'https://…', okText:'Sisipkan' }).then(function(url){
        url = (url || '').trim();
        if (url) { restoreEditorSel(); document.execCommand('createLink', false, url); markDirty(); }
      });
      break;
    }
    case 'unlink': document.execCommand('unlink'); break;
    case 'image': pickImage(); break;
  }
  markDirty();
}

// ── Upload gambar inline ─────────────────────────────────────
function pickImage() {
  const inp = document.createElement('input');
  inp.type = 'file'; inp.accept = 'image/*';
  inp.onchange = () => {
    const f = inp.files[0]; if (!f) return;
    scribePrompt('Alt text gambar — jelaskan isi gambar (baik untuk SEO & aksesibilitas).', { title:'Alt text gambar', placeholder:'mis. Grafik pertumbuhan trafik organik', okText:'Sisipkan' }).then(function(alt){
      if (alt === null) return; // dibatalkan
      alt = (alt || '').trim();
      const fd = new FormData();
      fd.append('image', f); fd.append('csrf_token', APP.csrf);
      fetch(APP.uploadUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(d => {
          if (d.url) { restoreEditorSel(); document.execCommand('insertHTML', false, '<img src="' + d.url + '" alt="' + alt.replace(/"/g,'&quot;') + '">'); markDirty(); }
          else scribeAlert(d.error || 'Upload gagal.', { title: 'Upload gagal' });
        }).catch(() => scribeAlert('Upload gagal.', { title: 'Upload gagal' }));
    });
  };
  inp.click();
}

// ── Paste bersih (strip style/tag Word/Docs) ─────────────────
editor.addEventListener('paste', e => {
  e.preventDefault();
  const html = (e.clipboardData || window.clipboardData).getData('text/html');
  const text = (e.clipboardData || window.clipboardData).getData('text/plain');
  if (html) {
    document.execCommand('insertHTML', false, cleanPastedHtml(html));
  } else {
    document.execCommand('insertText', false, text);
  }
  markDirty();
});
function cleanPastedHtml(html) {
  const allowed = ['P','BR','H2','H3','STRONG','B','EM','I','UL','OL','LI','A','BLOCKQUOTE'];
  const doc = new DOMParser().parseFromString(html, 'text/html');
  (function walk(node){
    [...node.childNodes].forEach(child => {
      if (child.nodeType === 1) {
        walk(child);
        if (!allowed.includes(child.tagName)) {
          while (child.firstChild) node.insertBefore(child.firstChild, child);
          node.removeChild(child);
        } else {
          [...child.attributes].forEach(a => { if (a.name !== 'href') child.removeAttribute(a.name); });
        }
      } else if (child.nodeType === 8) { node.removeChild(child); }
    });
  })(doc.body);
  return doc.body.innerHTML;
}

// ── Token tags ───────────────────────────────────────────────
const tagBox = document.getElementById('tagBox');
const tagInput = document.getElementById('tagInput');
const tagsField = document.getElementById('tagsField');
const tagSuggest = document.getElementById('tagSuggest');
let tags = (tagsField.value || '').split(',').map(s => s.trim()).filter(Boolean);
function renderTags() {
  tagBox.querySelectorAll('.tag-chip').forEach(c => c.remove());
  tags.forEach((t, i) => {
    const chip = document.createElement('span');
    chip.className = 'tag-chip';
    chip.innerHTML = '<span></span><button type="button" aria-label="hapus" style="line-height:1">&times;</button>';
    chip.querySelector('span').textContent = t;
    chip.querySelector('button').onclick = () => { tags.splice(i,1); syncTags(); };
    tagBox.insertBefore(chip, tagInput);
  });
  tagsField.value = tags.join(', ');
}
function syncTags(){ renderTags(); markDirty(); }
function addTag(name){ name = name.trim().replace(/,$/,''); if(name && !tags.some(t=>t.toLowerCase()===name.toLowerCase())){ tags.push(name); syncTags(); } tagInput.value=''; tagSuggest.classList.add('hidden'); }
tagInput.addEventListener('keydown', e => {
  if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(tagInput.value); }
  else if (e.key === 'Backspace' && tagInput.value === '' && tags.length) { tags.pop(); syncTags(); }
});
let suggestTimer;
tagInput.addEventListener('input', () => {
  clearTimeout(suggestTimer);
  const q = tagInput.value.trim();
  if (q.length < 1) { tagSuggest.classList.add('hidden'); return; }
  suggestTimer = setTimeout(() => {
    fetch(APP.tagSuggestUrl + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json()).then(list => {
        list = list.filter(n => !tags.some(t => t.toLowerCase() === n.toLowerCase()));
        if (!list.length) { tagSuggest.classList.add('hidden'); return; }
        tagSuggest.innerHTML = '';
        list.forEach(n => { const b = document.createElement('button'); b.type='button'; b.className='block w-full text-left px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-800'; b.textContent=n; b.onclick=()=>addTag(n); tagSuggest.appendChild(b); });
        tagSuggest.classList.remove('hidden');
      });
  }, 200);
});
renderTags();

// ── Recover / discard draft ──────────────────────────────────
function recoverDraft(){ const d = document.getElementById('draftData'); if (d){ editor.innerHTML = d.value; markDirty(); } document.getElementById('draftBanner')?.remove(); }
function discardDraft(){
  const fd = new FormData(); fd.append('id', APP.id); fd.append('mode','discard'); fd.append('csrf_token', APP.csrf);
  fetch(APP.autosaveUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).finally(()=>document.getElementById('draftBanner')?.remove());
}

// ── Dirty tracking + autosave ────────────────────────────────
function markDirty(){ dirty = true; if (typeof syncDraftBtn === 'function') syncDraftBtn(); }
editor.addEventListener('input', markDirty);
titleInput.addEventListener('input', markDirty);

// ── Hapus cover artikel ──────────────────────────────────────
(function(){
  var btn = document.getElementById('coverRemoveBtn');
  if (!btn) return;
  var hid = document.getElementById('coverRemove'),
      note = document.getElementById('coverRemoveNote'),
      undo = document.getElementById('coverRemoveUndo'),
      wrap = document.getElementById('coverPreviewWrap'),
      file = document.getElementById('coverFile');
  function setRemoved(on){
    hid.value = on ? '1' : '0';
    if (note) note.classList.toggle('hidden', !on);
    if (wrap) wrap.style.opacity = on ? '0.4' : '';
    btn.classList.toggle('hidden', on);
    markDirty();
  }
  btn.addEventListener('click', function(){ setRemoved(true); });
  if (undo) undo.addEventListener('click', function(){ setRemoved(false); });
  // Memilih file baru membatalkan status hapus (upload menang).
  if (file) file.addEventListener('change', function(){ if (file.files && file.files.length) setRemoved(false); });
})();

// ── Hapus gambar inline: klik gambar di editor → tombol hapus mengambang ──
(function(){
  var del = document.getElementById('imgDelBtn');
  if (!del) return;
  var target = null;
  function hide(){ if (target) target.classList.remove('img-selected'); target = null; del.classList.add('hidden'); }
  function show(img){
    if (target) target.classList.remove('img-selected');
    target = img; img.classList.add('img-selected');
    del.classList.remove('hidden');               // tampil dulu agar offsetWidth valid
    var r = img.getBoundingClientRect();
    del.style.top = Math.max(8, r.top + 8) + 'px';
    del.style.left = Math.max(8, r.right - del.offsetWidth - 8) + 'px';
  }
  editor.addEventListener('click', function(e){
    var img = e.target && e.target.closest ? e.target.closest('img') : null;
    if (img && editor.contains(img)) show(img); else hide();
  });
  del.addEventListener('click', function(){
    if (!target) return;
    var fig = target.closest('figure');
    (fig || target).remove();
    hide(); markDirty();
  });
  // Sembunyikan saat konteks berubah.
  editor.addEventListener('keydown', hide);
  editor.addEventListener('scroll', hide, true);
  window.addEventListener('scroll', hide, true);
  window.addEventListener('resize', hide);
  document.addEventListener('mousedown', function(e){
    if (e.target === del || (e.target.closest && e.target.closest('#imgDelBtn'))) return;
    if (e.target.closest && e.target.closest('#editor img')) return;
    hide();
  });
})();

function autosave(){
  if (!dirty || !APP.id) return;
  const fd = new FormData();
  fd.append('id', APP.id); fd.append('content', editor.innerHTML); fd.append('csrf_token', APP.csrf);
  const oj = serializeOutline(); if (oj !== null) fd.append('outline_json', JSON.stringify(oj)); // outline ikut siklus autosave
  fetch(APP.autosaveUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => { if (d.success && d.saved_at){ document.getElementById('autosaveStatus').textContent = 'Draft tersimpan otomatis ' + d.saved_at; dirty = false; } });
}
// Pratinjau: simpan draft (autosave) dulu, lalu buka hasil di tab baru.
function previewArticle(btn){
  if (!APP.previewUrl){ return; }
  const orig = btn.innerHTML; btn.disabled = true; btn.textContent = 'Menyimpan…';
  const open = () => window.open(APP.previewUrl, '_blank', 'noopener');
  const done = () => { btn.disabled = false; btn.innerHTML = orig; };
  const fd = new FormData();
  fd.append('id', APP.id); fd.append('content', editor.innerHTML); fd.append('csrf_token', APP.csrf);
  const oj = serializeOutline(); if (oj !== null) fd.append('outline_json', JSON.stringify(oj));
  fetch(APP.autosaveUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(() => { dirty = false; open(); })
    .catch(() => open())
    .finally(done);
}
setInterval(autosave, 45000);
window.addEventListener('blur', autosave);

// ── Submit: pindahkan konten editor + outline ke hidden field ──
form.addEventListener('submit', () => {
  document.getElementById('contentField').value = editor.innerHTML;
  const oj = serializeOutline(); if (oj !== null) document.getElementById('outlineField').value = JSON.stringify(oj);
  dirty = false;
});

// ═══════════════════════ AI ASSIST ═══════════════════════════
// PENTING: semua nilai dari respons gateway diperlakukan sebagai DATA —
// dirender via textContent/createElement, TIDAK PERNAH innerHTML mentah.

function aiInlineMsg(el, res) {
  el.classList.remove('hidden', 'text-red-600', 'text-emerald-600', 'text-gray-500');
  el.innerHTML = '';
  const span = document.createElement('span');
  span.textContent = res.message || 'Terjadi kesalahan.';
  el.appendChild(span);
  el.classList.add(res.ok ? 'text-emerald-600' : 'text-red-600', 'dark:' + (res.ok ? 'text-emerald-400' : 'text-red-400'));
  // Aksi tambahan sesuai flag.
  if (res.flags && res.flags.show_topup) {
    const a = document.createElement('a');
    a.href = APP.creditsUrl; a.target = '_blank';
    a.className = 'ml-2 underline font-semibold'; a.textContent = 'Beli Kredit';
    el.appendChild(a);
  }
}

function aiFieldById(id) { return document.getElementById(id); }

// ── Riset Keyword ────────────────────────────────────────────
function researchEnabled() {
  const kw = aiFieldById('focusKeyword');
  return kw && kw.value.trim() !== '';
}
function syncResearchBtn() {
  const btn = document.getElementById('btnResearch');
  const hint = document.getElementById('researchHint');
  if (!btn) return;
  const ok = researchEnabled();
  btn.disabled = !ok;
  if (hint) hint.classList.toggle('hidden', ok);
}
if (aiFieldById('focusKeyword')) aiFieldById('focusKeyword').addEventListener('input', syncResearchBtn);

function runResearch() {
  if (!researchEnabled()) { syncResearchBtn(); return; }
  const btn = document.getElementById('btnResearch');
  const msg = document.getElementById('researchMsg');
  btn.disabled = true; btn.textContent = 'Meriset…';
  msg.className = 'text-xs mt-2 text-gray-500'; msg.classList.remove('hidden'); msg.textContent = 'Menghubungi AI…';
  const fd = new FormData();
  fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id);
  fd.append('keyword', aiFieldById('focusKeyword').value.trim());
  fd.append('language', aiFieldById('langSelect') ? aiFieldById('langSelect').value : 'id');
  fetch(APP.researchUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => {
      if (d.ok) { msg.classList.add('hidden'); renderResearch(d.result); }
      else aiInlineMsg(msg, d);
    })
    .catch(() => aiInlineMsg(msg, { ok:false, message:'Gateway tidak dapat dihubungi. Coba lagi.' }))
    .finally(() => { btn.disabled = false; btn.textContent = 'Riset Ulang'; });
}

function renderResearch(result) {
  const box = document.getElementById('researchResult');
  box.innerHTML = ''; box.classList.remove('hidden');
  if (!result || typeof result !== 'object') return;

  // Related keywords → checkbox.
  const rel = Array.isArray(result.related_keywords) ? result.related_keywords : [];
  if (rel.length) {
    const grp = document.createElement('div');
    const lbl = document.createElement('div'); lbl.className = 'text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1'; lbl.textContent = 'Keyword turunan';
    grp.appendChild(lbl);
    rel.forEach((kw, i) => {
      const id = 'relkw_' + i;
      const row = document.createElement('label'); row.className = 'flex items-center gap-2 text-sm py-0.5';
      const cb = document.createElement('input'); cb.type = 'checkbox'; cb.className = 'relkw'; cb.value = String(kw);
      const t = document.createElement('span'); t.textContent = String(kw);
      row.appendChild(cb); row.appendChild(t); grp.appendChild(row);
    });
    box.appendChild(grp);
  }

  // Clusters → read-only.
  const clusters = Array.isArray(result.clusters) ? result.clusters : [];
  if (clusters.length) {
    const grp = document.createElement('div');
    const lbl = document.createElement('div'); lbl.className = 'text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1'; lbl.textContent = 'Klaster topik';
    grp.appendChild(lbl);
    clusters.forEach(c => {
      const card = document.createElement('div'); card.className = 'rounded-md bg-gray-50 dark:bg-gray-800/50 px-2 py-1.5 mb-1';
      const nm = document.createElement('div'); nm.className = 'text-xs font-medium'; nm.textContent = String(c && c.name ? c.name : '-');
      card.appendChild(nm);
      const kws = (c && Array.isArray(c.keywords)) ? c.keywords : [];
      if (kws.length) { const k = document.createElement('div'); k.className = 'text-xs text-gray-500'; k.textContent = kws.map(String).join(', '); card.appendChild(k); }
      grp.appendChild(card);
    });
    box.appendChild(grp);
  }

  // Intent → radio, preselect hasil AI.
  const intent = String(result.intent || '');
  const grp = document.createElement('div');
  const lbl = document.createElement('div'); lbl.className = 'text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1'; lbl.textContent = 'Search intent';
  grp.appendChild(lbl);
  ['informational','transactional','navigational'].forEach(opt => {
    const row = document.createElement('label'); row.className = 'inline-flex items-center gap-1.5 text-sm mr-3';
    const rb = document.createElement('input'); rb.type = 'radio'; rb.name = 'ai_intent'; rb.className = 'ai-intent'; rb.value = opt;
    if (opt === intent) rb.checked = true;
    const t = document.createElement('span'); t.textContent = opt;
    row.appendChild(rb); row.appendChild(t); grp.appendChild(row);
  });
  box.appendChild(grp);

  // Tombol simpan ke artikel.
  const btn = document.createElement('button'); btn.type = 'button';
  btn.className = 'mt-1 rounded-md px-3 py-1.5 text-xs font-semibold text-white'; btn.style.background = 'var(--accent)';
  btn.textContent = 'Simpan ke Artikel'; btn.onclick = saveResearchToArticle;
  box.appendChild(btn);
}

function saveResearchToArticle() {
  // Related keywords terpilih → gabung ke field tanpa duplikat.
  const field = aiFieldById('relatedKeywords');
  const existing = field.value.split(',').map(s => s.trim()).filter(Boolean);
  document.querySelectorAll('#researchResult .relkw:checked').forEach(cb => {
    if (!existing.some(x => x.toLowerCase() === cb.value.toLowerCase())) existing.push(cb.value);
  });
  field.value = existing.join(', ');
  // Intent → select.
  const chosen = document.querySelector('#researchResult .ai-intent:checked');
  if (chosen && aiFieldById('searchIntent')) aiFieldById('searchIntent').value = chosen.value;
  markDirty();
  const msg = document.getElementById('researchMsg');
  aiInlineMsg(msg, { ok:true, message:'Tersimpan ke field artikel. Jangan lupa Simpan artikel.' });
}

// ── Outline editable ─────────────────────────────────────────
async function runOutline() {
  if (!researchEnabled()) { const m = document.getElementById('outlineMsg'); aiInlineMsg(m, {ok:false, message:'Isi Focus Keyword dulu.'}); return; }
  // Konfirmasi timpa bila outline sudah ada.
  if (currentOutlineHasContent() && !(await scribeConfirm('Outline yang ada akan ditimpa dengan hasil baru.', { title: 'Timpa outline?', okText: 'Timpa' }))) return;
  const btn = document.getElementById('btnOutline');
  const msg = document.getElementById('outlineMsg');
  btn.disabled = true; btn.textContent = 'Membuat…';
  msg.className = 'text-xs mt-2 text-gray-500'; msg.classList.remove('hidden'); msg.textContent = 'Menghubungi AI…';
  const fd = new FormData();
  fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id);
  fd.append('keyword', aiFieldById('focusKeyword').value.trim());
  fd.append('related_keywords', aiFieldById('relatedKeywords').value.trim());
  fd.append('intent', aiFieldById('searchIntent') ? aiFieldById('searchIntent').value : '');
  fd.append('language', aiFieldById('langSelect') ? aiFieldById('langSelect').value : 'id');
  fetch(APP.outlineUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => {
      if (d.ok) { msg.classList.add('hidden'); renderOutline(outlineFromAi(d.result)); markDirty(); }
      else aiInlineMsg(msg, d);
    })
    .catch(() => aiInlineMsg(msg, { ok:false, message:'Gateway tidak dapat dihubungi. Coba lagi.' }))
    .finally(() => { btn.disabled = false; btn.textContent = 'Generate Outline'; });
}

// Konversi hasil AI outline → struktur internal {h1, sections, faq_suggestions, faq_selected}.
function outlineFromAi(result) {
  const secs = (result && Array.isArray(result.sections)) ? result.sections.map(s => ({
    h2: String(s && s.h2 ? s.h2 : ''),
    h3s: (s && Array.isArray(s.h3s)) ? s.h3s.map(String) : [],
  })) : [];
  const faqs = (result && Array.isArray(result.faq_suggestions)) ? result.faq_suggestions.map(f => String(f && f.q ? f.q : f)) : [];
  return { h1: String(result && result.h1 ? result.h1 : ''), sections: secs, faq_suggestions: faqs, faq_selected: [] };
}

function currentOutlineHasContent() {
  const ed = document.getElementById('outlineEditor');
  return ed && !ed.classList.contains('hidden') && ed.querySelector('.ol-section');
}

// Render editor outline (editable penuh, DOM dibangun via createElement).
function renderOutline(data) {
  const ed = document.getElementById('outlineEditor');
  ed.innerHTML = ''; ed.classList.remove('hidden');
  ed.dataset.faqs = JSON.stringify(data.faq_suggestions || []);

  // H1
  const h1wrap = document.createElement('div'); h1wrap.className = 'mb-3';
  const h1lbl = document.createElement('label'); h1lbl.className = 'block text-xs font-medium mb-1'; h1lbl.textContent = 'H1 (Judul utama)';
  const h1inp = document.createElement('input'); h1inp.id = 'olH1'; h1inp.className = 'w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm'; h1inp.value = data.h1 || '';
  h1inp.addEventListener('input', markDirty);
  h1wrap.appendChild(h1lbl); h1wrap.appendChild(h1inp); ed.appendChild(h1wrap);

  // Sections container
  const secWrap = document.createElement('div'); secWrap.id = 'olSections'; secWrap.className = 'space-y-2';
  ed.appendChild(secWrap);
  (data.sections || []).forEach(s => secWrap.appendChild(buildSection(s)));

  // + Tambah seksi
  const addBtn = document.createElement('button'); addBtn.type = 'button';
  addBtn.className = 'mt-2 rounded-md px-3 py-1.5 text-xs font-medium border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800';
  addBtn.textContent = '+ Tambah Seksi';
  addBtn.onclick = () => { secWrap.appendChild(buildSection({ h2:'', h3s:[] })); markDirty(); };
  ed.appendChild(addBtn);

  // FAQ suggestions (read-only checkbox → dipakai A-06)
  const faqs = data.faq_suggestions || [];
  if (faqs.length) {
    const fwrap = document.createElement('div'); fwrap.className = 'mt-4';
    const flbl = document.createElement('div'); flbl.className = 'text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1'; flbl.textContent = 'Saran FAQ (dipakai di langkah FAQ)';
    fwrap.appendChild(flbl);
    faqs.forEach((q, i) => {
      const row = document.createElement('label'); row.className = 'flex items-center gap-2 text-sm py-0.5';
      const cb = document.createElement('input'); cb.type = 'checkbox'; cb.className = 'ol-faq'; cb.value = String(q);
      if ((data.faq_selected || []).includes(q)) cb.checked = true;
      cb.addEventListener('change', markDirty);
      const t = document.createElement('span'); t.textContent = String(q);
      row.appendChild(cb); row.appendChild(t); fwrap.appendChild(row);
    });
    ed.appendChild(fwrap);
  }
}

function buildSection(s) {
  const card = document.createElement('div'); card.className = 'ol-section rounded-md border border-gray-200 dark:border-gray-800 p-3'; card.draggable = true;
  card.addEventListener('dragstart', e => { card.classList.add('opacity-50'); e.dataTransfer.setData('text/plain', ''); dragSrc = card; });
  card.addEventListener('dragend', () => card.classList.remove('opacity-50'));
  card.addEventListener('dragover', e => e.preventDefault());
  card.addEventListener('drop', e => { e.preventDefault(); if (dragSrc && dragSrc !== card) { const p = card.parentNode; const rect = card.getBoundingClientRect(); (e.clientY < rect.top + rect.height/2) ? p.insertBefore(dragSrc, card) : p.insertBefore(dragSrc, card.nextSibling); markDirty(); } });

  // Header: drag handle + H2 input + up/down/remove
  const head = document.createElement('div'); head.className = 'flex items-center gap-2 mb-2';
  const handle = document.createElement('span'); handle.className = 'cursor-move text-gray-400 select-none'; handle.textContent = '\u22EE\u22EE'; handle.title = 'Seret untuk urutkan';
  const h2 = document.createElement('input'); h2.className = 'ol-h2 flex-1 rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-2 py-1.5 text-sm font-medium'; h2.placeholder = 'Judul H2'; h2.value = s.h2 || ''; h2.addEventListener('input', markDirty);
  const up = mkIconBtn('\u25B2', () => { const prev = card.previousElementSibling; if (prev) { card.parentNode.insertBefore(card, prev); markDirty(); } });
  const down = mkIconBtn('\u25BC', () => { const next = card.nextElementSibling; if (next) { card.parentNode.insertBefore(next, card); markDirty(); } });
  const del = mkIconBtn('\u2715', () => { card.remove(); markDirty(); }); del.classList.add('hover:text-red-600');
  head.appendChild(handle); head.appendChild(h2); head.appendChild(up); head.appendChild(down); head.appendChild(del);
  card.appendChild(head);

  // H3 list
  const h3wrap = document.createElement('div'); h3wrap.className = 'ol-h3s space-y-1 pl-4';
  (s.h3s || []).forEach(h3 => h3wrap.appendChild(buildH3(h3)));
  card.appendChild(h3wrap);
  const addH3 = document.createElement('button'); addH3.type = 'button'; addH3.className = 'ml-4 mt-1 text-xs font-medium'; addH3.style.color = 'var(--accent)'; addH3.textContent = '+ Sub-bagian H3';
  addH3.onclick = () => { h3wrap.appendChild(buildH3('')); markDirty(); };
  card.appendChild(addH3);
  return card;
}
function buildH3(val) {
  const row = document.createElement('div'); row.className = 'flex items-center gap-2';
  const inp = document.createElement('input'); inp.className = 'ol-h3 flex-1 rounded-md border border-gray-200 dark:border-gray-800 bg-transparent px-2 py-1 text-sm'; inp.placeholder = 'Sub-bagian H3'; inp.value = val || ''; inp.addEventListener('input', markDirty);
  const del = mkIconBtn('\u2715', () => { row.remove(); markDirty(); });
  row.appendChild(inp); row.appendChild(del); return row;
}
function mkIconBtn(sym, fn) { const b = document.createElement('button'); b.type = 'button'; b.className = 'w-6 h-6 rounded-md text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 text-xs shrink-0'; b.textContent = sym; b.onclick = fn; return b; }
let dragSrc = null;

// Serialisasi outline dari DOM → objek (null bila editor belum aktif).
function serializeOutline() {
  const ed = document.getElementById('outlineEditor');
  if (!ed || ed.classList.contains('hidden')) return null;
  const h1el = document.getElementById('olH1');
  const sections = [];
  document.querySelectorAll('#olSections .ol-section').forEach(card => {
    const h2 = card.querySelector('.ol-h2').value.trim();
    const h3s = [...card.querySelectorAll('.ol-h3')].map(i => i.value.trim()).filter(Boolean);
    if (h2 || h3s.length) sections.push({ h2, h3s });
  });
  const faqSel = [...document.querySelectorAll('.ol-faq:checked')].map(c => c.value);
  const faqSug = ed.dataset.faqs ? JSON.parse(ed.dataset.faqs) : [];
  return { h1: h1el ? h1el.value.trim() : '', sections, faq_suggestions: faqSug, faq_selected: faqSel };
}

// State draft per-seksi — HARUS dideklarasikan SEBELUM initAi() dijalankan,
// karena initAi() memanggil syncDraftBtn() yang membaca draftRunning. Bila
// deklarasi ada di bawah initAi(), akses ini kena Temporal Dead Zone
// (ReferenceError) yang meng-halt seluruh skrip → semua tombol AI mati.
let draftCancelled = false, draftRunning = false;

// ── Inisialisasi dari data tersimpan ─────────────────────────
(function initAi() {
  if (!APP.id) return;
  syncResearchBtn();
  if (APP.initialResearch) renderResearch(APP.initialResearch);
  if (APP.initialOutline && (APP.initialOutline.h1 || (APP.initialOutline.sections||[]).length)) {
    renderOutline({
      h1: APP.initialOutline.h1 || '',
      sections: APP.initialOutline.sections || [],
      faq_suggestions: APP.initialOutline.faq_suggestions || [],
      faq_selected: APP.initialOutline.faq_selected || [],
    });
  }
  syncDraftBtn();
})();

// ═══════════════════════ DRAFT PER-SEKSI ═════════════════════
// Anti-timeout: SATU seksi per request (tidak pernah seluruh artikel sekaligus).
// (state draftCancelled/draftRunning dideklarasikan di atas — sebelum initAi)

function syncDraftBtn() {
  const btn = document.getElementById('btnDraft');
  if (!btn) return;
  const ol = serializeOutline();
  const n = ol ? ol.sections.length : 0;
  const note = document.getElementById('draftCostNote');
  const hint = document.getElementById('draftHint');
  if (!draftRunning) btn.disabled = n < 1;
  if (hint) hint.classList.toggle('hidden', n >= 1);
  if (note) note.textContent = n >= 1 ? ('(' + n + ' seksi × ' + APP.draftCost + ' = ~' + (n * APP.draftCost) + ' kredit, +' + APP.metaCost + ' meta)') : '';
}

function draftRow(i, h2) {
  return '<div id="drow_' + i + '" class="flex items-center gap-2 text-xs">'
       + '<span id="dstat_' + i + '" class="inline-flex items-center justify-center w-4 h-4 rounded-full border border-gray-300 dark:border-gray-600 text-[9px]">' + (i + 1) + '</span>'
       + '<span class="flex-1 truncate"></span><span id="dmsg_' + i + '" class="text-gray-400"></span></div>';
}

async function runDraft() {
  const ol = serializeOutline();
  if (!ol || !ol.sections.length) { syncDraftBtn(); return; }
  const n = ol.sections.length;
  if (!(await scribeConfirm('Perkiraan biaya ~' + (n * APP.draftCost) + ' kredit.', { title: 'Tulis draft ' + n + ' seksi?', okText: 'Tulis Draft' }))) return;

  draftRunning = true; draftCancelled = false;
  document.getElementById('btnDraft').disabled = true;
  document.getElementById('btnDraftCancel').classList.remove('hidden');
  const dm = document.getElementById('draftMsg'); dm.classList.add('hidden');

  // Bangun progress rows (h2 via textContent — DATA).
  const prog = document.getElementById('draftProgress');
  prog.innerHTML = ''; prog.classList.remove('hidden');
  ol.sections.forEach((s, i) => {
    prog.insertAdjacentHTML('beforeend', draftRow(i, s.h2));
    prog.querySelector('#drow_' + i + ' .flex-1').textContent = s.h2 || ('Seksi ' + (i + 1));
  });

  for (let i = 0; i < n; i++) {
    if (draftCancelled) { setRow(i, 'batal', 'dibatalkan'); break; }
    const outcome = await draftOne(i, ol.sections[i].h2 || ('Seksi ' + (i + 1)));
    if (outcome === 'stop') break; // INSUFFICIENT_CREDITS → berhenti total
  }

  draftRunning = false;
  document.getElementById('btnDraftCancel').classList.add('hidden');
  syncDraftBtn();
}

function cancelDraft() { draftCancelled = true; document.getElementById('btnDraftCancel').disabled = true; }

function setRow(i, state, msg) {
  const stat = document.getElementById('dstat_' + i);
  const m = document.getElementById('dmsg_' + i);
  if (!stat) return;
  stat.className = 'inline-flex items-center justify-center w-4 h-4 rounded-full text-[9px] ';
  if (state === 'run') { stat.className += 'border border-gray-400'; stat.textContent = '\u00B7'; }
  else if (state === 'done') { stat.className += 'bg-emerald-500 text-white'; stat.textContent = '\u2713'; }
  else if (state === 'error') { stat.className += 'bg-red-500 text-white'; stat.textContent = '!'; }
  else { stat.className += 'border border-gray-300'; stat.textContent = String(i + 1); }
  if (m) m.textContent = msg || '';
}

async function draftOne(i, h2, attempt = 1) {
  setRow(i, 'run', 'Menulis…');
  const fd = new FormData();
  fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id); fd.append('section_index', i);
  try {
    const r = await fetch(APP.draftUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
    const d = await r.json();
    if (d.ok) {
      // d.html SUDAH disanitasi server (whitelist §6.4) → innerHTML aman di sini.
      insertSectionOrdered(d.section_index, d.html);
      setRow(i, 'done', '');
      if (d.balance) updateSaldo(d.balance);
      markDirty();
      return 'ok';
    }
    if (d.code === 'INSUFFICIENT_CREDITS') {
      setRow(i, 'error', 'kredit habis');
      aiInlineMsg(document.getElementById('draftMsg'), d);
      return 'stop';
    }
    // RATE_LIMITED → BUKAN gagal: jeda 20 dtk lalu auto-retry (maks 3 percobaan).
    if (d.code === 'RATE_LIMITED') {
      if (attempt < 3 && !draftCancelled) {
        await draftCountdown(i, 20);
        if (draftCancelled) { setRow(i, 'error', 'dibatalkan'); return 'error'; }
        return draftOne(i, h2, attempt + 1);
      }
      setRow(i, 'error', '');
      addRetry(i, h2, 'Jalur AI masih sibuk setelah beberapa kali. Coba lagi nanti.');
      return 'error';
    }
    // Error lain → tandai + tombol ulangi, lanjut seksi berikutnya.
    setRow(i, 'error', '');
    addRetry(i, h2, d.message || 'Gagal.');
    return 'error';
  } catch (e) {
    setRow(i, 'error', '');
    addRetry(i, h2, 'Gateway tidak dapat dihubungi.');
    return 'error';
  }
}

// Hitung mundur saat menunggu rate limit longgar.
async function draftCountdown(i, secs) {
  for (let s = secs; s > 0; s--) {
    if (draftCancelled) return;
    const m = document.getElementById('dmsg_' + i);
    if (m) m.textContent = 'Menunggu jalur AI longgar… (' + s + 's)';
    await new Promise(res => setTimeout(res, 1000));
  }
}

function addRetry(i, h2, msg) {
  const m = document.getElementById('dmsg_' + i);
  if (!m) return;
  m.innerHTML = '';
  const t = document.createElement('span'); t.className = 'text-red-600 dark:text-red-400 mr-2'; t.textContent = msg;
  const b = document.createElement('button'); b.type = 'button'; b.className = 'underline'; b.style.color = 'var(--accent)'; b.textContent = 'Ulangi seksi ini';
  b.onclick = () => draftOne(i, h2);
  m.appendChild(t); m.appendChild(b);
}

// Sisip seksi ke editor pada urutan idx (mirror server) — idempotent.
function insertSectionOrdered(idx, html) {
  editor.querySelectorAll('section[data-outline-idx="' + idx + '"]').forEach(s => s.remove());
  const sec = document.createElement('section');
  sec.setAttribute('data-outline-idx', String(idx));
  sec.innerHTML = html; // aman: sudah disanitasi server-side
  let before = null;
  editor.querySelectorAll('section[data-outline-idx]').forEach(s => {
    if (before === null && parseInt(s.getAttribute('data-outline-idx'), 10) > idx) before = s;
  });
  before ? editor.insertBefore(sec, before) : editor.appendChild(sec);
}

function updateSaldo(bal) {
  const el = document.getElementById('aiSaldo');
  if (!el || !bal) return;
  const total = (parseInt(bal.monthly_remaining || 0, 10) + parseInt(bal.topup_active_total || 0, 10)) / 100;
  el.textContent = 'Sisa ~' + (Math.round(total * 10) / 10) + ' artikel';
}

// ── Generate Meta ────────────────────────────────────────────
function runMeta() {
  const btn = document.getElementById('btnMeta');
  const msg = document.getElementById('metaMsg');
  btn.disabled = true; btn.textContent = 'Membuat…';
  msg.className = 'text-xs mt-2 text-gray-500'; msg.classList.remove('hidden'); msg.textContent = 'Menghubungi AI…';
  const fd = new FormData();
  fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id);
  fetch(APP.metaUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => {
      if (d.ok) {
        const mt = document.getElementById('metaTitle'), md = document.getElementById('metaDesc');
        if (mt) { mt.value = d.meta_title; mt.dispatchEvent(new Event('input')); }
        if (md) { md.value = d.meta_description; md.dispatchEvent(new Event('input')); }
        aiInlineMsg(msg, { ok:true, message:'Meta terisi. Periksa lalu Simpan artikel bila cocok.' });
      } else aiInlineMsg(msg, d);
    })
    .catch(() => aiInlineMsg(msg, { ok:false, message:'Gateway tidak dapat dihubungi.' }))
    .finally(() => { btn.disabled = false; btn.textContent = 'Generate Meta'; });
}

// ═══════════════════ INTERNAL LINK (lokal, gratis) ═══════════════
let ilSuggestions = [];
function ilEsc(s){ const d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
function runInternalLinks() {
  const btn = document.getElementById('btnIlinks');
  const msg = document.getElementById('ilinksMsg');
  const list = document.getElementById('ilinksList');
  const insBtn = document.getElementById('btnIlinksInsert');
  btn.disabled = true; btn.textContent = 'Mencari…';
  msg.className = 'text-xs mt-2 text-gray-500'; msg.classList.remove('hidden'); msg.textContent = 'Mencari artikel relevan…';
  list.classList.add('hidden'); insBtn.classList.add('hidden');
  const fd = new FormData();
  fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id);
  fd.append('content_html', editor.innerHTML);
  fetch(APP.internalLinksUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => {
      if (!d.ok) { aiInlineMsg(msg, d); return; }
      ilSuggestions = d.suggestions || [];
      if (!ilSuggestions.length) { aiInlineMsg(msg, { ok:true, message:'Belum ada frasa yang cocok. Sebut topik artikel lain di tulisan, lalu coba lagi.' }); return; }
      msg.classList.add('hidden');
      list.innerHTML = ilSuggestions.map((s, i) =>
        '<label class="flex items-start gap-2 text-sm">' +
        '<input type="checkbox" class="il-cb mt-0.5" data-i="' + i + '" checked>' +
        '<span><span class="font-medium">' + ilEsc(s.anchor) + '</span> <span class="text-gray-400">→ ' + ilEsc(s.title) + '</span></span></label>'
      ).join('');
      list.classList.remove('hidden'); insBtn.classList.remove('hidden');
    })
    .catch(() => aiInlineMsg(msg, { ok:false, message:'Tidak dapat menghubungi server.' }))
    .finally(() => { btn.disabled = false; btn.textContent = 'Cari saran'; });
}
function insertInternalLinks() {
  const chosen = [];
  document.querySelectorAll('#ilinksList .il-cb:checked').forEach(function (cb) {
    const s = ilSuggestions[parseInt(cb.getAttribute('data-i'), 10)];
    if (s) chosen.push({ anchor: s.anchor, url: s.url });
  });
  const msg = document.getElementById('ilinksMsg');
  if (!chosen.length) { aiInlineMsg(msg, { ok:false, message:'Pilih minimal satu saran.' }); return; }
  const btn = document.getElementById('btnIlinksInsert');
  btn.disabled = true; btn.textContent = 'Menyisipkan…';
  const fd = new FormData();
  fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id);
  fd.append('content_html', editor.innerHTML);
  fd.append('apply', JSON.stringify(chosen));
  fetch(APP.internalLinksUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => {
      if (!d.ok) { aiInlineMsg(msg, d); return; }
      editor.innerHTML = d.content_html; markDirty();
      aiInlineMsg(msg, { ok:true, message: (d.inserted || 0) + ' tautan disisipkan. Periksa lalu Simpan artikel.' });
      document.getElementById('ilinksList').classList.add('hidden');
      btn.classList.add('hidden');
    })
    .catch(() => aiInlineMsg(msg, { ok:false, message:'Tidak dapat menghubungi server.' }))
    .finally(() => { btn.disabled = false; btn.textContent = 'Sisipkan terpilih'; });
}

// ═══════════════════ ALT TEXT GAMBAR (lokal, gratis) ═════════════
function runAltText() {
  const btn = document.getElementById('btnAltText');
  const msg = document.getElementById('altMsg');
  btn.disabled = true; btn.textContent = 'Memproses…';
  msg.className = 'text-xs mt-2 text-gray-500'; msg.classList.remove('hidden'); msg.textContent = 'Memeriksa gambar…';
  const fd = new FormData();
  fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id);
  fd.append('content_html', editor.innerHTML);
  fetch(APP.altTextUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => {
      if (!d.ok) { aiInlineMsg(msg, d); return; }
      if (d.filled > 0) { editor.innerHTML = d.content_html; markDirty(); }
      aiInlineMsg(msg, { ok:true, message: d.filled > 0
        ? (d.filled + ' alt text terisi. Periksa lalu Simpan artikel.')
        : 'Semua gambar sudah punya alt text (atau tidak ada gambar).' });
    })
    .catch(() => aiInlineMsg(msg, { ok:false, message:'Tidak dapat menghubungi server.' }))
    .finally(() => { btn.disabled = false; btn.textContent = 'Isi alt text'; });
}

// ═══════════════════ CHECKLIST SEO LOKAL (tanpa kredit) ══════════
let checkTimer = null;
function scheduleChecklist() { clearTimeout(checkTimer); checkTimer = setTimeout(() => runChecklist(false), 2000); }

function runChecklist(force) {
  if (!document.getElementById('seoChecks')) return;
  const fd = new FormData();
  fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id);
  fd.append('content', editor.innerHTML);
  fd.append('meta_title', document.getElementById('metaTitle') ? document.getElementById('metaTitle').value : '');
  fd.append('meta_description', document.getElementById('metaDesc') ? document.getElementById('metaDesc').value : '');
  fd.append('focus_keyword', aiFieldById('focusKeyword') ? aiFieldById('focusKeyword').value : '');
  fd.append('title', titleInput.value);
  if (force) fd.append('refresh', '1');
  fetch(APP.checkRulesUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => { if (d.ok) renderChecklist(d); });
}
function refreshRuleset() { runChecklist(true); }

function renderChecklist(d) {
  const num = document.getElementById('seoScoreNum');
  const ring = document.getElementById('seoRing');
  num.textContent = d.score;
  const color = d.score >= 80 ? '#10b981' : (d.score >= 50 ? '#f59e0b' : '#ef4444');
  ring.style.borderColor = color; num.style.color = color;
  document.getElementById('seoWord').textContent = (d.word_count || 0) + ' kata';

  const box = document.getElementById('seoChecks'); box.innerHTML = '';
  (d.checks || []).forEach(c => {
    const row = document.createElement('div'); row.className = 'flex items-start gap-1.5';
    const ic = document.createElement('span');
    ic.className = 'shrink-0 mt-0.5 ' + (c.pass ? 'text-emerald-500' : 'text-gray-300 dark:text-gray-600');
    ic.textContent = c.pass ? '\u2713' : '\u25CB';
    const txt = document.createElement('span');
    txt.className = c.pass ? 'text-gray-600 dark:text-gray-300' : 'text-gray-500';
    txt.textContent = c.label; // DATA
    if (!c.pass && c.hint) { const h = document.createElement('span'); h.className = 'text-gray-400'; h.textContent = ' — ' + c.hint; txt.appendChild(h); }
    row.appendChild(ic); row.appendChild(txt); box.appendChild(row);
  });
  const info = document.getElementById('rulesetInfo');
  info.textContent = 'Ruleset v' + (d.ruleset_version || 0) + ' (' + (d.ruleset_source || '') + ')';
}

// Pemicu debounce checklist.
if (document.getElementById('seoChecks')) {
  editor.addEventListener('input', scheduleChecklist);
  editor.addEventListener('blur', () => runChecklist(false));
  ['metaTitle','metaDesc','focusKeyword'].forEach(id => { const el = document.getElementById(id); if (el) el.addEventListener('input', scheduleChecklist); });
  titleInput.addEventListener('input', scheduleChecklist);
}

// ═══════════════════ ANALISIS AI (berbayar) ═════════════════════
function runAnalysis() {
  const btn = document.getElementById('btnAnalysis');
  const msg = document.getElementById('analysisMsg');
  btn.disabled = true; btn.textContent = 'Menganalisis…';
  msg.className = 'text-xs mt-2 text-gray-500'; msg.classList.remove('hidden'); msg.textContent = 'Menghubungi AI…';
  const fd = new FormData(); fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id);
  fetch(APP.analysisUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => {
      if (d.ok) { msg.classList.add('hidden'); renderAnalysis(d); }
      else aiInlineMsg(msg, d);
    })
    .catch(() => aiInlineMsg(msg, { ok:false, message:'Gateway tidak dapat dihubungi.' }))
    .finally(() => { btn.disabled = false; btn.textContent = 'Analisis Ulang'; });
}

function renderAnalysis(d) {
  const box = document.getElementById('analysisResult'); box.innerHTML = ''; box.classList.remove('hidden');
  // Skor.
  const head = document.createElement('div'); head.className = 'flex items-center gap-2 mb-2';
  const sc = document.createElement('span'); sc.className = 'text-2xl font-semibold font-display';
  const val = (d.score === null || d.score === undefined) ? '–' : d.score;
  sc.textContent = val;
  const lbl = document.createElement('span'); lbl.className = 'text-xs text-gray-400'; lbl.textContent = 'skor AI \u00B7 ' + (d.word_count || 0) + ' kata';
  head.appendChild(sc); head.appendChild(lbl); box.appendChild(head);

  box.appendChild(listBlock('Masalah', d.issues || []));
  box.appendChild(listBlock('Saran', d.suggestions || []));

  // Keyword coverage.
  const kws = d.keywords || [];
  if (kws.length) {
    const wrap = document.createElement('div'); wrap.className = 'mt-2';
    const t = document.createElement('div'); t.className = 'text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1'; t.textContent = 'Cakupan keyword';
    wrap.appendChild(t);
    kws.forEach(k => {
      const row = document.createElement('div'); row.className = 'flex items-center gap-2 text-xs py-0.5';
      const nm = document.createElement('span'); nm.className = 'flex-1 truncate'; nm.textContent = String(k.keyword || '');
      row.appendChild(nm);
      [['T', k.in_title], ['H', k.in_headings], ['I', k.in_intro]].forEach(([lab, on]) => {
        const b = document.createElement('span');
        b.className = 'inline-flex items-center justify-center w-5 h-5 rounded text-[10px] font-bold ' + (on ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-gray-100 text-gray-400 dark:bg-gray-800');
        b.textContent = lab; b.title = { T:'di judul', H:'di heading', I:'di intro' }[lab];
        row.appendChild(b);
      });
      wrap.appendChild(row);
    });
    box.appendChild(wrap);
  }
}
function listBlock(title, items) {
  const wrap = document.createElement('div'); wrap.className = 'mt-2';
  const t = document.createElement('div'); t.className = 'text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1'; t.textContent = title;
  wrap.appendChild(t);
  if (!items.length) { const e = document.createElement('div'); e.className = 'text-xs text-gray-400'; e.textContent = '—'; wrap.appendChild(e); return wrap; }
  const ul = document.createElement('ul'); ul.className = 'list-disc pl-4 space-y-0.5';
  items.forEach(it => { const li = document.createElement('li'); li.className = 'text-xs'; li.textContent = String(it); ul.appendChild(li); });
  wrap.appendChild(ul); return wrap;
}

// ═══════════════════════════ FAQ ════════════════════════════════
let faqItems = [];
function runFaq() {
  const btn = document.getElementById('btnFaq');
  const msg = document.getElementById('faqMsg');
  btn.disabled = true; btn.textContent = 'Membuat…';
  msg.className = 'text-xs mt-2 text-gray-500'; msg.classList.remove('hidden'); msg.textContent = 'Menghubungi AI…';
  const fd = new FormData(); fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id);
  fetch(APP.faqUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => {
      if (d.ok) {
        msg.classList.add('hidden');
        (d.faqs || []).forEach(f => faqItems.push({ q: f.q, a: f.a, approved: false }));
        renderFaq();
      } else aiInlineMsg(msg, d);
    })
    .catch(() => aiInlineMsg(msg, { ok:false, message:'Gateway tidak dapat dihubungi.' }))
    .finally(() => { btn.disabled = false; btn.textContent = 'Generate FAQ'; });
}

function renderFaq() {
  const box = document.getElementById('faqList'); box.innerHTML = '';
  document.getElementById('btnFaqSave').classList.toggle('hidden', faqItems.length === 0);
  faqItems.forEach((item, i) => {
    const card = document.createElement('div'); card.className = 'rounded-md border border-gray-200 dark:border-gray-800 p-2';
    const top = document.createElement('div'); top.className = 'flex items-center gap-2 mb-1';
    const cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = !!item.approved;
    cb.onchange = () => { item.approved = cb.checked; };
    const cbl = document.createElement('span'); cbl.className = 'text-xs text-gray-500'; cbl.textContent = 'Approve';
    const del = document.createElement('button'); del.type = 'button'; del.className = 'ml-auto text-gray-400 hover:text-red-600 text-xs'; del.textContent = '\u2715 hapus';
    del.onclick = () => { faqItems.splice(i, 1); renderFaq(); };
    top.appendChild(cb); top.appendChild(cbl); top.appendChild(del);
    const q = document.createElement('input'); q.className = 'w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-2 py-1 text-sm font-medium mb-1'; q.value = item.q || ''; q.placeholder = 'Pertanyaan';
    q.oninput = () => { item.q = q.value; };
    const a = document.createElement('textarea'); a.className = 'w-full rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-2 py-1 text-sm'; a.rows = 2; a.value = item.a || ''; a.placeholder = 'Jawaban';
    a.oninput = () => { item.a = a.value; };
    card.appendChild(top); card.appendChild(q); card.appendChild(a); box.appendChild(card);
  });
}

function saveFaq() {
  const btn = document.getElementById('btnFaqSave');
  const msg = document.getElementById('faqMsg');
  btn.disabled = true;
  const fd = new FormData(); fd.append('csrf_token', APP.csrf); fd.append('article_id', APP.id);
  fd.append('items', JSON.stringify(faqItems));
  fetch(APP.faqSaveUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json()).then(d => {
      if (d.ok) {
        syncFaqBlock(d.faq_block || '');
        markDirty();
        aiInlineMsg(msg, { ok:true, message:'FAQ tersimpan. Blok FAQ diperbarui di konten.' });
      } else aiInlineMsg(msg, d);
    })
    .catch(() => aiInlineMsg(msg, { ok:false, message:'Gagal menyimpan FAQ.' }))
    .finally(() => { btn.disabled = false; });
}

// Sinkron blok FAQ di editor (mirror server; inner sudah tersanitasi).
function syncFaqBlock(inner) {
  editor.querySelectorAll('section[data-faq-block="1"]').forEach(s => s.remove());
  if (inner && inner.trim() !== '') {
    const sec = document.createElement('section');
    sec.setAttribute('data-faq-block', '1');
    sec.innerHTML = inner; // aman: tersanitasi server-side
    editor.appendChild(sec);
  }
  scheduleChecklist();
}

// ── Inisialisasi A-06 ────────────────────────────────────────
(function initSeo() {
  if (!APP.id) return;
  if (APP.initialAnalysis) renderAnalysis(APP.initialAnalysis);
  if (APP.initialFaq && APP.initialFaq.length) {
    faqItems = APP.initialFaq.map(f => ({ q: f.q, a: f.a, approved: !!f.approved }));
    renderFaq();
  } else if (APP.initialOutline && (APP.initialOutline.faq_selected || []).length) {
    faqItems = APP.initialOutline.faq_selected.map(q => ({ q: String(q), a: '', approved: false }));
    renderFaq();
  }
  runChecklist(false); // skor awal
})();

// Popover "Rincian biaya" — vanilla, tutup saat klik luar / Esc.
function toggleCostInfo(e) {
  if (e) e.stopPropagation();
  var pop = document.getElementById('costInfoPop');
  if (!pop) return;
  var btn = e && e.currentTarget;
  var open = pop.classList.toggle('hidden') === false;
  if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
}
document.addEventListener('click', function (e) {
  var pop = document.getElementById('costInfoPop');
  if (pop && !pop.classList.contains('hidden') && !pop.contains(e.target)) pop.classList.add('hidden');
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') { var p = document.getElementById('costInfoPop'); if (p) p.classList.add('hidden'); }
});
</script>
<?php admin_shell_bottom(); ?>
