<?php

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Serwis analityki produktowej dla panelu administracyjnego.
 *
 * Odpowiada za zapis eventów oraz agregację danych pod dashboard
 * administracyjny. Dane odtwarzalne z istniejących tabel liczy bezpośrednio
 * z bazy, a `analytics_events` wykorzystuje dla zdarzeń produktowych.
 *
 * @package App\Services
 */
class AnalyticsService
{
  private Database $db;

  /** @var string[] */
  private array $allowedEventTypes = [
    'page_view',
    'login',
    'registration',
    'test_started',
    'test_completed',
    'question_answered',
    'single_question_started',
    'single_question_answered',
  ];

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  public function trackEvent(
    string $eventType,
    ?int $userId = null,
    array $metadata = [],
    ?DateTimeInterface $eventDate = null
  ): bool {
    if (!in_array($eventType, $this->allowedEventTypes, true)) {
      return false;
    }

    $sql = "INSERT INTO analytics_events
            (user_id, event_type, event_date, ip_address, user_agent, metadata, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())";

    return $this->db->execute($sql, [
      $userId,
      $eventType,
      ($eventDate ?? new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
      $this->getIpAddress(),
      $this->getUserAgent(),
      $this->encodeMetadata($this->sanitizeMetadata($metadata)),
    ]);
  }

  public function trackLogin(?User $user, ?array $request = null, array $metadata = []): bool
  {
    if ($request !== null) {
      $this->overrideRequestContext($request);
    }

    return $this->trackEvent('login', $user?->getId(), array_merge([
      'auth_provider' => $user?->getAuthProvider() ?? 'local',
    ], $metadata));
  }

  public function trackQuestionAnswered(
    ?int $userId,
    int $examId,
    int $questionId,
    bool $isCorrect,
    ?int $testId = null
  ): bool {
    return $this->trackEvent('question_answered', $userId, [
      'exam_id' => $examId,
      'test_id' => $testId,
      'question_id' => $questionId,
      'is_correct' => $isCorrect ? 1 : 0,
    ]);
  }

  public function trackTestStarted(?int $userId, int $examId, array $metadata = []): bool
  {
    return $this->trackEvent('test_started', $userId, array_merge([
      'exam_id' => $examId,
    ], $metadata));
  }

  public function trackTestCompleted(?int $userId, int $examId, int $testId, array $metadata = []): bool
  {
    return $this->trackEvent('test_completed', $userId, array_merge([
      'exam_id' => $examId,
      'test_id' => $testId,
    ], $metadata));
  }

  public function trackSingleQuestionStarted(
    ?int $userId = null,
    ?int $examId = null,
    ?int $questionId = null,
    array $metadata = []
  ): bool {
    return $this->trackEvent('single_question_started', $userId, array_merge([
      'exam_id' => $examId,
      'question_id' => $questionId,
      'source' => 'single_question_module',
    ], $metadata));
  }

  public function trackSingleQuestionAnswered(
    ?int $userId = null,
    ?int $examId = null,
    ?int $questionId = null,
    ?bool $isCorrect = null,
    array $metadata = []
  ): bool {
    return $this->trackEvent('single_question_answered', $userId, array_merge([
      'exam_id' => $examId,
      'question_id' => $questionId,
      'is_correct' => $isCorrect === null ? null : ($isCorrect ? 1 : 0),
      'source' => 'single_question_module',
    ], $metadata));
  }

  public function trackQuestionAnsweredBatch(
    ?int $userId,
    int $examId,
    int $testId,
    array $answersDetails
  ): bool {
    if (empty($answersDetails)) {
      return true;
    }

    $versionIds = [];
    foreach ($answersDetails as $detail) {
      if (!empty($detail['question_version_id'])) {
        $versionIds[] = (int) $detail['question_version_id'];
      }
    }

    $versionIds = array_values(array_unique(array_filter($versionIds)));
    if (empty($versionIds)) {
      return true;
    }

    $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
    $rows = $this->db->fetchAll(
      "SELECT id, question_id FROM question_versions WHERE id IN ($placeholders)",
      $versionIds
    );

    $versionMap = [];
    foreach ($rows as $row) {
      $versionMap[(int) $row['id']] = (int) $row['question_id'];
    }

    $values = [];
    $params = [];
    foreach ($answersDetails as $detail) {
      if (!array_key_exists('answer_id', $detail) || $detail['answer_id'] === null) {
        continue;
      }

      $versionId = (int) ($detail['question_version_id'] ?? 0);
      if (!$versionId || !isset($versionMap[$versionId])) {
        continue;
      }

      $values[] = '(?, ?, ?, ?, ?, ?, NOW())';
      $params[] = $userId;
      $params[] = 'question_answered';
      $params[] = $this->getCurrentTimestamp();
      $params[] = $this->getIpAddress();
      $params[] = $this->getUserAgent();
      $params[] = $this->encodeMetadata($this->sanitizeMetadata([
        'exam_id' => $examId,
        'test_id' => $testId,
        'question_id' => $versionMap[$versionId],
        'is_correct' => !empty($detail['is_correct']) ? 1 : 0,
      ]));
    }

    if (empty($values)) {
      return true;
    }

    $sql = "INSERT INTO analytics_events
            (user_id, event_type, event_date, ip_address, user_agent, metadata, created_at)
            VALUES " . implode(', ', $values);

    return $this->db->execute($sql, $params);
  }

  public function getDashboardData(array $filters = []): array
  {
    $range = $this->resolveDateRange($filters);

    return [
      'range' => $range,
      'summary' => $this->getSummaryStats($range),
      'daily' => $this->getDailyAnalytics($range),
      'examActivity' => $this->getExamActivity($range),
      'singleQuestionActivity' => $this->getSingleQuestionActivity($range),
      'hardestQuestions' => $this->getHardestQuestions($range),
      'recentActivity' => $this->getRecentActivity(10),
      'recentLogins' => $this->getRecentLogins(10),
    ];
  }

  public function getSummaryStats(array $range): array
  {
    $today = $this->buildTodayRange();
    $totalUsers = $this->fetchCount('SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL');
    $newRegistrationsToday = $this->fetchCount(
      'SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL AND created_at BETWEEN ? AND ?',
      [
        $today['start']->format('Y-m-d 00:00:00'),
        $today['end']->format('Y-m-d 23:59:59'),
      ]
    );
    $loginsToday = $this->fetchEventCount('login', $today);
    $pageViewsToday = $this->fetchEventCount('page_view', $today);
    $testsStartedToday = $this->fetchEventCount('test_started', $today);
    $testsCompletedToday = $this->fetchEventCount('test_completed', $today);
    $questionsAnsweredToday = $this->fetchEventCount('question_answered', $today);
    $averageScore = $this->fetchAverageScore($range['start']->format('Y-m-d 00:00:00'), $range['end']->format('Y-m-d 23:59:59'));

    return array_merge([
      'totalUsers' => $totalUsers,
      'newRegistrationsToday' => $newRegistrationsToday,
      'loginsToday' => $loginsToday,
      'pageViewsToday' => $pageViewsToday,
      'testsStartedToday' => $testsStartedToday,
      'testsCompletedToday' => $testsCompletedToday,
      'questionsAnsweredToday' => $questionsAnsweredToday,
      'averageScore' => $averageScore,
    ], $this->getSingleQuestionSummary($range));
  }

  public function getSingleQuestionSummary(array $range): array
  {
    $today = $this->buildTodayRange();
    $startedToday = $this->fetchEventCount('single_question_started', $today);
    $startedInRange = $this->fetchEventCount('single_question_started', $range);
    $answeredToday = $this->fetchEventCount('single_question_answered', $today);
    $accuracy = $this->fetchSingleQuestionAccuracy($range);

    return [
      'singleQuestionStartedToday' => $startedToday,
      'singleQuestionStartedInRange' => $startedInRange,
      'singleQuestionAnsweredToday' => $answeredToday,
      'singleQuestionAccuracy' => $accuracy,
    ];
  }

  public function getDailyCounts(string $eventType, array $range): array
  {
    $sql = "SELECT DATE(event_date) as day, COUNT(*) as total
            FROM analytics_events
            WHERE event_type = ? AND event_date BETWEEN ? AND ?
            GROUP BY DATE(event_date)";

    $rows = $this->db->fetchAll($sql, [
      $eventType,
      $range['start']->format('Y-m-d 00:00:00'),
      $range['end']->format('Y-m-d 23:59:59'),
    ]);

    $map = [];
    foreach ($rows as $row) {
      $map[(string) $row['day']] = (int) $row['total'];
    }

    return $map;
  }

  public function getSingleQuestionDailyCounts(array $range): array
  {
    return [
      'singleQuestionStarted' => $this->getDailyCounts('single_question_started', $range),
      'singleQuestionAnswered' => $this->getDailyCounts('single_question_answered', $range),
    ];
  }

  public function getDailyAnalytics(array $range): array
  {
    $labels = $this->buildDateLabels($range['start'], $range['end']);
    $eventMaps = [
      'pageViews' => $this->getDailyCounts('page_view', $range),
      'logins' => $this->getDailyCounts('login', $range),
      'registrations' => $this->getDailyCounts('registration', $range),
      'testsStarted' => $this->getDailyCounts('test_started', $range),
      'testsCompleted' => $this->getDailyCounts('test_completed', $range),
    ];

    $singleQuestionMaps = $this->getSingleQuestionDailyCounts($range);

    $daily = [
      'labels' => $labels,
      'pageViews' => [],
      'logins' => [],
      'registrations' => [],
      'testsStarted' => [],
      'testsCompleted' => [],
      'singleQuestionStarted' => [],
      'singleQuestionAnswered' => [],
    ];

    foreach ($labels as $label) {
      $daily['pageViews'][] = (int) ($eventMaps['pageViews'][$label] ?? 0);
      $daily['logins'][] = (int) ($eventMaps['logins'][$label] ?? 0);
      $daily['registrations'][] = (int) ($eventMaps['registrations'][$label] ?? 0);
      $daily['testsStarted'][] = (int) ($eventMaps['testsStarted'][$label] ?? 0);
      $daily['testsCompleted'][] = (int) ($eventMaps['testsCompleted'][$label] ?? 0);
      $daily['singleQuestionStarted'][] = (int) ($singleQuestionMaps['singleQuestionStarted'][$label] ?? 0);
      $daily['singleQuestionAnswered'][] = (int) ($singleQuestionMaps['singleQuestionAnswered'][$label] ?? 0);
    }

    return $daily;
  }

  public function getDailyOverview(array $range): array
  {
    return $this->getDailyAnalytics($range);
  }

  public function getExamActivity(array $range): array
  {
    $start = $range['start']->format('Y-m-d 00:00:00');
    $end = $range['end']->format('Y-m-d 23:59:59');

    $sql = "SELECT
              a.exam_type_id,
              et.code,
              et.name,
              COUNT(CASE WHEN a.started_at BETWEEN ? AND ? THEN 1 END) as started_tests,
              COUNT(CASE WHEN a.completed_at BETWEEN ? AND ? THEN 1 END) as completed_tests,
              ROUND(AVG(CASE
                WHEN a.completed_at IS NOT NULL
                 AND a.total_questions > 0
                 AND a.completed_at BETWEEN ? AND ?
                THEN (a.correct_count * 100.0 / a.total_questions)
              END), 1) as avg_score,
              COUNT(DISTINCT CASE
                WHEN (a.started_at BETWEEN ? AND ?) OR (a.completed_at BETWEEN ? AND ?)
                THEN a.user_id
              END) as unique_users
            FROM attempts a
            JOIN exam_types et ON et.id = a.exam_type_id
            WHERE (a.started_at BETWEEN ? AND ? OR a.completed_at BETWEEN ? AND ?)
            GROUP BY a.exam_type_id, et.code, et.name
            ORDER BY completed_tests DESC, started_tests DESC, et.code ASC";

    return $this->db->fetchAll($sql, [
      $start, $end,
      $start, $end,
      $start, $end,
      $start, $end,
      $start, $end,
      $start, $end,
      $start, $end,
    ]);
  }

  public function getSingleQuestionActivity(array $range): array
  {
    $start = $range['start']->format('Y-m-d 00:00:00');
    $end = $range['end']->format('Y-m-d 23:59:59');

    $sql = "SELECT
              sq.exam_id,
              COALESCE(et.code, '') as code,
              COALESCE(et.name, 'Brak przypisanego egzaminu') as name,
              sq.started_count,
              sq.answered_count,
              sq.unique_users,
              sq.accuracy,
              qv.question_text as top_question_text
            FROM (
              SELECT
                source.exam_id,
                COUNT(CASE WHEN source.event_type = 'single_question_started' THEN 1 END) as started_count,
                COUNT(CASE WHEN source.event_type = 'single_question_answered' THEN 1 END) as answered_count,
                COUNT(DISTINCT source.user_id) as unique_users,
                ROUND(AVG(CASE
                  WHEN source.event_type = 'single_question_answered'
                   AND source.is_correct IS NOT NULL
                  THEN source.is_correct
                END) * 100, 1) as accuracy,
                (
                  SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(inner_events.metadata, '$.question_id')) AS UNSIGNED)
                  FROM analytics_events inner_events
                  WHERE inner_events.event_type = 'single_question_answered'
                    AND inner_events.event_date BETWEEN ? AND ?
                    AND (
                      (source.exam_id IS NULL AND JSON_EXTRACT(inner_events.metadata, '$.exam_id') IS NULL)
                      OR CAST(JSON_UNQUOTE(JSON_EXTRACT(inner_events.metadata, '$.exam_id')) AS UNSIGNED) = source.exam_id
                    )
                    AND JSON_EXTRACT(inner_events.metadata, '$.question_id') IS NOT NULL
                  GROUP BY CAST(JSON_UNQUOTE(JSON_EXTRACT(inner_events.metadata, '$.question_id')) AS UNSIGNED)
                  ORDER BY COUNT(*) DESC, CAST(JSON_UNQUOTE(JSON_EXTRACT(inner_events.metadata, '$.question_id')) AS UNSIGNED) ASC
                  LIMIT 1
                ) as top_question_id
              FROM (
                SELECT
                  user_id,
                  event_type,
                  CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.exam_id')) AS UNSIGNED) as exam_id,
                  CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.is_correct')) AS UNSIGNED) as is_correct
                FROM analytics_events
                WHERE event_type IN ('single_question_started', 'single_question_answered')
                  AND event_date BETWEEN ? AND ?
              ) source
              GROUP BY source.exam_id
            ) sq
            LEFT JOIN exam_types et ON et.id = sq.exam_id
            LEFT JOIN (
              SELECT qv1.question_id, qv1.question_text
              FROM question_versions qv1
              INNER JOIN (
                SELECT question_id, MAX(version_num) as version_num
                FROM question_versions
                WHERE deleted_at IS NULL
                GROUP BY question_id
              ) latest
                ON latest.question_id = qv1.question_id
               AND latest.version_num = qv1.version_num
              WHERE qv1.deleted_at IS NULL
            ) qv ON qv.question_id = sq.top_question_id
            ORDER BY sq.started_count DESC, sq.answered_count DESC, name ASC";

