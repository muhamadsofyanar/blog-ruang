-- Migrasi 1.0.28 — lead magnet, subscriber attribution, Mailketing, dan email sequence.
-- Semua email masuk tetap opt-in dan artikel tidak diubah oleh migrasi ini.
-- Tanda titik-koma tidak dipakai di komentar agar lolos gerbang artefak.

ALTER TABLE `subscribers`
  ADD COLUMN `name` VARCHAR(120) NULL AFTER `email`;

ALTER TABLE `subscribers`
  ADD COLUMN `source_article_id` INT UNSIGNED NULL AFTER `source`;

ALTER TABLE `subscribers`
  ADD COLUMN `source_url` VARCHAR(500) NULL AFTER `source_article_id`;

ALTER TABLE `subscribers`
  ADD COLUMN `lead_magnet_id` INT UNSIGNED NULL AFTER `source_url`;

ALTER TABLE `subscribers`
  ADD COLUMN `unsubscribe_token` CHAR(64) NULL AFTER `lead_magnet_id`;

ALTER TABLE `subscribers`
  ADD COLUMN `mailketing_list_id` VARCHAR(40) NULL AFTER `unsubscribe_token`;

ALTER TABLE `subscribers`
  ADD COLUMN `mailketing_status` ENUM('pending','sent','failed','skipped') NULL DEFAULT NULL AFTER `mailketing_list_id`;

ALTER TABLE `subscribers`
  ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL AFTER `created_at`;

ALTER TABLE `subscribers`
  ADD UNIQUE KEY `uq_subscribers_unsubscribe_token` (`unsubscribe_token`);

ALTER TABLE `subscribers`
  ADD KEY `idx_subscribers_source_article` (`source_article_id`);

ALTER TABLE `subscribers`
  ADD KEY `idx_subscribers_lead_magnet` (`lead_magnet_id`);

CREATE TABLE IF NOT EXISTS `lead_magnets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(190) NOT NULL,
  `slug` VARCHAR(190) NOT NULL,
  `description` TEXT NULL,
  `delivery_url` VARCHAR(500) NULL,
  `cta_label` VARCHAR(80) NOT NULL DEFAULT 'Dapatkan Gratis',
  `status` ENUM('draft','published','paused') NOT NULL DEFAULT 'draft',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lead_magnets_slug` (`slug`),
  KEY `idx_lead_magnets_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_sequences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `trigger_event` VARCHAR(40) NOT NULL DEFAULT 'subscriber_created',
  `lead_magnet_id` INT UNSIGNED NULL,
  `provider` VARCHAR(50) NOT NULL DEFAULT 'mailketing',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_sequences_status` (`status`),
  KEY `idx_email_sequences_trigger` (`trigger_event`, `lead_magnet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_sequence_steps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sequence_id` INT UNSIGNED NOT NULL,
  `step_number` INT UNSIGNED NOT NULL DEFAULT 1,
  `delay_days` INT UNSIGNED NOT NULL DEFAULT 0,
  `subject` VARCHAR(255) NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `body_format` ENUM('text','html') NOT NULL DEFAULT 'html',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_sequence_step` (`sequence_id`, `step_number`),
  KEY `idx_email_sequence_steps_sequence` (`sequence_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_sequence_enrollments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sequence_id` INT UNSIGNED NOT NULL,
  `subscriber_id` INT UNSIGNED NOT NULL,
  `trigger_ref` VARCHAR(190) NULL,
  `enrolled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('active','completed','unsubscribed') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_sequence_enrollment` (`sequence_id`, `subscriber_id`),
  KEY `idx_email_sequence_enrollment_subscriber` (`subscriber_id`),
  KEY `idx_email_sequence_enrollment_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_sequence_sends` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` BIGINT UNSIGNED NOT NULL,
  `step_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  `scheduled_at` DATETIME NOT NULL,
  `sent_at` DATETIME NULL,
  `response` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_sequence_send` (`enrollment_id`, `step_id`),
  KEY `idx_email_sequence_send_due` (`status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scribe_mailketing_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subscriber_id` INT UNSIGNED NOT NULL,
  `list_id` VARCHAR(40) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `first_name` VARCHAR(120) NULL,
  `last_name` VARCHAR(120) NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME NULL,
  `response` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scribe_mailketing_subscriber_list` (`subscriber_id`, `list_id`),
  KEY `idx_scribe_mailketing_queue` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
