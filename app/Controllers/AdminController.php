<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\AdminExamService;
use App\Services\AdminQuestionService;
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
      'pageTitle' => 'Admin Panel | Examly',
      'pageDescription' => 'Panel administracyjny Examly do zarządzania treścią i strukturą serwisu.',
      'overview' => $this->adminService->getOverview(),
      'recentUsers' => $this->adminService->getRecentUsers(6),
      'hasActivityData' => $this->adminService->hasActivityData(),
    ]);
  }

  /**
   * Widok użytkowników.
   */
  public function users(): void
  {
    $filters = [
      'query' => trim((string) ($_GET['q'] ?? '')),
      'role' => trim((string) ($_GET['role'] ?? '')),
      'status' => trim((string) ($_GET['status'] ?? '')),
    ];
    $sort = (string) ($_GET['sort'] ?? 'id');
    $dir = (string) ($_GET['dir'] ?? 'desc');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $result = $this->userService->searchUsers($filters, $page, $perPage, $sort, $dir);

    $this->renderView('admin/users', [
      'pageTitle' => 'Użytkownicy | Admin Panel | Examly',
      'pageDescription' => 'Lista użytkowników w panelu CMS Examly.',
      'overview' => $this->adminService->getOverview(),
      'users' => $result['items'],
      'filters' => $filters,
      'sort' => $sort,
      'dir' => $dir,
      'pagination' => $result,
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
      'pageTitle' => 'Użytkownik #' . $userId . ' | Admin Panel | Examly',
      'pageDescription' => 'Szczegóły użytkownika w panelu CMS Examly.',
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
    if ($userId === $currentAdminId && $newRole === 'user' && $this->userService->countActiveAdmins() <= 1) {
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

    if ($this->userService->updateUserRole($userId, $newRole)) {
      $this->auditService->logRoleChange(
        $currentAdminId,
        $userId,
        [
          'role' => $user['role'] ?? 'user',
        ],
        [
          'role' => $newRole,
        ]
      );

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

      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Rola użytkownika została zaktualizowana.'
      ];
      header('Location: ' . $redirectUrl);
      exit();
    } else {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Nie udało się zapisać zmian.'
      ];
      header('Location: ' . url('admin/uzytkownicy/' . $userId));
      exit();
    }
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
      'pageTitle' => 'Egzaminy | Admin Panel | Examly',
      'pageDescription' => 'Zarządzanie egzaminami w panelu CMS Examly.',
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
      'pageTitle' => 'Egzamin ' . ($exam['code'] ?? ('#' . $examId)) . ' | Admin Panel | Examly',
      'pageDescription' => 'Szczegóły egzaminu w panelu CMS Examly.',
      'exam' => $exam,
      'questions' => $this->examService->getExamQuestions($examId, 200),
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Nieprawidłowy token bezpieczeństwa.',
      ];
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

    if ((int) ($exam['is_active'] ?? 1) === 0) {
      $_SESSION['flash_message'] = [
        'type' => 'info',
        'text' => 'Egzamin jest już nieaktywny.',
      ];
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    }

    if (!$this->db->beginTransaction()) {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Nie udało się rozpocząć transakcji.',
      ];
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    }

    try {
      $oldValue = [
        'is_active' => (int) ($exam['is_active'] ?? 1),
      ];
      $newValue = [
        'is_active' => 0,
      ];

      if (!$this->examService->deactivateExam($examId)) {
        $this->db->rollBack();
        $_SESSION['flash_message'] = [
          'type' => 'error',
          'text' => 'Nie udało się dezaktywować egzaminu.',
        ];
        header('Location: ' . url('admin/egzaminy/' . $examId));
        exit();
      }

      if (!$this->auditService->logExamUpdated(
        $this->currentUser->getId(),
        $examId,
        $oldValue,
        $newValue
      )) {
        $this->db->rollBack();
        $_SESSION['flash_message'] = [
          'type' => 'error',
          'text' => 'Nie udało się zapisać wpisu audytowego.',
        ];
        header('Location: ' . url('admin/egzaminy/' . $examId));
        exit();
      }

      $this->db->commit();
      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Egzamin został zdezaktywowany.',
      ];
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminController] deactivateExam: ' . $e->getMessage());
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Wystąpił nieoczekiwany błąd podczas dezaktywacji.',
      ];
      header('Location: ' . url('admin/egzaminy/' . $examId));
      exit();
    }
  }

  /**
   * Lista pytań.
   */
  public function questions(): void
  {
    $filters = [
      'query' => trim((string) ($_GET['q'] ?? '')),
      'exam_id' => trim((string) ($_GET['exam_id'] ?? '')),
      'status' => trim((string) ($_GET['status'] ?? '')),
    ];
    $sort = (string) ($_GET['sort'] ?? 'id');
    $dir = (string) ($_GET['dir'] ?? 'desc');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $result = $this->questionService->searchQuestions($filters, $page, $perPage, $sort, $dir);

    $this->renderView('admin/questions', [
      'pageTitle' => 'Pytania | Admin Panel | Examly',
      'pageDescription' => 'Zarządzanie pytaniami z wersjonowaniem w panelu CMS Examly.',
      'overview' => $this->adminService->getOverview(),
      'questions' => $result['items'],
      'filters' => $filters,
      'sort' => $sort,
      'dir' => $dir,
      'pagination' => $result,
      'exams' => $this->questionService->getExams(),
    ]);
  }

  /**
   * Formularz dodawania pytania.
   */
  public function showCreateQuestionForm(): void
  {
    $this->renderQuestionForm('create', [
      'exam_type_id' => 0,
      'topic_id' => 0,
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
      'pageTitle' => 'Pytanie #' . $questionId . ' | Admin Panel | Examly',
      'pageDescription' => 'Szczegóły pytania w panelu CMS Examly.',
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

    $this->renderView('admin/analytics', [
      'pageTitle' => 'Analityka | Admin Panel | Examly',
      'pageDescription' => 'Panel analityczny produktu Examly dla administratorów.',
      'analytics' => $dashboardData,
      'filters' => $filters,
      'availableRanges' => [
        '7d' => 'Ostatnie 7 dni',
        '30d' => 'Ostatnie 30 dni',
        '90d' => 'Ostatnie 90 dni',
      ],
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
   * Lista logów audytowych administracji.
   */
  public function auditLogs(): void
  {
    $filters = [
      'admin_user_id' => trim((string) ($_GET['admin_user_id'] ?? '')),
      'action' => trim((string) ($_GET['action'] ?? '')),
      'date_from' => trim((string) ($_GET['date_from'] ?? '')),
      'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    ];

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $result = $this->auditService->searchLogs($filters, $page, $perPage);

    $this->renderView('admin/audit_logs', [
      'pageTitle' => 'Audyt administracji | Admin Panel | Examly',
      'pageDescription' => 'Lista ostatnich akcji administracyjnych w panelu CMS Examly.',
      'filters' => $filters,
      'logs' => $result['items'],
      'pagination' => $result,
      'actions' => $this->getAuditActions(),
      'admins' => $result['admins'],
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
      'create_exam' => 'Utworzenie egzaminu',
      'update_exam' => 'Edycja egzaminu',
      'create_question' => 'Utworzenie pytania',
      'update_question' => 'Edycja pytania',
      'deactivate_question' => 'Usunięcie/dezaktywacja pytania',
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
      'pageTitle' => $title . ' | Admin Panel | Examly',
      'pageDescription' => 'Formularz zarządzania egzaminem w panelu CMS Examly.',
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
      'pageTitle' => ($mode === 'edit' ? 'Edycja pytania' : 'Dodawanie pytania') . ' | Admin Panel | Examly',
      'pageDescription' => 'Formularz zarządzania pytaniem w panelu CMS Examly.',
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
   * @return array<string, string>
   */
  private function extractAnalyticsFilters(): array
  {
    return [
      'range' => trim((string) ($_GET['range'] ?? '30d')),
      'from' => trim((string) ($_GET['from'] ?? '')),
      'to' => trim((string) ($_GET['to'] ?? '')),
    ];
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
}
