<?php

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Zapis zdarzeń produktowych i agregaty panelu administracyjnego.
 *
 * Metryki wyników i treści bazują na tabelach domenowych. Eventy są używane
 * tam, gdzie baza domenowa nie przechowuje odsłon, logowań i sesji.
 */
class AnalyticsService
{
  private const EVENT_TYPES = [
    'page_view',
    'entry',
    'login',
    'registration',
    'test_started',
    'test_completed',
    'test_exited',
    'question_answered',
    'single_question_started',
    'single_question_answered',
    'search_performed',
    'saved_question_added',
    'saved_question_removed',
    'explanation_viewed',
    'answer_changed',
    'settings_updated',
    'email_verified',
    'password_reset_requested',
    'account_deleted',
    'account_anonymized',
    'no_questions_left',
    'validation_error',
  ];

  private const TEST_TYPES = ['full_exam', 'personalized'];
  private const AUTH_PROVIDERS = ['local', 'google'];
  private const USER_ROLES = ['admin', 'user'];
  /**
   * Wzorzec user-agentów botów i narzędzi automatycznych (MySQL REGEXP i PHP preg).
   * Roboty wyszukiwarek renderują JS, więc bez filtra zawyżały odsłony
   * i "rozpoczęte testy" (strona testu sama pobiera zestaw pytań).
   */
  public const BOT_USER_AGENT_PATTERN = 'bot|crawl|spider|slurp|headless|lighthouse|preview|facebookexternalhit|curl|wget|python-requests|axios|go-http-client|java/';

  private const METADATA_KEYS = [
    'exam_id',
    'question_id',
    'topic_id',
    'test_id',
    'test_type',
    'source',
    'path',
    'result',
    'count',
    'duration_seconds',
    'score',
    'correct_answers',
    'total_questions',
  ];

