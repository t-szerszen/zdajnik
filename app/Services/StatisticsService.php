<?php

namespace App\Services;

use App\Core\Database;

class StatisticsService
{
	private Database $db;

	private int $defaultLimit = 5;

	public function __construct()
	{
		$this->db = Database::getInstance();
	}

	/**
	 * Pobiera wszystkie dostępne typy egzaminów dla selektora.
	 */
	public function getExamTypes(): array
	{
		$sql = "SELECT id, code, name FROM exam_types WHERE is_active = 1 AND deleted_at IS NULL";
		return $this->db->fetchAll($sql);
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

		// Liczba pytań, na które użytkownik odpowiedział poprawnie chociaż raz (lub ostatnio poprawnie)
		$sqlCompleted = "SELECT COUNT(*) as completed FROM user_progress up
                         JOIN questions q ON up.question_id = q.id
                         WHERE up.user_id = ? AND q.exam_type_id = ? AND up.last_result = 1
                         AND q.is_active = 1 AND q.deleted_at IS NULL";
		$completedResult = $this->db->fetch($sqlCompleted, [$userId, $examTypeId]);
		$completedQuestions = $completedResult['completed'] ?? 0;

		$percent = ($totalQuestions > 0) ? round(($completedQuestions / $totalQuestions) * 100) : 0;

		return [
			'total' => $totalQuestions,
			'completed' => $completedQuestions,
			'percent' => $percent
		];
	}

	/**
	 * Pobiera statystyki postępu z podziałem na tematy.
	 */
	public function getTopicStats(int $userId, int $examTypeId): array
	{
		$sql = "SELECT t.id, t.name, 
                       (SELECT COUNT(*) FROM questions q WHERE q.topic_id = t.id AND q.is_active = 1 AND q.deleted_at IS NULL) as total,
                       (SELECT COUNT(*) FROM user_progress up 
                        JOIN questions q ON up.question_id = q.id 
                        WHERE up.user_id = ? AND q.topic_id = t.id AND up.last_result = 1
                        AND q.is_active = 1 AND q.deleted_at IS NULL) as completed
                FROM topics t
                WHERE t.exam_type_id = ? AND t.is_active = 1 AND t.deleted_at IS NULL";

		$topics = $this->db->fetchAll($sql, [$userId, $examTypeId]);

		foreach ($topics as &$topic) {
			$topic['progress'] = ($topic['total'] > 0) ? round(($topic['completed'] / $topic['total']) * 100) : 0;
		}

		return $topics;
	}

	/**
	 * Pobiera rekordy użytkownika (najlepszy wynik, ostatnia aktywność itp.).
	 */
	public function getRecords(int $userId, int $examTypeId): array
	{
		$sql = "SELECT 
                    MAX(ROUND((correct_count / total_questions) * 100)) as best_score,
                    MAX(completed_at) as last_exam,
                    COUNT(id) as total_exams,
                    AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration
                FROM attempts 
                WHERE user_id = ? AND exam_type_id = ? AND completed_at IS NOT NULL";

		$result = $this->db->fetch($sql, [$userId, $examTypeId]);

		return [
			'best_score' => $result['best_score'] ?? 0,
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
			$limitSql = "LIMIT " . (int)$limit;
		}
		
		$sql = "SELECT q.id, qv.question_text
                FROM user_progress up
                JOIN questions q ON up.question_id = q.id
                LEFT JOIN question_versions qv ON q.id = qv.question_id AND qv.is_active = 1 AND qv.deleted_at IS NULL
                WHERE up.user_id = ? 
                  AND q.exam_type_id = ? 
                  AND up.last_result = 0
                  AND q.is_active = 1 
                  AND q.deleted_at IS NULL
                GROUP BY q.id
                ORDER BY up.updated_at DESC
                $limitSql";

		return $this->db->fetchAll($sql, [$userId, $examTypeId]);
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
		$totalAnswered = (int)($totalResult['total_answered'] ?? 0);
		
		$isUnlocked = $totalAnswered >= 100;
		$unlockProgress = min(100, $totalAnswered); // Cap at 100
		
		// Pobierz krytyczne błędy
		$items = [];
		if ($isUnlocked) {
			$limitSql = "";
			if ($limit !== null) {
				$limitSql = "LIMIT " . (int)$limit;
			}

			$sql = "SELECT q.id as question_id, qv.question_text, 
			               ROUND((up.correct_count / up.attempts_count) * 100) as success_rate
			        FROM user_progress up
			        JOIN questions q ON up.question_id = q.id
			        LEFT JOIN question_versions qv ON q.id = qv.question_id 
			                                         AND qv.is_active = 1 
			                                         AND qv.deleted_at IS NULL
			        WHERE up.user_id = ? 
			          AND q.exam_type_id = ? 
			          AND q.is_active = 1 
			          AND q.deleted_at IS NULL
			          AND (up.correct_count / up.attempts_count) < 0.6
			        GROUP BY q.id
			        ORDER BY success_rate ASC, up.updated_at DESC
			        $limitSql";
			$items = $this->db->fetchAll($sql, [$userId, $examTypeId]);
		}
		
		return [
			'items' => $items,
			'total_answered' => $totalAnswered,
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
			$limitSql = "LIMIT " . (int)$limit;
		}

		$sql = "SELECT id, ROUND((correct_count / total_questions) * 100) as score, completed_at, test_type
                FROM attempts 
                WHERE user_id = ? AND exam_type_id = ? AND completed_at IS NOT NULL
                ORDER BY completed_at DESC
                $limitSql";

		$exams = $this->db->fetchAll($sql, [$userId, $examTypeId]);

		foreach ($exams as &$exam) {
			$date = date('d.m.Y', strtotime($exam['completed_at']));
			
			if ($exam['test_type'] === 'full_exam') {
				$exam['display_name'] = "Pełny test";
				$exam['badge_text'] = "Pełny test";
			} else {
				// Fetch topics for personalized test
				$topicsSql = "SELECT t.name FROM topics t 
                              JOIN attempt_topics atop ON t.id = atop.topic_id 
                              WHERE atop.attempt_id = ?";
				$topics = $this->db->fetchAll($topicsSql, [$exam['id']]);
				$topicNames = array_column($topics, 'name');
				
				$exam['display_name'] = "Test z " . implode(', ', $topicNames);
				$exam['badge_text'] = "Personalizowany test";
			}
		}

		return $exams;
	}

