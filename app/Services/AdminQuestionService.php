<?php

namespace App\Services;

use App\Core\Database;

/**
 * Serwis zarządzania pytaniami w panelu admina.
 *
 * Opakowuje istniejący model wersjonowania:
 * - `questions` jako rekord bazowy,
 * - `question_versions` jako historia treści i obrazków,
 * - `answers` jako odpowiedzi przypięte do konkretnej wersji.
 *
 * @version 1.0.0
 * @package App\Services
 */
class AdminQuestionService
{
  private Database $db;
  private AdminAuditService $auditService;

  public function __construct()
  {
    $this->db = Database::getInstance();
    $this->auditService = new AdminAuditService();
  }

  /**
   * Pobiera listę pytań z filtrami, sortowaniem i paginacją.
   *
   * @param array<string, mixed> $filters
   * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int, totalPages: int}
   */
  public function searchQuestions(array $filters, int $page, int $perPage, string $sortBy, string $sortDir): array
  {
    $allowedSort = [
      'id' => 'q.id',
      'exam' => 'et.code',
      'created_at' => 'q.created_at',
      'updated_at' => 'q.updated_at',
    ];

    $sortColumn = $allowedSort[$sortBy] ?? 'q.id';
    $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $offset = ($page - 1) * $perPage;

    [$whereSql, $params] = $this->buildFilterSql($filters);

    $sql = "SELECT
              q.id,
              q.exam_type_id,
              q.topic_id,
              q.is_active,
              q.created_at as question_created_at,
              q.updated_at as question_updated_at,
              et.code as exam_code,
              et.name as exam_name,
              t.name as topic_name,
              qv.id as version_id,
              qv.version_num,
              qv.question_text,
              qv.image_name,
              qv.explanation,
              qv.created_at as version_created_at,
              qv.updated_at as version_updated_at,
              qv.is_active as version_is_active,
              COALESCE(answer_stats.answers_count, 0) as answers_count,
              COALESCE(answer_stats.correct_answers_count, 0) as correct_answers_count
            FROM questions q
            JOIN exam_types et ON q.exam_type_id = et.id
            LEFT JOIN topics t ON q.topic_id = t.id
            LEFT JOIN (
              SELECT qv1.question_id, qv1.id, qv1.version_num
              FROM question_versions qv1
              INNER JOIN (
                SELECT question_id, MAX(version_num) as version_num
                FROM question_versions
                WHERE deleted_at IS NULL
                GROUP BY question_id
              ) latest ON latest.question_id = qv1.question_id AND latest.version_num = qv1.version_num
              WHERE qv1.deleted_at IS NULL
            ) latest_version ON latest_version.question_id = q.id
            LEFT JOIN question_versions qv ON qv.id = latest_version.id
            LEFT JOIN (
              SELECT
                question_version_id,
                COUNT(*) as answers_count,
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers_count
              FROM answers
              WHERE deleted_at IS NULL
              GROUP BY question_version_id
            ) answer_stats ON answer_stats.question_version_id = qv.id
            WHERE q.deleted_at IS NULL $whereSql
            ORDER BY $sortColumn $sortDir, q.id DESC
            LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

    $items = $this->db->fetchAll($sql, $params);

    $countSql = "SELECT COUNT(*) as total
                 FROM questions q
                 JOIN exam_types et ON q.exam_type_id = et.id
                 LEFT JOIN topics t ON q.topic_id = t.id
                 LEFT JOIN (
                   SELECT qv1.question_id, qv1.id, qv1.version_num
                   FROM question_versions qv1
                   INNER JOIN (
                     SELECT question_id, MAX(version_num) as version_num
                     FROM question_versions
                     WHERE deleted_at IS NULL
                     GROUP BY question_id
                   ) latest ON latest.question_id = qv1.question_id AND latest.version_num = qv1.version_num
                   WHERE qv1.deleted_at IS NULL
                 ) latest_version ON latest_version.question_id = q.id
                 LEFT JOIN question_versions qv ON qv.id = latest_version.id
                 WHERE q.deleted_at IS NULL $whereSql";

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
   * Pobiera dane jednego pytania wraz z wersją aktywną i historią.
   *
   * @return array<string, mixed>|false
   */
  public function getQuestionById(int $questionId): array|false
  {
    $questionSql = "SELECT
                      q.id,
                      q.exam_type_id,
                      q.topic_id,
                      q.is_active,
                      q.created_at as question_created_at,
                      q.updated_at as question_updated_at,
                      et.code as exam_code,
                      et.name as exam_name,
                      t.name as topic_name
                    FROM questions q
                    JOIN exam_types et ON q.exam_type_id = et.id
                    LEFT JOIN topics t ON q.topic_id = t.id
                    WHERE q.id = ? AND q.deleted_at IS NULL
                    LIMIT 1";

    $question = $this->db->fetch($questionSql, [$questionId]);
    if (!$question) {
      return false;
    }

    $currentVersion = $this->getLatestVersionByQuestionId($questionId);
    if (!is_array($currentVersion)) {
      $currentVersion = [];
    }
    $question['current_version'] = $currentVersion;
    $question['answers'] = !empty($currentVersion['id']) ? $this->getAnswersByVersionId((int) $currentVersion['id']) : [];
    $question['versions'] = $this->getVersionHistory($questionId);

    return $question;
  }

  /**
   * Pobiera grupę egzaminów z tematami do formularza.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getExamTopicGroups(): array
  {
    $sql = "SELECT
              et.id as exam_id,
              et.code,
              et.name as exam_name,
              et.is_active as exam_is_active,
              t.id as topic_id,
              t.name as topic_name,
              t.is_active as topic_is_active
            FROM exam_types et
            LEFT JOIN topics t ON t.exam_type_id = et.id AND t.deleted_at IS NULL
            WHERE et.deleted_at IS NULL
            ORDER BY et.code ASC, t.name ASC";

    $rows = $this->db->fetchAll($sql);
    $groups = [];

    foreach ($rows as $row) {
      $examId = (int) $row['exam_id'];

      if (!isset($groups[$examId])) {
        $groups[$examId] = [
          'exam_id' => $examId,
          'code' => $row['code'],
          'name' => $row['exam_name'],
          'is_active' => (int) $row['exam_is_active'],
          'topics' => [],
        ];
      }

      if (!empty($row['topic_id'])) {
        $groups[$examId]['topics'][] = [
          'id' => (int) $row['topic_id'],
          'name' => $row['topic_name'],
          'is_active' => (int) $row['topic_is_active'],
        ];
      }
    }

    return array_values($groups);
  }

  /**
   * Tworzy nowe pytanie z pierwszą wersją i odpowiedziami.
   *
   * @param array<string, mixed> $input
   * @param array<string, mixed>|null $file
   * @return array{success: bool, question_id?: int, errors?: array<string, string>, data?: array<string, mixed>}
   */
  public function createQuestion(array $input, ?array $file, int $adminUserId): array
  {
    [$data, $answers, $errors] = $this->validatePayload($input);
    if (!empty($errors)) {
      return ['success' => false, 'errors' => $errors, 'data' => $data];
    }

    if (!$this->isValidExamTopicPair((int) $data['exam_type_id'], (int) $data['topic_id'])) {
      return ['success' => false, 'errors' => ['topic_id' => 'Wybrany temat nie należy do wskazanego egzaminu.'], 'data' => $data];
    }

    $imageResult = $this->processImageUpload($file, $data['exam_code'], null);
    if (!$imageResult['success']) {
      return ['success' => false, 'errors' => ['image' => $imageResult['error']], 'data' => $data];
    }

    $db = $this->db;
    if (!$db->beginTransaction()) {
      return ['success' => false, 'errors' => ['general' => 'Nie udało się rozpocząć transakcji.'], 'data' => $data];
    }

    try {
      $questionSql = "INSERT INTO questions (exam_type_id, topic_id, is_active, created_at, updated_at)
                      VALUES (?, ?, ?, NOW(), NOW())";
      if (!$db->execute($questionSql, [
        (int) $data['exam_type_id'],
        (int) $data['topic_id'],
        (int) $data['is_active'],
      ])) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się utworzyć pytania.'], 'data' => $data];
      }

      $questionId = $db->lastInsertId();
      if (!$questionId) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się pobrać ID pytania.'], 'data' => $data];
      }

      $versionId = $this->insertQuestionVersion((int) $questionId, 1, $data['question_text'], $data['explanation'], $imageResult['path'], (int) $data['is_active']);
      if (!$versionId) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się utworzyć wersji pytania.'], 'data' => $data];
      }