  private Database $db;

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
    try {
      if (!in_array($eventType, self::EVENT_TYPES, true)) {
        return false;
      }

      // Ruch botów nie trafia do analityki (zob. BOT_USER_AGENT_PATTERN).
      if ($this->isBotUserAgent($this->getUserAgent())) {
        return false;
      }

      $dimensions = $this->extractEventDimensions($metadata);
      $metadata = $this->sanitizeMetadata($metadata);
      $sql = "INSERT INTO analytics_events
              (user_id, event_type, event_date, ip_address, user_agent, session_hash,
               exam_type_id, question_id, topic_id, test_id, source, test_type,
               auth_provider, page_path, search_term, is_correct, metadata, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

      return $this->db->execute($sql, [
        $userId,
        $eventType,
        ($eventDate ?? new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        $this->getIpAddress(),
        $this->getUserAgent(),
        $this->getSessionHash(),
        $dimensions['exam_type_id'],
        $dimensions['question_id'],
        $dimensions['topic_id'],
        $dimensions['test_id'],
        $dimensions['source'],
        $dimensions['test_type'],
        $dimensions['auth_provider'],
        $dimensions['page_path'],
        $dimensions['search_term'],
        $dimensions['is_correct'],
        $this->encodeMetadata($metadata),
      ]);
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Rejestruje wejście na stronę główną (landing), w odróżnieniu od ogólnego
   * `page_view`, który liczy odsłony wszystkich stron aplikacji.
   */
  public function trackEntry(?int $userId, array $metadata = []): bool
  {
    return $this->trackEvent('entry', $userId, $metadata);
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
    ?int $testId = null,
    ?string $testType = null,
    ?string $source = null
  ): bool {
    return $this->trackEvent('question_answered', $userId, [
      'exam_id' => $examId,
      'test_id' => $testId,
      'test_type' => $testType,
      'question_id' => $questionId,
      'is_correct' => $isCorrect ? 1 : 0,
      'source' => $source,
    ]);
  }

  public function trackTestStarted(?int $userId, int $examId, array $metadata = []): bool
  {
    return $this->trackEvent('test_started', $userId, array_merge(['exam_id' => $examId], $metadata));
  }

  public function trackTestCompleted(?int $userId, int $examId, int $testId, array $metadata = []): bool
  {
    return $this->trackEvent('test_completed', $userId, array_merge([
      'exam_id' => $examId,
      'test_id' => $testId,
    ], $metadata));
  }

  public function trackTestExited(?int $userId, ?int $examId, ?string $testType, int $durationSeconds): bool
  {
    return $this->trackEvent('test_exited', $userId, [
      'exam_id' => $examId,
      'test_type' => $testType,
      'source' => 'test_runner',
      'result' => 'exited_before_completion',
      'duration_seconds' => max(0, $durationSeconds),
    ]);
  }

  public function trackSearchPerformed(
    ?int $userId,
    ?int $examId,
    ?int $topicId,
    ?string $query,
    int $resultCount,
    string $source
  ): bool {
    return $this->trackEvent('search_performed', $userId, [
      'exam_id' => $examId,
      'topic_id' => $topicId,
      'search_term' => $this->normalizeSearchTerm($query),
      'source' => $source,
      'result' => $resultCount > 0 ? 'results_found' : 'no_results',
      'count' => max(0, $resultCount),
    ]);
  }

  public function trackSavedQuestion(
    ?int $userId,
    int $questionId,
    ?int $examId,
    ?int $topicId,
    bool $isSaved,
    string $source = 'question'
  ): bool {
    return $this->trackEvent($isSaved ? 'saved_question_added' : 'saved_question_removed', $userId, [
      'exam_id' => $examId,
      'question_id' => $questionId,
      'topic_id' => $topicId,
      'source' => $source,
      'result' => $isSaved ? 'added' : 'removed',
    ]);
  }

  public function trackExplanationViewed(
    ?int $userId,
    ?int $examId,
    ?int $questionId,
    ?int $topicId,
    ?bool $isCorrect,
    string $source
  ): bool {
    return $this->trackEvent('explanation_viewed', $userId, [
      'exam_id' => $examId,
      'question_id' => $questionId,
      'topic_id' => $topicId,
      'is_correct' => $isCorrect,
      'source' => $source,
      'result' => $isCorrect === null ? 'unknown' : ($isCorrect ? 'correct' : 'incorrect'),
    ]);
  }

  public function trackAnswerChanged(
    ?int $userId,
    ?int $examId,
    ?int $questionId,
    ?int $topicId,
    ?string $testType,
    string $source
  ): bool {
    return $this->trackEvent('answer_changed', $userId, [
      'exam_id' => $examId,
      'question_id' => $questionId,
      'topic_id' => $topicId,
      'test_type' => $testType,
      'source' => $source,
      'result' => 'changed_before_completion',
    ]);
  }

  public function trackValidationError(
    ?int $userId,
    string $source,
    string $result,
    array $metadata = []
  ): bool {
    return $this->trackEvent('validation_error', $userId, array_merge($metadata, [
      'source' => $source,
      'result' => $this->normalizeResultCode($result),
    ]));
  }

  public function trackSettingsUpdated(?int $userId, string $result, array $metadata = []): bool
  {
    return $this->trackEvent('settings_updated', $userId, array_merge($metadata, [
      'source' => 'settings',
      'result' => $this->normalizeResultCode($result),
    ]));
  }

  public function trackEmailVerified(?int $userId, string $source): bool
  {
    return $this->trackEvent('email_verified', $userId, [
      'source' => $source,
      'result' => 'verified',
    ]);
  }

  public function trackPasswordResetRequested(?int $userId): bool
  {
    return $this->trackEvent('password_reset_requested', $userId, [
      'source' => 'forgot_password_form',
      'result' => 'accepted',
    ]);
  }

  public function trackAccountDeleted(): bool
  {
    // Po soft delete raporty celowo odcinają relację z rekordem użytkownika.
    return $this->trackEvent('account_deleted', null, [
      'source' => 'settings',
      'result' => 'soft_deleted',
    ]);
  }

  public function trackNoQuestionsLeft(
    ?int $userId,
    ?int $examId,
    ?string $testType,
    string $source,
    string $result,
    int $count = 0
  ): bool {
    return $this->trackEvent('no_questions_left', $userId, [
      'exam_id' => $examId,
      'test_type' => $testType,
      'source' => $source,
      'result' => $this->normalizeResultCode($result),
      'count' => max(0, $count),
    ]);
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
    array $answersDetails,
    ?string $testType = null
  ): bool {
    try {
      if (empty($answersDetails)) {
        return true;
      }

      $versionIds = array_values(array_unique(array_filter(array_map(
        static fn(array $detail): int => (int) ($detail['question_version_id'] ?? 0),
        $answersDetails
      ))));

      if (empty($versionIds)) {
        return true;
      }

      $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
      $rows = $this->db->fetchAll(
        "SELECT qv.id, qv.question_id, q.topic_id
         FROM question_versions qv
         JOIN questions q ON q.id = qv.question_id
         WHERE qv.id IN ($placeholders)",
        $versionIds
      );
      $versionMap = [];
      foreach ($rows as $row) {
        $versionMap[(int) $row['id']] = [
          'question_id' => (int) $row['question_id'],
          'topic_id' => (int) $row['topic_id'],
        ];
      }

      $values = [];
      $params = [];
      $timestamp = $this->getCurrentTimestamp();
      $ipAddress = $this->getIpAddress();
      $userAgent = $this->getUserAgent();
      $sessionHash = $this->getSessionHash();

      foreach ($answersDetails as $detail) {
        $versionId = (int) ($detail['question_version_id'] ?? 0);
        if (!array_key_exists('answer_id', $detail) || $detail['answer_id'] === null || !isset($versionMap[$versionId])) {
          continue;
        }

        $rawMetadata = [
          'exam_id' => $examId,
          'test_id' => $testId,
          'test_type' => $testType,
          'question_id' => $versionMap[$versionId]['question_id'],
          'topic_id' => $versionMap[$versionId]['topic_id'],
          'is_correct' => !empty($detail['is_correct']) ? 1 : 0,
        ];
        $dimensions = $this->extractEventDimensions($rawMetadata);
        $metadata = $this->sanitizeMetadata($rawMetadata);
        $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
        array_push(
          $params,
          $userId,
          'question_answered',
          $timestamp,
          $ipAddress,
          $userAgent,
          $sessionHash,
          $dimensions['exam_type_id'],
          $dimensions['question_id'],
          $dimensions['topic_id'],
          $dimensions['test_id'],
          $dimensions['source'],
          $dimensions['test_type'],
          $dimensions['auth_provider'],
          $dimensions['page_path'],
          $dimensions['search_term'],
          $dimensions['is_correct'],
          $this->encodeMetadata($metadata)
        );
      }

      if (empty($values)) {
        return true;
      }

      return $this->db->execute(
        "INSERT INTO analytics_events
         (user_id, event_type, event_date, ip_address, user_agent, session_hash,
          exam_type_id, question_id, topic_id, test_id, source, test_type,
          auth_provider, page_path, search_term, is_correct, metadata, created_at)
         VALUES " . implode(', ', $values),
        $params
      );
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Stabilny kontrakt danych panelu. Klucze legacy pozostają dla zgodności.
   */
  public function getDashboardData(array $filters = []): array
  {
    $filters = $this->normalizeFilters($filters);
    $range = $this->resolveDateRange($filters);
    $previousRange = $this->buildPreviousRange($range);

    $summary = $this->getSummaryStats($range, $filters);
    $daily = $this->getDailyAnalytics($range, $filters);
    $examActivity = $this->getExamActivity($range, $filters);
    $singleQuestionActivity = $this->getSingleQuestionActivity($range, $filters);
    $recentActivity = $this->getRecentActivity(10, $filters);
    $comparison = $this->getComparisonSummary($range, $previousRange ?? $range, $filters);
    if ($previousRange === null) {
      $comparison['previous'] = null;
      foreach ($comparison['metrics'] as &$metric) {
        $metric['previous'] = null;
        $metric['changePercent'] = null;
      }
      unset($metric);
    }
    $funnel = $this->getFunnelStats($range, $filters);
    $activeUsers = $this->getActiveUsers($range, $filters);
    $searchActivity = $this->getSearchActivity($range, $filters);
    $retention = $this->getRetentionSection($range, $filters);
    $contentQuality = $this->getContentQuality(
      $range,
      $filters,
      (int) $filters['page'],
      (int) $filters['per_page']
    );
    $abandonedTests = $this->getAbandonedTests($range, $filters);
    $userActivity = $this->getUserActivityTable(
      $range,
      $filters,
      (int) $filters['page'],
      (int) $filters['per_page']
    );

    return [
      'schemaVersion' => 5,
      'range' => $this->serializeRange($range, $previousRange),
      'filters' => $filters,
      'summary' => $summary,
      'comparison' => $comparison,
      'daily' => $daily,
      'funnel' => $funnel,
      'activeUsers' => $activeUsers,
      'searchActivity' => $searchActivity,
      'retention' => $retention,
      'examActivity' => $examActivity,
      'topicActivity' => $contentQuality['weakestTopics'],
      'contentQuality' => $contentQuality,
      'hardestQuestions' => $contentQuality['hardestQuestions'],
      'abandonedTests' => $abandonedTests,
      'userActivity' => $userActivity,
      'recentActivity' => $recentActivity,
      'exportLinks' => $this->buildExportLinks($filters),

      // Legacy aliases consumed by the current server-rendered view and JS.
      'singleQuestionActivity' => $singleQuestionActivity,
      'recentLogins' => $recentActivity['logins'],
      'overview' => ['kpis' => $comparison['metrics'], 'daily' => $daily, 'legacySummary' => $summary],
      'acquisition' => $this->getAcquisitionSection($range, $filters, $funnel, $searchActivity),
      'engagement' => $this->getEngagementSection($range, $filters, $activeUsers),
      'learning' => $this->getLearningSection(
        $range,
        $filters,
        $examActivity,
        $abandonedTests,
        $singleQuestionActivity
      ),
      'users' => $userActivity,
      'dataQuality' => [
        'sessions' => 'Sesje są liczone po anonimowym hashu sesji. Pełne pokrycie dotyczy zdarzeń zapisanych po wdrożeniu migracji analytics.',
        'dropOff' => 'Historyczny drop-off pytania jest przybliżony udziałem pominięć w ukończonych testach.',
        'retention' => 'Retencja bazuje na analytics_events i nie odtwarza aktywności sprzed uruchomienia trackingu.',
      ],
    ];
  }

  public function getFilterOptions(): array
  {
    return [
      'ranges' => [
        '7d' => 'Ostatnie 7 dni',
        '30d' => 'Ostatnie 30 dni',
        '90d' => 'Ostatnie 90 dni',
        'all' => 'Cały okres',
      ],
      'examTypes' => $this->db->fetchAll(
        'SELECT id, code, name FROM exam_types WHERE deleted_at IS NULL ORDER BY code'
      ),
      'testTypes' => [
        'all' => 'Wszystkie typy',
        'full_exam' => 'Pełny test',
        'personalized' => 'Test personalizowany',
      ],
      'authProviders' => [
        'all' => 'Wszyscy dostawcy',
        'local' => 'E-mail i hasło',
        'google' => 'Google',
      ],
      'roles' => [
        'all' => 'Wszystkie role',
        'admin' => 'Administratorzy',
        'user' => 'Użytkownicy',
      ],
      'verificationStatuses' => [
        'all' => 'Dowolna weryfikacja',
        '1' => 'Zweryfikowani',
        '0' => 'Niezweryfikowani',
      ],
      'userStatuses' => [
        'all' => 'Wszyscy użytkownicy',
        'verified' => 'Zweryfikowani',
        'unverified' => 'Niezweryfikowani',
        'admin' => 'Administratorzy',
        'user' => 'Użytkownicy',
      ],
      'eventTypes' => array_combine(self::EVENT_TYPES, self::EVENT_TYPES),
    ];
  }

  public function getExportData(string $dataset, array $filters): array
  {
    $filters = $this->normalizeFilters($filters);
    $range = $this->resolveDateRange($filters);
    $dataset = strtolower(trim($dataset));

    return match ($dataset) {
      'users' => [
        'filename' => 'analytics-users.csv',
        'headers' => ['ID', 'Imię', 'Nazwisko', 'E-mail', 'Provider', 'Rola', 'Weryfikacja', 'Status', 'Rejestracja', 'Ostatni login', 'Ostatnia aktywność', 'Testy', 'Średni wynik'],
        'rows' => array_map(static fn(array $row): array => [
          $row['id'], $row['first_name'], $row['last_name'], $row['email'], $row['auth_provider'],
          $row['role'], $row['is_verified'], $row['account_status'], $row['created_at'],
          $row['last_login'], $row['last_activity'], $row['tests_completed'], $row['average_score'],
        ], $this->getAllUsersForExport($range, $filters)),
      ],
      'exams' => [
        'filename' => 'analytics-exams.csv',
        'headers' => ['ID egzaminu', 'Kod', 'Nazwa', 'Rozpoczęte', 'Ukończone', 'Unikalni użytkownicy', 'Średni wynik %', 'Średni czas s'],
        'rows' => array_map(static fn(array $row): array => [
          $row['exam_type_id'], $row['code'], $row['name'], $row['started_tests'],
          $row['completed_tests'], $row['unique_users'], $row['avg_score'], $row['avg_duration_seconds'],
        ], $this->getExamActivity($range, $filters)),
      ],
      'questions', 'hardest_questions' => [
        'filename' => 'analytics-questions.csv',
        'headers' => ['ID pytania', 'Pytanie', 'Egzamin', 'Temat', 'Odpowiedzi', 'Błędne', 'Pominięte', 'Błędy %'],
        'rows' => array_map(static fn(array $row): array => [
          $row['question_id'], $row['question_text'], $row['exam_code'], $row['topic_name'],
          $row['total_answers'], $row['wrong_answers'], $row['skipped_answers'], $row['wrong_percent'],
        ], $this->getHardestQuestions($range, 0, $filters)),
      ],
      'events' => [
        'filename' => 'analytics-events.csv',
        'headers' => ['ID', 'Data', 'Typ', 'User ID', 'Exam ID', 'Question ID', 'Test ID', 'Źródło', 'Typ testu', 'Provider', 'Ścieżka', 'Poprawna'],
        'rows' => array_map(static fn(array $row): array => [
          $row['id'], $row['event_date'], $row['event_type'], $row['user_id'],
          $row['exam_type_id'], $row['question_id'], $row['test_id'], $row['source'],
          $row['test_type'], $row['auth_provider'], $row['page_path'], $row['is_correct'],
        ], $this->getEventExportRows($range, $filters)),
      ],
      'weakest_topics' => [
        'filename' => 'analytics-weakest-topics.csv',
        'headers' => ['ID tematu', 'Temat', 'Egzamin', 'Odpowiedzi', 'Poprawne', 'Skuteczność %'],
        'rows' => array_map(static fn(array $row): array => [
          $row['topic_id'], $row['topic_name'], $row['exam_code'], $row['total_answers'],
          $row['correct_answers'], $row['accuracy'],
        ], $this->getWeakestTopics($range, $filters, 5000)),
      ],
      'retention', 'cohorts' => [
        'filename' => 'analytics-retention-cohorts.csv',
        'headers' => ['Kohorta', 'Użytkownicy', 'D1', 'D1 %', 'D7', 'D7 %', 'D30', 'D30 %'],
        'rows' => array_map(static fn(array $row): array => [
          $row['cohort'], $row['users'], $row['d1_users'], $row['d1_rate'],
          $row['d7_users'], $row['d7_rate'], $row['d30_users'], $row['d30_rate'],
        ], $this->getRetentionCohorts($range, $filters)),
      ],
      'learning' => [
        'filename' => 'analytics-learning.csv',
        'headers' => ['Typ testu', 'Rozpoczęte', 'Ukończone', 'Completion rate %', 'Średni wynik %', 'Średni czas s'],
        'rows' => array_map(static fn(array $row): array => [
          $row['test_type'], $row['started'], $row['completed'], $row['completion_rate'],
          $row['average_score'], $row['average_duration_seconds'],
        ], $this->getLearningByTestType($range, $filters)),
      ],
      default => [
        'filename' => 'analytics-overview.csv',
        'headers' => ['Metryka', 'Wartość'],
        'rows' => array_map(
          static fn(string $key, mixed $value): array => [$key, is_scalar($value) ? $value : ''],
          array_keys($this->getOverviewMetrics($range, $filters)['summary']),
          array_values($this->getOverviewMetrics($range, $filters)['summary'])
        ),
      ],
    };
  }

  private function buildExportLinks(array $filters): array
  {
    $queryFilters = array_filter([
      'range' => $filters['range'],
      'from' => $filters['from'],
      'to' => $filters['to'],
      'exam_type_id' => $filters['exam_type_id'],
      'test_type' => $filters['test_type'],
      'auth_provider' => $filters['auth_provider'],
      'role' => $filters['role'],
      'is_verified' => $filters['is_verified'],
      'event_type' => $filters['event_type'],
      'search' => $filters['search'],
    ], static fn(mixed $value): bool => $value !== null && $value !== '');
    $baseUrl = url('admin/analytics/export');
    $links = [];

    foreach (['users', 'exams', 'questions', 'events', 'retention'] as $dataset) {
      $links[$dataset] = $baseUrl . '?' . http_build_query(array_merge(
        $queryFilters,
        ['dataset' => $dataset]
      ));
    }

    return $links;
  }

  private function getEventExportRows(array $range, array $filters): array
  {
    $where = $this->buildEventWhere($range, $filters, 'e', 'u');
    return $this->db->fetchAll(
      "SELECT e.id, e.event_date, e.event_type, e.user_id, e.exam_type_id,
              e.question_id, e.test_id, e.source, e.test_type,
              COALESCE(e.auth_provider, u.auth_provider) AS auth_provider,
              e.page_path, e.is_correct
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       ORDER BY e.event_date DESC, e.id DESC",
      $where['params']
    );
  }

  public function getSummaryStats(array $range, array $filters = []): array
  {
    $today = $this->buildTodayRange();
    $userWhere = $this->buildUserWhere($filters, 'u', false);

    $totalUsers = $this->fetchCount(
      'SELECT COUNT(*) AS total FROM users u WHERE ' . implode(' AND ', $userWhere['conditions']),
      $userWhere['params']
    );

    return array_merge([
      'totalUsers' => $totalUsers,
      'newRegistrationsToday' => $this->countRegistrations($today, $filters),
      'loginsToday' => $this->countEvents('login', $today, $filters),
      'pageViewsToday' => $this->countEvents('page_view', $today, $filters),
      'entriesToday' => $this->countEvents('entry', $today, $filters),
      'testsStartedToday' => $this->countEvents('test_started', $today, $filters),
      'testsCompletedToday' => $this->countEvents('test_completed', $today, $filters),
      'questionsAnsweredToday' => $this->countEvents('question_answered', $today, $filters),
      'averageScore' => $this->fetchAverageScore($range, $filters),
    ], $this->getSingleQuestionSummary($range, $filters));
  }

  public function getSingleQuestionSummary(array $range, array $filters = []): array
  {
    $today = $this->buildTodayRange();
    return [
      'singleQuestionStartedToday' => $this->countEvents('single_question_started', $today, $filters),
      'singleQuestionStartedInRange' => $this->countEvents('single_question_started', $range, $filters),
      'singleQuestionAnsweredToday' => $this->countEvents('single_question_answered', $today, $filters),
      'singleQuestionAccuracy' => $this->fetchSingleQuestionAccuracy($range, $filters),
    ];
  }

  public function getDailyCounts(string $eventType, array $range, array $filters = []): array
  {
    $bucket = $this->getBucketDefinition($range);
    $where = $this->buildEventWhere($range, $filters, 'e', 'u', $eventType);
    $rows = $this->db->fetchAll(
      "SELECT {$bucket['sql']} AS bucket, COUNT(*) AS total
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY bucket
       ORDER BY bucket",
      $where['params']
    );

    $map = [];
    foreach ($rows as $row) {
      $map[(string) $row['bucket']] = (int) $row['total'];
    }
    return $map;
  }

  public function getSingleQuestionDailyCounts(array $range, array $filters = []): array
  {
    return [
      'singleQuestionStarted' => $this->getDailyCounts('single_question_started', $range, $filters),
      'singleQuestionAnswered' => $this->getDailyCounts('single_question_answered', $range, $filters),
    ];
  }

  public function getDailyAnalytics(array $range, array $filters = []): array
  {
    $labels = $this->buildBucketLabels($range);
    $maps = [
      'pageViews' => $this->getDailyCounts('page_view', $range, $filters),
      'entries' => $this->getDailyCounts('entry', $range, $filters),
      'logins' => $this->getDailyCounts('login', $range, $filters),
      'registrations' => $this->getRegistrationTimeline($range, $filters),
      'testsStarted' => $this->getDailyCounts('test_started', $range, $filters),
      'testsCompleted' => $this->getDailyCounts('test_completed', $range, $filters),
      'singleQuestionStarted' => $this->getDailyCounts('single_question_started', $range, $filters),
      'singleQuestionAnswered' => $this->getDailyCounts('single_question_answered', $range, $filters),
    ];

    $result = ['labels' => $labels, 'granularity' => $this->getBucketDefinition($range)['name']];
    foreach (array_keys($maps) as $key) {
      $result[$key] = array_map(static fn(string $label): int => (int) ($maps[$key][$label] ?? 0), $labels);
    }
    return $result;
  }

  public function getDailyOverview(array $range): array
  {
    return $this->getDailyAnalytics($range);
  }

  public function getExamActivity(array $range, array $filters = []): array
  {
    $conditions = [
      '(a.user_id IS NULL OR u.deleted_at IS NULL)',
      '(a.started_at BETWEEN ? AND ? OR a.completed_at BETWEEN ? AND ?)',
    ];
    $params = [
      $this->rangeStart($range),
      $this->rangeEnd($range),
      $this->rangeStart($range),
      $this->rangeEnd($range),
    ];
    $dimensions = $this->buildAttemptDimensionConditions($filters, 'a');
    $conditions = array_merge($conditions, $dimensions['conditions']);
    $params = array_merge($params, $dimensions['params']);
    if ($filters['auth_provider'] !== '') {
      $conditions[] = 'u.auth_provider = ?';
      $params[] = $filters['auth_provider'];
    }
    $this->appendUserFilterConditions($conditions, $params, $filters, 'u');
    $start = $this->rangeStart($range);
    $end = $this->rangeEnd($range);

    return $this->db->fetchAll(
      "SELECT a.exam_type_id, et.code, et.name,
              COUNT(CASE WHEN a.started_at BETWEEN ? AND ? THEN 1 END) AS started_tests,
              COUNT(CASE WHEN a.completed_at BETWEEN ? AND ? THEN 1 END) AS completed_tests,
              ROUND(AVG(CASE
                WHEN a.completed_at BETWEEN ? AND ? AND a.total_questions > 0
                THEN a.correct_count * 100.0 / a.total_questions
              END), 1) AS avg_score,
              COUNT(DISTINCT CASE
                WHEN a.started_at BETWEEN ? AND ? OR a.completed_at BETWEEN ? AND ?
                THEN a.user_id
              END) AS unique_users,
              ROUND(AVG(CASE
                WHEN a.completed_at BETWEEN ? AND ?
                THEN TIMESTAMPDIFF(SECOND, a.started_at, a.completed_at)
              END), 0) AS avg_duration_seconds
       FROM attempts a
       JOIN exam_types et ON et.id = a.exam_type_id
       LEFT JOIN users u ON u.id = a.user_id
       WHERE " . implode(' AND ', $conditions) . "
       GROUP BY a.exam_type_id, et.code, et.name
       ORDER BY completed_tests DESC, started_tests DESC, et.code",
      array_merge([
        $start, $end,
        $start, $end,
        $start, $end,
        $start, $end, $start, $end,
        $start, $end,
      ], $params)
    );
  }

  public function getSingleQuestionActivity(array $range, array $filters = []): array
  {
    $where = $this->buildEventWhere($range, array_merge($filters, ['test_type' => '']), 'e', 'u');
    $where['conditions'][] = "e.event_type IN ('single_question_started', 'single_question_answered')";

    return $this->db->fetchAll(
      "SELECT e.exam_type_id AS exam_id,
              COALESCE(et.code, '') AS code,
              COALESCE(et.name, 'Brak przypisanego egzaminu') AS name,
              SUM(e.event_type = 'single_question_started') AS started_count,
              SUM(e.event_type = 'single_question_answered') AS answered_count,
              SUM(e.event_type = 'single_question_answered' AND e.user_id IS NULL) AS guest_answered_count,
              COUNT(DISTINCT CASE WHEN e.event_type = 'single_question_answered'
                THEN COALESCE(e.session_hash, CONCAT('user-', e.user_id)) END) AS sessions,
              COUNT(DISTINCT e.user_id) AS unique_users,
              ROUND(AVG(CASE WHEN e.event_type = 'single_question_answered' THEN e.is_correct END) * 100, 1) AS accuracy,
              (
                SELECT qv.question_text
                FROM analytics_events top_e
                JOIN question_versions qv ON qv.question_id = top_e.question_id
                  AND qv.is_active = 1 AND qv.deleted_at IS NULL
                WHERE top_e.event_type = 'single_question_answered'
                  AND top_e.event_date BETWEEN ? AND ?
                  AND top_e.exam_type_id <=> e.exam_type_id
                GROUP BY top_e.question_id, qv.question_text
                ORDER BY COUNT(*) DESC
                LIMIT 1
              ) AS top_question_text
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       LEFT JOIN exam_types et ON et.id = e.exam_type_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY e.exam_type_id, et.code, et.name
       ORDER BY started_count DESC, answered_count DESC",
      array_merge([$this->rangeStart($range), $this->rangeEnd($range)], $where['params'])
    );
  }

  public function getHardestQuestions(array $range, int $limit = 10, array $filters = []): array
  {
    $limitSql = $limit > 0 ? 'LIMIT ' . min(5000, $limit) : '';
    $where = $this->buildAttemptWhere($range, $filters, 'a', 'u', 'completed_at');

    return $this->db->fetchAll(
      "SELECT q.id AS question_id,
              MAX(qv.question_text) AS question_text,
              et.code AS exam_code,
              et.name AS exam_name,
              t.name AS topic_name,
              COUNT(*) AS total_answers,
              SUM(aa.is_correct = 0) AS wrong_answers,
              SUM(aa.answer_id IS NULL) AS skipped_answers,
              ROUND(SUM(aa.is_correct = 0) * 100.0 / COUNT(*), 1) AS wrong_percent
       FROM attempt_answers aa
       JOIN attempts a ON a.id = aa.attempt_id
       JOIN question_versions qv ON qv.id = aa.question_version_id
       JOIN questions q ON q.id = qv.question_id
       JOIN exam_types et ON et.id = q.exam_type_id
       JOIN topics t ON t.id = q.topic_id
       LEFT JOIN users u ON u.id = a.user_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY q.id, et.code, et.name, t.name
       HAVING total_answers >= 3
       ORDER BY wrong_percent DESC, total_answers DESC
       $limitSql",
      $where['params']
    );
  }

  public function getRecentActivity(int $limit = 10, array $filters = []): array
  {
    return [
      'logins' => $this->getRecentLogins($limit, $filters),
      'registrations' => $this->getRecentRegistrations($limit, $filters),
      'testsCompleted' => $this->getRecentCompletedTests($limit, $filters),
    ];
  }

  public function getOverviewMetrics(array $range, array $filters = []): array
  {
    return [
      'summary' => $this->getSummaryStats($range, $filters),
      'daily' => $this->getDailyAnalytics($range, $filters),
      'examActivity' => $this->getExamActivity($range, $filters),
      'singleQuestionActivity' => $this->getSingleQuestionActivity($range, $filters),
      'hardestQuestions' => $this->getHardestQuestions($range, 10, $filters),
      'recentActivity' => $this->getRecentActivity(10, $filters),
    ];
  }

  public function getRecentLogins(int $limit = 10, array $filters = []): array
  {
    $range = $this->resolveDateRange($filters);
    $where = $this->buildEventWhere($range, $filters, 'e', 'u', 'login');
    $rows = $this->db->fetchAll(
      "SELECT e.id, e.event_date, e.user_id, e.ip_address, e.user_agent,
              u.first_name, u.last_name, u.email, COALESCE(e.auth_provider, u.auth_provider) AS auth_provider
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       ORDER BY e.event_date DESC, e.id DESC
       LIMIT " . max(1, min(50, $limit)),
      $where['params']
    );
    foreach ($rows as &$row) {
      $row['browser_label'] = $this->simplifyUserAgent((string) ($row['user_agent'] ?? ''));
      $row['display_name'] = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')) ?: 'Użytkownik nieznany';
    }
    return $rows;
  }

  public function resolveDateRangeFromRequest(array $request): array
  {
    return $this->serializeRange($this->resolveDateRange($this->normalizeFilters($request)));
  }

  public function getComparisonSummary(
    array $currentRange,
    array $previousRange,
    array $filters = []
  ): array {
    $filters = $this->normalizeFilters($filters);
    $buildSummary = function (array $range) use ($filters): array {
      // Starty i ukończenia liczone z tego samego źródła (zdarzeń), z podziałem
      // na gości i zalogowanych - wcześniej ukończenia brano z tabeli attempts,
      // do której trafiają tylko zalogowani, więc completion rate był zaniżony.
      $started = $this->countEventsByAudience('test_started', $range, $filters);
      $completed = $this->countEventsByAudience('test_completed', $range, $filters);
      $singleAnswered = $this->countEventsByAudience('single_question_answered', $range, $filters);

      return [
        'newUsers' => $this->countRegistrations($range, $filters),
        'activeUsers' => $this->countActiveUsers($range, $filters),
        'pageViews' => $this->countEvents('page_view', $range, $filters),
        'entries' => $this->countEvents('entry', $range, $filters),
        'testsStarted' => $started['total'],
        'testsStartedGuests' => $started['guests'],
        'testsStartedLoggedIn' => $started['loggedIn'],
        'testsCompleted' => $completed['total'],
        'testsCompletedGuests' => $completed['guests'],
        'testsCompletedLoggedIn' => $completed['loggedIn'],
        'completionRate' => $this->percentage($completed['total'], $started['total']),
        // Tryb jednego pytania - główny sposób nauki (wielokrotnie więcej odpowiedzi niż testów).
        'singleQuestionAnswered' => $singleAnswered['total'],
        'singleQuestionAnsweredGuests' => $singleAnswered['guests'],
        'singleQuestionAnsweredLoggedIn' => $singleAnswered['loggedIn'],
        'singleQuestionSessions' => $this->countEventSessions('single_question_answered', $range, $filters),
        'averageScore' => $this->fetchAverageScore($range, $filters),
      ];
    };

    $current = $buildSummary($currentRange);
    $previous = $buildSummary($previousRange);
    $metrics = [];

    foreach ($current as $key => $value) {
      $metrics[$key] = [
        'value' => $value,
        'previous' => $previous[$key],
        'changePercent' => $this->changePercent((float) $value, (float) $previous[$key]),
      ];
    }

    return [
      'current' => $current,
      'previous' => $previous,
      'metrics' => $metrics,
    ];
  }

  public function getFunnelStats(array $range, array $filters = []): array
  {
    $filters = $this->normalizeFilters($filters);
    $eventTypes = ['page_view', 'registration', 'login', 'test_started', 'test_completed'];
    $stages = [];
    $firstActors = null;
    $previousActors = null;

    foreach ($eventTypes as $eventType) {
      $where = $this->buildEventWhere($range, $filters, 'e', 'u', $eventType);
      $row = $this->db->fetch(
        "SELECT COUNT(*) AS events,
                COUNT(DISTINCT COALESCE(
                  IF(e.user_id IS NOT NULL, CONCAT('u:', e.user_id), NULL),
                  IF(e.session_hash IS NOT NULL, CONCAT('s:', e.session_hash), NULL),
                  CONCAT('e:', e.id)
                )) AS actors
         FROM analytics_events e
         LEFT JOIN users u ON u.id = e.user_id
         WHERE " . implode(' AND ', $where['conditions']),
        $where['params']
      ) ?: [];

      $actors = (int) ($row['actors'] ?? 0);
      $firstActors ??= $actors;
      $stages[] = [
        'eventType' => $eventType,
        'events' => (int) ($row['events'] ?? 0),
        'actors' => $actors,
        'conversionFromPrevious' => $previousActors === null ? 100.0 : $this->percentage($actors, $previousActors),
        'conversionFromFirst' => $this->percentage($actors, $firstActors),
      ];
      $previousActors = $actors;
    }

    return ['stages' => $stages];
  }

  public function getActiveUsers(array $range, array $filters = []): array
  {
    $filters = $this->normalizeFilters($filters);
    $end = $range['end'];
    $dauRange = ['start' => $end->setTime(0, 0), 'end' => $end];
    $wauRange = ['start' => $end->sub(new DateInterval('P6D'))->setTime(0, 0), 'end' => $end];
    $mauRange = ['start' => $end->sub(new DateInterval('P29D'))->setTime(0, 0), 'end' => $end];
    $dau = $this->countActiveUsers($dauRange, $filters);
    $wau = $this->countActiveUsers($wauRange, $filters);
    $mau = $this->countActiveUsers($mauRange, $filters);

    return [
      'dau' => $dau,
      'wau' => $wau,
      'mau' => $mau,
      'dauMauStickiness' => $this->percentage($dau, $mau),
      'activeUsersInRange' => $this->countActiveUsers($range, $filters),
    ];
  }

  public function getSearchActivity(array $range, array $filters = []): array
  {
    $filters = $this->normalizeFilters($filters);
    $where = $this->buildEventWhere($range, $filters, 'e', 'u', 'search_performed');
    $summary = $this->db->fetch(
      "SELECT COUNT(*) AS searches,
              COUNT(DISTINCT e.user_id) AS unique_users,
              SUM(e.search_term IS NOT NULL) AS searches_with_safe_term,
              SUM(e.search_term IS NULL) AS searches_without_stored_term
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']),
      $where['params']
    ) ?: [];

    $termWhere = $where;
    $termWhere['conditions'][] = 'e.search_term IS NOT NULL';
    $topQueries = $this->db->fetchAll(
      "SELECT e.search_term AS query, COUNT(*) AS searches,
              COUNT(DISTINCT e.user_id) AS unique_users
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $termWhere['conditions']) . "
       GROUP BY e.search_term
       ORDER BY searches DESC, e.search_term
       LIMIT 25",
      $termWhere['params']
    );

    return [
      'summary' => [
        'searches' => (int) ($summary['searches'] ?? 0),
        'uniqueUsers' => (int) ($summary['unique_users'] ?? 0),
        'searchesWithSafeTerm' => (int) ($summary['searches_with_safe_term'] ?? 0),
        'searchesWithoutStoredTerm' => (int) ($summary['searches_without_stored_term'] ?? 0),
      ],
      'topQueries' => $topQueries,
    ];
  }

  public function getSavedQuestionActivity(array $range, array $filters = []): array
  {
    $filters = $this->normalizeFilters($filters);
    $where = $this->buildEventWhere(
      $range,
      array_merge($filters, ['event_type' => '']),
      'e',
      'u'
    );
    $where['conditions'][] = "e.event_type IN ('saved_question_added', 'saved_question_removed')";

    $summary = $this->db->fetch(
      "SELECT SUM(e.event_type = 'saved_question_added') AS added,
              SUM(e.event_type = 'saved_question_removed') AS removed,
              COUNT(DISTINCT e.user_id) AS unique_users
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']),
      $where['params']
    ) ?: [];

    $byExamTopic = $this->db->fetchAll(
      "SELECT e.exam_type_id AS exam_id, COALESCE(et.code, '') AS exam_code,
              e.topic_id, COALESCE(t.name, 'Brak tematu') AS topic_name,
              SUM(e.event_type = 'saved_question_added') AS added,
              SUM(e.event_type = 'saved_question_removed') AS removed,
              COUNT(DISTINCT e.user_id) AS unique_users
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       LEFT JOIN exam_types et ON et.id = e.exam_type_id
       LEFT JOIN topics t ON t.id = e.topic_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY e.exam_type_id, et.code, e.topic_id, t.name
       ORDER BY added DESC, removed DESC
       LIMIT 50",
      $where['params']
    );

    return [
      'summary' => [
        'added' => (int) ($summary['added'] ?? 0),
        'removed' => (int) ($summary['removed'] ?? 0),
        'uniqueUsers' => (int) ($summary['unique_users'] ?? 0),
      ],
      'byExamTopic' => $byExamTopic,
    ];
  }

  public function getExplanationInsights(array $range, array $filters = []): array
  {
    $filters = $this->normalizeFilters($filters);
    $where = $this->buildEventWhere($range, $filters, 'e', 'u', 'explanation_viewed');
    $row = $this->db->fetch(
      "SELECT COUNT(*) AS views,
              COUNT(DISTINCT e.user_id) AS unique_users,
              SUM(e.is_correct = 1) AS after_correct,
              SUM(e.is_correct = 0) AS after_incorrect,
              SUM(e.is_correct IS NULL) AS correctness_unknown
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']),
      $where['params']
    ) ?: [];

    $views = (int) ($row['views'] ?? 0);
    $afterCorrect = (int) ($row['after_correct'] ?? 0);
    $afterIncorrect = (int) ($row['after_incorrect'] ?? 0);

    return [
      'views' => $views,
      'uniqueUsers' => (int) ($row['unique_users'] ?? 0),
      'afterCorrect' => $afterCorrect,
      'afterIncorrect' => $afterIncorrect,
      'correctnessUnknown' => (int) ($row['correctness_unknown'] ?? 0),
      'afterIncorrectRate' => $this->percentage($afterIncorrect, $afterCorrect + $afterIncorrect),
    ];
  }

  public function getChurnRisk(array $filters = []): array
  {
    $filters = $this->normalizeFilters($filters);
    return [
      'inactive7d' => $this->countInactiveUsers($filters, 7),
      'inactive14d' => $this->countInactiveUsers($filters, 14),
      'inactive30d' => $this->countInactiveUsers($filters, 30),
      'users' => $this->getInactiveUsers($filters, 14, 25),
    ];
  }

  public function getAbandonedTests(array $range, array $filters = []): array
  {
    $filters = $this->normalizeFilters($filters);
    $startedWhere = $this->buildEventWhere($range, $filters, 's', 'u', 'test_started');
    $startedWhere['conditions'][] = '(s.user_id IS NOT NULL OR s.session_hash IS NOT NULL)';
    $maturedEnd = min(
      $range['end']->getTimestamp(),
      (new DateTimeImmutable('now'))->sub(new DateInterval('PT2H'))->getTimestamp()
    );
    $startedWhere['conditions'][] = 's.event_date <= ?';
    $startedWhere['params'][] = (new DateTimeImmutable('@' . $maturedEnd))
      ->setTimezone($range['end']->getTimezone())
      ->format('Y-m-d H:i:s');

    $actorMatch = "(
      (s.user_id IS NOT NULL AND outcome.user_id = s.user_id)
      OR (
        s.user_id IS NULL
        AND outcome.user_id IS NULL
        AND outcome.session_hash = s.session_hash
      )
    )";
    $outcomeMatch = "outcome.event_type IN ('test_completed', 'test_exited')
      AND outcome.event_date BETWEEN s.event_date AND DATE_ADD(s.event_date, INTERVAL 6 HOUR)
      AND outcome.exam_type_id <=> s.exam_type_id
      AND outcome.test_type <=> s.test_type
      AND $actorMatch";

    $inferredRows = $this->db->fetchAll(
      "SELECT COALESCE(s.test_type, 'unknown') AS test_type,
              COUNT(*) AS started,
              SUM(EXISTS(
                SELECT 1 FROM analytics_events outcome
                WHERE outcome.event_type = 'test_completed'
                  AND outcome.event_date BETWEEN s.event_date AND DATE_ADD(s.event_date, INTERVAL 6 HOUR)
                  AND outcome.exam_type_id <=> s.exam_type_id
                  AND outcome.test_type <=> s.test_type
                  AND $actorMatch
              )) AS matched_completed,
              SUM(NOT EXISTS(
                SELECT 1 FROM analytics_events outcome WHERE $outcomeMatch
              )) AS inferred_abandoned
       FROM analytics_events s
       LEFT JOIN users u ON u.id = s.user_id
       WHERE " . implode(' AND ', $startedWhere['conditions']) . "
       GROUP BY COALESCE(s.test_type, 'unknown')",
      $startedWhere['params']
    );

    $exitWhere = $this->buildEventWhere($range, $filters, 'e', 'u', 'test_exited');
    $exitRows = $this->db->fetchAll(
      "SELECT COALESCE(e.test_type, 'unknown') AS test_type, COUNT(*) AS explicit_exits
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $exitWhere['conditions']) . "
       GROUP BY COALESCE(e.test_type, 'unknown')",
      $exitWhere['params']
    );

    $byType = [];
    foreach ($inferredRows as $row) {
      $type = (string) $row['test_type'];
      $byType[$type] = [
        'test_type' => $type,
        'started' => (int) $row['started'],
        'matched_completed' => (int) $row['matched_completed'],
        'explicit_exits' => 0,
        'inferred_abandoned' => (int) $row['inferred_abandoned'],
      ];
    }
    foreach ($exitRows as $row) {
      $type = (string) $row['test_type'];
      $byType[$type] ??= [
        'test_type' => $type,
        'started' => 0,
        'matched_completed' => 0,
        'explicit_exits' => 0,
        'inferred_abandoned' => 0,
      ];
      $byType[$type]['explicit_exits'] = (int) $row['explicit_exits'];
    }

    $rows = array_values($byType);
    $summary = [
      'started' => 0,
      'matchedCompleted' => 0,
      'explicitExits' => 0,
      'inferredAbandoned' => 0,
      'abandoned' => 0,
      'abandonmentRate' => 0.0,
    ];
    foreach ($rows as &$row) {
      $row['abandoned'] = $row['explicit_exits'] + $row['inferred_abandoned'];
      $row['abandonment_rate'] = $this->percentage(
        $row['abandoned'],
        max($row['started'], $row['abandoned'])
      );
      $summary['started'] += $row['started'];
      $summary['matchedCompleted'] += $row['matched_completed'];
      $summary['explicitExits'] += $row['explicit_exits'];
      $summary['inferredAbandoned'] += $row['inferred_abandoned'];
      $summary['abandoned'] += $row['abandoned'];
    }
    unset($row);
    usort($rows, static fn(array $a, array $b): int => $b['abandoned'] <=> $a['abandoned']);
    $summary['abandonmentRate'] = $this->percentage(
      $summary['abandoned'],
      max($summary['started'], $summary['abandoned'])
    );

    return [
      'summary' => $summary,
      'byTestType' => $rows,
      'method' => 'Porzucenia łączą jawne wyjścia z testu z dojrzałymi startami bez ukończenia lub wyjścia w ciągu 6 godzin. Ostatnie 2 godziny są pomijane.',
    ];
  }

  public function getContentQuality(
    array $range,
    array $filters = [],
    int $page = 1,
    int $perPage = 25
  ): array
  {
    $filters = $this->normalizeFilters(array_merge($filters, [
      'page' => max(1, $page),
      'per_page' => min(100, max(10, $perPage)),
    ]));
    return array_merge($this->getContentQualityTable($range, $filters), [
      'hardestQuestions' => $this->getHardestQuestions($range, 15, $filters),
      'weakestTopics' => $this->getWeakestTopics($range, $filters),
      'mostSavedQuestions' => $this->getMostSavedQuestions($range, $filters),
      'savedQuestionActivity' => $this->getSavedQuestionActivity($range, $filters),
      'explanationInsights' => $this->getExplanationInsights($range, $filters),
      'dropOffQuestions' => $this->getDropOffQuestions($range, $filters),
    ]);
  }

  public function getUserActivityTable(
    array $range,
    array $filters = [],
    int $page = 1,
    int $perPage = 25
  ): array {
    $filters = $this->normalizeFilters(array_merge($filters, [
      'page' => max(1, $page),
      'per_page' => min(100, max(10, $perPage)),
    ]));

    return $this->getUsersSection($range, $filters);
  }

  private function getOverviewSection(
    array $range,
    ?array $previousRange,
    array $filters,
    array $summary,
    array $daily
  ): array {
    $comparison = $this->getComparisonSummary($range, $previousRange ?? $range, $filters);
    $kpis = $comparison['metrics'];
    if ($previousRange === null) {
      foreach ($kpis as &$metric) {
        $metric['previous'] = null;
        $metric['changePercent'] = null;
      }
      unset($metric);
    }

    return ['kpis' => $kpis, 'daily' => $daily, 'legacySummary' => $summary];
  }

  private function getAcquisitionSection(
    array $range,
    array $filters,
    ?array $funnel = null,
    ?array $searchActivity = null
  ): array
  {
    return [
      'summary' => [
        'registrations' => $this->countRegistrations($range, $filters),
        'logins' => $this->countEvents('login', $range, $filters),
      ],
      'registrationsTrend' => $this->timelineToSeries($this->getRegistrationTimeline($range, $filters), $range),
      'authProviders' => $this->getAuthProviderBreakdown($range, $filters),
      'sources' => $this->getEventDimensionBreakdown($range, $filters, 'source', 'page_view', 15),
      'entryPaths' => $this->getEventDimensionBreakdown($range, $filters, 'page_path', 'page_view', 20),
      'funnel' => $funnel ?? $this->getFunnelStats($range, $filters),
      'searches' => $searchActivity ?? $this->getSearchActivity($range, $filters),
    ];
  }

  private function getEngagementSection(
    array $range,
    array $filters,
    ?array $activeUsers = null
  ): array
  {
    $activeUsers ??= $this->getActiveUsers($range, $filters);
    $sessions = $this->getSessionStats($range, $filters);

    return [
      'summary' => [
        ...$activeUsers,
        'pageViews' => $this->countEvents('page_view', $range, $filters),
        'sessions' => $sessions['sessions'],
        'sessionCoveragePercent' => $sessions['coverage'],
      ],
      'activeUsersTrend' => $this->getActiveUsersTimeline($range, $filters),
      'topPaths' => $this->getEventDimensionBreakdown($range, $filters, 'page_path', 'page_view', 25),
      'eventTypes' => $this->getEventTypeBreakdown($range, $filters),
    ];
  }

  private function getLearningSection(
    array $range,
    array $filters,
    array $examActivity,
    ?array $abandonedTests = null,
    ?array $singleQuestionActivity = null
  ): array
  {
    $started = $this->countEvents('test_started', $range, $filters);
    $completed = $this->countEvents('test_completed', $range, $filters);
    $abandonedTests ??= $this->getAbandonedTests($range, $filters);
    return [
      'summary' => [
        'started' => $started,
        'completed' => $completed,
        'completionRate' => $this->percentage($completed, $started),
        'abandoned' => $abandonedTests['summary']['abandoned'],
        'abandonmentRate' => $abandonedTests['summary']['abandonmentRate'],
        'averageScore' => $this->fetchAverageScore($range, $filters),
        'averageDurationSeconds' => $this->fetchAverageDuration($range, $filters),
        'uniqueLearners' => $this->countAttemptUsers($range, $filters),
      ],
      'byTestType' => $this->getLearningByTestType($range, $filters),
      'byExam' => $examActivity,
      'singleQuestion' => $singleQuestionActivity ?? $this->getSingleQuestionActivity($range, $filters),
      'abandonedTests' => $abandonedTests,
    ];
  }

  private function getRetentionSection(array $range, array $filters): array
  {
    $cohorts = $this->getRetentionCohorts($range, $filters);
    $churnRisk = $this->getChurnRisk($filters);
    $totals = [];
    foreach (['d1', 'd7', 'd30'] as $window) {
      $totals[$window] = ['users' => 0, 'eligible' => 0];
      foreach ($cohorts as $row) {
        $totals[$window]['users'] += $row[$window . '_users'];
        $totals[$window]['eligible'] += $row[$window . '_eligible'];
      }
    }

    return [
      'summary' => [
        'd1Rate' => $this->percentage($totals['d1']['users'], $totals['d1']['eligible']),
        'd7Rate' => $this->percentage($totals['d7']['users'], $totals['d7']['eligible']),
        'd30Rate' => $this->percentage($totals['d30']['users'], $totals['d30']['eligible']),
        'inactiveUsers7d' => $churnRisk['inactive7d'],
        'inactiveUsers14d' => $churnRisk['inactive14d'],
        'inactiveUsers30d' => $churnRisk['inactive30d'],
      ],
      'cohorts' => $cohorts,
      'inactiveUsers' => $churnRisk['users'],
      'churnRisk' => $churnRisk,
    ];
  }

  private function getUsersSection(array $range, array $filters, int $maxPerPage = 100): array
  {
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = min($maxPerPage, max(10, (int) ($filters['per_page'] ?? 25)));
    if ($maxPerPage > 100) {
      $perPage = $maxPerPage;
    }
    $offset = ($page - 1) * $perPage;
    $userWhere = $this->buildUserWhere($filters, 'u', true);
    $attemptWhere = $this->buildAttemptDimensionConditions($filters, 'a');
    $attemptSql = empty($attemptWhere['conditions']) ? '1 = 1' : implode(' AND ', $attemptWhere['conditions']);

    $total = $this->fetchCount(
      'SELECT COUNT(*) AS total FROM users u WHERE ' . implode(' AND ', $userWhere['conditions']),
      $userWhere['params']
    );

    $params = array_merge(
      [$this->rangeStart($range), $this->rangeEnd($range)],
      $attemptWhere['params'],
      $userWhere['params']
    );
    $rows = $this->db->fetchAll(
      "SELECT u.id, u.first_name, u.last_name, u.email, u.auth_provider, u.role,
              u.is_verified, u.is_active, u.deleted_at, u.created_at,
              MAX(CASE WHEN e.event_type = 'login' THEN e.event_date END) AS last_login,
              GREATEST(
                COALESCE(MAX(e.event_date), '1970-01-01'),
                COALESCE(MAX(a.completed_at), '1970-01-01'),
                u.updated_at
              ) AS last_activity,
              COUNT(DISTINCT CASE WHEN a.completed_at BETWEEN ? AND ? AND $attemptSql THEN a.id END) AS tests_completed,
              ROUND(AVG(CASE
                WHEN a.completed_at BETWEEN ? AND ? AND $attemptSql AND a.total_questions > 0
                THEN a.correct_count * 100.0 / a.total_questions
              END), 1) AS average_score
       FROM users u
       LEFT JOIN analytics_events e ON e.user_id = u.id
       LEFT JOIN attempts a ON a.user_id = u.id
       WHERE " . implode(' AND ', $userWhere['conditions']) . "
       GROUP BY u.id
       ORDER BY last_activity DESC, u.id DESC
       LIMIT $perPage OFFSET $offset",
      array_merge(
        [$this->rangeStart($range), $this->rangeEnd($range)],
        $attemptWhere['params'],
        [$this->rangeStart($range), $this->rangeEnd($range)],
        $attemptWhere['params'],
        $userWhere['params']
      )
    );

    foreach ($rows as &$row) {
      $row['account_status'] = $row['deleted_at'] !== null
        ? 'deleted'
        : ((int) $row['is_active'] === 1 ? 'active' : 'inactive');
      $row['tests_completed'] = (int) $row['tests_completed'];
      $row['average_score'] = (float) ($row['average_score'] ?? 0);
    }

    return [
      'items' => $rows,
      'page' => $page,
      'perPage' => $perPage,
      'total' => $total,
      'totalPages' => max(1, (int) ceil($total / $perPage)),
    ];
  }

  private function getAllUsersForExport(array $range, array $filters): array
  {
    $rows = [];
    $page = 1;

    do {
      $result = $this->getUsersSection(
        $range,
        array_merge($filters, ['page' => $page, 'per_page' => 1000]),
        1000
      );
      array_push($rows, ...$result['items']);
      $page++;
    } while ($page <= $result['totalPages']);

    return $rows;
  }

  private function getContentQualityTable(array $range, array $filters): array
  {
    $page = max(1, (int) $filters['page']);
    $perPage = min(100, max(10, (int) $filters['per_page']));
    $offset = ($page - 1) * $perPage;
    $where = $this->buildAttemptWhere($range, $filters, 'a', 'u', 'completed_at');
    $baseFrom = "FROM attempt_answers aa
      JOIN attempts a ON a.id = aa.attempt_id
      JOIN question_versions qv ON qv.id = aa.question_version_id
      JOIN questions q ON q.id = qv.question_id
      JOIN exam_types et ON et.id = q.exam_type_id
      JOIN topics t ON t.id = q.topic_id
      LEFT JOIN users u ON u.id = a.user_id
      WHERE " . implode(' AND ', $where['conditions']);

    $total = $this->fetchCount(
      "SELECT COUNT(*) AS total
       FROM (
         SELECT q.id
         $baseFrom
         GROUP BY q.id
         HAVING COUNT(*) >= 3
       ) quality_rows",
      $where['params']
    );

    $items = $this->db->fetchAll(
      "SELECT q.id AS question_id,
              MAX(qv.question_text) AS question_text,
              et.code AS exam_code,
              et.name AS exam_name,
              t.name AS topic_name,
              COUNT(*) AS total_answers,
              SUM(aa.is_correct = 1) AS correct_answers,
              SUM(aa.is_correct = 0) AS wrong_answers,
              SUM(aa.answer_id IS NULL) AS skipped_answers,
              ROUND(AVG(aa.is_correct) * 100, 1) AS accuracy,
              ROUND(SUM(aa.is_correct = 0) * 100.0 / COUNT(*), 1) AS wrong_percent,
              ROUND(SUM(aa.answer_id IS NULL) * 100.0 / COUNT(*), 1) AS drop_off_percent
       $baseFrom
       GROUP BY q.id, et.code, et.name, t.name
       HAVING total_answers >= 3
       ORDER BY wrong_percent DESC, total_answers DESC, q.id
       LIMIT $perPage OFFSET $offset",
      $where['params']
    );

    return [
      'items' => $items,
      'page' => $page,
      'perPage' => $perPage,
      'total' => $total,
      'totalPages' => max(1, (int) ceil($total / $perPage)),
    ];
  }

  private function getAuthProviderBreakdown(array $range, array $filters): array
  {
    $where = $this->buildUserWhere($filters, 'u', false);
    $where['conditions'][] = 'u.created_at BETWEEN ? AND ?';
    array_push($where['params'], $this->rangeStart($range), $this->rangeEnd($range));
    return $this->db->fetchAll(
      "SELECT u.auth_provider AS label, COUNT(*) AS total, COUNT(*) AS unique_users
       FROM users u
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY u.auth_provider ORDER BY total DESC",
      $where['params']
    );
  }

  private function getEventDimensionBreakdown(
    array $range,
    array $filters,
    string $column,
    string $eventType,
    int $limit
  ): array {
    $allowed = ['source', 'page_path'];
    if (!in_array($column, $allowed, true)) {
      return [];
    }
    $where = $this->buildEventWhere($range, $filters, 'e', 'u', $eventType);
    $where['conditions'][] = "e.$column IS NOT NULL";
    return $this->db->fetchAll(
      "SELECT e.$column AS label, COUNT(*) AS total, COUNT(DISTINCT e.user_id) AS unique_users
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY e.$column
       ORDER BY total DESC
       LIMIT " . max(1, min(100, $limit)),
      $where['params']
    );
  }

  private function getEventTypeBreakdown(array $range, array $filters): array
  {
    $where = $this->buildEventWhere($range, array_merge($filters, ['event_type' => '']), 'e', 'u');
    return $this->db->fetchAll(
      "SELECT e.event_type AS label, COUNT(*) AS total, COUNT(DISTINCT e.user_id) AS unique_users
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY e.event_type ORDER BY total DESC",
      $where['params']
    );
  }

  private function getSessionStats(array $range, array $filters): array
  {
    $where = $this->buildEventWhere($range, $filters, 'e', 'u');
    $row = $this->db->fetch(
      "SELECT COUNT(DISTINCT e.session_hash) AS sessions,
              COUNT(*) AS events,
              SUM(e.session_hash IS NOT NULL) AS session_events
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']),
      $where['params']
    ) ?: [];
    return [
      'sessions' => (int) ($row['sessions'] ?? 0),
      'coverage' => $this->percentage((int) ($row['session_events'] ?? 0), (int) ($row['events'] ?? 0)),
    ];
  }

  private function getActiveUsersTimeline(array $range, array $filters): array
  {
    $bucket = $this->getBucketDefinition($range);
    $where = $this->buildEventWhere($range, $filters, 'e', 'u');
    $where['conditions'][] = 'e.user_id IS NOT NULL';
    $rows = $this->db->fetchAll(
      "SELECT {$bucket['sql']} AS bucket, COUNT(DISTINCT e.user_id) AS total
       FROM analytics_events e
       LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY bucket ORDER BY bucket",
      $where['params']
    );
    $map = [];
    foreach ($rows as $row) {
      $map[(string) $row['bucket']] = (int) $row['total'];
    }
    return $this->timelineToSeries($map, $range);
  }

  private function getLearningByTestType(array $range, array $filters): array
  {
    $rows = [];
    foreach (self::TEST_TYPES as $testType) {
      if ($filters['test_type'] !== '' && $filters['test_type'] !== $testType) {
        continue;
      }
      $scoped = array_merge($filters, ['test_type' => $testType]);
      $started = $this->countEvents('test_started', $range, $scoped);
      $completed = $this->countEvents('test_completed', $range, $scoped);
      $rows[] = [
        'test_type' => $testType,
        'started' => $started,
        'completed' => $completed,
        'completion_rate' => $this->percentage($completed, $started),
        'average_score' => $this->fetchAverageScore($range, $scoped),
        'average_duration_seconds' => $this->fetchAverageDuration($range, $scoped),
      ];
    }
    return $rows;
  }

  private function getWeakestTopics(array $range, array $filters, int $limit = 20): array
  {
    $where = $this->buildAttemptWhere($range, $filters, 'a', 'u', 'completed_at');
    return $this->db->fetchAll(
      "SELECT t.id AS topic_id, t.name AS topic_name, et.code AS exam_code,
              COUNT(*) AS total_answers,
              SUM(aa.is_correct = 1) AS correct_answers,
              ROUND(AVG(aa.is_correct) * 100, 1) AS accuracy
       FROM attempt_answers aa
       JOIN attempts a ON a.id = aa.attempt_id
       JOIN question_versions qv ON qv.id = aa.question_version_id
       JOIN questions q ON q.id = qv.question_id
       JOIN topics t ON t.id = q.topic_id
       JOIN exam_types et ON et.id = q.exam_type_id
       LEFT JOIN users u ON u.id = a.user_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY t.id, t.name, et.code
       HAVING total_answers >= 5
       ORDER BY accuracy ASC, total_answers DESC
       LIMIT " . max(1, min(5000, $limit)),
      $where['params']
    );
  }

  private function getMostSavedQuestions(array $range, array $filters, int $limit = 20): array
  {
    $conditions = ['sq.created_at BETWEEN ? AND ?', 'q.deleted_at IS NULL'];
    $params = [$this->rangeStart($range), $this->rangeEnd($range)];
    if ($filters['exam_type_id'] !== null) {
      $conditions[] = 'q.exam_type_id = ?';
      $params[] = $filters['exam_type_id'];
    }
    $userWhere = $this->buildUserWhere($filters, 'u', false);
    $conditions = array_merge($conditions, $userWhere['conditions']);
    $params = array_merge($params, $userWhere['params']);

    return $this->db->fetchAll(
      "SELECT q.id AS question_id, MAX(qv.question_text) AS question_text,
              et.code AS exam_code, t.name AS topic_name, COUNT(*) AS saves
       FROM saved_questions sq
       JOIN questions q ON q.id = sq.question_id
       JOIN question_versions qv ON qv.question_id = q.id AND qv.is_active = 1 AND qv.deleted_at IS NULL
       JOIN exam_types et ON et.id = q.exam_type_id
       JOIN topics t ON t.id = q.topic_id
       JOIN users u ON u.id = sq.user_id
       WHERE " . implode(' AND ', $conditions) . "
       GROUP BY q.id, et.code, t.name
       ORDER BY saves DESC, q.id
       LIMIT " . max(1, min(100, $limit)),
      $params
    );
  }

  private function getDropOffQuestions(array $range, array $filters, int $limit = 20): array
  {
    $where = $this->buildAttemptWhere($range, $filters, 'a', 'u', 'completed_at');
    return $this->db->fetchAll(
      "SELECT q.id AS question_id, MAX(qv.question_text) AS question_text,
              et.code AS exam_code, t.name AS topic_name,
              COUNT(*) AS appearances,
              SUM(aa.answer_id IS NULL) AS skipped,
              ROUND(SUM(aa.answer_id IS NULL) * 100.0 / COUNT(*), 1) AS drop_off_percent
       FROM attempt_answers aa
       JOIN attempts a ON a.id = aa.attempt_id
       JOIN question_versions qv ON qv.id = aa.question_version_id
       JOIN questions q ON q.id = qv.question_id
       JOIN exam_types et ON et.id = q.exam_type_id
       JOIN topics t ON t.id = q.topic_id
       LEFT JOIN users u ON u.id = a.user_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY q.id, et.code, t.name
       HAVING appearances >= 3 AND skipped > 0
       ORDER BY drop_off_percent DESC, appearances DESC
       LIMIT " . max(1, min(100, $limit)),
      $where['params']
    );
  }

  public function getRetentionCohorts(array $range, array $filters = []): array
  {
    $filters = $this->normalizeFilters($filters);
    $userWhere = $this->buildUserWhere($filters, 'u', false);
    $userWhere['conditions'][] = 'u.created_at BETWEEN ? AND ?';
    array_push($userWhere['params'], $this->rangeStart($range), $this->rangeEnd($range));
    $eventConditions = ['e.user_id = u.id'];
    $eventParams = [];
    if ($filters['exam_type_id'] !== null) {
      $eventConditions[] = 'e.exam_type_id = ?';
      $eventParams[] = $filters['exam_type_id'];
    }
    if ($filters['test_type'] !== '') {
      $eventConditions[] = 'e.test_type = ?';
      $eventParams[] = $filters['test_type'];
    }
    if ($filters['event_type'] !== '') {
      $eventConditions[] = 'e.event_type = ?';
      $eventParams[] = $filters['event_type'];
    }

    // Powrót = dowolna aktywność zalogowanego użytkownika (odsłona, logowanie,
    // nauka, test) w dniu innym niż dzień rejestracji:
    // - D1: następnego dnia,
    // - D7 / D30: w dowolnym dniu z okresu 1-7 / 1-30 dni po rejestracji.
    // Mianownikiem są tylko konta, którym dane okno już minęło - inaczej świeże
    // rejestracje zaniżały wskaźnik. Wcześniej liczono aktywność wyłącznie
    // dokładnie w 1., 7. i 30. dniu, co przy małej liczbie kont dawało ~0%.
    $rows = $this->db->fetchAll(
      "SELECT cohort,
              COUNT(*) AS users,
              SUM(d1_eligible) AS d1_eligible,
              SUM(back_d1 AND d1_eligible) AS d1_users,
              SUM(d7_eligible) AS d7_eligible,
              SUM(back_d7 AND d7_eligible) AS d7_users,
              SUM(d30_eligible) AS d30_eligible,
              SUM(back_d30 AND d30_eligible) AS d30_users
       FROM (
         SELECT u.id,
                DATE_FORMAT(u.created_at, '%Y-%m') AS cohort,
                DATE(u.created_at) <= CURDATE() - INTERVAL 1 DAY AS d1_eligible,
                DATE(u.created_at) <= CURDATE() - INTERVAL 7 DAY AS d7_eligible,
                DATE(u.created_at) <= CURDATE() - INTERVAL 30 DAY AS d30_eligible,
                COALESCE(MAX(DATEDIFF(DATE(e.event_date), DATE(u.created_at)) = 1), 0) AS back_d1,
                COALESCE(MAX(DATEDIFF(DATE(e.event_date), DATE(u.created_at)) BETWEEN 1 AND 7), 0) AS back_d7,
                COALESCE(MAX(DATEDIFF(DATE(e.event_date), DATE(u.created_at)) BETWEEN 1 AND 30), 0) AS back_d30
         FROM users u
         LEFT JOIN analytics_events e ON " . implode(' AND ', $eventConditions) . "
         WHERE " . implode(' AND ', $userWhere['conditions']) . "
         GROUP BY u.id, u.created_at
       ) per_user
       GROUP BY cohort
       ORDER BY cohort DESC",
      array_merge($eventParams, $userWhere['params'])
    );

    return array_map(function (array $row): array {
      foreach (['users', 'd1_eligible', 'd1_users', 'd7_eligible', 'd7_users', 'd30_eligible', 'd30_users'] as $key) {
        $row[$key] = (int) ($row[$key] ?? 0);
      }
      foreach (['d1', 'd7', 'd30'] as $window) {
        $row[$window . '_rate'] = $row[$window . '_eligible'] > 0
          ? $this->percentage($row[$window . '_users'], $row[$window . '_eligible'])
          : null;
      }
      return $row;
    }, $rows);
  }

  private function getInactiveUsers(array $filters, int $days, int $limit): array
  {
    $where = $this->buildUserWhere($filters, 'u', true);
    $cutoff = (new DateTimeImmutable('now'))->sub(new DateInterval('P' . $days . 'D'))->format('Y-m-d H:i:s');
    return $this->db->fetchAll(
      "SELECT u.id, u.first_name, u.last_name, u.email, u.auth_provider, u.created_at,
              MAX(e.event_date) AS last_event, MAX(a.completed_at) AS last_test
       FROM users u
       LEFT JOIN analytics_events e ON e.user_id = u.id
       LEFT JOIN attempts a ON a.user_id = u.id
       WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY u.id
       HAVING GREATEST(COALESCE(last_event, '1970-01-01'), COALESCE(last_test, '1970-01-01'), u.created_at) < ?
       ORDER BY GREATEST(COALESCE(last_event, '1970-01-01'), COALESCE(last_test, '1970-01-01'), u.created_at)
       LIMIT " . max(1, min(100, $limit)),
      array_merge($where['params'], [$cutoff])
    );
  }

  private function countInactiveUsers(array $filters, int $days): int
  {
    $where = $this->buildUserWhere($filters, 'u', true);
    $cutoff = (new DateTimeImmutable('now'))->sub(new DateInterval('P' . $days . 'D'))->format('Y-m-d H:i:s');
    return $this->fetchCount(
      "SELECT COUNT(*) AS total
       FROM (
         SELECT u.id
         FROM users u
         LEFT JOIN analytics_events e ON e.user_id = u.id
         LEFT JOIN attempts a ON a.user_id = u.id
         WHERE " . implode(' AND ', $where['conditions']) . "
         GROUP BY u.id
         HAVING GREATEST(
           COALESCE(MAX(e.event_date), '1970-01-01'),
           COALESCE(MAX(a.completed_at), '1970-01-01'),
           MAX(u.created_at)
         ) < ?
       ) inactive",
      array_merge($where['params'], [$cutoff])
    );
  }

  private function getRecentRegistrations(int $limit, array $filters): array
  {
    $range = $this->resolveDateRange($filters);
    $where = $this->buildUserWhere($filters, 'u', true);
    $where['conditions'][] = 'u.created_at BETWEEN ? AND ?';
    array_push($where['params'], $this->rangeStart($range), $this->rangeEnd($range));
    return $this->db->fetchAll(
      "SELECT u.id, u.created_at, u.first_name, u.last_name, u.email, u.auth_provider
       FROM users u WHERE " . implode(' AND ', $where['conditions']) . "
       ORDER BY u.created_at DESC LIMIT " . max(1, min(50, $limit)),
      $where['params']
    );
  }

  private function getRecentCompletedTests(int $limit, array $filters): array
  {
    $range = $this->resolveDateRange($filters);
    $where = $this->buildAttemptWhere($range, $filters, 'a', 'u', 'completed_at');
    return $this->db->fetchAll(
      "SELECT a.id, a.completed_at, a.correct_count, a.total_questions, a.user_id, a.test_type,
              u.first_name, u.last_name, u.email, et.code AS exam_code, et.name AS exam_name
       FROM attempts a
       LEFT JOIN users u ON u.id = a.user_id
       JOIN exam_types et ON et.id = a.exam_type_id
       WHERE " . implode(' AND ', $where['conditions']) . "
       ORDER BY a.completed_at DESC LIMIT " . max(1, min(50, $limit)),
      $where['params']
    );
  }

  private function countEvents(string $eventType, array $range, array $filters): int
  {
    $where = $this->buildEventWhere($range, $filters, 'e', 'u', $eventType);
    return $this->fetchCount(
      'SELECT COUNT(*) AS total FROM analytics_events e LEFT JOIN users u ON u.id = e.user_id WHERE ' .
      implode(' AND ', $where['conditions']),
      $where['params']
    );
  }

  /**
   * Liczy zdarzenia danego typu z podziałem na gości (bez konta) i zalogowanych.
   *
   * @return array{total: int, guests: int, loggedIn: int}
   */
  private function countEventsByAudience(string $eventType, array $range, array $filters): array
  {
    $where = $this->buildEventWhere($range, $filters, 'e', 'u', $eventType);
    $row = $this->db->fetch(
      'SELECT COUNT(*) AS total, SUM(e.user_id IS NULL) AS guests
       FROM analytics_events e LEFT JOIN users u ON u.id = e.user_id
       WHERE ' . implode(' AND ', $where['conditions']),
      $where['params']
    );

    $total = (int) ($row['total'] ?? 0);
    $guests = (int) ($row['guests'] ?? 0);

    return ['total' => $total, 'guests' => $guests, 'loggedIn' => $total - $guests];
  }

  /**
   * Liczy sesje (także gości), w których wystąpiło zdarzenie danego typu.
   * Zalogowanego bez skrótu sesji liczymy po identyfikatorze użytkownika.
   */
  private function countEventSessions(string $eventType, array $range, array $filters): int
  {
    $where = $this->buildEventWhere($range, $filters, 'e', 'u', $eventType);
    return $this->fetchCount(
      "SELECT COUNT(DISTINCT COALESCE(e.session_hash, CONCAT('user-', e.user_id))) AS total
       FROM analytics_events e LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']),
      $where['params']
    );
  }

  private function countRegistrations(array $range, array $filters): int
  {
    $where = $this->buildUserWhere($filters, 'u', false);
    $where['conditions'][] = 'u.created_at BETWEEN ? AND ?';
    array_push($where['params'], $this->rangeStart($range), $this->rangeEnd($range));
    return $this->fetchCount(
      'SELECT COUNT(*) AS total FROM users u WHERE ' . implode(' AND ', $where['conditions']),
      $where['params']
    );
  }

  private function countAttemptUsers(array $range, array $filters): int
  {
    $where = $this->buildAttemptWhere($range, $filters, 'a', 'u', 'completed_at');
    return $this->fetchCount(
      'SELECT COUNT(DISTINCT a.user_id) AS total FROM attempts a LEFT JOIN users u ON u.id = a.user_id WHERE ' .
      implode(' AND ', $where['conditions']),
      $where['params']
    );
  }

  private function countActiveUsers(array $range, array $filters): int
  {
    $where = $this->buildEventWhere($range, $filters, 'e', 'u');
    $where['conditions'][] = 'e.user_id IS NOT NULL';
    return $this->fetchCount(
      'SELECT COUNT(DISTINCT e.user_id) AS total FROM analytics_events e LEFT JOIN users u ON u.id = e.user_id WHERE ' .
      implode(' AND ', $where['conditions']),
      $where['params']
    );
  }

  private function fetchCount(string $sql, array $params = []): int
  {
    $result = $this->db->fetch($sql, $params);
    return (int) ($result['total'] ?? 0);
  }

  private function fetchAverageScore(array $range, array $filters): float
  {
    $where = $this->buildAttemptWhere($range, $filters, 'a', 'u', 'completed_at');
    $result = $this->db->fetch(
      "SELECT ROUND(AVG(CASE WHEN a.total_questions > 0 THEN a.correct_count * 100.0 / a.total_questions END), 1) AS value
       FROM attempts a LEFT JOIN users u ON u.id = a.user_id
       WHERE " . implode(' AND ', $where['conditions']),
      $where['params']
    );
    return (float) ($result['value'] ?? 0);
  }

  private function fetchAverageDuration(array $range, array $filters): int
  {
    $where = $this->buildAttemptWhere($range, $filters, 'a', 'u', 'completed_at');
    $result = $this->db->fetch(
      "SELECT ROUND(AVG(TIMESTAMPDIFF(SECOND, a.started_at, a.completed_at))) AS value
       FROM attempts a LEFT JOIN users u ON u.id = a.user_id
       WHERE " . implode(' AND ', $where['conditions']),
      $where['params']
    );
    return (int) ($result['value'] ?? 0);
  }

  private function fetchSingleQuestionAccuracy(array $range, array $filters): float
  {
    $where = $this->buildEventWhere($range, $filters, 'e', 'u', 'single_question_answered');
    $result = $this->db->fetch(
      "SELECT ROUND(AVG(e.is_correct) * 100, 1) AS value
       FROM analytics_events e LEFT JOIN users u ON u.id = e.user_id
       WHERE " . implode(' AND ', $where['conditions']),
      $where['params']
    );
    return (float) ($result['value'] ?? 0);
  }

  private function getRegistrationTimeline(array $range, array $filters): array
  {
    $bucket = $this->getBucketDefinition($range, 'u.created_at');
    $where = $this->buildUserWhere($filters, 'u', false);
    $where['conditions'][] = 'u.created_at BETWEEN ? AND ?';
    array_push($where['params'], $this->rangeStart($range), $this->rangeEnd($range));
    $rows = $this->db->fetchAll(
      "SELECT {$bucket['sql']} AS bucket, COUNT(*) AS total
       FROM users u WHERE " . implode(' AND ', $where['conditions']) . "
       GROUP BY bucket ORDER BY bucket",
      $where['params']
    );
    $map = [];
    foreach ($rows as $row) {
      $map[(string) $row['bucket']] = (int) $row['total'];
    }
    return $map;
  }

  private function timelineToSeries(array $map, array $range): array
  {
    $labels = $this->buildBucketLabels($range);
    return [
      'labels' => $labels,
      'values' => array_map(static fn(string $label): int => (int) ($map[$label] ?? 0), $labels),
      'granularity' => $this->getBucketDefinition($range)['name'],
    ];
  }

  private function buildEventWhere(
    array $range,
    array $filters,
    string $eventAlias,
    string $userAlias,
    ?string $forcedEventType = null
  ): array {
    $conditions = [
      "$eventAlias.event_date BETWEEN ? AND ?",
      "($eventAlias.user_id IS NULL OR $userAlias.deleted_at IS NULL)",
      // Starsze zdarzenia zapisane przed filtrem botów w trackEvent().
      "($eventAlias.user_agent IS NULL OR LOWER($eventAlias.user_agent) NOT REGEXP ?)",
    ];
    $params = [$this->rangeStart($range), $this->rangeEnd($range), self::BOT_USER_AGENT_PATTERN];

    if ($forcedEventType !== null) {
      $conditions[] = "$eventAlias.event_type = ?";
      $params[] = $forcedEventType;
    } elseif ($filters['event_type'] !== '') {
      $conditions[] = "$eventAlias.event_type = ?";
      $params[] = $filters['event_type'];
    }
    $examDimensionApplies = $forcedEventType === null || in_array($forcedEventType, [
      'test_started',
      'test_completed',
      'test_exited',
      'question_answered',
      'single_question_started',
      'single_question_answered',
      'search_performed',
      'saved_question_added',
      'saved_question_removed',
      'explanation_viewed',
      'answer_changed',
      'no_questions_left',
    ], true);
    $testTypeDimensionApplies = $forcedEventType === null || in_array($forcedEventType, [
      'test_started',
      'test_completed',
      'test_exited',
      'question_answered',
      'explanation_viewed',
      'answer_changed',
      'no_questions_left',
    ], true);

    if ($filters['exam_type_id'] !== null && $examDimensionApplies) {
      $conditions[] = "$eventAlias.exam_type_id = ?";
      $params[] = $filters['exam_type_id'];
    }
    if ($filters['test_type'] !== '' && $testTypeDimensionApplies) {
      $conditions[] = "$eventAlias.test_type = ?";
      $params[] = $filters['test_type'];
    }
    if ($filters['auth_provider'] !== '') {
      $conditions[] = "($eventAlias.auth_provider = ? OR ($eventAlias.auth_provider IS NULL AND $userAlias.auth_provider = ?))";
      $params[] = $filters['auth_provider'];
      $params[] = $filters['auth_provider'];
    }
    $this->appendUserFilterConditions($conditions, $params, $filters, $userAlias);
    return compact('conditions', 'params');
  }

  private function buildAttemptWhere(
    array $range,
    array $filters,
    string $attemptAlias,
    string $userAlias,
    string $dateColumn
  ): array {
    $conditions = [
      "$attemptAlias.$dateColumn IS NOT NULL",
      "$attemptAlias.$dateColumn BETWEEN ? AND ?",
      "($attemptAlias.user_id IS NULL OR $userAlias.deleted_at IS NULL)",
    ];
    $params = [$this->rangeStart($range), $this->rangeEnd($range)];
    $dimensions = $this->buildAttemptDimensionConditions($filters, $attemptAlias);
    $conditions = array_merge($conditions, $dimensions['conditions']);
    $params = array_merge($params, $dimensions['params']);
    if ($filters['auth_provider'] !== '') {
      $conditions[] = "$userAlias.auth_provider = ?";
      $params[] = $filters['auth_provider'];
    }
    $this->appendUserFilterConditions($conditions, $params, $filters, $userAlias);
    return compact('conditions', 'params');
  }

  private function buildAttemptDimensionConditions(array $filters, string $alias): array
  {
    $conditions = [];
    $params = [];
    if ($filters['exam_type_id'] !== null) {
      $conditions[] = "$alias.exam_type_id = ?";
      $params[] = $filters['exam_type_id'];
    }
    if ($filters['test_type'] !== '') {
      $conditions[] = "$alias.test_type = ?";
      $params[] = $filters['test_type'];
    }
    return compact('conditions', 'params');
  }

  private function buildUserWhere(array $filters, string $alias, bool $includeSearch): array
  {
    $conditions = ["$alias.deleted_at IS NULL"];
    $params = [];
    if ($filters['auth_provider'] !== '') {
      $conditions[] = "$alias.auth_provider = ?";
      $params[] = $filters['auth_provider'];
    }
    $this->appendUserFilterConditions($conditions, $params, $filters, $alias);
    if ($includeSearch && $filters['search'] !== '') {
      $conditions[] = "($alias.email LIKE ? OR $alias.first_name LIKE ? OR $alias.last_name LIKE ? OR CONCAT_WS(' ', $alias.first_name, $alias.last_name) LIKE ?)";
      $term = '%' . $filters['search'] . '%';
      array_push($params, $term, $term, $term, $term);
    }
    return compact('conditions', 'params');
  }

  private function appendUserFilterConditions(
    array &$conditions,
    array &$params,
    array $filters,
    string $alias
  ): void
  {
    if ($filters['role'] !== '') {
      $conditions[] = "$alias.role = ?";
      $params[] = $filters['role'];
    }
    if ($filters['is_verified'] !== null) {
      $conditions[] = "$alias.is_verified = ?";
      $params[] = $filters['is_verified'];
    }
  }

  private function normalizeFilters(array $filters): array
  {
    $range = strtolower(trim((string) ($filters['range'] ?? '30d')));
    if (!in_array($range, ['7d', '30d', '90d', 'custom', 'all'], true)) {
      $range = '30d';
    }
    $testType = trim((string) ($filters['test_type'] ?? ''));
    $authProvider = trim((string) ($filters['auth_provider'] ?? ''));
    $legacyStatus = trim((string) ($filters['user_status'] ?? ''));
    $role = trim((string) ($filters['role'] ?? ''));
    $rawIsVerified = trim((string) ($filters['is_verified'] ?? ''));
    $eventType = trim((string) ($filters['event_type'] ?? ''));

    if ($role === '' && in_array($legacyStatus, self::USER_ROLES, true)) {
      $role = $legacyStatus;
    }
    if ($rawIsVerified === '' && in_array($legacyStatus, ['verified', 'unverified'], true)) {
      $rawIsVerified = $legacyStatus === 'verified' ? '1' : '0';
    }
    $isVerified = in_array($rawIsVerified, ['0', '1'], true) ? (int) $rawIsVerified : null;
    $legacyStatus = match (true) {
      $role !== '' => $role,
      $isVerified === 1 => 'verified',
      $isVerified === 0 => 'unverified',
      default => '',
    };

    return [
      'range' => $range,
      'from' => $this->validateDate((string) ($filters['from'] ?? '')),
      'to' => $this->validateDate((string) ($filters['to'] ?? '')),
      'exam_type_id' => max(0, (int) ($filters['exam_type_id'] ?? 0)) ?: null,
      'test_type' => in_array($testType, self::TEST_TYPES, true) ? $testType : '',
      'auth_provider' => in_array($authProvider, self::AUTH_PROVIDERS, true) ? $authProvider : '',
      'role' => in_array($role, self::USER_ROLES, true) ? $role : '',
      'is_verified' => $isVerified,
      'event_type' => in_array($eventType, self::EVENT_TYPES, true) ? $eventType : '',
      'search' => mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100),
      'page' => max(1, (int) ($filters['page'] ?? 1)),
      'per_page' => min(100, max(10, (int) ($filters['per_page'] ?? 25))),
      'user_status' => $legacyStatus,
    ];
  }

  private function resolveDateRange(array $filters): array
  {
    $today = new DateTimeImmutable('today');
    $end = $today->setTime(23, 59, 59);

    if ($filters['range'] === 'custom' && $filters['from'] !== '' && $filters['to'] !== '') {
      $from = new DateTimeImmutable($filters['from']);
      $to = new DateTimeImmutable($filters['to']);
      if ($from > $to) {
        [$from, $to] = [$to, $from];
      }
      return [
        'value' => 'custom',
        'label' => $from->format('d.m.Y') . ' - ' . $to->format('d.m.Y'),
        'start' => $from->setTime(0, 0),
        'end' => $to->setTime(23, 59, 59),
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
      ];
    }

    if ($filters['range'] === 'all') {
      $start = $this->getEarliestDataDate();
      return [
        'value' => 'all',
        'label' => 'Cały okres',
        'start' => $start,
        'end' => $end,
        'from' => $start->format('Y-m-d'),
        'to' => $end->format('Y-m-d'),
      ];
    }

    $days = match ($filters['range']) {
      '7d' => 7,
      '90d' => 90,
      default => 30,
    };
    $start = $today->sub(new DateInterval('P' . ($days - 1) . 'D'))->setTime(0, 0);
    return [
      'value' => $days . 'd',
      'label' => 'Ostatnie ' . $days . ' dni',
      'start' => $start,
      'end' => $end,
      'from' => $start->format('Y-m-d'),
      'to' => $end->format('Y-m-d'),
    ];
  }

  private function buildPreviousRange(array $range): ?array
  {
    if ($range['value'] === 'all') {
      return null;
    }
    $days = (int) $range['start']->diff($range['end'])->days + 1;
    $end = $range['start']->sub(new DateInterval('P1D'))->setTime(23, 59, 59);
    return [
      'start' => $end->sub(new DateInterval('P' . ($days - 1) . 'D'))->setTime(0, 0),
      'end' => $end,
    ];
  }

  private function serializeRange(array $range, ?array $previous = null): array
  {
    return [
      'value' => $range['value'] ?? 'custom',
      'label' => $range['label'] ?? '',
      'from' => $range['from'] ?? $range['start']->format('Y-m-d'),
      'to' => $range['to'] ?? $range['end']->format('Y-m-d'),
      'previousFrom' => ($previous['start'] ?? null)?->format('Y-m-d'),
      'previousTo' => ($previous['end'] ?? null)?->format('Y-m-d'),
    ];
  }

  private function getEarliestDataDate(): DateTimeImmutable
  {
    $row = $this->db->fetch(
      "SELECT MIN(first_seen) AS first_seen FROM (
         SELECT MIN(created_at) AS first_seen FROM users
         UNION ALL SELECT MIN(started_at) FROM attempts
         UNION ALL SELECT MIN(event_date) FROM analytics_events
       ) dates"
    );
    $value = (string) ($row['first_seen'] ?? '');
    return $value !== '' ? (new DateTimeImmutable($value))->setTime(0, 0) : new DateTimeImmutable('today');
  }

