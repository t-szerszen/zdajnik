-- migrate:up
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE user_progress;
TRUNCATE TABLE attempt_answers;
TRUNCATE TABLE attempt_topics;
TRUNCATE TABLE attempts;
TRUNCATE TABLE answers;
TRUNCATE TABLE question_versions;
TRUNCATE TABLE questions;
TRUNCATE TABLE topics;
TRUNCATE TABLE exam_types;
SET FOREIGN_KEY_CHECKS = 1;

START TRANSACTION;

-- EXAM: INF.02
INSERT INTO exam_types (code, name, description) VALUES ('INF.02', 'Administracja i eksploatacja systemów komputerowych', 'Kwalifikacja INF.02 obejmująca zagadnienia z zakresu: Systemy Operacyjne, Urządzenia Peryferyjne, Lokalne Sieci Komputerowe, Diagnostyka i Naprawa, Bezpieczeństwo Systemów');
SET @exam_id = LAST_INSERT_ID();

-- Topic: Systemy Operacyjne
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Systemy Operacyjne', 'Pytania z zakresu Systemy Operacyjne');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Systemy Operacyjne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Windows Server?', 'Podstawowe wyjaśnienie dla zagadnienia: Windows Server. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Windows Server.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Windows Server.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Windows Server.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Windows Server.', 0, 4);

-- Question 2 for Systemy Operacyjne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Linux Ubuntu?', 'Podstawowe wyjaśnienie dla zagadnienia: Linux Ubuntu. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Linux Ubuntu.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Linux Ubuntu.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Linux Ubuntu.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Linux Ubuntu.', 0, 4);

-- Question 3 for Systemy Operacyjne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące PowerShell jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: PowerShell. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca PowerShell.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca PowerShell.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla PowerShell.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca PowerShell.', 0, 4);

-- Question 4 for Systemy Operacyjne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Wiersz poleceń?', 'Podstawowe wyjaśnienie dla zagadnienia: Wiersz poleceń. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Wiersz poleceń.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Wiersz poleceń.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Wiersz poleceń.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Wiersz poleceń.', 1, 4);

-- Question 5 for Systemy Operacyjne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Rejestr systemu?', 'Podstawowe wyjaśnienie dla zagadnienia: Rejestr systemu. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Rejestr systemu.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Rejestr systemu.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Rejestr systemu.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Rejestr systemu.', 0, 4);

-- Topic: Urządzenia Peryferyjne
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Urządzenia Peryferyjne', 'Pytania z zakresu Urządzenia Peryferyjne');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Urządzenia Peryferyjne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Drukarka laserowa?', 'Podstawowe wyjaśnienie dla zagadnienia: Drukarka laserowa. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Drukarka laserowa.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Drukarka laserowa.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Drukarka laserowa.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Drukarka laserowa.', 0, 4);

-- Question 2 for Urządzenia Peryferyjne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Skaner 3D?', 'Podstawowe wyjaśnienie dla zagadnienia: Skaner 3D. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Skaner 3D.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Skaner 3D.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Skaner 3D.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Skaner 3D.', 0, 4);

-- Question 3 for Urządzenia Peryferyjne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Plotter jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Plotter. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Plotter.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Plotter.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Plotter.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Plotter.', 0, 4);

-- Question 4 for Urządzenia Peryferyjne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Interfejs USB?', 'Podstawowe wyjaśnienie dla zagadnienia: Interfejs USB. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Interfejs USB.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Interfejs USB.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Interfejs USB.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Interfejs USB.', 1, 4);

-- Question 5 for Urządzenia Peryferyjne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Karta graficzna?', 'Podstawowe wyjaśnienie dla zagadnienia: Karta graficzna. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Karta graficzna.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Karta graficzna.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Karta graficzna.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Karta graficzna.', 0, 4);

-- Topic: Lokalne Sieci Komputerowe
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Lokalne Sieci Komputerowe', 'Pytania z zakresu Lokalne Sieci Komputerowe');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Lokalne Sieci Komputerowe
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Adres IP?', 'Podstawowe wyjaśnienie dla zagadnienia: Adres IP. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Adres IP.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Adres IP.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Adres IP.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Adres IP.', 0, 4);

