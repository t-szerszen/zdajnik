<?php

namespace App\Models;

use App\Core\Database;

/**
 * Model historii odpowiedzi z trybu "jedno pytanie".
 *
 * Testy mają pełną historię w attempts/attempt_answers, a tryb jednego pytania
 * aktualizował dotąd tylko liczniki w user_progress - nie było wiadomo, kiedy
 * i co użytkownik odpowiedział. Ta tabela zapisuje każdą odpowiedź zalogowanego
 * użytkownika (pytanie, wersja, wybrany wariant, wynik, czas).
 *
 * Anonimizacja (usunięcie konta, reset postępu) ustawia user_id = NULL zamiast
 * kasować wiersze - tak jak attempts, żeby statystyki treści zostały.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class SingleQuestionAnswer
{
  /** Odpowiedź na tę samą wersję pytania w tym oknie (s) traktujemy jak duplikat (podwójne kliknięcie, ponowione żądanie). */
  public const DUPLICATE_WINDOW_SECONDS = 5;

  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Zapisuje odpowiedź użytkownika.
   */
  public function record(int $userId, int $questionId, int $questionVersionId, int $answerId, bool $isCorrect): bool
  {
    $sql = "INSERT INTO single_question_answers (user_id, question_id, question_version_id, answer_id, is_correct)
            VALUES (?, ?, ?, ?, ?)";

    return $this->db->execute($sql, [$userId, $questionId, $questionVersionId, $answerId, $isCorrect ? 1 : 0]);
  }

  /**
   * Czy użytkownik odpowiedział na tę wersję pytania przed chwilą (duplikat żądania).
   */
  public function isRecentDuplicate(int $userId, int $questionVersionId): bool
  {
    $sql = "SELECT 1 FROM single_question_answers
            WHERE user_id = ? AND question_version_id = ?
              AND answered_at >= NOW() - INTERVAL " . self::DUPLICATE_WINDOW_SECONDS . " SECOND
            LIMIT 1";

    return (bool) $this->db->fetch($sql, [$userId, $questionVersionId]);
  }

  /**
   * Podsumowanie odpowiedzi użytkownika (liczba, poprawne, ostatnia odpowiedź).
   *
   * @return array{total:int, correct:int, accuracy:float|null, last_answered_at:?string}
   */
  public function getUserSummary(int $userId): array
  {
    $row = $this->db->fetch(
      "SELECT COUNT(*) AS total, COALESCE(SUM(is_correct), 0) AS correct, MAX(answered_at) AS last_answered_at
       FROM single_question_answers
       WHERE user_id = ?",
      [$userId]
    ) ?: [];

    $total = (int) ($row['total'] ?? 0);
    $correct = (int) ($row['correct'] ?? 0);

    return [
      'total' => $total,
      'correct' => $correct,
      'accuracy' => $total > 0 ? round($correct * 100 / $total, 1) : null,
      'last_answered_at' => $row['last_answered_at'] ?? null,
    ];
  }

  /**
   * Anonimizuje odpowiedzi użytkownika w obrębie jednej kwalifikacji (reset postępu).
   */
  public function anonymizeForExam(int $userId, int $examTypeId): bool
  {
    $sql = "UPDATE single_question_answers sqa
            JOIN questions q ON q.id = sqa.question_id
            SET sqa.user_id = NULL
            WHERE sqa.user_id = ? AND q.exam_type_id = ?";

    return $this->db->execute($sql, [$userId, $examTypeId]);
  }
}
