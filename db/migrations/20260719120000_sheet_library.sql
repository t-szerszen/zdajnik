-- migrate:up

-- ---------------------------------------------------------
-- BIBLIOTEKA ARKUSZY
-- Tematy grupujące arkusze (niezależne od tabeli `topics`,
-- która opisuje tematy pytań egzaminacyjnych per egzamin).
-- ---------------------------------------------------------
CREATE TABLE sheet_topics (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  description TEXT,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,   -- Kolejność wyświetlania sekcji
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_sheet_topic_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pojedynczy arkusz (plik PDF + opcjonalne przykładowe rozwiązanie ZIP).
CREATE TABLE sheets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sheet_topic_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  difficulty TINYINT UNSIGNED NOT NULL,            -- 1-10, oficjalna trudność ustalana przez admina
  pdf_path VARCHAR(255) NOT NULL,                  -- Nazwa pliku w public/files/sheets/
  zip_path VARCHAR(255) DEFAULT NULL,              -- NULL = brak przykładowego rozwiązania
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sheet_topic (sheet_topic_id),
  FOREIGN KEY (sheet_topic_id) REFERENCES sheet_topics(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prywatna notatka użytkownika do arkusza (jedna na parę arkusz+użytkownik).
-- `subjective_difficulty` jest oceną użytkownika, niezależną od `sheets.difficulty`.
CREATE TABLE sheet_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sheet_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  subjective_difficulty TINYINT UNSIGNED NOT NULL, -- 1-10, subiektywna ocena użytkownika
  problem_description TEXT,                        -- Co sprawiło problem
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_sheet_user (sheet_id, user_id),
  KEY idx_sheet_note_user (user_id),
  FOREIGN KEY (sheet_id) REFERENCES sheets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- migrate:down
DROP TABLE IF EXISTS sheet_notes;
DROP TABLE IF EXISTS sheets;
DROP TABLE IF EXISTS sheet_topics;
