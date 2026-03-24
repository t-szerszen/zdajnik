<?php

namespace App\Models;

use App\Core\Database;

class UserProgress
{
  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Masowa aktualizacja postępu po zakończeniu testu.
   * Obsługuje również pominięte pytania (answer_id = NULL).
   */
  public function updateBatchProgress(int $userId, array $answersDetails): void
  {
    if (empty($answersDetails))
      return;

    foreach ($answersDetails as $detail) {
      $qvId = (int) $detail['question_version_id'];
      $answerId = $detail['answer_id'] ?? null;

      // Jeśli pytanie zostało pominięte
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
      } else {
        // Pytanie zostało udzielone - normalna logika
        $sql = "INSERT INTO user_progress (user_id, question_id, attempts_count, correct_count, incorrect_count, skipped_count, last_result)
                  SELECT ?, qv.question_id, 1, a.is_correct, (1 - a.is_correct), 0, a.is_correct
                  FROM question_versions qv
                  JOIN answers a ON a.id = ?
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
   * Usuwa postęp użytkownika dla podanego typu egzaminu.
   */
  public function deleteProgress(int $userId, int $examTypeId): bool
  {
    $sql = "DELETE up FROM user_progress up 
            JOIN questions q ON up.question_id = q.id 
            WHERE up.user_id = ? AND q.exam_type_id = ?";
    return $this->db->execute($sql, [$userId, $examTypeId]);
  }
}
