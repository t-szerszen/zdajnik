-- migrate:up
-- Zgłoszenia błędów w pytaniach wysyłane przez użytkowników (również gości).
-- question_version_id zapamiętuje wersję treści, którą widział zgłaszający -
-- po poprawce pytanie dostaje nową wersję, a zgłoszenie dalej wskazuje starą.
CREATE TABLE question_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id INT UNSIGNED NOT NULL,
  question_version_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  category ENUM('wrong_answer', 'content_error', 'explanation', 'display', 'other') NOT NULL,
  message VARCHAR(1000) NULL,
  context VARCHAR(32) NULL,
  status ENUM('new', 'resolved', 'rejected') NOT NULL DEFAULT 'new',
  resolved_by INT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_question_reports_status_created (status, created_at),
  KEY idx_question_reports_question (question_id),
  CONSTRAINT fk_question_reports_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE,
  CONSTRAINT fk_question_reports_version FOREIGN KEY (question_version_id) REFERENCES question_versions (id) ON DELETE SET NULL,
  CONSTRAINT fk_question_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_question_reports_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- migrate:down
DROP TABLE IF EXISTS question_reports;
