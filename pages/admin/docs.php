<?php
// ════════════════════════════════════════════════════════════════════════
// Dokumentasi produk (bantuan pemakaian) — Averion SEO Engine.
// Data-driven: array $docs jadi SATU sumber untuk nav kiri, kartu section,
// dan indeks pencarian (tak mungkin desync). Bahasa Indonesia. Akses staff.
// Konten hardcode → ikut rilis & self-update. Tanpa DB.
// ════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/../../helpers/ai-balance.php'; // aiTaskCost() untuk biaya kredit dinamis
require_once __DIR__ . '/../../helpers/mailketing.php'; // scribeCronUrl() untuk dokumentasi Cron

$appName = getSetting('app_name', 'Averion SEO Engine');

// Biaya kredit NYATA (mengikuti gateway lewat aiTaskCost) → dokumentasi selalu akurat.
$cKw = aiTaskCost('keyword_research'); $cOut = aiTaskCost('outline'); $cDraft = aiTaskCost('draft_section');
$cMeta = aiTaskCost('meta'); $cFaq = aiTaskCost('faq'); $cAna = aiTaskCost('analysis');
$cArticle = $cKw + $cOut + 6 * $cDraft + $cMeta + $cFaq + $cAna;

// ─── Komponen konten (DNA SEO Engine: radius kecil, --accent, SVG inline, tanpa emoji) ───
$mono = fn(string $c): string => '<code class="px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-[13px] font-mono text-gray-800 dark:text-gray-200">' . $c . '</code>';
$kbd  = fn(string $k): string => '<kbd class="px-1.5 py-0.5 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-[11px] font-mono text-gray-600 dark:text-gray-300">' . $k . '</kbd>';
$h3   = fn(string $t): string => '<h3 class="font-semibold text-gray-900 dark:text-white mt-5 mb-2 text-[15px]">' . $t . '</h3>';
$para = fn(string $t): string => '<p class="mb-3">' . $t . '</p>';
$ext  = fn(string $url, string $text): string => '<a href="' . $url . '" target="_blank" rel="noopener" class="underline" style="color:var(--accent)">' . $text . '</a>';
$ul   = fn(array $items): string => '<ul class="list-disc pl-5 space-y-1.5 mb-4">' . implode('', array_map(fn($i) => '<li>' . $i . '</li>', $items)) . '</ul>';

$check = function (array $items): string {
    $ico = '<svg class="w-4 h-4 shrink-0 mt-0.5" style="color:var(--accent)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
    $o = '<ul class="space-y-1.5 mb-4">';
    foreach ($items as $i) $o .= '<li class="flex gap-2.5">' . $ico . '<span>' . $i . '</span></li>';
    return $o . '</ul>';
};
$steps = function (array $items): string {
    $o = '<div class="space-y-1 mb-4">';
    foreach ($items as $i => [$title, $desc]) {
        $o .= '<div class="flex gap-3.5 py-2">'
            . '<div class="w-7 h-7 rounded-md text-white shrink-0 flex items-center justify-center text-[13px] font-bold" style="background:var(--accent)">' . ($i + 1) . '</div>'
            . '<div class="pt-0.5 min-w-0"><p class="font-medium text-gray-800 dark:text-gray-100">' . $title . '</p>'
            . ($desc !== '' ? '<p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">' . $desc . '</p>' : '') . '</div></div>';
    }
    return $o . '</div>';
};
$callout = function (string $type, string $html): string {
    // type: info (aksen) | warn (amber)
    if ($type === 'warn') {
        $ico = '<svg class="w-4 h-4 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>';
        return '<div class="flex gap-2.5 rounded-lg border border-amber-200 dark:border-amber-800/60 bg-amber-50 dark:bg-amber-950/30 p-3.5 mb-4"><span>' . $ico . '</span><p class="text-sm text-amber-800 dark:text-amber-200">' . $html . '</p></div>';
    }
    $ico = '<svg class="w-4 h-4 shrink-0 mt-0.5" style="color:var(--accent)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';
    return '<div class="flex gap-2.5 rounded-lg border p-3.5 mb-4" style="border-color:color-mix(in srgb, var(--accent) 26%, transparent); background:color-mix(in srgb, var(--accent) 7%, transparent)"><span>' . $ico . '</span><p class="text-sm text-gray-700 dark:text-gray-200">' . $html . '</p></div>';
};
$info = fn(string $h): string => $GLOBALS['__doc_callout']('info', $h);
$warn = fn(string $h): string => $GLOBALS['__doc_callout']('warn', $h);
$GLOBALS['__doc_callout'] = $callout;

// Tabel ringkas bertema (radius kecil, border halus, scroll-x di layar sempit).
$table = function (array $head, array $rows): string {
    $th = '';
    foreach ($head as $h) {
        $th .= '<th class="text-left font-semibold px-3 py-2 border-b border-gray-200 dark:border-gray-700 whitespace-nowrap">' . $h . '</th>';
    }
    $body = '';
    foreach ($rows as $r) {
        $tds = '';
        foreach ($r as $c) {
            $tds .= '<td class="px-3 py-2 align-top border-b border-gray-100 dark:border-gray-800">' . $c . '</td>';
        }
        $body .= '<tr>' . $tds . '</tr>';
    }
    return '<div class="overflow-x-auto mb-4 rounded-lg border border-gray-200 dark:border-gray-700">'
        . '<table class="w-full text-[13px] border-collapse"><thead class="bg-gray-50 dark:bg-gray-800/50"><tr>'
        . $th . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
};

$codeCounter = 0;
$code = function (string $c, string $lang = '') use (&$codeCounter): string {
    $id = 'doccode' . (++$codeCounter);
    $copyIco = icon('copy', 'w-3.5 h-3.5');
    // Latar gelap via INLINE style + tanpa class `border` agar TIDAK terkena aturan
    // surface tema ([data-ui-theme] main .rounded-lg.border → var(--surface-bg) !important),
    // supaya kode block tetap gelap & terbaca di light maupun dark mode.
    return '<div class="relative group mb-4">'
        . '<pre id="' . $id . '" class="doc-code rounded-lg p-3.5 pr-12 text-[12.5px] font-mono overflow-x-auto whitespace-pre leading-relaxed" style="background:#0b1020;color:#e5e7eb;border:1px solid #1f2937">' . $c . '</pre>'
        . '<button type="button" data-copy="#' . $id . '" class="doc-copy absolute top-2.5 right-2.5 inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium bg-gray-800 text-gray-200 hover:bg-gray-700 border border-gray-700">' . $copyIco . '<span class="doc-copy-label">Salin</span></button>'
        . '</div>';
};

// Endpoint nyata (terisi domain instalasi) untuk prompt integrasi.
$apiBase   = rtrim(APP_URL, '/');
$ingestUrl = $apiBase . '/api/ingest-article.php';
// URL Cron (rahasia) hanya dibentuk/ditampilkan untuk admin.
$cronUrl   = isAdmin() ? scribeCronUrl() : '';
$cronCmd   = $cronUrl !== '' ? 'curl -s "' . $cronUrl . '" >/dev/null 2>&1' : '';

// Prompt SIAP-COPAS untuk AI yang MENYETEL agen Hermes (bukan persona Hermes).
$hermesIngestPrompt = <<<TXT
Tujuan: hubungkan agen konten "Hermes" ke Averion SEO Engine untuk mengirim artikel
jadi lewat Ingest API. Default aman: artikel masuk sebagai Draft AI untuk ditinjau.
Opsional (mode otonom): Hermes boleh menerbitkan sendiri. Gunakan kontrak di bawah
sebagai sumber kebenaran saat menyetel Hermes.

(Opsional) Cek dulu apa yang aktif:
  GET {$apiBase}/api/capabilities.php
  Header: Authorization: Bearer {INGEST_TOKEN}
  -> manage=true berarti boleh publish; publish_min_score = 80.

Setel Hermes untuk mengirim HTTP request ini setiap kali satu artikel selesai:
  POST {$ingestUrl}
  Header:
    Authorization: Bearer {INGEST_TOKEN}
    Content-Type: application/json
  Body (JSON):
    {
      "title": "<judul>",                         // wajib
      "content_html": "<isi artikel HTML>",       // wajib (disanitasi otomatis)
      "external_ref": "<id unik & stabil>",       // wajib, anti-dobel
      "excerpt": "<ringkasan singkat>",
      "focus_keyword": "<kata kunci utama>",
      "related_keywords": ["...", "..."],
      "meta_title": "<45-60 karakter>",
      "meta_description": "<120-160 karakter>",
      "slug": "<opsional>",
      "search_intent": "informational",           // informational|transactional|navigational
      "language": "id",
      "category": "<nama kategori yang SUDAH ada>",
      "tags": ["...", "..."],
      "publish": false                            // true = terbit otomatis bila skor >= 80 (butuh Manajemen aktif)
    }

Pastikan Hermes mematuhi aturan berikut:
  1. Mode default (publish:false / tanpa field publish): artikel masuk Draft AI
     (status draft_ai) untuk ditinjau admin. Mode otonom (publish:true): tayang
     otomatis HANYA bila skor kesehatan >= 80; bila kurang, tetap Draft AI beserta
     daftar "issues" untuk diperbaiki.
  2. Selalu sertakan external_ref unik & stabil per artikel. Mengirim ulang ref yang
     sama = mengembalikan artikel yang sama (bukan duplikat). Untuk MENGUBAH isi
     artikel lama, pakai Management API (content-health), bukan kirim ulang ingest.
  3. category harus cocok nama/slug kategori yang sudah ada; bila tidak, dikosongkan.
  4. tags dibuat otomatis bila belum ada. content_html boleh HTML (tag berbahaya dibuang).
  5. Satu artikel per request.

