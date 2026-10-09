-- ════════════════════════════════════════════════════════════════════════
-- Migrasi 1.0.2 — kolom outline_json (SATU sumber kebenaran outline editable).
-- JSON {h1, sections:[{h2,h3s[]}], faq_selected:[...]}. ATURAN KERAS: tidak ada
-- tanda titik-koma di dalam komentar SQL ini.
-- ════════════════════════════════════════════════════════════════════════

ALTER TABLE `articles`
  ADD COLUMN `outline_json` LONGTEXT NULL AFTER `related_keywords`;