	/**
	 * Pobiera dane do wykresu trendu (ostatnie 10 wyników).
	 * @deprecated Use getEfficiencyTrendWithMovingAvg() for smoother trend visualization
	 */
	public function getEfficiencyTrend(int $userId, int $examTypeId, int $limit = 10): array
	{
		$limit = (int)$limit;
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
			$limitSql = "LIMIT " . (int)$limit;
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
		
		// MVP: Używamy correct_count >= 1 jako przybliżenia "opanowania"
		// W przyszłości można dodać kolumnę consecutive_correct w user_progress
		$sqlMastered = "SELECT COUNT(*) as mastered FROM user_progress up
		                JOIN questions q ON up.question_id = q.id
		                WHERE up.user_id = ? AND q.exam_type_id = ? 
		                AND up.correct_count >= 1
		                AND q.is_active = 1 AND q.deleted_at IS NULL";
		$masteredResult = $this->db->fetch($sqlMastered, [$userId, $examTypeId]);
		$masteredQuestions = $masteredResult['mastered'] ?? 0;
		
		$percent = ($totalQuestions > 0) ? round(($masteredQuestions / $totalQuestions) * 100) : 0;
		
		return [
			'total' => $totalQuestions,
			'mastered' => $masteredQuestions,
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
		
		$predictedScore = round($weightedSum / $totalWeight);
		
		// Określ poziom pewności na podstawie liczby egzaminów
		$confidenceLevel = match(true) {
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
                    qv.image_path,
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
		// 1. Meta dane próby
		$sqlAttempt = "SELECT id, correct_count, total_questions, completed_at, test_type, 
		               TIMESTAMPDIFF(SECOND, started_at, completed_at) as duration_seconds 
		               FROM attempts WHERE id = ? AND user_id = ?";
		$attempt = $this->db->fetch($sqlAttempt, [$attemptId, $userId]);
		
		if (!$attempt) return null;

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
            $sqlQuestions = "SELECT 
                                qv.id as version_id, 
                                qv.question_id,
                                qv.question_text, 
                                qv.explanation, 
                                qv.image_path
                             FROM attempt_answers aa
                             JOIN question_versions qv ON aa.question_version_id = qv.id
                             WHERE aa.attempt_id = ?
                             GROUP BY qv.id, aa.attempt_id, aa.question_version_id, aa.answered_at
                             ORDER BY aa.answered_at ASC";
						 
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
	public function getQuestionDetails(int $questionId): ?array
	{
		// 1. Pobierz wersję pytania
		$sqlQ = "SELECT q.id, qv.question_text, qv.explanation, qv.image_path, qv.id as version_id
		         FROM questions q
		         JOIN question_versions qv ON q.id = qv.question_id
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
                    ROUND((correct_count / total_questions) * 100, 2) as score_percent,
                    TIMESTAMPDIFF(SECOND, started_at, completed_at) as duration_seconds
                FROM attempts 
                WHERE user_id = ? 
                ORDER BY completed_at DESC";

		$attempts = $this->db->fetchAll($sql, [$userId]);

		foreach ($attempts as &$attempt) {
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
}
