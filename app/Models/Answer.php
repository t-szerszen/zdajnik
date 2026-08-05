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