-- Question 2 for Lokalne Sieci Komputerowe
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Maska podsieci?', 'Podstawowe wyjaśnienie dla zagadnienia: Maska podsieci. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Maska podsieci.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Maska podsieci.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Maska podsieci.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Maska podsieci.', 0, 4);

-- Question 3 for Lokalne Sieci Komputerowe
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Router jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Router. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Router.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Router.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Router.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Router.', 0, 4);

-- Question 4 for Lokalne Sieci Komputerowe
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Switch?', 'Podstawowe wyjaśnienie dla zagadnienia: Switch. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Switch.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Switch.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Switch.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Switch.', 1, 4);

-- Question 5 for Lokalne Sieci Komputerowe
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Protokół TCP/UDP?', 'Podstawowe wyjaśnienie dla zagadnienia: Protokół TCP/UDP. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Protokół TCP/UDP.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Protokół TCP/UDP.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Protokół TCP/UDP.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Protokół TCP/UDP.', 0, 4);

-- Topic: Diagnostyka i Naprawa
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Diagnostyka i Naprawa', 'Pytania z zakresu Diagnostyka i Naprawa');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Diagnostyka i Naprawa
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Multimetr?', 'Podstawowe wyjaśnienie dla zagadnienia: Multimetr. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Multimetr.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Multimetr.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Multimetr.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Multimetr.', 0, 4);

-- Question 2 for Diagnostyka i Naprawa
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Karta diagnostyczna?', 'Podstawowe wyjaśnienie dla zagadnienia: Karta diagnostyczna. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Karta diagnostyczna.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Karta diagnostyczna.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Karta diagnostyczna.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Karta diagnostyczna.', 0, 4);

-- Question 3 for Diagnostyka i Naprawa
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Błąd POST jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Błąd POST. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Błąd POST.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Błąd POST.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Błąd POST.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Błąd POST.', 0, 4);

-- Question 4 for Diagnostyka i Naprawa
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Sektor rozruchowy?', 'Podstawowe wyjaśnienie dla zagadnienia: Sektor rozruchowy. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Sektor rozruchowy.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Sektor rozruchowy.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Sektor rozruchowy.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Sektor rozruchowy.', 1, 4);

-- Question 5 for Diagnostyka i Naprawa
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Defragmentacja?', 'Podstawowe wyjaśnienie dla zagadnienia: Defragmentacja. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Defragmentacja.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Defragmentacja.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Defragmentacja.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Defragmentacja.', 0, 4);

-- Topic: Bezpieczeństwo Systemów
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Bezpieczeństwo Systemów', 'Pytania z zakresu Bezpieczeństwo Systemów');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Bezpieczeństwo Systemów
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Firewall?', 'Podstawowe wyjaśnienie dla zagadnienia: Firewall. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Firewall.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Firewall.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Firewall.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Firewall.', 0, 4);

-- Question 2 for Bezpieczeństwo Systemów
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Szyfrowanie AES?', 'Podstawowe wyjaśnienie dla zagadnienia: Szyfrowanie AES. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Szyfrowanie AES.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Szyfrowanie AES.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Szyfrowanie AES.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Szyfrowanie AES.', 0, 4);

-- Question 3 for Bezpieczeństwo Systemów
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Kopia zapasowa jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Kopia zapasowa. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Kopia zapasowa.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Kopia zapasowa.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Kopia zapasowa.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Kopia zapasowa.', 0, 4);

-- Question 4 for Bezpieczeństwo Systemów
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Ransomware?', 'Podstawowe wyjaśnienie dla zagadnienia: Ransomware. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Ransomware.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Ransomware.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Ransomware.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Ransomware.', 1, 4);

-- Question 5 for Bezpieczeństwo Systemów
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy VPN?', 'Podstawowe wyjaśnienie dla zagadnienia: VPN. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla VPN.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca VPN.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca VPN.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca VPN.', 0, 4);

