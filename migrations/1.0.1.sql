-- ════════════════════════════════════════════════════════════════════════
-- Migrasi 1.0.1 — kolom alt text cover artikel (aksesibilitas + SEO gambar).
-- Dijalankan sekali (dicatat runner). ALTER tanpa IF NOT EXISTS agar portabel
-- ke MySQL 8 maupun MariaDB. ATURAN KERAS: tidak ada titik-koma di komentar.
-- ════════════════════════════════════════════════════════════════════════

ALTER TABLE `articles`
  ADD COLUMN `cover_alt` VARCHAR(255) NULL AFTER `cover_image`;