  private function getBucketDefinition(array $range, string $column = 'e.event_date'): array
  {
    $days = (int) $range['start']->diff($range['end'])->days + 1;
    if ($days > 120) {
      return ['name' => 'month', 'sql' => "DATE_FORMAT($column, '%Y-%m-01')"];
    }
    return ['name' => 'day', 'sql' => "DATE($column)"];
  }

  private function buildBucketLabels(array $range): array
  {
    $bucket = $this->getBucketDefinition($range);
    $labels = [];
    $cursor = $range['start']->setTime(0, 0);
    $end = $range['end']->setTime(0, 0);
    if ($bucket['name'] === 'month') {
      $cursor = $cursor->modify('first day of this month');
      while ($cursor <= $end) {
        $labels[] = $cursor->format('Y-m-01');
        $cursor = $cursor->modify('first day of next month');
      }
      return $labels;
    }
    while ($cursor <= $end) {
      $labels[] = $cursor->format('Y-m-d');
      $cursor = $cursor->modify('+1 day');
    }
    return $labels;
  }

  private function buildTodayRange(): array
  {
    $today = new DateTimeImmutable('today');
    return ['start' => $today->setTime(0, 0), 'end' => $today->setTime(23, 59, 59)];
  }

  private function rangeStart(array $range): string
  {
    return $range['start']->format('Y-m-d H:i:s');
  }

