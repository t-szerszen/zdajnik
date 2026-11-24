<?php

namespace App\Models;

use App\Core\Database;

/**
 * Model Postępu Użytkownika (UserProgress).
 *
 * Klasa odpowiada za precyzyjne śledzenie statystyk nauki dla każdego pytania. 
 * Rejestruje liczbę prób, poprawnych i błędnych odpowiedzi oraz pytania pominięte.
 * Wykorzystuje zaawansowane zapytania SQL (`ON DUPLICATE KEY UPDATE`), aby:
 * - Tworzyć nowe rekordy postępu przy pierwszym zetknięciu z pytaniem.
 * - Inkrementować liczniki przy każdej kolejnej próbie (Upsert).
 *
 * Dane te są kluczowe dla algorytmów doboru pytań (np. Spaced Repetition) 
 * oraz wizualizacji progresu na Dashboardzie.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class UserProgress
{
  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  /**
   * Inicjalizuje model postępu.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Masowa aktualizacja postępu po zakończeniu całego zestawu pytań (testu).
   *
   * Metoda przetwarza tablicę szczegółów odpowiedzi i wykonuje serię zapytań UPSERT.
   * Obsługuje dwa stany:
   * 1. Pytania pominięte (skipped): Inkrementuje `attempts_count` oraz `skipped_count`.
   * 2. Pytania udzielone: Aktualizuje statystyki poprawności oraz flagę `last_result`.
   *
   * @param int $userId ID użytkownika.
   * @param array $answersDetails Szczegółowa tablica wyników (question_version_id, answer_id, is_correct).
   * @return void
   */
  public function updateBatchProgress(int $userId, array $answersDetails): void
  {
    if (empty($answersDetails)) {
      return;
    }

    foreach ($answersDetails as $detail) {
      $qvId = (int) $detail['question_version_id'];
      $answerId = $detail['answer_id'] ?? null;

      // KROK A: Obsługa pytań pominiętych (brak wyboru odpowiedzi)
      if ($answerId === null) {
        $sql = "INSERT INTO user_progress (user_id, question_id, attempts_count, correct_count, incorrect_count, skipped_count, last_result)
                  SELECT ?, qv.question_id, 1, 0, 0, 1, 0
                  FROM question_versions qv
                  WHERE qv.id = ?
                  ON DUPLICATE KEY UPDATE 
                      attempts_count = attempts_count + 1,
                      skipped_count = skipped_count + 1,
                      last_result = 0";

        $this->db->execute($sql, [$userId, $qvId]);
      }
      // KROK B: Obsługa pytań udzielonych (prawidłowo lub błędnie)
      else {
        $sql = "INSERT INTO user_progress (user_id, question_id, attempts_count, correct_count, incorrect_count, skipped_count, last_result)
                  SELECT ?, qv.question_id, 1, a.is_correct, (1 - a.is_correct), 0, a.is_correct
                  FROM question_versions qv
                  JOIN answers a ON a.id = ? AND a.question_version_id = qv.id
                  WHERE qv.id = ?
                  ON DUPLICATE KEY UPDATE 
                      attempts_count = attempts_count + 1,
                      correct_count = correct_count + VALUES(correct_count),
                      incorrect_count = incorrect_count + VALUES(incorrect_count),
                      last_result = VALUES(last_result)";

        $this->db->execute($sql, [$userId, (int) $answerId, $qvId]);
      }
    }
  }

  /**
   * Zapisuje postęp dla pojedynczego pytania (używane w trybie "jedno pytanie").
   *
   * @param int $userId
   * @param int $questionId ID bazowego pytania (nie wersji).
   * @param int $isCorrect 1 dla odpowiedzi poprawnej, 0 dla błędnej.
   * @return bool
   */
  public function saveProgress(int $userId, int $questionId, int $isCorrect): bool
  {
    $sql = "INSERT INTO user_progress 
              (user_id, question_id, attempts_count, correct_count, incorrect_count, skipped_count, last_result)
            VALUES 
              (?, ?, 1, ?, ?, 0, ?)
            ON DUPLICATE KEY UPDATE
              attempts_count = attempts_count + 1,
              correct_count = correct_count + VALUES(correct_count),
              incorrect_count = incorrect_count + VALUES(incorrect_count),
              last_result = VALUES(last_result)";

    return $this->db->execute($sql, [$userId, $questionId, $isCorrect, 1 - $isCorrect, $isCorrect]);
  }

  /**
   * Sprawdza, czy użytkownik udzielił już odpowiedzi na dane pytanie.
   *
   * @param int $userId
   * @param int $questionId
   * @return bool
   */
  public function hasAnsweredQuestion(int $userId, int $questionId): bool
  {
    $sql = "SELECT 1 FROM user_progress
            WHERE user_id = ? AND question_id = ? AND attempts_count > 0
            LIMIT 1";

    return (bool) $this->db->fetch($sql, [$userId, $questionId]);
  }

  /**
   * Trwale usuwa całą historię postępu użytkownika dla wybranej kwalifikacji.
   * 
   * @param int $userId
   * @param int $examTypeId ID kwalifikacji (np. INF.04).
   * @return bool
   */
  public function deleteProgress(int $userId, int $examTypeId): bool
  {
    $sql = "DELETE up FROM user_progress up 
            JOIN questions q ON up.question_id = q.id 
            WHERE up.user_id = ? AND q.exam_type_id = ?";
    return $this->db->execute($sql, [$userId, $examTypeId]);
  }

  /**
   * Oblicza liczbę unikalnych pytań, na które użytkownik udzielił odpowiedzi.
   * Pytania tylko przeglądane (pominięte) nie są wliczane do unikalnego progresu.
   * 
   * @param int $userId
   * @param int $examTypeId
   * @return int Liczba "odkrytych" pytań.
   */
  public function countAnsweredQuestionsForExam(int $userId, int $examTypeId): int
  {
    $sql = "SELECT COUNT(up.id) as answered_count
            FROM user_progress up
            JOIN questions q ON up.question_id = q.id
            WHERE up.user_id = ? AND q.exam_type_id = ? AND (up.correct_count + up.incorrect_count) > 0";

    $result = $this->db->fetch($sql, [$userId, $examTypeId]);
    return (int) ($result['answered_count'] ?? 0);
  }
}
