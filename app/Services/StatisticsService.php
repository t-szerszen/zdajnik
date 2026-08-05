<?php

namespace App\Services;

use App\Core\Database;

class StatisticsService
{
  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Pomocnicza metoda formatująca wynik procentowy wg wymagań:
   * Pełna cyfra jako int, połówka (x.5) jako float z jedną cyfrą po przecinku (x.y).
   */
  private function formatScore(float $score): string|int
  {
    if (floor($score) == $score) {
      return (int) $score;
    }
    return number_format($score, 1, '.', '');
  }

  /**
   * Pobiera wszystkie dostępne typy egzaminów dla selektora.
   */
  public function getExamTypes(): array
  {
    $sql = "SELECT id, code, name FROM exam_types WHERE is_active = 1 AND deleted_at IS NULL";
    return $this->db->fetchAll($sql);
  }

  private function checkUserProgress(int $userId): bool
  {
    $sql = "SELECT 1 FROM user_progress WHERE user_id = ? LIMIT 1";
    return $this->db->fetch($sql, [$userId]) !== false;
  }

  public function getUserExams(int $userId): array
  {
    if ($this->checkUserProgress($userId)) {
      $sql = "SELECT DISTINCT et.id, et.code, et.name 
              FROM user_progress up
              JOIN questions q ON up.question_id = q.id 
              JOIN exam_types et ON q.exam_type_id = et.id
              WHERE up.user_id = ? 
              AND q.is_active = 1 AND q.deleted_at IS NULL
              AND et.is_active = 1 AND et.deleted_at IS NULL";

      return $this->db->fetchAll($sql, [$userId]);
    }

    return [];
  }

  /**
   * Pobiera ogólny postęp użytkownika w danej kategorii (procent zaliczonych pytań).
   */
  public function getOverallProgress(int $userId, int $examTypeId): array
  {
    // Liczba wszystkich pytań w kategorii
    $sqlTotal = "SELECT COUNT(*) as total FROM questions WHERE exam_type_id = ? AND is_active = 1 AND deleted_at IS NULL";
    $totalResult = $this->db->fetch($sqlTotal, [$examTypeId]);
    $totalQuestions = $totalResult['total'] ?? 0;

    // Liczba pytań opanowanych (>= 60% skuteczności) oraz w ogóle podjętych
    $sqlStats = "SELECT 
                    COUNT(CASE WHEN up.attempts_count > 0 AND (up.correct_count * 1.0 / up.attempts_count) >= 0.6 THEN 1 END) as mastered,
                    COUNT(CASE WHEN up.attempts_count > 0 THEN 1 END) as attempted
                  FROM user_progress up
                  JOIN questions q ON up.question_id = q.id
                  WHERE up.user_id = ? AND q.exam_type_id = ?
                  AND q.is_active = 1 AND q.deleted_at IS NULL";

    $stats = $this->db->fetch($sqlStats, [$userId, $examTypeId]);
    $masteredQuestions = $stats['mastered'] ?? 0;
    $attemptedQuestions = $stats['attempted'] ?? 0;

    $percentRaw = ($totalQuestions > 0) ? ($masteredQuestions * 100.0 / $totalQuestions) : 0;
    $percent = $this->formatScore($percentRaw);

    return [
      'total' => $totalQuestions,
      'mastered' => $masteredQuestions,
      'attempted' => $attemptedQuestions,
      'percent' => $percent
    ];
  }