-- EXAM: INF.03
INSERT INTO exam_types (code, name, description) VALUES ('INF.03', 'Tworzenie i administrowanie stronami i aplikacjami internetowymi', 'Kwalifikacja INF.03 obejmująca zagadnienia z zakresu: HTML i CSS, JavaScript, PHP, Bazy Danych SQL, Grafika i Multimedia');
SET @exam_id = LAST_INSERT_ID();

-- Topic: HTML i CSS
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'HTML i CSS', 'Pytania z zakresu HTML i CSS');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for HTML i CSS
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Znacznik <div>?', 'Podstawowe wyjaśnienie dla zagadnienia: Znacznik <div>. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Znacznik <div>.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Znacznik <div>.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Znacznik <div>.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Znacznik <div>.', 0, 4);

-- Question 2 for HTML i CSS
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Model pudełkowy (Box Model)?', 'Podstawowe wyjaśnienie dla zagadnienia: Model pudełkowy (Box Model). Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Model pudełkowy (Box Model).', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Model pudełkowy (Box Model).', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Model pudełkowy (Box Model).', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Model pudełkowy (Box Model).', 0, 4);

-- Question 3 for HTML i CSS
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Selektor CSS jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Selektor CSS. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Selektor CSS.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Selektor CSS.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Selektor CSS.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Selektor CSS.', 0, 4);

-- Question 4 for HTML i CSS
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Flexbox?', 'Podstawowe wyjaśnienie dla zagadnienia: Flexbox. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Flexbox.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Flexbox.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Flexbox.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Flexbox.', 1, 4);

-- Question 5 for HTML i CSS
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Media Queries?', 'Podstawowe wyjaśnienie dla zagadnienia: Media Queries. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Media Queries.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Media Queries.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Media Queries.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Media Queries.', 0, 4);

-- Topic: JavaScript
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'JavaScript', 'Pytania z zakresu JavaScript');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for JavaScript
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Zmienna const?', 'Podstawowe wyjaśnienie dla zagadnienia: Zmienna const. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Zmienna const.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Zmienna const.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Zmienna const.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Zmienna const.', 0, 4);

-- Question 2 for JavaScript
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Funkcja strzałkowa?', 'Podstawowe wyjaśnienie dla zagadnienia: Funkcja strzałkowa. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Funkcja strzałkowa.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Funkcja strzałkowa.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Funkcja strzałkowa.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Funkcja strzałkowa.', 0, 4);

-- Question 3 for JavaScript
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące DOM Manipulation jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: DOM Manipulation. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca DOM Manipulation.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca DOM Manipulation.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla DOM Manipulation.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca DOM Manipulation.', 0, 4);

-- Question 4 for JavaScript
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Event Listener?', 'Podstawowe wyjaśnienie dla zagadnienia: Event Listener. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Event Listener.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Event Listener.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Event Listener.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Event Listener.', 1, 4);

-- Question 5 for JavaScript
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy JSON?', 'Podstawowe wyjaśnienie dla zagadnienia: JSON. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla JSON.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca JSON.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca JSON.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca JSON.', 0, 4);

-- Topic: PHP
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'PHP', 'Pytania z zakresu PHP');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for PHP
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Funkcja echo?', 'Podstawowe wyjaśnienie dla zagadnienia: Funkcja echo. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Funkcja echo.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Funkcja echo.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Funkcja echo.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Funkcja echo.', 0, 4);

-- Question 2 for PHP
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Zmienna superglobalna $_GET?', 'Podstawowe wyjaśnienie dla zagadnienia: Zmienna superglobalna $_GET. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Zmienna superglobalna $_GET.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Zmienna superglobalna $_GET.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Zmienna superglobalna $_GET.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Zmienna superglobalna $_GET.', 0, 4);

-- Question 3 for PHP
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Klasa i Obiekt jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Klasa i Obiekt. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Klasa i Obiekt.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Klasa i Obiekt.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Klasa i Obiekt.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Klasa i Obiekt.', 0, 4);

-- Question 4 for PHP
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Interfejs PDO?', 'Podstawowe wyjaśnienie dla zagadnienia: Interfejs PDO. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Interfejs PDO.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Interfejs PDO.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Interfejs PDO.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Interfejs PDO.', 1, 4);

