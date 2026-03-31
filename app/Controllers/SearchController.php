<?php

namespace App\Controllers;

use App\Models\Question;
use App\Services\DynamicDataService;

/**
 * Kontroler Wyszukiwarki Pytań.
 */
class SearchController extends BaseController
{
  private Question $questionModel;
  private DynamicDataService $dynamicData;

  public function __construct()
  {
    parent::__construct();
    // Rezygnujemy z wymogu logowania dla samej wyszukiwarki (ReviewController i tak go wymusi przy wejściu w detal)
    $this->questionModel = new Question();
    $this->dynamicData = new DynamicDataService();
  }

  /**
   * Wyświetla stronę wyszukiwarki i przetwarza wyniki.
   */
  public function showSearch(): void
  {
    $filters = [
      'exam_id' => $_GET['exam_id'] ?? null,
      'topic_id' => $_GET['topic_id'] ?? null,
      'query' => $_GET['query'] ?? null
    ];

    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = 25;

    $results = [];
    $totalCount = 0;
    $totalPages = 1;

    if (!empty($filters['exam_id']) || !empty($filters['topic_id']) || !empty($filters['query'])) {
      $totalCount = $this->questionModel->countSearchQuestions($filters);
      $totalPages = max(1, (int) ceil($totalCount / $limit));

      if ($page > $totalPages) {
        $page = $totalPages;
      }

      $results = $this->questionModel->searchQuestions($filters, $page, $limit);
    }

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if ($isAjax) {
      // Renderujemy tylko partial wyników
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
   * Renderuje tylko fragment HTML bez głownego layoutu.
   */
  private function renderPartial(string $partialName, array $data = []): void
  {
    extract($data);
    include __DIR__ . "/../../views/partials/{$partialName}.php";
  }
}