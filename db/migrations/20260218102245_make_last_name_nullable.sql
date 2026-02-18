-- migrate:up
ALTER TABLE users MODIFY last_name VARCHAR(100) NULL;

-- migrate:down
UPDATE users SET last_name = '' WHERE last_name IS NULL;
ALTER TABLE users MODIFY last_name VARCHAR(100) NOT NULL;