  /**
   * Pobiera statystyki postępu z podziałem na tematy.
   */
  public function getTopicStats(int $userId, int $examTypeId): array
  {
    $sql = "SELECT t.id, t.name, 
                       (SELECT COUNT(*) FROM questions q 
                        WHERE q.topic_id = t.id AND q.is_active = 1 AND q.deleted_at IS NULL) as total,
                       (SELECT COUNT(*) FROM user_progress up 
                        JOIN questions q ON up.question_id = q.id 
                        WHERE up.user_id = ? AND q.topic_id = t.id
                        AND q.is_active = 1 AND q.deleted_at IS NULL
                        AND up.attempts_count > 0
                        AND (up.correct_count * 1.0 / up.attempts_count) >= 0.6) as mastered,
                       (SELECT COUNT(*) FROM user_progress up 
                        JOIN questions q ON up.question_id = q.id 
                        WHERE up.user_id = ? AND q.topic_id = t.id
                        AND q.is_active = 1 AND q.deleted_at IS NULL
                        AND up.attempts_count > 0) as attempted
                FROM topics t
                WHERE t.exam_type_id = ? AND t.is_active = 1 AND t.deleted_at IS NULL";

    $topics = $this->db->fetchAll($sql, [$userId, $userId, $examTypeId]);

    foreach ($topics as &$topic) {
      $percentRaw = ($topic['total'] > 0) ? ($topic['mastered'] * 100.0 / $topic['total']) : 0;
      $topic['progress'] = $this->formatScore($percentRaw);
    }

    return $topics;
  }

  /**
   * Pobiera rekordy użytkownika (najlepszy wynik, ostatnia aktywność itp.).
   */
  public function getRecords(int $userId, int $examTypeId): array
  {
    $sql = "SELECT 
                    MAX(correct_count * 100.0 / total_questions) as best_score_raw,
                    MAX(completed_at) as last_exam,
                    COUNT(id) as total_exams,
                    AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration
                FROM attempts 
                WHERE user_id = ? AND exam_type_id = ? AND completed_at IS NOT NULL";

    $result = $this->db->fetch($sql, [$userId, $examTypeId]);
    $bestScoreRaw = (float) ($result['best_score_raw'] ?? 0);
    $bestScore = $this->formatScore($bestScoreRaw);

    return [
      'best_score' => $bestScore,
      'last_exam' => $result['last_exam'] ? date('d.m.Y H:i', strtotime($result['last_exam'])) : 'Brak',
      'total_exams' => $result['total_exams'] ?? 0,
      'avg_duration' => $result['avg_duration'] ? round($result['avg_duration']) : 0,
      'last_activity' => $this->getLastActivity($userId, $examTypeId)
    ];
  }

  /**
   * Pobiera datę ostatniej aktywności użytkownika (zmiana w postępie pytań).
   */
  private function getLastActivity(int $userId, int $examTypeId): string
  {
    $sql = "SELECT MAX(up.updated_at) as last_activity 
                FROM user_progress up
                JOIN questions q ON up.question_id = q.id
                WHERE up.user_id = ? AND q.exam_type_id = ?";

    $result = $this->db->fetch($sql, [$userId, $examTypeId]);

    return $result['last_activity'] ? date('d.m.Y H:i', strtotime($result['last_activity'])) : 'Brak';
  }

  /**
   * Pobiera listę pytań, w których użytkownik najczęściej popełnia błędy.
   */
  public function getErrorHistory(int $userId, int $examTypeId, ?int $limit = 5): array
  {
    $limitSql = "";
    if ($limit !== null) {
      $limitSql = "LIMIT " . (int) $limit;
    }

    $sql = "SELECT q.id, qv.question_text, t.name as topic_name,
                       up.incorrect_count, up.skipped_count, up.attempts_count,
                       MAX(up.updated_at) as last_updated
                FROM user_progress up
                JOIN questions q ON up.question_id = q.id
                LEFT JOIN topics t ON q.topic_id = t.id
                LEFT JOIN question_versions qv ON q.id = qv.question_id AND qv.is_active = 1 AND qv.deleted_at IS NULL
                WHERE up.user_id = ? 
                  AND q.exam_type_id = ? 
                  AND up.last_result = 0
                  AND q.is_active = 1 
                  AND q.deleted_at IS NULL
                GROUP BY q.id, qv.question_text, t.name, up.incorrect_count, up.skipped_count, up.attempts_count
                ORDER BY last_updated DESC
                $limitSql";

    $items = $this->db->fetchAll($sql, [$userId, $examTypeId]);

    $sqlTotal = "SELECT COUNT(*) as total FROM user_progress up
                 JOIN questions q ON up.question_id = q.id
                 WHERE up.user_id = ? AND q.exam_type_id = ? AND up.last_result = 0
                 AND q.is_active = 1 AND q.deleted_at IS NULL";
    $totalResult = $this->db->fetch($sqlTotal, [$userId, $examTypeId]);

    return [
      'items' => $items,
      'total' => $totalResult['total'] ?? 0
    ];
  }

