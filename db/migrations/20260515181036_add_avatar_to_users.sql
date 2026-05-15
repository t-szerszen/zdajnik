-- migrate:up
ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) DEFAULT NULL AFTER email;

-- migrate:down
ALTER TABLE users DROP COLUMN avatar_path;
