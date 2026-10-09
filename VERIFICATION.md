# Pemeriksaan paket

- PHP 8.3.6 syntax lint: 182 file PHP lulus.
- Shell syntax entrypoint: lulus `sh -n`.
- Compose YAML dapat diparse: 2 services, 5 named volumes.
- HTTP smoke dengan PHP development server: health endpoint lulus; installer menolak token kosong/salah; token benar membuka halaman requirements.
- Pemecah SQL migrasi dapat membaca seluruh file migrasi dengan hasil statement nonkosong. SQL belum dieksekusi terhadap database.

Docker dan daemon container tidak tersedia dalam lingkungan pemeriksaan. Build image, konfigurasi Apache, koneksi/migrasi MariaDB, full installer, persistence saat redeploy, dan integrasi vendor belum diuji end-to-end. Gunakan checklist README pada deployment pertama. Tes HTTP memakai development server, sehingga tidak membuktikan aturan akses Apache.