      if (!$this->insertAnswers((int) $versionId, $answers)) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się zapisać odpowiedzi.'], 'data' => $data];
      }

      if (!$this->auditService->logQuestionCreated($adminUserId, (int) $questionId, $this->snapshotForAudit($data, $answers, $imageResult['path'] ?? null))) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się zapisać wpisu audytowego.'], 'data' => $data];
      }

      $db->commit();
      return ['success' => true, 'question_id' => (int) $questionId];
    } catch (\Throwable $e) {
      $db->rollBack();
      error_log('[AdminQuestionService] createQuestion: ' . $e->getMessage());
      return ['success' => false, 'errors' => ['general' => 'Wystąpił nieoczekiwany błąd podczas zapisu.'], 'data' => $data];
    }
  }

  /**
   * Aktualizuje pytanie poprzez utworzenie nowej wersji.
   *
   * @param array<string, mixed> $input
   * @param array<string, mixed>|null $file
   * @return array{success: bool, errors?: array<string, string>, data?: array<string, mixed>}
   */
  public function updateQuestion(int $questionId, array $input, ?array $file, int $adminUserId): array
  {
    $current = $this->getQuestionById($questionId);
    if (!$current) {
      return ['success' => false, 'errors' => ['general' => 'Pytanie nie istnieje.']];
    }

    [$data, $answers, $errors] = $this->validatePayload($input, $current);
    if (!empty($errors)) {
      return ['success' => false, 'errors' => $errors, 'data' => $data];
    }

    if (!$this->isValidExamTopicPair((int) $data['exam_type_id'], (int) $data['topic_id'])) {
      return ['success' => false, 'errors' => ['topic_id' => 'Wybrany temat nie należy do wskazanego egzaminu.'], 'data' => $data];
    }

    $currentVersion = $current['current_version'] ?? null;
    $currentImage = $currentVersion['image_name'] ?? null;
    $keepImage = empty($data['remove_image']) && empty($file);
    $newImagePath = $currentImage;

    if (!empty($data['remove_image'])) {
      $newImagePath = null;
    } elseif (!empty($file)) {
      $imageResult = $this->processImageUpload($file, (string) ($current['exam_code'] ?? 'exam'), $questionId);
      if (!$imageResult['success']) {
        return ['success' => false, 'errors' => ['image' => $imageResult['error']], 'data' => $data];
      }
      $newImagePath = $imageResult['path'];
    } elseif (!$keepImage) {
      $newImagePath = null;
    }

    $normalizedCurrent = $this->normalizeQuestionSnapshot($current);
    $normalizedNew = $this->normalizeSubmittedSnapshot($data, $answers, $newImagePath);

    if ($normalizedCurrent === $normalizedNew) {
      return ['success' => false, 'errors' => ['general' => 'Nie wprowadzono żadnych zmian.'], 'data' => $data];
    }

    $db = $this->db;
    if (!$db->beginTransaction()) {
      return ['success' => false, 'errors' => ['general' => 'Nie udało się rozpocząć transakcji.'], 'data' => $data];
    }

    try {
      $updateQuestionSql = "UPDATE questions
                            SET exam_type_id = ?, topic_id = ?, is_active = ?, updated_at = NOW()
                            WHERE id = ? AND deleted_at IS NULL";
      if (!$db->execute($updateQuestionSql, [
        (int) $data['exam_type_id'],
        (int) $data['topic_id'],
        (int) $data['is_active'],
        $questionId,
      ])) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się zaktualizować pytania.'], 'data' => $data];
      }

      $db->execute("UPDATE question_versions SET is_active = 0 WHERE question_id = ?", [$questionId]);

      $nextVersion = $this->getNextVersionNumber($questionId);
      $versionId = $this->insertQuestionVersion($questionId, $nextVersion, $data['question_text'], $data['explanation'], $newImagePath, (int) $data['is_active']);
      if (!$versionId) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się utworzyć nowej wersji pytania.'], 'data' => $data];
      }

      if (!$this->insertAnswers((int) $versionId, $answers)) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się zapisać odpowiedzi.'], 'data' => $data];
      }

      if (!$this->auditService->logQuestionUpdated($adminUserId, $questionId, $normalizedCurrent, $normalizedNew)) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się zapisać wpisu audytowego.'], 'data' => $data];
      }

      if (
        (int) ($current['is_active'] ?? 1) !== (int) $data['is_active']
        && !(
          (int) $data['is_active'] === 1
            ? $this->auditService->logQuestionActivated($adminUserId, $questionId, ['is_active' => 0], ['is_active' => 1])
            : $this->auditService->logQuestionDeactivated($adminUserId, $questionId, ['is_active' => 1], ['is_active' => 0])
        )
      ) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się zapisać audytu zmiany statusu.'], 'data' => $data];
      }

      $db->commit();
      return ['success' => true];
    } catch (\Throwable $e) {
      $db->rollBack();
      error_log('[AdminQuestionService] updateQuestion: ' . $e->getMessage());
      return ['success' => false, 'errors' => ['general' => 'Wystąpił nieoczekiwany błąd podczas zapisu.'], 'data' => $data];
    }
  }

  /**
   * Dezaktywuje pytanie i jego bieżące wersje.
   */
  public function deactivateQuestion(int $questionId, int $adminUserId): array
  {
    $current = $this->getQuestionById($questionId);
    if (!$current) {
      return ['success' => false, 'errors' => ['general' => 'Pytanie nie istnieje.']];
    }

    if ((int) ($current['is_active'] ?? 1) === 0) {
      return ['success' => false, 'errors' => ['general' => 'Pytanie jest już nieaktywne.']];
    }

    $db = $this->db;
    if (!$db->beginTransaction()) {
      return ['success' => false, 'errors' => ['general' => 'Nie udało się rozpocząć transakcji.']];
    }

    try {
      if (!$db->execute("UPDATE questions SET is_active = 0, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL", [$questionId])) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się dezaktywować pytania.']];
      }

      $versions = $this->getVersionHistory($questionId);
      $versionIds = array_map(static fn(array $version): int => (int) $version['id'], $versions);

      if (!empty($versionIds)) {
        $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
        if (
          !$db->execute("UPDATE question_versions SET is_active = 0, updated_at = NOW() WHERE id IN ($placeholders)", $versionIds)
          || !$db->execute("UPDATE answers SET is_active = 0, updated_at = NOW() WHERE question_version_id IN ($placeholders)", $versionIds)
        ) {
          $db->rollBack();
          return ['success' => false, 'errors' => ['general' => 'Nie udało się zsynchronizować wersji i odpowiedzi pytania.']];
        }
      }

      $oldValue = ['is_active' => 1];
      $newValue = ['is_active' => 0];
      if (!$this->auditService->logQuestionDeactivated($adminUserId, $questionId, $oldValue, $newValue)) {
        $db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się zapisać wpisu audytowego.']];
      }

      $db->commit();
      return ['success' => true];
    } catch (\Throwable $e) {
      $db->rollBack();
      error_log('[AdminQuestionService] deactivateQuestion: ' . $e->getMessage());
      return ['success' => false, 'errors' => ['general' => 'Wystąpił nieoczekiwany błąd podczas dezaktywacji.']];
    }
  }

  /**
   * Aktywuje pytanie, jego najnowszą wersję i odpowiedzi tej wersji.
   */
  public function activateQuestion(int $questionId, int $adminUserId): array
  {
    $current = $this->getQuestionById($questionId);
    if (!$current) {
      return ['success' => false, 'errors' => ['general' => 'Pytanie nie istnieje.']];
    }

    if ((int) ($current['is_active'] ?? 0) === 1) {
      return ['success' => false, 'errors' => ['general' => 'Pytanie jest już aktywne.']];
    }

    $latestVersionId = (int) ($current['current_version']['id'] ?? 0);
    if ($latestVersionId <= 0) {
      return ['success' => false, 'errors' => ['general' => 'Pytanie nie ma wersji, którą można aktywować.']];
    }

    if (!$this->db->beginTransaction()) {
      return ['success' => false, 'errors' => ['general' => 'Nie udało się rozpocząć transakcji.']];
    }

    try {
      if (!$this->db->execute(
        "UPDATE questions SET is_active = 1, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL",
        [$questionId]
      )) {
        $this->db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się aktywować pytania.']];
      }

      if (
        !$this->db->execute(
          "UPDATE question_versions
           SET is_active = CASE WHEN id = ? THEN 1 ELSE 0 END, updated_at = NOW()
           WHERE question_id = ? AND deleted_at IS NULL",
          [$latestVersionId, $questionId]
        )
        || !$this->db->execute(
          "UPDATE answers SET is_active = 1, updated_at = NOW()
           WHERE question_version_id = ? AND deleted_at IS NULL",
          [$latestVersionId]
        )
      ) {
        $this->db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się zsynchronizować wersji i odpowiedzi pytania.']];
      }

      if (!$this->auditService->logQuestionActivated(
        $adminUserId,
        $questionId,
        ['is_active' => 0],
        ['is_active' => 1]
      )) {
        $this->db->rollBack();
        return ['success' => false, 'errors' => ['general' => 'Nie udało się zapisać wpisu audytowego.']];
      }

      $this->db->commit();
      return ['success' => true];
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminQuestionService] activateQuestion: ' . $e->getMessage());
      return ['success' => false, 'errors' => ['general' => 'Wystąpił nieoczekiwany błąd podczas aktywacji.']];
    }
  }

  /**
   * Pobiera odpowiedzi dla danej wersji.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getAnswersByVersionId(int $versionId): array
  {
    $sql = "SELECT id, question_version_id, answer_text, is_correct, answer_order, is_active, created_at, updated_at
            FROM answers
            WHERE question_version_id = ? AND deleted_at IS NULL
            ORDER BY answer_order ASC, id ASC";

    return $this->db->fetchAll($sql, [$versionId]);
  }

  /**
   * Pobiera historię wersji pytania.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getVersionHistory(int $questionId): array
  {
    $sql = "SELECT
              qv.id,
              qv.version_num,
              qv.question_text,
              qv.image_name,
              qv.explanation,
              qv.is_active,
              qv.created_at,
              qv.updated_at,
              COALESCE(ans.answers_count, 0) as answers_count,
              COALESCE(ans.correct_answers_count, 0) as correct_answers_count
            FROM question_versions qv
            LEFT JOIN (
              SELECT
                question_version_id,
                COUNT(*) as answers_count,
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers_count
              FROM answers
              WHERE deleted_at IS NULL
              GROUP BY question_version_id
            ) ans ON ans.question_version_id = qv.id
            WHERE qv.question_id = ? AND qv.deleted_at IS NULL
            ORDER BY qv.version_num DESC, qv.id DESC";

    return $this->db->fetchAll($sql, [$questionId]);
  }

  /**
   * Pobiera egzaminy do filtra/listy.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getExams(): array
  {
    $sql = "SELECT id, code, name, is_active
            FROM exam_types
            WHERE deleted_at IS NULL
            ORDER BY code ASC";

    return $this->db->fetchAll($sql);
  }

  /**
   * Pobiera tematy dla egzaminu.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getTopicsByExam(int $examId): array
  {
    $sql = "SELECT id, name, is_active
            FROM topics
            WHERE exam_type_id = ? AND deleted_at IS NULL
            ORDER BY name ASC";

    return $this->db->fetchAll($sql, [$examId]);
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function getTopicsForFilter(?int $examId = null): array
  {
    $where = $examId !== null && $examId > 0 ? ' AND t.exam_type_id = ?' : '';
    $params = $where !== '' ? [$examId] : [];

    return $this->db->fetchAll(
      "SELECT t.id, t.name, t.exam_type_id, et.code as exam_code
       FROM topics t
       JOIN exam_types et ON et.id = t.exam_type_id
       WHERE t.deleted_at IS NULL AND et.deleted_at IS NULL $where
       ORDER BY et.code ASC, t.name ASC",
      $params
    );
  }

  /**
   * Zwraca aktywną wersję pytania.
   */
  private function getLatestVersionByQuestionId(int $questionId): array|false
  {
    $sql = "SELECT qv.*
            FROM question_versions qv
            WHERE qv.question_id = ? AND qv.deleted_at IS NULL
            ORDER BY qv.version_num DESC, qv.id DESC
            LIMIT 1";

    return $this->db->fetch($sql, [$questionId]);
  }

  /**
   * Zwraca kolejną numerację wersji.
   */
  private function getNextVersionNumber(int $questionId): int
  {
    $sql = "SELECT COALESCE(MAX(version_num), 0) as max_version
            FROM question_versions
            WHERE question_id = ? AND deleted_at IS NULL";
    $result = $this->db->fetch($sql, [$questionId]);
    return ((int) ($result['max_version'] ?? 0)) + 1;
  }

  /**
   * Wstawia wersję pytania.
   */
  private function insertQuestionVersion(int $questionId, int $versionNum, string $questionText, ?string $explanation, ?string $imageName, int $isActive): int|false
  {
    $sql = "INSERT INTO question_versions
              (question_id, version_num, question_text, image_name, explanation, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";

    if (!$this->db->execute($sql, [
      $questionId,
      $versionNum,
      $questionText,
      $imageName,
      $explanation ?: null,
      $isActive,
    ])) {
      return false;
    }

    $lastInsertId = $this->db->lastInsertId();
    return $lastInsertId ?? false;
  }

  /**
   * Wstawia odpowiedzi do danej wersji.
   *
   * @param array<int, array<string, mixed>> $answers
   */
  private function insertAnswers(int $versionId, array $answers): bool
  {
    if (empty($answers)) {
      return false;
    }

    $values = [];
    $params = [];
    foreach ($answers as $index => $answer) {
      $values[] = '(?, ?, ?, ?, NOW(), NOW())';
      $params[] = $versionId;
      $params[] = (string) ($answer['content'] ?? '');
      $params[] = !empty($answer['is_correct']) ? 1 : 0;
      $params[] = $index + 1;
    }

    $sql = "INSERT INTO answers (question_version_id, answer_text, is_correct, answer_order, created_at, updated_at) VALUES " . implode(', ', $values);
    return $this->db->execute($sql, $params);
  }

  /**
   * Waliduje i normalizuje dane pytania.
   *
   * @param array<string, mixed> $input
   * @param array<string, mixed>|null $current
   * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: array<string, string>}
   */
  private function validatePayload(array $input, ?array $current = null): array
  {
    $data = [
      'exam_type_id' => (int) ($input['exam_type_id'] ?? ($current['exam_type_id'] ?? 0)),
      'topic_id' => (int) ($input['topic_id'] ?? ($current['topic_id'] ?? 0)),
      'question_text' => trim((string) ($input['question_text'] ?? ($current['current_version']['question_text'] ?? ''))),
      'explanation' => trim((string) ($input['explanation'] ?? ($current['current_version']['explanation'] ?? ''))),
      'is_active' => (int) (($input['is_active'] ?? ($current['is_active'] ?? 1)) ? 1 : 0),
      'remove_image' => !empty($input['remove_image']) ? 1 : 0,
      'exam_code' => '',
    ];

    $errors = [];
    $answers = [];

    if ($data['exam_type_id'] <= 0) {
      $errors['exam_type_id'] = 'Wybierz egzamin.';
    } else {
      $exam = $this->db->fetch("SELECT code FROM exam_types WHERE id = ? AND deleted_at IS NULL", [$data['exam_type_id']]);
      if (!$exam) {
        $errors['exam_type_id'] = 'Wybrany egzamin nie istnieje.';
      } else {
        $data['exam_code'] = (string) $exam['code'];
      }
    }

    if ($data['topic_id'] <= 0) {
      $errors['topic_id'] = 'Wybierz temat.';
    }

    if ($data['question_text'] === '') {
      $errors['question_text'] = 'Treść pytania jest wymagana.';
    } elseif (mb_strlen($data['question_text']) > 5000) {
      $errors['question_text'] = 'Treść pytania nie może przekraczać 5000 znaków.';
    }

    if ($data['explanation'] !== '' && mb_strlen($data['explanation']) > 5000) {
      $errors['explanation'] = 'Wyjaśnienie nie może przekraczać 5000 znaków.';
    }

    if (!in_array($data['is_active'], [0, 1], true)) {
      $errors['is_active'] = 'Nieprawidłowy status pytania.';
    }

    $rawAnswers = $input['answers'] ?? [];
    if (!is_array($rawAnswers)) {
      $rawAnswers = [];
    }

    foreach ($rawAnswers as $index => $answer) {
      $content = trim((string) ($answer['content'] ?? ''));
      $isCorrect = !empty($answer['is_correct']);

      if ($content === '') {
        continue;
      }

      if (mb_strlen($content) > 4000) {
        $errors['answers_' . $index] = 'Odpowiedź nie może przekraczać 4000 znaków.';
      }

      $answers[] = [
        'content' => $content,
        'is_correct' => $isCorrect,
      ];
    }

    if (count($answers) < 2) {
      $errors['answers'] = 'Dodaj co najmniej 2 odpowiedzi.';
    }

    $correctAnswers = array_values(array_filter($answers, static fn(array $answer): bool => !empty($answer['is_correct'])));
    if (count($correctAnswers) !== 1) {
      $errors['answers'] = 'Oznacz dokładnie jedną poprawną odpowiedź.';
    }

    return [$data, $answers, $errors];
  }

  /**
   * Sprawdza, czy temat należy do egzaminu.
   */
  private function isValidExamTopicPair(int $examId, int $topicId): bool
  {
    $sql = "SELECT id
            FROM topics
            WHERE id = ? AND exam_type_id = ? AND deleted_at IS NULL
            LIMIT 1";
    return $this->db->fetch($sql, [$topicId, $examId]) !== false;
  }

  /**
   * Obsługuje upload obrazu pytania.
   *
   * @return array{success: bool, path?: string|null, error?: string|null}
   */
  private function processImageUpload(?array $file, string $examCode, ?int $questionId): array
  {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      return ['success' => true, 'path' => null];
    }

    if (!isset($file['tmp_name'], $file['name'], $file['size'], $file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
      return ['success' => false, 'error' => 'Wystąpił błąd podczas uploadu pliku.'];
    }

    $maxBytes = 5 * 1024 * 1024;
    if ((int) $file['size'] > $maxBytes) {
      return ['success' => false, 'error' => 'Plik graficzny nie może być większy niż 5 MB.'];
    }

    $tmpName = (string) $file['tmp_name'];
    $mimeType = $this->detectMimeType($tmpName);
    $allowedMimeTypes = [
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
      return ['success' => false, 'error' => 'Dozwolone są tylko pliki JPG, JPEG, PNG i WEBP.'];
    }

    $extension = $allowedMimeTypes[$mimeType];
    $safeExamCode = preg_replace('/[^A-Z0-9]/', '_', strtoupper($examCode)) ?: 'exam';
    try {
      $suffix = bin2hex(random_bytes(8));
    } catch (\Throwable $e) {
      $suffix = str_replace('.', '', uniqid('', true));
    }
    $filename = sprintf('%s_%s_%s.%s', $safeExamCode, $questionId ? 'q' . $questionId : 'new', $suffix, $extension);

    $directory = dirname(__DIR__, 2) . '/public/images/questions/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
      return ['success' => false, 'error' => 'Nie udało się przygotować katalogu na pliki.'];
    }

    $targetPath = $directory . '/' . $filename;
    if (!move_uploaded_file($tmpName, $targetPath)) {
      return ['success' => false, 'error' => 'Nie udało się zapisać pliku na serwerze.'];
    }

    return ['success' => true, 'path' => 'uploads/' . $filename];
  }

  /**
   * Rozpoznaje MIME obrazu.
   */
  private function detectMimeType(string $path): string
  {
    if (!function_exists('finfo_open')) {
      return '';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
      return '';
    }

    $mime = finfo_file($finfo, $path) ?: '';
    finfo_close($finfo);
    return (string) $mime;
  }

  /**
   * Buduje filtr SQL.
   *
   * @param array<string, mixed> $filters
   * @return array{0:string,1:array<int, mixed>}
   */
  private function buildFilterSql(array $filters): array
  {
    $where = '';
    $params = [];

    if (!empty($filters['exam_id']) && ctype_digit((string) $filters['exam_id'])) {
      $where .= ' AND q.exam_type_id = ?';
      $params[] = (int) $filters['exam_id'];
    }

    if (!empty($filters['topic_id']) && ctype_digit((string) $filters['topic_id'])) {
      $where .= ' AND q.topic_id = ?';
      $params[] = (int) $filters['topic_id'];
    }

    if (!empty($filters['status'])) {
      $status = (string) $filters['status'];
      if ($status === 'active') {
        $where .= ' AND q.is_active = 1';
      } elseif ($status === 'inactive') {
        $where .= ' AND q.is_active = 0';
      }
    }

    if (!empty($filters['query'])) {
      $query = trim((string) $filters['query']);
      $like = '%' . $query . '%';

      if (ctype_digit($query)) {
        $where .= ' AND (q.id = ? OR et.code LIKE ? OR et.name LIKE ? OR t.name LIKE ? OR qv.question_text LIKE ? OR qv.explanation LIKE ?)';
        $params[] = (int) $query;
      } else {
        $where .= ' AND (et.code LIKE ? OR et.name LIKE ? OR t.name LIKE ? OR qv.question_text LIKE ? OR qv.explanation LIKE ?)';
      }

      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
    }

    return [$where, $params];
  }

  /**
   * Tworzy uproszczony snapshot pytania do audytu.
   *
   * @param array<int, array<string, mixed>> $answers
   * @return array<string, mixed>
   */
  private function snapshotForAudit(array $data, array $answers, ?string $imagePath): array
  {
    return [
      'exam_type_id' => (int) $data['exam_type_id'],
      'topic_id' => (int) $data['topic_id'],
      'question_text' => $data['question_text'],
      'explanation' => $data['explanation'],
      'image_name' => $imagePath,
      'is_active' => (int) $data['is_active'],
      'answers' => array_map(static fn(array $answer): array => [
        'content' => $answer['content'],
        'is_correct' => (int) $answer['is_correct'],
      ], $answers),
    ];
  }

  /**
   * Normalizuje snapshot istniejącego pytania.
   *
   * @param array<string, mixed> $question
   * @return array<string, mixed>
   */
  private function normalizeQuestionSnapshot(array $question): array
  {
    $currentVersion = $question['current_version'] ?? [];
    $answers = $question['answers'] ?? [];

    return [
      'exam_type_id' => (int) ($question['exam_type_id'] ?? 0),
      'topic_id' => (int) ($question['topic_id'] ?? 0),
      'question_text' => trim((string) ($currentVersion['question_text'] ?? '')),
      'explanation' => trim((string) ($currentVersion['explanation'] ?? '')),
      'image_name' => $currentVersion['image_name'] ?? null,
      'is_active' => (int) ($question['is_active'] ?? 1),
      'answers' => array_map(static fn(array $answer): array => [
        'content' => trim((string) ($answer['answer_text'] ?? '')),
        'is_correct' => (int) ($answer['is_correct'] ?? 0),
      ], $answers),
    ];
  }

  /**
   * Normalizuje dane po stronie formularza.
   *
   * @param array<string, mixed> $data
   * @param array<int, array<string, mixed>> $answers
   * @return array<string, mixed>
   */
  private function normalizeSubmittedSnapshot(array $data, array $answers, ?string $imagePath): array
  {
    return [
      'exam_type_id' => (int) $data['exam_type_id'],
      'topic_id' => (int) $data['topic_id'],
      'question_text' => trim((string) $data['question_text']),
      'explanation' => trim((string) $data['explanation']),
      'image_name' => $imagePath,
      'is_active' => (int) $data['is_active'],
      'answers' => array_map(static fn(array $answer): array => [
        'content' => trim((string) ($answer['content'] ?? '')),
        'is_correct' => (int) ($answer['is_correct'] ?? 0),
      ], $answers),
    ];
  }
}
