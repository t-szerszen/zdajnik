<?php

namespace App\Models;

use App\Core\Database;

/**
 * Model Pytania (Question) - Serce Bazy Pytań.
 *
 * Klasa implementuje architekturę wersjonowania pytań:
 * - `questions`: Statyczne ID pytania i jego przynależność (egzamin, temat).
 * - `question_versions`: Treść pytania, zdjęcia i wyjaśnienia (umożliwia historię zmian).
 *
 * Model oferuje zaawansowane algorytmy doboru pytań do testów:
 * - Losowanie (Random pool).
 * - Algorytm "Nieodkryte" (Discovery mode).
 * - Algorytm "Słabe punkty" (Accuracy mode).
 * - Algorytm "Zapomniane" (Spaced repetition simulation).
 * - Filtrowanie i wyszukiwanie tekstowe z paginacją.
 *
 * @version 2.2.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class Question
{
  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  /**
   * Inicjalizuje model pytań.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Bazowy fragment SQL łączący tabelę pytań z ich aktywnymi wersjami.
   * Centralizacja tego fragmentu ułatwia późniejsze modyfikacje struktury.
   * 
   * @return string Fragment zapytania JOIN.
   */
  private function getBaseSelectionSql(): string
  {
    return "SELECT 
                    q.id as question_id, 
                    qv.id as question_version_id, 
                    q.topic_id, 
                    qv.question_text, 
                    qv.image_name, 
                    qv.explanation 
                FROM questions q
                JOIN question_versions qv ON q.id = qv.question_id";
  }

  /**
   * Pobiera losowy zestaw pytań z wybranych tematów.
   *
   * @param array<int> $subjectIds Lista ID tematów.
   * @param int $limit Maksymalna liczba pytań.
   * @param int $examTypeId ID kwalifikacji.
   * @return array Zestaw pytań.
   */
  public function getQuestions(array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders) {
      return [];
    }

    $sql = $this->getBaseSelectionSql() . "
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND q.topic_id IN ($placeholders) 
                AND q.exam_type_id = ? 
                ORDER BY RAND() LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge($subjectIds, [$examTypeId]));
  }

  /**
   * Pobiera pytania, których użytkownik jeszcze nigdy nie widział (tryb nauki).
   *
   * @param int $userId ID użytkownika.
   * @param array<int> $subjectIds Lista tematów.
   * @param int $limit Limit wyników.
   * @param int $examTypeId ID kwalifikacji.
   * @return array
   */
  public function getUndiscoveredQuestions(int $userId, array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders) {
      return [];
    }

    $sql = $this->getBaseSelectionSql() . "
                LEFT JOIN user_progress up ON q.id = up.question_id AND up.user_id = ? 
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND up.question_id IS NULL
                AND q.topic_id IN ($placeholders) 
                AND q.exam_type_id = ?
                ORDER BY RAND() LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge([$userId], $subjectIds, [$examTypeId]));
  }

  /**
   * Wybiera pytania, w których użytkownik ma najniższą skuteczność (poniżej 60%).
   * 
   * @param int $userId ID użytkownika.
   * @param array<int> $subjectIds Lista tematów.
   * @param int $limit Limit wyników.
   * @param int $examTypeId ID kwalifikacji.
   * @return array
   */
  public function getLowerAccuracyQuestions(int $userId, array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders) {
      return [];
    }

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

  /**
   * Wybiera pytania, które nie były powtarzane od najdłuższego czasu.
   * 
   * @param int $userId ID użytkownika.
   * @param array<int> $subjectIds Lista tematów.
   * @param int $limit Limit wyników.
   * @param int $examTypeId ID kwalifikacji.
   * @return array
   */
  public function getQuestionsRepeatedAtTheLatest(int $userId, array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders) {
      return [];
    }

    $sql = $this->getBaseSelectionSql() . "
                INNER JOIN user_progress up ON q.id = up.question_id AND up.user_id = ?
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND q.topic_id IN ($placeholders) AND q.exam_type_id = ?
                ORDER BY up.updated_at ASC LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge([$userId], $subjectIds, [$examTypeId]));
  }

  /**
   * Pobiera losowe pytania, na które użytkownik udzielił ostatnio błędnej odpowiedzi.
   * 
   * @param int $userId ID użytkownika.
   * @param array<int> $subjectIds Lista tematów.
   * @param int $limit Limit wyników.
   * @param int $examTypeId ID kwalifikacji.
   * @return array
   */
  public function getLastMistakes(int $userId, array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders) {
      return [];
    }

    $sql = $this->getBaseSelectionSql() . "
                INNER JOIN user_progress up ON q.id = up.question_id AND up.user_id = ?
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND q.topic_id IN ($placeholders) 
                AND q.exam_type_id = ? 
                AND up.last_result = 0
                ORDER BY RAND() LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge([$userId], $subjectIds, [$examTypeId]));
  }

  /**
   * Pobiera pytania zapisane przez użytkownika w "ulubionych".
   * 
   * @param int $userId ID użytkownika.
   * @param array<int> $subjectIds Lista tematów.
   * @param int $limit Limit wyników.
   * @param int $examTypeId ID kwalifikacji.
   * @return array
   */
  public function getSavedQuestions(int $userId, array $subjectIds, int $limit, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders) {
      return [];
    }

    $sql = $this->getBaseSelectionSql() . "
                INNER JOIN saved_questions sq ON q.id = sq.question_id AND sq.user_id = ?
                WHERE q.is_active = 1 AND qv.is_active = 1
                AND q.topic_id IN ($placeholders) AND q.exam_type_id = ?
                ORDER BY RAND() LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, array_merge([$userId], $subjectIds, [$examTypeId]));
  }

  /**
   * Pobiera statystyki progresu (ile rozwiązano vs ile istnieje) dla danej puli tematów.
   * 
   * @param int $userId ID użytkownika.
   * @param array $subjectIds Lista tematów.
   * @param int $examTypeId ID kwalifikacji.
   * @return array Tablica ['total' => int, 'answered' => int].
   */
  public function getPoolProgress(int $userId, array $subjectIds, int $examTypeId): array
  {
    $placeholders = $this->buildInClausePlaceholders($subjectIds);
    if (!$placeholders) {
      return ['total' => 0, 'answered' => 0];
    }

    // Liczba wszystkich dostępnych pytań w puli
    $sqlTotal = "SELECT COUNT(*) as total FROM questions q 
                 WHERE q.topic_id IN ($placeholders) AND q.exam_type_id = ? 
                 AND q.is_active = 1 AND q.deleted_at IS NULL";
    $total = $this->db->fetch($sqlTotal, array_merge($subjectIds, [$examTypeId]));

    // Liczba pytań, na które użytkownik udzielił co najmniej jednej odpowiedzi
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

  /**
   * Pobiera ID bazowego pytania na podstawie identyfikatora wersji.
   * 
   * @param int $qvId ID wersji pytania.
   * @return int|false
   */
  public function getQuestionIdByVersionId(int $qvId): int|false
  {
    $sql = "SELECT question_id FROM question_versions WHERE id = ? LIMIT 1";
    $result = $this->db->fetch($sql, [$qvId]);
    return $result ? (int) $result['question_id'] : false;
  }

  /**
   * Pobiera pojedyncze pytanie wraz z jego aktualną wersją.
   * 
   * @param int $id ID pytania.
   * @return array|false
   */
  public function getQuestionById(int $id): array|false
  {
    $sql = $this->getBaseSelectionSql() . " WHERE q.is_active = 1 AND qv.is_active = 1 AND q.id = ? LIMIT 1";
    return $this->db->fetch($sql, [$id]);
  }

  /**
   * Zwraca ID typu egzaminu na podstawie jego kodu technicznego (np. INF.02).
   * 
   * @param string $code Kod kwalifikacji.
   * @return int|null
   */
  public function getExamTypeIdByCode(string $code): ?int
  {
    $sql = 'SELECT id FROM exam_types WHERE code = ? AND is_active = 1 LIMIT 1';
    $result = $this->db->fetch($sql, [$code]);
    return $result['id'] ?? null;
  }

  /**
   * Pobiera unikalne ID tematów, które mają przypisane jakiekolwiek pytania w danym egzaminie.
   * 
   * @param int $examTypeId ID kwalifikacji.
   * @return array
   */
  public function getTopicIdsByExamType(int $examTypeId): array
  {
    $sql = 'SELECT DISTINCT topic_id FROM questions WHERE exam_type_id = ? AND is_active = 1';
    $results = $this->db->fetchAll($sql, [$examTypeId]);
    return array_column($results, 'topic_id');
  }

  /**
   * Generuje listę placeholderów (?) dla klauzul SQL IN.
   * 
   * @param array $ids Tablica wartości.
   * @return string|null Ciąg oddzielony przecinkami lub null.
   */
  private function buildInClausePlaceholders(array $ids): ?string
  {
    if (empty($ids)) {
      return null;
    }
    return implode(',', array_fill(0, count($ids), '?'));
  }

  /**
   * Buduje warunki WHERE i wiązania parametrów dla wyszukiwarki pytań.
   * 
   * @param array $filters Tablica filtrów (exam_id, topic_id, query).
   * @return array Tablica [string $where, array $params].
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

      // Jeśli zapytanie jest liczbą, traktujemy je jako wyszukiwanie po konkretnym ID pytania
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
   * Oblicza całkowitą liczbę pytań pasujących do filtrów (na potrzeby paginacji).
   * 
   * @param array $filters Filtry wyszukiwania.
   * @return int Total count.
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
   * Implementuje zaawansowane wyszukiwanie pytań z paginacją i dołączaniem nazw kategorii.
   * 
   * @param array $filters Filtry wyszukiwania.
   * @param int $page Numer strony.
   * @param int $limit Liczba rekordów na stronę.
   * @return array
   */
  public function searchQuestions(array $filters, int $page = 1, int $limit = 25): array
  {
    [$where, $params] = $this->buildSearchWhereClause($filters);

    $sql = "SELECT 
                q.id as question_id, 
                qv.id as question_version_id, 
                q.topic_id, 
                qv.question_text, 
                qv.image_name, 
                qv.explanation,
                et.code as exam_code,
                t.name as topic_name
            FROM questions q
            JOIN question_versions qv ON q.id = qv.question_id
            JOIN exam_types et ON q.exam_type_id = et.id
            JOIN topics t ON q.topic_id = t.id
            WHERE $where ";

    $sql .= " ORDER BY q.id DESC ";

    // Kalkulacja offsetu dla SQL LIMIT
    $offset = ($page - 1) * $limit;
    if ($offset < 0) {
      $offset = 0;
    }

    $sql .= " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

    return $this->db->fetchAll($sql, $params);
  }
}