Tangani respons:
  - 201 sukses: {"ok":true,"article_id":..,"slug":"..","status":"draft_ai|published",
    "published":true|false,"edit_url":"..","public_url":".."}. Bila publish diminta,
    ada juga "seo_score","seo_level","issues","publish_note" -> jika belum tayang,
    perbaiki sesuai issues lalu (untuk artikel yang sudah ada) terbitkan via
    Management API.
  - 200 idempoten: external_ref sudah ada (artikel yang sama dikembalikan).
  - Gagal {"ok":false,"error":".."}: 401 (token / header Authorization tak diteruskan
    server), 403 (Ingest nonaktif, atau Manajemen nonaktif untuk publish), 413 (>5MB),
    415 (content-type), 422 (validasi), 429 (rate-limit).

Untuk alur otonom penuh (pilih target dari data Google Search Console lalu tulis,
terbitkan, dan optimasi), lihat prompt Autopilot.
TXT;

$hermesManagePrompt = <<<TXT
Tujuan: setel agen "Hermes" untuk MENGOPTIMASI dan (opsional) MENERBITKAN artikel
di Averion SEO Engine via Management API. Prasyarat: toggle "Manajemen & Publikasi"
aktif di panel, memakai token Ingest yang sama.

Alur per artikel yang perlu diperbaiki:
  1) Ambil audit + isi terbaru:
       GET {$apiBase}/api/content-health.php?id=<ARTICLE_ID>
       Header: Authorization: Bearer {INGEST_TOKEN}
       -> catat "updated_at" dari respons (dipakai sebagai expected_updated_at).
  2) Kirim hasil optimasi:
       POST {$apiBase}/api/content-health.php
       Header: Authorization: Bearer {INGEST_TOKEN} ; Content-Type: application/json
       Body (JSON):
         {
           "article_id": <ARTICLE_ID>,
           "expected_updated_at": "<updated_at dari langkah 1>",
           "content_html": "<isi hasil optimasi>",
           "meta_title": "<45-60 karakter>",
           "meta_description": "<120-160 karakter>",
           "related_keywords": ["...", "..."],
           "faq": [ {"question":"..","answer":".."} ],   // maks 20; mengganti FAQ lama, langsung approved
           "dry_run": false,                              // true = validasi tanpa menulis
           "publish": true                                // terbit otomatis bila skor >= 80
         }

Aturan:
  - expected_updated_at WAJIB sama dengan hasil GET terbaru. Bila artikel sudah
    berubah, server menolak (cegah timpa) -> GET ulang lalu kirim lagi.
  - Untuk konten LIVE (publish, atau optimasi artikel published) skor minimal 80.
    Bila kurang, respons 422 + health_after -> perbaiki lalu kirim ulang.
  - Gunakan dry_run:true lebih dulu untuk memvalidasi tanpa menulis.
  - Endpoint tulis butuh toggle "Manajemen & Publikasi" aktif.
TXT;

$hermesAutopilotPrompt = <<<TXT
Tujuan: jalankan agen "Hermes" sebagai AUTOPILOT konten penuh untuk Averion SEO
Engine — riset, menulis, MENERBITKAN, dan mengoptimasi sendiri berdasarkan data
nyata Google Search Console, dengan campur tangan manual seminimal mungkin.
Semua request memakai header: Authorization: Bearer {INGEST_TOKEN}
Prasyarat panel: Ingest API + "Manajemen & Publikasi" aktif; Search Performance
(Google Search Console) tersambung.

Langkah 0 - Kenali kapabilitas (sekali di awal sesi):
  GET {$apiBase}/api/capabilities.php
  -> pastikan manage=true & search_performance=true. Catat publish_min_score (80)
     dan ruleset_version.

Langkah 1 - Pilih pekerjaan dari DATA NYATA (bukan tebakan):
  UTAMA - antrean siap-prioritas (gabungan GSC + umur + skor):
    GET {$apiBase}/api/content-refresh.php
    -> items[] urut prioritas, tiap item punya article_id + reasons[] + metrik.
  RINCI (opsional) - filter mentah Search Console:
    GET {$apiBase}/api/search-performance.php?filter=page2|lowctr|zero
    -> (opsional) tambahkan ?refresh=1 sekali untuk data terbaru (maks 1x / 6 jam).
  -> susun daftar target (article_id) untuk dioptimasi + celah topik untuk artikel baru.

Langkah 2A - Artikel BARU (tulis sendiri lalu terbitkan otomatis):
  POST {$apiBase}/api/ingest-article.php
  Body (JSON): {
    "title","content_html","external_ref"(unik & stabil),"focus_keyword",
    "meta_title"(45-60),"meta_description"(120-160),"related_keywords":[...],
    "search_intent","category"(harus SUDAH ada),"tags":[...],
    "publish": true
  }
  -> Bila skor kesehatan >= 80 artikel LANGSUNG tayang (status "published").
     Bila < 80: tersimpan draft_ai + daftar "issues" -> perbaiki lalu lanjut Langkah 2B.

Langkah 2B - Optimasi / terbitkan artikel yang SUDAH ada:
  GET  {$apiBase}/api/content-health.php?id=<ARTICLE_ID>   -> catat "updated_at"
  POST {$apiBase}/api/content-health.php
  Body: {
    "article_id":<id>, "expected_updated_at":"<updated_at>",
    "content_html","meta_title","meta_description","related_keywords":[...],
    "faq":[{"question":"..","answer":".."}], "publish": true
  }
  -> Skor minimal 80 untuk konten LIVE; bila kurang, respons 422 + health_after.

Aturan:
  1. external_ref unik & stabil per artikel (anti-duplikat). Untuk MENGUBAH isi
     artikel lama gunakan Langkah 2B (content-health), JANGAN kirim ulang ingest.
  2. category wajib nama/slug yang SUDAH ada; tags dibuat otomatis.
  3. Hormati publish_min_score (80). Jangan spam ?refresh.
  4. Loop: pantau Search Performance berkala -> perbaiki yang lemah, isi celah topik.
TXT;

