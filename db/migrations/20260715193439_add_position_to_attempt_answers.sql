-- migrate:up
ALTER TABLE attempt_answers
  ADD COLUMN position SMALLINT UNSIGNED DEFAULT NULL AFTER answered_at;

-- migrate:down
ALTER TABLE attempt_answers
  DROP COLUMN position;