    return $this->db->fetchAll($sql, [
      $start, $end,
      $start, $end,
    ]);
  }

  public function getHardestQuestions(array $range, int $limit = 10): array
  {
    $start = $range['start']->format('Y-m-d 00:00:00');
    $end = $range['end']->format('Y-m-d 23:59:59');
    $limit = max(1, min(50, $limit));

    $sql = "SELECT
              qa.question_id,
              qv.question_text,
              et.code as exam_code,
              et.name as exam_name,
              qa.total_answers,
              qa.wrong_answers,
              ROUND((qa.wrong_answers * 100.0 / qa.total_answers), 1) as wrong_percent
            FROM (
              SELECT
                CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.question_id')) AS UNSIGNED) as question_id,
                COUNT(*) as total_answers,
                SUM(
                  CASE
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.is_correct')) IN ('1', 'true', 'TRUE') THEN 0
                    ELSE 1
                  END
                ) as wrong_answers
              FROM analytics_events
              WHERE event_type = 'question_answered'
                AND event_date BETWEEN ? AND ?
                AND JSON_EXTRACT(metadata, '$.question_id') IS NOT NULL
              GROUP BY CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.question_id')) AS UNSIGNED)
              HAVING total_answers > 0
            ) qa
            LEFT JOIN questions q ON q.id = qa.question_id
            LEFT JOIN exam_types et ON et.id = q.exam_type_id
            LEFT JOIN (
              SELECT qv1.question_id, qv1.question_text
              FROM question_versions qv1
              INNER JOIN (
                SELECT question_id, MAX(version_num) as version_num
                FROM question_versions
                WHERE deleted_at IS NULL
                GROUP BY question_id
              ) latest ON latest.question_id = qv1.question_id AND latest.version_num = qv1.version_num
              WHERE qv1.deleted_at IS NULL
            ) qv ON qv.question_id = qa.question_id
            ORDER BY qa.wrong_answers DESC, qa.total_answers DESC
            LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, [$start, $end]);
  }

  public function getRecentActivity(int $limit = 10): array
  {
    $limit = max(1, min(50, $limit));

    return [
      'logins' => $this->getRecentLogins($limit),
      'registrations' => $this->getRecentRegistrations($limit),
      'testsCompleted' => $this->getRecentCompletedTests($limit),
    ];
  }

  public function getOverviewMetrics(array $range): array
  {
    return [
      'summary' => $this->getSummaryStats($range),
      'daily' => $this->getDailyAnalytics($range),
      'examActivity' => $this->getExamActivity($range),
      'singleQuestionActivity' => $this->getSingleQuestionActivity($range),
      'hardestQuestions' => $this->getHardestQuestions($range),
      'recentActivity' => $this->getRecentActivity(10),
    ];
  }

  public function getRecentLogins(int $limit = 10): array
  {
    $sql = "SELECT
              e.id,
              e.event_date,
              e.user_id,
              e.ip_address,
              e.user_agent,
              u.first_name,
              u.last_name,
              u.email
            FROM analytics_events e
            LEFT JOIN users u ON u.id = e.user_id
            WHERE e.event_type = 'login'
            ORDER BY e.event_date DESC, e.id DESC
            LIMIT " . (int) max(1, min(50, $limit));

    $rows = $this->db->fetchAll($sql);
    foreach ($rows as &$row) {
      $row['browser_label'] = $this->simplifyUserAgent((string) ($row['user_agent'] ?? ''));
      $fullName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
      $row['display_name'] = $fullName !== '' ? $fullName : 'Użytkownik nieznany';
    }

    return $rows;
  }

  public function resolveDateRangeFromRequest(array $request): array
  {
    return $this->resolveDateRange($request);
  }

  private function getRecentRegistrations(int $limit): array
  {
    $sql = "SELECT id, created_at, first_name, last_name, email, auth_provider
            FROM users
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql);
  }

  private function getRecentCompletedTests(int $limit): array
  {
    $sql = "SELECT a.id, a.completed_at, a.correct_count, a.total_questions, a.user_id,
                   u.first_name, u.last_name, u.email,
                   et.code as exam_code, et.name as exam_name
            FROM attempts a
            LEFT JOIN users u ON u.id = a.user_id
            JOIN exam_types et ON et.id = a.exam_type_id
            WHERE a.completed_at IS NOT NULL
            ORDER BY a.completed_at DESC
            LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql);
  }

  private function fetchEventCount(string $eventType, array $range): int
  {
    return $this->fetchCount(
      "SELECT COUNT(*) as total FROM analytics_events WHERE event_type = ? AND event_date BETWEEN ? AND ?",
      [$eventType, $range['start']->format('Y-m-d 00:00:00'), $range['end']->format('Y-m-d 23:59:59')]
    );
  }

  private function fetchCount(string $sql, array $params = []): int
  {
    $result = $this->db->fetch($sql, $params);
    return (int) ($result['total'] ?? 0);
  }

  private function fetchAverageScore(string $start, string $end): float
  {
    $sql = "SELECT ROUND(AVG(CASE WHEN total_questions > 0 THEN (correct_count * 100.0 / total_questions) END), 1) as average_score
            FROM attempts
            WHERE completed_at IS NOT NULL AND completed_at BETWEEN ? AND ?";
    $result = $this->db->fetch($sql, [$start, $end]);
    return (float) ($result['average_score'] ?? 0);
  }

  private function fetchSingleQuestionAccuracy(array $range): float
  {
    $sql = "SELECT ROUND(AVG(CASE
              WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.is_correct')) IN ('1', 'true', 'TRUE') THEN 100
              WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.is_correct')) IN ('0', 'false', 'FALSE') THEN 0
              ELSE NULL
            END), 1) as accuracy
            FROM analytics_events
            WHERE event_type = 'single_question_answered'
              AND event_date BETWEEN ? AND ?";

    $result = $this->db->fetch($sql, [
      $range['start']->format('Y-m-d 00:00:00'),
      $range['end']->format('Y-m-d 23:59:59'),
    ]);

    return (float) ($result['accuracy'] ?? 0);
  }

  private function resolveDateRange(array $filters): array
  {
    $range = strtolower((string) ($filters['range'] ?? '30d'));
    $today = new DateTimeImmutable('today');
    $end = $today->setTime(23, 59, 59);

    if (!empty($filters['from']) && !empty($filters['to'])) {
      $from = DateTimeImmutable::createFromFormat('Y-m-d', (string) $filters['from']);
      $to = DateTimeImmutable::createFromFormat('Y-m-d', (string) $filters['to']);

      if ($from instanceof DateTimeImmutable && $to instanceof DateTimeImmutable) {
        if ($from > $to) {
          [$from, $to] = [$to, $from];
        }

        return [
          'value' => 'custom',
          'label' => $from->format('d.m.Y') . ' - ' . $to->format('d.m.Y'),
          'start' => $from->setTime(0, 0, 0),
          'end' => $to->setTime(23, 59, 59),
          'from' => $from->format('Y-m-d'),
          'to' => $to->format('Y-m-d'),
        ];
      }
    }

    $days = match ($range) {
      '7d' => 7,
      '90d' => 90,
      '30d' => 30,
      default => 30,
    };

    $start = $today->sub(new DateInterval('P' . ($days - 1) . 'D'))->setTime(0, 0, 0);

    return [
      'value' => $days . 'd',
      'label' => 'Ostatnie ' . $days . ' dni',
      'start' => $start,
      'end' => $end,
      'from' => $start->format('Y-m-d'),
      'to' => $end->format('Y-m-d'),
    ];
  }

  private function buildDateLabels(DateTimeImmutable $start, DateTimeImmutable $end): array
  {
    $labels = [];
    $cursor = $start->setTime(0, 0, 0);
    $limit = $end->setTime(0, 0, 0);

    while ($cursor <= $limit) {
      $labels[] = $cursor->format('Y-m-d');
      $cursor = $cursor->modify('+1 day');
    }

    return $labels;
  }

  private function buildTodayRange(): array
  {
    $today = new DateTimeImmutable('today');

    return [
      'start' => $today->setTime(0, 0, 0),
      'end' => $today->setTime(23, 59, 59),
    ];
  }

  private function sanitizeMetadata(array $metadata): array
  {
    $sensitiveKeys = ['password', 'token', 'csrf', 'session', 'auth', 'password_hash', 'email', 'user_agent', 'ip_address'];
    $sanitized = [];

    foreach ($metadata as $key => $value) {
      $normalizedKey = strtolower((string) $key);
      if (in_array($normalizedKey, $sensitiveKeys, true)) {
        continue;
      }

      if (is_scalar($value) || $value === null) {
        $sanitized[$key] = $value;
        continue;
      }

      if (is_array($value)) {
        $sanitized[$key] = $this->sanitizeMetadata($value);
      }
    }

    return $sanitized;
  }

  private function encodeMetadata(array $metadata): string
  {
    return json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
  }

  private function getIpAddress(): ?string
  {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    return is_string($ipAddress) && $ipAddress !== '' ? $ipAddress : null;
  }

  private function getUserAgent(): ?string
  {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    return is_string($userAgent) && $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null;
  }

  private function getCurrentTimestamp(): string
  {
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
  }

  private function overrideRequestContext(array $request): void
  {
    if (isset($request['REMOTE_ADDR']) && is_string($request['REMOTE_ADDR'])) {
      $_SERVER['REMOTE_ADDR'] = $request['REMOTE_ADDR'];
    }

    if (isset($request['HTTP_USER_AGENT']) && is_string($request['HTTP_USER_AGENT'])) {
      $_SERVER['HTTP_USER_AGENT'] = $request['HTTP_USER_AGENT'];
    }
  }

  private function simplifyUserAgent(string $userAgent): string
  {
    if ($userAgent === '') {
      return 'Brak danych';
    }

    $map = [
      'Edg/' => 'Microsoft Edge',
      'Chrome/' => 'Google Chrome',
      'Firefox/' => 'Mozilla Firefox',
      'Safari/' => 'Safari',
      'OPR/' => 'Opera',
    ];

    foreach ($map as $needle => $label) {
      if (str_contains($userAgent, $needle)) {
        return $label;
      }
    }

    return mb_strimwidth($userAgent, 0, 48, '...');
  }
}