  private function rangeEnd(array $range): string
  {
    return $range['end']->format('Y-m-d H:i:s');
  }

  private function validateDate(string $value): string
  {
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
  }

  private function percentage(int|float $part, int|float $total): float
  {
    return $total > 0 ? round($part * 100 / $total, 1) : 0.0;
  }

  private function changePercent(float $current, float $previous): ?float
  {
    if ($previous == 0.0) {
      return $current == 0.0 ? 0.0 : null;
    }
    return round(($current - $previous) * 100 / abs($previous), 1);
  }

  private function sanitizeMetadata(array $metadata): array
  {
    $sanitized = [];
    foreach ($metadata as $key => $value) {
      $normalizedKey = strtolower((string) $key);
      if (!in_array($normalizedKey, self::METADATA_KEYS, true)) {
        continue;
      }

      if ($normalizedKey === 'score') {
        $sanitized['score'] = max(0.0, min(100.0, round((float) $value, 1)));
        continue;
      }

      if (in_array($normalizedKey, ['exam_id', 'question_id', 'topic_id', 'test_id', 'count', 'duration_seconds', 'correct_answers', 'total_questions'], true)) {
        $number = (int) $value;
        if (in_array($normalizedKey, ['count', 'duration_seconds', 'correct_answers', 'total_questions'], true)) {
          $sanitized[$normalizedKey] = max(0, $number);
        } elseif ($number > 0) {
          $sanitized[$normalizedKey] = $number;
        }
        continue;
      }

      if (!is_scalar($value) && $value !== null) {
        continue;
      }

      $stringValue = trim((string) $value);
      if ($stringValue === '' || $this->containsPotentialPii($stringValue)) {
        continue;
      }

      if ($normalizedKey === 'test_type' && !in_array($stringValue, self::TEST_TYPES, true)) {
        continue;
      }

      if ($normalizedKey === 'path') {
        $path = parse_url($stringValue, PHP_URL_PATH);
        $stringValue = is_string($path) ? $path : '';
      }

      if ($stringValue !== '') {
        $maxLength = $normalizedKey === 'path' ? 255 : 100;
        $sanitized[$normalizedKey] = mb_substr($stringValue, 0, $maxLength);
      }
    }
    return $sanitized;
  }

