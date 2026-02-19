-- migrate:up
ALTER TABLE user_tokens MODIFY COLUMN type ENUM('email_verify', 'password_reset', 'email_change', 'account_deletion') NOT NULL;

-- migrate:down
ALTER TABLE user_tokens MODIFY COLUMN type ENUM('email_verify', 'password_reset', 'email_change') NOT NULL;