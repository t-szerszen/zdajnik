<?php

namespace App\Services;

use App\Core\Database;

/**
 * Serwis Danych Dynamicznych (DynamicDataService).
 *
 * Klasa pomocnicza służąca do agregacji i wzbogacania danych o egzaminach oraz 
 * kwalifikacjach zawodowych. Odpowiada za:
 * - Pobieranie list dostępnych kwalifikacji (INF.02, INF.03, etc.).
 * - Mapowanie technicznych kodów egzaminów na przyjazne nazwy wyświetlane.
 * - Dostarczanie metadanych SEO (Title, Description) dla poszczególnych widoków quizów.
 * - Pobieranie struktury kategorii tematycznych (Topics) dla konkretnych egzaminów.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Services
 */
class DynamicDataService
{
  /** @var string[] Kody kwalifikacji z gotową bazą pytań, dostępne dla użytkowników w trybach nauki i wyszukiwarce */
  public const AVAILABLE_EXAM_CODES = ['INF.03', 'INF.04'];

  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  /**
   * Inicjalizuje serwis danych dynamicznych.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Pobiera listę wszystkich dostępnych typów egzaminów z bazy danych.
   *
   * @return array Tablica asocjacyjna [id, code].
   */
  public function getExams(): array
  {
    $sql = "SELECT id, code FROM exam_types";
    return $this->db->fetchAll($sql) ?? [];
  }

  /**
   * Pobiera listę typów egzaminów, których baza pytań jest udostępniona
   * użytkownikom (patrz self::AVAILABLE_EXAM_CODES).
   *
   * Używane wszędzie tam, gdzie użytkownik może dotrzeć do pytań bezpośrednio
   * (np. wyszukiwarka), aby kwalifikacje "Wkrótce" nie były wybieralne ani
   * przeszukiwalne, mimo że nadal figurują w bazie.
   *
   * @return array Tablica asocjacyjna [id, code].
   */
  public function getSearchableExams(): array
  {
    return array_values(array_filter(
      $this->getExams(),
      static fn(array $exam): bool => in_array(strtoupper($exam['code']), self::AVAILABLE_EXAM_CODES, true)
    ));
  }

  /**
   * Pobiera udostępnione kwalifikacje wraz ze znacznikiem ostatniej modyfikacji.
   *
   * Używane przez generator mapy witryny - w odróżnieniu od getSearchableExams()
   * dokłada `updated_at`, który trafia do znacznika <lastmod>.
   *
   * @return array Tablica [id, code, name, updated_at].
   */
  public function getSearchableExamsWithTimestamps(): array
  {
    $placeholders = implode(',', array_fill(0, count(self::AVAILABLE_EXAM_CODES), '?'));

    $sql = "SELECT id, code, name, updated_at
            FROM exam_types
            WHERE is_active = 1 AND deleted_at IS NULL AND code IN ($placeholders)
            ORDER BY code ASC";

    return $this->db->fetchAll($sql, self::AVAILABLE_EXAM_CODES);
  }

  /**
   * Rozwiązuje segment adresu URL na kod kwalifikacji (np. "inf03", "INF.03",
   * "inf-03" => "INF.03").
   *
   * Segment jest normalizowany tą samą funkcją co kody kandydatów
   * (exam_code_to_url() - usuwa wszystko poza literami/cyframi i obniża
   * wielkość liter), dzięki czemu dopasowuje dowolny wariant zapisu, nie
   * tylko już-kanoniczną formę bez separatora. Dopasowanie odbywa się
   * wyłącznie w obrębie kwalifikacji z gotową bazą pytań
   * (self::AVAILABLE_EXAM_CODES), dzięki czemu publiczne strony pytań nigdy nie
   * wystawią treści z kwalifikacji oznaczonej jako "Wkrótce".
   *
   * @param string $segment Segment adresu URL (dowolny wariant zapisu).
   * @return string|null Kanoniczny kod kwalifikacji z bazy lub null, gdy segment nie pasuje do żadnej dostępnej.
   */
  public static function getExamCodeByUrlSegment(string $segment): ?string
  {
    $segment = exam_code_to_url($segment);

    foreach (self::AVAILABLE_EXAM_CODES as $code) {
      if (exam_code_to_url($code) === $segment) {
        return $code;
      }
    }

    return null;
  }

