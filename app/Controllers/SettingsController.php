<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\TokenService;
use App\Services\Mailer;
use App\Services\Validator;

class SettingsController extends BaseController
{
  private UserModel $userModel;
  private TokenService $tokenService;
  private Mailer $mailer;
  private Validator $validator;

  public function __construct()
  {
    parent::__construct();
    $this->requireAuth();
    $this->userModel = new UserModel();
    $this->tokenService = new TokenService();
    $this->mailer = new Mailer();
    $this->validator = new Validator();
  }

  /**
   * Główny punkt wejścia dla strony ustawień (GET).
   */
  public function index(?string $action = null): void
  {
    $this->renderSettingsView($action);
  }

  /**
   * Procesor akcji POST - wywoływany przez Router dla /ustawienia/{action}
   */
  public function handleAction(string $action): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->index();
      return;
    }

    match ($action) {
      'change_name'     => $this->processNameChange(),
      'change_password' => $this->processPasswordChange(),
      'change_email'    => $this->processEmailChange(),
      default           => $this->redirectWithFlash('error', ['Nieznana akcja.'], 'profile')
    };
  }

  /**
   * Obsługuje żądanie usunięcia konta (Soft Delete).
   * Wywoływane przez POST /usun-konto
   */
  public function handleAccountDeletion(): void
  {
    $password = $_POST['password'] ?? '';
    $isGoogleUser = ($this->currentUser->getAuthProvider() === 'google');

    // 1. Walidacja bezpieczeństwa
    if (!$isGoogleUser && !$this->userModel->checkPassword($this->currentUser->getId(), $password)) {
      $this->redirectWithFlash('error', ['Podane hasło jest nieprawidłowe.'], 'delete');
    }

    // 2. Wykonanie operacji
    if ($this->userModel->deleteUser($this->currentUser->getId())) {
      $this->terminateSession();

      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Konto zostało pomyślnie usunięte. Przykro nam, że odchodzisz.'
      ];

      header('Location: ' . url('/'));
      exit();
    }

    $this->redirectWithFlash('error', ['Wystąpił błąd podczas usuwania konta.'], 'delete');
  }

  private function processNameChange(): void
  {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = $this->validator->validateNames($firstName, $lastName);

    if ($this->currentUser->getAuthProvider() === 'local') {
      if (empty($password)) {
        $errors[] = 'Musisz podać hasło, aby zatwierdzić zmiany.';
      } elseif (!$this->userModel->checkPassword($this->currentUser->getId(), $password)) {
        $errors[] = 'Podane hasło jest nieprawidłowe.';
      }
    }

    if (!empty($errors)) {
      $this->redirectWithFlash('error', $errors, 'profile');
    }

    if ($this->userModel->updateName($this->currentUser->getId(), $firstName, $lastName)) {
      $this->refreshSessionUser();
      $this->redirectWithFlash('success', 'Twoje dane zostały zaktualizowane.', 'profile');
    }

    $this->redirectWithFlash('error', ['Błąd zapisu danych.'], 'profile');
  }

  private function processPasswordChange(): void
  {
    if ($this->currentUser->getAuthProvider() === 'google') {
      $this->redirectWithFlash('error', ['Konto Google nie posiada hasła.'], 'password');
    }

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_new_password'] ?? '';

    if (!$this->userModel->checkPassword($this->currentUser->getId(), $current)) {
      $this->redirectWithFlash('error', ['Obecne hasło jest nieprawidłowe.'], 'password');
    }

    $errors = $this->validator->validatePasswordStrength($new, $confirm);
    if ($current === $new) $errors[] = 'Nowe hasło musi różnić się od obecnego.';

    if (!empty($errors)) {
      $this->redirectWithFlash('error', $errors, 'password');
    }

    if ($this->userModel->updatePassword($this->currentUser->getId(), $new)) {
      $this->redirectWithFlash('success', 'Hasło zostało zmienione.', 'password');
    }
  }

  private function processEmailChange(): void
  {
    if ($this->isEmailCooldownActive()) {
      $this->redirectWithFlash('error', ['Odczekaj minutę przed kolejną próbą.'], 'email');
    }

    $newEmail = trim($_POST['new_email'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = $this->validateEmailChange($newEmail, $password);
    if (!empty($errors)) {
      $this->redirectWithFlash('error', $errors, 'email');
    }

    $token = $this->tokenService->generateToken($this->currentUser->getId(), 'email_change', $newEmail)['token'];
    $link = url("weryfikacja?token=$token");
    $body = "<p>Potwierdź zmianę adresu na $newEmail klikając: <a href='$link'>$link</a></p>";

    if ($this->mailer->send($newEmail, 'Zmiana e-mail - Examly', $body)) {
      $_SESSION['email_change_sent'] = time();
      $this->redirectWithFlash('success', 'Link weryfikacyjny został wysłany.', 'email');
    }

    $this->redirectWithFlash('error', ['Błąd podczas wysyłki e-mail.'], 'email');
  }

  // --- Helpery ---

  /**
   * Czyści sesję po usunięciu konta lub wylogowaniu.
   */
  private function terminateSession(): void
  {
    session_unset();
    session_destroy();
    session_start(); // Startujemy nową, by móc przypisać flash_message
  }

  private function renderSettingsView(?string $action = null): void
  {
    $elapsed = time() - ($_SESSION['email_change_sent'] ?? 0);

    // Mapowanie przyjaznych nazw akcji na zakładki w widoku
    $activeMapping = [
      'change_name'     => 'name',
      'change_password' => 'password',
      'change_email'    => 'email',
    ];

    $activeTab = $_GET['active'] ?? ($activeMapping[$action] ?? 'profile');

    $this->renderView('settings', [
      'activeForm' => $activeTab,
      'emailChangeRemaining' => max(0, 60 - $elapsed)
    ]);
  }

  private function redirectWithFlash(string $type, string|array $content, string $activeTab): void
  {
    $_SESSION['flash_message'] = $type === 'success'
      ? ['type' => 'success', 'text' => $content]
      : ['type' => 'error', 'errors' => (array)$content];

    header('Location: ' . url("ustawienia?active=$activeTab"));
    exit();
  }

  private function refreshSessionUser(): void
  {
    $this->currentUser = $this->userModel->getUserById($this->currentUser->getId());
    $_SESSION['user'] = $this->currentUser;
  }

  private function isEmailCooldownActive(): bool
  {
    return isset($_SESSION['email_change_sent']) && (time() - $_SESSION['email_change_sent'] < 60);
  }

  private function validateEmailChange(string $email, string $password): array
  {
    $errors = [];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Niepoprawny format e-mail.';
    if ($this->userModel->checkEmail($email)) $errors[] = 'Ten e-mail jest już zajęty.';
    if (!$this->userModel->checkPassword($this->currentUser->getId(), $password)) $errors[] = 'Hasło jest nieprawidłowe.';
    return $errors;
  }
}
