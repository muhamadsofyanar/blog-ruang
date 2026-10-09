-- Averion SEO Engine 1.0.6 — Biolink (link-in-bio) untuk homepage.
-- Menambah SATU tabel bio_blocks (blok tersusun). Profil biolink disimpan di
-- tabel settings (grup biolink) mengikuti pola rebrand, jadi tak ada tabel profil.
--
-- Aturan skema (memori averion-scribe): aman MySQL 8 dan MariaDB.
--   CREATE TABLE IF NOT EXISTS (bukan ADD COLUMN IF NOT EXISTS ala MariaDB)
--   TANPA FOREIGN KEY, KEY didefinisikan di dalam CREATE (idempoten bersama tabel)
--   TANPA titik-koma di dalam komentar

CREATE TABLE IF NOT EXISTS bio_blocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(24) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  config_json TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
