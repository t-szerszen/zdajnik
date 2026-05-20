<?php

namespace App\Services;

use App\Core\Database;

/**
 * Serwis logów audytowych administracji.
 *
 * @version 1.0.0
 * @package App\Services
 */
class AdminAuditService
{
  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Zapisuje wpis audytowy.
   *
   * @param array<string, mixed>|scalar|null $oldValue
   * @param array<string, mixed>|scalar|null $newValue
   */
  public function logAction(
    int $adminUserId,
    string $action,
    string $targetType,
    ?int $targetId = null,
    array|string|int|float|bool|null $oldValue = null,
    array|string|int|float|bool|null $newValue = null
  ): bool {
    $sql = "INSERT INTO admin_audit_logs
            (admin_user_id, action, target_type, target_id, old_value, new_value, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    return $this->db->execute($sql, [
      $adminUserId,
      $action,
      $targetType,
      $targetId,
      $this->normalizeValue($oldValue),
      $this->normalizeValue($newValue),
      $this->getIpAddress(),
      $this->getUserAgent(),
    ]);
  }

  /**
   * Pobiera ostatnie akcje adminów z filtrami.
   *
   * @param array<string, mixed> $filters
   * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int, totalPages: int, admins: array<int, array<string, mixed>>}
   */
  public function searchLogs(array $filters, int $page, int $perPage): array
  {
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $offset = ($page - 1) * $perPage;

    [$whereSql, $params] = $this->buildFilterSql($filters);

    $sql = "SELECT
              l.*,
              u.first_name,
              u.last_name,
              u.email
            FROM admin_audit_logs l
            JOIN users u ON l.admin_user_id = u.id
            WHERE 1=1 $whereSql
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

    $items = $this->db->fetchAll($sql, $params);

    $countSql = "SELECT COUNT(*) as total
                 FROM admin_audit_logs l
                 JOIN users u ON l.admin_user_id = u.id
                 WHERE 1=1 $whereSql";
    $countResult = $this->db->fetch($countSql, $params);
    $total = (int) ($countResult['total'] ?? 0);

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'perPage' => $perPage,
      'totalPages' => max(1, (int) ceil($total / $perPage)),
      'admins' => $this->getAdmins(),
    ];
  }

  /**
   * Log roli użytkownika.
   */
  public function logRoleChange(int $adminUserId, int $targetUserId, array $oldValue, array $newValue): bool
  {
    return $this->logAction($adminUserId, 'change_user_role', 'user', $targetUserId, $oldValue, $newValue);
  }

  /**
   * Log blokady/odblokowania.
   */
  public function logUserStatusChange(int $adminUserId, int $targetUserId, array $oldValue, array $newValue): bool
  {
    return $this->logAction($adminUserId, 'change_user_status', 'user', $targetUserId, $oldValue, $newValue);
  }

  public function logExamCreated(int $adminUserId, int $targetId, array|string|int|float|bool|null $newValue = null): bool
  {
    return $this->logAction($adminUserId, 'create_exam', 'exam', $targetId, null, $newValue);
  }

  public function logExamUpdated(int $adminUserId, int $targetId, array|string|int|float|bool|null $oldValue = null, array|string|int|float|bool|null $newValue = null): bool
  {
    return $this->logAction($adminUserId, 'update_exam', 'exam', $targetId, $oldValue, $newValue);
  }

  public function logQuestionCreated(int $adminUserId, int $targetId, array|string|int|float|bool|null $newValue = null): bool
  {
    return $this->logAction($adminUserId, 'create_question', 'question', $targetId, null, $newValue);
  }

  public function logQuestionUpdated(int $adminUserId, int $targetId, array|string|int|float|bool|null $oldValue = null, array|string|int|float|bool|null $newValue = null): bool
  {
    return $this->logAction($adminUserId, 'update_question', 'question', $targetId, $oldValue, $newValue);
  }

  public function logQuestionDeactivated(int $adminUserId, int $targetId, array|string|int|float|bool|null $oldValue = null, array|string|int|float|bool|null $newValue = null): bool
  {
    return $this->logAction($adminUserId, 'deactivate_question', 'question', $targetId, $oldValue, $newValue);
  }

  /**
   * Lista adminów do filtra.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getAdmins(): array
  {
    $sql = "SELECT id, first_name, last_name, email
            FROM users
            WHERE deleted_at IS NULL AND role = 'admin'
            ORDER BY first_name ASC, email ASC";

    return $this->db->fetchAll($sql);
  }

  /**
   * Buduje filtry dla listy.
   *
   * @param array<string, mixed> $filters
   * @return array{0:string,1:array<int, mixed>}
   */
  private function buildFilterSql(array $filters): array
  {
    $where = '';
    $params = [];

    if (!empty($filters['admin_user_id']) && ctype_digit((string) $filters['admin_user_id'])) {
      $where .= ' AND l.admin_user_id = ?';
      $params[] = (int) $filters['admin_user_id'];
    }

    if (!empty($filters['action'])) {
      $where .= ' AND l.action = ?';
      $params[] = (string) $filters['action'];
    }

    if (!empty($filters['date_from'])) {
      $where .= ' AND DATE(l.created_at) >= ?';
      $params[] = (string) $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
      $where .= ' AND DATE(l.created_at) <= ?';
      $params[] = (string) $filters['date_to'];
    }

    return [$where, $params];
  }

  /**
   * Ujednolica wartości do JSON/text.
   */
  private function normalizeValue(array|string|int|float|bool|null $value): ?string
  {
    if ($value === null) {
      return null;
    }

    if (is_array($value)) {
      return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (is_bool($value)) {
      return $value ? '1' : '0';
    }

    return (string) $value;
  }

  private function getIpAddress(): ?string
  {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    return $ip ? substr((string) $ip, 0, 45) : null;
  }

  private function getUserAgent(): ?string
  {
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if (!$agent) {
      return null;
    }

    return substr((string) $agent, 0, 255);
  }
}