-- Question 5 for PHP
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Composer?', 'Podstawowe wyjaśnienie dla zagadnienia: Composer. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Composer.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Composer.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Composer.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Composer.', 0, 4);

-- Topic: Bazy Danych SQL
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Bazy Danych SQL', 'Pytania z zakresu Bazy Danych SQL');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Bazy Danych SQL
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Klauzula SELECT?', 'Podstawowe wyjaśnienie dla zagadnienia: Klauzula SELECT. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Klauzula SELECT.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Klauzula SELECT.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Klauzula SELECT.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Klauzula SELECT.', 0, 4);

-- Question 2 for Bazy Danych SQL
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Relacja One-to-Many?', 'Podstawowe wyjaśnienie dla zagadnienia: Relacja One-to-Many. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Relacja One-to-Many.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Relacja One-to-Many.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Relacja One-to-Many.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Relacja One-to-Many.', 0, 4);

-- Question 3 for Bazy Danych SQL
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Klucz obcy (Foreign Key) jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Klucz obcy (Foreign Key). Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Klucz obcy (Foreign Key).', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Klucz obcy (Foreign Key).', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Klucz obcy (Foreign Key).', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Klucz obcy (Foreign Key).', 0, 4);

-- Question 4 for Bazy Danych SQL
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Funkcja JOIN?', 'Podstawowe wyjaśnienie dla zagadnienia: Funkcja JOIN. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Funkcja JOIN.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Funkcja JOIN.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Funkcja JOIN.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Funkcja JOIN.', 1, 4);

-- Question 5 for Bazy Danych SQL
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Normalizacja?', 'Podstawowe wyjaśnienie dla zagadnienia: Normalizacja. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Normalizacja.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Normalizacja.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Normalizacja.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Normalizacja.', 0, 4);

-- Topic: Grafika i Multimedia
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Grafika i Multimedia', 'Pytania z zakresu Grafika i Multimedia');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Grafika i Multimedia
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Format SVG?', 'Podstawowe wyjaśnienie dla zagadnienia: Format SVG. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Format SVG.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Format SVG.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Format SVG.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Format SVG.', 0, 4);

-- Question 2 for Grafika i Multimedia
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Kompresja stratna?', 'Podstawowe wyjaśnienie dla zagadnienia: Kompresja stratna. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Kompresja stratna.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Kompresja stratna.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Kompresja stratna.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Kompresja stratna.', 0, 4);

-- Question 3 for Grafika i Multimedia
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Kanał Alpha jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Kanał Alpha. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Kanał Alpha.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Kanał Alpha.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Kanał Alpha.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Kanał Alpha.', 0, 4);

-- Question 4 for Grafika i Multimedia
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Rasteryzacja?', 'Podstawowe wyjaśnienie dla zagadnienia: Rasteryzacja. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Rasteryzacja.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Rasteryzacja.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Rasteryzacja.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Rasteryzacja.', 1, 4);

-- Question 5 for Grafika i Multimedia
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Przestrzeń barw RGB?', 'Podstawowe wyjaśnienie dla zagadnienia: Przestrzeń barw RGB. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Przestrzeń barw RGB.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Przestrzeń barw RGB.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Przestrzeń barw RGB.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Przestrzeń barw RGB.', 0, 4);

-- EXAM: INF.04
INSERT INTO exam_types (code, name, description) VALUES ('INF.04', 'Projektowanie, programowanie i testowanie aplikacji', 'Kwalifikacja INF.04 obejmująca zagadnienia z zakresu: Inżynieria Oprogramowania, Programowanie Obiektowe, Aplikacje Mobilne, Testowanie i Dokumentacja, Algorytmy');
SET @exam_id = LAST_INSERT_ID();

-- Topic: Inżynieria Oprogramowania
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Inżynieria Oprogramowania', 'Pytania z zakresu Inżynieria Oprogramowania');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Inżynieria Oprogramowania
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Metodyka Agile?', 'Podstawowe wyjaśnienie dla zagadnienia: Metodyka Agile. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Metodyka Agile.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Metodyka Agile.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Metodyka Agile.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Metodyka Agile.', 0, 4);

