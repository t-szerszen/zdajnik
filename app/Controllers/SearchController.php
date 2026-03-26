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

    $results = [];
    if (!empty($filters['exam_id']) || !empty($filters['topic_id']) || !empty($filters['query'])) {
        $results = $this->questionModel->searchQuestions($filters);
    }

    $this->renderView('search', [
      'exams' => $this->dynamicData->getExams(),
      'categories' => $filters['exam_id'] ? $this->dynamicData->getCategoriesForExam((int)$filters['exam_id']) : [],
      'filters' => $filters,
      'results' => $results
    ]);
  }
}
