<?php

namespace App\Models;

use App\Core\Database;
use Exception;

/**
 * Model Podejścia do Egzaminu (Attempt).
 *
 * Klasa zarządza danymi historycznymi o rozwiązanych testach i egzaminach. 
 * Obsługuje operacje na trzech powiązanych tabelach:
 * - `attempts`: Główny rekord podejścia (kiedy, kto, jaki wynik).
 * - `attempt_topics`: Relacja Many-to-Many określająca zakres tematyczny testu.
 * - `attempt_answers`: Szczegółowy zapis każdej udzielonej odpowiedzi.
 *
 * Klasa zapewnia integralność danych poprzez wykorzystanie transakcji SQL.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class Attempt
{
  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  /**
   * Inicjalizuje model podejść egzaminacyjnych.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Tworzy kompletną migawkę zakończonego egzaminu w bazie danych.
   *
   * Metoda wykonuje operację atomową (Transakcja):
   * 1. Zapis nagłówka egzaminu (started_at obliczany na podstawie czasu trwania).
   * 2. Rejestracja tematów objętych testem (Multi-insert).
   * 3. Szczegółowy zapis odpowiedzi użytkownika (Multi-insert dla optymalizacji).
   * 4. Obsługa wyjątków i Rollback w przypadku jakiegokolwiek błędu zapisu.
   *
   * @param array $attemptData Podstawowe dane (user_id, exam_type_id, duration_seconds, etc.).
   * @param array $topicIds Tablica ID tematów/kategorii testu.
   * @param array $answersDetails Tablica asocjacyjna udzielonych odpowiedzi.
   * @return int|false ID nowej próby w przypadku sukcesu lub false w razie błędu.
   */
  public function createAttempt(array $attemptData, array $topicIds, array $answersDetails): int|false
  {
    // Start transakcji - zapewnienie spójności między 3 tabelami
    if (!$this->db->beginTransaction()) {
      return false;
    }

    try {
      // KROK 1: Zapis nagłówka (attempts)
      $sqlAttempt = "INSERT INTO attempts (user_id, exam_type_id, test_type, started_at, completed_at, correct_count, total_questions) 
                       VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? SECOND), NOW(), ?, ?)";

      if (
        !$this->db->execute($sqlAttempt, [
          $attemptData['user_id'],
          $attemptData['exam_type_id'],
          $attemptData['test_type'],
          $attemptData['duration_seconds'],
          $attemptData['correct_count'],
          $attemptData['total_questions']
        ])
      ) {
        throw new \Exception('Błąd zapisu nagłówka próby.');
      }

      $attemptId = $this->db->lastInsertId();

      // KROK 2: Powiązanie z tematami (attempt_topics)
      if (!empty($topicIds)) {
        // Usuwamy duplikaty i rzutujemy na int dla bezpieczeństwa
        $topicIds = array_unique(array_map('intval', $topicIds));
        $placeholders = implode(',', array_fill(0, count($topicIds), "($attemptId, ?)"));
        $sqlTopics = "INSERT INTO attempt_topics (attempt_id, topic_id) VALUES $placeholders";

        if (!$this->db->execute($sqlTopics, $topicIds)) {
          throw new \Exception('Błąd zapisu tematów próby.');
        }
      }

      // KROK 3: Zapis szczegółowych odpowiedzi (attempt_answers)
      if (!empty($answersDetails)) {
        $values = [];
        $params = [];
        foreach ($answersDetails as $ans) {
          $values[] = "(?, ?, ?, ?)";

          // Obsługa pytań pominiętych (null answer_id)
          $isCorrect = isset($ans['is_correct']) ? (int) $ans['is_correct'] : 0;
          $answerId = $ans['answer_id'] ?? null;

          array_push($params, $attemptId, $ans['question_version_id'], $answerId, $isCorrect);
        }

        $sqlAnswers = "INSERT INTO attempt_answers (attempt_id, question_version_id, answer_id, is_correct) VALUES " . implode(',', $values);
        if (!$this->db->execute($sqlAnswers, $params)) {
          throw new \Exception('Błąd zapisu szczegółowych odpowiedzi.');
        }
      }

      // KROK 4: Zatwierdzenie wszystkich zmian
      $this->db->commit();
      return (int) $attemptId;
    } catch (\Exception $e) {
      // Cofnięcie zmian w razie błędu na dowolnym etapie
      $this->db->rollBack();
      error_log('[Attempt Error] ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Pobiera pełną historię podejść konkretnego użytkownika.
   *
   * @param int $userId ID użytkownika.
   * @return array Tablica prób posortowana od najnowszych.
   */
  public function getUserAttempts(int $userId): array
  {
    $sql = "SELECT a.*, et.code as exam_code, et.name as exam_name
                FROM attempts a
                JOIN exam_types et ON a.exam_type_id = et.id
                WHERE a.user_id = ?
                ORDER BY a.completed_at DESC";

    return $this->db->fetchAll($sql, [$userId]);
  }

  /**
   * Pobiera listę kwalifikacji, w których użytkownik podjął jakąkolwiek aktywność.
   * 
   * @param int $userId ID użytkownika.
   * @return array Unikalna lista kwalifikacji [id, code].
   */
  public function getStartedExamsByUser(int $userId): array
  {
    $sql = "SELECT DISTINCT e.id, e.code 
            FROM exam_types e
            WHERE EXISTS (SELECT 1 FROM attempts a WHERE a.exam_type_id = e.id AND a.user_id = ?)
               OR EXISTS (SELECT 1 FROM user_progress up 
                          JOIN questions q ON up.question_id = q.id 
                          WHERE q.exam_type_id = e.id AND up.user_id = ?)";
    return $this->db->fetchAll($sql, [$userId, $userId]);
  }

  /**
   * Anonimizuje próby użytkownika (ustawia user_id na NULL).
   * 
   * Pozwala na zachowanie danych statystycznych przy jednoczesnym usunięciu 
   * powiązania z konkretnym kontem użytkownika.
   *
   * @param int $userId ID użytkownika.
   * @param int $examTypeId ID kwalifikacji.
   * @return bool
   */
  public function anonymizeAttempts(int $userId, int $examTypeId): bool
  {
    $sql = "UPDATE attempts SET user_id = NULL WHERE user_id = ? AND exam_type_id = ?";
    return $this->db->execute($sql, [$userId, $examTypeId]);
  }

  /**
   * Trwale usuwa rekordy podejść użytkownika z bazy danych.
   *
   * @param int $userId ID użytkownika.
   * @param int $examTypeId ID kwalifikacji.
   * @return bool
   */
  public function deleteAttempts(int $userId, int $examTypeId): bool
  {
    $sql = "DELETE FROM attempts WHERE user_id = ? AND exam_type_id = ?";
    return $this->db->execute($sql, [$userId, $examTypeId]);
  }
}
