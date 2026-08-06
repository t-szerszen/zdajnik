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
      'users_active' => $this->fetchCount('SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL AND is_active = 1'),
      'users_unverified' => $this->fetchCount('SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL AND is_verified = 0'),
      'users_inactive' => $this->fetchCount('SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL AND is_active = 0'),
      'admins' => $this->fetchCount("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL AND is_active = 1 AND role = 'admin'"),
      'exams' => $this->fetchCount('SELECT COUNT(*) as total FROM exam_types WHERE deleted_at IS NULL'),
      'exams_active' => $this->fetchCount('SELECT COUNT(*) as total FROM exam_types WHERE deleted_at IS NULL AND is_active = 1'),
      'exams_inactive' => $this->fetchCount('SELECT COUNT(*) as total FROM exam_types WHERE deleted_at IS NULL AND is_active = 0'),
      'questions' => $this->fetchCount('SELECT COUNT(*) as total FROM questions WHERE deleted_at IS NULL'),
      'questions_active' => $this->fetchCount('SELECT COUNT(*) as total FROM questions WHERE deleted_at IS NULL AND is_active = 1'),
      'questions_inactive' => $this->fetchCount('SELECT COUNT(*) as total FROM questions WHERE deleted_at IS NULL AND is_active = 0'),
      'attempts' => $this->fetchCount('SELECT COUNT(*) as total FROM attempts'),
      'last_activity' => $this->fetchLastActivity(),
    ];
  }

  /**
   * Dzienne serie trendów dla sparklines na dashboardzie.
   *
   * Zwraca pełną siatkę dni (łącznie z zerami), żeby wykresy nie miały dziur.
   *
   * @return array<string, array<int, array{date: string, value: int}>>
   */
  public function getTrendSeries(int $days = 30): array
  {
    $days = max(7, min(90, $days));

    return [
      'newUsers' => $this->fetchDailySeries(
        "SELECT DATE(created_at) as day, COUNT(*) as total
         FROM users
         WHERE deleted_at IS NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(created_at)",
        $days
      ),
      'attempts' => $this->fetchDailySeries(
        "SELECT DATE(started_at) as day, COUNT(*) as total
         FROM attempts
         WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(started_at)",
        $days
      ),
      'activity' => $this->fetchDailySeries(
        "SELECT DATE(event_date) as day, COUNT(*) as total
         FROM analytics_events
         WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(event_date)",
        $days
      ),
    ];
  }

  /**
   * Wykonuje zapytanie grupujące po dniu i uzupełnia brakujące dni zerami.
   *
   * @return array<int, array{date: string, value: int}>
   */
  private function fetchDailySeries(string $sql, int $days): array
  {
    $rows = $this->db->fetchAll($sql, [$days]);

    $byDay = [];
    foreach ($rows as $row) {
      $byDay[(string) $row['day']] = (int) $row['total'];
    }

    $series = [];
    for ($i = $days; $i >= 0; $i--) {
      $day = date('Y-m-d', strtotime("-$i days"));
      $series[] = ['date' => $day, 'value' => $byDay[$day] ?? 0];
    }

    return $series;
  }

  /**
   * @return array<string, array<int, array<string, mixed>>>
   */
  public function getAttentionItems(int $limit = 6): array
  {
    $limit = max(1, $limit);

    return [
      'unverified_users' => $this->db->fetchAll(
        "SELECT id, first_name, last_name, email, auth_provider, created_at
         FROM users
         WHERE deleted_at IS NULL AND is_verified = 0
         ORDER BY created_at ASC
         LIMIT $limit"
      ),
      'inactive_exams_with_questions' => $this->db->fetchAll(
        "SELECT et.id, et.code, et.name, COUNT(q.id) as questions_count
         FROM exam_types et
         JOIN questions q ON q.exam_type_id = et.id AND q.deleted_at IS NULL
         WHERE et.deleted_at IS NULL AND et.is_active = 0
         GROUP BY et.id, et.code, et.name
         ORDER BY questions_count DESC, et.id DESC
         LIMIT $limit"
      ),
      'invalid_questions' => $this->db->fetchAll(
        "SELECT
           q.id,
           et.code as exam_code,
           LEFT(qv.question_text, 180) as question_text,
           COUNT(a.id) as answers_count,
           SUM(CASE WHEN a.is_correct = 1 AND a.is_active = 1 THEN 1 ELSE 0 END) as correct_answers_count
         FROM questions q
         JOIN exam_types et ON et.id = q.exam_type_id
         LEFT JOIN question_versions qv
           ON qv.question_id = q.id AND qv.is_active = 1 AND qv.deleted_at IS NULL
         LEFT JOIN answers a
           ON a.question_version_id = qv.id AND a.deleted_at IS NULL AND a.is_active = 1
         WHERE q.deleted_at IS NULL AND q.is_active = 1
         GROUP BY q.id, et.code, qv.question_text
         HAVING answers_count < 2 OR correct_answers_count = 0
         ORDER BY answers_count ASC, q.id DESC
         LIMIT $limit"
      ),
      'operation_errors' => $this->db->fetchAll(
        "SELECT id, admin_user_id, action, target_type, target_id, created_at
         FROM admin_audit_logs
         WHERE action LIKE '%error%' OR action LIKE '%failed%'
         ORDER BY created_at DESC, id DESC
         LIMIT $limit"
      ),
    ];
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function getRecentAuditActions(int $limit = 8): array
  {
    return $this->db->fetchAll(
      "SELECT
         l.id, l.action, l.target_type, l.target_id, l.created_at,
         u.first_name, u.last_name, u.email
       FROM admin_audit_logs l
       LEFT JOIN users u ON u.id = l.admin_user_id
       ORDER BY l.created_at DESC, l.id DESC
       LIMIT " . max(1, $limit)
    );
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
