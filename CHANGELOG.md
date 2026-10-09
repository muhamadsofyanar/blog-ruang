# Changelog — Averion SEO Engine

Semua perubahan penting produk didokumentasikan di sini.
Format mengikuti versi semantik `MAJOR.MINOR.PATCH`.

## v1.0.63 — 2026-10-03

### Penyempurnaan
- Informasi dan URL Cron dipindahkan dari halaman Email Sequence ke menu
  Dokumentasi (topik Email Sequence) agar halaman pengaturan lebih ringkas. URL
  Cron rahasia kini hanya ditampilkan untuk admin. Tanpa migrasi database.

## v1.0.62 — 2026-10-03

### Perbaikan & fitur
- Menghindari email terkirim ganda. Batas waktu panggilan ke penyedia diperpanjang
  agar respons yang lambat tidak dianggap gagal. Bila benar-benar kehabisan waktu
  (timeout tanpa respons), pengiriman tidak diulang otomatis karena email mungkin
  sudah terkirim di sisi penyedia — statusnya ditandai gagal dengan catatan, dan
  bisa dikirim ulang manual bila perlu.
- Menambahkan pemrosesan via Cron. Di halaman Email Sequence tersedia URL Cron
  rahasia beserta panduan memasangnya di cPanel (jalankan tiap 5 menit) agar
  antrean tetap diproses meski situs sepi pengunjung. Pemrosesan tetap idempoten
  (aman dipanggil berulang, tidak mengirim ganda).
- Dokumentasi Email Sequence diperbarui: cara kerja pemrosesan otomatis, arti
  status pending/failed, dan pemasangan Cron.

### Catatan
- Token Mailketing, isi/jadwal sequence, artikel, dan setting lain tidak diubah.
  Pengiriman tetap melalui antrean. Tanpa migrasi database.

## v1.0.61 — 2026-10-03

### Perbaikan
- Memperbaiki penyebab sebenarnya antrean email yang gagal diproses: fungsi
  pendukung pengiriman (pemecah nama dan pembuat tautan berhenti berlangganan)
  tidak termuat saat antrean dijalankan dari tombol admin, kunjungan halaman,
  atau proses latar belakang, sehingga setiap pemrosesan berhenti dengan galat
  "fungsi tidak ditemukan" dan tidak ada email yang terkirim. Kini modul tersebut
  selalu dimuat di jalur worker. Log worker yang ditambahkan pada v1.0.60 yang
  menampilkan galat ini.

### Catatan
- Tidak mengubah token Mailketing, isi atau jadwal sequence, artikel, maupun
  setting lain. Pengiriman tetap melalui antrean. Tanpa migrasi database.

## v1.0.60 — 2026-10-03

### Perbaikan
- Antrean email yang terus "pending" kini diproses dengan andal. Penyebabnya:
  pemrosesan berjalan di dalam permintaan halaman dan bisa berhenti sebelum
  mencatat hasil ketika panggilan ke penyedia lambat. Sekarang pemrosesan berjalan
  setelah respons dikirim (tidak menahan halaman), dengan batas waktu per proses
  dan batas waktu per panggilan agar tidak pernah mentok waktu eksekusi.
- Setiap percobaan dicatat sebelum memanggil penyedia, dengan percobaan terbatas
  (maksimal 5×) lalu ditandai gagal, pencegahan kirim ganda, serta waktu percobaan
  terakhir dan pesan respons penyedia yang tersimpan dan terlihat.
- Halaman Subscribers kini menampilkan Log worker (hasil tiap pengiriman: status,
  kode, durasi, pesan penyedia) sehingga keberhasilan atau kegagalan bisa
  dibuktikan, bukan sekadar status berubah.

### Catatan
- Migrasi menambahkan kolom pelacakan percobaan pada antrean email (tidak mengubah
  isi atau jadwal sequence). Token Mailketing dan pengaturan lain tidak diubah,
  dan pengiriman tetap melalui antrean.

## v1.0.59 — 2026-10-03

### Penyempurnaan
- Halaman Subscribers kini menampilkan "Diagnosa pengiriman email" bila ada yang
  tertahan: status token dan email pengirim Mailketing, list auto-add, jumlah
  antrean list-add dan email sequence yang jatuh tempo, serta pesan respons
  terakhir dari provider. Tujuannya agar status "pending" tidak lagi tanpa
  penjelasan.
- Setiap baris subscriber kini menampilkan status penambahan ke list Mailketing
  dan status email sequence terpisah, dengan pesan respons provider saat gagal
  (arahkan kursor ke item untuk melihat detail).
- Tersedia tombol "Proses antrean sekarang" langsung di halaman Subscribers.

### Catatan
- Hanya menampilkan data yang sudah tersimpan; tidak mengubah token, pengaturan,
  atau logika pengiriman. Tanpa migrasi database.

## v1.0.58 — 2026-10-03

### Perbaikan
- Email sequence D+0 kini terkirim tepat setelah subscriber mendaftar. Sebelumnya
  email pertama hanya diproses saat ada kunjungan ke beranda atau halaman admin,
  sehingga pada situs dengan lalu lintas rendah email bisa tertahan lama di
  antrean. Kini antrean (email sequence dan penambahan ke list Mailketing)
  langsung diproses setelah pendaftaran, tanpa menunggu kunjungan lain.
- Pemrosesan berjalan setelah respons dikirim ke pengunjung sehingga pendaftaran
  tetap terasa instan, dengan pengaman agar tidak terjadi pengiriman ganda.
- Subscriber tanpa konfigurasi list auto-add tidak lagi tampak "pending" terus
  menerus; statusnya ditandai "skipped" (memang tidak ada yang perlu dikirim).

### Catatan
- Enrollment tetap idempotent dan D+0 dijadwalkan saat pendaftaran. Token
  Mailketing, pengaturan global, dan isi artikel tidak diubah. Tanpa migrasi
  database.

## v1.0.57 — 2026-10-03

### Penyempurnaan
- Form Lead Magnet kini lebih mulus setelah dikirim. Halaman tidak lagi meloncat
  ke atas; posisi tetap di area form dan tampilan langsung berganti menjadi
  status "Pendaftaran berhasil" tanpa memuat ulang halaman. Berlaku untuk form di
  halaman materi maupun form yang tampil di dalam artikel.
- Teks tampilan sukses kini dapat diatur di Admin (menu Lead Magnet): judul,
  deskripsi, dan teks tombol setelah submit. Jika dikosongkan, dipakai teks
  bawaan; teks tombol kosong otomatis mengikuti label tombol form.

### Catatan
- Proses penyimpanan lead, email, dan unduhan tidak berubah. Form tetap berfungsi
  bila JavaScript nonaktif. Tanpa migrasi database.

## v1.0.56 — 2026-10-02

### Fitur baru
- Hapus cover artikel. Tombol "Hapus cover" kini tersedia di preview cover (untuk
  cover unggahan asli). Setelah dihapus dan disimpan, artikel otomatis kembali
  memakai cover placeholder bawaan — jadi artikel tetap selalu punya cover.
- Hapus gambar di dalam artikel. Klik gambar mana pun di editor untuk memunculkan
  tombol "Hapus gambar"; satu klik menghapus gambar beserta blok pembungkusnya.

### Catatan
- Tanpa migrasi database.

## v1.0.55 — 2026-10-02

### Penyempurnaan
- Tombol ganti tema di dashboard admin kini memakai tampilan switch yang sama
  dengan di halaman publik: berbentuk sakelar dengan tuas geser dan ikon matahari
  atau bulan di kedua sisi. Konsisten di mode terang maupun gelap. Tanpa migrasi
  database.

## v1.0.54 — 2026-10-02

### Perbaikan
- Gambar otomatis: penyaringan tanpa manusia diperketat. Daftar penolakan
  diperluas ke tangan, telapak, pergelangan, jari, lengan, kaki, tubuh, siluet,
  dan kerumunan, sehingga aset yang menampilkan bagian tubuh tidak lagi lolos ke
  rencana maupun hasil.
- Relevansi kini wajib: aset dengan relevansi nol tidak dapat dipilih lagi
  walaupun kebetulan memuat objek umum. Bila tidak ada aset yang benar-benar
  cocok dengan topik, gambar dilewati dan diberi peringatan.
