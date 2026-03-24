-- migrate:up
ALTER TABLE attempts DROP FOREIGN KEY attempts_ibfk_1;
ALTER TABLE attempts MODIFY user_id INT UNSIGNED NULL;
ALTER TABLE attempts ADD CONSTRAINT attempts_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- migrate:down
DELETE FROM attempts WHERE user_id IS NULL;
ALTER TABLE attempts DROP FOREIGN KEY attempts_ibfk_1;
ALTER TABLE attempts MODIFY user_id INT UNSIGNED NOT NULL;
ALTER TABLE attempts ADD CONSTRAINT attempts_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;
