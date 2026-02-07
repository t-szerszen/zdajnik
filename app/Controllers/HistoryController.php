<?php

namespace App\Controllers;

use App\Services\StatisticsService;

class HistoryController extends BaseController
{
  private StatisticsService $statsService;

  public function __construct()
  {
    parent::__construct();
    // Inicjalizacja serwisu bez limitu (limit = null obsługiwany jest w serwisie jako brak limitu)
    $this->statsService = new StatisticsService();
  }

  /**
   * Wyświetla pełną historię dla danego typu.
   */
  public function showHistory(): void
  {
    $this->requireAuth();

    $examTypes = $this->statsService->getExamTypes();
    // Pobranie aktualnie wybranej kategorii (domyślnie pierwsza) - dla kontekstu danych
    $selectedExamTypeId = (int)($_GET['category'] ?? $examTypes[0]['id'] ?? 0);
    
    // Znajdź kod kategorii na podstawie ID
    $selectedCategoryCode = '';
    foreach ($examTypes as $type) {
        if ((int)$type['id'] === $selectedExamTypeId) {
            $selectedCategoryCode = $type['code'];
            break;
        }
    }
    $userId = $this->currentUser->getId();
    
    $type = $_GET['type'] ?? 'exams'; // Domyślnie egzaminy
    
    $viewData = [
      'type' => $type,
      'selectedCategoryId' => $selectedExamTypeId,
      'selectedCategoryCode' => $selectedCategoryCode,
      'categories' => $examTypes, // w razie potrzeby zmiany kategorii w historii
    ];

    // Pobieranie danych w zależności od typu
    switch ($type) {
      case 'critical':
        $viewData['title'] = ' Krytycznych błędów';
        $viewData['backTo'] = 'criticalMistakes';
        // limit = null -> pobierz wszystko
        $viewData['items'] = $this->statsService->getCriticalErrors($userId, $selectedExamTypeId, null)['items']; 
        break;

      case 'errors':
        $viewData['title'] = ' Błędów';
        $viewData['backTo'] = 'lastMistakes';
        // limit = null -> pobierz wszystko
        $viewData['items'] = $this->statsService->getErrorHistory($userId, $selectedExamTypeId, null); 
        break;

      case 'exams':
      default:
        $viewData['title'] = ' Egzaminów';
        $viewData['backTo'] = 'examsHistory';
        // limit = null -> pobierz wszystko
        $viewData['items'] = $this->statsService->getRecentExams($userId, $selectedExamTypeId, null);
        break;
    }

    $this->renderView('history', $viewData);
  }
}