-- Question 2 for Inżynieria Oprogramowania
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Wzorzec MVC?', 'Podstawowe wyjaśnienie dla zagadnienia: Wzorzec MVC. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Wzorzec MVC.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Wzorzec MVC.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Wzorzec MVC.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Wzorzec MVC.', 0, 4);

-- Question 3 for Inżynieria Oprogramowania
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Diagram UML jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Diagram UML. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Diagram UML.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Diagram UML.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Diagram UML.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Diagram UML.', 0, 4);

-- Question 4 for Inżynieria Oprogramowania
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Cykl życia oprogramowania?', 'Podstawowe wyjaśnienie dla zagadnienia: Cykl życia oprogramowania. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Cykl życia oprogramowania.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Cykl życia oprogramowania.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Cykl życia oprogramowania.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Cykl życia oprogramowania.', 1, 4);

-- Question 5 for Inżynieria Oprogramowania
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy System kontroli wersji GIT?', 'Podstawowe wyjaśnienie dla zagadnienia: System kontroli wersji GIT. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla System kontroli wersji GIT.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca System kontroli wersji GIT.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca System kontroli wersji GIT.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca System kontroli wersji GIT.', 0, 4);

-- Topic: Programowanie Obiektowe
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Programowanie Obiektowe', 'Pytania z zakresu Programowanie Obiektowe');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Programowanie Obiektowe
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Polimorfizm?', 'Podstawowe wyjaśnienie dla zagadnienia: Polimorfizm. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Polimorfizm.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Polimorfizm.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Polimorfizm.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Polimorfizm.', 0, 4);

-- Question 2 for Programowanie Obiektowe
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Dziedziczenie?', 'Podstawowe wyjaśnienie dla zagadnienia: Dziedziczenie. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Dziedziczenie.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Dziedziczenie.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Dziedziczenie.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Dziedziczenie.', 0, 4);

-- Question 3 for Programowanie Obiektowe
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Hermetyzacja jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Hermetyzacja. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Hermetyzacja.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Hermetyzacja.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Hermetyzacja.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Hermetyzacja.', 0, 4);

-- Question 4 for Programowanie Obiektowe
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Klasa abstrakcyjna?', 'Podstawowe wyjaśnienie dla zagadnienia: Klasa abstrakcyjna. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Klasa abstrakcyjna.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Klasa abstrakcyjna.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Klasa abstrakcyjna.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Klasa abstrakcyjna.', 1, 4);

-- Question 5 for Programowanie Obiektowe
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Interfejs?', 'Podstawowe wyjaśnienie dla zagadnienia: Interfejs. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Interfejs.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Interfejs.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Interfejs.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Interfejs.', 0, 4);

-- Topic: Aplikacje Mobilne
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Aplikacje Mobilne', 'Pytania z zakresu Aplikacje Mobilne');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Aplikacje Mobilne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: System Android?', 'Podstawowe wyjaśnienie dla zagadnienia: System Android. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla System Android.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca System Android.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca System Android.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca System Android.', 0, 4);

-- Question 2 for Aplikacje Mobilne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Widok (View)?', 'Podstawowe wyjaśnienie dla zagadnienia: Widok (View). Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Widok (View).', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Widok (View).', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Widok (View).', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Widok (View).', 0, 4);

-- Question 3 for Aplikacje Mobilne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Aktywność (Activity) jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Aktywność (Activity). Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Aktywność (Activity).', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Aktywność (Activity).', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Aktywność (Activity).', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Aktywność (Activity).', 0, 4);

-- Question 4 for Aplikacje Mobilne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Plik Manifest?', 'Podstawowe wyjaśnienie dla zagadnienia: Plik Manifest. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Plik Manifest.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Plik Manifest.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Plik Manifest.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Plik Manifest.', 1, 4);

-- Question 5 for Aplikacje Mobilne
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Responsywność?', 'Podstawowe wyjaśnienie dla zagadnienia: Responsywność. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Responsywność.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Responsywność.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Responsywność.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Responsywność.', 0, 4);