// ═══════════════════════ DATA DOKUMENTASI (satu sumber) ═══════════════════════
$docs = [
    [
        'cat' => 'Menulis & SEO',
        'topics' => [
            [
                'id' => 'editor', 'title' => 'Editor Artikel',
                'desc' => 'Buat & sunting artikel: judul, konten, cover, kategori, tag, status, dan SEO.',
                'body' =>
                    $para('Buka lewat sidebar <strong>Artikel</strong> → tombol <strong>Artikel Baru</strong>, atau klik salah satu artikel untuk menyuntingnya.')
                    . $steps([
                        ['Judul & Slug', 'Slug (URL) dibuat otomatis dari judul; boleh diubah manual sebelum diterbitkan.'],
                        ['Tulis konten', 'Editor dengan toolbar: Heading (H2/H3), tebal, miring, daftar, kutipan, tautan, dan gambar. Tempelan dari Word/Google Docs dibersihkan otomatis.'],
                        ['Cover', 'Unggah gambar sampul, atau biarkan kosong untuk memakai cover fallback otomatis.'],
                        ['Kategori & Tag', 'Pilih satu kategori dan tambahkan tag (ketik lalu tekan Enter).'],
                        ['SEO', 'Isi Focus Keyword, Meta Title (55–60 karakter), dan Meta Description (120–160) — ada penghitung warna sebagai panduan.'],
                        ['Status', 'Draft, Draft AI, Terjadwal (atur tanggal terbit), atau Published.'],
                    ])
                    . $h3('Autosave & Pulihkan')
                    . $para('Draft tersimpan otomatis tiap ~45 detik dan saat Anda berpindah jendela. Bila ada draft belum tersimpan dari sesi sebelumnya, muncul banner dengan tombol ' . $mono('Pulihkan') . ' / ' . $mono('Buang') . '.')
                    . $h3('Pratinjau')
                    . $para('Tombol <strong>Pratinjau</strong> membuka tampilan artikel di tab baru memakai template publik asli — tanpa perlu menerbitkan lebih dulu.')
                    . $info('Simpan artikel terlebih dahulu sebelum memakai panel <strong>AI Assist</strong> (riset, outline, draft, meta).'),
            ],
            [
                'id' => 'ai-assist', 'title' => 'AI Assist',
                'desc' => 'Riset keyword, outline, tulis draft per-seksi, dan meta — langsung di dalam editor.',
                'body' =>
                    $para('Panel <strong>AI Assist</strong> ada di editor artikel (buka artikel yang sudah disimpan). Alurnya empat langkah:')
                    . $steps([
                        ['Riset Keyword (~' . $cKw . ' kredit)', 'Isi Focus Keyword → <strong>Riset Keyword</strong> → dapat keyword turunan, klaster topik, dan search intent → centang yang relevan → <strong>Simpan ke Artikel</strong>.'],
                        ['Outline (~' . $cOut . ' kredit)', '<strong>Generate Outline</strong> membuat kerangka H2/H3 yang bisa disunting penuh (tambah/hapus/ubah seksi) beserta saran FAQ.'],
                        ['Tulis Draft (~' . $cDraft . ' kredit/seksi)', 'Menulis isi <strong>satu seksi per permintaan</strong> (anti-timeout). Perkiraan biaya total tampil di tombol sebelum mulai.'],
                        ['Generate Meta (~' . $cMeta . ' kredit)', 'Membuat Meta Title & Meta Description otomatis dari isi artikel.'],
                    ])
                    . $info('Setiap langkah memotong kredit sesuai estimasi (lihat <strong>Rincian biaya</strong>). Bila <strong>BYOK</strong> aktif, kredit tidak dipotong. Lihat topik <a href="#kredit-byok" class="doc-nav underline" style="color:var(--accent)">Kredit AI & BYOK</a>.')
                    . $warn('Selalu tinjau hasil AI. Artikel dari AI masuk berstatus <strong>Draft AI</strong> dan tidak pernah otomatis tayang — Anda yang menerbitkan.')
                    . $h3('Internal Link (gratis)')
                    . $para('Tombol <strong>Internal Link → Cari saran</strong> mencari artikel published lain yang <strong>topiknya sudah Anda sebut</strong> di tulisan, lalu menawarkannya sebagai tautan. Centang yang relevan → <strong>Sisipkan terpilih</strong> (link ditaruh pada kemunculan pertama frasa, bukan di heading atau teks yang sudah bertaut). <strong>Lokal &amp; tanpa kredit</strong> — membantu memenuhi aturan SEO tautan internal. Lihat topik <a href="#internal-link" class="doc-nav underline" style="color:var(--accent)">Internal Link</a>.')
                    . $h3('Alt Text Gambar (gratis)')
                    . $para('Tombol <strong>Alt Text Gambar → Isi alt text</strong> mengisi atribut <em>alt</em> gambar yang <strong>kosong</strong> — diambil dari caption/judul gambar, nama file yang dibersihkan, lalu kata kunci sebagai cadangan. Alt yang sudah ada tidak diubah. <strong>Lokal &amp; tanpa kredit</strong>, membantu aturan SEO &amp; aksesibilitas. Artikel yang masuk via API (Hermes) juga <strong>otomatis</strong> terisi alt-nya saat diterima.'),
            ],
            [
                'id' => 'internal-link', 'title' => 'Internal Link',
                'desc' => 'Saran & sisip tautan ke artikel lain secara otomatis — lokal, tanpa kredit.',
                'body' =>
                    $para('<strong>Tautan internal</strong> (dari satu artikel ke artikel lain di situs Anda) menguatkan SEO: membantu Google memahami hubungan topik, menyebar otoritas, dan menahan pembaca lebih lama. Fitur ini menyarankan tautan dari <strong>frasa yang memang sudah ada</strong> di tulisan Anda ke artikel relevan — <strong>tanpa kredit AI</strong>.')
                    . $h3('Di editor')
                    . $steps([
                        ['Cari saran', 'Buka artikel → panel <strong>AI Assist → Internal Link → Cari saran</strong>. Muncul daftar artikel lain yang topiknya Anda sebut, beserta anchor-nya.'],
                        ['Pilih', 'Centang saran yang relevan (semua tercentang secara default).'],
                        ['Sisipkan', '<strong>Sisipkan terpilih</strong> → tautan dipasang pada <strong>kemunculan pertama</strong> frasa. Tinjau lalu Simpan artikel.'],
                    ])
                    . $info('Aman: tautan tidak dipasang di dalam heading, blok kode, atau teks yang sudah bertaut; satu tautan per artikel target. Artikel yang slug-nya sudah Anda tautkan tidak disarankan ulang.')
                    . $h3('Untuk agen otomatis (Hermes)')
                    . $para('Daftar yang sama tersedia di ' . $mono('GET /api/internal-links.php?article_id=') . ' (memberi ' . $mono('anchor') . '+' . $mono('url') . '), sehingga agen dapat menautkan sendiri saat menulis atau mengoptimasi. Lihat topik <a href="#autopilot" class="doc-nav underline" style="color:var(--accent)">Autopilot</a>.')
                    . $warn('Agar ada saran, <strong>sebut topik artikel lain</strong> di tulisan Anda (mis. judul/kata kunci utamanya). Semakin banyak artikel published, semakin kaya sarannya.'),
            ],
            [
                'id' => 'alt-text', 'title' => 'Alt Text Gambar',
                'desc' => 'Isi otomatis atribut alt gambar yang kosong — lokal, tanpa kredit.',
                'body' =>
                    $para('Atribut <strong>alt</strong> pada gambar penting untuk <strong>SEO gambar</strong> dan <strong>aksesibilitas</strong> (dan termasuk dalam skor SEO). Fitur ini mengisi alt yang <strong>kosong</strong> secara otomatis — <strong>tanpa kredit</strong>.')
                    . $h3('Di editor')
                    . $para('Panel <strong>AI Assist → Alt Text Gambar → Isi alt text</strong>. Alt diambil dari sumber paling bermakna berurutan: <strong>caption</strong> gambar → <strong>nama file</strong> yang dibersihkan → <strong>kata kunci/judul</strong> artikel sebagai cadangan. Alt yang <strong>sudah ada tidak diubah</strong>.')
                    . $h3('Otomatis via API (Hermes)')
                    . $para('Artikel yang masuk lewat Ingest API otomatis terisi alt gambarnya saat diterima, sehingga lebih siap untuk publish otomatis.')
                    . $info('Untuk hasil terbaik, beri <strong>nama file yang deskriptif</strong> sebelum mengunggah gambar (mis. ' . $mono('grafik-trafik-organik.jpg') . ') — jauh lebih baik daripada ' . $mono('IMG_1234.jpg') . '.'),
            ],
            [
                'id' => 'seo-score', 'title' => 'SEO Score & Aturan',
                'desc' => 'Skor SEO dua lapis: aturan teknis lokal + Analisis AI, plus halaman SEO Health.',
                'body' =>
                    $para('Skor SEO dihitung <strong>dua lapis</strong>:')
                    . $check([
                        '<strong>Ruleset lokal</strong> — checklist teknis instan (panjang meta, keyword di judul & paragraf awal, struktur heading, tautan, gambar ber-alt, dll). Tanpa kredit.',
                        '<strong>Analisis AI</strong> (~' . $cAna . ' kredit) — menilai kualitas, kedalaman, dan relevansi konten terhadap keyword.',
                    ])
                    . $para('Checklist tampil langsung di editor menandai poin yang lolos dan yang masih kurang.')
                    . $h3('SEO Health')
                    . $para('Halaman <strong>SEO Health</strong> (sidebar) mengaudit seluruh artikel sekaligus: skor tiap artikel, kekurangan, dan saran perbaikan — praktis untuk membenahi konten lama.')
                    . $info('Untuk konten yang diterbitkan lewat API optimasi, skor minimal <strong>80</strong> disyaratkan sebelum artikel bisa tayang otomatis.'),
            ],
            [
                'id' => 'search-performance', 'title' => 'Search Performance (Google Search Console)',
                'desc' => 'Data nyata Google — klik, impresi, CTR, dan posisi per artikel.',
                'body' =>
                    $para('Tab <strong>Search Performance</strong> di halaman <strong>SEO Health</strong> menampilkan data asli dari Google Search Console per artikel: klik, impresi, CTR, dan posisi rata-rata — untuk tahu artikel mana yang sudah tayang tapi belum menghasilkan klik.')
                    . $info('<strong>Gratis.</strong> Google Search Console API tidak berbayar dan <strong>tidak perlu mengaktifkan Billing</strong> di Google Cloud. Kuotanya besar; produk hanya menariknya sesekali.')
                    . $para('<span class="text-sm text-gray-500 dark:text-gray-400">Prasyarat: situs Anda sudah <strong>terverifikasi</strong> di Google Search Console, dan Anda punya akun Google Cloud.</span>')
                    . $h3('Langkah 1 — Buat Service Account + kunci JSON (Google Cloud)')
                    . $para('Produk memakai <strong>Service Account</strong> (bukan "API key" biasa), dan kuncinya dibuat di menu <strong>Credentials</strong> — bukan di halaman detail API.')
                    . $steps([
                        ['Buat service account', 'Buka ' . $ext('https://console.cloud.google.com/apis/credentials', 'APIs &amp; Services → Credentials') . ' → <strong>+ Create credentials → Service account</strong>. Beri nama (mis. ' . $mono('seo-engine-reader') . ') → <strong>Create and continue</strong> → bagian <em>role</em> boleh dilewati → <strong>Done</strong>.'],
                        ['Aktifkan API', 'Aktifkan ' . $ext('https://console.cloud.google.com/apis/library/searchconsole.googleapis.com', 'Google Search Console API') . ' (tombol <strong>Enable</strong>).'],
                        ['Unduh kunci JSON', 'Klik service account tadi → tab <strong>Keys → Add key → Create new key → JSON → Create</strong>. File ' . $mono('.json') . ' otomatis terunduh — <strong>ini rahasia, jangan dibagikan</strong>.'],
                    ])
                    . $h3('Langkah 2 — Beri akses di Search Console')
                    . $steps([
                        ['Salin email service account', 'Di dalam file JSON ada ' . $mono('client_email') . ' (format ' . $mono('nama@proyek.iam.gserviceaccount.com') . ').'],
                        ['Tambahkan sebagai pengguna', 'Buka ' . $ext('https://search.google.com/search-console/users', 'Search Console → Setelan → Pengguna dan izin') . ' → <strong>Tambahkan pengguna</strong> → tempel email tadi → izin <em>Penuh</em> (atau <em>Terbatas</em> cukup untuk baca) → <strong>Tambahkan</strong>.'],
                    ])
                    . $h3('Langkah 3 — Hubungkan di SEO Engine')
                    . $steps([
                        ['Buka pengaturan koneksi', '<strong>SEO Health → tab Search Performance → Ubah pengaturan koneksi</strong>.'],
                        ['Isi kredensial', 'Tempel <strong>seluruh isi file JSON</strong> ke kolom <strong>Kunci Service Account</strong>, isi <strong>URL properti</strong> (lihat format di bawah), centang <strong>Aktifkan Search Performance</strong>, lalu <strong>Simpan &amp; Tes Koneksi</strong>. Bila berhasil, status menjadi <strong>Tersambung</strong>.'],
                        ['Tarik data', 'Klik <strong>Perbarui data</strong> untuk menarik metrik terbaru. Ulangi kapan saja — data di-cache (tanpa tabel &amp; tanpa cron).'],
                    ])
                    . $h3('Format URL properti')
                    . $check([
                        '<strong>Domain property</strong>: ' . $mono('sc-domain:contoh.com') . ' — mencakup semua subdomain &amp; protokol. Ciri: di Search Console propertinya tertulis <em>polos</em> tanpa ' . $mono('https://') . '.',
                        '<strong>URL-prefix property</strong>: ' . $mono('https://contoh.com/') . ' — persis seperti tertera di Search Console (dengan protokol).',
                    ])
                    . $h3('Membaca datanya')
                    . $ul([
                        '<strong>Belum dapat klik</strong> — artikel published yang belum menghasilkan klik: prioritas perbaikan.',
                        '<strong>CTR rendah</strong> — banyak impresi tapi sedikit klik: perbaiki Judul &amp; Meta Description agar lebih menarik diklik.',
                        '<strong>Posisi 11–20</strong> — sudah di halaman 2 Google: dorong sedikit lagi (perkuat konten &amp; internal link) untuk tembus halaman 1.',
                    ])
                    . $h3('Untuk agen otomatis (Autopilot)')
                    . $para('Setelah tersambung, data yang sama tersedia lewat API ' . $mono('GET /api/search-performance.php') . ' (filter ' . $mono('zero') . '/' . $mono('lowctr') . '/' . $mono('page2') . ') sehingga agen seperti Hermes bisa <strong>memilih target optimasi dari data nyata</strong>. Lihat topik <a href="#autopilot" class="doc-nav underline" style="color:var(--accent)">Autopilot (Hermes otonom)</a>.')
                    . $h3('Bila bermasalah')
                    . $ul([
                        'Tes koneksi gagal <strong>403</strong> → email service account belum ditambahkan sebagai <em>pengguna</em> di Search Console (Langkah 2).',
                        'Muncul "<em>Lengkapi service account dan URL properti sebelum mengaktifkan</em>" padahal sudah diisi → simpan ulang: bila JSON sudah tersimpan, biarkan kolomnya <strong>kosong</strong>, jangan ubah URL bersamaan saat mencentang Aktifkan. (Diperbaiki di versi terbaru.)',
                        'Data kosong / artikel baru belum muncul → Google menahan data <strong>~2–3 hari terakhir</strong>; tunggu lalu <strong>Perbarui data</strong>.',
                    ]),
            ],
            [
                'id' => 'perlu-diperbarui', 'title' => 'Perlu Diperbarui (antrean refresh)',
                'desc' => 'Daftar artikel berprioritas untuk dioptimasi — gabungan sinyal GSC, umur, dan skor SEO.',
                'body' =>
                    $para('Tab <strong>Perlu Diperbarui</strong> di halaman <strong>SEO Health</strong> menyusun artikel published yang paling layak diperbaiki lebih dulu, menggabungkan beberapa sinyal menjadi satu prioritas — tanpa kredit.')
                    . $h3('Sinyal yang dinilai')
                    . $ul([
                        '<strong>Posisi 11–20</strong> — di halaman 2 Google, dekat tembus halaman 1.',
                        '<strong>Posisi turun</strong> — peringkat memburuk dibanding data GSC sebelumnya (butuh minimal 2 kali Perbarui data).',
                        '<strong>CTR rendah</strong> — banyak impresi tapi sedikit klik: perbaiki judul &amp; meta.',
                        '<strong>Belum dapat klik</strong> — sudah tampil tapi nol klik.',
                        '<strong>Lama tidak diperbarui</strong> — lebih dari 180 hari.',
                        '<strong>Skor SEO di bawah 80</strong> — kelengkapan on-page kurang.',
                    ])
                    . $info('Tanpa koneksi Search Performance, antrean tetap bekerja memakai <strong>skor SEO &amp; umur</strong>. Sambungkan GSC (lihat topik Search Performance) agar sinyal klik/posisi/tren ikut dihitung — makin tajam prioritasnya.')
                    . $h3('Untuk agen otomatis (Autopilot)')
                    . $para('Daftar yang sama tersedia di ' . $mono('GET /api/content-refresh.php') . ' (urut prioritas, dengan ' . $mono('reasons') . ' + metrik). Agen seperti Hermes mengambilnya untuk memilih pekerjaan, lalu memperbaiki via ' . $mono('POST /api/content-health.php') . ' dan menerbitkan (skor &ge; 80). Lihat topik <a href="#autopilot" class="doc-nav underline" style="color:var(--accent)">Autopilot</a>.'),
            ],
            [
                'id' => 'kategori-tag', 'title' => 'Kategori & Tag',
                'desc' => 'Taksonomi konten: kategori (satu per artikel) dan tag (banyak).',
                'body' =>
                    $check([
                        '<strong>Kategori</strong> — satu artikel satu kategori. Kelola di ' . $mono('/admin/categories') . ' (khusus admin). Muncul di URL & navigasi frontend.',
                        '<strong>Tag</strong> — banyak per artikel. Tambah langsung di editor (ketik + Enter; tag baru dibuat otomatis) atau kelola di ' . $mono('/admin/tags') . '.',
                    ])
                    . $info('Kategori dan tag punya halaman arsip sendiri di frontend (' . $mono('/kategori/{slug}') . ', ' . $mono('/tag/{slug}') . ') dan ikut masuk sitemap.')
                    . $para('<span class="text-gray-500 dark:text-gray-400 text-sm">Catatan: penulis (writer) memilih kategori & menambah tag dari dalam editor; halaman kelola kategori/tag khusus admin.</span>'),
            ],
            [
                'id' => 'kredit-byok', 'title' => 'Kredit AI & BYOK',
                'desc' => 'Cara kerja kredit AI dan memakai API key sendiri (BYOK) tanpa memotong kredit.',
                'body' =>
                    $para('Setiap tugas AI memotong <strong>kredit granular</strong>. Satu artikel penuh tipikal ≈ <strong>' . $cArticle . ' kredit</strong>, dengan rincian:')
                    . $ul([
                        'Riset Keyword: ' . $cKw,
                        'Outline: ' . $cOut,
                        '6 × Tulis Draft: 6 × ' . $cDraft . ' = ' . (6 * $cDraft),
                        'Generate Meta: ' . $cMeta,
                        'FAQ: ' . $cFaq,
                        'Analisis AI: ' . $cAna,
                    ])
                    . $para('Saldo & pembelian kredit ada di menu <strong>Kredit AI</strong> (sidebar, khusus admin).')
                    . $h3('BYOK (Bring Your Own Key)')
                    . $para('Pakai API key <strong>Google Gemini</strong> atau <strong>Anthropic (Claude)</strong> milik sendiri di <strong>Pengaturan</strong>. Saat BYOK aktif, seluruh tugas AI memakai key Anda dan <strong>tidak memotong kredit</strong>. Key diverifikasi lalu disimpan terenkripsi di gateway — tidak pernah tersimpan di database situs Anda.')
                    . $h3('Mengambil API key')
                    . $steps([
                        ['Google Gemini (ada tier gratis)', 'Buka ' . $ext('https://aistudio.google.com/app/apikey', 'Google AI Studio → API keys') . ' → <strong>Create API key</strong> → salin. Tempel di <strong>Pengaturan → BYOK</strong> lalu simpan (key diverifikasi otomatis).'],
                        ['Anthropic (Claude)', 'Buka ' . $ext('https://console.anthropic.com/settings/keys', 'Anthropic Console → API Keys') . ' → <strong>Create Key</strong> → salin. Tempel di <strong>Pengaturan → BYOK</strong> lalu simpan.'],
                    ])
                    . $info('BYOK cocok untuk volume tinggi (kelola API key sendiri). <strong>Google Gemini punya tier gratis</strong>, jadi bisa dipakai tanpa biaya kredit sama sekali.'),
            ],
            [
                'id' => 'faq-related', 'title' => 'FAQ & Artikel Terkait',
                'desc' => 'Blok FAQ ber-schema dan artikel terkait untuk memperkaya halaman.',
                'body' =>
                    $h3('FAQ')
                    . $para('Saran FAQ muncul dari <strong>AI Assist → Outline</strong>. Setujui (approve) blok FAQ yang relevan → tampil di artikel sekaligus menghasilkan <strong>JSON-LD FAQ</strong> (peluang rich result di Google).')
                    . $h3('Artikel Terkait')
                    . $para('Artikel terkait ditampilkan otomatis di bawah artikel berdasarkan kategori/tag yang sama — menambah internal link dan waktu baca.')
                    . $info('FAQ yang disetujui masuk structured data, memperbesar peluang tampil sebagai rich snippet di hasil pencarian.'),
            ],
            [
                'id' => 'profil-penulis', 'title' => 'Profil Penulis (E-E-A-T)',
                'desc' => 'Halaman penulis publik + schema Person untuk memperkuat kepercayaan.',
                'body' =>
                    $para('Profil penulis memperkuat sinyal <strong>E-E-A-T</strong> (Experience, Expertise, Authoritativeness, Trust) — Google menilai siapa di balik konten. Setiap penulis punya <strong>halaman publik</strong> dan <strong>schema Person</strong> otomatis.')
                    . $steps([
                        ['Isi profil', '<strong>Profil</strong> (sidebar) → isi jabatan, bio, foto (avatar), dan tautan sosial. Setiap pengguna menyunting profilnya sendiri.'],
                        ['Halaman penulis', 'Publik di ' . $mono('/penulis/{nama}') . ' — menampilkan bio, foto, sosial, dan daftar artikelnya. Byline di setiap artikel menautkan ke sini.'],
                        ['Schema Person', 'Halaman penulis menyertakan JSON-LD <strong>Person</strong> (nama, foto, bio, jabatan, ' . $mono('sameAs') . '), dan penulis di schema artikel ditautkan ke halaman ini.'],
                    ])
                    . $info('Nama penulis diubah di <strong>Pengguna</strong> (admin); slug halaman mengikuti nama. Isi bio yang menunjukkan pengalaman/keahlian nyata untuk manfaat E-E-A-T maksimal.'),
            ],
        ],
    ],
    [
        'cat' => 'Situs & Tampilan',
        'topics' => [
            [
                'id' => 'appearance', 'title' => 'Appearance & Tema',
                'desc' => 'Atur identitas visual dashboard dan situs publik.',
                'body' =>
                    $para('Ubah tampilan lewat <strong>Pengaturan → Appearance</strong>.')
                    . $check([
                        '<strong>UI Theme</strong> — preset yang mengubah warna aksen, gaya sidebar/topbar, radius, dan bayangan. Diterapkan ke dashboard, Homepage Blog, dan BioLink sekaligus.',
                        '<strong>Rebrand</strong> — logo, favicon, nama blog, warna aksen, dan <strong>Profil Sosial</strong> (Pengaturan → Rebrand).',
                        '<strong>Mode gelap</strong> — tombol bulan/matahari di topbar; preferensi tersimpan per perangkat.',
                    ])
                    . $info('Perubahan tema langsung tampil di frontend publik, jadi identitas dashboard dan situs pengunjung tetap selaras.')
                    . $h3('Schema brand (otomatis)')
                    . $para('Homepage otomatis menyertakan <strong>JSON-LD WebSite + Organization</strong>: memperkuat pengenalan brand di Google dan membuka peluang <strong>kotak pencarian sitelinks</strong>. Isi <strong>Profil Sosial</strong> (satu URL per baris di Rebrand) untuk menambah ' . $mono('sameAs') . ' — tautan ke akun resmi brand. Logo brand juga ikut dipakai sebagai logo Organization.'),
            ],
            [
                'id' => 'biolink', 'title' => 'Biolink',
                'desc' => 'Halaman link-in-bio: banyak tautan penting dalam satu halaman.',
                'body' =>
                    $para('<strong>Biolink</strong> (sidebar) cocok untuk bio media sosial — kumpulan tautan dalam satu halaman.')
                    . $steps([
                        ['Buka builder', 'Menu Biolink → susun blok: foto profil, judul, dan tombol-tautan.'],
                        ['Atur blok', 'Tambah tombol link, ubah teks/URL, dan urutkan sesuai kebutuhan.'],
                        ['Tampilkan', 'Aktifkan lewat Mode Homepage (topik berikut) agar muncul di halaman depan.'],
                    ]),
            ],
            [
                'id' => 'home-mode', 'title' => 'Mode Homepage',
                'desc' => 'Pilih apa yang tampil di halaman depan: Blog, BioLink, atau Hybrid.',
                'body' =>
                    $para('Mode Homepage menentukan isi halaman depan (' . $mono('/') . '):')
                    . $check([
                        '<strong>Blog</strong> — daftar artikel terbaru.',
                        '<strong>BioLink</strong> — halaman link-in-bio.',
                        '<strong>Hybrid</strong> — gabungan BioLink dan feed artikel.',
                    ])
                    . $info('Saat memakai BioLink atau Hybrid, daftar artikel tetap dapat diakses di ' . $mono('/blog') . '.'),
            ],
            [
                'id' => 'redirects', 'title' => 'Redirects',
                'desc' => 'Kelola pengalihan URL (301/302) agar tidak kehilangan SEO.',
                'body' =>
                    $para('<strong>Redirects</strong> (sidebar) mengelola pengalihan URL.')
                    . $ul([
                        'Arahkan URL lama ke URL baru saat Anda mengubah slug atau struktur.',
                        'Menjaga peringkat SEO dan mencegah halaman 404 dari tautan lama.',
                    ])
                    . $info('Gunakan <strong>301</strong> (permanen) untuk perpindahan tetap; <strong>302</strong> (sementara) untuk yang akan dikembalikan.'),
            ],
        ],
    ],
    [
        'cat' => 'Audiens & Growth',
        'topics' => [
            [
                'id' => 'lead-magnet', 'title' => 'Lead Magnet',
                'desc' => 'Tukar konten/berkas dengan email untuk menumbuhkan subscriber.',
                'body' =>
                    $para('<strong>Lead Magnet</strong> (sidebar) = konten/berkas yang ditukar dengan email pengunjung.')
                    . $steps([
                        ['Buat magnet', 'Menu Lead Magnet → judul, deskripsi, dan berkas/isi yang diberikan.'],
                        ['Bagikan', 'Tiap magnet punya halaman publik ' . $mono('/lead-magnet/{slug}') . ' berisi form email.'],
                        ['Kumpulkan lead', 'Email yang masuk otomatis menjadi subscriber dan bisa ditindaklanjuti.'],
                    ]),
            ],
            [
                'id' => 'subscribers', 'title' => 'Subscribers',
                'desc' => 'Daftar pelanggan email dari newsletter dan Lead Magnet.',
                'body' =>
                    $para('<strong>Subscribers</strong> (sidebar) berisi pelanggan email Anda.')
                    . $ul([
                        'Lihat, cari, dan kelola subscriber.',
                        'Sumber (newsletter/magnet) tercatat untuk segmentasi.',
                    ])
                    . $info('Subscriber dapat dimasukkan ke Email Sequence untuk tindak lanjut otomatis.'),
            ],
            [
                'id' => 'email-sequences', 'title' => 'Email Sequence',
                'desc' => 'Rangkaian email otomatis (drip) bertahap ke subscriber.',
                'body' =>
                    $para('<strong>Email Sequence</strong> (sidebar) mengirim email bertahap secara otomatis.')
                    . $steps([
                        ['Buat sequence', 'Beri nama, kaitkan dengan Lead Magnet bila perlu, atur status aktif.'],
                        ['Susun step', 'Tiap step: jeda hari (delay), subjek, dan isi email (HTML). Tombol <strong>Pratinjau</strong> menampilkan tampilan email (placeholder diisi contoh) sebelum disimpan.'],
                        ['Kirim otomatis', 'Sistem mengirim sesuai jadwal; subscriber bisa berhenti berlangganan kapan saja.'],
                    ])
                    . $h3('Placeholder yang tersedia')
                    . $ul([
                        $mono('{{name}}') . ', ' . $mono('{{email}}') . ', ' . $mono('{{blog_name}}'),
                        $mono('{{article_title}}') . ', ' . $mono('{{article_url}}'),
                        $mono('{{lead_magnet_title}}') . ', ' . $mono('{{lead_magnet_url}}') . ', ' . $mono('{{unsubscribe_url}}'),
                    ])
                    . $info('Pengiriman butuh integrasi <strong>Mailketing</strong> diatur di Pengaturan → Integrasi.')
                    . $h3('Pemrosesan otomatis & Cron')
                    . $para('Antrean email diproses otomatis <strong>setelah setiap pendaftaran</strong> dan saat ada <strong>kunjungan halaman</strong> (publik maupun admin) — berjalan di latar belakang setelah respons dikirim, jadi halaman tidak menunggu provider. Tiap percobaan dicatat (maksimal 5×) dengan pencegahan kirim ganda; hasilnya terlihat di <strong>Log worker</strong> pada halaman Subscribers.')
                    . $para('Untuk situs dengan lalu lintas rendah, pasang <strong>Cron</strong> agar antrean tetap jalan tanpa menunggu pengunjung. Jadwalkan URL Cron tiap 5 menit di <strong>cPanel → Cron Jobs</strong> (' . $mono('*/5 * * * *') . ').')
                    . ($cronUrl !== ''
                        ? $para('URL Cron instalasi ini (<strong>rahasia</strong> — perlakukan seperti kata sandi):') . $code(e($cronUrl))
                            . $para('Perintah untuk kolom Command di cPanel:') . $code(e($cronCmd))
                            . $para('Bila kunci bocor, hapus setting ' . $mono('cron_key') . ' (via database) untuk memutarnya.')
                        : $info('URL Cron bersifat rahasia dan hanya ditampilkan untuk admin.'))
                    . $info('Status <strong>pending</strong> berarti antrean belum selesai diproses (bukan tentu gagal). Status <strong>failed</strong> setelah timeout berarti respons provider tidak diterima — email <em>mungkin</em> tetap terkirim, sengaja tidak diulang agar tidak dobel. Cek <strong>Log worker</strong> untuk respons asli provider (kode HTTP + pesan).'),
            ],
            [
                'id' => 'meta-pixel', 'title' => 'Meta Pixel',
                'desc' => 'Ukur kunjungan Blog & BioLink dengan Pixel Meta/Facebook.',
                'body' =>
                    $para('<strong>Meta Pixel</strong> (Pengaturan → Integrasi) memasang Pixel untuk pengukuran.')
                    . $check([
                        'Pasang beberapa Pixel ID (satu per baris), maksimal 20.',
                        'Pilih area tracking: Blog, BioLink, atau keduanya.',
                        'Opsi lacak klik tombol BioLink, CTA header, CTA artikel, dan Popup Promo.',
                    ])
                    . $warn('Tracking hanya berjalan setelah pengunjung menyetujui. Admin, pengguna login, dan preview tidak dilacak. Nonaktifkan pemasangan Pixel manual lain agar tidak dobel.'),
            ],
            [
                'id' => 'cta-artikel', 'title' => 'CTA Artikel',
                'desc' => 'Ajakan bertindak di dalam artikel — global, per-kategori, atau per-artikel.',
                'body' =>
                    $para('<strong>CTA Artikel</strong> tampil di dalam artikel dan bisa diatur berlapis.')
                    . $ul([
                        'Konten CTA: judul, caption, label tombol, URL tujuan, dan opsi buka tab baru.',
                        'Prioritas: CTA per-artikel menimpa CTA kategori, yang menimpa CTA global.',
                    ])
                    . $info('CTA per-artikel diisi dari editor; CTA global/kategori dari pengaturan. Tersedia juga lewat Management API (' . $mono('/api/article-cta.php') . ').'),
            ],
        ],
    ],
    [
        'cat' => 'Lanjutan',
        'topics' => [
            [
                'id' => 'ingest-api', 'title' => 'Ingest API',
                'desc' => 'Kirim artikel jadi dari sistem eksternal (mis. agen AI) sebagai Draft AI.',
                'body' =>
                    $para('<strong>Ingest API</strong> adalah pintu masuk bagi agen AI (mis. <strong>Hermes</strong>) untuk mengirim artikel jadi lewat HTTP ber-token. Secara default artikel masuk sebagai <strong>Draft AI</strong> untuk ditinjau; dengan izin Manajemen, agen bahkan bisa menerbitkan sendiri.')
                    . $h3('Yang bisa dilakukan Hermes (ringkasan lengkap)')
                    . $para('Dengan satu token yang sama, agen bisa membaca kondisi situs lalu menulis, mengoptimasi, dan menerbitkan konten. Kolom <strong>Butuh</strong>: ' . $mono('Ingest') . ' = cukup Ingest API aktif; ' . $mono('Manajemen') . ' = butuh toggle <strong>Manajemen &amp; Publikasi</strong> (aksi LIVE).')
                    . $table(
                        ['Kemampuan', 'Endpoint', 'Butuh', 'Catatan'],
                        [
                            ['Lihat kapabilitas', $mono('GET /api/capabilities.php'), 'Ingest', 'Cek fitur aktif, ' . $mono('publish_min_score') . ', versi ruleset, daftar endpoint.'],
                            ['Buat artikel (Draft AI)', $mono('POST /api/ingest-article.php'), 'Ingest', 'Artikel masuk ' . $mono('draft_ai') . ' untuk ditinjau manusia.'],
                            ['Buat &amp; terbitkan otomatis', $mono('POST /api/ingest-article.php') . ' + ' . $mono('publish:true'), 'Manajemen', 'Langsung tayang bila skor kesehatan &ge; 80.'],
                            ['Baca daftar &amp; isi artikel', $mono('GET /api/articles.php'), 'Ingest', $mono('?id=&full=1') . ' untuk isi lengkap; dukung paginasi &amp; filter kategori.'],
                            ['Audit skor SEO', $mono('GET /api/content-health.php'), 'Ingest', 'Per artikel atau batch + filter level (sehat/perlu perhatian/kritis).'],
                            ['Optimasi &amp; terbitkan artikel lama', $mono('POST /api/content-health.php'), 'Manajemen', 'Kirim ' . $mono('expected_updated_at') . '; skor &ge; 80 untuk konten LIVE.'],
                            ['Pilih target dari data Google', $mono('GET /api/search-performance.php'), 'Ingest¹', 'Filter ' . $mono('zero') . '/' . $mono('lowctr') . '/' . $mono('page2') . ' — lihat topik <a href="#search-performance" class="doc-nav underline" style="color:var(--accent)">Search Performance</a>.'],
                            ['Kelola kategori', $mono('GET/POST /api/categories.php'), 'Manajemen', 'Buat/ubah (tanpa hapus).'],
                            ['Baca/ubah setelan SEO &amp; brand', $mono('GET/POST /api/settings.php'), 'Manajemen', 'Whitelist ketat, perubahan LIVE.'],
                            ['Baca ruleset SEO', $mono('GET /api/seo-rules.php'), 'Ingest', 'Untuk menyelaraskan artikel ke aturan aktif.'],
                            ['Saran internal link', $mono('GET /api/internal-links.php'), 'Ingest', 'Dapat ' . $mono('anchor') . '+' . $mono('url') . ' untuk ditautkan ke artikel relevan (lokal, gratis).'],
                            ['Antrean "Perlu Diperbarui"', $mono('GET /api/content-refresh.php'), 'Ingest', 'Daftar artikel berprioritas (GSC + umur + skor) untuk dioptimasi.'],
                            ['Kelola CTA artikel', $mono('GET/POST /api/article-cta.php'), 'Manajemen', 'Atur ajakan bertindak per artikel / global.'],
                            ['Kelola email sequence', $mono('GET/POST /api/email-sequences.php'), 'Manajemen', 'Kelola urutan email &amp; langkahnya.'],
                        ]
                    )
                    . $para('<span class="text-sm text-gray-500 dark:text-gray-400">¹ Menyegarkan data (' . $mono('?refresh=1') . ') butuh Manajemen &amp; dibatasi 1&times; / 6 jam.</span>')
                    . $para('Rincian endpoint tulis ada di topik <a href="#management-api" class="doc-nav underline" style="color:var(--accent)">Management API</a>; alur kerja otonom penuh (riset &rarr; tulis &rarr; terbit &rarr; optimasi) ada di <a href="#autopilot" class="doc-nav underline" style="color:var(--accent)">Autopilot (Hermes otonom)</a>.')
                    . $h3('Mengaktifkan')
                    . $steps([
                        ['Aktifkan Ingest', 'Pengaturan → Integrasi → aktifkan <strong>Ingest API</strong>.'],
                        ['Buat token', 'Buat token akses; token mentah tampil <strong>sekali</strong> — simpan baik-baik (hanya hash yang disimpan di server).'],
                        ['(Opsional) penulis default', 'Pilih penulis untuk artikel yang masuk.'],
                        ['(Untuk publish/kelola)', 'Aktifkan juga <strong>Manajemen &amp; Publikasi</strong> bila ingin agen menerbitkan/mengelola sendiri.'],
                    ])
                    . $h3('Field request — ' . $mono('POST /api/ingest-article.php'))
                    . $para('Kirim JSON dengan header ' . $mono('Authorization: Bearer &lt;token&gt;') . ' dan ' . $mono('Content-Type: application/json') . '.')
                    . $table(
                        ['Field', 'Wajib', 'Tipe', 'Keterangan'],
                        [
                            [$mono('title'), 'Ya', 'teks', 'Judul artikel (maks 255).'],
                            [$mono('content_html'), 'Ya', 'HTML', 'Isi artikel; disanitasi otomatis (tag berbahaya dibuang); maks 5 MB.'],
                            [$mono('external_ref'), 'Disarankan', 'teks', 'ID unik &amp; stabil → <strong>idempoten</strong>: kirim ulang ref yang sama mengembalikan artikel yang sama (bukan duplikat).'],
                            [$mono('excerpt'), 'Opsional', 'teks', 'Ringkasan singkat (maks 500).'],
                            [$mono('focus_keyword'), 'Opsional', 'teks', 'Kata kunci utama.'],
                            [$mono('related_keywords'), 'Opsional', 'array/teks', 'Kata kunci turunan.'],
                            [$mono('meta_title'), 'Opsional', 'teks', 'Disarankan 45–60 karakter.'],
                            [$mono('meta_description'), 'Opsional', 'teks', 'Disarankan 120–160 karakter.'],
                            [$mono('slug'), 'Opsional', 'teks', 'Bila kosong dibuat otomatis dari judul (dijamin unik).'],
                            [$mono('search_intent'), 'Opsional', 'enum', $mono('informational') . ' / ' . $mono('transactional') . ' / ' . $mono('navigational') . '.'],
                            [$mono('language'), 'Opsional', 'teks', 'mis. ' . $mono('id') . ' (default dari setelan situs).'],
                            [$mono('category'), 'Opsional', 'teks', 'Nama/slug kategori yang <strong>sudah ada</strong> (tidak membuat kategori baru).'],
                            [$mono('tags'), 'Opsional', 'array', 'Dibuat otomatis bila belum ada.'],
                            [$mono('article_cta'), 'Opsional', 'object', 'Butuh <strong>Manajemen</strong>.'],
                            [$mono('publish'), 'Opsional', 'boolean', 'Butuh <strong>Manajemen</strong>; tayang bila skor kesehatan &ge; 80, selain itu tetap Draft AI.'],
                        ]
                    )
                    . $h3('Respons &amp; kode status')
                    . $para('Sukses <strong>201</strong> mengembalikan ' . $mono('article_id') . ', ' . $mono('slug') . ', ' . $mono('status') . ' (' . $mono('draft_ai') . '|' . $mono('published') . '), ' . $mono('published') . ', ' . $mono('edit_url') . ', ' . $mono('public_url') . '. Bila ' . $mono('publish') . ' diminta, ditambah ' . $mono('seo_score') . ', ' . $mono('seo_level') . ', ' . $mono('issues') . ', dan ' . $mono('publish_note') . ' (agar agen tahu apa yang perlu diperbaiki bila belum tayang).')
                    . $ul([
                        $mono('200') . ' — idempoten (' . $mono('external_ref') . ' sudah ada) atau balasan GET.',
                        $mono('400') . ' — body JSON tidak valid. &nbsp; ' . $mono('401') . ' — token tidak ada/salah.',
                        $mono('403') . ' — Ingest nonaktif, atau Manajemen nonaktif (untuk ' . $mono('publish') . '/' . $mono('article_cta') . ').',
                        $mono('413') . ' — ' . $mono('content_html') . ' &gt; 5 MB. &nbsp; ' . $mono('415') . ' — Content-Type bukan ' . $mono('application/json') . '.',
                        $mono('422') . ' — validasi field gagal. &nbsp; ' . $mono('429') . ' — terlalu banyak permintaan (rate-limit).',
                    ])
                    . $info('Mode otonom: sertakan ' . $mono('"publish": true') . ' agar artikel langsung tayang bila skor kesehatan &ge; 80. Untuk alur penuh berbasis data, lihat topik <a href="#autopilot" class="doc-nav underline" style="color:var(--accent)">Autopilot</a>.')
                    . $h3('Prompt untuk menyetel Hermes')
                    . $para('Salin ke AI yang menyetel agen Hermes Anda. Endpoint sudah terisi domain Anda; ganti ' . $mono('{INGEST_TOKEN}') . ' dengan token dari langkah di atas.')
                    . $code(e($hermesIngestPrompt))
                    . $warn('Sebagian server tidak meneruskan header Authorization ke PHP. Bila selalu 401 padahal token benar, tambahkan ' . $mono('CGIPassAuth On') . ' di konfigurasi server.'),
            ],
            [
                'id' => 'management-api', 'title' => 'Management API',
                'desc' => 'Endpoint untuk mengelola & mengoptimasi konten (token yang sama).',
                'body' =>
                    $para('<strong>Management API</strong> memperluas Ingest dengan endpoint kelola & optimasi.')
                    . $steps([
                        ['Aktifkan', 'Pengaturan → Integrasi → aktifkan <strong>Manajemen &amp; Publikasi</strong> (endpoint tulis membutuhkannya).'],
                    ])
                    . $h3('Endpoint')
                    . $ul([
                        $mono('GET/POST /api/categories.php') . ' — daftar/buat/ubah kategori.',
                        $mono('GET/POST /api/settings.php') . ' — baca/ubah setelan SEO &amp; brand (whitelist ketat, LIVE).',
                        $mono('GET /api/articles.php') . ' — daftar artikel.',
                        $mono('GET/POST /api/content-health.php') . ' — audit, optimasi, FAQ approved, publish.',
                        $mono('GET/POST /api/article-cta.php') . ' — kelola CTA.',
                        $mono('GET/POST /api/email-sequences.php') . ' — kelola sequence &amp; step.',
                        $mono('GET /api/seo-rules.php') . ' — ruleset SEO aktif (untuk menyelaraskan artikel).',
                        $mono('GET /api/search-performance.php') . ' — metrik Google Search Console per artikel (target optimasi).',
                        $mono('GET /api/internal-links.php') . ' — saran internal link (' . $mono('anchor') . '+' . $mono('url') . '); lokal &amp; gratis.',
                        $mono('GET /api/content-refresh.php') . ' — antrean "Perlu Diperbarui" berprioritas (GSC + umur + skor).',
                        $mono('GET /api/capabilities.php') . ' — discovery: apa yang aktif &amp; batas operasi.',
                    ])
                    . $h3('Prompt untuk alur optimasi &amp; publish (Hermes)')
                    . $para('Salin ke AI yang menyetel Hermes untuk memperbaiki lalu (opsional) menerbitkan artikel.')
                    . $code(e($hermesManagePrompt))
                    . $info('Optimasi memakai kontrol konkurensi: kirim ' . $mono('expected_updated_at') . ' dari GET terbaru agar tidak menimpa perubahan. Skor minimal <strong>80</strong> untuk konten LIVE.'),
            ],
            [
                'id' => 'autopilot', 'title' => 'Autopilot (Hermes otonom)',
                'desc' => 'Beri agen AI kendali penuh: riset dari data GSC, menulis, menerbitkan, dan mengoptimasi sendiri.',
                'body' =>
                    $para('<strong>Autopilot</strong> menggabungkan seluruh API menjadi satu alur otonom: agen (mis. Hermes) memilih pekerjaan dari <strong>data nyata Google Search Console</strong>, menulis artikel, menerbitkan sendiri (skor &ge; 80), lalu mengoptimasi yang lemah — dengan kerja manual seminimal mungkin.')
                    . $steps([
                        ['Aktifkan semua', 'Pengaturan → Integrasi: <strong>Ingest API</strong> + <strong>Manajemen &amp; Publikasi</strong>. Lalu Admin → SEO Health → <strong>Search Performance</strong> tersambung.'],
                        ['Discovery', $mono('GET /api/capabilities.php') . ' → agen memastikan ' . $mono('manage') . ' &amp; ' . $mono('search_performance') . ' aktif serta batas ' . $mono('publish_min_score') . '.'],
                        ['Pilih dari data', $mono('GET /api/search-performance.php?filter=page2|lowctr|zero') . ' → daftar target optimasi + celah topik.'],
                        ['Tulis &amp; terbitkan', $mono('POST /api/ingest-article.php') . ' dengan ' . $mono('"publish": true') . ' → tayang otomatis bila skor &ge; 80.'],
                        ['Perbaiki yang lemah', $mono('POST /api/content-health.php') . ' (dengan ' . $mono('expected_updated_at') . ') → optimasi + publish artikel lama.'],
                    ])
                    . $h3('Prompt autopilot (siap-copas)')
                    . $para('Salin ke AI yang menyetel Hermes. Endpoint sudah terisi domain Anda; ganti ' . $mono('{INGEST_TOKEN}') . ' dengan token Ingest.')
                    . $code(e($hermesAutopilotPrompt))
                    . $warn('Mode otonom berarti artikel bisa <strong>tayang tanpa ditinjau manusia</strong> (dijaga skor &ge; 80). Bila ingin tetap meninjau sebelum tayang, jangan kirim ' . $mono('publish:true') . ' — biarkan masuk sebagai Draft AI lalu terbitkan manual.'),
            ],
            [
                'id' => 'sitemap-rss', 'title' => 'Sitemap, RSS & robots.txt',
                'desc' => 'Berkas teknis untuk mesin pencari — diperbarui otomatis.',
                'body' =>
                    $para('Situs otomatis menyediakan:')
                    . $check([
                        $mono('/sitemap.xml') . ' — peta situs, diperbarui otomatis saat artikel terbit/berubah (tanpa cron).',
                        $mono('/feed') . ' — RSS artikel terbaru.',
                        $mono('/robots.txt') . ' — dibuat otomatis: menunjuk ke sitemap dan melarang area privat (admin, aksi, API, login, pencarian).',
                    ])
                    . $info('Kirim ' . $mono('/sitemap.xml') . ' ke Google Search Console agar konten cepat terindeks. Sitemap, RSS &amp; robots.txt memakai URL situs Anda otomatis — tidak perlu diedit manual.'),
            ],
            [
                'id' => 'indexnow', 'title' => 'IndexNow (indeks cepat)',
                'desc' => 'Beri tahu mesin pencari otomatis saat artikel terbit/berubah agar cepat terindeks.',
                'body' =>
                    $para('<strong>IndexNow</strong> mengirim URL artikel ke mesin pencari (Bing, Yandex, Seznam, dll lewat satu endpoint) begitu artikel <strong>terbit atau diperbarui</strong> — tanpa menunggu crawl rutin. Berlaku juga untuk artikel yang diterbitkan via API/Hermes. <strong>Event-driven, tanpa cron.</strong>')
                    . $steps([
                        ['Aktifkan', 'Pengaturan → Integrasi → kartu <strong>IndexNow</strong> → centang Aktifkan → Simpan. Key verifikasi dibuat otomatis.'],
                        ['File verifikasi otomatis', 'Situs menyajikan sendiri ' . $mono('{domain}/{key}.txt') . ' (tidak perlu upload). Mesin pencari mengambilnya untuk memverifikasi kepemilikan domain.'],
                        ['Tes', 'Tombol <strong>Tes ping</strong> mengirim URL beranda; hasil (HTTP) tampil di kartu.'],
                    ])
                    . $info('Setelah aktif, tidak ada yang perlu dilakukan manual — setiap publish/update langsung dikirim. Kegagalan ping bersifat <strong>non-fatal</strong> (tidak pernah menggagalkan penyimpanan artikel).')
                    . $h3('Verifikasi lewat API (untuk agen/Hermes)')
                    . $para('Status IndexNow tersedia di ' . $mono('GET /api/capabilities.php') . ' pada objek ' . $mono('indexnow') . ': ' . $mono('enabled') . ', ' . $mono('configured') . ', ' . $mono('key_location') . ', ' . $mono('last_ping_at') . ', ' . $mono('last_status') . ' (HTTP), dan ' . $mono('last_url') . '. Untuk membuktikan penerimaan <strong>tanpa menerbitkan artikel</strong>, agen dapat memicu ping uji: ' . $mono('POST /api/indexnow-ping.php') . ' (butuh Manajemen; body opsional ' . $mono('{"urls":[...]}') . ' — hanya URL host sendiri). Respons memuat ' . $mono('http') . ' + objek ' . $mono('indexnow') . ' terbaru.')
                    . $warn('Google <strong>tidak</strong> memakai IndexNow, tetapi tetap menemukan artikel lewat ' . $mono('/sitemap.xml') . ' (lihat topik Sitemap &amp; RSS) dan data ' . $mono('Search Performance') . '. IndexNow melengkapi, bukan menggantikan.'),
            ],
            [
                'id' => 'update', 'title' => 'Update Sistem',
                'desc' => 'Tarik versi terbaru langsung dari rilis resmi (self-update).',
                'body' =>
                    $para('<strong>Pembaruan</strong> (sidebar) menarik versi terbaru dari rilis resmi.')
                    . $steps([
                        ['Cek update', 'Menu Pembaruan → membandingkan versi terpasang dengan rilis terbaru.'],
                        ['Pasang', 'Jalankan pembaruan; backup dibuat otomatis sebelum menimpa berkas.'],
                    ])
                    . $info($mono('config.php') . ', folder ' . $mono('uploads/') . ', dan kunci lisensi tidak ikut tertimpa. Info lisensi &amp; perpanjangan ada di menu <strong>Lisensi</strong>.'),
            ],
            [
                'id' => 'pengguna', 'title' => 'Pengguna & Peran',
                'desc' => 'Kelola admin & penulis (writer), dan kaitannya dengan halaman penulis.',
                'body' =>
                    $para('Kelola tim di <strong>Pengguna</strong> (sidebar, khusus admin): tambah/edit pengguna, reset kata sandi, dan aktif/nonaktifkan.')
                    . $check([
                        '<strong>Admin</strong> — akses penuh: pengaturan, pengguna, integrasi, kategori, semua artikel.',
                        '<strong>Writer</strong> — menulis &amp; mengelola <strong>artikelnya sendiri</strong>, memakai AI Assist; tanpa akses pengaturan/pengguna.',
                    ])
                    . $info('Setiap pengguna adalah <strong>penulis</strong>: punya halaman ' . $mono('/penulis/{nama}') . ' dan profil sendiri (lihat topik <a href="#profil-penulis" class="doc-nav underline" style="color:var(--accent)">Profil Penulis</a>). Nama di sini menentukan slug halaman penulis.')
                    . $warn('Minimal satu admin aktif selalu dijaga — admin aktif terakhir tidak bisa dinonaktifkan/dihapus.'),
            ],
            [
                'id' => 'lisensi', 'title' => 'Lisensi',
                'desc' => 'Status lisensi, aktivasi, dan re-validasi.',
                'body' =>
                    $para('Menu <strong>Lisensi</strong> (sidebar) menampilkan status lisensi produk: <strong>valid</strong>, <strong>masa tenggang</strong>, <strong>kedaluwarsa</strong>, atau <strong>menunggu aktivasi</strong>, beserta plan.')
                    . $steps([
                        ['Aktivasi', 'Tombol <strong>Aktivasi Lisensi</strong> → masukkan kunci lisensi untuk mengikat domain ini.'],
                        ['Re-validasi', 'Admin dapat <strong>me-revalidasi</strong> untuk menyegarkan status (mis. setelah perpanjangan).'],
                    ])
                    . $info('Halaman Lisensi tetap bisa diakses meski lisensi kedaluwarsa (untuk pemulihan). Perpanjangan dilakukan melalui vendor tempat Anda membeli.'),
            ],
        ],
    ],
];