  /**
   * Wydziela często filtrowane wymiary do normalnych kolumn. Metadata pozostaje
   * pełnym, elastycznym opisem eventu po usunięciu danych wrażliwych.
   *
   * @return array<string, int|string|null>
   */
  private function extractEventDimensions(array $metadata): array
  {
    $examId = (int) ($metadata['exam_id'] ?? $metadata['exam_type_id'] ?? 0);
    $questionId = (int) ($metadata['question_id'] ?? 0);
    $topicId = (int) ($metadata['topic_id'] ?? 0);
    $testId = (int) ($metadata['test_id'] ?? 0);
    $testType = (string) ($metadata['test_type'] ?? '');
    $authProvider = (string) ($metadata['auth_provider'] ?? '');
    $source = trim((string) ($metadata['source'] ?? ''));
    $rawPath = trim((string) ($metadata['path'] ?? ''));
    $parsedPath = $rawPath !== '' ? parse_url($rawPath, PHP_URL_PATH) : '';
    $pagePath = is_string($parsedPath) ? $parsedPath : '';
    $searchTerm = $this->normalizeSearchTerm(
      isset($metadata['search_term']) ? (string) $metadata['search_term'] : null
    );
    $isCorrect = $metadata['is_correct'] ?? null;

    if (is_bool($isCorrect)) {
      $isCorrect = $isCorrect ? 1 : 0;
    } elseif (is_numeric($isCorrect) && in_array((int) $isCorrect, [0, 1], true)) {
      $isCorrect = (int) $isCorrect;
    } else {
      $isCorrect = null;
    }

    return [
      'exam_type_id' => $examId > 0 ? $examId : null,
      'question_id' => $questionId > 0 ? $questionId : null,
      'topic_id' => $topicId > 0 ? $topicId : null,
      'test_id' => $testId > 0 ? $testId : null,
      'source' => $source !== '' && !$this->containsPotentialPii($source) ? mb_substr($source, 0, 100) : null,
      'test_type' => in_array($testType, self::TEST_TYPES, true) ? $testType : null,
      'auth_provider' => in_array($authProvider, self::AUTH_PROVIDERS, true) ? $authProvider : null,
      'page_path' => $pagePath !== '' ? mb_substr($pagePath, 0, 255) : null,
      'search_term' => $searchTerm,
      'is_correct' => $isCorrect,
    ];
  }

