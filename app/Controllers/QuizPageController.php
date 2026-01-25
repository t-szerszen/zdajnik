<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\StatisticsService;

/**
 * Kontroler Stron Związanych z Quizami.
 *
 * Odpowiada za renderowanie widoków HTML dla różnych trybów nauki
 * i testów w ramach kwalifikacji INF.03. Działa jako pośrednik
 * między routingiem a warstwą prezentacji (szablonami).
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */
class QuizPageController extends BaseController
{
  private Database $db;
  private StatisticsService $statsService;

  public function __construct()
  {
    parent::__construct();
    $this->db = Database::getInstance();
    $this->statsService = new StatisticsService();
  }

  /**
   * Wyświetla stronę quizu w trybie "jedno pytanie".
   *
   * @return void
   */
  public function showOneQuestionPage(string $code): void
  {
    $exam = $this->getExamDetails($code);
    if ($exam) {
      $categories = $this->getCategoriesForExam($exam['id']);
      $this->renderView('one_question', [
        'exam' => $exam,
        'examCode' => $code, // Zachowujemy dla kompatybilności JS
        'categories' => $categories
      ]);
    } else {
      http_response_code(404);
      echo "Egzamin o kodzie $code nie istnieje.";
    }
  }

  /**
   * Wyświetla stronę interfejsu do generowania spersonalizowanego testu.
   *
   * @return void
   */
  public function showPersonalizedTestPage(string $code): void
  {
    $exam = $this->getExamDetails($code);
    if (!$exam) {
      http_response_code(404);
      echo "Egzamin o kodzie $code nie istnieje.";
      return;
    }

    // Przygotuj domyślną tablicę danych dla widoku.
    $data = [
      'exam' => $exam,
      'examCode' => $code,
      'selectedSubjectId' => null // Domyślnie brak wybranego tematu
    ];

    // Sprawdź, czy przekazano ID tematu w URL.
    if (isset($_GET['subject']) && is_array($_GET['subject']) && !empty($_GET['subject'][0])) {
      // Jeśli tak, nadpisz wartość w tablicy danych.
      $data['selectedSubjectId'] = (int) $_GET['subject'][0];
    }
    
    // Fetch categories needed for the view
    $data['categories'] = $this->getCategoriesForExam($exam['id']);

    // Wyrenderuj widok tylko raz, przekazując kompletną tablicę danych.
    $this->renderView('personalized_test', $data);
  }

  /**
   * Wyświetla stronę pełnego testu egzaminacyjnego.
   *
   * @return void
   */
  public function showTestPage(string $code): void
  {
    $exam = $this->getExamDetails($code);
    if ($exam) {
      $this->renderView('test', [
        'exam' => $exam,
        'examCode' => $code
      ]);
    } else {
      http_response_code(404);
      echo "Egzamin o kodzie $code nie istnieje.";
    }
  }

  // Private
  private function getExamIdByCode($code): int|null
  {
    $sql = "SELECT id FROM exam_types WHERE code = ? LIMIT 1";
    $result = $this->db->fetch($sql, [$code]);
    return $result ? (int) $result['id'] : null;
  }

  /**
   * Pobiera szczegóły egzaminu na podstawie kodu.
   * Łączy dane z bazy z predefiniowanymi opisami dla lepszego UX.
   */
  private function getExamDetails(string $code): ?array
  {
    $examId = $this->getExamIdByCode($code);
    if (!$examId) return null;

    // Pobierz podstawowe dane z bazy (zakładając, że tabela exam_types może mieć kolumnę name, ale jeśli nie - fallback)
    $sql = "SELECT * FROM exam_types WHERE id = ?";
    $dbExam = $this->db->fetch($sql, [$examId]);

    // Predefiniowane metadane dla znanych kodów (dla bogatych opisów)
    // W przyszłości te dane powinny trafić do bazy danych.
    $metadata = [
      'INF.03' => [
        'formatted_name' => 'EE.09 / INF.03',
        'title_one_question' => 'INF.03 - Jedno pytanie - Inteligentna Powtórka Online | Examly',
        'desc_one_question' => 'Nie masz czasu na cały test? Wylosuj jedno pytanie z bazy egzaminacyjnej INF.03 i sprawdź swoją wiedzę w mniej niż minutę.',
        'title_test' => 'INF.03 - Egzamin próbny - Test Online 40 Pytań | Examly',
        'desc_test' => 'Sprawdź swoją wiedzę przed egzaminem zawodowym INF.03. Rozwiąż pełny test próbny online.',
        'title_personalized' => 'INF.03 - Stwórz swój własny test - Spersonalizowana Nauka | Examly',
        'desc_personalized' => 'Dopasuj naukę do swoich potrzeb. Stwórz spersonalizowany test z kwalifikacji INF.03.'
      ],
      'INF.02' => [
        'formatted_name' => 'EE.08 / INF.02',
        'title_one_question' => 'INF.02 - Jedno pytanie - Szybka powtórka | Examly',
        'desc_one_question' => 'Szybka powtórka z INF.02. Jedno pytanie losowe.',
        'title_test' => 'INF.02 - Egzamin próbny - Test Online | Examly',
        'desc_test' => 'Pełny egzamin próbny INF.02.',
        'title_personalized' => 'INF.02 - Test Spersonalizowany | Examly',
        'desc_personalized' => 'Skonfiguruj swój test z INF.02.'
      ]
    ];

    $meta = $metadata[$code] ?? [
      'formatted_name' => $code,
      'title_one_question' => "$code - Jedno pytanie | Examly",
      'desc_one_question' => "Losowe pytanie z kwalifikacji $code.",
      'title_test' => "$code - Egzamin Próbny | Examly",
      'desc_test' => "Pełny test próbny z $code.",
      'title_personalized' => "$code - Test Spersonalizowany | Examly",
      'desc_personalized' => "Stwórz własny test z $code."
    ];

    // Scal dane z bazy z metadanymi
    return array_merge($dbExam, $meta);
  }

  private function getCategoriesForExam(int $examId): array
  {
    $sql = "SELECT name, id FROM topics WHERE exam_type_id = ?";
    return $this->db->fetchAll($sql, [$examId]);
  }
}
