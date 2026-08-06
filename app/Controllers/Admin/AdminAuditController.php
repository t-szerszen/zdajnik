<?php

namespace App\Controllers\Admin;

/**
 * Moduł logów audytowych panelu admina (tylko odczyt z filtrami).
 *
 * @package App\Controllers\Admin
 */
class AdminAuditController extends AdminBaseController
{
  /**
   * Lista logów audytowych administracji.
   */
  public function auditLogs(): void
  {
    $filters = [
      'admin_user_id' => trim((string) ($_GET['admin_user_id'] ?? '')),
      'action' => trim((string) ($_GET['action'] ?? '')),
      'target_type' => trim((string) ($_GET['target_type'] ?? '')),
      'target_id' => trim((string) ($_GET['target_id'] ?? '')),
      'date_from' => trim((string) ($_GET['date_from'] ?? '')),
      'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    ];

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $result = $this->auditService->searchLogs($filters, $page, $perPage);

    $this->renderView('admin/audit_logs', [
      'pageTitle' => 'Audyt administracji | Admin Panel | Zdajnik',
      'pageDescription' => 'Lista ostatnich akcji administracyjnych w panelu CMS Zdajnik.',
      'filters' => $filters,
      'logs' => $result['items'],
      'pagination' => $result,
      'actions' => $this->getAuditActions(),
      'admins' => $result['admins'],
      'targetTypes' => [
        'user' => 'Użytkownik',
        'exam' => 'Egzamin',
        'question' => 'Pytanie',
      ],
    ]);
  }
}
