-- ════════════════════════════════════════════════════════════════════════
-- Averion SEO Engine (averion-scribe) — migrasi awal 1.0.0
-- Skema inti client sesuai KONSEP ARSITEKTUR section 6.2 (12 tabel domain).
-- InnoDB utf8mb4. IF NOT EXISTS = idempotent (aman dijalankan 2x).
-- ATURAN KERAS: TIDAK ada tanda titik-koma di dalam komentar SQL ini
-- (migration runner memakai pemecah statement — komentar tetap ditulis bersih).
-- ════════════════════════════════════════════════════════════════════════

-- settings: key-value (nama blog, tagline, logo, accent color, flags, lisensi cache)
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` LONGTEXT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- users: role admin (semua + settings + kredit) atau writer (artikel sendiri + AI)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','writer') NOT NULL DEFAULT 'writer',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- categories: satu kategori utama per artikel, parent opsional (hierarki 1 level)
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(180) NOT NULL,
  `description` TEXT NULL,
  `parent_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tags: taksonomi ringan many-to-many via article_tags
CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- articles: entitas inti produksi konten SEO
CREATE TABLE IF NOT EXISTS `articles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(220) NOT NULL,
  `content` LONGTEXT NULL,
  `draft_content` LONGTEXT NULL,
  `content_snapshot` LONGTEXT NULL,
  `excerpt` VARCHAR(500) NULL,
  `cover_image` VARCHAR(255) NULL,
  `cover_is_fallback` TINYINT(1) NOT NULL DEFAULT 0,
  `category_id` INT UNSIGNED NULL,
  `author_id` INT UNSIGNED NULL,
  `focus_keyword` VARCHAR(190) NULL,
  `related_keywords` TEXT NULL,
  `search_intent` ENUM('informational','transactional','navigational') NULL,
  `language` ENUM('id','en') NOT NULL DEFAULT 'id',
  `meta_title` VARCHAR(255) NULL,
  `meta_description` VARCHAR(320) NULL,
  `canonical_url` VARCHAR(255) NULL,
  `status` ENUM('draft','draft_ai','scheduled','published') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_articles_slug` (`slug`),
  KEY `idx_articles_status_pub` (`status`, `published_at`),
  KEY `idx_articles_category` (`category_id`),
  KEY `idx_articles_author` (`author_id`),
  CONSTRAINT `fk_articles_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_articles_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- article_tags: pivot many-to-many dengan PK komposit
CREATE TABLE IF NOT EXISTS `article_tags` (
  `article_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`article_id`, `tag_id`),
  KEY `idx_article_tags_tag` (`tag_id`, `article_id`),
  CONSTRAINT `fk_article_tags_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_article_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- seo_ai_runs: jejak tiap panggilan AI (riset/outline/draft/meta/faq/analysis)
CREATE TABLE IF NOT EXISTS `seo_ai_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NULL,
  `task_type` VARCHAR(40) NOT NULL,
  `status` ENUM('pending','success','error') NOT NULL DEFAULT 'pending',
  `credits_charged` INT UNSIGNED NOT NULL DEFAULT 0,
  `request_payload` MEDIUMTEXT NULL,
  `result` MEDIUMTEXT NULL,
  `error_code` VARCHAR(40) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seo_runs_article` (`article_id`),
  KEY `idx_seo_runs_task` (`task_type`, `created_at`),
  CONSTRAINT `fk_seo_runs_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- seo_ai_analysis: hasil analisis SEO berbayar (skor 0-100 + rekomendasi)
-- prompt_version diisi NULL di sisi client (traceability prompt ada di ls_ai_logs gateway)
-- ruleset_version = versi ruleset lokal saat analisis dijalankan
CREATE TABLE IF NOT EXISTS `seo_ai_analysis` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NOT NULL,
  `score` TINYINT UNSIGNED NULL,
  `issues` MEDIUMTEXT NULL,
  `suggestions` MEDIUMTEXT NULL,
  `keyword_coverage` MEDIUMTEXT NULL,
  `prompt_version` SMALLINT UNSIGNED NULL,
  `ruleset_version` SMALLINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seo_analysis_article` (`article_id`, `created_at`),
  CONSTRAINT `fk_seo_analysis_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- seo_ai_keywords: keyword turunan/cluster hasil riset AI
CREATE TABLE IF NOT EXISTS `seo_ai_keywords` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NOT NULL,
  `keyword` VARCHAR(190) NOT NULL,
  `keyword_type` ENUM('related','cluster','lsi') NOT NULL DEFAULT 'related',
  `intent` VARCHAR(20) NULL,
  `is_selected` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seo_keywords_article` (`article_id`),
  CONSTRAINT `fk_seo_keywords_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- seo_ai_faq: pasangan Q-and-A hasil AI, di-approve per item sebelum masuk artikel
CREATE TABLE IF NOT EXISTS `seo_ai_faq` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NOT NULL,
  `question` VARCHAR(255) NOT NULL,
  `answer` TEXT NOT NULL,
  `approved` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seo_faq_article` (`article_id`),
  CONSTRAINT `fk_seo_faq_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- redirects: 301/302 otomatis saat slug artikel published berubah (cek sebelum 404)
CREATE TABLE IF NOT EXISTS `redirects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `old_slug` VARCHAR(220) NOT NULL,
  `new_slug` VARCHAR(220) NOT NULL,
  `type` SMALLINT NOT NULL DEFAULT 301,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_redirects_old` (`old_slug`),
  KEY `idx_redirects_new` (`new_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- article_performance: hook feedback loop (v1 manual, GSC fase 2)
CREATE TABLE IF NOT EXISTS `article_performance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
  `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
  `position` DECIMAL(5,1) NULL,
  `source` ENUM('manual','gsc') NOT NULL DEFAULT 'manual',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perf_article_date_source` (`article_id`, `date`, `source`),
  CONSTRAINT `fk_perf_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
