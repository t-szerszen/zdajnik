-- migrate:up
ALTER TABLE users MODIFY COLUMN `role` ENUM('user', 'redaktor', 'admin') COLLATE utf8mb4_polish_ci DEFAULT 'user';

-- migrate:down
ALTER TABLE users MODIFY COLUMN `role` ENUM('user', 'admin') COLLATE utf8mb4_polish_ci DEFAULT 'user';
