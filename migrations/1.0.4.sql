-- ════════════════════════════════════════════════════════════════════════
-- Migrasi 1.0.4 — kolom users.last_login_at (jejak login) + redirects.source
-- (pembeda otomatis vs manual). ATURAN KERAS: tidak ada titik-koma di komentar.
-- ════════════════════════════════════════════════════════════════════════

ALTER TABLE `users`
  ADD COLUMN `last_login_at` DATETIME NULL AFTER `is_active`;

ALTER TABLE `redirects`
  ADD COLUMN `source` ENUM('auto','manual') NOT NULL DEFAULT 'auto' AFTER `type`;