  /**
   * Pobiera listę pytań o niskiej skuteczności z metadanymi gamifikacji.
   * @param int $userId ID użytkownika
   * @param int $examTypeId ID typu egzaminu
   * @param int $limit Maksymalna liczba pytań do pobrania
   * @return array Tablica z kluczami: items, total_answered, unlock_progress, is_unlocked
   */
  public function getCriticalErrors(int $userId, int $examTypeId, ?int $limit = 5): array
  {
    // Oblicz całkowitą liczbę odpowiedzi w kategorii
    $sqlTotal = "SELECT COUNT(DISTINCT up.question_id) as total_answered
		             FROM user_progress up
		             JOIN questions q ON up.question_id = q.id
		             WHERE up.user_id = ? AND q.exam_type_id = ?
		             AND q.is_active = 1 AND q.deleted_at IS NULL";
    $totalResult = $this->db->fetch($sqlTotal, [$userId, $examTypeId]);
    $totalAnswered = (int) ($totalResult['total_answered'] ?? 0);

    $isUnlocked = $totalAnswered >= 100;
    $unlockProgress = min(100, $totalAnswered); // Cap at 100

    // Pobierz krytyczne błędy
    $items = [];
    if ($isUnlocked) {
      $limitSql = "";
      if ($limit !== null) {
        $limitSql = "LIMIT " . (int) $limit;
      }

      $sql = "SELECT q.id as question_id, qv.question_text, t.name as topic_name, 
			               (up.correct_count * 100.0 / up.attempts_count) as success_rate_raw,
                           MAX(up.updated_at) as last_updated,
                           up.correct_count, up.attempts_count, up.incorrect_count
			        FROM user_progress up
			        JOIN questions q ON up.question_id = q.id
			        LEFT JOIN topics t ON q.topic_id = t.id
			        LEFT JOIN question_versions qv ON q.id = qv.question_id 
			                                         AND qv.is_active = 1 
			                                         AND qv.deleted_at IS NULL
			        WHERE up.user_id = ? 
			          AND q.exam_type_id = ? 
			          AND q.is_active = 1 
			          AND q.deleted_at IS NULL
			          AND (up.correct_count / up.attempts_count) < 0.6
			        GROUP BY q.id, qv.question_text, t.name, up.correct_count, up.attempts_count, up.incorrect_count
			        ORDER BY success_rate_raw ASC, last_updated DESC
			        $limitSql";
      $rawItems = $this->db->fetchAll($sql, [$userId, $examTypeId]);
      foreach ($rawItems as $item) {
        $item['success_rate'] = round((float) $item['success_rate_raw']);
        $items[] = $item;
      }

      $sqlTotalCritical = "SELECT COUNT(*) as total FROM user_progress up
                           JOIN questions q ON up.question_id = q.id
                           WHERE up.user_id = ? AND q.exam_type_id = ? 
                           AND q.is_active = 1 AND q.deleted_at IS NULL
                           AND (up.correct_count / up.attempts_count) < 0.6";
      $totalCriticalResult = $this->db->fetch($sqlTotalCritical, [$userId, $examTypeId]);
      $totalCritical = (int) ($totalCriticalResult['total'] ?? 0);
    }

    return [
      'items' => $items,
      'total_answered' => $totalAnswered,
      'total_critical' => $totalCritical ?? 0,
      'unlock_progress' => $unlockProgress,
      'is_unlocked' => $isUnlocked
    ];
  }

