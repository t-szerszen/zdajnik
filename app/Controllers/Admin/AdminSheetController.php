<?php

namespace App\Controllers\Admin;

use App\Services\AdminSheetService;

/**
 * Moduł biblioteki arkuszy panelu admina (PDF/ZIP + tematy arkuszy).
 *
 * @package App\Controllers\Admin
 */
class AdminSheetController extends AdminBaseController
{
  private AdminSheetService $sheetService;

  public function __construct()
  {
    parent::__construct();
    $this->sheetService = new AdminSheetService();
  }

  protected function authorize(): void
  {
    $this->requireContentManager();
  }

  /**
   * Lista arkuszy wraz z zarządzaniem tematami.
   */
  public function sheets(): void
  {
    ['filters' => $filters, 'page' => $page] = $this->extractListParams();
    $result = $this->sheetService->searchSheets($filters, $page, 20);

    $this->renderView('admin/sheets', [
      'pageTitle' => 'Arkusze | Admin Panel | Zdajnik',
      'pageDescription' => 'Zarządzanie biblioteką arkuszy w panelu CMS Zdajnik.',
      'sheets' => $result['items'],
      'sheetTopics' => $this->sheetService->getTopics(),
      'examTypes' => $this->sheetService->getExamTypes(),
      'filters' => $filters,
      'pagination' => $result,
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Endpoint JSON listy arkuszy (filtry/paginacja bez przeładowania).
   */
  public function search(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      $this->jsonResponse(['ok' => false, 'message' => 'Metoda niedozwolona.'], 405);
    }

    ['filters' => $filters, 'page' => $page] = $this->extractListParams();
    $result = $this->sheetService->searchSheets($filters, $page, 20);

    $this->jsonResponse([
      'ok' => true,
      'html' => $this->renderPartialToString('admin/partials/sheets_rows', ['sheets' => $result['items']]),
      'meta' => [
        'total' => $result['total'],
        'page' => $result['page'],
        'perPage' => $result['perPage'],
        'totalPages' => $result['totalPages'],
      ],
    ]);
  }

  /**
   * Akcje zbiorcze na arkuszach (aktywacja/dezaktywacja).
   */
  public function bulk(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->jsonResponse(['ok' => false, 'message' => 'Metoda niedozwolona.'], 405);
    }

    if (!$this->validateAjaxCsrf()) {
      $this->jsonResponse(['ok' => false, 'message' => 'Nieprawidłowy token bezpieczeństwa.'], 403);
    }

    $payload = json_decode((string) file_get_contents('php://input'), true);
    $payload = is_array($payload) ? $payload : [];

    $action = (string) ($payload['action'] ?? '');
    if (!in_array($action, ['activate', 'deactivate'], true)) {
      $this->jsonResponse(['ok' => false, 'message' => 'Nieznana akcja zbiorcza.'], 400);
    }

    $ids = array_values(array_unique(array_map('intval', (array) ($payload['ids'] ?? []))));
    $ids = array_slice(array_filter($ids, static fn(int $id): bool => $id > 0), 0, 500);
    if (empty($ids)) {
      $this->jsonResponse(['ok' => false, 'message' => 'Nie zaznaczono żadnych arkuszy.'], 400);
    }

    $result = $this->sheetService->bulkSheetAction($ids, $action === 'activate', $this->currentUser->getId(), $this->auditService);

    $label = $action === 'activate' ? 'aktywowano' : 'zdezaktywowano';
    $this->jsonResponse([
      'ok' => true,
      'succeeded' => $result['succeeded'],
      'failed' => $result['failed'],
      'message' => sprintf('Pomyślnie %s %d z %d arkuszy.', $label, count($result['succeeded']), count($ids)),
    ]);
  }

  /**
   * Wspólna ekstrakcja filtrów listy z query stringa.
   *
   * @return array{filters: array<string, string>, page: int}
   */
  private function extractListParams(): array
  {
    return [
      'filters' => [
        'query' => trim((string) ($_GET['q'] ?? '')),
        'topic_id' => trim((string) ($_GET['topic_id'] ?? '')),
        'exam_id' => trim((string) ($_GET['exam_id'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
      ],
      'page' => max(1, (int) ($_GET['page'] ?? 1)),
    ];
  }

  /**
   * Wyświetla formularz dodawania arkusza.
   */
  public function showCreateSheetForm(): void
  {
    $this->renderSheetForm('create', [
      'sheet_topic_id' => (int) ($_GET['topic_id'] ?? 0),
      'title' => '',
      'description' => '',
      'difficulty' => 5,
      'is_active' => 1,
    ]);
  }

  /**
   * Zapisuje nowy arkusz (wraz z uploadem PDF i opcjonalnego ZIP).
   */
  public function storeSheet(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/arkusze'));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->renderSheetForm('create', $this->extractSheetPayload($_POST), ['csrf' => 'Nieprawidłowy token bezpieczeństwa.']);
      return;
    }

    [$data, $errors] = $this->validateSheetPayload($_POST);

    $pdfUpload = $this->sheetService->processFileUpload($_FILES['pdf_file'] ?? null, 'pdf');
    if (!$pdfUpload['success']) {
      $errors['pdf_file'] = $pdfUpload['error'];
    } elseif (empty($pdfUpload['path'])) {
      $errors['pdf_file'] = 'Plik PDF arkusza jest wymagany.';
    }

    $zipUpload = $this->sheetService->processFileUpload($_FILES['zip_file'] ?? null, 'zip');
    if (!$zipUpload['success']) {
      $errors['zip_file'] = $zipUpload['error'];
    }

    $thumbnailUpload = $this->sheetService->processFileUpload($_FILES['thumbnail_file'] ?? null, 'image');
    if (!$thumbnailUpload['success']) {
      $errors['thumbnail_file'] = $thumbnailUpload['error'];
    }

    $resourcesUpload = $this->sheetService->processFileUpload($_FILES['resources_file'] ?? null, 'resources');
    if (!$resourcesUpload['success']) {
      $errors['resources_file'] = $resourcesUpload['error'];
    }

    if (!empty($errors)) {
      // Sprzątanie plików zapisanych przed wykryciem błędów walidacji
      $this->cleanupUploads($pdfUpload, $zipUpload, $thumbnailUpload, $resourcesUpload);
      $this->renderSheetForm('create', $data, $errors);
      return;
    }

    $data['pdf_path'] = $pdfUpload['path'];
    $data['zip_path'] = $zipUpload['path'] ?? null;
    $data['thumbnail_path'] = $thumbnailUpload['path'] ?? null;
    $data['resources_path'] = $resourcesUpload['path'] ?? null;

    $sheetId = $this->sheetService->createSheet($data);
    if (!$sheetId) {
      $this->cleanupUploads($pdfUpload, $zipUpload, $thumbnailUpload, $resourcesUpload);
      $this->renderSheetForm('create', $data, ['general' => 'Nie udało się utworzyć arkusza.']);
      return;
    }

    $this->auditService->logAction($this->currentUser->getId(), 'sheet_created', 'sheet', (int) $sheetId, null, [
      'title' => $data['title'],
      'sheet_topic_id' => $data['sheet_topic_id'],
      'difficulty' => $data['difficulty'],
      'has_zip' => $data['zip_path'] !== null,
    ]);

    $this->setFlash('success', 'Arkusz został dodany.');
    header('Location: ' . url('admin/arkusze'));
    exit();
  }

  /**
   * Wyświetla formularz edycji arkusza.
   */
  public function showEditSheetForm(int $sheetId): void
  {
    $sheet = $this->sheetService->getSheetById($sheetId);

    if (!$sheet) {
      $this->renderNotFound('Arkusz nie został znaleziony.');
      return;
    }

    $this->renderSheetForm('edit', $sheet, [], $sheetId);
  }

  /**
   * Aktualizuje arkusz. Nowe pliki podmieniają dotychczasowe.
   */
  public function updateSheet(int $sheetId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/arkusze'));
      exit();
    }

    $sheet = $this->sheetService->getSheetById($sheetId);
    if (!$sheet) {
      $this->setFlash('error', 'Arkusz nie istnieje.');
      header('Location: ' . url('admin/arkusze'));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->renderSheetForm('edit', array_merge($sheet, $this->extractSheetPayload($_POST)), [
        'csrf' => 'Nieprawidłowy token bezpieczeństwa.',
      ], $sheetId);
      return;
    }

    [$data, $errors] = $this->validateSheetPayload($_POST);

    $pdfUpload = $this->sheetService->processFileUpload($_FILES['pdf_file'] ?? null, 'pdf');
    if (!$pdfUpload['success']) {
      $errors['pdf_file'] = $pdfUpload['error'];
    }

    $zipUpload = $this->sheetService->processFileUpload($_FILES['zip_file'] ?? null, 'zip');
    if (!$zipUpload['success']) {
      $errors['zip_file'] = $zipUpload['error'];
    }

    $thumbnailUpload = $this->sheetService->processFileUpload($_FILES['thumbnail_file'] ?? null, 'image');
    if (!$thumbnailUpload['success']) {
      $errors['thumbnail_file'] = $thumbnailUpload['error'];
    }

    $resourcesUpload = $this->sheetService->processFileUpload($_FILES['resources_file'] ?? null, 'resources');
    if (!$resourcesUpload['success']) {
      $errors['resources_file'] = $resourcesUpload['error'];
    }

    if (!empty($errors)) {
      $this->cleanupUploads($pdfUpload, $zipUpload, $thumbnailUpload, $resourcesUpload);
      $this->renderSheetForm('edit', array_merge($sheet, $data), $errors, $sheetId);
      return;
    }

    $data['pdf_path'] = $pdfUpload['path'] ?? $sheet['pdf_path'];
    $data['zip_path'] = $zipUpload['path'] ?? $sheet['zip_path'];
    $data['thumbnail_path'] = $thumbnailUpload['path'] ?? $sheet['thumbnail_path'];
    $data['resources_path'] = $resourcesUpload['path'] ?? $sheet['resources_path'];

    if (!$this->sheetService->updateSheet($sheetId, $data)) {
      $this->cleanupUploads($pdfUpload, $zipUpload, $thumbnailUpload, $resourcesUpload);
      $this->renderSheetForm('edit', array_merge($sheet, $data), ['general' => 'Nie udało się zapisać zmian.'], $sheetId);
      return;
    }

    // Stare pliki usuwamy dopiero po udanym zapisie w bazie
    foreach (['pdf_path' => $pdfUpload, 'zip_path' => $zipUpload, 'thumbnail_path' => $thumbnailUpload, 'resources_path' => $resourcesUpload] as $column => $upload) {
      if (!empty($upload['path']) && ($sheet[$column] ?? null) !== $upload['path']) {
        $this->sheetService->deleteStoredFile($sheet[$column] ?? null);
      }
    }

    $this->auditService->logAction($this->currentUser->getId(), 'sheet_updated', 'sheet', $sheetId, [
      'title' => $sheet['title'],
      'sheet_topic_id' => (int) $sheet['sheet_topic_id'],
      'difficulty' => (int) $sheet['difficulty'],
    ], [
      'title' => $data['title'],
      'sheet_topic_id' => $data['sheet_topic_id'],
      'difficulty' => $data['difficulty'],
      'pdf_replaced' => !empty($pdfUpload['path']),
      'zip_replaced' => !empty($zipUpload['path']),
    ]);

    $this->setFlash('success', 'Arkusz został zaktualizowany.');
    header('Location: ' . url('admin/arkusze'));
    exit();
  }

  /**
   * Aktywuje arkusz.
   */
  public function activateSheet(int $sheetId): void
  {
    $this->changeSheetState($sheetId, true);
  }

  /**
   * Dezaktywuje arkusz.
   */
  public function deactivateSheet(int $sheetId): void
  {
    $this->changeSheetState($sheetId, false);
  }

  /**
   * Tworzy nowy temat arkuszy.
   */
  public function storeSheetTopic(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/arkusze'));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->redirectSheetsWithError('Nieprawidłowy token bezpieczeństwa.');
    }

    [$data, $errors] = $this->validateSheetTopicPayload($_POST);
    if (!empty($errors)) {
      $this->redirectSheetsWithError(implode(' ', $errors));
    }

    if (!$this->sheetService->isTopicNameAvailable($data['name'], $data['exam_type_id'])) {
      $this->redirectSheetsWithError('Temat o takiej nazwie już istnieje w tej kwalifikacji.');
    }

    $topicId = $this->sheetService->createTopic($data['exam_type_id'], $data['name'], $data['description'], $data['display_order']);
    if (!$topicId) {
      $this->redirectSheetsWithError('Nie udało się utworzyć tematu.');
    }

    $this->auditService->logAction($this->currentUser->getId(), 'sheet_topic_created', 'sheet_topic', (int) $topicId, null, $data);

    $this->setFlash('success', 'Temat został dodany.');
    header('Location: ' . url('admin/arkusze'));
    exit();
  }

  /**
   * Aktualizuje temat arkuszy.
   */
  public function updateSheetTopic(int $topicId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/arkusze'));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->redirectSheetsWithError('Nieprawidłowy token bezpieczeństwa.');
    }

    if (!$this->sheetService->topicExists($topicId)) {
      $this->redirectSheetsWithError('Temat nie istnieje.');
    }

    [$data, $errors] = $this->validateSheetTopicPayload($_POST);
    if (!empty($errors)) {
      $this->redirectSheetsWithError(implode(' ', $errors));
    }

    if (!$this->sheetService->isTopicNameAvailable($data['name'], $data['exam_type_id'], $topicId)) {
      $this->redirectSheetsWithError('Temat o takiej nazwie już istnieje w tej kwalifikacji.');
    }

    $isActive = (int) ($_POST['is_active'] ?? 1) === 1;
    if (!$this->sheetService->updateTopic($topicId, $data['exam_type_id'], $data['name'], $data['description'], $data['display_order'], $isActive)) {
      $this->redirectSheetsWithError('Nie udało się zapisać tematu.');
    }

    $this->auditService->logAction($this->currentUser->getId(), 'sheet_topic_updated', 'sheet_topic', $topicId, null, array_merge($data, ['is_active' => $isActive]));

    $this->setFlash('success', 'Temat został zaktualizowany.');
    header('Location: ' . url('admin/arkusze'));
    exit();
  }

