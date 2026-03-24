-- migrate:up
ALTER TABLE user_tokens MODIFY COLUMN type ENUM('email_verify', 'password_reset', 'email_change', 'account_deletion', 'progress_anonymization') NOT NULL;

-- migrate:down
DELETE FROM user_tokens WHERE type = 'progress_anonymization';
ALTER TABLE user_tokens MODIFY COLUMN type ENUM('email_verify', 'password_reset', 'email_change', 'account_deletion') NOT NULL;
