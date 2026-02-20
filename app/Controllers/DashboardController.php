<?php

namespace App\Controllers;

use App\Services\StatisticsService;

class DashboardController extends BaseController
{
  private StatisticsService $statsService;

  public function __construct()
  {
    parent::__construct();
    // Sztywno ustawiony limit 5 dla dashboardu
    $this->statsService = new StatisticsService();
  }

  /**
   * Wyświetla stronę statystyk użytkownika (Dashboard).
   */
  public function showDashboard(): void
  {
    $this->requireAuth();

    $examTypes = $this->statsService->getUserExams($this->currentUser->getId());
    $selectedExamTypeId = (int) ($_GET['category'] ?? $examTypes[0]['id'] ?? 0);
    $userId = $this->currentUser->getId();

    // Dla dashboardu zawsze limit 5 (domyślny w metodach, ale warto być explicite lub polegać na defaultLimit)
    // Metody getErrorHistory, getCriticalErrors, etc. mają domyślny limit 5.

    $data = [
      'categories' => $examTypes,
      'selectedCategoryId' => $selectedExamTypeId,
      'overallProgress' => $this->statsService->getOverallProgress($userId, $selectedExamTypeId),
      'masteryStats' => $this->statsService->getMasteryStats($userId, $selectedExamTypeId),
      'topicStats' => $this->statsService->getTopicStats($userId, $selectedExamTypeId),
      'records' => $this->statsService->getRecords($userId, $selectedExamTypeId),
      'predictedScore' => $this->statsService->getPredictedExamScore($userId, $selectedExamTypeId),
      'errorHistory' => $this->statsService->getErrorHistory($userId, $selectedExamTypeId, 5),
      'criticalErrors' => $this->statsService->getCriticalErrors($userId, $selectedExamTypeId, 5),
      'recentExams' => $this->statsService->getRecentExams($userId, $selectedExamTypeId, 5),
      'efficiencyTrend' => $this->statsService->getEfficiencyTrendWithMovingAvg($userId, $selectedExamTypeId, 10)
    ];

    $this->renderView('statistics', $data);
  }
}