// ─── Rakit nav (kiri) + body (kanan) dari SATU sumber ───
$navHtml = '';
$bodyHtml = '';
$firstTopic = $docs[0]['topics'][0]['id'] ?? '';
foreach ($docs as $group) {
    $navHtml .= '<div class="doc-nav-group mb-4">'
        . '<p class="px-2 mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400">' . e($group['cat']) . '</p>'
        . '<nav class="space-y-0.5">';
    foreach ($group['topics'] as $t) {
        $navHtml .= '<a href="#' . e($t['id']) . '" data-target="' . e($t['id']) . '" class="doc-nav block px-2 py-1.5 rounded-md text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition">' . e($t['title']) . '</a>';
        $bodyHtml .= '<section id="' . e($t['id']) . '" class="doc-section scroll-mt-24">'
            . '<div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white/80 dark:bg-gray-900/70 backdrop-blur-sm p-6 lg:p-7">'
            . '<h2 class="font-display text-xl font-bold text-gray-900 dark:text-white mb-1">' . e($t['title']) . '</h2>'
            . '<p class="text-sm text-gray-500 dark:text-gray-400 mb-5">' . e($t['desc']) . '</p>'
            . '<div class="doc-prose text-gray-600 dark:text-gray-300 text-[14.5px] leading-relaxed">' . $t['body'] . '</div>'
            . '</div></section>';
    }
    $navHtml .= '</nav></div>';
}

