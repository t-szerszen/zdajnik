-- migrate:up

-- ---------------------------------------------------------
-- BIBLIOTEKA ARKUSZY v2
-- 1. Tematy arkuszy przypięte do kwalifikacji (exam_types) —
--    każda kwalifikacja ma własną bibliotekę arkuszy.
-- 2. Arkusz zyskuje miniaturkę oraz archiwum zasobów
--    potrzebnych do wykonania egzaminu.
-- ---------------------------------------------------------

ALTER TABLE sheet_topics
  ADD COLUMN exam_type_id INT UNSIGNED DEFAULT NULL AFTER id;

-- Backfill dla ewentualnych istniejących tematów: przypięcie do pierwszej kwalifikacji
UPDATE sheet_topics
SET exam_type_id = (SELECT id FROM exam_types ORDER BY id ASC LIMIT 1)
WHERE exam_type_id IS NULL;

ALTER TABLE sheet_topics
  MODIFY COLUMN exam_type_id INT UNSIGNED NOT NULL,
  DROP KEY unique_sheet_topic_name,
  ADD UNIQUE KEY unique_sheet_topic (exam_type_id, name),
  ADD CONSTRAINT fk_sheet_topics_exam_type FOREIGN KEY (exam_type_id) REFERENCES exam_types(id) ON DELETE RESTRICT;

ALTER TABLE sheets
  ADD COLUMN thumbnail_path VARCHAR(255) DEFAULT NULL AFTER zip_path,   -- Miniaturka arkusza (obrazek)
  ADD COLUMN resources_path VARCHAR(255) DEFAULT NULL AFTER thumbnail_path; -- Archiwum ZIP z zasobami do egzaminu

-- migrate:down
ALTER TABLE sheets
  DROP COLUMN resources_path,
  DROP COLUMN thumbnail_path;

ALTER TABLE sheet_topics
  DROP FOREIGN KEY fk_sheet_topics_exam_type,
  DROP KEY unique_sheet_topic,
  ADD UNIQUE KEY unique_sheet_topic_name (name),
  DROP COLUMN exam_type_id;