  /**
   * Pobiera historię ostatnich egzaminów.
   */
  public function getRecentExams(int $userId, int $examTypeId, ?int $limit = 5): array
  {
    $limitSql = "";
    if ($limit !== null) {
      $limitSql = "LIMIT " . (int) $limit;
    }

    $sql = "SELECT a.id, (a.correct_count * 100.0 / a.total_questions) as score_raw, a.completed_at, a.test_type, et.code as exam_code
                FROM attempts a
                JOIN exam_types et ON a.exam_type_id = et.id
                WHERE a.user_id = ? AND a.exam_type_id = ? AND a.completed_at IS NOT NULL
                ORDER BY a.completed_at DESC
                $limitSql";

    $exams = $this->db->fetchAll($sql, [$userId, $examTypeId]);

    // Pobierz całkowitą liczbę tematów dla danej kategorii dla logiki nazewnictwa
    $sqlTotalTopics = "SELECT COUNT(*) as total FROM topics WHERE exam_type_id = ? AND is_active = 1 AND deleted_at IS NULL";
    $totalTopicsResult = $this->db->fetch($sqlTotalTopics, [$examTypeId]);
    $totalTopicsInExam = (int) ($totalTopicsResult['total'] ?? 0);

    foreach ($exams as &$exam) {
      $exam['score'] = $this->formatScore((float) $exam['score_raw']);

      if ($exam['test_type'] === 'full_exam') {
        $exam['display_name'] = "Pełny test";
        $exam['badge_text'] = "Pełny test";
      } else {
        // Pobierz tematy dla personalizowanego testu
        $topicsSql = "SELECT t.name FROM topics t 
                              JOIN attempt_topics atop ON t.id = atop.topic_id 
                              WHERE atop.attempt_id = ?";
        $topicsResult = $this->db->fetchAll($topicsSql, [$exam['id']]);
        $topicNames = array_column($topicsResult, 'name');

        // Sprawdź czy test obejmuje wszystkie tematy kategorii
        if (count($topicNames) >= $totalTopicsInExam && $totalTopicsInExam > 0) {
          $exam['display_name'] = "Personalizowany test (" . $exam['exam_code'] . ")";
        } else {
          $exam['display_name'] = "Test z " . implode(', ', $topicNames);
        }
        $exam['badge_text'] = "Personalizowany test";
      }
    }

    $sqlTotal = "SELECT COUNT(*) as total FROM attempts WHERE user_id = ? AND exam_type_id = ? AND completed_at IS NOT NULL";
    $totalResult = $this->db->fetch($sqlTotal, [$userId, $examTypeId]);

    return [
      'items' => $exams,
      'total' => $totalResult['total'] ?? 0
    ];
  }

  /**
   * Pobiera dane do wykresu trendu (ostatnie 10 wyników).
   * @deprecated Use getEfficiencyTrendWithMovingAvg() for smoother trend visualization
   */
  public function getEfficiencyTrend(int $userId, int $examTypeId, int $limit = 10): array
  {
    $limit = (int) $limit;
    $sql = "SELECT ROUND((correct_count / total_questions) * 100) as score, completed_at
                FROM attempts 
                WHERE user_id = ? AND exam_type_id = ? AND completed_at IS NOT NULL
                ORDER BY completed_at ASC
                LIMIT $limit";
    // Zwracamy w kolejności rosnącej daty dla wykresu

    return $this->db->fetchAll($sql, [$userId, $examTypeId]);
  }

  /**
   * Pobiera dane do wykresu trendu ze średnią kroczącą (5 ostatnich podejść).
   * @param int $userId ID użytkownika
   * @param int $examTypeId ID typu egzaminu
   * @param int $limit Maksymalna liczba punktów do wyświetlenia
   * @return array Tablica wyników ze średnią kroczącą
   */
  public function getEfficiencyTrendWithMovingAvg(int $userId, int $examTypeId, ?int $limit = 10): array
  {
    $limitSql = "";
    if ($limit !== null) {
      $limitSql = "LIMIT " . (int) $limit;
    }

    $sql = "SELECT 
		            ROUND((correct_count / total_questions) * 100) as raw_score,
		            ROUND(AVG(ROUND((correct_count / total_questions) * 100)) 
		                  OVER (ORDER BY completed_at ASC 
		                        ROWS BETWEEN 4 PRECEDING AND CURRENT ROW)) as moving_avg_score,
		            completed_at
		        FROM attempts
		        WHERE user_id = ? AND exam_type_id = ? AND completed_at IS NOT NULL
		        ORDER BY completed_at ASC
		        $limitSql";

    return $this->db->fetchAll($sql, [$userId, $examTypeId]);
  }

