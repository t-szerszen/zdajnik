<?php

namespace App\Services;

use App\Core\Database;

/**
 * Serwis zarządzania egzaminami w panelu admina.
 *
 * Wykorzystuje istniejącą tabelę `exam_types`, bez tworzenia nowej struktury.
 * Zapewnia listowanie, szczegóły, relację egzamin -> pytania oraz operacje CRUD.
 *
 * @version 1.0.0
 * @package App\Services
 */
class AdminExamService
{
  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Pobiera egzaminy z filtrami, sortowaniem i paginacją.
   *
   * @param array<string, mixed> $filters
   * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int, totalPages: int}
   */
  public function searchExams(array $filters, int $page, int $perPage, string $sortBy, string $sortDir): array
  {
    $allowedSort = [
      'id' => 'et.id',
      'code' => 'et.code',
      'name' => 'et.name',
      'created_at' => 'et.created_at',
    ];

    $sortColumn = $allowedSort[$sortBy] ?? 'et.id';
    $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $offset = ($page - 1) * $perPage;

    [$whereSql, $params] = $this->buildFilterSql($filters);

    $sql = "SELECT
              et.id,
              et.code,
              et.name,
              et.description,
              et.is_active,
              et.created_at,
              et.updated_at,
              COALESCE(question_stats.questions_count, 0) as questions_count,
              COALESCE(question_stats.active_questions_count, 0) as active_questions_count,
              COALESCE(question_stats.inactive_questions_count, 0) as inactive_questions_count
            FROM exam_types et
            LEFT JOIN (
              SELECT
                exam_type_id,
                COUNT(*) as questions_count,
                SUM(CASE WHEN is_active = 1 AND deleted_at IS NULL THEN 1 ELSE 0 END) as active_questions_count,
                SUM(CASE WHEN is_active = 0 AND deleted_at IS NULL THEN 1 ELSE 0 END) as inactive_questions_count
              FROM questions
              WHERE deleted_at IS NULL
              GROUP BY exam_type_id
            ) question_stats ON question_stats.exam_type_id = et.id
            WHERE et.deleted_at IS NULL $whereSql
            ORDER BY $sortColumn $sortDir, et.id DESC
            LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

    $items = $this->db->fetchAll($sql, $params);

    $countSql = "SELECT COUNT(*) as total
                 FROM exam_types et
                 WHERE et.deleted_at IS NULL $whereSql";
    $countResult = $this->db->fetch($countSql, $params);
    $total = (int) ($countResult['total'] ?? 0);

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'perPage' => $perPage,
      'totalPages' => max(1, (int) ceil($total / $perPage)),
    ];
  }

  /**
   * Pobiera pojedynczy egzamin.
   */
  public function getExamById(int $examId): array|false
  {
    $sql = "SELECT
              et.id,
              et.code,
              et.name,
              et.description,
              et.is_active,
              et.created_at,
              et.updated_at,
              COALESCE(question_stats.questions_count, 0) as questions_count,
              COALESCE(question_stats.active_questions_count, 0) as active_questions_count,
              COALESCE(question_stats.inactive_questions_count, 0) as inactive_questions_count
            FROM exam_types et
            LEFT JOIN (
              SELECT
                exam_type_id,
                COUNT(*) as questions_count,
                SUM(CASE WHEN is_active = 1 AND deleted_at IS NULL THEN 1 ELSE 0 END) as active_questions_count,
                SUM(CASE WHEN is_active = 0 AND deleted_at IS NULL THEN 1 ELSE 0 END) as inactive_questions_count
              FROM questions
              WHERE deleted_at IS NULL
              GROUP BY exam_type_id
            ) question_stats ON question_stats.exam_type_id = et.id
            WHERE et.id = ? AND et.deleted_at IS NULL
            LIMIT 1";

    return $this->db->fetch($sql, [$examId]);
  }

  /**
   * Pobiera listę pytań przypisanych do egzaminu.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getExamQuestions(int $examId, int $limit = 200): array
  {
    $sql = "SELECT
              q.id,
              q.topic_id,
              q.is_active,
              q.created_at,
              q.updated_at,
              t.name as topic_name,
              qv.id as question_version_id,
              qv.question_text,
              qv.created_at as version_created_at,
              qv.updated_at as version_updated_at,
              qv.is_active as version_is_active,
              COALESCE(version_stats.version_count, 0) as version_count
            FROM questions q
            LEFT JOIN question_versions qv
              ON qv.question_id = q.id
             AND qv.is_active = 1
             AND qv.deleted_at IS NULL
            LEFT JOIN topics t ON t.id = q.topic_id
            LEFT JOIN (
              SELECT question_id, COUNT(*) as version_count
              FROM question_versions
              WHERE deleted_at IS NULL
              GROUP BY question_id
            ) version_stats ON version_stats.question_id = q.id
            WHERE q.exam_type_id = ? AND q.deleted_at IS NULL
            ORDER BY q.updated_at DESC, q.id DESC
            LIMIT " . max(1, $limit);

    return $this->db->fetchAll($sql, [$examId]);
  }

  /**
   * Zwraca informację o unikalności kodu egzaminu.
   */
  public function isCodeAvailable(string $code, ?int $ignoreId = null): bool
  {
    $sql = "SELECT id
            FROM exam_types
            WHERE code = ?"
            . ($ignoreId !== null ? " AND id <> ?" : '') . "
            LIMIT 1";

    $params = [$code];
    if ($ignoreId !== null) {
      $params[] = $ignoreId;
    }

    return $this->db->fetch($sql, $params) === false;
  }

  /**
   * Tworzy egzamin.
   */
  public function createExam(string $code, string $name, ?string $description, bool $isActive): int|false
  {
    $sql = "INSERT INTO exam_types (code, name, description, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())";

    if (!$this->db->execute($sql, [$code, $name, $description, $isActive ? 1 : 0])) {
      return false;
    }

    $lastInsertId = $this->db->lastInsertId();
    return $lastInsertId ?? false;
  }

  /**
   * Aktualizuje egzamin.
   */
  public function updateExam(int $examId, string $code, string $name, ?string $description, bool $isActive): bool
  {
    $sql = "UPDATE exam_types
            SET code = ?, name = ?, description = ?, is_active = ?, updated_at = NOW()
            WHERE id = ? AND deleted_at IS NULL";

    return $this->db->execute($sql, [$code, $name, $description, $isActive ? 1 : 0, $examId]);
  }

  /**
   * Dezaktywuje egzamin.
   */
  public function deactivateExam(int $examId): bool
  {
    $sql = "UPDATE exam_types
            SET is_active = 0, updated_at = NOW()
            WHERE id = ? AND deleted_at IS NULL";

    return $this->db->execute($sql, [$examId]);
  }

  public function activateExam(int $examId): bool
  {
    $sql = "UPDATE exam_types
            SET is_active = 1, updated_at = NOW()
            WHERE id = ? AND deleted_at IS NULL";

    return $this->db->execute($sql, [$examId]);
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function getExamTopics(int $examId): array
  {
    return $this->db->fetchAll(
      "SELECT
         t.id,
         t.name,
         t.description,
         t.is_active,
         COUNT(q.id) as questions_count,
         SUM(CASE WHEN q.is_active = 1 THEN 1 ELSE 0 END) as active_questions_count
       FROM topics t
       LEFT JOIN questions q ON q.topic_id = t.id AND q.deleted_at IS NULL
       WHERE t.exam_type_id = ? AND t.deleted_at IS NULL
       GROUP BY t.id, t.name, t.description, t.is_active
       ORDER BY t.name ASC",
      [$examId]
    );
  }

  /**
   * Buduje SQL dla filtrów listy egzaminów.
   *
   * @param array<string, mixed> $filters
   * @return array{0:string,1:array<int, mixed>}
   */
  private function buildFilterSql(array $filters): array
  {
    $where = '';
    $params = [];

    if (!empty($filters['query'])) {
      $query = trim((string) $filters['query']);
      $like = '%' . $query . '%';

      if (ctype_digit($query)) {
        $where .= ' AND (et.id = ? OR et.code LIKE ? OR et.name LIKE ? OR et.description LIKE ?)';
        $params[] = (int) $query;
      } else {
        $where .= ' AND (et.code LIKE ? OR et.name LIKE ? OR et.description LIKE ?)';
      }

      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
    }

    if (!empty($filters['status'])) {
      $status = (string) $filters['status'];
      if ($status === 'active') {
        $where .= ' AND et.is_active = 1';
      } elseif ($status === 'inactive') {
        $where .= ' AND et.is_active = 0';
      }
    }

    return [$where, $params];
  }
}
