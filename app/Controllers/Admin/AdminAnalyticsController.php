<?php

namespace App\Controllers\Admin;

use App\Services\AnalyticsService;

/**
 * Moduł analityki produktowej panelu admina (dashboard, dane JSON, eksport CSV).
 *
 * @package App\Controllers\Admin
 */
class AdminAnalyticsController extends AdminBaseController
{
  private AnalyticsService $analyticsService;

  public function __construct()
  {
    parent::__construct();
    $this->analyticsService = new AnalyticsService();
  }

  /**
   * Wyświetla dashboard analityki produktowej.
   */
  public function analytics(): void
  {
    $filters = $this->extractAnalyticsFilters();
    $dashboardData = $this->analyticsService->getDashboardData($filters);
    $filterOptions = $this->analyticsService->getFilterOptions();

    $this->renderView('admin/analytics', [
      'pageTitle' => 'Analityka | Admin Panel | Zdajnik',
      'pageDescription' => 'Panel analityczny produktu Zdajnik dla administratorów.',
      'analytics' => $dashboardData,
      'filters' => $filters,
      'filterOptions' => $filterOptions,
      'availableRanges' => $filterOptions['ranges'],
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Endpoint JSON dla wykresów i KPI analityki.
   */
  public function analyticsData(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      $this->jsonResponse(['success' => false, 'message' => 'Metoda niedozwolona.'], 405);
    }

    $filters = $this->extractAnalyticsFilters();
    $dashboardData = $this->analyticsService->getDashboardData($filters);

    $this->jsonResponse([
      'success' => true,
      'data' => $dashboardData,
    ]);
  }

  /**
   * Eksportuje wybrany zestaw danych analitycznych do CSV.
   */
  public function analyticsExport(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      $this->jsonResponse(['success' => false, 'message' => 'Metoda niedozwolona.'], 405);
    }

    $dataset = strtolower(trim((string) ($_GET['dataset'] ?? 'users')));
    $allowedDatasets = [
      'users',
      'exams',
      'questions',
      'events',
      'retention',
      // Legacy aliases used by the current analytics view.
      'overview',
      'learning',
      'hardest_questions',
      'weakest_topics',
      'cohorts',
    ];
    if (!in_array($dataset, $allowedDatasets, true)) {
      $this->jsonResponse(['success' => false, 'message' => 'Nieprawidłowy zestaw danych.'], 400);
    }

    $export = $this->analyticsService->getExportData($dataset, $this->extractAnalyticsFilters());

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename((string) $export['filename']) . '"');
    header('X-Content-Type-Options: nosniff');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
      http_response_code(500);
      exit();
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $export['headers'], ';', '"', '\\');
    foreach ($export['rows'] as $row) {
      fputcsv($output, array_map(
        fn(mixed $value): string => $this->sanitizeCsvCell($value),
        $row
      ), ';', '"', '\\');
    }
    fclose($output);
    exit();
  }

  /**
   * Pobiera filtry zakresu dla analityki.
   *
   * @return array<string, int|string>
   */
  private function extractAnalyticsFilters(): array
  {
    $legacyStatus = trim((string) ($_GET['user_status'] ?? ''));
    $role = trim((string) ($_GET['role'] ?? ''));
    $isVerified = trim((string) ($_GET['is_verified'] ?? ''));

    if ($role === '' && in_array($legacyStatus, ['admin', 'user'], true)) {
      $role = $legacyStatus;
    }
    if ($isVerified === '' && in_array($legacyStatus, ['verified', 'unverified'], true)) {
      $isVerified = $legacyStatus === 'verified' ? '1' : '0';
    }

    return [
      'range' => trim((string) ($_GET['range'] ?? '30d')),
      'from' => trim((string) ($_GET['from'] ?? '')),
      'to' => trim((string) ($_GET['to'] ?? '')),
      'exam_type_id' => trim((string) ($_GET['exam_type_id'] ?? '')),
      'test_type' => trim((string) ($_GET['test_type'] ?? '')),
      'auth_provider' => trim((string) ($_GET['auth_provider'] ?? '')),
      'role' => $role,
      'is_verified' => $isVerified,
      'event_type' => trim((string) ($_GET['event_type'] ?? '')),
      'search' => trim((string) ($_GET['search'] ?? '')),
      'page' => max(1, (int) ($_GET['page'] ?? 1)),
      'per_page' => min(100, max(10, (int) ($_GET['per_page'] ?? 25))),
      'user_status' => $legacyStatus,
    ];
  }

  /**
   * Neutralizuje formuły arkusza kalkulacyjnego w danych eksportowanych do CSV.
   */
  private function sanitizeCsvCell(mixed $value): string
  {
    $cell = is_scalar($value) || $value === null ? (string) $value : '';
    return preg_match('/^[=+\-@]/u', $cell) === 1 ? "'" . $cell : $cell;
  }
}
