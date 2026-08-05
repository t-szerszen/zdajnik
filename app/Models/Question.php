<?php

namespace App\Models;

use App\Core\Database;
use App\Services\DynamicDataService;

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
                    q.difficulty,
                    q.source,
                    t.name as topic_name,
                    qv.question_text,
                    qv.image_name,
                    qv.explanation
                FROM questions q
                JOIN question_versions qv ON q.id = qv.question_id
                JOIN topics t ON q.topic_id = t.id";
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

  // --- PUBLICZNA BAZA PYTAŃ (strony indeksowalne /pytania/...) ---

  /**
   * Buduje warunek WHERE dla treści dopuszczonej do publicznego wyświetlania,
   * opcjonalnie zawężony o filtr tematu i/lub tekstu/ID (listing z filtrami,
   * wyszukiwanie międzykwalifikacyjne).
   *
   * Jedno miejsce egzekwujące komplet bramek:
   * - pytanie i wersja aktywne oraz nieusunięte (miękko),
   * - wyłącznie NAJNOWSZA aktywna wersja pytania - bez tego dwie aktywne wersje
   *   zduplikowałyby wiersz, a więc i adres URL z tą samą treścią,
   * - wyłącznie kwalifikacje z gotową bazą pytań (AVAILABLE_EXAM_CODES).
   *
   * @param array $filters Opcjonalne filtry: topic_id, query.
   * @return array Tablica [string $where, array $params].
   */
  private function getPublicScopeSql(array $filters = []): array
  {
    $placeholders = $this->buildInClausePlaceholders(DynamicDataService::AVAILABLE_EXAM_CODES);

    $where = "q.is_active = 1 AND q.deleted_at IS NULL
              AND qv.is_active = 1 AND qv.deleted_at IS NULL
              AND qv.version_num = (
                  SELECT MAX(v.version_num) FROM question_versions v
                  WHERE v.question_id = q.id AND v.is_active = 1 AND v.deleted_at IS NULL
              )
              AND q.exam_type_id IN (SELECT id FROM exam_types WHERE code IN ($placeholders))";
    $params = DynamicDataService::AVAILABLE_EXAM_CODES;

    if (!empty($filters['topic_id'])) {
      $where .= " AND q.topic_id = ?";
      $params[] = (int) $filters['topic_id'];
    }

    if (!empty($filters['query'])) {
      $query = trim((string) $filters['query']);

      // Jeśli zapytanie jest liczbą, traktujemy je jako wyszukiwanie po konkretnym ID pytania
      if (is_numeric($query)) {
        $where .= " AND q.id = ?";
        $params[] = (int) $query;
      } elseif (mb_strlen($query) >= 3) {
        $where .= " AND (qv.question_text LIKE ? OR qv.explanation LIKE ?)";
        $params[] = "%$query%";
        $params[] = "%$query%";
      }
    }

    return [$where, $params];
  }

  /**
   * Bazowy SELECT dla stron publicznych - komplet danych potrzebnych do
   * wyrenderowania pytania oraz zbudowania jego adresu kanonicznego.
   *
   * @return string Fragment zapytania.
   */
  private function getPublicSelectionSql(): string
  {
    return "SELECT
                    q.id AS question_id,
                    qv.id AS question_version_id,
                    q.topic_id,
                    q.difficulty,
                    q.source,
                    qv.question_text,
                    qv.image_name,
                    qv.explanation,
                    et.code AS exam_code,
                    et.name AS exam_name,
                    t.name AS topic_name,
                    GREATEST(q.updated_at, qv.updated_at) AS updated_at
                FROM questions q
                JOIN question_versions qv ON q.id = qv.question_id
                JOIN exam_types et ON q.exam_type_id = et.id
                JOIN topics t ON q.topic_id = t.id";
  }

  /**
   * Pobiera pojedyncze pytanie dopuszczone do publicznego wyświetlania.
   *
   * @param int $id ID pytania.
   * @return array|false Dane pytania lub false, gdy pytanie nie może być pokazane publicznie.
   */
  public function findPublicById(int $id): array|false
  {
    [$where, $params] = $this->getPublicScopeSql();
    $params[] = $id;

    $sql = $this->getPublicSelectionSql() . " WHERE $where AND q.id = ? LIMIT 1";

    return $this->db->fetch($sql, $params);
  }

  /**
   * Zlicza publicznie dostępne pytania danej kwalifikacji (paginacja listingu),
   * opcjonalnie zawężone filtrem tematu i/lub tekstu/ID.
   *
   * @param int $examTypeId ID kwalifikacji.
   * @param array $filters Opcjonalne filtry: topic_id, query.
   * @return int Liczba pytań.
   */
  public function countPublicByExam(int $examTypeId, array $filters = []): int
  {
    [$where, $params] = $this->getPublicScopeSql($filters);
    $params[] = $examTypeId;

    $sql = "SELECT COUNT(*) AS total
            FROM questions q
            JOIN question_versions qv ON q.id = qv.question_id
            WHERE $where AND q.exam_type_id = ?";

    $result = $this->db->fetch($sql, $params);
    return (int) ($result['total'] ?? 0);
  }

  /**
   * Pobiera stronę listingu pytań danej kwalifikacji, opcjonalnie zawężoną
   * filtrem tematu i/lub tekstu/ID.
   *
   * Sortowanie po ID rosnąco jest celowe - losowa kolejność przestawiałaby
   * pytania między stronami przy każdym przejściu robota indeksującego.
   *
   * @param int $examTypeId ID kwalifikacji.
   * @param int $limit Liczba pytań na stronę.
   * @param int $offset Przesunięcie.
   * @param array $filters Opcjonalne filtry: topic_id, query.
   * @return array Lista pytań.
   */
  public function getPublicPageByExam(int $examTypeId, int $limit, int $offset, array $filters = []): array
  {
    [$where, $params] = $this->getPublicScopeSql($filters);
    $params[] = $examTypeId;

    $sql = $this->getPublicSelectionSql() . "
            WHERE $where AND q.exam_type_id = ?
            ORDER BY q.id ASC
            LIMIT " . (int) $limit . " OFFSET " . max(0, (int) $offset);

    return $this->db->fetchAll($sql, $params);
  }

  /**
   * Wyszukiwanie pytań po treści/ID BEZ zawężenia do jednej kwalifikacji.
   * Używane wyłącznie przez lekki punkt wejścia /search, zanim użytkownik
   * wybierze konkretną kwalifikację do przeglądania.
   *
   * @param string $query Fraza lub numer ID pytania.
   * @param int $limit Maksymalna liczba wyników.
   * @return array Lista pytań (z exam_code/topic_name z getPublicSelectionSql()).
   */
  public function searchAcrossExams(string $query, int $limit = 10): array
  {
    $trimmed = trim($query);
    if ($trimmed === '' || (!is_numeric($trimmed) && mb_strlen($trimmed) < 3)) {
      return [];
    }

    [$where, $params] = $this->getPublicScopeSql(['query' => $trimmed]);

    $sql = $this->getPublicSelectionSql() . "
            WHERE $where
            ORDER BY q.id DESC
            LIMIT " . (int) $limit;

    return $this->db->fetchAll($sql, $params);
  }

  /**
   * Pobiera pytania powiązane tematycznie - najbliższych sąsiadów po ID w obrębie tematu.
   *
   * Dobór jest deterministyczny (bez RAND()), dzięki czemu blok linków wewnętrznych
   * wygląda tak samo przy każdym wejściu i buduje stabilny graf linków.
   *
   * @param int $questionId ID bieżącego pytania.
   * @param int $topicId ID tematu.
   * @param int $limit Maksymalna liczba propozycji.
   * @return array Lista pytań posortowana po ID rosnąco.
   */
  public function getSimilarPublicQuestions(int $questionId, int $topicId, int $limit = 6): array
  {
    [$where, $scopeParams] = $this->getPublicScopeSql();

    $fetchNeighbours = function (string $comparison, string $direction) use ($where, $scopeParams, $questionId, $topicId, $limit): array {
      $params = array_merge($scopeParams, [$topicId, $questionId]);

      $sql = $this->getPublicSelectionSql() . "
              WHERE $where AND q.topic_id = ? AND q.id $comparison ?
              ORDER BY q.id $direction
              LIMIT " . (int) $limit;

      return $this->db->fetchAll($sql, $params);
    };

    $candidates = array_merge($fetchNeighbours('<', 'DESC'), $fetchNeighbours('>', 'ASC'));

    // Najpierw najbliżsi sąsiedzi, potem stała kolejność prezentacji
    usort($candidates, static fn(array $a, array $b): int =>
      abs((int) $a['question_id'] - $questionId) <=> abs((int) $b['question_id'] - $questionId));

    $selected = array_slice($candidates, 0, $limit);
    usort($selected, static fn(array $a, array $b): int => (int) $a['question_id'] <=> (int) $b['question_id']);

    return $selected;
  }

  /**
   * Pobiera poprzednie i następne pytanie w obrębie kwalifikacji (nawigacja liniowa).
   *
   * @param int $questionId ID bieżącego pytania.
   * @param int $examTypeId ID kwalifikacji.
   * @return array Tablica ['prev' => array|null, 'next' => array|null].
   */
  public function getPrevNextPublic(int $questionId, int $examTypeId): array
  {
    [$where, $scopeParams] = $this->getPublicScopeSql();

    $fetchAdjacent = function (string $comparison, string $direction) use ($where, $scopeParams, $questionId, $examTypeId): ?array {
      $params = array_merge($scopeParams, [$examTypeId, $questionId]);

      $sql = $this->getPublicSelectionSql() . "
              WHERE $where AND q.exam_type_id = ? AND q.id $comparison ?
              ORDER BY q.id $direction
              LIMIT 1";

      return $this->db->fetch($sql, $params) ?: null;
    };

    return [
      'prev' => $fetchAdjacent('<', 'DESC'),
      'next' => $fetchAdjacent('>', 'ASC'),
    ];
  }

  /**
   * Zestawienie kwalifikacji z liczbą publicznie dostępnych pytań (strona-hub /pytania).
   *
   * Kolumna `lastmod` niesie datę ostatniej zmiany w obrębie puli pytań danej
   * kwalifikacji - wykorzystuje ją generator mapy witryny.
   *
   * @return array Lista [exam_code, exam_name, questions_count, lastmod].
   */
  public function getPublicExamSummary(): array
  {
    [$where, $params] = $this->getPublicScopeSql();

    $sql = "SELECT
                et.code AS exam_code,
                et.name AS exam_name,
                COUNT(*) AS questions_count,
                MAX(GREATEST(q.updated_at, qv.updated_at)) AS lastmod
            FROM questions q
            JOIN question_versions qv ON q.id = qv.question_id
            JOIN exam_types et ON q.exam_type_id = et.id
            WHERE $where
            GROUP BY et.id, et.code, et.name
            ORDER BY et.code ASC";

    return $this->db->fetchAll($sql, $params);
  }

  /**
   * Wąski zestaw danych do wygenerowania sitemapy pytań.
   *
   * @param int $examTypeId ID kwalifikacji.
   * @return array Lista [question_id, question_text, exam_code, lastmod].
   */
  public function getPublicSitemapRows(int $examTypeId): array
  {
    [$where, $params] = $this->getPublicScopeSql();
    $params[] = $examTypeId;

    $sql = "SELECT
                q.id AS question_id,
                qv.question_text,
                et.code AS exam_code,
                GREATEST(q.updated_at, qv.updated_at) AS lastmod
            FROM questions q
            JOIN question_versions qv ON q.id = qv.question_id
            JOIN exam_types et ON q.exam_type_id = et.id
            WHERE $where AND q.exam_type_id = ?
            ORDER BY q.id ASC";

    return $this->db->fetchAll($sql, $params);
  }
}