  private function normalizeSearchTerm(?string $query): ?string
  {
    $query = trim((string) $query);
    if ($this->containsPotentialPii($query)) {
      return null;
    }

    $query = mb_strtolower($query);
    $query = preg_replace('/\s+/u', ' ', $query) ?? '';
    $query = trim($query);

    if (mb_strlen($query) < 3 || mb_strlen($query) > 80) {
      return null;
    }

    return $query;
  }

  private function normalizeResultCode(string $result): string
  {
    $result = strtolower(trim($result));
    $result = preg_replace('/[^a-z0-9_-]+/', '_', $result) ?? '';
    return mb_substr(trim($result, '_'), 0, 100);
  }

  private function containsPotentialPii(string $value): bool
  {
    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
      return true;
    }

    return preg_match(
      '/(?:[^\s@]+@[^\s@]+\.[^\s@]+)|(?:https?:\/\/)|(?:\b(?:\+?\d[\s().-]*){7,}\b)|(?:\b\d{11}\b)|(?:\b\d{2}-\d{3}\b)|(?:\b[a-f0-9]{32,}\b)/iu',
      $value
    ) === 1;
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

  /**
   * Sprawdza, czy user-agent należy do bota lub narzędzia automatycznego.
   */
  public function isBotUserAgent(?string $userAgent): bool
  {
    if ($userAgent === null || $userAgent === '') {
      return false;
    }

    return (bool) preg_match('#' . self::BOT_USER_AGENT_PATTERN . '#i', $userAgent);
  }

  private function getUserAgent(): ?string
  {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    return is_string($userAgent) && $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null;
  }

  private function getSessionHash(): ?string
  {
    $sessionId = session_id();
    return $sessionId !== '' ? hash('sha256', $sessionId) : null;
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
    foreach ([
      'Edg/' => 'Microsoft Edge',
      'OPR/' => 'Opera',
      'Chrome/' => 'Google Chrome',
      'Firefox/' => 'Mozilla Firefox',
      'Safari/' => 'Safari',
    ] as $needle => $label) {
      if (str_contains($userAgent, $needle)) {
        return $label;
      }
    }
    return 'Inna przeglądarka';
  }
}
