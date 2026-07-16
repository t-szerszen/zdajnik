-- migrate:up
-- Wymiary używane w agregacjach są normalnymi kolumnami. Zapytanie
-- wyszukiwania trafia tu dopiero po normalizacji i odrzuceniu wzorców PII.
ALTER TABLE analytics_events
  ADD COLUMN topic_id int unsigned DEFAULT NULL AFTER question_id,
  ADD COLUMN search_term varchar(80) COLLATE utf8mb4_polish_ci DEFAULT NULL AFTER page_path,
  ADD KEY idx_analytics_type_topic_date (event_type, topic_id, event_date),
  ADD KEY idx_analytics_type_search_date (event_type, search_term, event_date);

-- migrate:down
ALTER TABLE analytics_events
  DROP KEY idx_analytics_type_topic_date,
  DROP KEY idx_analytics_type_search_date,
  DROP COLUMN search_term,
  DROP COLUMN topic_id;
