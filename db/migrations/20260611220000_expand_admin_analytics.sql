-- migrate:up
-- Zwykłe nullable columns są celowe: działają stabilnie na MySQL 8 i MariaDB,
-- unikają różnic kolacji generated columns i pozwalają trackEvent zapisywać
-- indeksowane wymiary bez ponownego parsowania JSON przy każdym raporcie.
ALTER TABLE analytics_events
  ADD COLUMN session_hash char(64) DEFAULT NULL AFTER user_agent,
  ADD COLUMN exam_type_id int unsigned DEFAULT NULL,
  ADD COLUMN question_id int unsigned DEFAULT NULL,
  ADD COLUMN test_id int unsigned DEFAULT NULL,
  ADD COLUMN source varchar(100) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  ADD COLUMN test_type varchar(20) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  ADD COLUMN auth_provider varchar(20) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  ADD COLUMN page_path varchar(255) COLLATE utf8mb4_polish_ci DEFAULT NULL,
  ADD COLUMN is_correct tinyint DEFAULT NULL,
  ADD KEY idx_analytics_type_date (event_type, event_date),
  ADD KEY idx_analytics_user_date (user_id, event_date),
  ADD KEY idx_analytics_exam_date (exam_type_id, event_date),
  ADD KEY idx_analytics_test_date (test_id, event_date),
  ADD KEY idx_analytics_test_type_date (test_type, event_date),
  ADD KEY idx_analytics_provider_date (auth_provider, event_date),
  ADD KEY idx_analytics_path_date (page_path, event_date),
  ADD KEY idx_analytics_source_date (source, event_date),
  ADD KEY idx_analytics_question_date (question_id, event_date),
  ADD KEY idx_analytics_correct_date (is_correct, event_date),
  ADD KEY idx_analytics_session_date (session_hash, event_date);

-- Jednorazowy backfill zachowuje użyteczność historycznych metadata.
UPDATE analytics_events
SET
  exam_type_id = CAST(NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.exam_id')), ''), 'null') AS UNSIGNED),
  question_id = CAST(NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.question_id')), ''), 'null') AS UNSIGNED),
  test_id = CAST(NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.test_id')), ''), 'null') AS UNSIGNED),
  source = LEFT(NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.source')), ''), 'null'), 100),
  test_type = NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.test_type')), ''), 'null'),
  auth_provider = NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.auth_provider')), ''), 'null'),
  page_path = LEFT(NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.path')), ''), 'null'), 255),
  is_correct = CASE LOWER(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.is_correct')))
    WHEN 'true' THEN 1
    WHEN '1' THEN 1
    WHEN 'false' THEN 0
    WHEN '0' THEN 0
    ELSE NULL
  END;

ALTER TABLE attempts
  ADD KEY idx_attempts_completed_filters (completed_at, exam_type_id, test_type, user_id),
  ADD KEY idx_attempts_started_filters (started_at, exam_type_id, test_type, user_id);

ALTER TABLE saved_questions
  ADD KEY idx_saved_questions_created (created_at, question_id);

ALTER TABLE users
  ADD KEY idx_users_analytics_filters (created_at, auth_provider, role, is_verified, is_active);

-- migrate:down
ALTER TABLE users
  DROP KEY idx_users_analytics_filters;

ALTER TABLE saved_questions
  DROP KEY idx_saved_questions_created;

ALTER TABLE attempts
  DROP KEY idx_attempts_completed_filters,
  DROP KEY idx_attempts_started_filters;

ALTER TABLE analytics_events
  DROP KEY idx_analytics_type_date,
  DROP KEY idx_analytics_user_date,
  DROP KEY idx_analytics_exam_date,
  DROP KEY idx_analytics_test_date,
  DROP KEY idx_analytics_test_type_date,
  DROP KEY idx_analytics_provider_date,
  DROP KEY idx_analytics_path_date,
  DROP KEY idx_analytics_source_date,
  DROP KEY idx_analytics_question_date,
  DROP KEY idx_analytics_correct_date,
  DROP KEY idx_analytics_session_date,
  DROP COLUMN is_correct,
  DROP COLUMN page_path,
  DROP COLUMN auth_provider,
  DROP COLUMN test_type,
  DROP COLUMN source,
  DROP COLUMN test_id,
  DROP COLUMN question_id,
  DROP COLUMN exam_type_id,
  DROP COLUMN session_hash;
