<?php

namespace App\Controllers\Admin;

use App\Services\AdminDashboardService;
use App\Services\AdminExamService;

/**
 * Moduł egzaminów (kwalifikacji) panelu admina.
 *
 * @package App\Controllers\Admin
 */
class AdminExamController extends AdminBaseController
{
  private AdminDashboardService $adminService;
  private AdminExamService $examService;

  public function __construct()
  {
    parent::__construct();
    $this->adminService = new AdminDashboardService();
    $this->examService = new AdminExamService();
  }

  /**
   * Lista egzaminów.
   */
  public function exams(): void
  {
    ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'page' => $page] = $this->extractListParams();
    $result = $this->examService->searchExams($filters, $page, 20, $sort, $dir);

    $this->renderView('admin/exams', [
      'pageTitle' => 'Egzaminy | Admin Panel | Zdajnik',
      'pageDescription' => 'Zarządzanie egzaminami w panelu CMS Zdajnik.',
      'overview' => $this->adminService->getOverview(),
      'exams' => $result['items'],
      'filters' => $filters,
      'sort' => $sort,
      'dir' => $dir,
      'pagination' => $result,
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Endpoint JSON listy egzaminów (filtry/sort/paginacja bez przeładowania).
   */
  public function search(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      $this->jsonResponse(['ok' => false, 'message' => 'Metoda niedozwolona.'], 405);
    }

    ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'page' => $page] = $this->extractListParams();
    $result = $this->examService->searchExams($filters, $page, 20, $sort, $dir);

    $this->jsonResponse([
      'ok' => true,
      'html' => $this->renderPartialToString('admin/partials/exams_rows', ['exams' => $result['items']]),
      'meta' => [
        'total' => $result['total'],
        'page' => $result['page'],
        'perPage' => $result['perPage'],
        'totalPages' => $result['totalPages'],
      ],
    ]);
  }

