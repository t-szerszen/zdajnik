<?php

namespace App\Models;

use App\Core\Database;

/**
 * Model Zapisanych Pytań (SavedQuestion).
 *
 * Klasa obsługuje funkcjonalność "Schowka" lub "Ulubionych", pozwalając użytkownikom
 * na oznaczanie konkretnych pytań do późniejszego przejrzenia. Realizuje relację 
 * Many-to-Many pomiędzy tabelami `users` oraz `questions`.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class SavedQuestion
{
  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  /**
   * Inicjalizuje model zapisanych pytań.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Weryfikuje, czy wskazane pytanie znajduje się już w schowku użytkownika.
   * 
   * @param int $userId ID użytkownika.
   * @param int $questionId ID bazowego pytania.
   * @return bool True jeśli pytanie jest zapisane.
   */
  public function isSaved(int $userId, int $questionId): bool
  {
    $sql = "SELECT 1 FROM saved_questions WHERE user_id = ? AND question_id = ? LIMIT 1";
    return $this->db->fetch($sql, [$userId, $questionId]) !== false;
  }

  /**
   * Przełącza stan zapisania pytania (Logika Toggle).
   * 
   * Jeśli pytanie figurowało w schowku - zostaje usunięte. 
   * W przeciwnym razie zostaje dodane nowe powiązanie.
   * 
   * @param int $userId ID użytkownika.
   * @param int $questionId ID pytania.
   * @return bool Status powodzenia operacji SQL.
   */
  public function toggle(int $userId, int $questionId): bool
  {
    if ($this->isSaved($userId, $questionId)) {
      // Usunięcie ze schowka
      $sql = "DELETE FROM saved_questions WHERE user_id = ? AND question_id = ?";
      return $this->db->execute($sql, [$userId, $questionId]);
    } else {
      // Dodanie do schowka
      $sql = "INSERT INTO saved_questions (user_id, question_id) VALUES (?, ?)";
      return $this->db->execute($sql, [$userId, $questionId]);
    }
  }

  /**
   * Pobiera kompletną listę identyfikatorów pytań zapisanych przez użytkownika.
   * 
   * @param int $userId ID użytkownika.
   * @return array Tablica jednowymiarowa z ID pytań.
   */
  public function getUserSavedIds(int $userId): array
  {
    $sql = "SELECT question_id FROM saved_questions WHERE user_id = ?";
    return $this->db->fetchColumn($sql, [$userId]);
  }

  /**
   * Zlicza łączną liczbę pytań przechowywanych w schowku użytkownika.
   * 
   * @param int $userId ID użytkownika.
   * @return int Liczba zapisanych pytań.
   */
  public function countForUser(int $userId): int
  {
    $sql = "SELECT COUNT(*) as total FROM saved_questions WHERE user_id = ?";
    $result = $this->db->fetch($sql, [$userId]);
    return (int) ($result['total'] ?? 0);
  }
}
