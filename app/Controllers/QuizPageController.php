<?php

namespace App\Controllers;

use App\Services\DynamicDataService;

/**
 * Kontroler Stron Kontekstowych Quizów i Egzaminów.
 *
 * Klasa odpowiada za przygotowanie i wyrenderowanie widoków HTML dla różnych 
 * trybów nauki (jedno pytanie, test spersonalizowany, pełny egzamin).
 * Stanowi warstwę pośrednią, która waliduje dostępność konkretnych 
 * kwalifikacji zawodowych i dostarcza dane o ich strukturze do widoków.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class QuizPageController extends BaseController
{
  /** @var DynamicDataService Serwis do pobierania struktury egzaminów i ich tematów */
  private DynamicDataService $dynamicData;

  /**
   * Inicjalizuje kontroler stron quizowych.
   */
  public function __construct()
  {
    parent::__construct();
    $this->dynamicData = new DynamicDataService();
  }

  /**
   * Strażnik dostępności egzaminów.
   *
   * Tymczasowe zabezpieczenie blokujące dostęp do kwalifikacji, których baza
   * pytań jest wciąż w przygotowaniu (obecnie INF.02).
   *
   * @param string $code Kod kwalifikacji (np. INF.02, INF.03, INF.04).
   * @return void
   */
  private function checkAvailability(string $code): void
  {
    if (!in_array(strtoupper($code), DynamicDataService::AVAILABLE_EXAM_CODES, true)) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser?->getId(),
        'quiz_page',
        'exam_unavailable',
        ['source' => 'quiz_page']
      );
      header("Location: /?message=" . urlencode("Ten egzamin będzie dostępny wkrótce!"));
      exit;
    }
  }

  /**
   * Wyświetla stronę quizu w trybie "Jedno pytanie po drugim".
   *
   * Metoda wykonuje następujące kroki:
   * 1. Walidacja dostępności danej kwalifikacji.
   * 2. Pobranie metadanych egzaminu (nazwa, opis, kolory).
   * 3. Pobranie listy kategorii tematycznych przypisanych do egzaminu.
   * 4. Renderowanie widoku one_question.
   *
   * @param string $code Kod kwalifikacji.
   * @return void
   */
  public function showOneQuestionPage(string $code): void
  {
    // KROK 1: Walidacja dostępności
    $this->checkAvailability($code);

    // KROK 2: Pobranie metadanych egzaminu
    $exam = $this->dynamicData->getExamDetails($code);

    if ($exam) {
      // KROK 3: Pobranie kategorii tematycznych
      $categories = $this->dynamicData->getCategoriesForExam($exam['id']);

      // KROK 4: Renderowanie widoku
      $this->renderView('one_question', [
        'exam' => $exam,
        'examCode' => $code,
        'categories' => $categories
      ]);
    } else {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser?->getId(),
        'one_question_page',
        'exam_not_found'
      );
      http_response_code(404);
      echo "Egzamin o kodzie $code nie istnieje.";
    }
  }

  /**
   * Wyświetla interfejs do generowania spersonalizowanego zestawu pytań.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Walidacja dostępności i pobranie danych egzaminu.
   * 2. Wykrycie wstępnie wybranych tematów z parametrów URL (np. ze statystyk).
   * 3. Przygotowanie listy kategorii do filtrów w widoku.
   * 4. Renderowanie interfejsu konfiguracji testu.
   *
   * @param string $code Kod kwalifikacji.
   * @return void
   */
  public function showPersonalizedTestPage(string $code): void
  {
    // KROK 1: Walidacja i pobranie danych podstawowych
    $this->checkAvailability($code);
    $exam = $this->dynamicData->getExamDetails($code);

    if (!$exam) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser?->getId(),
        'personalized_test_page',
        'exam_not_found'
      );
      http_response_code(404);
      echo "Egzamin o kodzie $code nie istnieje.";
      return;
    }

    $data = [
      'exam' => $exam,
      'examCode' => $code,
      'selectedSubjectId' => null
    ];

    // KROK 2: Obsługa opcjonalnych parametrów preselekcji tematów
    if (isset($_GET['subject']) && is_array($_GET['subject']) && !empty($_GET['subject'][0])) {
      $data['selectedSubjectId'] = (int) $_GET['subject'][0];
    }

    // KROK 3: Pobranie wszystkich kategorii dla danej kwalifikacji
    $data['categories'] = $this->dynamicData->getCategoriesForExam($exam['id']);

    // KROK 4: Renderowanie widoku konfiguracji
    $this->renderView('personalized_test', $data);
  }

  /**
   * Wyświetla stronę głównego testu egzaminacyjnego (40 pytań, limit czasu).
   *
   * @param string $code Kod kwalifikacji.
   * @return void
   */
  public function showTestPage(string $code): void
  {
    $this->checkAvailability($code);
    $exam = $this->dynamicData->getExamDetails($code);

    if ($exam) {
      $this->renderView('test', [
        'exam' => $exam,
        'examCode' => $code
      ]);
    } else {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser?->getId(),
        'full_test_page',
        'exam_not_found'
      );
      http_response_code(404);
      echo "Egzamin o kodzie $code nie istnieje.";
    }
  }
}
