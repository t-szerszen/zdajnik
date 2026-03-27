<?php

namespace App\Models;

use App\Core\Database;

/**
 * Model Pytania (Question) - Wersja 2.0 (Wersjonowanie)
 * Obsługuje nową strukturę: questions -> question_versions -> answers
 */
class Question
{
  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  private function getBaseSelectionSql(): string
  {
    return "SELECT 
                    q.id as question_id, 
                    qv.id as question_version_id, 
                    q.topic_id, 
                    qv.question_text, 
                    qv.image_path, 
                    qv.explanation 
                FROM questions q
                JOIN question_versions qv ON q.id = qv.question_id";
  }

  // --- POPRAWIONE METODY ---

  public function getQuestions(array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders)
      return [];

    $sql = $this->getBaseSelectionSql() . "
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND q.topic_id IN ($placeholders) 
                AND q.exam_type_id = ? 
                ORDER BY RAND() LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge($subjectIds, [$examTypeId]));
  }

  public function getUndiscoveredQuestions(int $userId, array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders)
      return [];

    // JOIN dodany PRZED WHERE
    $sql = $this->getBaseSelectionSql() . "
                LEFT JOIN user_progress up ON q.id = up.question_id AND up.user_id = ? 
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND up.question_id IS NULL
                AND q.topic_id IN ($placeholders) 
                AND q.exam_type_id = ?
                ORDER BY RAND() LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge([$userId], $subjectIds, [$examTypeId]));
  }

  public function getLowerAccuracyQuestions(int $userId, array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders)
      return [];

    $sql = $this->getBaseSelectionSql() . "
                INNER JOIN user_progress up ON q.id = up.question_id AND up.user_id = ?
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND q.topic_id IN ($placeholders) AND q.exam_type_id = ?
                AND up.attempts_count > 0
                AND (up.correct_count * 100.0 / up.attempts_count) < 60
                ORDER BY (up.correct_count * 100.0 / up.attempts_count) ASC, RAND() 
                LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge([$userId], $subjectIds, [$examTypeId]));
  }

  public function getQuestionsRepeatedAtTheLatest(int $userId, array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders)
      return [];

    $sql = $this->getBaseSelectionSql() . "
                INNER JOIN user_progress up ON q.id = up.question_id AND up.user_id = ?
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND q.topic_id IN ($placeholders) AND q.exam_type_id = ?
                ORDER BY up.updated_at ASC LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge([$userId], $subjectIds, [$examTypeId]));
  }

  public function getLastMistakes(int $userId, array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders)
      return [];

    $sql = $this->getBaseSelectionSql() . "
                INNER JOIN user_progress up ON q.id = up.question_id AND up.user_id = ?
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND q.topic_id IN ($placeholders) 
                AND q.exam_type_id = ? 
                AND up.last_result = 0
                ORDER BY RAND() LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge([$userId], $subjectIds, [$examTypeId]));
  }

  public function getSavedQuestions(int $userId, array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders)
      return [];

    $sql = $this->getBaseSelectionSql() . "
                INNER JOIN saved_questions sq ON q.id = sq.question_id AND sq.user_id = ?
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND q.topic_id IN ($placeholders) AND q.exam_type_id = ?
                ORDER BY RAND() LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge([$userId], $subjectIds, [$examTypeId]));
  }

  public function getPoolProgress(int $userId, array $subjectIds, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders)
      return ['total' => 0, 'answered' => 0];

    // Total in pool
    $sqlTotal = "SELECT COUNT(*) as total FROM questions q 
                 WHERE q.topic_id IN ($placeholders) AND q.exam_type_id = ? 
                 AND q.is_active = 1 AND q.deleted_at IS NULL";
    $total = $this->db->fetch($sqlTotal, array_merge($subjectIds, [$examTypeId]));

    // Answered in pool
    $sqlAnswered = "SELECT COUNT(DISTINCT up.question_id) as answered FROM user_progress up
                    JOIN questions q ON up.question_id = q.id
                    WHERE up.user_id = ? AND q.topic_id IN ($placeholders) AND q.exam_type_id = ?
                    AND q.is_active = 1 AND q.deleted_at IS NULL
                    AND up.attempts_count > 0";
    $answered = $this->db->fetch($sqlAnswered, array_merge([$userId], $subjectIds, [$examTypeId]));

    return [
      'total' => (int) ($total['total'] ?? 0),
      'answered' => (int) ($answered['answered'] ?? 0)
    ];
  }

  public function getQuestionIdByVersionId(int $qvId): int|false
  {
    $sql = "SELECT question_id FROM question_versions WHERE id = ? LIMIT 1";
    $result = $this->db->fetch($sql, [$qvId]);
    return $result ? (int) $result['question_id'] : false; // Zwracamy int, nie tablicę!
  }

  public function getQuestionById(int $id): array|false
  {
    // Tutaj też musimy dodać WHERE ręcznie
    $sql = $this->getBaseSelectionSql() . " WHERE q.is_active = 1 AND qv.is_active = 1 AND q.id = ? LIMIT 1";
    return $this->db->fetch($sql, [$id]);
  }

  public function getExamTypeIdByCode(string $code): ?int
  {
    $sql = 'SELECT id FROM exam_types WHERE code = ? AND is_active = 1 LIMIT 1';
    $result = $this->db->fetch($sql, [$code]);
    return $result['id'] ?? null;
  }

  public function getTopicIdsByExamType(int $examTypeId): array
  {
    $sql = 'SELECT DISTINCT topic_id FROM questions WHERE exam_type_id = ? AND is_active = 1';
    $results = $this->db->fetchAll($sql, [$examTypeId]);
    return array_column($results, 'topic_id');
  }

  private function buildInClausePlaceholders(array $ids): ?string
  {
    if (empty($ids))
      return null;
    return implode(',', array_fill(0, count($ids), '?'));
  }

  /**
   * Zwraca sql $where oraz $params dla wyszukiwarki.
   */
  private function buildSearchWhereClause(array $filters): array
  {
    $where = "q.is_active = 1 AND qv.is_active = 1 AND q.deleted_at IS NULL ";
    $params = [];

    if (!empty($filters['exam_id'])) {
      $where .= " AND q.exam_type_id = ? ";
      $params[] = (int) $filters['exam_id'];
    }

    if (!empty($filters['topic_id'])) {
      $where .= " AND q.topic_id = ? ";
      $params[] = (int) $filters['topic_id'];
    }

    if (!empty($filters['query'])) {
      $query = trim($filters['query']);

      if (is_numeric($query)) {
        $where .= " AND q.id = ? ";
        $params[] = (int) $query;
      } elseif (mb_strlen($query) >= 3) {
        $where .= " AND (qv.question_text LIKE ? OR qv.explanation LIKE ?) ";
        $params[] = "%$query%";
        $params[] = "%$query%";
      }
    }

    return [$where, $params];
  }

  /**
   * Zlicza wszystkie pytania spełniające kryteria. (Do paginacji)
   */
  public function countSearchQuestions(array $filters): int
  {
    [$where, $params] = $this->buildSearchWhereClause($filters);

    $sql = "SELECT COUNT(*) as total
            FROM questions q
            JOIN question_versions qv ON q.id = qv.question_id
            WHERE $where";

    $result = $this->db->fetch($sql, $params);
    return (int) ($result['total'] ?? 0);
  }

  /**
   * Wyszukuje pytania na podstawie filtrów z paginacją.
   */
  public function searchQuestions(array $filters, int $page = 1, int $limit = 25): array
  {
    [$where, $params] = $this->buildSearchWhereClause($filters);

    $sql = "SELECT 
                q.id as question_id, 
                qv.id as question_version_id, 
                q.topic_id, 
                qv.question_text, 
                qv.image_path, 
                qv.explanation,
                et.code as exam_code,
                t.name as topic_name
            FROM questions q
            JOIN question_versions qv ON q.id = qv.question_id
            JOIN exam_types et ON q.exam_type_id = et.id
            JOIN topics t ON q.topic_id = t.id
            WHERE $where ";

    $sql .= " ORDER BY q.id DESC ";

    // Paginacja
    $offset = ($page - 1) * $limit;
    if ($offset < 0)
      $offset = 0;

    $sql .= " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

    return $this->db->fetchAll($sql, $params);
  }
}
