<?php

namespace App\Models;

use App\Core\Database;

/**
 * Model Arkusza (Sheet).
 *
 * Warstwa odczytu danych dla publicznej Biblioteki Arkuszy. Biblioteka jest
 * podzielona per kwalifikacja (exam_types): hub pokazuje kwalifikacje wraz
 * z liczbą arkuszy, a lista arkuszy danej kwalifikacji grupuje je po tematach.
 * Zapytania uwzględniają wyłącznie rekordy aktywne i nieusunięte, a przy
 * zalogowanym użytkowniku dołączają informację o jego notatce (sheet_notes).
 *
 * @version 2.0.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class Sheet
{
  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  /**
   * Inicjalizuje model arkuszy.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Pobiera aktywne kwalifikacje wraz z liczbą aktywnych arkuszy (hub biblioteki).
   *
   * @return array Tablica kwalifikacji: id, code, name, description, sheets_count.
   */
  public function getExamsWithSheetCounts(): array
  {
    $sql = "SELECT e.id, e.code, e.name, e.description,
                   COUNT(s.id) AS sheets_count
            FROM exam_types e
            LEFT JOIN sheet_topics t ON t.exam_type_id = e.id
              AND t.is_active = 1 AND t.deleted_at IS NULL
            LEFT JOIN sheets s ON s.sheet_topic_id = t.id
              AND s.is_active = 1 AND s.deleted_at IS NULL
            WHERE e.is_active = 1 AND e.deleted_at IS NULL
            GROUP BY e.id, e.code, e.name, e.description
            ORDER BY e.code ASC";
    return $this->db->fetchAll($sql);
  }

  /**
   * Pobiera aktywne tematy danej kwalifikacji wraz z listą aktywnych arkuszy.
   *
   * Dla zalogowanego użytkownika każdy arkusz otrzymuje flagę `has_note`
   * oraz jego subiektywną ocenę trudności (`user_difficulty`).
   *
   * @param int $examTypeId ID kwalifikacji (exam_types).
   * @param int|null $userId ID zalogowanego użytkownika lub null dla gościa.
   * @return array Tablica tematów, każdy z kluczem 'sheets'.
   */
  public function getTopicsWithSheets(int $examTypeId, ?int $userId): array
  {
    $topicsSql = "SELECT id, name, description
                  FROM sheet_topics
                  WHERE exam_type_id = ? AND is_active = 1 AND deleted_at IS NULL
                  ORDER BY display_order ASC, name ASC";
    $topics = $this->db->fetchAll($topicsSql, [$examTypeId]);

    if (empty($topics)) {
      return [];
    }

    $sheetsSql = "SELECT s.id, s.sheet_topic_id, s.title, s.description, s.difficulty,
                         s.zip_path IS NOT NULL AS has_zip,
                         s.resources_path IS NOT NULL AS has_resources,
                         s.thumbnail_path, s.created_at,
                         n.id IS NOT NULL AS has_note,
                         n.subjective_difficulty AS user_difficulty
                  FROM sheets s
                  JOIN sheet_topics t ON t.id = s.sheet_topic_id
                  LEFT JOIN sheet_notes n ON n.sheet_id = s.id AND n.user_id = ?
                  WHERE t.exam_type_id = ? AND s.is_active = 1 AND s.deleted_at IS NULL
                  ORDER BY s.difficulty ASC, s.title ASC";
    $sheets = $this->db->fetchAll($sheetsSql, [$userId ?? 0, $examTypeId]);

    // Grupowanie arkuszy po temacie
    $sheetsByTopic = [];
    foreach ($sheets as $sheet) {
      $sheetsByTopic[(int) $sheet['sheet_topic_id']][] = $sheet;
    }

    foreach ($topics as &$topic) {
      $topic['sheets'] = $sheetsByTopic[(int) $topic['id']] ?? [];
    }
    unset($topic);

    // Tematy bez aktywnych arkuszy nie są pokazywane na liście publicznej
    return array_values(array_filter($topics, static fn(array $topic): bool => !empty($topic['sheets'])));
  }

  /**
   * Pobiera pojedynczy aktywny arkusz wraz z nazwą tematu i kwalifikacją.
   *
   * @param int $sheetId ID arkusza.
   * @return array|false Dane arkusza lub false, gdy nie istnieje / jest nieaktywny.
   */
  public function findActiveById(int $sheetId): array|false
  {
    $sql = "SELECT s.id, s.sheet_topic_id, s.title, s.description, s.difficulty,
                   s.pdf_path, s.zip_path, s.thumbnail_path, s.resources_path, s.created_at,
                   t.name AS topic_name,
                   e.code AS exam_code, e.name AS exam_name
            FROM sheets s
            JOIN sheet_topics t ON t.id = s.sheet_topic_id
            JOIN exam_types e ON e.id = t.exam_type_id
            WHERE s.id = ? AND s.is_active = 1 AND s.deleted_at IS NULL
              AND t.is_active = 1 AND t.deleted_at IS NULL
              AND e.is_active = 1 AND e.deleted_at IS NULL
            LIMIT 1";
    return $this->db->fetch($sql, [$sheetId]);
  }
}