-- Topic: Testowanie i Dokumentacja
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Testowanie i Dokumentacja', 'Pytania z zakresu Testowanie i Dokumentacja');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Testowanie i Dokumentacja
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Test jednostkowy?', 'Podstawowe wyjaśnienie dla zagadnienia: Test jednostkowy. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Test jednostkowy.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Test jednostkowy.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Test jednostkowy.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Test jednostkowy.', 0, 4);

-- Question 2 for Testowanie i Dokumentacja
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Test integracyjny?', 'Podstawowe wyjaśnienie dla zagadnienia: Test integracyjny. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Test integracyjny.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Test integracyjny.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Test integracyjny.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Test integracyjny.', 0, 4);

-- Question 3 for Testowanie i Dokumentacja
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Przypadek testowy (Test Case) jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Przypadek testowy (Test Case). Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Przypadek testowy (Test Case).', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Przypadek testowy (Test Case).', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Przypadek testowy (Test Case).', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Przypadek testowy (Test Case).', 0, 4);

-- Question 4 for Testowanie i Dokumentacja
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Dokumentacja techniczna?', 'Podstawowe wyjaśnienie dla zagadnienia: Dokumentacja techniczna. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Dokumentacja techniczna.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Dokumentacja techniczna.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Dokumentacja techniczna.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Dokumentacja techniczna.', 1, 4);

-- Question 5 for Testowanie i Dokumentacja
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Raport błędów?', 'Podstawowe wyjaśnienie dla zagadnienia: Raport błędów. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Raport błędów.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Raport błędów.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Raport błędów.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Raport błędów.', 0, 4);

-- Topic: Algorytmy
INSERT INTO topics (exam_type_id, name, description) VALUES (@exam_id, 'Algorytmy', 'Pytania z zakresu Algorytmy');
SET @topic_id = LAST_INSERT_ID();

-- Question 1 for Algorytmy
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Co najlepiej opisuje pojęcie: Sortowanie bąbelkowe?', 'Podstawowe wyjaśnienie dla zagadnienia: Sortowanie bąbelkowe. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Sortowanie bąbelkowe.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Sortowanie bąbelkowe.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Sortowanie bąbelkowe.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Sortowanie bąbelkowe.', 0, 4);

-- Question 2 for Algorytmy
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'W jakim celu stosuje się Złożoność obliczeniowa?', 'Podstawowe wyjaśnienie dla zagadnienia: Złożoność obliczeniowa. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Złożoność obliczeniowa.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Złożoność obliczeniowa.', 1, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Złożoność obliczeniowa.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Złożoność obliczeniowa.', 0, 4);

-- Question 3 for Algorytmy
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Które stwierdzenie dotyczące Struktura stosu jest prawdziwe?', 'Podstawowe wyjaśnienie dla zagadnienia: Struktura stosu. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Struktura stosu.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Struktura stosu.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Struktura stosu.', 1, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Struktura stosu.', 0, 4);

-- Question 4 for Algorytmy
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Jaka jest główna cecha Drzewo binarne?', 'Podstawowe wyjaśnienie dla zagadnienia: Drzewo binarne. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 1 dotycząca Drzewo binarne.', 0, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Drzewo binarne.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Drzewo binarne.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Drzewo binarne.', 1, 4);

-- Question 5 for Algorytmy
INSERT INTO questions (exam_type_id, topic_id) VALUES (@exam_id, @topic_id);
SET @q_id = LAST_INSERT_ID();
INSERT INTO question_versions (question_id, version_num, question_text, explanation) VALUES (@q_id, 1, 'Gdzie najczęściej wykorzystujemy Rekurencja?', 'Podstawowe wyjaśnienie dla zagadnienia: Rekurencja. Dowiedz się więcej w materiałach szkoleniowych.');
SET @qv_id = LAST_INSERT_ID();
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, '[POPR] To jest poprawna definicja lub opis dla Rekurencja.', 1, 1);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 2 dotycząca Rekurencja.', 0, 2);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 3 dotycząca Rekurencja.', 0, 3);
INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order) VALUES (@qv_id, 'To jest błędna odpowiedź numer 4 dotycząca Rekurencja.', 0, 4);

COMMIT;

-- migrate:down
