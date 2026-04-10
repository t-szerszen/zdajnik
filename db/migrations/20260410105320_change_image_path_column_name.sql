-- migrate:up
ALTER TABLE question_versions RENAME COLUMN image_path TO image_name;

-- migrate:down
ALTER TABLE question_versions RENAME COLUMN image_name TO image_path;