  /**
   * Pobiera statystyki opanowania materiału (>3 poprawne odpowiedzi pod rząd).
   * @param int $userId ID użytkownika
   * @param int $examTypeId ID typu egzaminu
   * @return array Tablica z total, mastered, percent
   */
  public function getMasteryStats(int $userId, int $examTypeId): array
  {
    // Liczba wszystkich pytań w kategorii
    $sqlTotal = "SELECT COUNT(*) as total FROM questions 
		             WHERE exam_type_id = ? AND is_active = 1 AND deleted_at IS NULL";
    $totalResult = $this->db->fetch($sqlTotal, [$examTypeId]);
    $totalQuestions = $totalResult['total'] ?? 0;

    // Statystyki opanowania i podejmowania wyzwań
    $sqlStats = "SELECT 
                    COUNT(CASE WHEN up.attempts_count > 0 AND (up.correct_count * 1.0 / up.attempts_count) >= 0.6 THEN 1 END) as mastered,
                    COUNT(CASE WHEN up.attempts_count > 0 THEN 1 END) as attempted
                  FROM user_progress up
                  JOIN questions q ON up.question_id = q.id
                  WHERE up.user_id = ? AND q.exam_type_id = ? 
                  AND q.is_active = 1 AND q.deleted_at IS NULL";

    $stats = $this->db->fetch($sqlStats, [$userId, $examTypeId]);
    $masteredQuestions = $stats['mastered'] ?? 0;
    $attemptedQuestions = $stats['attempted'] ?? 0;

    $percentRaw = ($totalQuestions > 0) ? ($masteredQuestions * 100.0 / $totalQuestions) : 0;
    $percent = $this->formatScore($percentRaw);

    return [
      'total' => $totalQuestions,
      'mastered' => $masteredQuestions,
      'attempted' => $attemptedQuestions,
      'percent' => $percent
    ];
  }

  /**
   * Oblicza przewidywany wynik egzaminu na podstawie ostatnich wyników.
   * @param int $userId ID użytkownika
   * @param int $examTypeId ID typu egzaminu
   * @return array Tablica z predicted_score i confidence_level
   */
  public function getPredictedExamScore(int $userId, int $examTypeId): array
  {
    // Pobierz ostatnie 5 egzaminów pełnych
    $sql = "SELECT ROUND((correct_count / total_questions) * 100) as score
		        FROM attempts
		        WHERE user_id = ? AND exam_type_id = ? 
		        AND test_type = 'full_exam' AND completed_at IS NOT NULL
		        ORDER BY completed_at DESC
		        LIMIT 5";
    $recentExams = $this->db->fetchAll($sql, [$userId, $examTypeId]);

    $examCount = count($recentExams);

    // WYMAGANIE: Przewidywany wynik dostępny dopiero po 5 pełnych próbach
    if ($examCount < 5) {
      return [
        'predicted_score' => 0,
        'confidence_level' => 'none',
        'exams_analyzed' => $examCount, // Zwracamy faktyczną liczbę, by pokazać progress
        'min_exams_required' => 5
      ];
    }

    // Oblicz średnią ważoną (nowsze egzaminy mają większą wagę)
    $scores = array_column($recentExams, 'score');
    $weights = [5, 4, 3, 2, 1]; // Wagi dla ostatnich 5 egzaminów
    $weightedSum = 0;
    $totalWeight = 0;

    foreach ($scores as $index => $score) {
      $weight = $weights[$index] ?? 1;
      $weightedSum += $score * $weight;
      $totalWeight += $weight;
    }

    $predictedScoreRaw = ($totalWeight > 0) ? ($weightedSum / $totalWeight) : 0;
    $predictedScore = $this->formatScore((float) $predictedScoreRaw);

    // Określ poziom pewności na podstawie liczby egzaminów
    $confidenceLevel = match (true) {
      $examCount >= 5 => 'high',
      $examCount >= 3 => 'medium',
      default => 'low'
    };

    return [
      'predicted_score' => $predictedScore,
      'confidence_level' => $confidenceLevel,
      'exams_analyzed' => $examCount,
      'min_exams_required' => 5
    ];
  }

