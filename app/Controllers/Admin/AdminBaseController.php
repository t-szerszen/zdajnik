<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\ErrorController;
use App\Core\Database;
use App\Models\UserModel;
use App\Services\AdminAuditService;
use App\Services\CsrfService;

/**
 * Wspólna baza kontrolerów panelu admina.
 *
 * Wymusza autoryzację (domyślnie pełny admin), udostępnia zależności
 * współdzielone przez wszystkie moduły (DB, audyt, CSRF) oraz helpery
 * odpowiedzi: flash+redirect dla klasycznych formularzy i JSON dla AJAX.
 *
 * @package App\Controllers\Admin
 */
abstract class AdminBaseController extends BaseController
{
  protected Database $db;
  protected AdminAuditService $auditService;
  protected CsrfService $csrfService;

  public function __construct()
  {
    parent::__construct();
    $this->authorize();
    $this->db = Database::getInstance();
    $this->auditService = new AdminAuditService();
    $this->csrfService = new CsrfService();
  }

  /**
   * Bramka dostępu do modułu — kontrolery treści mogą ją nadpisać
   * łagodniejszym strażnikiem, pozostałe dziedziczą pełny wymóg admina.
   */
  protected function authorize(): void
  {
    $this->requireAdmin();
  }

  /**
   * Strażnik dostępu do modułów treści (pytania, arkusze):
   * przepuszcza admina oraz redaktora. Zawsze re-fetchuje użytkownika
   * z bazy (nie ufa samej sesji), analogicznie do requireAdmin().
   */
  protected function requireContentManager(): void
  {
    $this->requireAuth();

    $freshUser = (new UserModel())->getUserById($this->currentUser->getId());

    if ($freshUser === null || $freshUser->canManageContent() === false) {
      http_response_code(403);
      $errorController = new ErrorController();
      $errorController->showError(403);
      exit();
    }

    $this->currentUser = $freshUser;
    $_SESSION['user'] = $freshUser;
  }

  protected function setFlash(string $type, string $text): void
  {
    $_SESSION['flash_message'] = [
      'type' => $type,
      'text' => $text,
    ];
  }

  /**
   * Czy żądanie oczekuje odpowiedzi JSON (fetch z panelu).
   */
  protected function wantsJson(): bool
  {
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

    return $requestedWith === 'xmlhttprequest'
      || str_contains($accept, 'application/json');
  }

  /**
   * Walidacja tokenu CSRF przekazanego nagłówkiem X-CSRF-Token (żądania AJAX).
   */
  protected function validateAjaxCsrf(): bool
  {
    return $this->csrfService->validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
  }

  /**
   * Wysyła odpowiedź JSON i kończy żądanie.
   *
   * @param array<string, mixed> $payload
   */
  protected function jsonResponse(array $payload, int $statusCode = 200): never
  {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
  }

  /**
   * Renderuje partial widoku do stringa (dla odpowiedzi JSON typu "HTML over the wire").
   *
   * @param string $partialPath ścieżka względem views/, bez rozszerzenia
   * @param array<string, mixed> $data
   */
  protected function renderPartialToString(string $partialPath, array $data = []): string
  {
    extract($data);
    ob_start();
    include __DIR__ . '/../../../views/' . $partialPath . '.php';
    return (string) ob_get_clean();
  }

  /**
   * Renderuje stronę 404 panelu z komunikatem.
   */
  protected function renderNotFound(string $message): void
  {
    http_response_code(404);
    $errorController = new ErrorController();
    $errorController->showError(404, $message);
  }

  /**
   * Zwraca słownik akcji audytowych.
   *
   * @return array<string, string>
   */
  protected function getAuditActions(): array
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
      'update_topic' => 'Edycja tematu (kategorii)',
      'create_question' => 'Utworzenie pytania',
      'update_question' => 'Edycja pytania',
      'activate_question' => 'Aktywacja pytania',
      'deactivate_question' => 'Dezaktywacja pytania',
      'update_report_status' => 'Zmiana statusu zgłoszenia błędu',
    ];
  }
}