admin_shell_top('Dokumentasi', '/admin/docs');
?>
<style>
/* Scrollbar tipis, lembut & menyatu (light & dark) — dipakai nav dokumentasi. */
.doc-scroll{ scrollbar-width: thin; scrollbar-color: color-mix(in srgb, var(--accent) 24%, transparent) transparent; }
.doc-scroll::-webkit-scrollbar{ width: 6px; height: 6px; }
.doc-scroll::-webkit-scrollbar-track{ background: transparent; }
.doc-scroll::-webkit-scrollbar-thumb{ background: color-mix(in srgb, var(--accent) 18%, transparent); border-radius: 9999px; border: 1px solid transparent; background-clip: padding-box; }
.doc-scroll::-webkit-scrollbar-thumb:hover{ background: color-mix(in srgb, var(--accent) 36%, transparent); background-clip: padding-box; }
</style>
<div class="doc-wrap flex gap-6 lg:gap-8 items-start">

  <!-- Nav kiri (desktop, sticky + scrollspy) -->
  <aside class="doc-scroll hidden lg:block w-56 shrink-0 sticky top-20 self-start max-h-[calc(100vh-6rem)] overflow-y-auto pr-1">
    <?= $navHtml ?>
  </aside>

  <!-- Konten -->
  <div class="min-w-0 flex-1">
    <!-- Pencarian -->
    <div class="mb-5 sticky top-16 z-20 -mx-1 px-1 py-1 bg-[var(--bg-canvas)] dark:bg-transparent">
      <div class="relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><?= icon('search', 'w-4 h-4') ?></span>
        <input id="docSearch" type="text" placeholder="Cari dokumentasi…" autocomplete="off"
               class="w-full rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 pl-9 pr-16 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--accent)]/40 focus:border-[var(--accent)]">
        <span class="absolute right-3 top-1/2 -translate-y-1/2 hidden sm:flex items-center gap-1 text-[11px] text-gray-400">
          <kbd class="px-1.5 py-0.5 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 font-mono">Ctrl</kbd>
          <kbd class="px-1.5 py-0.5 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 font-mono">K</kbd>
        </span>
      </div>
    </div>

    <!-- Daftar isi (mobile) -->
    <details class="lg:hidden mb-5 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
      <summary class="cursor-pointer select-none px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">Daftar Topik</summary>
      <div class="px-3 pb-3"><?= $navHtml ?></div>
    </details>

    <div class="space-y-5">
      <?= $bodyHtml ?>
      <p id="docNoResult" class="hidden text-center text-sm text-gray-400 py-10">Tidak ada topik yang cocok dengan pencarian.</p>
    </div>
  </div>
