<?php

namespace App\Controllers\Admin;

use App\Models\QuestionReport;

/**
 * Moduł zgłoszeń błędów w pytaniach (panel CMS).
 *
 * Lista zgłoszeń wysłanych z kart pytań oraz zmiana ich statusu
 * (nowe → poprawione / odrzucone). Dostęp: admin i redaktor.
 *
 * @package App\Controllers\Admin
 */
class AdminReportController extends AdminBaseController
{
  private QuestionReport $reportModel;

  public function __construct()
  {
    parent::__construct();
    $this->reportModel = new QuestionReport();
  }

  protected function authorize(): void
  {
    $this->requireContentManager();
  }

  /**
   * Lista zgłoszeń z filtrami (domyślnie tylko nowe).
   */
  public function reports(): void
  {
    $filters = [
      'status' => trim((string) ($_GET['status'] ?? 'new')),
      'category' => trim((string) ($_GET['category'] ?? '')),
      'question_id' => trim((string) ($_GET['question_id'] ?? '')),
    ];
    $page = max(1, (int) ($_GET['page'] ?? 1));

    $result = $this->reportModel->search($filters, $page, 20);

    $this->renderView('admin/reports', [
      'pageTitle' => 'Zgłoszenia błędów | Admin Panel | Zdajnik',
      'pageDescription' => 'Zgłoszenia błędów w pytaniach wysłane przez użytkowników.',
      'filters' => $filters,
      'reports' => $result['items'],
      'pagination' => $result,
      'categories' => QuestionReport::CATEGORIES,
      'statuses' => QuestionReport::STATUSES,
      'contexts' => QuestionReport::CONTEXTS,
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Zmienia status zgłoszenia i wraca na listę z zachowanymi filtrami.
   */
  public function updateStatus(int $reportId): void
  {
    $returnUrl = $this->safeReturnUrl((string) ($_POST['return_to'] ?? ''));

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . $returnUrl);
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->setFlash('error', 'Nieprawidłowy token bezpieczeństwa.');
      header('Location: ' . $returnUrl);
      exit();
    }

    $status = (string) ($_POST['status'] ?? '');
    $report = $this->reportModel->findById($reportId);

    if (!$report || !isset(QuestionReport::STATUSES[$status])) {
      $this->setFlash('error', 'Nie znaleziono zgłoszenia lub status jest nieprawidłowy.');
      header('Location: ' . $returnUrl);
      exit();
    }

    if ($this->reportModel->updateStatus($reportId, $status, $this->currentUser->getId())) {
      $this->auditService->logAction(
        $this->currentUser->getId(),
        'update_report_status',
        'question_report',
        $reportId,
        ['status' => $report['status'], 'question_id' => (int) $report['question_id']],
        ['status' => $status, 'question_id' => (int) $report['question_id']]
      );
      $this->setFlash('success', 'Zgłoszenie #' . $reportId . ': ' . mb_strtolower(QuestionReport::STATUSES[$status]) . '.');
    } else {
      $this->setFlash('error', 'Nie udało się zmienić statusu zgłoszenia.');
    }

    header('Location: ' . $returnUrl);
    exit();
  }

  /**
   * Przyjmuje wyłącznie ścieżkę listy zgłoszeń (ochrona przed open redirect).
   */
  private function safeReturnUrl(string $returnTo): string
  {
    $base = url('admin/zgloszenia');
    $query = parse_url($returnTo, PHP_URL_QUERY);

    return is_string($query) && $query !== '' ? $base . '?' . $query : $base;
  }
}