  /**
   * Wspólna ekstrakcja filtrów listy z query stringa.
   *
   * @return array{filters: array<string, string>, sort: string, dir: string, page: int}
   */
  private function extractListParams(): array
  {
    return [
      'filters' => [
        'query' => trim((string) ($_GET['q'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
      ],
      'sort' => (string) ($_GET['sort'] ?? 'id'),
      'dir' => (string) ($_GET['dir'] ?? 'desc'),
      'page' => max(1, (int) ($_GET['page'] ?? 1)),
    ];
  }

  /**
   * Wyświetla formularz tworzenia egzaminu.
   */
  public function showCreateExamForm(): void
  {
    $this->renderExamForm('create', [
      'code' => '',
      'name' => '',
      'description' => '',
      'is_active' => 1,
    ]);
  }

  /**
   * Zapisuje nowy egzamin.
   */
  public function storeExam(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/egzaminy'));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->renderExamForm('create', $this->extractExamPayload($_POST), ['csrf' => 'Nieprawidłowy token bezpieczeństwa.']);
      return;
    }

    [$data, $errors] = $this->validateExamPayload($_POST);
    if (!empty($errors)) {
      $this->renderExamForm('create', $data, $errors);
      return;
    }

    if (!$this->examService->isCodeAvailable($data['code'])) {
      $errors['code'] = 'Egzamin o takim kodzie już istnieje.';
      $this->renderExamForm('create', $data, $errors);
      return;
    }

    if (!$this->db->beginTransaction()) {
      $this->renderExamForm('create', $data, ['general' => 'Nie udało się rozpocząć transakcji.']);
      return;
    }

    try {
      $examId = $this->examService->createExam(
        $data['code'],
        $data['name'],
        $data['description'],
        (bool) $data['is_active']
      );

      if (!$examId) {
        $this->db->rollBack();
        $this->renderExamForm('create', $data, ['general' => 'Nie udało się utworzyć egzaminu.']);
        return;
      }

      if (!$this->auditService->logExamCreated(
        $this->currentUser->getId(),
        (int) $examId,
        [
          'code' => $data['code'],
          'name' => $data['name'],
          'description' => $data['description'],
          'is_active' => (bool) $data['is_active'],
        ]
      )) {
        $this->db->rollBack();
        $this->renderExamForm('create', $data, ['general' => 'Nie udało się zapisać wpisu audytowego.']);
        return;
      }

      $this->db->commit();
      $this->setFlash('success', 'Egzamin został utworzony.');
      header('Location: ' . url('admin/egzaminy/' . (int) $examId));
      exit();
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminExamController] storeExam: ' . $e->getMessage());
      $this->renderExamForm('create', $data, ['general' => 'Wystąpił nieoczekiwany błąd podczas zapisu.']);
    }
  }

  /**
   * Wyświetla szczegóły egzaminu.
   */
  public function showExam(int $examId): void
  {
    $exam = $this->examService->getExamById($examId);

    if (!$exam) {
      $this->renderNotFound('Egzamin nie został znaleziony.');
      return;
    }

    $this->renderView('admin/exam_detail', [
      'pageTitle' => 'Egzamin ' . ($exam['code'] ?? ('#' . $examId)) . ' | Admin Panel | Zdajnik',
      'pageDescription' => 'Szczegóły egzaminu w panelu CMS Zdajnik.',
      'exam' => $exam,
      'questions' => $this->examService->getExamQuestions($examId, 200),
      'topics' => $this->examService->getExamTopics($examId),
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Wyświetla formularz edycji egzaminu.
   */
  public function showEditExamForm(int $examId): void
  {
    $exam = $this->examService->getExamById($examId);

    if (!$exam) {
      $this->renderNotFound('Egzamin nie został znaleziony.');
      return;
    }

    $this->renderExamForm('edit', $exam);
  }

  /**
   * Aktualizuje egzamin.
   */
  public function updateExam(int $examId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    }

    $exam = $this->examService->getExamById($examId);
    if (!$exam) {
      $this->setFlash('error', 'Egzamin nie istnieje.');
      header('Location: ' . url('admin/egzaminy'));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->renderExamForm('edit', $this->extractExamPayload($_POST, $exam), [
        'csrf' => 'Nieprawidłowy token bezpieczeństwa.',
      ], $examId);
      return;
    }

    [$data, $errors] = $this->validateExamPayload($_POST, $examId);
    if (!empty($errors)) {
      $this->renderExamForm('edit', $data, $errors, $examId);
      return;
    }

    if (!$this->examService->isCodeAvailable($data['code'], $examId)) {
      $errors['code'] = 'Egzamin o takim kodzie już istnieje.';
      $this->renderExamForm('edit', $data, $errors, $examId);
      return;
    }

    $normalizedCurrent = [
      'code' => trim((string) ($exam['code'] ?? '')),
      'name' => trim((string) ($exam['name'] ?? '')),
      'description' => trim((string) ($exam['description'] ?? '')),
      'is_active' => (int) ($exam['is_active'] ?? 1),
    ];

    $normalizedNew = [
      'code' => $data['code'],
      'name' => $data['name'],
      'description' => $data['description'],
      'is_active' => (int) ((bool) $data['is_active']),
    ];

    if ($normalizedCurrent === $normalizedNew) {
      $this->setFlash('info', 'Nie wprowadzono żadnych zmian.');
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    }

    if (!$this->db->beginTransaction()) {
      $this->renderExamForm('edit', $data, ['general' => 'Nie udało się rozpocząć transakcji.'], $examId);
      return;
    }

    try {
      if (!$this->examService->updateExam(
        $examId,
        $data['code'],
        $data['name'],
        $data['description'],
        (bool) $data['is_active']
      )) {
        $this->db->rollBack();
        $this->renderExamForm('edit', $data, ['general' => 'Nie udało się zapisać egzaminu.'], $examId);
        return;
      }

      if (!$this->auditService->logExamUpdated(
        $this->currentUser->getId(),
        $examId,
        $normalizedCurrent,
        $normalizedNew
      )) {
        $this->db->rollBack();
        $this->renderExamForm('edit', $data, ['general' => 'Nie udało się zapisać wpisu audytowego.'], $examId);
        return;
      }

      if (
        $normalizedCurrent['is_active'] !== $normalizedNew['is_active']
        && !$this->auditService->logExamStatusChanged(
          $this->currentUser->getId(),
          $examId,
          $normalizedNew['is_active'] === 1,
          ['is_active' => $normalizedCurrent['is_active']],
          ['is_active' => $normalizedNew['is_active']]
        )
      ) {
        $this->db->rollBack();
        $this->renderExamForm('edit', $data, ['general' => 'Nie udało się zapisać audytu zmiany statusu.'], $examId);
        return;
      }

      $this->db->commit();
      $this->setFlash('success', 'Egzamin został zaktualizowany.');
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminExamController] updateExam: ' . $e->getMessage());
      $this->renderExamForm('edit', $data, ['general' => 'Wystąpił nieoczekiwany błąd podczas zapisu.'], $examId);
    }
  }

  /**
   * Aktualizuje nazwę/opis/status tematu (kategorii pytań) należącego do egzaminu.
   */
  public function updateTopic(int $topicId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/egzaminy'));
      exit();
    }

    $topic = $this->examService->getTopicById($topicId);
    if (!$topic) {
      $this->setFlash('error', 'Temat nie istnieje.');
      header('Location: ' . url('admin/egzaminy'));
      exit();
    }

    $examId = (int) $topic['exam_type_id'];
    $redirectUrl = url('admin/egzaminy/' . $examId);

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->setFlash('error', 'Nieprawidłowy token bezpieczeństwa.');
      header('Location: ' . $redirectUrl);
      exit();
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $isActive = (int) ($_POST['is_active'] ?? 1) === 1;

    if ($name === '') {
      $this->setFlash('error', 'Nazwa tematu jest wymagana.');
      header('Location: ' . $redirectUrl);
      exit();
    }

    if (mb_strlen($name) > 200) {
      $this->setFlash('error', 'Nazwa tematu nie może przekraczać 200 znaków.');
      header('Location: ' . $redirectUrl);
      exit();
    }

    if (!$this->examService->isTopicNameAvailable($name, $examId, $topicId)) {
      $this->setFlash('error', 'Temat o takiej nazwie już istnieje w tej kwalifikacji.');
      header('Location: ' . $redirectUrl);
      exit();
    }

    $normalizedCurrent = [
      'name' => (string) $topic['name'],
      'description' => (string) ($topic['description'] ?? ''),
      'is_active' => (int) ($topic['is_active'] ?? 1),
    ];
    $normalizedNew = [
      'name' => $name,
      'description' => $description,
      'is_active' => (int) $isActive,
    ];

    if ($normalizedCurrent === $normalizedNew) {
      $this->setFlash('info', 'Nie wprowadzono żadnych zmian.');
      header('Location: ' . $redirectUrl);
      exit();
    }

    if (!$this->examService->updateTopic($topicId, $name, $description, $isActive)) {
      $this->setFlash('error', 'Nie udało się zapisać tematu.');
      header('Location: ' . $redirectUrl);
      exit();
    }

    $this->auditService->logAction(
      $this->currentUser->getId(),
      'update_topic',
      'topic',
      $topicId,
      $normalizedCurrent,
      $normalizedNew
    );

    $this->setFlash('success', 'Temat został zaktualizowany.');
    header('Location: ' . $redirectUrl);
    exit();
  }

  /**
   * Dezaktywuje egzamin.
   */
  public function deactivateExam(int $examId): void
  {
    $this->changeExamStatus($examId, false);
  }

  public function activateExam(int $examId): void
  {
    $this->changeExamStatus($examId, true);
  }

  private function changeExamStatus(int $examId, bool $isActive): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->respondExamAction($examId, 'error', 'Nieprawidłowy token bezpieczeństwa.');
    }

