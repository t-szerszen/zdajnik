<?php

namespace App\Services;

use App\Core\Database;

/**
 * Serwis danych panelu administracyjnego.
 *
 * Dostarcza podstawowe metryki dashboardu CMS oraz lekkie listy
 * do widoków administracyjnych bez mieszania SQL w kontrolerach.
 *
 * @version 1.0.0
 * @package App\Services
 */
class AdminDashboardService
{
  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Zwraca podstawowy zestaw liczników dla dashboardu.
   *
   * @return array<string, int|string|null>
   */
  public function getOverview(): array
  {
    return [
      'users' => $this->fetchCount('SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL'),
      'admins' => $this->fetchCount("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL AND role = 'admin'"),
      'exams' => $this->fetchCount('SELECT COUNT(*) as total FROM exam_types WHERE deleted_at IS NULL'),
      'questions' => $this->fetchCount('SELECT COUNT(*) as total FROM questions WHERE deleted_at IS NULL'),
      'attempts' => $this->fetchCount('SELECT COUNT(*) as total FROM attempts'),
      'last_activity' => $this->fetchLastActivity(),
    ];
  }

  /**
   * Pobiera listę najnowszych użytkowników.
   *
   * @param int $limit
   * @return array<int, array<string, mixed>>
   */
  public function getRecentUsers(int $limit = 8): array
  {
    $sql = "SELECT id, first_name, last_name, email, role, is_verified, auth_provider, created_at, updated_at
            FROM users
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT " . max(1, $limit);

    return $this->db->fetchAll($sql);
  }

  /**
   * Pobiera listę ostatnich aktywności na podstawie prób egzaminacyjnych.
   *
   * @param int $limit
   * @return array<int, array<string, mixed>>
   */
  public function getRecentActivity(int $limit = 8): array
  {
    $sql = "SELECT a.id, a.completed_at, a.created_at, a.correct_count, a.total_questions, a.test_type,
                   u.first_name, u.last_name, u.email,
                   et.code as exam_code, et.name as exam_name
            FROM attempts a
            JOIN users u ON a.user_id = u.id
            JOIN exam_types et ON a.exam_type_id = et.id
            WHERE a.completed_at IS NOT NULL
            ORDER BY a.completed_at DESC
            LIMIT " . max(1, $limit);

    return $this->db->fetchAll($sql);
  }

  /**
   * Zwraca flagę, czy istnieją jakiekolwiek dane aktywności.
   *
   * @return bool
   */
  public function hasActivityData(): bool
  {
    return !empty($this->getRecentActivity(1)) || $this->fetchLastActivity() !== null;
  }

  /**
   * Zwraca pojedynczy licznik.
   *
   * @param string $sql
   * @return int
   */
  private function fetchCount(string $sql): int
  {
    $result = $this->db->fetch($sql);
    return (int) ($result['total'] ?? 0);
  }

  /**
   * Pobiera ostatni timestamp aktywności z całej aplikacji.
   *
   * @return string|null
   */
  private function fetchLastActivity(): ?string
  {
    $sql = "SELECT MAX(last_seen) as last_activity
            FROM (
              SELECT MAX(updated_at) as last_seen FROM users WHERE deleted_at IS NULL
              UNION ALL
              SELECT MAX(updated_at) as last_seen FROM exam_types WHERE deleted_at IS NULL
              UNION ALL
              SELECT MAX(updated_at) as last_seen FROM questions WHERE deleted_at IS NULL
              UNION ALL
              SELECT MAX(updated_at) as last_seen FROM attempts
            ) activity_feed";

    $result = $this->db->fetch($sql);
    return !empty($result['last_activity']) ? (string) $result['last_activity'] : null;
  }
}
