-- migrate:up
ALTER TABLE questions
  ADD COLUMN source varchar(255) DEFAULT NULL AFTER topic_id,
  ADD COLUMN difficulty enum('easy','medium','hard') NOT NULL DEFAULT 'medium' AFTER source,
  ADD KEY idx_difficulty (difficulty);

-- migrate:down
ALTER TABLE questions
  DROP KEY idx_difficulty,
  DROP COLUMN difficulty,
  DROP COLUMN source;
