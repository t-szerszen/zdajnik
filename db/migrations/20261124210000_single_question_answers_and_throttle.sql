-- migrate:up

-- Historia odpowiedzi z trybu "jedno pytanie" (zalogowani użytkownicy).
-- Testy mają swoją historię w attempts/attempt_answers - ta tabela uzupełnia ją
-- o odpowiedzi udzielane pojedynczo, których wcześniej nie zapisywaliśmy
-- (user_progress trzyma tylko liczniki, zdarzenia analityki - bez wybranej odpowiedzi).
--
-- user_id NULL = odpowiedź zanonimizowana (usunięte konto / reset postępu) -
-- zostaje do statystyk treści, ale nie jest już przypisana do osoby (jak attempts).
CREATE TABLE `single_question_answers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `question_id` int unsigned NOT NULL,
  `question_version_id` int unsigned DEFAULT NULL,
  `answer_id` int unsigned DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `answered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sqa_user_answered` (`user_id`, `answered_at`),
  KEY `idx_sqa_question` (`question_id`),
  KEY `idx_sqa_answered` (`answered_at`),
  KEY `idx_sqa_version` (`question_version_id`),
  KEY `idx_sqa_answer` (`answer_id`),
  CONSTRAINT `fk_sqa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sqa_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sqa_version` FOREIGN KEY (`question_version_id`) REFERENCES `question_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sqa_answer` FOREIGN KEY (`answer_id`) REFERENCES `answers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Import dotychczasowych odpowiedzi zalogowanych ze zdarzeń analityki.
-- Zdarzenia nie zawierają wybranej odpowiedzi, więc answer_id zostaje NULL.
-- Wersja pytania = ta, która obowiązywała w chwili odpowiedzi (najnowsza utworzona
-- nie później niż zdarzenie). Konta zanonimizowane trafiają z user_id = NULL.
INSERT INTO `single_question_answers` (`user_id`, `question_id`, `question_version_id`, `answer_id`, `is_correct`, `answered_at`)
SELECT
  CASE WHEN u.password_hash = 'ANONYMIZED' THEN NULL ELSE e.user_id END,
  e.question_id,
  (
    SELECT qv.id
    FROM question_versions qv
    WHERE qv.question_id = e.question_id AND qv.created_at <= e.event_date
    ORDER BY qv.version_num DESC
    LIMIT 1
  ),
  NULL,
  e.is_correct,
  e.event_date
FROM analytics_events e
JOIN users u ON u.id = e.user_id
JOIN questions q ON q.id = e.question_id
WHERE e.event_type = 'single_question_answered'
  AND e.is_correct IS NOT NULL
ORDER BY e.event_date, e.id;

-- Liczniki prób dla formularzy logowania, rejestracji, resetu hasła i kontaktu
-- (ThrottleService). Klucz = SHA-256 z zakresu i identyfikatora (IP / e-mail),
-- więc w tabeli nie ma jawnych adresów. Wiersze starsze niż doba są sprzątane.
CREATE TABLE `request_throttle` (
  `throttle_key` char(64) NOT NULL,
  `hits` int unsigned NOT NULL DEFAULT '0',
  `window_start` datetime NOT NULL,
  PRIMARY KEY (`throttle_key`),
  KEY `idx_request_throttle_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- migrate:down
DROP TABLE IF EXISTS `request_throttle`;
DROP TABLE IF EXISTS `single_question_answers`;
