<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\AdminExamService;
use App\Services\AdminQuestionService;
use App\Services\AdminSheetService;
use App\Services\AdminUserService;
use App\Services\AdminDashboardService;
use App\Services\AdminAuditService;
use App\Services\AnalyticsService;
use App\Services\CsrfService;

/**
 * Kontroler Panelu CMS/Admin.
 *
 * Odpowiada za główny dashboard administracyjny dostępny wyłącznie dla ról admin.
 * W obecnym etapie serwuje bezpieczny punkt wejścia do panelu CMS.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class AdminController extends BaseController
{
  private Database $db;
  private AdminDashboardService $adminService;
  private AdminExamService $examService;
  private AdminQuestionService $questionService;
  private AdminSheetService $sheetService;
  private AdminUserService $userService;
  private AdminAuditService $auditService;
  private AnalyticsService $analyticsService;
  private CsrfService $csrfService;

  /**
   * Inicjalizuje kontroler i wymusza dostęp administracyjny.
   */
  public function __construct()
  {
    parent::__construct();
    $this->requireAdmin();
    $this->db = Database::getInstance();
    $this->adminService = new AdminDashboardService();
    $this->examService = new AdminExamService();
    $this->questionService = new AdminQuestionService();
    $this->sheetService = new AdminSheetService();
    $this->userService = new AdminUserService();
    $this->auditService = new AdminAuditService();
    $this->analyticsService = new AnalyticsService();
    $this->csrfService = new CsrfService();
  }

  /**
   * Renderuje główny dashboard CMS.
   *
   * @return void
   */
  public function index(): void
  {
    $this->renderView('admin/dashboard', [
      'pageTitle' => 'Admin Panel | Zdajnik',
      'pageDescription' => 'Panel administracyjny Zdajnik do zarządzania treścią i strukturą serwisu.',
      'overview' => $this->adminService->getOverview(),
      'attention' => $this->adminService->getAttentionItems(6),
      'recentAuditActions' => $this->adminService->getRecentAuditActions(8),
      'recentUserActivity' => $this->userService->getRecentUserActivity(8),
      'hasActivityData' => $this->adminService->hasActivityData(),
      'auditActions' => $this->getAuditActions(),
    ]);
  }

  /**
   * Widok użytkowników.
   */
  public function users(): void
  {
    $verified = trim((string) ($_GET['verified'] ?? ''));
    if ($verified === '1') {
      $verified = 'verified';
    } elseif ($verified === '0') {
      $verified = 'unverified';
    }

    $filters = [
      'query' => trim((string) ($_GET['q'] ?? '')),
      'role' => trim((string) ($_GET['role'] ?? '')),
      'status' => trim((string) ($_GET['status'] ?? '')),
      'verified' => $verified,
      'auth_provider' => trim((string) ($_GET['auth_provider'] ?? '')),
      'activity' => trim((string) ($_GET['activity'] ?? '')),
    ];
    $sort = (string) ($_GET['sort'] ?? 'id');
    $dir = (string) ($_GET['dir'] ?? 'desc');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $result = $this->userService->searchUsers($filters, $page, $perPage, $sort, $dir);

    $this->renderView('admin/users', [
      'pageTitle' => 'Użytkownicy | Admin Panel | Zdajnik',
      'pageDescription' => 'Lista użytkowników w panelu CMS Zdajnik.',
      'overview' => $this->adminService->getOverview(),
      'users' => $result['items'],
      'filters' => $filters,
      'sort' => $sort,
      'dir' => $dir,
      'pagination' => $result,
      'summary' => $this->userService->getUserManagementSummary(),
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Wyświetla szczegóły użytkownika.
   */
  public function showUser(int $userId): void
  {
    $user = $this->userService->getUserById($userId);

    if (!$user) {
      http_response_code(404);
      $errorController = new ErrorController();
      $errorController->showError(404, 'Użytkownik nie został znaleziony.');
      return;
    }

    $this->renderView('admin/user_detail', [
      'pageTitle' => 'Użytkownik #' . $userId . ' | Admin Panel | Zdajnik',
      'pageDescription' => 'Szczegóły użytkownika w panelu CMS Zdajnik.',
      'user' => $user,
      'userStats' => $this->userService->getUserStats($userId),
      'attempts' => $this->userService->getUserAttempts($userId, 100),
      'answerPreview' => $this->userService->getUserAnswerPreview($userId, 40),
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Aktualizuje rolę użytkownika.
   */
  public function updateUserRole(int $userId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/uzytkownicy/' . $userId));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Nieprawidłowy token bezpieczeństwa.'
      ];
      header('Location: ' . url('admin/uzytkownicy/' . $userId));
      exit();
    }

    $newRole = trim((string) ($_POST['role'] ?? ''));
    if (!in_array($newRole, ['user', 'admin'], true)) {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Nieprawidłowa rola użytkownika.'
      ];
      header('Location: ' . url('admin/uzytkownicy/' . $userId));
      exit();
    }

    $user = $this->userService->getUserById($userId);
    if (!$user) {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Użytkownik nie istnieje.'
      ];
      header('Location: ' . url('admin/uzytkownicy'));
      exit();
    }

    $currentAdminId = $this->currentUser->getId();
    if (
      ($user['role'] ?? 'user') === 'admin'
      && (int) ($user['is_active'] ?? 0) === 1
      && $newRole === 'user'
      && $this->userService->countActiveAdmins() <= 1
    ) {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Nie możesz odebrać roli jedynemu administratorowi.'
      ];
      header('Location: ' . url('admin/uzytkownicy/' . $userId));
      exit();
    }

    if (($user['role'] ?? 'user') === $newRole) {
      $_SESSION['flash_message'] = [
        'type' => 'info',
        'text' => 'Rola użytkownika już jest ustawiona tak samo.'
      ];
      header('Location: ' . url('admin/uzytkownicy/' . $userId));
      exit();
    }

    if (!$this->db->beginTransaction()) {
      $this->setFlash('error', 'Nie udało się rozpocząć transakcji.');
      $this->redirectAfterUserAction($userId);
    }

    try {
      if (
        !$this->userService->updateUserRole($userId, $newRole)
        || !$this->auditService->logRoleChange(
          $currentAdminId,
          $userId,
          ['role' => $user['role'] ?? 'user'],
          ['role' => $newRole]
        )
      ) {
        $this->db->rollBack();
        $this->setFlash('error', 'Nie udało się zapisać zmiany roli.');
        $this->redirectAfterUserAction($userId);
      }

      $this->db->commit();

      $redirectUrl = url('admin/uzytkownicy/' . $userId);

      if ($userId === $currentAdminId) {
        $freshUser = (new \App\Models\UserModel())->getUserById($currentAdminId);
        if ($freshUser) {
          $_SESSION['user'] = $freshUser;
        }

        if ($newRole === 'user') {
          $redirectUrl = url('/');
        }
      }

      $this->setFlash('success', 'Rola użytkownika została zaktualizowana.');
      if (($_POST['return_to'] ?? '') === 'list' && $userId !== $currentAdminId) {
        $redirectUrl = url('admin/uzytkownicy');
      }
      header('Location: ' . $redirectUrl);
      exit();
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminController] updateUserRole: ' . $e->getMessage());
      $this->setFlash('error', 'Wystąpił nieoczekiwany błąd podczas zmiany roli.');
      $this->redirectAfterUserAction($userId);
    }
  }

  public function verifyUser(int $userId): void
  {
    $this->changeUserState($userId, 'verify');
  }

  public function deactivateUser(int $userId): void
  {
    $this->changeUserState($userId, 'deactivate');
  }

  public function reactivateUser(int $userId): void
  {
    $this->changeUserState($userId, 'reactivate');
  }

  /**
   * Lista egzaminów.
   */
  public function exams(): void
  {
    $filters = [
      'query' => trim((string) ($_GET['q'] ?? '')),
      'status' => trim((string) ($_GET['status'] ?? '')),
    ];
    $sort = (string) ($_GET['sort'] ?? 'id');
    $dir = (string) ($_GET['dir'] ?? 'desc');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $result = $this->examService->searchExams($filters, $page, $perPage, $sort, $dir);

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
      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Egzamin został utworzony.',
      ];
      header('Location: ' . url('admin/egzaminy/' . (int) $examId));
      exit();
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminController] storeExam: ' . $e->getMessage());
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
      http_response_code(404);
      $errorController = new ErrorController();
      $errorController->showError(404, 'Egzamin nie został znaleziony.');
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
      http_response_code(404);
      $errorController = new ErrorController();
      $errorController->showError(404, 'Egzamin nie został znaleziony.');
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
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Egzamin nie istnieje.',
      ];
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
      $_SESSION['flash_message'] = [
        'type' => 'info',
        'text' => 'Nie wprowadzono żadnych zmian.',
      ];
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
      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Egzamin został zaktualizowany.',
      ];
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminController] updateExam: ' . $e->getMessage());
      $this->renderExamForm('edit', $data, ['general' => 'Wystąpił nieoczekiwany błąd podczas zapisu.'], $examId);
    }
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

  /**
   * Lista pytań.
   */
  public function questions(): void
  {
    $filters = [
      'query' => trim((string) ($_GET['q'] ?? '')),
      'exam_id' => trim((string) ($_GET['exam_id'] ?? '')),
      'topic_id' => trim((string) ($_GET['topic_id'] ?? '')),
      'status' => trim((string) ($_GET['status'] ?? '')),
      'difficulty' => trim((string) ($_GET['difficulty'] ?? '')),
    ];
    $sort = (string) ($_GET['sort'] ?? 'id');
    $dir = (string) ($_GET['dir'] ?? 'desc');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $result = $this->questionService->searchQuestions($filters, $page, $perPage, $sort, $dir);

    $this->renderView('admin/questions', [
      'pageTitle' => 'Pytania | Admin Panel | Zdajnik',
      'pageDescription' => 'Zarządzanie pytaniami z wersjonowaniem w panelu CMS Zdajnik.',
      'overview' => $this->adminService->getOverview(),
      'questions' => $result['items'],
      'filters' => $filters,
      'sort' => $sort,
      'dir' => $dir,
      'pagination' => $result,
      'exams' => $this->questionService->getExams(),
      'topics' => $this->questionService->getTopicsForFilter(
        ctype_digit((string) $filters['exam_id']) ? (int) $filters['exam_id'] : null
      ),
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Formularz dodawania pytania.
   */
  public function showCreateQuestionForm(): void
  {
    $examId = max(0, (int) ($_GET['exam_id'] ?? 0));
    $topicId = max(0, (int) ($_GET['topic_id'] ?? 0));
    $this->renderQuestionForm('create', [
      'exam_type_id' => $examId,
      'topic_id' => $topicId,
      'source' => '',
      'difficulty' => 'medium',
      'question_text' => '',
      'explanation' => '',
      'is_active' => 1,
      'remove_image' => 0,
      'answers' => $this->buildDefaultAnswers(),
    ]);
  }

  /**
   * Zapisuje nowe pytanie.
   */
  public function storeQuestion(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/pytania'));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->renderQuestionForm('create', $this->extractQuestionPayload($_POST), ['csrf' => 'Nieprawidłowy token bezpieczeństwa.']);
      return;
    }

    $payload = $this->extractQuestionPayload($_POST);
    $result = $this->questionService->createQuestion($payload, $_FILES['image'] ?? null, $this->currentUser->getId());
    if (!($result['success'] ?? false)) {
      $this->renderQuestionForm('create', $payload, $result['errors'] ?? []);
      return;
    }

    $_SESSION['flash_message'] = [
      'type' => 'success',
      'text' => 'Pytanie zostało utworzone.',
    ];
    header('Location: ' . url('admin/pytania/' . (int) $result['question_id']));
    exit();
  }

  /**
   * Szczegóły pytania.
   */
  public function showQuestion(int $questionId): void
  {
    $question = $this->questionService->getQuestionById($questionId);
    if (!$question) {
      http_response_code(404);
      $errorController = new ErrorController();
      $errorController->showError(404, 'Pytanie nie zostało znalezione.');
      return;
    }

    $this->renderView('admin/question_detail', [
      'pageTitle' => 'Pytanie #' . $questionId . ' | Admin Panel | Zdajnik',
      'pageDescription' => 'Szczegóły pytania w panelu CMS Zdajnik.',
      'question' => $question,
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Formularz edycji pytania.
   */
  public function showEditQuestionForm(int $questionId): void
  {
    $question = $this->questionService->getQuestionById($questionId);
    if (!$question) {
      http_response_code(404);
      $errorController = new ErrorController();
      $errorController->showError(404, 'Pytanie nie zostało znalezione.');
      return;
    }

    $currentVersion = $question['current_version'] ?? [];
    $answers = $question['answers'] ?? [];
    $this->renderQuestionForm('edit', [
      'id' => $questionId,
      'exam_type_id' => (int) ($question['exam_type_id'] ?? 0),
      'topic_id' => (int) ($question['topic_id'] ?? 0),
      'source' => (string) ($question['source'] ?? ''),
      'difficulty' => (string) ($question['difficulty'] ?? 'medium'),
      'question_text' => (string) ($currentVersion['question_text'] ?? ''),
      'explanation' => (string) ($currentVersion['explanation'] ?? ''),
      'is_active' => (int) ($question['is_active'] ?? 1),
      'remove_image' => 0,
      'image_name' => $currentVersion['image_name'] ?? null,
      'answers' => $this->normalizeAnswersForForm($answers),
    ], [], $questionId);
  }

  /**
   * Aktualizuje pytanie.
   */
  public function updateQuestion(int $questionId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/pytania/' . $questionId));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $currentQuestion = $this->questionService->getQuestionById($questionId);
      $payload = $this->extractQuestionPayload($_POST);
      if ($currentQuestion && !isset($payload['image_name'])) {
        $payload['image_name'] = $currentQuestion['current_version']['image_name'] ?? null;
      }
      $this->renderQuestionForm('edit', $payload, ['csrf' => 'Nieprawidłowy token bezpieczeństwa.'], $questionId);
      return;
    }

    $payload = $this->extractQuestionPayload($_POST);
    $currentQuestion = $this->questionService->getQuestionById($questionId);
    if ($currentQuestion && !isset($payload['image_name'])) {
      $payload['image_name'] = $currentQuestion['current_version']['image_name'] ?? null;
    }

    $result = $this->questionService->updateQuestion($questionId, $payload, $_FILES['image'] ?? null, $this->currentUser->getId());
    if (!($result['success'] ?? false)) {
      $this->renderQuestionForm('edit', $payload, $result['errors'] ?? [], $questionId);
      return;
    }

    $_SESSION['flash_message'] = [
      'type' => 'success',
      'text' => 'Pytanie zostało zaktualizowane.',
    ];
    header('Location: ' . url('admin/pytania/' . $questionId));
    exit();
  }

  /**
   * Dezaktywuje pytanie.
   */
  public function deactivateQuestion(int $questionId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/pytania/' . $questionId));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Nieprawidłowy token bezpieczeństwa.',
      ];
      header('Location: ' . url('admin/pytania/' . $questionId));
      exit();
    }

    $result = $this->questionService->deactivateQuestion($questionId, $this->currentUser->getId());
    if (!($result['success'] ?? false)) {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => (string) ($result['errors']['general'] ?? 'Nie udało się dezaktywować pytania.'),
      ];
      header('Location: ' . url('admin/pytania/' . $questionId));
      exit();
    }

    $_SESSION['flash_message'] = [
      'type' => 'success',
      'text' => 'Pytanie zostało zdezaktywowane.',
    ];
    header('Location: ' . url('admin/pytania/' . $questionId));
    exit();
  }

  public function activateQuestion(int $questionId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/pytania/' . $questionId));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->setFlash('error', 'Nieprawidłowy token bezpieczeństwa.');
      header('Location: ' . url('admin/pytania/' . $questionId));
      exit();
    }

    $result = $this->questionService->activateQuestion($questionId, $this->currentUser->getId());
    if (!($result['success'] ?? false)) {
      $this->setFlash('error', (string) ($result['errors']['general'] ?? 'Nie udało się aktywować pytania.'));
      header('Location: ' . url('admin/pytania/' . $questionId));
      exit();
    }

    $this->setFlash('success', 'Pytanie zostało aktywowane.');
    header('Location: ' . url('admin/pytania/' . $questionId));
    exit();
  }

  /**
   * Widok aktywności i statystyk panelu.
   */
  public function activity(): void
  {
    $this->analytics();
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
      $this->sendJsonResponse(['success' => false, 'message' => 'Metoda niedozwolona.'], 405);
    }

    $filters = $this->extractAnalyticsFilters();
    $dashboardData = $this->analyticsService->getDashboardData($filters);

    $this->sendJsonResponse([
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
      $this->sendJsonResponse(['success' => false, 'message' => 'Metoda niedozwolona.'], 405);
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
      $this->sendJsonResponse(['success' => false, 'message' => 'Nieprawidłowy zestaw danych.'], 400);
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

  /**
   * Zwraca słownik akcji audytowych.
   *
   * @return array<string, string>
   */
  private function getAuditActions(): array
  {
    return [
      'change_user_role' => 'Zmiana roli użytkownika',
      'change_user_status' => 'Zmiana statusu użytkownika',
      'verify_user' => 'Weryfikacja użytkownika',
      'deactivate_user' => 'Dezaktywacja użytkownika',
      'reactivate_user' => 'Reaktywacja użytkownika',
      'create_exam' => 'Utworzenie egzaminu',
      'update_exam' => 'Edycja egzaminu',
      'activate_exam' => 'Aktywacja egzaminu',
      'deactivate_exam' => 'Dezaktywacja egzaminu',
      'create_question' => 'Utworzenie pytania',
      'update_question' => 'Edycja pytania',
      'activate_question' => 'Aktywacja pytania',
      'deactivate_question' => 'Dezaktywacja pytania',
    ];
  }

  private function changeUserState(int $userId, string $action): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirectAfterUserAction($userId);
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->setFlash('error', 'Nieprawidłowy token bezpieczeństwa.');
      $this->redirectAfterUserAction($userId);
    }

    $user = $this->userService->getUserById($userId);
    if (!$user || !empty($user['deleted_at'])) {
      $this->setFlash('error', 'Użytkownik nie istnieje lub konto zostało usunięte.');
      header('Location: ' . url('admin/uzytkownicy'));
      exit();
    }

    $currentAdminId = $this->currentUser->getId();
    if ($action === 'deactivate' && $userId === $currentAdminId) {
      $this->setFlash('error', 'Nie możesz zdezaktywować własnego konta.');
      $this->redirectAfterUserAction($userId);
    }

    if (
      $action === 'deactivate'
      && ($user['role'] ?? 'user') === 'admin'
      && (int) ($user['is_active'] ?? 0) === 1
      && $this->userService->countActiveAdmins() <= 1
    ) {
      $this->setFlash('error', 'Nie możesz zdezaktywować ostatniego aktywnego administratora.');
      $this->redirectAfterUserAction($userId);
    }

    $alreadyApplied = ($action === 'verify' && (int) ($user['is_verified'] ?? 0) === 1)
      || ($action === 'deactivate' && (int) ($user['is_active'] ?? 1) === 0)
      || ($action === 'reactivate' && (int) ($user['is_active'] ?? 0) === 1);
    if ($alreadyApplied) {
      $this->setFlash('info', 'Wybrany status jest już ustawiony.');
      $this->redirectAfterUserAction($userId);
    }

    if (!$this->db->beginTransaction()) {
      $this->setFlash('error', 'Nie udało się rozpocząć transakcji.');
      $this->redirectAfterUserAction($userId);
    }

    try {
      if ($action === 'verify') {
        $changed = $this->userService->verifyUser($userId);
        $audited = $this->auditService->logUserVerified(
          $currentAdminId,
          $userId,
          ['is_verified' => (int) ($user['is_verified'] ?? 0)],
          ['is_verified' => 1]
        );
        $message = 'Konto użytkownika zostało zweryfikowane.';
      } elseif ($action === 'deactivate') {
        $changed = $this->userService->deactivateUser($userId);
        $audited = $this->auditService->logUserDeactivated(
          $currentAdminId,
          $userId,
          ['is_active' => (int) ($user['is_active'] ?? 1)],
          ['is_active' => 0]
        );
        $message = 'Konto użytkownika zostało zdezaktywowane.';
      } else {
        $changed = $this->userService->reactivateUser($userId);
        $audited = $this->auditService->logUserReactivated(
          $currentAdminId,
          $userId,
          ['is_active' => (int) ($user['is_active'] ?? 0)],
          ['is_active' => 1]
        );
        $message = 'Konto użytkownika zostało reaktywowane.';
      }

      if (!$changed || !$audited) {
        $this->db->rollBack();
        $this->setFlash('error', 'Nie udało się zapisać zmiany statusu.');
        $this->redirectAfterUserAction($userId);
      }

      $this->db->commit();
      $this->setFlash('success', $message);
      $this->redirectAfterUserAction($userId);
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminController] changeUserState: ' . $e->getMessage());
      $this->setFlash('error', 'Wystąpił nieoczekiwany błąd podczas zmiany statusu.');
      $this->redirectAfterUserAction($userId);
    }
  }

  private function changeExamStatus(int $examId, bool $isActive): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->setFlash('error', 'Nieprawidłowy token bezpieczeństwa.');
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    }

    $exam = $this->examService->getExamById($examId);
    if (!$exam) {
      $this->setFlash('error', 'Egzamin nie istnieje.');
      header('Location: ' . url('admin/egzaminy'));
      exit();
    }

    $oldStatus = (int) ($exam['is_active'] ?? 0);
    $newStatus = $isActive ? 1 : 0;
    if ($oldStatus === $newStatus) {
      $this->setFlash('info', $isActive ? 'Egzamin jest już aktywny.' : 'Egzamin jest już nieaktywny.');
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    }

    if (!$this->db->beginTransaction()) {
      $this->setFlash('error', 'Nie udało się rozpocząć transakcji.');
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
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
        $this->setFlash('error', 'Nie udało się zmienić statusu egzaminu.');
        header('Location: ' . url('admin/egzaminy/' . $examId));
        exit();
      }

      $this->db->commit();
      $this->setFlash('success', $isActive ? 'Egzamin został aktywowany.' : 'Egzamin został zdezaktywowany.');
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminController] changeExamStatus: ' . $e->getMessage());
      $this->setFlash('error', 'Wystąpił nieoczekiwany błąd podczas zmiany statusu.');
    }

    header('Location: ' . url('admin/egzaminy/' . $examId));
    exit();
  }

  private function redirectAfterUserAction(int $userId): never
  {
    $target = ($_POST['return_to'] ?? '') === 'list'
      ? url('admin/uzytkownicy')
      : url('admin/uzytkownicy/' . $userId);
    header('Location: ' . $target);
    exit();
  }

  private function setFlash(string $type, string $text): void
  {
    $_SESSION['flash_message'] = [
      'type' => $type,
      'text' => $text,
    ];
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

  /**
   * Renderuje formularz pytania.
   *
   * @param array<string, mixed> $question
   * @param array<string, string> $errors
   */
  private function renderQuestionForm(string $mode, array $question, array $errors = [], ?int $questionId = null): void
  {
    $question['answers'] = $this->normalizeAnswersForForm($question['answers'] ?? []);

    $this->renderView('admin/question_form', [
      'pageTitle' => ($mode === 'edit' ? 'Edycja pytania' : 'Dodawanie pytania') . ' | Admin Panel | Zdajnik',
      'pageDescription' => 'Formularz zarządzania pytaniem w panelu CMS Zdajnik.',
      'mode' => $mode,
      'questionId' => $questionId,
      'question' => $question,
      'errors' => $errors,
      'examGroups' => $this->questionService->getExamTopicGroups(),
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Buduje domyślne pola odpowiedzi.
   *
   * @return array<int, array<string, mixed>>
   */
  private function buildDefaultAnswers(int $count = 4): array
  {
    $answers = [];
    for ($i = 0; $i < $count; $i++) {
      $answers[] = [
        'id' => null,
        'content' => '',
        'is_correct' => $i === 0 ? 1 : 0,
      ];
    }

    return $answers;
  }

  /**
   * Normalizuje odpowiedzi do formularza.
   *
   * @param array<int, array<string, mixed>> $answers
   * @return array<int, array<string, mixed>>
   */
  private function normalizeAnswersForForm(array $answers): array
  {
    if (empty($answers)) {
      return $this->buildDefaultAnswers();
    }

    $normalized = [];
    foreach ($answers as $index => $answer) {
      $normalized[] = [
        'id' => $answer['id'] ?? null,
        'content' => $answer['answer_text'] ?? ($answer['content'] ?? ''),
        'is_correct' => (int) ($answer['is_correct'] ?? 0),
      ];
    }

    while (count($normalized) < 4) {
      $normalized[] = [
        'id' => null,
        'content' => '',
        'is_correct' => 0,
      ];
    }

    return $normalized;
  }

  /**
   * Wydobywa dane pytania z requestu.
   *
   * @param array<string, mixed> $input
   * @return array<string, mixed>
   */
  private function extractQuestionPayload(array $input): array
  {
    $rawAnswers = $input['answers'] ?? [];
    if (!is_array($rawAnswers)) {
      $rawAnswers = [];
    }

    $correctIndex = isset($input['correct_answer_index']) && $input['correct_answer_index'] !== ''
      ? (int) $input['correct_answer_index']
      : null;

    $answers = [];
    foreach ($rawAnswers as $index => $answer) {
      $isCorrect = $correctIndex !== null && (int) $index === $correctIndex;
      $answers[] = [
        'id' => $answer['id'] ?? null,
        'content' => trim((string) ($answer['content'] ?? '')),
        'is_correct' => $isCorrect ? 1 : 0,
      ];
    }

    if (empty($answers)) {
      $answers = $this->buildDefaultAnswers();
    }

    return [
      'exam_type_id' => (int) ($input['exam_type_id'] ?? 0),
      'topic_id' => (int) ($input['topic_id'] ?? 0),
      'source' => trim((string) ($input['source'] ?? '')),
      'difficulty' => (string) ($input['difficulty'] ?? 'medium'),
      'question_text' => trim((string) ($input['question_text'] ?? '')),
      'explanation' => trim((string) ($input['explanation'] ?? '')),
      'is_active' => (int) (($input['is_active'] ?? 1) ? 1 : 0),
      'remove_image' => !empty($input['remove_image']) ? 1 : 0,
      'image_name' => $input['image_name'] ?? null,
      'correct_answer_index' => $correctIndex,
      'answers' => $answers,
    ];
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

  /**
   * Wysyła odpowiedź JSON.
   *
   * @param array<string, mixed> $data
   */
  private function sendJsonResponse(array $data, int $statusCode = 200): void
  {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
  }

  // ---------------------------------------------------------------------------
  // BIBLIOTEKA ARKUSZY
  // ---------------------------------------------------------------------------

  /**
   * Lista arkuszy wraz z zarządzaniem tematami.
   */
  public function sheets(): void
  {
    $filters = [
      'query' => trim((string) ($_GET['q'] ?? '')),
      'topic_id' => trim((string) ($_GET['topic_id'] ?? '')),
      'exam_id' => trim((string) ($_GET['exam_id'] ?? '')),
      'status' => trim((string) ($_GET['status'] ?? '')),
    ];
    $page = max(1, (int) ($_GET['page'] ?? 1));
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

    $_SESSION['flash_message'] = [
      'type' => 'success',
      'text' => 'Arkusz został dodany.',
    ];
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
      http_response_code(404);
      $errorController = new ErrorController();
      $errorController->showError(404, 'Arkusz nie został znaleziony.');
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
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Arkusz nie istnieje.',
      ];
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

    $_SESSION['flash_message'] = [
      'type' => 'success',
      'text' => 'Arkusz został zaktualizowany.',
    ];
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

    $_SESSION['flash_message'] = [
      'type' => 'success',
      'text' => 'Temat został dodany.',
    ];
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

    $_SESSION['flash_message'] = [
      'type' => 'success',
      'text' => 'Temat został zaktualizowany.',
    ];
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
      $this->redirectSheetsWithError('Nieprawidłowy token bezpieczeństwa.');
    }

    $sheet = $this->sheetService->getSheetById($sheetId);
    if (!$sheet) {
      $this->redirectSheetsWithError('Arkusz nie istnieje.');
    }

    if (!$this->sheetService->setSheetActive($sheetId, $isActive)) {
      $this->redirectSheetsWithError('Nie udało się zmienić statusu arkusza.');
    }

    $this->auditService->logAction(
      $this->currentUser->getId(),
      $isActive ? 'sheet_activated' : 'sheet_deactivated',
      'sheet',
      $sheetId,
      ['is_active' => (int) $sheet['is_active']],
      ['is_active' => (int) $isActive]
    );

    $_SESSION['flash_message'] = [
      'type' => 'success',
      'text' => $isActive ? 'Arkusz został aktywowany.' : 'Arkusz został dezaktywowany.',
    ];
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
  private function redirectSheetsWithError(string $message): void
  {
    $_SESSION['flash_message'] = [
      'type' => 'error',
      'text' => $message,
    ];
    header('Location: ' . url('admin/arkusze'));
    exit();
  }
}
