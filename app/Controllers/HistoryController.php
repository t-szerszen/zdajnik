<?php

namespace App\Controllers;

use App\Services\StatisticsService;

/**
 * Kontroler Historii Użytkownika.
 *
 * Klasa odpowiada za wyświetlanie szczegółowych zestawień historycznych dla zalogowanego użytkownika.
 * Obsługuje listowanie błędów krytycznych, ogólnej historii błędów, zapisanych pytań
 * oraz archiwalnych podejść do egzaminów.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class HistoryController extends BaseController
{
  /** @var StatisticsService Serwis do pobierania zestawień historycznych i statystyk */
  private StatisticsService $statsService;

  /**
   * Inicjalizuje kontroler historii.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
    // Serwis statystyk inicjalizowany do pobierania pełnych zestawień danych
    $this->statsService = new StatisticsService();
  }

  /**
   * Wyświetla pełną historię dla wybranego typu (egzaminy, błędy, zapisane).
   *
   * Metoda wykonuje następujące kroki:
   * 1. Autoryzacja dostępu do prywatnych danych historii.
   * 2. Wyznaczenie kontekstu egzaminu (identyfikacja wybranej kategorii).
   * 3. Określenie typu danych historycznych (exams, critical, errors, saved).
   * 4. Agregacja odpowiednich rekordów z bazy danych przez serwis statystyk.
   * 5. Renderowanie kompletnego widoku historii.
   *
   * @return void
   */
  public function showHistory(): void
  {
    // KROK 1: Weryfikacja tożsamości użytkownika
    $this->requireAuth();
    $userId = $this->currentUser->getId();

    // KROK 2: Ustalenie kontekstu kategorii (egzaminu)
    $examTypes = $this->statsService->getExamTypes();
    $selectedExamTypeId = (int) ($_GET['category'] ?? $examTypes[0]['id'] ?? 0);

    // Mapowanie ID kategorii na czytelny kod (INF.0X)
    $selectedCategoryCode = '';
    foreach ($examTypes as $type) {
      if ((int) $type['id'] === $selectedExamTypeId) {
        $selectedCategoryCode = $type['code'];
        break;
      }
    }

    // KROK 3: Identyfikacja typu żądanej historii
    $type = $_GET['type'] ?? 'exams';

    $viewData = [
      'type' => $type,
      'selectedCategoryId' => $selectedExamTypeId,
      'selectedCategoryCode' => $selectedCategoryCode,
      'categories' => $examTypes,
    ];

    // KROK 4: Pobieranie danych w zależności od wybranego profilu historii
    switch ($type) {
      case 'critical':
        $viewData['title'] = 'Pytania do powtórki';
        $viewData['backTo'] = 'criticalMistakes';
        $result = $this->statsService->getCriticalErrors($userId, $selectedExamTypeId, null);
        $viewData['items'] = $result['items'];
        break;

      case 'errors':
        $viewData['title'] = 'Ostatnie błędy';
        $viewData['backTo'] = 'lastMistakes';
        $result = $this->statsService->getErrorHistory($userId, $selectedExamTypeId, null);
        $viewData['items'] = $result['items'];
        break;

      case 'saved':
        $viewData['title'] = 'Zapisane pytania';
        $viewData['backTo'] = 'savedQuestions';
        $result = $this->statsService->getSavedQuestions($userId, $selectedExamTypeId, null);
        $viewData['items'] = $result['items'];
        break;

      case 'exams':
      default:
        $viewData['title'] = 'Zakończone próby';
        $viewData['backTo'] = 'examsHistory';
        $result = $this->statsService->getRecentExams($userId, $selectedExamTypeId, null);
        $viewData['items'] = $result['items'];
        break;
    }

    // KROK 5: Renderowanie końcowego widoku
    $this->renderView('history', $viewData);
  }
}
