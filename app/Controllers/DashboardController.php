<?php

namespace App\Controllers;

use App\Models\SheetNote;
use App\Services\StatisticsService;

/**
 * Kontroler Panelu Głównego (Dashboard/Statystyki).
 *
 * Klasa odpowiada za zbieranie i przygotowanie kompleksowych danych analitycznych
 * dla zalogowanego użytkownika. Dashboard stanowi centrum dowodzenia postępami
 * nauki, śledząc błędy, przewidywane wyniki egzaminu oraz trendy wydajności.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class DashboardController extends BaseController
{
  /** @var StatisticsService Serwis analityczny dostarczający surowe i przetworzone dane statystyczne */
  private StatisticsService $statsService;

  /**
   * Inicjalizuje kontroler panelu głównego.
   * 
   * Ustawia dostęp do serwisu statystyk. Wszelkie operacje w tej klasie
   * wymagają uprzedniego uwierzytelnienia użytkownika.
   */
  public function __construct()
  {
    parent::__construct();
    $this->statsService = new StatisticsService();
  }

  /**
   * Przygotowuje i wyświetla stronę statystyk użytkownika (Dashboard).
   *
   * Metoda wykonuje następujące kroki:
   * 1. Wymaganie autoryzacji (Require Auth).
   * 2. Wyznaczenie profilu egzaminu (wybrana kwalifikacja INF.0X).
   * 3. Pobranie kluczowych metryk (progres ogólny, opanowanie materiału, trendy).
   * 4. Agregacja list pomocniczych (ostatnie błędy, błędy krytyczne, niedawne egzaminy).
   * 5. Renderowanie widoku statystyk z kompletnym zestawem danych.
   *
   * @return void
   */
  public function showDashboard(): void
  {
    // KROK 1: Weryfikacja tożsamości
    $this->requireAuth();
    $userId = $this->currentUser->getId();

    // KROK 2: Wybór aktualnego kontekstu (kwalifikacji) z parametrów URL
    $examTypes = $this->statsService->getUserExams($this->currentUser->getId());
    $requestedCategory = $_GET['category'] ?? null;
    $selectedExamTypeId = null;

    if ($requestedCategory !== null && ctype_digit((string) $requestedCategory) && (int) $requestedCategory > 0) {
      $requestedCategoryId = (int) $requestedCategory;
      $allowedExamIds = array_map(static fn($exam) => (int) $exam['id'], $examTypes);

      if (in_array($requestedCategoryId, $allowedExamIds, true)) {
        $selectedExamTypeId = $requestedCategoryId;
      }
    }

    // Podsumowanie notatek do arkuszy (niezależne od wybranej kwalifikacji)
    $sheetNotesSummary = (new SheetNote())->getSummaryForUser($userId);

    if ($selectedExamTypeId === null) {
      $this->renderView('statistics', [
        'categories' => $examTypes,
        'selectedCategoryId' => null,
        'hasSelectedCategory' => false,
        'sheetNotesSummary' => $sheetNotesSummary
      ]);
      return;
    }

    // KROK 3 & 4: Gromadzenie kompletnych danych analitycznych
    // Pobieramy dane z محدودzeniem (limit) do 5 najświeższych rekordów dla widoku panelu.
    $data = [
      'categories' => $examTypes,
      'selectedCategoryId' => $selectedExamTypeId,
      'hasSelectedCategory' => true,
      'overallProgress' => $this->statsService->getOverallProgress($userId, $selectedExamTypeId),
      'masteryStats' => $this->statsService->getMasteryStats($userId, $selectedExamTypeId),
      'topicStats' => $this->statsService->getTopicStats($userId, $selectedExamTypeId),
      'records' => $this->statsService->getRecords($userId, $selectedExamTypeId),
      'predictedScore' => $this->statsService->getPredictedExamScore($userId, $selectedExamTypeId),
      'errorHistory' => $this->statsService->getErrorHistory($userId, $selectedExamTypeId, 5),
      'criticalErrors' => $this->statsService->getCriticalErrors($userId, $selectedExamTypeId, 5),
      'recentExams' => $this->statsService->getRecentExams($userId, $selectedExamTypeId, 5),
      'efficiencyTrend' => $this->statsService->getEfficiencyTrendWithMovingAvg($userId, $selectedExamTypeId, 10),
      'savedQuestions' => $this->statsService->getSavedQuestions($userId, $selectedExamTypeId, 5),
      'sheetNotesSummary' => $sheetNotesSummary
    ];

    // KROK 5: Przekazanie danych do modułu statystyk
    $this->renderView('statistics', $data);
  }
}