  /**
   * Pobiera szczegółowe zestawienie pytań i odpowiedzi dla konkretnego podejścia.
   * (Metoda uproszczona - może być używana w starych widokach)
   */
  public function getAttemptDetails(int $attemptId, int $userId): array
  {
    $sql = "SELECT 
                    qv.question_text, 
                    qv.explanation, 
                    qv.image_name,
                    ans_user.answer_text as user_answer,
                    ans_user.is_correct as is_user_correct,
                    ans_correct.answer_text as correct_answer
                FROM attempt_answers aa
                JOIN attempts a ON aa.attempt_id = a.id
                JOIN question_versions qv ON aa.question_version_id = qv.id
                JOIN answers ans_user ON aa.answer_id = ans_user.id
                JOIN answers ans_correct ON ans_correct.question_version_id = qv.id AND ans_correct.is_correct = 1
                WHERE aa.attempt_id = ? AND a.user_id = ?";

    return $this->db->fetchAll($sql, [$attemptId, $userId]);
  }

  /**
   * Pobiera pełne dane do przeglądu egzaminu (pytania, wszystkie odpowiedzi, wybór użytkownika).
   */
  public function getExamReviewDetails(int $attemptId, int $userId): ?array
  {
    $savedQuestionModel = new \App\Models\SavedQuestion();
    $savedIds = $savedQuestionModel->getUserSavedIds($userId);
    // 1. Meta dane próby
    $sqlAttempt = "SELECT a.id, a.correct_count, a.total_questions, a.completed_at, a.test_type, 
		               TIMESTAMPDIFF(SECOND, a.started_at, a.completed_at) as duration_seconds,
                       et.code as exam_code
		               FROM attempts a
                       JOIN exam_types et ON a.exam_type_id = et.id
                       WHERE a.id = ? AND a.user_id = ?";
    $attempt = $this->db->fetch($sqlAttempt, [$attemptId, $userId]);

    if (!$attempt)
      return null;

    // 2. Pobierz przyporządkowanie: question_version_id -> odpowiedź użytkownika
    $sqlUserAnswers = "SELECT question_version_id, answer_id, is_correct FROM attempt_answers WHERE attempt_id = ?";
    $userAnswersRaw = $this->db->fetchAll($sqlUserAnswers, [$attemptId]);

    $userAnswersMap = [];
    foreach ($userAnswersRaw as $row) {
      $userAnswersMap[$row['question_version_id']] = [
        'answer_id' => $row['answer_id'],
        'is_correct' => $row['is_correct'],
        'was_skipped' => ($row['answer_id'] === null) // NULL answer_id = pytanie pominięte
      ];
    }

    // 3. Pobierz pytania użyte w tej próbie
    // Sortujemy po position - kolejności zapisanej przy tworzeniu próby, odzwierciedlającej
    // faktyczną, jednorazowo wylosowaną kolejność prezentacji pytań (Attempt::createAttempt()).
    // answered_at jako fallback dla starszych prób sprzed dodania kolumny position.
    $sqlQuestions = "SELECT
                                qv.id as version_id,
                                qv.question_id,
                                qv.question_text,
                                qv.explanation,
                                qv.image_name,
                                q.difficulty
                             FROM attempt_answers aa
                             JOIN question_versions qv ON aa.question_version_id = qv.id
                             JOIN questions q ON qv.question_id = q.id
                             WHERE aa.attempt_id = ?
                             GROUP BY qv.id, aa.attempt_id, aa.question_version_id, aa.position, aa.answered_at
                             ORDER BY aa.position ASC, aa.answered_at ASC";

    $questions = $this->db->fetchAll($sqlQuestions, [$attemptId]);

    // 4. Pobierz wszystkie możliwe odpowiedzi dla każdego pytania
    foreach ($questions as &$q) {
      $sqlAns = "SELECT id, answer_text, is_correct FROM answers WHERE question_version_id = ? ORDER BY id ASC";
      $answers = $this->db->fetchAll($sqlAns, [$q['version_id']]);

      $q['answers'] = $answers;

      // Dołącz info o wyborze użytkownika
      $userChoice = $userAnswersMap[$q['version_id']] ?? null;
      $q['user_answer_id'] = $userChoice['answer_id'] ?? null;
      $q['is_user_correct'] = $userChoice['is_correct'] ?? 0;
      $q['was_skipped'] = $userChoice['was_skipped'] ?? false;
      $q['is_saved'] = in_array((int) $q['question_id'], $savedIds);
    }

    return [
      'meta' => $attempt,
      'questions' => $questions
    ];
  }