  /**
   * Zmienia status aktywności arkusza z wpisem audytowym.
   */
  private function changeSheetState(int $sheetId, bool $isActive): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/arkusze'));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->respondSheetAction('error', 'Nieprawidłowy token bezpieczeństwa.');
    }

    $sheet = $this->sheetService->getSheetById($sheetId);
    if (!$sheet) {
      $this->respondSheetAction('error', 'Arkusz nie istnieje.');
    }

    if (!$this->sheetService->setSheetActive($sheetId, $isActive)) {
      $this->respondSheetAction('error', 'Nie udało się zmienić statusu arkusza.');
    }

    $this->auditService->logAction(
      $this->currentUser->getId(),
      $isActive ? 'sheet_activated' : 'sheet_deactivated',
      'sheet',
      $sheetId,
      ['is_active' => (int) $sheet['is_active']],
      ['is_active' => (int) $isActive]
    );

    $this->respondSheetAction('success', $isActive ? 'Arkusz został aktywowany.' : 'Arkusz został dezaktywowany.');
  }

  /**
   * Kończy akcję statusu arkusza: JSON dla AJAX, flash+redirect dla klasycznych.
   */
  private function respondSheetAction(string $type, string $message): never
  {
    if ($this->wantsJson()) {
      $this->jsonResponse([
        'ok' => $type !== 'error',
        'type' => $type,
        'message' => $message,
      ], $type === 'error' ? 422 : 200);
    }

    $this->setFlash($type, $message);
    header('Location: ' . url('admin/arkusze'));
    exit();
  }

  /**
   * Renderuje formularz arkusza (tworzenie/edycja).
   *
   * @param array<string, mixed> $sheet
   * @param array<string, string> $errors
   */
  private function renderSheetForm(string $mode, array $sheet, array $errors = [], ?int $sheetId = null): void
  {
    $this->renderView('admin/sheet_form', [
      'pageTitle' => ($mode === 'edit' ? 'Edycja arkusza' : 'Dodawanie arkusza') . ' | Admin Panel | Zdajnik',
      'pageDescription' => 'Formularz zarządzania arkuszem w panelu CMS Zdajnik.',
      'mode' => $mode,
      'sheet' => $sheet,
      'sheetId' => $sheetId,
      'sheetTopics' => $this->sheetService->getTopics(),
      'errors' => $errors,
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Wyciąga dane arkusza z żądania POST.
   *
   * @param array<string, mixed> $input
   * @return array{sheet_topic_id: int, title: string, description: string, difficulty: int, is_active: int}
   */
  private function extractSheetPayload(array $input): array
  {
    return [
      'sheet_topic_id' => (int) ($input['sheet_topic_id'] ?? 0),
      'title' => trim((string) ($input['title'] ?? '')),
      'description' => trim((string) ($input['description'] ?? '')),
      'difficulty' => (int) ($input['difficulty'] ?? 0),
      'is_active' => (int) ($input['is_active'] ?? 1),
    ];
  }

  /**
   * Waliduje dane arkusza.
   *
   * @param array<string, mixed> $input
   * @return array{0: array<string, mixed>, 1: array<string, string>}
   */
  private function validateSheetPayload(array $input): array
  {
    $data = $this->extractSheetPayload($input);
    $errors = [];

    if ($data['sheet_topic_id'] <= 0 || !$this->sheetService->topicExists($data['sheet_topic_id'])) {
      $errors['sheet_topic_id'] = 'Wybierz istniejący temat arkusza.';
    }

    if ($data['title'] === '') {
      $errors['title'] = 'Tytuł arkusza jest wymagany.';
    } elseif (mb_strlen($data['title']) > 200) {
      $errors['title'] = 'Tytuł arkusza nie może przekraczać 200 znaków.';
    }

    if ($data['description'] !== '' && mb_strlen($data['description']) > 5000) {
      $errors['description'] = 'Opis arkusza nie może przekraczać 5000 znaków.';
    }

    if ($data['difficulty'] < 1 || $data['difficulty'] > 10) {
      $errors['difficulty'] = 'Poziom trudności musi mieścić się w skali 1-10.';
    }

    if (!in_array($data['is_active'], [0, 1], true)) {
      $errors['is_active'] = 'Nieprawidłowy status arkusza.';
    }

    return [$data, $errors];
  }

  /**
   * Waliduje dane tematu arkuszy.
   *
   * @param array<string, mixed> $input
   * @return array{0: array{name: string, description: string, display_order: int}, 1: array<string, string>}
   */
  private function validateSheetTopicPayload(array $input): array
  {
    $data = [
      'exam_type_id' => (int) ($input['exam_type_id'] ?? 0),
      'name' => trim((string) ($input['name'] ?? '')),
      'description' => trim((string) ($input['description'] ?? '')),
      'display_order' => max(0, (int) ($input['display_order'] ?? 0)),
    ];
    $errors = [];

    if ($data['exam_type_id'] <= 0 || !$this->sheetService->examTypeExists($data['exam_type_id'])) {
      $errors['exam_type_id'] = 'Wybierz istniejącą kwalifikację.';
    }

    if ($data['name'] === '') {
      $errors['name'] = 'Nazwa tematu jest wymagana.';
    } elseif (mb_strlen($data['name']) > 200) {
      $errors['name'] = 'Nazwa tematu nie może przekraczać 200 znaków.';
    }

    if ($data['description'] !== '' && mb_strlen($data['description']) > 2000) {
      $errors['description'] = 'Opis tematu nie może przekraczać 2000 znaków.';
    }

    return [$data, $errors];
  }

  /**
   * Usuwa z dysku pliki zapisane w ramach nieudanego zapisu arkusza.
   *
   * @param array{success: bool, path?: string|null} ...$uploads Wyniki processFileUpload.
   */
  private function cleanupUploads(array ...$uploads): void
  {
    foreach ($uploads as $upload) {
      $this->sheetService->deleteStoredFile($upload['path'] ?? null);
    }
  }

  /**
   * Przerywa akcję modułu arkuszy komunikatem błędu i przekierowaniem na listę.
   */
  private function redirectSheetsWithError(string $message): never
  {
    $this->setFlash('error', $message);
    header('Location: ' . url('admin/arkusze'));
    exit();
  }
}
