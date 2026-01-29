<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\DynamicDataService;

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
  private DynamicDataService $dynamicData;

  public function __construct()
  {
    parent::__construct();
    $this->dynamicData = new DynamicDataService();
  }

  /**
   * Wyświetla stronę quizu w trybie "jedno pytanie".
   *
   * @return void
   */
  public function showOneQuestionPage(string $code): void
  {
    $exam = $this->dynamicData->getExamDetails($code);
    if ($exam) {
      $categories = $this->dynamicData->getCategoriesForExam($exam['id']);
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
    $exam = $this->dynamicData->getExamDetails($code);
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
    $data['categories'] = $this->dynamicData->getCategoriesForExam($exam['id']);

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
    $exam = $this->dynamicData->getExamDetails($code);
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
}
