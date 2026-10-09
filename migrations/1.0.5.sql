-- ════════════════════════════════════════════════════════════════════════
-- Migrasi 1.0.5 (B-02) — dua penambah:
--   1) tabel subscribers (newsletter CTA beranda) — deliverable B-01 tertunda
--   2) kolom articles.is_pinned (satu artikel pinned jadi featured beranda)
-- CATATAN: jumlah tabel BUKAN batasan tetap. Skema tumbuh via migrasi bernomor.
-- ATURAN KERAS: tidak ada titik-koma di dalam komentar SQL ini.
-- PORTABILITAS: ALTER pakai bentuk POLOS (tanpa IF NOT EXISTS) — MySQL 8 TIDAK
-- mendukung ADD COLUMN/KEY IF NOT EXISTS (itu khusus MariaDB). Idempotensi
-- dijamin oleh version-tracking migration runner (applied_migrations di settings).
-- ════════════════════════════════════════════════════════════════════════

-- subscribers: email langganan dari CTA newsletter beranda (opt-in publik)
CREATE TABLE IF NOT EXISTS `subscribers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `source` VARCHAR(60) NULL,
  `unsubscribed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscribers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- articles.is_pinned: satu artikel disematkan sebagai featured beranda
ALTER TABLE `articles`
  ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cover_is_fallback`;

ALTER TABLE `articles`
  ADD KEY `idx_articles_pinned` (`is_pinned`);
