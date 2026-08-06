<?php

namespace App\Controllers\Admin;

use App\Models\UserModel;
use App\Services\AdminDashboardService;
use App\Services\AdminUserService;

/**
 * Moduł użytkowników panelu admina: lista, szczegóły, role i statusy kont.
 *
 * @package App\Controllers\Admin
 */
class AdminUserController extends AdminBaseController
{
  private AdminDashboardService $adminService;
  private AdminUserService $userService;

  public function __construct()
  {
    parent::__construct();
    $this->adminService = new AdminDashboardService();
    $this->userService = new AdminUserService();
  }

  /**
   * Widok użytkowników.
   */
  public function users(): void
  {
    ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'page' => $page] = $this->extractListParams();
    $result = $this->userService->searchUsers($filters, $page, 20, $sort, $dir);

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
   * Endpoint JSON listy użytkowników (filtry/sort/paginacja bez przeładowania).
   */
  public function search(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      $this->jsonResponse(['ok' => false, 'message' => 'Metoda niedozwolona.'], 405);
    }

    ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'page' => $page] = $this->extractListParams();
    $result = $this->userService->searchUsers($filters, $page, 20, $sort, $dir);

    $this->jsonResponse([
      'ok' => true,
      'html' => $this->renderPartialToString('admin/partials/users_rows', ['users' => $result['items']]),
      'meta' => [
        'total' => $result['total'],
        'page' => $result['page'],
        'perPage' => $result['perPage'],
        'totalPages' => $result['totalPages'],
      ],
    ]);
  }

  /**
   * Akcje zbiorcze na kontach użytkowników.
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
    if (!in_array($action, ['verify', 'deactivate', 'reactivate'], true)) {
      $this->jsonResponse(['ok' => false, 'message' => 'Nieznana akcja zbiorcza.'], 400);
    }

    $ids = array_values(array_unique(array_map('intval', (array) ($payload['ids'] ?? []))));
    $ids = array_slice(array_filter($ids, static fn(int $id): bool => $id > 0), 0, 500);
    if (empty($ids)) {
      $this->jsonResponse(['ok' => false, 'message' => 'Nie zaznaczono żadnych kont.'], 400);
    }

    $result = $this->userService->bulkUserAction($ids, $action, $this->currentUser->getId(), $this->auditService);

    $labels = [
      'verify' => 'zweryfikowano',
      'deactivate' => 'zdezaktywowano',
      'reactivate' => 'reaktywowano',
    ];
    $done = count($result['succeeded']);
    $total = count($ids);

    $this->jsonResponse([
      'ok' => true,
      'succeeded' => $result['succeeded'],
      'failed' => $result['failed'],
      'message' => sprintf('Pomyślnie %s %d z %d kont.', $labels[$action], $done, $total),
    ]);
  }

  /**
   * Wspólna ekstrakcja filtrów listy z query stringa.
   *
   * @return array{filters: array<string, string>, sort: string, dir: string, page: int}
   */
  private function extractListParams(): array
  {
    $verified = trim((string) ($_GET['verified'] ?? ''));
    if ($verified === '1') {
      $verified = 'verified';
    } elseif ($verified === '0') {
      $verified = 'unverified';
    }

    return [
      'filters' => [
        'query' => trim((string) ($_GET['q'] ?? '')),
        'role' => trim((string) ($_GET['role'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'verified' => $verified,
        'auth_provider' => trim((string) ($_GET['auth_provider'] ?? '')),
        'activity' => trim((string) ($_GET['activity'] ?? '')),
      ],
      'sort' => (string) ($_GET['sort'] ?? 'id'),
      'dir' => (string) ($_GET['dir'] ?? 'desc'),
      'page' => max(1, (int) ($_GET['page'] ?? 1)),
    ];
  }

  /**
   * Wyświetla szczegóły użytkownika.
   */
  public function showUser(int $userId): void
  {
    $user = $this->userService->getUserById($userId);

    if (!$user) {
      $this->renderNotFound('Użytkownik nie został znaleziony.');
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
      $this->setFlash('error', 'Nieprawidłowy token bezpieczeństwa.');
      header('Location: ' . url('admin/uzytkownicy/' . $userId));
      exit();
    }

    $newRole = trim((string) ($_POST['role'] ?? ''));
    if (!in_array($newRole, ['user', 'redaktor', 'admin'], true)) {
      $this->setFlash('error', 'Nieprawidłowa rola użytkownika.');
      header('Location: ' . url('admin/uzytkownicy/' . $userId));
      exit();
    }

    $user = $this->userService->getUserById($userId);
    if (!$user) {
      $this->setFlash('error', 'Użytkownik nie istnieje.');
      header('Location: ' . url('admin/uzytkownicy'));
      exit();
    }

    $currentAdminId = $this->currentUser->getId();
    if (
      ($user['role'] ?? 'user') === 'admin'
      && (int) ($user['is_active'] ?? 0) === 1
      && $newRole !== 'admin'
      && $this->userService->countActiveAdmins() <= 1
    ) {
      $this->setFlash('error', 'Nie możesz odebrać roli jedynemu administratorowi.');
      header('Location: ' . url('admin/uzytkownicy/' . $userId));
      exit();
    }

    if (($user['role'] ?? 'user') === $newRole) {
      $this->setFlash('info', 'Rola użytkownika już jest ustawiona tak samo.');
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
        $freshUser = (new UserModel())->getUserById($currentAdminId);
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
      error_log('[AdminUserController] updateUserRole: ' . $e->getMessage());
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

  private function changeUserState(int $userId, string $action): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->redirectAfterUserAction($userId);
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->respondUserAction($userId, 'error', 'Nieprawidłowy token bezpieczeństwa.');
    }

    $user = $this->userService->getUserById($userId);
    if (!$user || !empty($user['deleted_at'])) {
      if ($this->wantsJson()) {
        $this->jsonResponse(['ok' => false, 'type' => 'error', 'message' => 'Użytkownik nie istnieje lub konto zostało usunięte.'], 404);
      }
      $this->setFlash('error', 'Użytkownik nie istnieje lub konto zostało usunięte.');
      header('Location: ' . url('admin/uzytkownicy'));
      exit();
    }

    $currentAdminId = $this->currentUser->getId();
    if ($action === 'deactivate' && $userId === $currentAdminId) {
      $this->respondUserAction($userId, 'error', 'Nie możesz zdezaktywować własnego konta.');
    }

    if (
      $action === 'deactivate'
      && ($user['role'] ?? 'user') === 'admin'
      && (int) ($user['is_active'] ?? 0) === 1
      && $this->userService->countActiveAdmins() <= 1
    ) {
      $this->respondUserAction($userId, 'error', 'Nie możesz zdezaktywować ostatniego aktywnego administratora.');
    }

    $alreadyApplied = ($action === 'verify' && (int) ($user['is_verified'] ?? 0) === 1)
      || ($action === 'deactivate' && (int) ($user['is_active'] ?? 1) === 0)
      || ($action === 'reactivate' && (int) ($user['is_active'] ?? 0) === 1);
    if ($alreadyApplied) {
      $this->respondUserAction($userId, 'info', 'Wybrany status jest już ustawiony.');
    }

    if (!$this->db->beginTransaction()) {
      $this->respondUserAction($userId, 'error', 'Nie udało się rozpocząć transakcji.');
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
        $this->respondUserAction($userId, 'error', 'Nie udało się zapisać zmiany statusu.');
      }

      $this->db->commit();
      $this->respondUserAction($userId, 'success', $message);
    } catch (\Throwable $e) {
      $this->db->rollBack();
      error_log('[AdminUserController] changeUserState: ' . $e->getMessage());
      $this->respondUserAction($userId, 'error', 'Wystąpił nieoczekiwany błąd podczas zmiany statusu.');
    }
  }

  /**
   * Kończy akcję na koncie: JSON dla żądań AJAX, flash+redirect dla klasycznych.
   */
  private function respondUserAction(int $userId, string $type, string $message): never
  {
    if ($this->wantsJson()) {
      $this->jsonResponse([
        'ok' => $type !== 'error',
        'type' => $type,
        'message' => $message,
      ], $type === 'error' ? 422 : 200);
    }

    $this->setFlash($type, $message);
    $this->redirectAfterUserAction($userId);
  }

  private function redirectAfterUserAction(int $userId): never
  {
    $target = ($_POST['return_to'] ?? '') === 'list'
      ? url('admin/uzytkownicy')
      : url('admin/uzytkownicy/' . $userId);
    header('Location: ' . $target);
    exit();
  }
}
