-- ════════════════════════════════════════════════════════════════════════
-- Migrasi 1.0.3 — tambah bahasa Malaysia (ms) ke ENUM articles.language.
-- Gateway sudah menerima ms. MODIFY bersifat idempotent (aman dijalankan 2x).
-- ATURAN KERAS: tidak ada tanda titik-koma di dalam komentar SQL ini.
-- ════════════════════════════════════════════════════════════════════════

ALTER TABLE `articles`
  MODIFY COLUMN `language` ENUM('id','en','ms') NOT NULL DEFAULT 'id';