  /**
   * Rozwiązuje segment adresu URL na kod kwalifikacji, bez ograniczenia do
   * self::AVAILABLE_EXAM_CODES - przeszukuje wszystkie kwalifikacje w bazie.
   *
   * Odpowiednik getExamCodeByUrlSegment() dla miejsc, gdzie dostępność nie
   * jest jeszcze rozstrzygnięta osobną logiką (np. Biblioteka Arkuszy, która
   * w przyszłości może objąć również kwalifikacje spoza listy "gotowych").
   *
   * @param string $segment Segment adresu URL (dowolny wariant zapisu).
   * @return string|null Kanoniczny kod kwalifikacji z bazy lub null, gdy segment nie pasuje do żadnej.
   */
  public function resolveAnyExamCodeByUrlSegment(string $segment): ?string
  {
    $segment = exam_code_to_url($segment);

    foreach ($this->getExams() as $exam) {
      if (exam_code_to_url((string) $exam['code']) === $segment) {
        return (string) $exam['code'];
      }
    }

    return null;
  }

  /**
   * Pobiera unikalny identyfikator egzaminu na podstawie jego kodu tekstowego.
   *
   * @param string $code Kod kwalifikacji (np. 'INF.03').
   * @return int|null ID egzaminu lub null w przypadku braku dopasowania.
   */
  public function getExamIdByCode(string $code): ?int
  {
    $sql = "SELECT id FROM exam_types WHERE code = ? LIMIT 1";
    $result = $this->db->fetch($sql, [$code]);
    return $result ? (int) $result['id'] : null;
  }

  /**
   * Odwrotność getExamIdByCode() - potrzebna wyłącznie do przekierowania
   * starych adresów /search?exam_id=N (numeryczne ID) na kanoniczny
   * /pytania/{code}.
   *
   * @param int $id ID kwalifikacji.
   * @return string|null Kod kwalifikacji lub null, gdy ID nie istnieje.
   */
  public function getExamCodeById(int $id): ?string
  {
    $sql = "SELECT code FROM exam_types WHERE id = ? LIMIT 1";
    $result = $this->db->fetch($sql, [$id]);
    return $result['code'] ?? null;
  }

  /**
   * Rozwiązuje segment adresu URL (np. "inf03") na pełne dane kwalifikacji
   * dopuszczonej do publikacji - łączy getExamCodeByUrlSegment() z
   * getExamDetails() w jednym wywołaniu. Używane przez QuestionController
   * i SearchController.
   *
   * @param string $urlCode Segment adresu URL.
   * @return array|null Dane kwalifikacji lub null, gdy nieznany/niedostępny.
   */
  public function resolveExamByUrlCode(string $urlCode): ?array
  {
    $examCode = self::getExamCodeByUrlSegment($urlCode);
    if ($examCode === null) {
      return null;
    }

    return $this->getExamDetails($examCode);
  }

