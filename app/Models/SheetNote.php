<?php

namespace App\Models;

use App\Core\Database;

/**
 * Model Notatki do Arkusza (SheetNote).
 *
 * Obsługuje prywatne notatki użytkowników do arkuszy z Biblioteki Arkuszy:
 * subiektywną ocenę trudności (1-10) oraz opis napotkanych problemów.
 * Każdy użytkownik posiada maksymalnie jedną notatkę na arkusz (upsert
 * oparty o unikalny klucz sheet_id + user_id).
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class SheetNote
{
  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  /**
   * Inicjalizuje model notatek.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Pobiera notatkę użytkownika dla wskazanego arkusza.
   *
   * @param int $userId ID użytkownika.
   * @param int $sheetId ID arkusza.
   * @return array|false Dane notatki lub false, gdy nie istnieje.
   */
  public function findForUser(int $userId, int $sheetId): array|false
  {
    $sql = "SELECT id, sheet_id, user_id, subjective_difficulty, problem_description, updated_at
            FROM sheet_notes
            WHERE user_id = ? AND sheet_id = ?
            LIMIT 1";
    return $this->db->fetch($sql, [$userId, $sheetId]);
  }

  /**
   * Pobiera wszystkie notatki użytkownika wraz z danymi arkuszy.
   *
   * Wynik zawiera oficjalną trudność arkusza, subiektywną ocenę użytkownika,
   * temat i kod kwalifikacji — do panelu "Moje notatki" i analizy słabych stron.
   *
   * @param int $userId ID użytkownika.
   * @param string $sort 'najtrudniejsze' (domyślnie) lub 'najnowsze'.
   * @return array Tablica notatek z danymi arkuszy.
   */
  public function getAllForUser(int $userId, string $sort = 'najtrudniejsze'): array
  {
    $orderBy = $sort === 'najnowsze'
      ? 'n.updated_at DESC'
      : 'n.subjective_difficulty DESC, n.updated_at DESC';

    $sql = "SELECT n.sheet_id, n.subjective_difficulty, n.problem_description, n.updated_at,
                   s.title AS sheet_title, s.difficulty AS official_difficulty, s.thumbnail_path,
                   t.name AS topic_name,
                   e.code AS exam_code
            FROM sheet_notes n
            JOIN sheets s ON s.id = n.sheet_id AND s.deleted_at IS NULL
            JOIN sheet_topics t ON t.id = s.sheet_topic_id
            JOIN exam_types e ON e.id = t.exam_type_id
            WHERE n.user_id = ?
            ORDER BY $orderBy";
    return $this->db->fetchAll($sql, [$userId]);
  }

  /**
   * Buduje podsumowanie notatek użytkownika (do karty w statystykach).
   *
   * @param int $userId ID użytkownika.
   * @return array{count: int, avg_difficulty: float|null, hard_count: int, hardest: array}
   */
  public function getSummaryForUser(int $userId): array
  {
    $summarySql = "SELECT COUNT(*) AS total,
                          AVG(subjective_difficulty) AS avg_difficulty,
                          SUM(subjective_difficulty >= 8) AS hard_count
                   FROM sheet_notes n
                   JOIN sheets s ON s.id = n.sheet_id AND s.deleted_at IS NULL
                   WHERE n.user_id = ?";
    $row = $this->db->fetch($summarySql, [$userId]);

    return [
      'count' => (int) ($row['total'] ?? 0),
      'avg_difficulty' => isset($row['avg_difficulty']) && $row['avg_difficulty'] !== null
        ? round((float) $row['avg_difficulty'], 1)
        : null,
      'hard_count' => (int) ($row['hard_count'] ?? 0),
      'hardest' => $this->db->fetchAll(
        "SELECT n.sheet_id, n.subjective_difficulty, s.title AS sheet_title, e.code AS exam_code
         FROM sheet_notes n
         JOIN sheets s ON s.id = n.sheet_id AND s.deleted_at IS NULL
         JOIN sheet_topics t ON t.id = s.sheet_topic_id
         JOIN exam_types e ON e.id = t.exam_type_id
         WHERE n.user_id = ?
         ORDER BY n.subjective_difficulty DESC, n.updated_at DESC
         LIMIT 3",
        [$userId]
      ),
    ];
  }

  /**
   * Tworzy lub aktualizuje notatkę użytkownika (upsert).
   *
   * @param int $userId ID użytkownika.
   * @param int $sheetId ID arkusza.
   * @param int $subjectiveDifficulty Subiektywna trudność w skali 1-10.
   * @param string $problemDescription Opis problemów (może być pusty).
   * @return bool Status powodzenia operacji SQL.
   */
  public function upsert(int $userId, int $sheetId, int $subjectiveDifficulty, string $problemDescription): bool
  {
    $sql = "INSERT INTO sheet_notes (sheet_id, user_id, subjective_difficulty, problem_description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              subjective_difficulty = VALUES(subjective_difficulty),
              problem_description = VALUES(problem_description)";
    return $this->db->execute($sql, [$sheetId, $userId, $subjectiveDifficulty, $problemDescription]);
  }
}