    $exam = $this->examService->getExamById($examId);
    if (!$exam) {
      if ($this->wantsJson()) {
        $this->jsonResponse(['ok' => false, 'type' => 'error', 'message' => 'Egzamin nie istnieje.'], 404);
      }
      $this->setFlash('error', 'Egzamin nie istnieje.');
      header('Location: ' . url('admin/egzaminy'));
      exit();
    }

    $oldStatus = (int) ($exam['is_active'] ?? 0);
    $newStatus = $isActive ? 1 : 0;
    if ($oldStatus === $newStatus) {
      $this->respondExamAction($examId, 'info', $isActive ? 'Egzamin jest już aktywny.' : 'Egzamin jest już nieaktywny.');
    }

    if (!$this->db->beginTransaction()) {
      $this->respondExamAction($examId, 'error', 'Nie udało się rozpocząć transakcji.');
    }

    try {
      $changed = $isActive
        ? $this->examService->activateExam($examId)
        : $this->examService->deactivateExam($examId);
      $audited = $this->auditService->logExamStatusChanged(
        $this->currentUser->getId(),
        $examId,
        $isActive,
        ['is_active' => $oldStatus],
        ['is_active' => $newStatus]
      );

      if (!$changed || !$audited) {
        $this->db->rollBack();
        $this->respondExamAction($examId, 'error', 'Nie udało się zmienić statusu egzaminu.');
      }

      $this->db->commit();
      $this->respondExamAction($examId, 'success', $isActive ? 'Egzamin został aktywowany.' : 'Egzamin został zdezaktywowany.');
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminExamController] changeExamStatus: ' . $e->getMessage());
      $this->respondExamAction($examId, 'error', 'Wystąpił nieoczekiwany błąd podczas zmiany statusu.');
    }
  }

  /**
   * Kończy akcję statusu egzaminu: JSON dla AJAX, flash+redirect dla klasycznych.
   */
  private function respondExamAction(int $examId, string $type, string $message): never
  {
    if ($this->wantsJson()) {
      $this->jsonResponse([
        'ok' => $type !== 'error',
        'type' => $type,
        'message' => $message,
      ], $type === 'error' ? 422 : 200);
    }

    $this->setFlash($type, $message);
    header('Location: ' . url('admin/egzaminy/' . $examId));
    exit();
  }

  /**
   * Renderuje formularz egzaminu.
   *
   * @param array<string, mixed> $exam
   * @param array<string, string> $errors
   */
  private function renderExamForm(string $mode, array $exam, array $errors = [], ?int $examId = null): void
  {
    $isEdit = $mode === 'edit';
    $title = $isEdit ? 'Edycja egzaminu' : 'Dodawanie egzaminu';

    $this->renderView('admin/exam_form', [
      'pageTitle' => $title . ' | Admin Panel | Zdajnik',
      'pageDescription' => 'Formularz zarządzania egzaminem w panelu CMS Zdajnik.',
      'mode' => $mode,
      'examId' => $examId,
      'exam' => $exam,
      'errors' => $errors,
      'questionsCount' => (int) ($exam['questions_count'] ?? 0),
      'activeQuestionsCount' => (int) ($exam['active_questions_count'] ?? 0),
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Pobiera dane egzaminu z requestu.
   *
   * @param array<string, mixed> $input
   * @return array<string, mixed>
   */
  private function extractExamPayload(array $input, ?array $fallback = null): array
  {
    return [
      'code' => strtoupper(trim((string) ($input['code'] ?? ($fallback['code'] ?? '')))),
      'name' => trim((string) ($input['name'] ?? ($fallback['name'] ?? ''))),
      'description' => trim((string) ($input['description'] ?? ($fallback['description'] ?? ''))),
      'is_active' => (int) (($input['is_active'] ?? ($fallback['is_active'] ?? 1)) ? 1 : 0),
    ];
  }

  /**
   * Waliduje dane egzaminu.
   *
   * @param array<string, mixed> $input
   * @return array{0: array<string, mixed>, 1: array<string, string>}
   */
  private function validateExamPayload(array $input, ?int $examId = null): array
  {
    $data = $this->extractExamPayload($input);
    $errors = [];

    if ($data['code'] === '') {
      $errors['code'] = 'Kod egzaminu jest wymagany.';
    } elseif (mb_strlen($data['code']) > 50) {
      $errors['code'] = 'Kod egzaminu nie może przekraczać 50 znaków.';
    } elseif (!preg_match('/^[A-Z0-9][A-Z0-9._-]*$/', $data['code'])) {
      $errors['code'] = 'Kod egzaminu może zawierać tylko litery, cyfry, kropkę, myślnik i podkreślenie.';
    }

    if ($data['name'] === '') {
      $errors['name'] = 'Nazwa egzaminu jest wymagana.';
    } elseif (mb_strlen($data['name']) > 200) {
      $errors['name'] = 'Nazwa egzaminu nie może przekraczać 200 znaków.';
    }

    if ($data['description'] !== '' && mb_strlen($data['description']) > 5000) {
      $errors['description'] = 'Opis egzaminu nie może przekraczać 5000 znaków.';
    }

    if (!in_array($data['is_active'], [0, 1], true)) {
      $errors['is_active'] = 'Nieprawidłowy status egzaminu.';
    }

    return [$data, $errors];
  }
}