  /**
   * Pobiera rozszerzone detale egzaminu wraz z metadanymi SEO.
   *
   * Metoda łączy surowe dane z bazy danych z predefiniowaną mapą treści (Content Map) 
   * dla celów optymalizacji wyszukiwarek (SEO) i poprawy doświadczenia użytkownika (UX).
   *
   * @param string $code Kod techniczny kwalifikacji.
   * @return array|null Scalona tablica danych egzaminu lub null.
   */
  public function getExamDetails(string $code): ?array
  {
    $examId = $this->getExamIdByCode($code);
    if (!$examId) {
      return null;
    }

    // KROK 1: Pobranie podstawowych danych strukturalnych z bazy
    $sql = "SELECT * FROM exam_types WHERE id = ?";
    $dbExam = $this->db->fetch($sql, [$examId]);

    // KROK 2: Mapowanie metadanych SEO i UX (Fallback dla nowych kwalifikacji)
    // UWAGA: W przyszłości ta mapa powinna zostać przeniesiona do tabeli w bazie danych (np. exam_meta).
    $metadata = [
      'INF.03' => [
        'formatted_name' => 'INF.03',
        'title_one_question' => 'INF.03 - Jedno pytanie - Inteligentna Powtórka Online | Zdajnik',
        'desc_one_question' => 'Nie masz czasu na cały test? Wylosuj jedno pytanie z bazy egzaminacyjnej INF.03 i sprawdź swoją wiedzę w mniej niż minutę.',
        'title_test' => 'INF.03 - Egzamin próbny - Test Online 40 Pytań | Zdajnik',
        'desc_test' => 'Sprawdź swoją wiedzę przed egzaminem zawodowym INF.03. Rozwiąż pełny test próbny online.',
        'title_personalized' => 'INF.03 - Stwórz swój własny test - Spersonalizowana Nauka | Zdajnik',
        'desc_personalized' => 'Dopasuj naukę do swoich potrzeb. Stwórz spersonalizowany test z kwalifikacji INF.03.'
      ],
      'INF.02' => [
        'formatted_name' => 'INF.02',
        'title_one_question' => 'INF.02 - Jedno pytanie - Szybka Powtórka | Zdajnik',
        'desc_one_question' => 'Masz chwilę przerwy? Wylosuj jedno pytanie o sieciach, systemach czy sprzęcie z bazy INF.02 i sprawdź się na szybko.',
        'title_test' => 'INF.02 - Egzamin próbny - Test Online 40 Pytań | Zdajnik',
        'desc_test' => 'Sprawdź się przed egzaminem INF.02 - pełny test próbny 40 pytań z sieci, systemów i sprzętu komputerowego.',
        'title_personalized' => 'INF.02 - Stwórz swój własny test - Spersonalizowana Nauka | Zdajnik',
        'desc_personalized' => 'Skup się na tym, co sprawia Ci trudność - sieci, Windows Server czy diagnostyka - i zbuduj test dopasowany do siebie.'
      ],
      'INF.04' => [
        'formatted_name' => 'INF.04',
        'title_one_question' => 'INF.04 - Jedno pytanie - Szybki Trening | Zdajnik',
        'desc_one_question' => 'Masz 5 minut? Wylosuj jedno pytanie z programowania i algorytmów na INF.04 i sprawdź, ile już umiesz.',
        'title_test' => 'INF.04 - Egzamin próbny - Test Online 40 Pytań | Zdajnik',
        'desc_test' => 'Sprawdź się przed egzaminem INF.04 - 40 pytań z programowania, algorytmów i OOP, dokładnie jak na prawdziwym teście.',
        'title_personalized' => 'INF.04 - Stwórz swój własny test - Spersonalizowana Nauka | Zdajnik',
        'desc_personalized' => 'Wybierz tematy, które sprawiają Ci najwięcej problemów - OOP, algorytmy czy testowanie - i zrób test skrojony pod siebie.'
      ]
    ];

    $meta = $metadata[$code] ?? [
      'formatted_name' => $code,
      'title_one_question' => "$code - Jedno pytanie | Zdajnik",
      'desc_one_question' => "Losowe pytanie z kwalifikacji $code.",
      'title_test' => "$code - Egzamin Próbny | Zdajnik",
      'desc_test' => "Pełny test próbny z $code.",
      'title_personalized' => "$code - Test Spersonalizowany | Zdajnik",
      'desc_personalized' => "Stwórz własny test z $code."
    ];

    // KROK 3: Połączenie surowych danych SQL z metadanymi aplikacyjnymi
    return array_merge($dbExam, $meta);
  }

  /**
   * Pobiera listę tematów (podkategorii) przypisanych do danej kwalifikacji.
   * 
   * @param int $examId ID kwalifikacji.
   * @return array Tablica tematów [id, name].
   */
  public function getCategoriesForExam(int $examId): array
  {
    $sql = "SELECT name, id FROM topics WHERE exam_type_id = ? ORDER BY id ASC";
    return $this->db->fetchAll($sql, [$examId]);
  }
}