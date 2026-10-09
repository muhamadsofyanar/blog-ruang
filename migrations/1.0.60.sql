-- Migrasi 1.0.60 — ketahanan antrean email: lacak jumlah percobaan dan waktu
-- percobaan terakhir agar retry terbatas, pencegahan kirim ganda, serta error
-- dan waktu percobaan terakhir terlihat. Tidak mengubah isi atau jadwal sequence.
-- Tanda titik-koma tidak dipakai di komentar agar lolos gerbang artefak.

ALTER TABLE `email_sequence_sends`
  ADD COLUMN `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`;

ALTER TABLE `email_sequence_sends`
  ADD COLUMN `last_attempt_at` DATETIME NULL AFTER `sent_at`;

ALTER TABLE `scribe_mailketing_queue`
  ADD COLUMN `last_attempt_at` DATETIME NULL AFTER `sent_at`;
