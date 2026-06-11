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
        'formatted_name' => 'EE.09 / INF.03',
        'title_one_question' => 'Jedno pytanie z INF.03 | Examly',
        'desc_one_question' => 'Wybierz temat i rozwiąż jedno pytanie z INF.03. Od razu sprawdź odpowiedź i wyjaśnienie.',
        'title_test' => 'Próba egzaminacyjna INF.03 - 40 pytań | Examly',
        'desc_test' => 'Rozwiąż 40 pytań z INF.03 na czas, sprawdź wynik i zobacz, co warto powtórzyć.',
        'title_personalized' => 'Własny zestaw pytań INF.03 | Examly',
        'desc_personalized' => 'Wybierz tematy, liczbę pytań i sposób ich doboru. Ułóż własny zestaw z INF.03.'
      ],
      'INF.02' => [
        'formatted_name' => 'EE.08 / INF.02',
        'title_one_question' => 'Jedno pytanie z INF.02 | Examly',
        'desc_one_question' => 'Wybierz temat i rozwiąż jedno pytanie z INF.02.',
        'title_test' => 'Próba egzaminacyjna INF.02 | Examly',
        'desc_test' => 'Rozwiąż pełną próbę egzaminacyjną z INF.02 i sprawdź wynik.',
        'title_personalized' => 'Własny zestaw pytań INF.02 | Examly',
        'desc_personalized' => 'Wybierz tematy i ułóż własny zestaw pytań z INF.02.'
      ],
      'INF.04' => [
        'formatted_name' => 'E.14 / INF.04',
        'title_one_question' => 'Jedno pytanie z INF.04 | Examly',
        'desc_one_question' => 'Wybierz temat i rozwiąż jedno pytanie z INF.04. Od razu sprawdź odpowiedź i wyjaśnienie.',
        'title_test' => 'Próba egzaminacyjna INF.04 - 40 pytań | Examly',
        'desc_test' => 'Rozwiąż 40 pytań z INF.04 na czas, sprawdź wynik i zobacz, co warto powtórzyć.',
        'title_personalized' => 'Własny zestaw pytań INF.04 | Examly',
        'desc_personalized' => 'Wybierz tematy, liczbę pytań i sposób ich doboru. Ułóż własny zestaw z INF.04.'
      ]
    ];

    $meta = $metadata[$code] ?? [
      'formatted_name' => $code,
      'title_one_question' => "$code - Jedno pytanie | Examly",
      'desc_one_question' => "Losowe pytanie z kwalifikacji $code.",
      'title_test' => "Próba egzaminacyjna $code | Examly",
      'desc_test' => "Rozwiąż pełną próbę egzaminacyjną z $code.",
      'title_personalized' => "Własny zestaw pytań $code | Examly",
      'desc_personalized' => "Wybierz zakres i ułóż własny zestaw pytań z $code."
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
