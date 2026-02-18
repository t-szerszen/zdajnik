-- migrate:up
ALTER TABLE user_tokens
ADD COLUMN auth_code VARCHAR(50) NULL AFTER token_data;

-- migrate:down
ALTER TABLE user_tokens
DROP COLUMN auth_code;