</div>

<script>
(function () {
  const search   = document.getElementById('docSearch');
  const sections = Array.from(document.querySelectorAll('section.doc-section'));
  const navLinks = Array.from(document.querySelectorAll('.doc-nav[data-target]'));
  const noResult = document.getElementById('docNoResult');

  // Indeks teks per section (judul + isi) untuk pencarian.
  const index = sections.map(s => ({ id: s.id, text: (s.textContent || '').toLowerCase() }));

  // ── Smooth-scroll saat klik nav ──
  navLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      const t = document.getElementById(link.dataset.target);
      if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', '#' + link.dataset.target); }
    });
  });

  // ── Scrollspy: sorot nav aktif ──
  const setActive = (id) => {
    navLinks.forEach(l => {
      const on = l.dataset.target === id;
      l.classList.toggle('doc-nav-active', on);
      if (on) { l.style.background = 'color-mix(in srgb, var(--accent) 12%, transparent)'; l.style.color = 'var(--accent)'; l.style.fontWeight = '600'; }
      else { l.style.background = ''; l.style.color = ''; l.style.fontWeight = ''; }
    });
  };
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver((entries) => {
      const vis = entries.filter(en => en.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
      if (vis.length) setActive(vis[0].target.id);
    }, { rootMargin: '-96px 0px -65% 0px', threshold: 0 });
    sections.forEach(s => obs.observe(s));
  }

  // ── Pencarian (filter section + nav, sembunyikan grup kosong) ──
  const apply = () => {
    const q = search.value.trim().toLowerCase();
    const shown = new Set();
    index.forEach(it => {
      const match = q === '' || it.text.includes(q);
      const sec = document.getElementById(it.id);
      if (sec) sec.classList.toggle('hidden', !match);
      if (match) shown.add(it.id);
    });
    navLinks.forEach(l => l.classList.toggle('hidden', !(q === '' || shown.has(l.dataset.target))));
    document.querySelectorAll('.doc-nav-group').forEach(g => {
      const any = Array.from(g.querySelectorAll('.doc-nav')).some(a => !a.classList.contains('hidden'));
      g.classList.toggle('hidden', !any);
    });
    if (noResult) noResult.classList.toggle('hidden', shown.size !== 0);
  };
  search.addEventListener('input', apply);
  search.addEventListener('keydown', (e) => { if (e.key === 'Escape') { search.value = ''; apply(); search.blur(); } });

  // ── Ctrl/Cmd+K fokus pencarian ──
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); search.focus(); search.select(); }
  });

  // ── Tombol Salin (kode) ──
  document.querySelectorAll('.doc-copy[data-copy]').forEach(btn => {
    btn.addEventListener('click', () => {
      const el = document.querySelector(btn.dataset.copy);
      if (!el) return;
      const done = () => { const l = btn.querySelector('.doc-copy-label'); if (l) { const o = l.textContent; l.textContent = 'Tersalin'; setTimeout(() => l.textContent = o, 1400); } };
      const txt = el.textContent;
      if (navigator.clipboard) navigator.clipboard.writeText(txt).then(done).catch(() => {});
      else { const ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) {} ta.remove(); }
    });
  });

  // Buka langsung ke anchor bila ada di URL.
  if (location.hash) { const t = document.getElementById(location.hash.slice(1)); if (t) setTimeout(() => t.scrollIntoView({ block: 'start' }), 60); }
})();
</script>
<?php admin_shell_bottom(); ?>
