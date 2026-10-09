-- ════════════════════════════════════════════════════════════════════════
-- Migrasi 1.0.7 — Ingest API (kirim artikel jadi via HTTP ber-token).
-- Menambah kolom articles.external_ref (referensi unik dari sistem pengirim)
-- untuk idempotensi anti-dobel. Profil/token fitur disimpan di tabel settings
-- (getSetting/setSetting) sehingga TIDAK perlu tabel baru.
--
-- ATURAN KERAS: tidak ada tanda titik-koma di dalam komentar SQL ini.
-- PORTABILITAS: ALTER pakai bentuk POLOS (tanpa IF NOT EXISTS) — MySQL 8 TIDAK
-- mendukung ADD COLUMN/KEY IF NOT EXISTS (khusus MariaDB). Idempotensi dijamin
-- oleh version-tracking migration runner (applied_migrations di settings).
-- ════════════════════════════════════════════════════════════════════════

-- articles.external_ref: id/ref dari sistem eksternal pengirim (anti-dobel)
ALTER TABLE `articles`
  ADD COLUMN `external_ref` VARCHAR(190) NULL AFTER `canonical_url`;

ALTER TABLE `articles`
  ADD UNIQUE KEY `uq_articles_external_ref` (`external_ref`);
