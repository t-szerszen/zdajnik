<?php

namespace App\Services;

use App\Core\Database;
use App\Services\Concerns\AdminBulkActionTrait;
use App\Services\Concerns\AdminListQueryTrait;
use RuntimeException;

/**
 * Serwis danych modułu użytkowników panelu admina.
 *
 * Zawiera zapytania do listy użytkowników, szczegółów oraz statystyk.
 *
 * @version 1.0.0
 * @package App\Services
 */
class AdminUserService
{
  use AdminListQueryTrait;
  use AdminBulkActionTrait;

  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Pobiera użytkowników z filtrami, sortowaniem i paginacją.
   *
   * @param array<string, mixed> $filters
   * @param int $page
   * @param int $perPage
   * @param string $sortBy
   * @param string $sortDir
   * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int, totalPages: int}
   */
  public function searchUsers(array $filters, int $page, int $perPage, string $sortBy, string $sortDir): array
  {
    $allowedSort = [
      'id' => 'managed_users.id',
      'email' => 'managed_users.email',
      'created_at' => 'managed_users.created_at',
      'last_activity' => 'managed_users.last_activity',
    ];

    [$sortColumn, $sortDir] = $this->resolveSort($allowedSort, $sortBy, 'managed_users.id', $sortDir);

    [$whereSql, $params] = $this->buildFilterSql($filters);

    $baseSql = $this->getManagedUsersBaseSql();
    $itemsSql = "SELECT *
            FROM ($baseSql) managed_users
            WHERE 1=1 $whereSql
            ORDER BY $sortColumn $sortDir, managed_users.id DESC";

    $countSql = "SELECT COUNT(*) as total
                 FROM ($baseSql) managed_users
                 WHERE 1=1 $whereSql";

    return $this->paginate($itemsSql, $countSql, $params, $page, $perPage);
  }

  /**
   * Pobiera pełny rekord użytkownika.
   */
  public function getUserById(int $userId): array|false
  {
    $sql = "SELECT
              u.*,
              event_stats.last_login,
              GREATEST(
                COALESCE(event_stats.last_activity, '1000-01-01 00:00:00'),
                COALESCE(attempt_stats.last_attempt, '1000-01-01 00:00:00'),
                u.created_at
              ) as last_activity,
              COALESCE(attempt_stats.completed_tests, 0) as solved_tests
            FROM users u
            LEFT JOIN (
              SELECT
                user_id,
                MAX(event_date) as last_activity,
                MAX(CASE WHEN event_type = 'login' THEN event_date END) as last_login
              FROM analytics_events
              WHERE user_id IS NOT NULL
              GROUP BY user_id
            ) event_stats ON event_stats.user_id = u.id
            LEFT JOIN (
              SELECT
                user_id,
                MAX(COALESCE(completed_at, started_at)) as last_attempt,
                SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed_tests
              FROM attempts
              WHERE user_id IS NOT NULL
              GROUP BY user_id
            ) attempt_stats ON attempt_stats.user_id = u.id
            WHERE u.id = ?
            LIMIT 1";
    return $this->db->fetch($sql, [$userId]);
  }

  /**
   * Pobiera podstawowe statystyki użytkownika.
   *
   * @return array<string, mixed>
   */
  public function getUserStats(int $userId): array
  {
    $sql = "SELECT
              COUNT(*) as started_tests,
              COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed_tests,
              ROUND(AVG(CASE WHEN completed_at IS NOT NULL AND total_questions > 0 THEN (correct_count * 100.0 / total_questions) END), 1) as avg_score
            FROM attempts
            WHERE user_id = ?";

    $result = $this->db->fetch($sql, [$userId]) ?: [];

    $mistakesSql = "SELECT
                      q.id as question_id,
                      qv.question_text,
                      t.name as topic_name,
                      up.incorrect_count,
                      up.attempts_count,
                      up.updated_at
                    FROM user_progress up
                    JOIN questions q ON up.question_id = q.id
                    JOIN question_versions qv ON q.id = qv.question_id AND qv.is_active = 1
                    LEFT JOIN topics t ON q.topic_id = t.id
                    WHERE up.user_id = ? AND up.incorrect_count > 0
                    ORDER BY up.incorrect_count DESC, up.updated_at DESC
                    LIMIT 5";

    $result['top_mistakes'] = $this->db->fetchAll($mistakesSql, [$userId]);

    // Tryb "jedno pytanie" - główny sposób nauki, więc pokazujemy go obok testów.
    $result['single'] = (new \App\Models\SingleQuestionAnswer())->getUserSummary($userId);

    return $result;
  }

  /**
   * Historia prób użytkownika.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getUserAttempts(int $userId, int $limit = 100): array
  {
    $sql = "SELECT
              a.id,
              a.started_at,
              a.completed_at,
              a.correct_count,
              a.total_questions,
              a.test_type,
              et.code as exam_code,
              et.name as exam_name,
              ROUND((a.correct_count * 100.0 / NULLIF(a.total_questions, 0)), 1) as score_percent,
              (a.total_questions - a.correct_count) as wrong_count
            FROM attempts a
            JOIN exam_types et ON a.exam_type_id = et.id
            WHERE a.user_id = ?
            ORDER BY a.completed_at DESC, a.started_at DESC
            LIMIT " . max(1, $limit);

    return $this->db->fetchAll($sql, [$userId]);
  }

  /**
   * Podgląd odpowiedzi użytkownika.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getUserAnswerPreview(int $userId, int $limit = 40): array
  {
    // Odpowiedzi z testów i z trybu "jedno pytanie" na jednej osi czasu.
    // source: 'test' | 'single'; answer_unknown = 1 dla odpowiedzi zaimportowanych
    // ze zdarzeń analityki (znamy wynik, ale nie wybrany wariant).
    $limit = max(1, $limit);
    $sql = "SELECT * FROM (
              (SELECT
                'test' AS source,
                aa.answered_at,
                a.id AS attempt_id,
                q.id AS question_id,
                qv.question_text,
                ua.answer_text AS user_answer,
                ca.answer_text AS correct_answer,
                aa.is_correct,
                0 AS answer_unknown
              FROM attempt_answers aa
              JOIN attempts a ON aa.attempt_id = a.id
              JOIN question_versions qv ON aa.question_version_id = qv.id
              JOIN questions q ON qv.question_id = q.id
              LEFT JOIN answers ua ON aa.answer_id = ua.id
              LEFT JOIN answers ca ON ca.question_version_id = aa.question_version_id AND ca.is_correct = 1
              WHERE a.user_id = ?
              ORDER BY aa.answered_at DESC
              LIMIT $limit)
              UNION ALL
              (SELECT
                'single' AS source,
                sqa.answered_at,
                NULL AS attempt_id,
                sqa.question_id,
                COALESCE(qv.question_text, cur.question_text) AS question_text,
                ua.answer_text AS user_answer,
                ca.answer_text AS correct_answer,
                sqa.is_correct,
                CASE WHEN sqa.answer_id IS NULL THEN 1 ELSE 0 END AS answer_unknown
              FROM single_question_answers sqa
              LEFT JOIN question_versions qv ON qv.id = sqa.question_version_id
              LEFT JOIN question_versions cur ON cur.question_id = sqa.question_id AND cur.is_active = 1
              LEFT JOIN answers ua ON ua.id = sqa.answer_id
              LEFT JOIN answers ca ON ca.question_version_id = COALESCE(sqa.question_version_id, cur.id) AND ca.is_correct = 1
              WHERE sqa.user_id = ?
              ORDER BY sqa.answered_at DESC
              LIMIT $limit)
            ) answers_timeline
            ORDER BY answered_at DESC
            LIMIT $limit";

    return $this->db->fetchAll($sql, [$userId, $userId]);
  }

  /**
   * Aktualizuje rolę użytkownika.
   */
  public function updateUserRole(int $userId, string $role): bool
  {
    $sql = "UPDATE users SET role = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL";
    return $this->db->execute($sql, [$role, $userId]);
  }

  public function verifyUser(int $userId): bool
  {
    return $this->db->execute(
      "UPDATE users SET is_verified = 1, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL",
      [$userId]
    );
  }

  public function deactivateUser(int $userId): bool
  {
    return $this->db->execute(
      "UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL",
      [$userId]
    );
  }

  public function reactivateUser(int $userId): bool
  {
    return $this->db->execute(
      "UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL",
      [$userId]
    );
  }

  /**
   * @return array<string, int>
   */
  public function getUserManagementSummary(): array
  {
    $result = $this->db->fetch(
      "SELECT
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users,
        SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as unverified_users,
        SUM(CASE WHEN role = 'admin' AND is_active = 1 THEN 1 ELSE 0 END) as active_admins
       FROM users
       WHERE deleted_at IS NULL"
    ) ?: [];

    return [
      'active_users' => (int) ($result['active_users'] ?? 0),
      'inactive_users' => (int) ($result['inactive_users'] ?? 0),
      'unverified_users' => (int) ($result['unverified_users'] ?? 0),
      'active_admins' => (int) ($result['active_admins'] ?? 0),
    ];
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function getRecentUserActivity(int $limit = 8): array
  {
    $baseSql = $this->getManagedUsersBaseSql();
    return $this->db->fetchAll(
      "SELECT *
       FROM ($baseSql) managed_users
       ORDER BY last_activity DESC, id DESC
       LIMIT " . max(1, $limit)
    );
  }

  /**
   * Zwraca liczbę aktywnych adminów.
   */
  public function countActiveAdmins(): int
  {
    $sql = "SELECT COUNT(*) as total
            FROM users
            WHERE deleted_at IS NULL AND is_active = 1 AND role = 'admin'";
    $result = $this->db->fetch($sql);
    return (int) ($result['total'] ?? 0);
  }

  /**
   * Zwraca liczbę adminów poza wskazanym użytkownikiem.
   */
  public function countOtherActiveAdmins(int $userId): int
  {
    $sql = "SELECT COUNT(*) as total
            FROM users
            WHERE deleted_at IS NULL AND is_active = 1 AND role = 'admin' AND id <> ?";
    $result = $this->db->fetch($sql, [$userId]);
    return (int) ($result['total'] ?? 0);
  }

  /**
   * Akcja zbiorcza na kontach z zachowaniem zabezpieczeń domenowych
   * (własne konto, ostatni aktywny admin, idempotencja) i audytem per rekord.
   *
   * @param array<int, int> $ids
   * @return array{succeeded: array<int, int>, failed: array<int|string, string>}
   */
  public function bulkUserAction(array $ids, string $action, int $currentAdminId, AdminAuditService $auditService): array
  {
    return $this->applyBulkAction($ids, function (int $userId) use ($action, $currentAdminId, $auditService): ?string {
      $user = $this->getUserById($userId);
      if (!$user || !empty($user['deleted_at'])) {
        return 'Konto nie istnieje.';
      }

      if ($action === 'deactivate') {
        if ($userId === $currentAdminId) {
          return 'Nie można zdezaktywować własnego konta.';
        }
        if (
          ($user['role'] ?? 'user') === 'admin'
          && (int) ($user['is_active'] ?? 0) === 1
          && $this->countActiveAdmins() <= 1
        ) {
          return 'Ostatni aktywny administrator.';
        }
      }

      $alreadyApplied = ($action === 'verify' && (int) ($user['is_verified'] ?? 0) === 1)
        || ($action === 'deactivate' && (int) ($user['is_active'] ?? 1) === 0)
        || ($action === 'reactivate' && (int) ($user['is_active'] ?? 0) === 1);
      if ($alreadyApplied) {
        return 'Status już ustawiony.';
      }

      if ($action === 'verify') {
        $changed = $this->verifyUser($userId);
        $audited = $auditService->logUserVerified(
          $currentAdminId,
          $userId,
          ['is_verified' => (int) ($user['is_verified'] ?? 0)],
          ['is_verified' => 1]
        );
      } elseif ($action === 'deactivate') {
        $changed = $this->deactivateUser($userId);
        $audited = $auditService->logUserDeactivated(
          $currentAdminId,
          $userId,
          ['is_active' => (int) ($user['is_active'] ?? 1)],
          ['is_active' => 0]
        );
      } else {
        $changed = $this->reactivateUser($userId);
        $audited = $auditService->logUserReactivated(
          $currentAdminId,
          $userId,
          ['is_active' => (int) ($user['is_active'] ?? 0)],
          ['is_active' => 1]
        );
      }

      if (!$changed || !$audited) {
        throw new RuntimeException('Zapis zmiany statusu lub audytu nie powiódł się.');
      }

      return null;
    });
  }

  /**
   * Buduje fragment WHERE dla wyszukiwania.
   *
   * @param array<string, mixed> $filters
   * @return array{0:string,1:array<int, mixed>}
   */
  private function buildFilterSql(array $filters): array
  {
    $whereParts = [];
    $params = [];

    if (!empty($filters['query'])) {
      $query = trim((string) $filters['query']);
      if (ctype_digit($query)) {
        $whereParts[] = '(managed_users.id = ? OR managed_users.email LIKE ? OR managed_users.first_name LIKE ? OR managed_users.last_name LIKE ?)';
        $params[] = (int) $query;
        $like = '%' . $query . '%';
        array_push($params, $like, $like, $like);
      } else {
        $whereParts[] = '(managed_users.email LIKE ? OR managed_users.first_name LIKE ? OR managed_users.last_name LIKE ?)';
        $like = '%' . $query . '%';
        array_push($params, $like, $like, $like);
      }
    }

    if (!empty($filters['role']) && in_array($filters['role'], ['user', 'redaktor', 'admin'], true)) {
      $whereParts[] = 'managed_users.role = ?';
      $params[] = $filters['role'];
    }

    if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive'], true)) {
      if ($filters['status'] === 'active') {
        $whereParts[] = 'managed_users.is_active = 1';
      } else {
        $whereParts[] = 'managed_users.is_active = 0';
      }
    }

    if (!empty($filters['verified']) && in_array($filters['verified'], ['verified', 'unverified'], true)) {
      $whereParts[] = 'managed_users.is_verified = ?';
      $params[] = $filters['verified'] === 'verified' ? 1 : 0;
    }

    if (!empty($filters['auth_provider']) && in_array($filters['auth_provider'], ['local', 'google'], true)) {
      $whereParts[] = 'managed_users.auth_provider = ?';
      $params[] = $filters['auth_provider'];
    }

    if (!empty($filters['activity'])) {
      if ($filters['activity'] === 'active_recently') {
        $whereParts[] = 'managed_users.last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
      } elseif ($filters['activity'] === 'inactive_7d') {
        $whereParts[] = 'managed_users.last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY)';
      } elseif ($filters['activity'] === 'inactive_30d') {
        $whereParts[] = 'managed_users.last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)';
      } elseif ($filters['activity'] === 'no_attempts') {
        $whereParts[] = 'managed_users.solved_tests = 0';
      }
    }

    $where = empty($whereParts) ? '' : ' AND ' . implode(' AND ', $whereParts);

    return [$where, $params];
  }

  private function getManagedUsersBaseSql(): string
  {
    return "SELECT
              u.id,
              u.first_name,
              u.last_name,
              u.email,
              u.role,
              u.is_active,
              u.is_verified,
              u.auth_provider,
              u.created_at,
              u.updated_at,
              event_stats.last_login,
              GREATEST(
                COALESCE(event_stats.last_activity, '1000-01-01 00:00:00'),
                COALESCE(attempt_stats.last_attempt, '1000-01-01 00:00:00'),
                u.created_at
              ) as last_activity,
              COALESCE(attempt_stats.completed_tests, 0) as solved_tests
            FROM users u
            LEFT JOIN (
              SELECT
                user_id,
                MAX(event_date) as last_activity,
                MAX(CASE WHEN event_type = 'login' THEN event_date END) as last_login
              FROM analytics_events
              WHERE user_id IS NOT NULL
              GROUP BY user_id
            ) event_stats ON event_stats.user_id = u.id
            LEFT JOIN (
              SELECT
                user_id,
                MAX(COALESCE(completed_at, started_at)) as last_attempt,
                SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed_tests
              FROM attempts
              WHERE user_id IS NOT NULL
              GROUP BY user_id
            ) attempt_stats ON attempt_stats.user_id = u.id
            WHERE u.deleted_at IS NULL";
  }
}