- Query hasil normalisasi dijamin memakai kata kunci visual bahasa Inggris. Kata
  Indonesia yang tidak terpetakan tidak lagi ikut terkirim ke penyedia aset
  (tidak ada lagi query campur seperti "buat struktur penawaran mudah concept
  abstract background").
- Pratinjau (dry_run) memakai proses validasi yang sama persis dengan eksekusi
  dan kini mencantumkan alasan setiap kandidat ditolak (manusia, logo, atau
  relevansi nol).

### Catatan
- API tetap kompatibel; perlindungan SSRF, idempotency, dan perilaku aman saat
  provider gagal tetap berlaku. Tanpa migrasi database.

## v1.0.53 — 2026-10-02

### Penyempurnaan
- Gambar otomatis (Ingest API) kini jauh lebih relevan dan profesional. Judul
  dan heading berbahasa Indonesia diterjemahkan menjadi kata kunci visual
  Inggris yang konkret sebelum mencari aset (mis. "harga produk digital" menjadi
  pricing table, "biaya produksi" menjadi industrial/mesin, "analisis data"
  menjadi dashboard analitik), sehingga hasil tidak lagi melenceng.
- Penyaringan ketat tanpa manusia: aset yang memuat orang, wajah, tangan, tubuh,
  kerumunan, atau siluet ditolak. Aset berlogo, bermerek, atau berteks dominan
  juga ditolak. Diutamakan dashboard, tabel harga, kalkulator, data, perangkat,
  diagram, workspace kosong, dan visual abstrak.
- Penilaian relevansi dengan ambang minimum plus query cadangan. Bila tidak ada
  aset yang benar-benar layak, gambar dilewati dan diberi peringatan, bukan
  memaksakan gambar yang tidak nyambung.
- Pratinjau (dry_run) kini menampilkan query asli, query hasil normalisasi,
  alasan pemilihan atau penolakan, dan posisi setiap gambar.

### Catatan
- API tetap kompatibel dengan versi sebelumnya; perlindungan SSRF, idempotency,
  dan perilaku aman saat provider gagal tetap berlaku. Tanpa migrasi database.

## v1.0.52 — 2026-10-02

### Fitur baru
- Gambar artikel otomatis via Ingest API. Sistem eksternal (mis. Hermes) kini
  dapat meminta cover dan gambar inline ditambahkan otomatis saat mengirim
  artikel. Aset diambil dari provider legal Pexels, diunduh ke server,
  dioptimasi ke WebP, lalu disimpan lokal (bukan hotlink). Cover memakai rasio
  1200x630; gambar inline disisipkan setelah heading yang relevan dengan
  alt-text otomatis (alt yang sudah ada tidak ditimpa).
- Mode fleksibel: otomatis (pilih aset sesuai judul, kata kunci, kategori, dan
  heading, mengutamakan visual profesional tanpa manusia), URL dari sistem Anda,
  atau nonaktif. Tersedia dry_run untuk melihat rencana (query, cover, posisi
  gambar) tanpa membuat file atau artikel apa pun.
- Pengaturan baru di Integrasi: aktifkan "Gambar otomatis" lalu isi Pexels API
  key (gratis di pexels.com/api). Default nonaktif demi keamanan. Bila provider
  gagal, artikel tetap diproses tanpa gambar disertai peringatan.

### Keamanan
- Unduhan aset dilindungi dari SSRF (menolak alamat internal/privat), hanya
  skema http(s), validasi tipe dan ukuran berkas, serta API key tersimpan lokal
  dan tidak pernah ditampilkan. Payload lama tetap bekerja tanpa perubahan.

### Catatan
- Tanpa migrasi database.

## v1.0.51 — 2026-10-02

### Penyempurnaan
- Dialog saat menyisipkan gambar atau tautan di editor kini memakai tampilan
  premium bawaan aplikasi (bukan popup bawaan browser yang polos). Konsisten,
  rapi di light & dark mode, dan posisi kursor tetap terjaga saat menyisipkan.
- Tanpa migrasi database.

## v1.0.50 — 2026-10-02

### Perbaikan
- Dark mode: daftar opsi dropdown kini benar-benar gelap dan terbaca di semua
  halaman (Pengaturan, editor, dll). Perbaikan sebelumnya belum menuntaskan
  sebagian dropdown; kini diperkuat dengan pewarnaan opsi langsung sehingga
  konsisten di seluruh menu.
- Tanpa migrasi database.

## v1.0.49 — 2026-10-02

### Penyempurnaan
- Catatan rilis kini tampil lengkap di menu Pembaruan Sistem (/admin/update).
  Sebelumnya hanya pesan build generik; sekarang berisi ringkasan fitur &
  perbaikan versi tersebut, diambil otomatis dari catatan perubahan resmi.
- Tanpa migrasi database.

## v1.0.48 — 2026-10-02

### Fitur baru: Pratinjau Email (Email Sequence)
- Tombol **Pratinjau** di editor step Email Sequence menampilkan tampilan email
  (HTML) sebelum disimpan, dengan **placeholder diisi contoh**. Dirender di
  bingkai aman (tersandbox) sehingga tampilannya persis seperti yang diterima.

### Perbaikan
- **Dark mode**: daftar opsi dropdown (`<select>`) kini ikut gelap &amp; terbaca
  di seluruh halaman admin (sebelumnya tampil putih). Diterapkan menyeluruh via
  `color-scheme` dan tak akan terulang pada dropdown lain.
- Tanpa migrasi database.

## v1.0.47 — 2026-10-02

### Dokumentasi
- Dokumentasi bawaan dilengkapi agar mencakup semua menu & fitur: topik baru
  **Alt Text Gambar**, **Pengguna & Peran**, dan **Lisensi**. Kini setiap menu
  admin memiliki panduannya. Tanpa migrasi database.

## v1.0.46 — 2026-10-02

### Fitur baru: Halaman penulis + schema Person (E-E-A-T)
- Setiap penulis kini punya **halaman publik** `/penulis/{nama}` berisi foto,
  jabatan, bio, tautan sosial, dan daftar artikelnya — memperkuat sinyal
  **E-E-A-T** (kepercayaan/keahlian) di mata Google.
- **Profil Penulis** baru di sidebar (`/admin/profile`): setiap pengguna
  menyunting bio, jabatan, avatar, dan tautan sosialnya sendiri.
- Halaman penulis menyertakan **JSON-LD Person**; nama penulis di schema artikel
  dan di byline kini **tertaut** ke halaman penulis.
- Tanpa tabel/kolom database baru (profil disimpan di setelan). Tanpa migrasi.

## v1.0.45 — 2026-10-01

### Fitur baru: robots.txt dinamis
- Situs otomatis menyajikan `/robots.txt` yang **menunjuk ke sitemap** dan
  melarang area privat (admin, aksi, API, login, pencarian). Dibuat dari URL
  situs Anda (aman untuk instalasi subfolder) — tidak perlu diedit manual.

### Fitur baru: Auto alt-text gambar (gratis)
- Tombol **Alt Text Gambar → Isi alt text** di AI Assist mengisi atribut `alt`
  gambar yang **kosong**, diambil dari caption/judul gambar, nama file yang
  dibersihkan, lalu kata kunci sebagai cadangan. Alt yang sudah ada tidak diubah.
  **Lokal & tanpa kredit** — membantu SEO gambar &amp; aksesibilitas.
- Artikel yang masuk via API/Hermes **otomatis** terisi alt-nya saat diterima,
  sehingga lebih siap untuk publish otomatis.
- Tanpa migrasi database.

## v1.0.44 — 2026-10-01

### Fitur baru: Schema brand di homepage (WebSite + Organization)
- Homepage otomatis menyertakan **JSON-LD WebSite + Organization**: memperkuat
  pengenalan brand di Google dan membuka peluang **kotak pencarian sitelinks**
  (SearchAction → `/search?q=`). Logo brand dipakai sebagai logo Organization.
- Field baru **Profil Sosial** di Pengaturan → Rebrand (satu URL per baris) mengisi
  `sameAs` — tautan ke akun resmi brand untuk memperkuat entitas.
- Schema hanya muncul di homepage kanonik (bukan `/blog`).

### Dokumentasi
- Topik baru **Internal Link** (sebelumnya hanya catatan di AI Assist) agar mudah
  ditemukan: cara di editor, aturan penempatan, dan API untuk agen.
- Topik **Appearance** ditambah bagian "Schema brand (otomatis)".
- Tanpa migrasi database.

## v1.0.43 — 2026-10-01

### Fitur baru: Antrean "Perlu Diperbarui" (content refresh)
- Tab baru **Perlu Diperbarui** di **SEO Health** menyusun artikel published yang
  paling layak dioptimasi lebih dulu, menggabungkan sinyal: **posisi 11–20,
  posisi turun, CTR rendah, belum dapat klik, umur >180 hari, dan skor SEO <80**
  menjadi satu prioritas. Tanpa kredit.
- **Deteksi "posisi turun"**: setiap "Perbarui data" menyimpan snapshot Search
  Console sebelumnya, sehingga penurunan peringkat terdeteksi (aktif setelah 2×
  perbarui). Tanpa GSC pun antrean bekerja memakai skor & umur.
- Endpoint baru `GET /api/content-refresh.php` (Ingest API) memberi daftar yang
  sama, urut prioritas dengan alasan + metrik — agen seperti Hermes memakainya
  untuk memilih pekerjaan lalu memperbaiki via `POST /api/content-health.php`.
- Dokumentasi topik **Perlu Diperbarui**. Tanpa migrasi database.

## v1.0.42 — 2026-10-01

### Penyempurnaan: observability IndexNow via API
- `GET /api/capabilities.php` kini memuat status IndexNow: `capabilities.indexnow`,
  dan objek `indexnow` berisi `enabled`, `configured`, `key_location`,
  `last_ping_at`, `last_status` (HTTP), dan `last_url`. Juga `capabilities.internal_links`.
- Endpoint baru `POST /api/indexnow-ping.php` (butuh Manajemen) agar agen seperti
  Hermes dapat **membuktikan penerimaan ping tanpa menerbitkan artikel**. Body
  opsional `{"urls":[...]}` — hanya URL host sendiri yang dikirim (keamanan).
- Dokumentasi IndexNow ditambah bagian "Verifikasi lewat API". Tanpa migrasi database.

## v1.0.41 — 2026-10-01

### Fitur baru: Saran & sisip internal link (gratis)
- Tombol **Internal Link** di AI Assist editor: mencari artikel published lain
  yang **topiknya sudah Anda sebut** di tulisan, lalu menawarkannya sebagai tautan.
  Centang → **Sisipkan terpilih** (link ditaruh pada kemunculan pertama frasa,
  bukan di heading atau teks yang sudah bertaut).
- **Lokal & tanpa kredit** — pencocokan atas artikel situs sendiri (bukan panggilan
  AI), jadi instan dan tidak memotong kredit. Membantu memenuhi aturan SEO tautan
  internal.
- Endpoint baru `GET /api/internal-links.php?article_id=` (Ingest API) memberi
  `anchor`+`url` agar agen seperti Hermes menautkan sendiri saat menulis/optimasi.
- Keamanan: hanya URL internal (host sendiri) yang dapat disisipkan. Tanpa migrasi database.

## v1.0.40 — 2026-10-01

### Fitur baru: IndexNow (indeks cepat)
- Saat artikel **terbit atau diperbarui**, URL-nya otomatis dikirim ke mesin
  pencari (Bing, Yandex, Seznam, dll lewat satu endpoint) agar cepat terindeks —
  **event-driven, tanpa cron**. Berlaku juga untuk artikel yang diterbitkan via
  API/Hermes (ingest publish, optimasi content-health, publish terjadwal, editor).
- Aktifkan di **Pengaturan → Integrasi → IndexNow**. Key verifikasi dibuat
  otomatis dan file `{domain}/{key}.txt` **disajikan sendiri oleh situs** (tidak
  perlu upload). Tersedia tombol **Tes ping**.
- Non-fatal: kegagalan ping tidak pernah menggagalkan penyimpanan artikel. Hanya
  URL di host sendiri yang dikirim (guard keamanan).
- Google tidak memakai IndexNow, tetapi tetap menemukan artikel lewat sitemap.
- Topik **Dokumentasi → IndexNow** ditambahkan. Tanpa migrasi database.

## v1.0.39 — 2026-10-01

### Dokumentasi
- **Ingest API** diperluas menjadi referensi lengkap kemampuan agen (mis. Hermes):
  tabel ringkasan semua kemampuan (buat/terbitkan artikel, audit, optimasi, data
  Search Console, kelola kategori/setelan/CTA/sequence), tabel field request
  lengkap, serta daftar respons & kode status.
- **Prompt setup Hermes** diperbarui: cek kapabilitas, opsi `publish:true` (terbit
  otomatis bila skor ≥ 80), penanganan respons lengkap, dan arahan ke Autopilot.
- **Kredit AI & BYOK**: ditambah langkah mengambil API key dengan tautan langsung
  ke **Google AI Studio** dan **Anthropic Console**, plus catatan tier gratis Gemini.

## v1.0.38 — 2026-10-01

### Perbaikan
- **Search Performance kini bisa diaktifkan pada setup pertama.** Sebelumnya, saat
  menempel kunci JSON + URL properti lalu mencentang "Aktifkan" dalam sekali simpan,
  muncul pesan keliru "Lengkapi service account dan URL properti sebelum mengaktifkan"
  meski data sudah benar (cache setelan tidak sinkron dalam satu proses simpan).
  Cache setelan kini koheren (baca-setelah-tulis konsisten).

### Dokumentasi
- Topik **Search Performance (Google Search Console)** diperluas: catatan gratis
  (tanpa Billing), langkah membuat Service Account + kunci JSON, memberi akses di
  Search Console, menghubungkan di SEO Engine, format URL properti (Domain vs
  URL-prefix), tautan ke topik **Autopilot**, dan bagian pemecahan masalah.

## v1.0.37 — 2026-10-01

### Fitur baru: Autopilot API — kendali penuh untuk agen konten (mis. Hermes)
- **Publish otomatis via Ingest API**: kirim `"publish": true` pada
  `POST /api/ingest-article.php` agar artikel langsung tayang **bila skor
  kesehatan ≥ 80** (butuh toggle Manajemen & Publikasi). Bila skor kurang,
  artikel tetap tersimpan sebagai Draft AI beserta daftar `issues` untuk
  diperbaiki. Respons kini memuat `published`, `public_url`, `seo_score`,
  `seo_level`, `issues`, dan `publish_note`.
- **Endpoint baru `GET /api/search-performance.php`** (read-only): metrik Google
  Search Console per artikel (klik/impresi/CTR/posisi) dari data yang tersambung,
  dengan filter aksi `zero`, `lowctr`, dan `page2` — agar agen memilih target
  optimasi dari data nyata. Opsi `?refresh=1` menarik data terbaru (butuh
  Manajemen, dibatasi 1× / 6 jam).
- **Endpoint baru `GET /api/capabilities.php`**: discovery kapabilitas & batas
  operasi (apa yang aktif, `publish_min_score`, versi ruleset, daftar endpoint)
  agar agen bisa menyetel diri sendiri dan memverifikasi token.
- **Dokumentasi**: topik baru **Autopilot (Hermes otonom)** berisi alur penuh
  riset → tulis → terbit → optimasi beserta prompt siap-salin; daftar endpoint
  Management API diperbarui.
- Tanpa migrasi database.

## v1.0.36 — 2026-09-30

### Fitur baru: Search Performance (Google Search Console)
- Tab baru **Search Performance** di halaman **SEO Health** (`/admin/content-health`)
  menampilkan data nyata dari Google Search Console per artikel: **klik, impresi,
  CTR, dan posisi** rata-rata — melengkapi skor internal dengan bukti performa.
- Filter aksi: **Belum dapat klik**, **CTR rendah**, dan **Posisi 11–20 (halaman 2)**
  untuk langsung menemukan artikel yang perlu diperbaiki.
- Koneksi via **service account** Google (read-only, tanpa OAuth) — cocok untuk
  instalasi mandiri; tiap situs memakai kuota Google-nya sendiri. Kunci disimpan
  aman di server dan tidak pernah ditampilkan kembali. Data di-cache (tanpa tabel
  tambahan, tanpa cron) dengan tombol **Perbarui data**.
- Kartu setup menautkan langsung ke Google Cloud & Search Console, plus topik
  **Dokumentasi → Search Performance** berisi panduan lengkap.

### Penyempurnaan Dokumentasi
- Scrollbar panel navigasi Dokumentasi dibuat tipis, lembut, dan menyatu (light/dark).

- Tanpa migrasi database.

## v1.0.35 — 2026-09-30

### Fitur baru: Dokumentasi bawaan
- Halaman **Dokumentasi** (`/admin/docs`, akses admin & penulis) berisi panduan
  pemakaian produk: 19 topik dalam 4 kategori (Menulis & SEO, Situs & Tampilan,
  Audiens & Growth, Lanjutan).
- Navigasi kiri dengan scrollspy, pencarian cepat (Ctrl+K), dan tautan langsung
  antar-topik. Bahasa Indonesia, mengikuti tema aktif (light/dark).
- Topik **Ingest API** dan **Management API** menyertakan **prompt siap-salin**
  untuk menyetel agen integrasi (mis. Hermes) — endpoint terisi domain instalasi,
  lengkap dengan aturan Draft AI serta alur optimasi/publish.
- Menu "Dokumentasi" ditambahkan di kaki sidebar (kartu transparan, di atas Keluar).
- Tanpa migrasi database.

## v1.0.34 — 2026-09-30

### Perbaikan AI Assist (editor artikel)
- Tombol **Simpan ke Artikel** dan **Pulihkan** draft tidak berfungsi karena
  error JavaScript saat halaman dimuat: inisialisasi AI Assist mengakses state
  draft sebelum dideklarasikan (Temporal Dead Zone), sehingga seluruh skrip
  editor berhenti dan setiap aksi yang menandai perubahan ikut gagal.
- Deklarasi state draft dipindah sebelum inisialisasi. Kedua tombol berfungsi
  kembali, dan alur AI Assist (riset keyword, outline, tulis draft) stabil.

### Perbaikan redirect setelah login (instalasi subfolder)
- Pada instalasi di subfolder (mis. `domain.com/averion-scribe`), redirect
  setelah login menghasilkan path ganda (`/averion-scribe/averion-scribe/...`)
  sehingga berujung 404. Tujuan login kini disimpan sebagai path relatif-root
  aplikasi (base path ditanggalkan lebih dulu), aman untuk instalasi root
  maupun subfolder. Proteksi open-redirect tetap dipertahankan.

- Tanpa migrasi database.

## v1.0.33 — 2026-09-30

### Penyempurnaan editor artikel
- Section CTA Artikel kini memiliki breathing room responsif yang konsisten
  terhadap card sebelumnya: 20px di mobile dan 28px di desktop.
- Perubahan hanya pada wrapper layout sehingga form, preview, CTA, dan proses
  penyimpanan artikel tetap menggunakan perilaku yang sama.

### Penyempurnaan halaman 404
- Halaman tidak ditemukan ditata ulang menjadi panel premium yang proporsional,
  terpusat, dan menyatu dengan header serta footer website.
- Hierarki angka 404, judul, deskripsi, ikon SVG, dan tombol navigasi diperjelas
  dengan aksen halus yang mengikuti theme aktif.
- Tampilan responsif, mendukung dark mode, dan tetap mengembalikan status HTTP 404.
- Tanpa migrasi database.

## v1.0.32 — 2026-09-30

### Perkenalan BioLink
- Profil BioLink kini dapat menampilkan tombol perkenalan compact yang membuka
  modal premium di desktop dan bottom sheet responsif di perangkat mobile.
- Admin dapat mengatur status aktif, teks tombol, judul, isi perkenalan, foto
  opsional, animasi Typewriter/Fade/Normal, kecepatan animasi, serta CTA.
- Animasi dimulai setiap panel dibuka dan menghormati preferensi pengurangan
  gerakan. Panel mendukung klik backdrop, tombol Escape, focus trap, dan dark mode.
- Foto perkenalan memakai pipeline optimasi gambar BioLink yang sudah ada.

### Penyempurnaan UI frontend
- Scrollbar khusus panel perkenalan dibuat lebih tipis, lembut, responsif, dan
  selaras dengan theme aktif tanpa mengubah scrollbar halaman lain.
- Radius tombol frontend dipadatkan per theme agar tampil lebih tegas dan
  semi-kotak, sementara toggle theme, avatar, dan ikon sosial tetap bulat.
- Seluruh pengaturan aman secara default dan fitur Perkenalan nonaktif pada
  instalasi baru sampai diaktifkan admin.
- Tanpa migrasi database.

## v1.0.31 — 2026-09-30

### Popup pencarian header
- Kolom pencarian di header diubah menjadi tombol compact yang membuka dialog
  pencarian terpusat dengan backdrop blur dan fokus otomatis.
- Dialog dapat dibuka melalui tombol header atau shortcut `Ctrl/Cmd + K`, lalu
  ditutup dengan tombol `Esc`, keyboard Escape, atau klik area backdrop.
- Validasi minimal dua huruf ditampilkan secara inline tanpa alert bawaan.
- Tampilan responsif untuk desktop dan mobile serta mendukung dark mode dan
  seluruh UI Theme, termasuk Noir.
- Route dan hasil pencarian tetap menggunakan `/search?q=...`.
- Tanpa migrasi database.

## v1.0.30 — 2026-09-29

### Penyederhanaan card Lead Magnet frontend
- Latar gradient lembut dan ornamen SVG berupa grid serta lingkaran dihapus
  agar card lebih bersih dan fokus pada formulir.
- Card tetap mempertahankan ukuran compact, lebar penuh, border, shadow,
  spacing, warna tombol, dark mode, serta seluruh fungsi submit yang ada.
- Tanpa migrasi database.

## v1.0.29 — 2026-09-29

### Pengelolaan Lead Magnet, Subscribers, dan Email Sequence
- Dashboard Lead Magnet, Subscribers, dan Email Sequence kini memiliki aksi
  **Edit** serta **Hapus** yang jelas dengan ikon SVG dan modal konfirmasi.
- Lead Magnet yang masih dipakai subscriber atau email sequence dilindungi dari
  penghapusan agar atribusi dan automasi tidak terputus tanpa sengaja.
- Data subscriber dapat diperbarui tanpa mengubah sumber pendaftaran. Perubahan
  nama dan email ikut diterapkan ke antrean Mailketing yang masih pending.
- Penghapusan subscriber membersihkan enrollment, antrean sequence, dan antrean
  Mailketing lokal secara atomik. Data yang sudah tersimpan di akun Mailketing
  tidak dihapus otomatis.
- Penghapusan Email Sequence membersihkan step, enrollment, dan jadwal kirim
  terkait dalam satu transaksi dengan penanganan rollback yang lebih aman.

### Penyempurnaan form Lead Magnet frontend
- Setelah form berhasil dikirim, halaman kembali ke posisi card Lead Magnet
  sehingga pengunjung tidak dilempar ke bagian atas artikel.
- Pesan sukses diperbarui menjadi lebih profesional tanpa menyebut istilah
  internal "lead magnet" kepada pengunjung.
- Card form kini selebar card CTA artikel, lebih compact, memakai latar warna
  lembut serta ornamen SVG transparan yang mengikuti warna theme aktif.
- Teks catatan panjang di bawah tombol dihapus agar tampilan lebih ringkas.
- Tanpa migrasi database.

## v1.0.28 — 2026-09-29

### Lead Magnet, Mailketing, dan Email Sequence
- Form Lead Magnet kini menangkap nama dan email pengunjung, menyimpan sumber
  artikel, lead magnet, URL sumber, serta token unsubscribe yang aman.
- Sistem subscriber diperluas dengan atribusi artikel, status sinkronisasi
  Mailketing, antrean fail-soft, dan retry hingga lima percobaan.
- Integrasi Mailketing di Pengaturan mendukung token, sender, pemilihan list,
  daftar list, serta test kirim tanpa membuka token ke API eksternal.
- Dashboard baru untuk mengelola Lead Magnet, Subscribers, Email Sequence, dan
  setiap step email dengan jadwal jeda hari serta format HTML penuh.
- Email sequence menggunakan placeholder subscriber, artikel, lead magnet, dan
  tautan unsubscribe. Pengiriman dijalankan melalui antrean terjadwal dan dapat
  diproses dari dashboard.
- Management API menambahkan `GET/POST /api/email-sequences.php` agar Hermes
  Agent dapat membuat atau memperbarui draft sequence beserta isi email HTML
  secara atomik menggunakan token Integrasi.
- Migrasi database menambahkan tabel lead magnet, enrollment, pengiriman,
  antrean Mailketing, dan metadata subscriber.

## v1.0.27 — 2026-09-29

### Penyempurnaan header, footer, dan Popup Promo
- Logo dan nama blog di header serta footer kini memakai area logo berukuran
  konsisten dengan jarak presisi, sehingga tidak bergeser mengikuti dimensi asli
  file gambar pada berbagai ukuran layar.
- Container header dan footer dibuat lebih stabil pada layar lebar.
- Popup Promo mendukung jeda tampil yang dapat diatur dari admin agar tidak
  langsung muncul saat halaman dibuka.

## v1.0.26 — 2026-09-29

### Optimasi gambar dan performa frontend
- Upload raster untuk cover, branding, Popup Promo, BioLink, dan editor kini
  otomatis dibatasi dimensinya, dikompresi, serta disimpan sebagai WebP. Favicon
  raster tetap PNG dan GIF animasi dipertahankan agar kompatibilitas tidak rusak.
- Cover dan gambar berukuran besar menghasilkan varian responsif. Frontend
  menambahkan `srcset`, `sizes`, dimensi aktual, lazy loading, decoding async,
  serta prioritas tinggi untuk gambar utama guna memperbaiki LCP dan CLS.
- Aset kritis lama dioptimalkan satu kali setelah pembaruan tanpa menghapus file
  sumber. Tombol **Optimalkan gambar lama** tersedia di Rebrand untuk memproses
  branding, Popup Promo, BioLink, dan cover unggahan secara manual bila diperlukan.
- Tailwind Play CDN dan Google Fonts di frontend diganti CSS lokal terkompresi,
  sehingga tidak lagi menahan render awal. Event scroll digabung melalui
  `requestAnimationFrame`, cache aset statis ditambahkan, dan kontras teks kecil
  disempurnakan mengikuti theme aktif.
- Cache halaman publik otomatis dibersihkan setelah pembaruan sistem agar markup
  dan CSS terbaru langsung dipakai. Tanpa migrasi database.

### BYOK Google Gemini
- Petunjuk Google AI Studio mengikuti format Authorization API key terbaru
  `AQ.…`; format key lama tetap diterima demi kompatibilitas.
- Validasi client tidak lagi menganggap key Gemini harus selalu berawalan `AIza`,
  sehingga format key Google saat ini dan format mendatang dapat digunakan.

## v1.0.25 — 2026-09-28

### BYOK Google Gemini
- Pengaturan **BYOK — API Key Sendiri** kini mendukung Anthropic dan Google
  Gemini, lengkap dengan petunjuk format key yang mengikuti provider terpilih.
- Provider aktif ditampilkan bersama hint key setelah aktivasi agar konfigurasi
  lebih mudah dikenali tanpa memperlihatkan API key utuh.
- Provider dan API key divalidasi ketat sebelum dikirim ke AI Gateway; pesan
  kesalahan key/provider dibuat lebih jelas.
- API key tetap tidak disimpan di instalasi blog dan kredit AI tidak dipotong
  selama BYOK aktif.
- Tanpa migrasi database pada instalasi Scribe.

## v1.0.24 — 2026-09-28

### FAQ Management API dan penyempurnaan frontend
- `POST /api/content-health.php` kini menerima field `faq` agar Hermes Agent
  dapat mengganti seluruh FAQ artikel, menyetujuinya otomatis, menjalankan
  `dry_run`, dan mengirim optimasi serta publish dalam satu transaksi atomik.
- FAQ API divalidasi ketat, disimpan sebagai teks aman ke `seo_ai_faq`, lalu
  disinkronkan ke blok FAQ terkelola agar JSON-LD, SEO Health, frontend, dan
  editor admin memakai isi yang sama. Array kosong menghapus FAQ terkelola.
- Tombol footer kini memakai teks dan URL CTA header; route RSS `/feed` tetap
  tersedia meskipun tombol RSS di footer dihapus.
- CTA header memakai gaya outline compact dengan ikon SVG, dukungan theme,
  dark mode, hover halus, dan reduced motion.
- Toggle dark/light frontend diubah menjadi switch berikon dengan status
  aksesibel dan penyimpanan preferensi yang tetap kompatibel.
- Kontrol **Preferensi privasi Meta** dipindahkan sejajar dengan Login dan
  Powered by. Panel persetujuan mendapat hierarchy, ikon, tombol, dark mode,
  dan focus state yang lebih rapi tanpa mengubah perilaku consent.
- Tanpa migrasi database.

## v1.0.23 — 2026-09-28

### Perbaikan SEO Health dan footer
- Audit `faq_present` kini mengenali FAQ terstruktur dari tabel `seo_ai_faq`
  yang berstatus approved, selain blok FAQ lama di HTML artikel. FAQ yang belum
  disetujui tetap ditandai belum tersedia.
- Jumlah FAQ approved dimasukkan ke query dan kunci cache audit agar perubahan
  FAQ langsung tercermin pada skor SEO Health.
- Label menu sidebar **Kesehatan Konten** disederhanakan menjadi **SEO Health**.
- Tautan footer frontend **Masuk Admin** diubah menjadi **Login** tanpa mengubah
  URL tujuan atau atribut keamanan.
- Menu Jelajah di footer kini memakai ikon SVG compact untuk Beranda, Semua
  Artikel, dan Cari Artikel serta mengikuti warna theme/dark mode.
- Tanpa migrasi database.

## v1.0.22 — 2026-09-28

### Dashboard Kesehatan Konten
- Dashboard baru `/admin/content-health` mengaudit seluruh artikel berdasarkan
  ruleset SEO aktif, kelengkapan editorial, struktur isi, dan kesegaran artikel.
- Ringkasan Sehat, Perlu perhatian, dan Kritis tersedia dengan filter status,
  kategori, level kesehatan, pagination, serta prioritas perbaikan per artikel.
- Widget ringkas ditambahkan ke dashboard utama. Writer hanya melihat artikelnya
  sendiri; audit berjalan lokal tanpa memakai kredit AI.
- Hasil audit dicache per artikel dan otomatis dihitung ulang saat artikel,
  ruleset, atau tanggal berubah. File cache terlindungi dari akses web langsung.

### Management API: optimasi dan publikasi Hermes
- Endpoint `GET/POST /api/content-health.php` memungkinkan Hermes Agent membaca
  audit, mengirim optimasi isi/metadata/kategori/tag, dan menerbitkan artikel.
- POST wajib izin **manajemen & publikasi**, mendukung `dry_run`, sanitasi HTML,
  validasi SEO, snapshot konten lama, transaksi atomik, serta lock optimistik
  melalui `expected_updated_at` untuk mencegah overwrite edit terbaru.
- Slug, penulis, dan `external_ref` tidak diubah. Artikel LIVE wajib mencapai
  skor kesehatan minimal 80; cache dan sitemap diperbarui setelah write sukses.
- Petunjuk Integrasi dan dokumentasi alur Hermes diperbarui. Tanpa migrasi database.

## v1.0.21 — 2026-09-28

### Management API: CTA artikel dan kategori
- Hermes Agent dan automation eksternal kini dapat membaca serta mengatur CTA
  global, kategori, dan artikel melalui endpoint baru `GET/POST /api/article-cta.php`.
- Ingest artikel menerima objek `article_cta` opsional. Artikel tetap selalu
  dibuat sebagai `draft_ai`; penulisan CTA memerlukan izin manajemen dan disimpan
  dalam satu transaksi dengan artikelnya.
- API kategori dapat menerima CTA saat membuat atau memperbarui kategori. Detail
  artikel dan daftar kategori juga menampilkan CTA langsung beserta hasil efektif
  dari pewarisan artikel → kategori → global.
- Write API mendukung `dry_run`, validasi field ketat, perubahan idempoten,
  invalidasi cache, dan pencatatan tanpa token. `external_ref` yang sudah ada
  tidak menimpa CTA artikel sebelumnya.
- Petunjuk Integrasi dan dokumentasi API diperbarui. Tanpa migrasi database.

## v1.0.20 — 2026-09-28

### CTA per artikel dan kategori
- CTA halaman artikel kini dapat diatur berjenjang: **artikel → kategori → global**,
  dengan pilihan mengikuti induk, CTA khusus, atau menyembunyikan CTA.
- CTA khusus mendukung judul, caption, teks tombol, URL http/https tervalidasi,
  dan opsi membuka tab baru. Preview langsung tersedia di editor artikel dan form
  kategori; tampilan frontend mengikuti tema aktif dan tetap responsif.
- Instalasi lama tetap memakai CTA Beranda (Band Bawah) sampai CTA artikel global
  diatur. Beranda/BioLink tidak berubah dan CTA tidak dirender ganda.
- Klik CTA artikel mendukung event Meta Pixel `CTAClick` setelah persetujuan,
  tanpa mengirim judul, URL tujuan, atau data pribadi secara manual.
- Penyimpanan artikel/kategori beserta CTA bersifat transaksional, perubahan
  membersihkan cache terkait, dan setting CTA dibersihkan saat kontennya dihapus.
  Tanpa migrasi database.

### Identitas produk di dashboard
- Dashboard admin memiliki footer compact dengan ikon SVG dan tautan
  **Averion SEO Engine** menuju `https://adysheva.com` di tab baru.
- Footer mengikuti aksen, dark mode, dan layout responsif dashboard.

## v1.0.19 — 2026-09-28

### Meta Pixel: multi-ID dan persetujuan pengunjung
- Pengaturan **Integrasi → Meta Pixel**: aktif/nonaktif (default mati), hingga
  20 Pixel ID dengan deduplikasi, pilihan area Blog/BioLink/keduanya, dan tracking
  klik CTA opsional. Disimpan secara atomik di settings; tanpa migrasi database.
- Library dimuat asinkron setelah persetujuan pengunjung. Preferensi dapat
  diubah dan berlaku 180 hari; perubahan konfigurasi meminta persetujuan ulang.
- **PageView** dan **ViewContent** artikel dikirim per ID tanpa duplikasi;
  **CTAClick** mencatat penempatan tombol BioLink, header, atau Popup Promo.
- Admin, pengguna login, API, installer, preview, dan halaman gagal dikecualikan.
  URL/perujuk berfragmen atau berparameter selain nomor halaman tidak dilacak
  (termasuk UTM/fbclid). Tidak mengirim data pribadi atau tujuan CTA secara manual.
- Penyimpanan konfigurasi memvalidasi ID, akses admin, dan CSRF serta membersihkan
  cache halaman. Dokumentasi dan 53 pemeriksaan otomatis terisolasi disertakan
  di repository; penerimaan event di akun Meta perlu diverifikasi terpisah.

## v1.0.18 — 2026-09-27

### Header frontend: compact & premium (SaaS/editorial)
- Header lebih ringkas (tinggi 56px) namun lebih lega: container `max-w-7xl`
  dengan padding kiri/kanan lebih lapang.
- Menu navigasi **selalu 1 baris di desktop** (`whitespace-nowrap`) — tidak lagi
  pecah 2–3 baris; jarak antar-menu lebih baik, ukuran 14px + line-height rapat.
- Nav, search, dan tombol CTA tampil di layar besar (`lg+`); di **tablet/mobile**
  otomatis menjadi **menu hamburger** yang rapi (sebelumnya nav sudah menyempit di
  tablet). Search & CTA dibuat lebih compact agar tak memaksa menu menyempit.
- Hover menu lebih halus (aksen tipis), hierarchy logo/menu/search/CTA/dark-mode
  lebih konsisten. Warna brand & seluruh fungsi header dipertahankan. UI-only,
  tanpa migrasi.

## v1.0.17 — 2026-09-27

### Management API diperluas (kelola konten via token)
Semua reuse pola/auth Ingest (`Authorization: Bearer`, `ingest_manage_enabled`
untuk tulis, prepared statements, respons `{ok:...}`). Tanpa migrasi.
- **`api/articles.php`** (baca): tambah `?id=&full=1` (satu artikel lengkap +
  content; 404 bila tak ada) dan `?category_id=` (filter daftar per kategori).
- **`api/categories.php`** GET: tambah `article_count` per kategori.
- **`api/update-article-category.php`** (baru, tulis): ubah **hanya** kategori
  artikel — single atau batch, `dry_run`, transaksi **all-or-nothing**, idempoten
  (`unchanged`), tak menyentuh title/slug/content/status/published_at. Invalidasi
  cache + sitemap.
- **`api/update-article-seo.php`** (baru, tulis): update **parsial** metadata SEO
  (meta_title 45–60, meta_description 120–160, focus_keyword, excerpt,
  related_keywords, tags, title) — plain-text (strip_tags, tolak `<script>`),
  **title tanpa regenerasi slug**, tak mengubah status/content, idempoten,
  before/after. `dry_run` didukung.
- Aturan konten dijaga: artikel published tetap published, draft_ai tetap draft_ai,
  tak ada publish/hapus otomatis.

## v1.0.16 — 2026-09-27

### UI Hybrid (mobile): thumbnail feed disederhanakan
- Pada mode Homepage **Hybrid** di **mobile**, thumbnail "Artikel Terbaru" kini
  memakai **gradient soft + pola SVG transparan** (dots / grid / diagonal /
  lingkaran abstrak, opacity rendah) **tanpa teks judul di dalam thumbnail** —
  judul tetap hanya di area konten. Warna mengikuti karakter artikel (muted).
- Ringan (inline SVG kecil, deterministik per-artikel). Compact dipertahankan.
- **Khusus mobile Hybrid.** Desktop dan Homepage Blog `/blog` tidak berubah.
  UI-only, tanpa migrasi.

## v1.0.15 — 2026-09-27

### Powered by fleksibel (footer)
- Pengaturan **Rebrand → Warna & Footer**: teks Powered by kini **bebas diedit**
  (default "Powered by Averion SEO Engine"), **URL custom** (kosong = teks tampil
  tanpa link), dan opsi **buka di tab baru**. Checkbox tampil/sembunyi tetap ada.
- Berlaku ke footer Blog & BioLink. Disimpan di settings.

### UI Hybrid: feed artikel compact
- Mode Homepage **Hybrid**: bagian "Artikel Terbaru" kini **feed compact** —
  kartu kecil (thumbnail kecil, judul maks 2 baris, kategori/tanggal kecil), 3 per
  baris di desktop, horizontal (thumb kiri) di mobile, tanpa pagination, plus tombol
  **"Lihat Semua Artikel" → /blog**. Tidak lagi sedominan profil BioLink.
- Homepage Blog `/blog` **tidak berubah**. Ikut tema/aksen/dark mode. UI-only, tanpa migrasi.

## v1.0.14 — 2026-09-26

### Fitur baru: Popup Promo melayang (frontend)
- Popup promo compact & melayang di halaman blog, bisa diatur dari **Pengaturan →
  Rebrand → Popup Promo**: aktif/nonaktif, caption, gambar (opsional), teks & link
  CTA (buka tab baru), dan **posisi** (Kiri/Tengah/Kanan × Atas/Bawah).
- Tombol close (disimpan per-pengunjung; promo yang diubah tampil lagi), animasi
  masuk halus, jarak aman dari tepi, posisi atas menghindari header, posisi tengah
  tetap compact, dan responsif di mobile. Popup mengikuti tema aktif.
- Default nonaktif; tampil hanya bila caption terisi. Disimpan di settings.

### Perbaikan
- **Noir**: footer & menu mobile kini ikut gelap (charcoal) — sebelumnya latar
  terang membuat teks krem tak terbaca.
- **Topbar admin (Brand/Command Dark)**: tombol ganti-tema, nama & role user, garis
  pemisah, dan avatar diberi warna kontras (putih) agar terbaca di atas warna aksen.
- Semua UI-only, tanpa migrasi.

## v1.0.13 — 2026-09-26

### UI Theme diterapkan ke frontend publik
- Pilihan **UI Theme** (Default, Airy, Vivid, Corporate, Minimal, Noir) di
  Pengaturan → Appearance kini **memengaruhi frontend publik** (Homepage Blog,
  halaman artikel, & BioLink), bukan hanya dashboard admin.
- Tiap tema punya identitas visual berbeda — bukan sekadar warna: **tipografi**
  (bobot/tracking/leading heading), **spacing/rhythm** section, **radius**,
  **border**, **shadow**, **style kartu**, **tombol**, **header**, dan **latar**.
  Contoh: Airy (lapang, frosted, radius besar), Corporate (padat, border tegas,
  tanpa lift), Minimal (borderless & flat), Vivid (tombol/badge gradient), Noir
  (charcoal hangat + teks krem sejak mode terang).
- Diterapkan konsisten ke `.fe-card`/featured, pill/badge, header, hero, prose
  artikel, dan blok BioLink. Konfigurasi global → langsung dipakai Blog & BioLink.
- Pratinjau di halaman Appearance ditambah representasi **publik (Blog)** yang
  lebih akurat. Sidebar & Topbar Style tetap khusus admin.

### Peningkatan UI (footer)
- Link **"Peta Situs"** di footer diganti **"Semua Artikel"** (`/blog`) yang lebih
  berguna bagi pengunjung. `sitemap.xml` tetap aktif di `/sitemap.xml` untuk mesin
  pencari.
- Semua UI-only, tanpa perubahan skema/logika. Tanpa migrasi.

## v1.0.12 — 2026-09-26

### Fitur: Regenerasi cover fallback
- Tombol **"Regenerasi cover"** di Pengaturan → Rebrand: membuat ulang semua cover
  otomatis (SVG) dengan gaya/palet terkini + mengisi cover yang hilang. **Cover
  yang diunggah sendiri tidak diubah.**
- Berguna setelah pembaruan mengubah tampilan cover: file gambar berada di
  `uploads/` yang (memang) tidak ikut terbawa saat Update Sistem, sehingga cover
  lama perlu diregenerasi sekali agar konsisten dengan palet baru (v1.0.11).
- Helper `scribeBackfillFallbackCovers()` menerima parameter `$force` untuk
  regenerasi ulang meski file sudah ada. Aksi-only, tanpa migrasi.

## v1.0.11 — 2026-09-26

### Peningkatan UI (homepage blog)
- Homepage blog dibuat lebih premium & compact: ruang kosong hero dan antar-section
  dikurangi, heading/spacing/alignment dirapikan.
- **Hero** diberi pola titik SVG sangat halus (opacity ~5–8%, ikut warna aksen &
  dark/light, memudar dengan mask) — bukan foto. Mesh background diperhalus.
- **Kartu artikel**: shadow sangat halus, hover ringan `translateY(-2px)`, border
  hover lebih kalem. **Featured** dikecilkan agar tidak dominan.
- **Thumbnail fallback** memakai palet satu-karakter saturasi rendah (teal, navy,
  muted blue, soft purple, warm orange, emerald) menggantikan warna mencolok.
  Cover baru & artikel yang disimpan ulang otomatis memakai palet ini.
- Grid lebih rapat, hierarchy (kategori/judul/excerpt/author/tanggal) tetap jelas,
  responsif. UI-only: tanpa perubahan konten/route/SEO/logika. Tanpa migrasi.

## v1.0.10 — 2026-09-26

### Fitur baru: Pratinjau artikel
- Route admin `/admin/articles/{id}/preview` merender artikel (termasuk **draft**
  yang tak tampil publik) memakai template artikel publik yang sama — hasil persis.
  Mengutamakan `draft_content` (autosave terbaru), ber-banner "Mode Pratinjau",
  `noindex`, tanpa cache, ber-guard login (writer hanya artikelnya sendiri).
- Tombol **Pratinjau** di editor (autosave dulu → buka tab baru) dan ikon
  pratinjau di daftar artikel.

### Route Homepage Blog terpisah
- Route baru **`/blog`** selalu menampilkan homepage blog (hero, kategori,
  featured, grid, pagination) apa pun `home_mode` — sehingga `/` bisa dipakai
  khusus BioLink. Reuse penuh logika home (tanpa duplikasi).
- Link **"Blog"** di navigasi header (desktop + menu mobile).
- Panel Biolink admin menampilkan **URL homepage** (BioLink/Blog) yang menyesuaikan
  mode terpilih, lengkap tombol Salin & Buka.

### Peningkatan UI
- **Halaman artikel** dipoles: alignment konsisten (breadcrumb/judul/cover/konten
  sejajar `max-w-3xl`), cover lebih rendah & elegan, tipografi lebih terbaca
  (ukuran/line-height/jarak), Daftar Isi lebih compact + sorot bagian aktif
  (scrollspy), responsif desktop & mobile.
- **Nama blog** kini tampil berdampingan dengan logo (lanjutan v1.0.8).
- Navigasi **"Ke atas"** footer menjadi **tombol ikon melayang** (muncul saat
  scroll), menggantikan tombol teks.
- Semua UI-only, tanpa perubahan skema/logika. Tanpa migrasi baru.

## v1.0.9 — 2026-09-26

### Perluasan: Management API (kelola situs via token)
API ber-token (reuse token & pola auth Ingest API) diperluas jadi API manajemen
agar sistem eksternal bisa mengelola kategori & setelan SEO/brand serta membaca
daftar konten. **Baca** butuh Ingest API aktif; **tulis** butuh toggle baru.
- Toggle baru **"Izinkan kelola kategori & setelan (manajemen)"** (`ingest_manage_enabled`,
  default **mati**) di Pengaturan → Integrasi — memisahkan "kirim draft artikel"
  (aman) dari "kelola situs" (kuat). Perubahan tulis **langsung LIVE**.
- `GET/POST /api/categories.php` — daftar / buat / ubah kategori (uniqueSlug,
  tolak self-parent & parent tak dikenal). Tanpa hapus di v1.
- `GET/POST /api/settings.php` — baca / ubah setelan SEO & brand dengan
  **whitelist ketat** (blog_name, blog_tagline, brand_og_default, default_language,
  brandvoice_*). Key di luar whitelist → **400** (token/lisensi/kredensial mustahil
  tersentuh). Setelah tulis: flush cache + regen sitemap.
- `GET /api/articles.php?limit=&offset=` — daftar artikel terbaru (baca, anti-dobel).
- `GET /api/seo-rules.php` — ruleset SEO aktif (baca) untuk menyelaraskan artikel.
- Semua endpoint: auth `Authorization: Bearer <token>` (hash-only, timing-safe),
  401 token salah/absen, 403 bila fitur mati, 405/415 metode/tipe, 429 rate-limit.
  Setiap aksi tulis dicatat (tanpa token) ke `cache/logs/ingest.log`.
- Tanpa migrasi (kategori & settings sudah ada).

## v1.0.8 — 2026-09-25

### Perbaikan UI (frontend publik)
- Nama blog kini **selalu tampil berdampingan dengan logo** di header dan footer
  theme-default. Sebelumnya, saat logo di-unggah, teks nama blog disembunyikan
  (hanya logo) — sehingga nama tak muncul walau sudah diisi. Logo sedikit
  diperkecil agar nama muat rapi.
- Catatan: jika nama blog masih terlihat sebagai "Averion SEO Engine", isi
  **Pengaturan → Rebrand → Nama Blog** (fieldnya kosong akan memakai nama default).

## v1.0.7 — 2026-09-25

### Fitur baru: Ingest API (kirim artikel via HTTP)
- Endpoint mesin `POST /api/ingest-article.php` agar sistem eksternal (mis.
  automation/AI milik pemilik situs) bisa mengirim artikel jadi ke blog.
- **Selalu masuk sebagai Draft AI** untuk review manual — **tidak pernah**
  publish otomatis.
- Auth via header `Authorization: Bearer <token>`. Token 32-byte hex; server
  hanya menyimpan **hash sha256**-nya (nilai asli ditampilkan sekali saat dibuat,
  tak bisa dilihat ulang). Verifikasi timing-safe (`hash_equals`).
- **Default nonaktif** (`ingest_enabled`) demi keamanan; token wajib ada sebelum
  bisa diaktifkan.
- **Idempoten** via `external_ref` (kolom baru `articles.external_ref`, UNIQUE):
  kirim ulang ref yang sama → mengembalikan artikel yang sama, tidak dobel.
- Konten disanitasi (`sanitizeArticleHtml`), slug otomatis unik, cover fallback
  SVG otomatis, kategori dipetakan bila cocok (tak membuat kategori baru), tag
  dibuat otomatis. Penulis default dapat dipilih (Pengaturan → Integrasi).
- Panel admin baru **Pengaturan → Integrasi**: toggle aktif, generate/putar
  ulang token, pilih penulis default, URL endpoint + contoh curl.
- Kode HTTP: 201 dibuat, 200 idempoten, 400/422 payload, 401 token, 403 nonaktif,
  405 metode, 415 content-type, 429 rate-limit. Log permintaan (tanpa token) ke
  `cache/logs/ingest.log`.
- Migrasi `1.0.7` (`articles.external_ref` + UNIQUE KEY).

## v1.0.6 — 2026-09-25

### Fitur baru: Biolink (link-in-bio)
- **Mode homepage** (Pengaturan → Biolink): `blog` (default), `biolink` (profil +
  tautan saja), atau `hybrid` (profil biolink di atas, artikel terbaru di bawah).
- **Builder** `/admin/biolink`: profil (nama, bio, avatar, header gradien/warna/
  gambar) + blok tersusun — **Tombol** (ikon/foto, gaya solid/garis/lembut),
  **Sosial** (Instagram/Facebook/YouTube/TikTok/X/LinkedIn/WhatsApp/Telegram/
  Website/Email), **Teks**, **Gambar**, **Pembatas**. Reorder, aktif/nonaktif, hapus.
- Render mobile-first, ikut warna aksen rebrand & dark/light.
- Migrasi `1.0.6` (tabel `bio_blocks`). Profil disimpan di settings.

### Peningkatan UI mobile
- Header publik: **menu hamburger** di layar kecil (kategori + pencarian yang
  sebelumnya hilang di HP kini terjangkau).
- Footer: layout kolom rapi di mobile (brand + Jelajah + Kategori 2 kolom) dan
  bar bawah membungkus rapi (copyright, Masuk Admin, Powered by, Ke atas).

## v1.0.5 — 2026-09-21

### Peningkatan UI (frontend publik)
- Footer theme-default didesain ulang jadi lebih modern: layout 3 kolom
  (brand + tagline + tombol RSS, kolom "Jelajah", kolom "Kategori" dengan
  micro-interaction panah saat hover), garis aksen gradien + glow halus di
  tepi atas, dan bar bawah (copyright, Powered by, tombol "Ke atas").
- Link **"Masuk Admin"** di footer (menuju halaman login, `rel="nofollow"`).
- Konsisten warna aksen rebrand (dark/light), tanpa perubahan skema/logika.

## v1.0.4 — 2026-07-20

### Peningkatan UI
- Kartu statistik KPI dashboard (Published/Draft/Draft AI/Terjadwal) dipadatkan
  jadi layout horizontal: ikon chip di kiri, angka besar + label di kanan —
  tinggi kartu turun signifikan, tanpa ruang kosong. Kartu hero (Published)
  ikut layout yang sama, gradient accent dipertahankan. Kepadatan tetap
  mengikuti preset Appearance (density/font-scale).
- UI-only: tanpa perubahan skema atau logika penagihan kredit.

## v1.0.3 — 2026-07-20

### Peningkatan UI
- Modal konfirmasi/peringatan global (`scribeConfirm`/`scribeAlert`) menggantikan
  dialog `confirm()`/`alert()` bawaan browser di seluruh panel admin. Tampilan
  selaras tema (glass/blur, dark/light, ikon Lucide, radius 8px), varian danger
  untuk aksi hapus, dukungan keyboard (Esc/Enter/Tab-trap) dan fokus kembali.
- Form hapus (artikel/kategori/tag/pengguna/redirect/BYOK) memakai atribut
  `data-confirm` — konfirmasi konsisten tanpa popup native.
- Aksi berisiko di editor (terapkan update, timpa outline, tulis draft) dan
  notifikasi upload gagal kini lewat modal yang sama.
- UI-only: tanpa perubahan skema atau logika penagihan kredit.

## v1.0.2 — 2026-07-20

### Peningkatan UI
- Infobox edukasi komposisi kredit "1 artikel = apa saja" di halaman Kredit
  (collapsible) dan panel AI Assist editor (popover). Rincian dihitung dari
  satu sumber konstanta biaya — transparansi biaya, tanpa perubahan skema atau
  logika penagihan.

## v1.0.1 — 2026-07-20

### Perbaikan
- Migrasi `1.0.5` memakai `ALTER` polos (idempotensi dari version-tracking
  runner) menggantikan `ADD COLUMN/KEY IF NOT EXISTS` yang khusus MariaDB —
  memperbaiki galat error 1064 di MySQL 8 (mayoritas shared hosting).
- Self-update: endpoint disusun tanpa prefix `/api` (`{vendor}/seo/check-update`)
  dan versi dikirim dalam format `x.y.z` (buang suffix `-dev`) agar lolos
  validasi license server.

## v1.0.0 — 2026-07-20

Rilis publik pertama. Produk SEO content engine PHP murni terdistribusi
(dipasang customer di hosting sendiri, self-update via GitHub Release, semua
permintaan AI lewat gateway `ai.averion.id`, lisensi/enforcement via
`vendor.averion.id`).

### Admin & Workflow AI (Track A)
- Installer 5 langkah + migrasi terversi (runner sadar komentar/string).
- Enforcement lisensi signed-token Ed25519 (state machine: valid/grace/expired/
  suspended/pending) — halaman enforcement selalu identitas Averion.
- Auth dua peran: admin (penuh) & writer (artikel sendiri + AI).
- Editor artikel + workflow AI: riset keyword → outline editable → draft
  per-seksi (anti-timeout, retry) → generate meta → analisis SEO → FAQ approve.
- Skor SEO dua lapis: checklist lokal (tanpa kredit) + analisis AI berbayar.
- Kredit granular + BYOK (key hanya diteruskan ke gateway, tak disimpan client).
- Manajer redirect (301 otomatis saat slug berubah), taksonomi, pengguna.
- Self-update client (cek versi + unduh paket via token, backup+rollback).
- Rebrand & Appearance: identitas customer (nama/logo/favicon/OG/accent/CTA/
  powered-by) + 6 preset UI berkarakter (density/border/shadow/surface/topbar).

### Frontend Publik theme-default (Track B)
- Router publik: home, kategori, tag, pencarian, artikel, sitemap, feed, 404.
- Halaman artikel: breadcrumb, TOC otomatis, blok FAQ, share, related, prev/next.
- SEO head lengkap: title/description/canonical/OG/Twitter + JSON-LD Article +
  BreadcrumbList + FAQPage (dari FAQ approved).
- Cover: upload (varian srcset 480/960) atau fallback SVG deterministik;
  OG image raster (PNG) untuk crawler saat cover fallback.
- Sanitasi render ganda (whitelist) — pertahanan kedua saat menampilkan konten.
- Sitemap.xml + RSS 2.0 event-driven (regen saat publish/update/delete) — tanpa
  cron; file cache halaman (TTL 10 mnt, invalidate targeted, bypass sesi/flash);
  lazy scheduled publish (promosi scheduled→published pada kunjungan publik).
- Newsletter CTA (opsional) + tabel subscribers.
- Bahasa visual: gradient mesh, glass, depth (light/dark), radius 6–8px,
  Plus Jakarta Sans + Fraunces.

### Performa
- Lighthouse (lokal, desktop): Home Performance 99 / SEO 100; Artikel
  Performance 100 / SEO 100 (CLS 0, LCP ~0.6s).

### Catatan
- Distribusi self-update produksi membutuhkan jalur produk `seo` di license
  server (`/api/seo/check-update` + `/seo/download-update` + panel Paket Update
  SEO) — dikerjakan terpisah di repo `averion-license`.
