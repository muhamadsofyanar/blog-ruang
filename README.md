# Averion SEO Engine 1.0.63 — GitHub & Coolify

Paket ini menambahkan deployment PHP 8.3 + Apache + MariaDB 11.4. Aplikasi dan mekanisme lisensi vendor tetap dipertahankan. Gunakan repository **private**, kecuali Anda memiliki izin untuk memublikasikan source vendor.

## 1. Upload manual ke GitHub

1. Ekstrak ZIP. Buka folder hasil ekstraksi yang berisi `Dockerfile`, `index.php`, dan `docker-compose.yaml`.
2. Buat repository GitHub kosong (disarankan private).
3. Pilih **Add file → Upload files**. Unggah **isi** folder ke root repository; jangan unggah ZIP atau folder pembungkusnya.
4. Jika batas upload browser tercapai, unggah beberapa batch, maksimal 100 file per batch. Pertahankan struktur folder.
5. Pastikan `.htaccess`, `.gitignore`, `.dockerignore`, dan `.env.example` ikut masuk. Aktifkan tampilan hidden files di komputer. Bila browser tidak menyertakannya, gunakan **Add file → Create new file** untuk membuat file tersebut dengan isi dari paket ini.
6. Pastikan root repository menampilkan `Dockerfile`, `docker-compose.yaml`, `index.php`, folder `docker`, `installer`, `migrations`, `uploads`, dan `cache`. Jangan commit `.env`, `config.php`, password, atau data pengguna.

## 2. Konfigurasi Coolify

1. Tambahkan application dari repository tersebut. Untuk private repository, hubungkan GitHub App atau deploy key dengan izin membaca repository.
2. Pilih **Docker Compose / Compose** sebagai build strategy, bukan Nixpacks.
3. Base Directory: `/`. Docker Compose Location: `/docker-compose.yaml` (atau `docker-compose.yaml` jika UI mengharuskan path relatif).
4. Muat konfigurasi Compose, lalu isi Environment Variables:

| Variable | Nilai |
| --- | --- |
| `APP_URL` | `https://blog.domainanda.com` tanpa trailing slash atau `:80` |
| `DB_NAME` | `averion_scribe` |
| `DB_USER` | `averion` |
| `DB_PASSWORD` | Password database unik, acak, minimal 32 karakter |
| `DB_ROOT_PASSWORD` | Password root berbeda, acak, minimal 32 karakter |
| `INSTALLER_TOKEN` | Token setup berbeda, acak, minimal 32 karakter |

Buat password memakai password manager. Jangan gunakan nilai contoh atau masukkan secret ke GitHub. Bila password mengandung `$`, pastikan interpolasi Coolify/Compose tidak mengubahnya; nilai acak alfanumerik panjang memudahkan konfigurasi.

5. Arahkan DNS domain ke IP server Coolify. Tambahkan domain pada service **app**, menuju internal port **80**. Jika UI memakai suffix port, masukkan `https://blog.domainanda.com:80`; alamat pengunjung tetap `https://blog.domainanda.com`.
6. Jangan beri domain atau port publik pada service `database`.
7. Deploy, periksa build/runtime logs, tunggu database sehat.

## 3. Instalasi pertama

1. Buka `https://blog.domainanda.com/installer/` melalui HTTPS.
2. Masukkan `INSTALLER_TOKEN`. Token diminta sebelum halaman installer untuk melindungi pembuatan admin pertama.
3. Jalankan requirements check.
4. Konfigurasi database terisi dari environment: host **database**, port **3306**, nama/user/password sesuai variabel Coolify. Periksa APP_URL agar sama dengan domain HTTPS dan domain lisensi.
5. Simpan dan jalankan migrasi. Buat akun admin dengan password kuat.
6. Aktifkan lisensi vendor, atau lewati jika belum tersedia. Lisensi tetap dibutuhkan untuk fitur yang dibatasi vendor.
7. Selesaikan sampai halaman akhir agar `install.lock` berhasil ditulis. Login di `/login`.

Konfigurasi database disimpan installer dalam volume. Mengubah environment setelah instalasi **tidak otomatis mengubah config.php atau password MariaDB yang sudah diinisialisasi**. Perubahan kredensial harus dilakukan terkoordinasi pada database dan konfigurasi tersimpan. Jangan menghapus volume untuk mencoba memperbaiki password.

## 4. Data permanen dan update

Compose mendefinisikan volume untuk database, konfigurasi/lock, uploads, cache, dan PHP sessions. Jangan mount seluruh `/var/www/html` karena akan menutupi source baru saat redeploy. Jangan menghapus volume ketika redeploy.

Self-update melalui dashboard ditolak khusus pada container. Update source di GitHub lalu redeploy Coolify. Untuk upgrade vendor yang membawa migrasi baru, backup terlebih dahulu dan jalankan migration runner sesuai instruksi vendor; paket ini tidak menjalankan upgrade schema otomatis saat startup.

Backup database dan volume konfigurasi/uploads secara rutin serta uji restore. Volume permanen bukan backup. Konfigurasi dan database juga dapat berisi API key, sehingga backup harus dilindungi.

## 5. Verifikasi setelah deployment

- `/healthz.php` memberi `ok`; ini liveness Apache/PHP, bukan pengecekan seluruh aplikasi/database/lisensi.
- Installer dapat menyelesaikan migrasi dan membuat admin.
- `/login`, `/admin`, artikel publik, gambar upload, sitemap, dan robots bekerja.
- `/cache/`, `/migrations/1.0.0.sql`, `/config.php`, `/docker/entrypoint.sh` tidak dapat dibaca publik (403).
- Redeploy sekali; admin, artikel, uploads, dan lock instalasi harus tetap ada.
- Uji integrasi AI/lisensi/email menggunakan akun vendor Anda. Server perlu akses keluar HTTPS ke vendor dan penyedia integrasi.

## 6. Batas verifikasi paket

Lihat `VERIFICATION.md` untuk pemeriksaan yang telah dilakukan dan yang masih harus dilakukan di server. Tidak ada klaim bahwa semua fitur atau keamanan aplikasi telah diaudit menyeluruh.

Referensi resmi: https://coolify.io/docs/applications/builds/docker-compose dan https://coolify.io/docs/applications/configuration/persistent-storage
