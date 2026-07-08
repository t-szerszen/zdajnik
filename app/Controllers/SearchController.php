<?php

namespace App\Controllers;

use App\Models\Question;
use App\Services\DynamicDataService;

/**
 * Kontroler Wyszukiwarki Pytań.
 *
 * Klasa odpowiada za logikę wyszukiwania i filtrowania bazy pytań egzaminacyjnych.
 * Obsługuje wyszukiwanie tekstowe, filtrowanie po typie egzaminu oraz kategorii tematycznej.
 * Wspiera zarówno tradycyjne renderowanie widoków, jak i dynamiczne ładowanie wyników przez AJAX.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class SearchController extends BaseController
{
  /** @var Question Model do operacji na pytaniach egzaminacyjnych */
  private Question $questionModel;

  /** @var DynamicDataService Serwis do pobierania struktury egzaminów i kategorii */
  private DynamicDataService $dynamicData;

  /**
   * Inicjalizuje kontroler wyszukiwarki.
   *
   * Ustawia dostęp do modeli i serwisów danych. Akcja nie wymaga autoryzacji,
   * co pozwala na przeglądanie bazy pytań przez gości (szczegóły pytań
   * są chronione w ReviewController).
   */
  public function __construct()
  {
    parent::__construct();
    $this->questionModel = new Question();
    $this->dynamicData = new DynamicDataService();
  }

  /**
   * Główny punkt wejściowy wyszukiwarki - wyświetla stronę i przetwarza parametry filtrów.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Inicjalizacja parametrów filtra z danych wejściowych GET.
   * 2. Wyliczenie parametrów stronicowania (limit, offset, total pages).
   * 3. Pobranie pasujących pytań z modelu danych (tylko gdy podano filtry).
   * 4. Wykrycie typu żądania (AJAX vs Standard) i odpowiednie wyrenderowanie wyników.
   *
   * @return void
   */
  public function showSearch(): void
  {
    // KROK 1: Inicjalizacja parametrów filtra
    $filters = [
      'exam_id' => $_GET['exam_id'] ?? null,
      'topic_id' => $_GET['topic_id'] ?? null,
      'query' => $_GET['query'] ?? null
    ];

    // KROK 2: Parametry stronicowania
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = 25;

    $results = [];
    $totalCount = 0;
    $totalPages = 1;

    // KROK 3: Wykonanie wyszukiwania (walidacja obecności co najmniej jednego filtra)
    if (!empty($filters['exam_id']) || !empty($filters['topic_id']) || !empty($filters['query'])) {
      $totalCount = $this->questionModel->countSearchQuestions($filters);
      $totalPages = max(1, (int) ceil($totalCount / $limit));

      // Zabezpieczenie przed przejściem poza zakres
      if ($page > $totalPages) {
        $page = $totalPages;
      }

      $results = $this->questionModel->searchQuestions($filters, $page, $limit);
    }

    // KROK 4: Renderowanie wyniku (Wykrycie AJAX)
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if ($page === 1 && (!empty($filters['exam_id']) || !empty($filters['topic_id']) || !empty($filters['query']))) {
      try {
        $this->getAnalyticsService()->trackSearchPerformed(
          $this->currentUser?->getId(),
          !empty($filters['exam_id']) ? (int) $filters['exam_id'] : null,
          !empty($filters['topic_id']) ? (int) $filters['topic_id'] : null,
          is_string($filters['query']) ? $filters['query'] : null,
          $totalCount,
          $isAjax ? 'search_ajax' : 'search_page'
        );
      } catch (\Throwable $e) {
        // Wyszukiwanie nie może zależeć od dostępności analityki.
      }
    }

    if ($isAjax) {
      // Przygotowanie tylko fragmentu HTML dla żądania dynamicznego
      ob_start();
      $this->renderPartial('search_results_partial', [
        'results' => $results,
        'filters' => $filters,
        'page' => $page,
        'totalPages' => $totalPages,
        'totalCount' => $totalCount
      ]);
      $html = ob_get_clean();

      header('Content-Type: application/json');
      echo json_encode([
        'status' => 'success',
        'html' => $html,
        'count' => count($results),
        'totalCount' => $totalCount,
        'totalPages' => $totalPages,
        'page' => $page
      ]);
      exit;
    }

    // Pełne renderowanie strony przy klasycznym wejściu/odświeżeniu
    $this->renderView('search', [
      'exams' => $this->dynamicData->getExams(),
      'categories' => $filters['exam_id'] ? $this->dynamicData->getCategoriesForExam((int) $filters['exam_id']) : [],
      'filters' => $filters,
      'results' => $results,
      'page' => $page,
      'totalPages' => $totalPages,
      'totalCount' => $totalCount
    ]);
  }

  /**
   * Zwraca listę tematów dla wybranego egzaminu używaną przez dynamiczne filtry wyszukiwarki.
   *
   * @return void
   */
  public function getTopics(): void
  {
    $examId = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;

    header('Content-Type: application/json');

    if ($examId <= 0) {
      echo json_encode([
        'status' => 'success',
        'topics' => []
      ]);
      exit;
    }

    $topics = array_map(static function (array $topic): array {
      return [
        'id' => (int) $topic['id'],
        'name' => (string) $topic['name']
      ];
    }, $this->dynamicData->getCategoriesForExam($examId));

    echo json_encode([
      'status' => 'success',
      'topics' => $topics
    ]);
    exit;
  }

  /**
   * Renderuje tylko fragment HTML bez głównego layoutu aplikacji.
   *
   * Używane głównie przez mechanizm AJAX do dynamicznej aktualizacji listy
   * wyników bez przeładowywania całej nawigacji i stopki.
   *
   * @param string $partialName Nazwa pliku widoku partial (bez rozszerzenia .php).
   * @param array $data Dane wejściowe przekazywane do widoku.
   * @return void
   */
  private function renderPartial(string $partialName, array $data = []): void
  {
    extract($data);
    include __DIR__ . "/../../views/partials/{$partialName}.php";
  }
}