  /**
   * Pobiera szczegóły pojedynczego pytania do trybu podglądu (Review).
   * Zwraca treść, obrazek, wyjaśnienie i odpowiedzi.
   */
  public function getQuestionDetails(int $questionId, ?int $userId = null): ?array
  {
    // 1. Pobierz wersję pytania
    $sqlQ = "SELECT q.id, q.difficulty, qv.question_text, qv.explanation, qv.image_name, qv.id as version_id, et.code as exam_code
		         FROM questions q
		         JOIN question_versions qv ON q.id = qv.question_id
                 JOIN exam_types et ON q.exam_type_id = et.id
		         WHERE q.id = ? AND q.is_active = 1 AND qv.is_active = 1
		         LIMIT 1";
    $question = $this->db->fetch($sqlQ, [$questionId]);

    if (!$question) {
      return null;
    }

    // 2. Pobierz odpowiedzi dla tej wersji
    $sqlA = "SELECT id, answer_text, is_correct 
		         FROM answers 
		         WHERE question_version_id = ? 
		         ORDER BY id ASC";
    $answers = $this->db->fetchAll($sqlA, [$question['version_id']]);

    $question['answers'] = $answers;

    if ($userId) {
      $savedQuestionModel = new \App\Models\SavedQuestion();
      $question['is_saved'] = $savedQuestionModel->isSaved($userId, $questionId);
    } else {
      $question['is_saved'] = false;
    }

    return $question;
  }

  /**
   * Pobiera listę podejść (historycznych egzaminów).
   */
  public function getUserExamsData(int $userId): array
  {
    $sql = "SELECT 
                    id, completed_at as date_taken, test_type, 
                    correct_count as correct_answers, total_questions, 
                    (correct_count * 100.0 / total_questions) as score_percent_raw,
                    TIMESTAMPDIFF(SECOND, started_at, completed_at) as duration_seconds
                FROM attempts 
                WHERE user_id = ? 
                ORDER BY completed_at DESC";

    $attempts = $this->db->fetchAll($sql, [$userId]);

    foreach ($attempts as &$attempt) {
      $attempt['score_percent'] = $this->formatScore((float) $attempt['score_percent_raw']);
      $attempt['is_full_exam'] = ($attempt['test_type'] === 'full_exam') ? 1 : 0;

      if ($attempt['is_full_exam']) {
        $attempt['topics'] = ['Pełny egzamin'];
      } else {
        $topicsSql = "SELECT t.name FROM topics t 
                              JOIN attempt_topics atop ON t.id = atop.topic_id 
                              WHERE atop.attempt_id = ?";
        $topics = $this->db->fetchAll($topicsSql, [$attempt['id']]);
        $attempt['topics'] = array_column($topics, 'name');
      }
    }
    return $attempts;
  }

  /**
   * Pobiera ostatnio zapisane pytania dla danej kategorii.
   */
  public function getSavedQuestions(int $userId, int $examTypeId, ?int $limit = 5): array
  {
    $limitSql = ($limit !== null) ? " LIMIT " . (int) $limit : "";
    $sql = "SELECT q.id, qv.question_text, t.name as topic_name
            FROM saved_questions sq
            JOIN questions q ON sq.question_id = q.id
            LEFT JOIN topics t ON q.topic_id = t.id
            JOIN question_versions qv ON q.id = qv.question_id
            WHERE sq.user_id = ? AND q.exam_type_id = ?
            AND q.is_active = 1 AND qv.is_active = 1
            ORDER BY sq.created_at DESC" . $limitSql;

    $items = $this->db->fetchAll($sql, [$userId, $examTypeId]);

    $sqlTotal = "SELECT COUNT(*) as total FROM saved_questions sq 
                 JOIN questions q ON sq.question_id = q.id 
                 WHERE sq.user_id = ? AND q.exam_type_id = ?";
    $total = $this->db->fetch($sqlTotal, [$userId, $examTypeId]);

    return [
      'items' => $items,
      'total' => $total['total'] ?? 0
    ];
  }
}
