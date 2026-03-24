<?php

namespace App\Controllers;

use App\Services\DynamicDataService;

/**
 * Kontroler Strony Głównej.
 *
 * Odpowiada za logikę i wyświetlanie głównej strony aplikacji.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */
class HomeController extends BaseController
{
  private DynamicDataService $dynamicDataService;

  public function __construct()
  {
    parent::__construct();
    $this->dynamicDataService = new DynamicDataService();
  }

  /**
   * Wyświetla widok strony głównej.
   * 
   * @return void
   */
  public function show(): void
  {
    $examsCategories = $this->loadExamsAndTopics();
    $this->renderView('home', ['examsCategories' => $examsCategories]);
  }

  private function loadExamsAndTopics(): array
  {
    $exams = $this->dynamicDataService->getExams();
    $examsCategories = [];
    foreach ($exams as $exam) {
      $categories = $this->dynamicDataService->getCategoriesForExam($exam['id']);
      $examsCategories[$exam['code']] = $categories;
    }

    return $examsCategories;
  }
}