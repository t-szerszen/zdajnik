<?php

namespace App\Controllers\Admin;

use App\Services\AdminDashboardService;
use App\Services\AdminUserService;

/**
 * Dashboard panelu admina: KPI, pozycje wymagające uwagi, ostatnia aktywność.
 *
 * @package App\Controllers\Admin
 */
class AdminDashboardController extends AdminBaseController
{
  private AdminDashboardService $adminService;
  private AdminUserService $userService;

  public function __construct()
  {
    parent::__construct();
    $this->adminService = new AdminDashboardService();
    $this->userService = new AdminUserService();
  }

  protected function authorize(): void
  {
    $this->requireContentManager();
  }

  /**
   * Renderuje główny dashboard CMS.
   *
   * Redaktor widzi wariant ograniczony do treści (pytania/arkusze),
   * bez KPI kont, audytu i aktywności użytkowników.
   */
  public function index(): void
  {
    $isFullAdmin = $this->currentUser->isAdmin();

    $this->renderView('admin/dashboard', [
      'pageTitle' => 'Admin Panel | Zdajnik',
      'pageDescription' => 'Panel administracyjny Zdajnik do zarządzania treścią i strukturą serwisu.',
      'isFullAdmin' => $isFullAdmin,
      'overview' => $this->adminService->getOverview(),
      'attention' => $this->adminService->getAttentionItems(6),
      'recentAuditActions' => $isFullAdmin ? $this->adminService->getRecentAuditActions(8) : [],
      'recentUserActivity' => $isFullAdmin ? $this->userService->getRecentUserActivity(8) : [],
      'hasActivityData' => $this->adminService->hasActivityData(),
      'auditActions' => $this->getAuditActions(),
    ]);
  }

  /**
   * Endpoint JSON z dziennymi seriami trendów dla sparklines na dashboardzie.
   *
   * Serie obejmują dane o kontach i aktywności — tylko dla pełnego admina.
   */
  public function trends(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      $this->jsonResponse(['ok' => false, 'message' => 'Metoda niedozwolona.'], 405);
    }

    if (!$this->currentUser->isAdmin()) {
      $this->jsonResponse(['ok' => false, 'message' => 'Brak uprawnień.'], 403);
    }

    $this->jsonResponse([
      'ok' => true,
      'series' => $this->adminService->getTrendSeries(30),
    ]);
  }
}
