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
   * Strażnik dostępności egzaminów i normalizator adresu URL.
   *
   * Tymczasowe zabezpieczenie blokujące dostęp do kwalifikacji, których baza
   * pytań jest wciąż w przygotowaniu (obecnie INF.02). Dodatkowo rozwiązuje
   * dowolny wariant zapisu segmentu URL (INF.03, inf.03, Inf03, ...) do
   * kanonicznego kodu z bazy i - jeśli wejściowy segment nie był już w
   * znormalizowanej formie (bez separatora, małe litery) - przekierowuje
   * 301 na kanoniczny adres zamiast renderować treść pod wieloma wariantami
   * tego samego URL-a.
   *
   * @param string $code Surowy segment adresu URL (kod kwalifikacji w dowolnym zapisie).
   * @param string $routePrefix Prefiks trasy tej strony (np. "test", "jedno-pytanie").
   * @return string Kanoniczny kod kwalifikacji z bazy (np. "INF.03").
   */
  private function checkAvailability(string $code, string $routePrefix): string
  {
    $canonicalCode = DynamicDataService::getExamCodeByUrlSegment($code);

    if ($canonicalCode === null) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser?->getId(),
        'quiz_page',
        'exam_unavailable',
        ['source' => 'quiz_page']
      );
      header("Location: /?message=" . urlencode("Ten egzamin będzie dostępny wkrótce!"));
      exit;
    }

    $canonicalSegment = exam_code_to_url($canonicalCode);
    if ($code !== $canonicalSegment) {
      $queryParams = $_GET;
      unset($queryParams['url']);
      
      $redirectUrl = url($routePrefix . '/' . $canonicalSegment);
      if ($queryParams) {
        $redirectUrl .= '?' . http_build_query($queryParams);
      }
      
      header('Location: ' . $redirectUrl, true, 301);
      exit;
    }

    return $canonicalCode;
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
    // KROK 1: Walidacja dostępności i normalizacja URL-a
    $examCode = $this->checkAvailability($code, 'jedno-pytanie');

    // KROK 2: Pobranie metadanych egzaminu
    $exam = $this->dynamicData->getExamDetails($examCode);

    if ($exam) {
      // KROK 3: Pobranie kategorii tematycznych
      $categories = $this->dynamicData->getCategoriesForExam($exam['id']);

      // KROK 4: Renderowanie widoku
      $this->renderView('one_question', [
        'exam' => $exam,
        'examCode' => $examCode,
        'categories' => $categories
      ]);
    } else {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser?->getId(),
        'one_question_page',
        'exam_not_found'
      );
      http_response_code(404);
      echo "Egzamin o kodzie $examCode nie istnieje.";
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
    // KROK 1: Walidacja, normalizacja URL-a i pobranie danych podstawowych
    $examCode = $this->checkAvailability($code, 'personalizowany-test');
    $exam = $this->dynamicData->getExamDetails($examCode);

    if (!$exam) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser?->getId(),
        'personalized_test_page',
        'exam_not_found'
      );
      http_response_code(404);
      echo "Egzamin o kodzie $examCode nie istnieje.";
      return;
    }

    $data = [
      'exam' => $exam,
      'examCode' => $examCode,
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
    $examCode = $this->checkAvailability($code, 'test');
    $exam = $this->dynamicData->getExamDetails($examCode);

    if ($exam) {
      $this->renderView('test', [
        'exam' => $exam,
        'examCode' => $examCode
      ]);
    } else {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser?->getId(),
        'full_test_page',
        'exam_not_found'
      );
      http_response_code(404);
      echo "Egzamin o kodzie $examCode nie istnieje.";
    }
  }
}
