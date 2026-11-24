<?php

namespace App\Models;

use App\Core\Database;

/**
 * Model Odpowiedzi (Answer).
 *
 * Klasa zarządza dostępem do danych w tabeli `answers`. Odpowiada za pobieranie 
 * opcji odpowiedzi (wariantów A, B, C, D) przypisanych do konkretnych wersji pytań 
 * egzaminacyjnych. Wspiera pobieranie jednostkowe oraz optymalizowane pobieranie 
 * masowe (Batch Loading) dla całych testów.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class Answer
{
  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  /**
   * Inicjalizuje model odpowiedzi.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Pobiera wszystkie aktywne odpowiedzi dla danej wersji pytania w losowej kolejności.
   *
   * @param int $questionVersionId Unikalny identyfikator wersji pytania.
   * @return array Tablica asocjacyjna odpowiedzi [id, answer_text, is_correct].
   */
  public function getAnswersByVersionId(int $questionVersionId): array
  {
    $sql = "SELECT id, answer_text, is_correct 
            FROM answers 
            WHERE question_version_id = ? AND is_active = 1
            ORDER BY RAND();";

    return $this->db->fetchAll($sql, [$questionVersionId]);
  }

  /**
   * Pobiera odpowiedzi w stałej kolejności na potrzeby publicznych stron pytań.
   *
   * W odróżnieniu od getAnswersByVersionId() nie losuje kolejności i pomija
   * odpowiedzi usunięte miękko - strona indeksowalna musi wyglądać tak samo
   * przy każdym przejściu robota.
   *
   * @param int $questionVersionId Unikalny identyfikator wersji pytania.
   * @return array Odpowiedzi posortowane wg answer_order.
   */
  public function getPublicAnswersByVersionId(int $questionVersionId): array
  {
    $sql = "SELECT id, answer_text, answer_order, is_correct
            FROM answers
            WHERE question_version_id = ? AND is_active = 1 AND deleted_at IS NULL
            ORDER BY answer_order ASC, id ASC;";

    return $this->db->fetchAll($sql, [$questionVersionId]);
  }

  /**
   * Sprawdza wybraną odpowiedź w kontekście wersji pytania (jedno zapytanie).
   *
   * Zwraca dane tylko wtedy, gdy odpowiedź naprawdę należy do tej wersji pytania -
   * wcześniej endpoint porównywał jedynie ID z poprawną odpowiedzią i przyjmował
   * dowolną liczbę jako "wybraną odpowiedź".
   *
   * @return array{answer_id:int, is_correct:int, correct_answer_id:?int, question_id:int, exam_type_id:int, topic_id:int}|false
   */
  public function getAnswerContext(int $versionId, int $answerId): array|false
  {
    $sql = "SELECT
              a.id AS answer_id,
              a.is_correct,
              qv.question_id,
              q.exam_type_id,
              q.topic_id,
              (SELECT c.id FROM answers c
               WHERE c.question_version_id = qv.id AND c.is_correct = 1 AND c.is_active = 1
               LIMIT 1) AS correct_answer_id
            FROM question_versions qv
            JOIN questions q ON q.id = qv.question_id
            JOIN answers a ON a.id = ? AND a.question_version_id = qv.id AND a.is_active = 1
            WHERE qv.id = ?
            LIMIT 1";

    return $this->db->fetch($sql, [$answerId, $versionId]);
  }

  /**
   * Zwraca poprawność wskazanych odpowiedzi - tylko tych, które należą do podanych wersji pytań.
   *
   * @param array<int, int> $pairs Mapa question_version_id => answer_id.
   * @return array<int, array{question_id:int, is_correct:int}> Klucz: question_version_id.
   */
  public function verifyAnswersForVersions(array $pairs): array
  {
    if (empty($pairs)) {
      return [];
    }

    $conditions = [];
    $params = [];
    foreach ($pairs as $versionId => $answerId) {
      $conditions[] = '(a.question_version_id = ? AND a.id = ?)';
      $params[] = (int) $versionId;
      $params[] = (int) $answerId;
    }

    $sql = "SELECT a.question_version_id, a.is_correct, qv.question_id
            FROM answers a
            JOIN question_versions qv ON qv.id = a.question_version_id
            WHERE " . implode(' OR ', $conditions);

    $result = [];
    foreach ($this->db->fetchAll($sql, $params) as $row) {
      $result[(int) $row['question_version_id']] = [
        'question_id' => (int) $row['question_id'],
        'is_correct' => (int) $row['is_correct'],
      ];
    }

    return $result;
  }

  /**
   * Pobiera definicję poprawnej odpowiedzi dla danej wersji pytania.
   *
   * @param int $versionId ID wersji pytania.
   * @return array|false Tablica z danymi poprawnej odpowiedzi lub false w razie braku.
   */
  public function getCorrectAnswerForVersion(int $versionId): array|false
  {
    $sql = "SELECT id, answer_text 
            FROM answers 
            WHERE question_version_id = ? AND is_correct = 1 AND is_active = 1 
            LIMIT 1";

    return $this->db->fetch($sql, [$versionId]);
  }

  /**
   * Pobiera odpowiedzi dla zestawu wersji pytań w jednym zapytaniu SQL.
   *
   * Metoda optymalizuje wydajność aplikacji poprzez redukcję liczby zapytań 
   * do bazy danych (N+1 Problem) podczas generowania pełnych testów.
   *
   * @param array<int> $versionIds Tablica identyfikatorów wersji pytań.
   * @return array Odpowiedzi pogrupowane wg klucza `question_version_id`.
   */
  public function getAnswersForBatch(array $versionIds): array
  {
    // Zabezpieczenie przed błędem SQL przy pustej tablicy wejściowej
    if (empty($versionIds)) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
    $sql = "SELECT id, question_version_id, answer_text, is_correct 
            FROM answers 
            WHERE question_version_id IN ($placeholders) AND is_active = 1
            ORDER BY question_version_id, RAND();";

    $results = $this->db->fetchAll($sql, $versionIds);

    // KROK: Grupowanie wyników w tablicę asocjacyjną dla szybkiego dostępu O(1)
    $grouped = [];
    foreach ($results as $row) {
      $grouped[$row['question_version_id']][] = $row;
    }

    return $grouped;
  }
}
