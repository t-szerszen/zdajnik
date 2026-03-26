<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Model SavedQuestion (MVC)
 * Obsługuje zapisywanie pytań przez użytkowników (relacja wiele-do-wielu).
 */
class SavedQuestion
{
  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Sprawdza, czy pytanie jest zapisane przez użytkownika.
   * 
   * @param int $userId
   * @param int $questionId
   * @return bool
   */
  public function isSaved(int $userId, int $questionId): bool
  {
    $sql = "SELECT 1 FROM saved_questions WHERE user_id = ? AND question_id = ? LIMIT 1";
    return $this->db->fetch($sql, [$userId, $questionId]) !== false;
  }

  /**
   * Przełącza stan zapisania pytania (Toggle).
   * Jeśli zapisane - usuwa. Jeśli nie - dodaje.
   * 
   * @param int $userId
   * @param int $questionId
   * @return bool Prawda jeśli operacja się udała.
   */
  public function toggle(int $userId, int $questionId): bool
  {
    if ($this->isSaved($userId, $questionId)) {
      $sql = "DELETE FROM saved_questions WHERE user_id = ? AND question_id = ?";
      return $this->db->execute($sql, [$userId, $questionId]);
    } else {
      $sql = "INSERT INTO saved_questions (user_id, question_id) VALUES (?, ?)";
      return $this->db->execute($sql, [$userId, $questionId]);
    }
  }

  /**
   * Pobiera listę ID zapisanych pytań dla danego użytkownika.
   * 
   * @param int $userId
   * @return array Tablica ID pytań.
   */
  public function getUserSavedIds(int $userId): array
  {
    $sql = "SELECT question_id FROM saved_questions WHERE user_id = ?";
    return $this->db->fetchColumn($sql, [$userId]);
  }

  /**
   * Zlicza wszystkie zapisane pytania przez danego użytkownika.
   * 
   * @param int $userId
   * @return int
   */
  public function countForUser(int $userId): int
  {
    $sql = "SELECT COUNT(*) as total FROM saved_questions WHERE user_id = ?";
    $result = $this->db->fetch($sql, [$userId]);
    return (int)($result['total'] ?? 0);
  }
}
