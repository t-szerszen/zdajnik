<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\Attempt;
use App\Models\UserProgress;
use App\Services\TokenService;
use App\Services\Mailer;
use App\Services\Validator;

class SettingsController extends BaseController
{
  private UserModel $userModel;
  private Attempt $attemptModel;
  private UserProgress $userProgressModel;
  private TokenService $tokenService;
  private Mailer $mailer;
  private Validator $validator;

  public function __construct()
  {
    parent::__construct();
    $this->requireAuth();
    $this->userModel = new UserModel();
    $this->attemptModel = new Attempt();
    $this->userProgressModel = new UserProgress();
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
      'change_name' => $this->processNameChange(),
      'change_password' => $this->processPasswordChange(),
      'change_email' => $this->processEmailChange(),
      'anonymize_progress' => $this->processProgressAnonymization(),
      default => $this->redirectWithFlash('error', ['Nieznana akcja.'], 'profile')
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

    // 2. Wykonanie operacji - Generowanie tokena weryfikacyjnego
    $tokenData = $this->tokenService->generateToken($this->currentUser->getId(), 'account_deletion');

    if ($tokenData) {
      $link = url('potwierdz-usuniecie?token=' . $tokenData['token']);

      $this->mailer->sendAccountDeletionConfirmationEmail($this->currentUser->getEmail(), $link);

      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Na Twój adres e-mail wysłaliśmy link potwierdzający. Kliknij go, aby dokończyć usuwanie konta.'
      ];
    } else {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Wystąpił błąd podczas generowania tokena. Spróbuj ponownie.'
      ];
    }

    $this->redirectWithFlash('success', 'Sprawdź skrzynkę odbiorczą.', 'delete');
  }

  /**
   * Obsługuje potwierdzenie usunięcia konta z linku email.
   * GET /potwierdz-usuniecie?token=XYZ
   */
  public function confirmDeletion(): void
  {
    $token = $_GET['token'] ?? '';
    // Token service sprawdza czy token nie wygasł (expires_at > NOW())
    $tokenRecord = $this->tokenService->getTokenRecord($token);

    if (!$tokenRecord || $tokenRecord['type'] !== 'account_deletion') {
      // Token nieważny lub nieprawidłowy
      $this->redirectWithFlash('error', ['Link weryfikacyjny wygasł lub jest nieprawidłowy.'], 'profile');
    }

    $userId = $tokenRecord['user_id'];

    // Wykonaj właściwe usuwanie (Soft Delete + Smart Cleanup)
    if ($this->userModel->requestDeletion($userId)) {
      // Posprzątaj tokeny tego typu
      $this->tokenService->deleteTokensForUserByType($userId, 'account_deletion');

      // Wyloguj i przekieruj
      $this->terminateSession();

      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Twoje konto zostało dezaktywowane. Masz 30 dni na anulowanie tej operacji poprzez ponowne zalogowanie.'
      ];

      header('Location: ' . url('/'));
      exit();
    }

    $this->redirectWithFlash('error', ['Wystąpił błąd podczas usuwania konta.'], 'profile');
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
    if ($current === $new)
      $errors[] = 'Nowe hasło musi różnić się od obecnego.';

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

    // Generujemy token i kod
    $tokenData = $this->tokenService->generateToken($this->currentUser->getId(), 'email_change', $newEmail);
    $token = $tokenData['token'];
    $code = $tokenData['code'];

    // Symulacja wysyłki (lub prawdziwa wysyłka) - tutaj wyświetlamy kod w flash message dla UX
    $body = "<p>Twój kod weryfikacyjny zmiany e-maila to: <b>$code</b></p>";
    $this->mailer->send($newEmail, 'Weryfikacja zmiany e-mail - Examly', $body);

    $_SESSION['email_change_sent'] = time();
    $_SESSION['flash_message'] = [
      'type' => 'success',
      'text' => "Na nowy adres e-mail wysłaliśmy kod. Wpisz go poniżej."
    ];

    header('Location: ' . url("zmiana-email?token=$token"));
    exit();
  }

  public function showVerifyEmailForm(): void
  {
    $token = $_GET['token'] ?? null;
    $tokenRecord = $this->tokenService->getTokenRecord($token);

    if (!$token || !$tokenRecord) {
      $this->redirectWithFlash('error', ['Link weryfikacyjny wygasł lub jest nieprawidłowy.'], 'email');
    }

    $this->renderView('reset_email', ['token' => $token]);
  }

  public function handleVerifyEmailRequest(): void
  {
    $token = $_POST['token'] ?? null;
    $authCode = $_POST['auth_code'] ?? '';

    $tokenRecord = $this->tokenService->getTokenRecord($token);

    if (!$tokenRecord) {
      $this->redirectWithFlash('error', ['Sesja wygasła. Spróbuj ponownie.'], 'email');
    }

    $errors = $this->validator->validateAuthCode($authCode, $tokenRecord['auth_code']);

    if (!empty($errors)) {
      $_SESSION['flash_message'] = ['type' => 'error', 'errors' => $errors];
      header('Location: ' . url("zmiana-email?token=$token"));
      exit();
    }

    // Zmiana OK
    $newEmail = $tokenRecord['token_data'];
    if ($this->userModel->updateAndVerifyEmail($this->currentUser->getId(), $newEmail)) {
      $this->tokenService->deleteTokensForUserByType($this->currentUser->getId(), 'email_change');
      $this->refreshSessionUser();
      $this->redirectWithFlash('success', 'Adres e-mail został pomyślnie zmieniony.', 'email');
    } else {
      $this->redirectWithFlash('error', ['Wystąpił błąd podczas aktualizacji adresu e-mail.'], 'email');
    }
  }

  private function processProgressAnonymization(): void
  {
    $examTypeId = $_POST['exam_type_id'] ?? null;
    $password = $_POST['password'] ?? '';
    $isGoogleUser = ($this->currentUser->getAuthProvider() === 'google');

    if (!$examTypeId) {
      $this->redirectWithFlash('error', ['Musisz wybrać egzamin z listy.'], 'progress');
    }

    if (!$isGoogleUser && !$this->userModel->checkPassword($this->currentUser->getId(), $password)) {
      $this->redirectWithFlash('error', ['Podane hasło jest nieprawidłowe.'], 'progress');
    }

    $userId = $this->currentUser->getId();

    // Find exam code for email
    $startedExams = $this->attemptModel->getStartedExamsByUser($userId);
    $examCode = '';
    foreach ($startedExams as $exam) {
      if ((int) $exam['id'] === (int) $examTypeId) {
        $examCode = $exam['code'];
        break;
      }
    }

    if (!$examCode) {
      $this->redirectWithFlash('error', ['Wybrany egzamin nie istnieje lub nie został rozpoczęty.'], 'progress');
    }

    // 2. Generowanie tokena weryfikacyjnego
    $tokenData = $this->tokenService->generateToken($userId, 'progress_anonymization', (string) $examTypeId);

    if ($tokenData) {
      $link = url('potwierdz-anonimizacje?token=' . $tokenData['token']);

      $this->mailer->sendProgressAnonymizationEmail($this->currentUser->getEmail(), $link, $examCode);

      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Na Twój adres e-mail wysłaliśmy link potwierdzający. Kliknij go, aby wyzerować postęp wybranego egzaminu.'
      ];
    } else {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Wystąpił błąd podczas przetwarzania żądania. Spróbuj ponownie.'
      ];
    }

    $this->redirectWithFlash('success', 'Sprawdź skrzynkę odbiorczą.', 'progress');
  }

  /**
   * Obsługuje potwierdzenie z wyzerowaniem postępu z linku email.
   * GET /potwierdz-anonimizacje?token=XYZ
   */
  public function confirmProgressAnonymization(): void
  {
    $token = $_GET['token'] ?? '';
    $tokenRecord = $this->tokenService->getTokenRecord($token);

    if (!$tokenRecord || $tokenRecord['type'] !== 'progress_anonymization') {
      $this->redirectWithFlash('error', ['Link weryfikacyjny wygasł lub jest nieprawidłowy.'], 'profile');
    }

    $userId = (int) $tokenRecord['user_id'];
    $examTypeId = (int) $tokenRecord['token_data'];

    // 1. Zlicz postęp dla decyzji: usuwanie vs anonimizacja
    $answeredCount = $this->userProgressModel->countAnsweredQuestionsForExam($userId, $examTypeId);
    
    // 2. Wykonaj odpowiednią akcję dla prób (attempts)
    if ($answeredCount <= 40) {
      // Przy małej liczbie pytań zakładamy, że to dane testowe/śmieciowe - usuwamy na stałe
      $attemptsResult = $this->attemptModel->deleteAttempts($userId, $examTypeId);
    } else {
      // Powyżej 40 pytań dane są wartościowe statystycznie - anonimizujemy
      $attemptsResult = $this->attemptModel->anonymizeAttempts($userId, $examTypeId);
    }

    // 3. Usuń postęp użytkownika (zawsze usuwamy powiązanie użytkownik-pytanie)
    $progressDeleted = $this->userProgressModel->deleteProgress($userId, $examTypeId);

    if ($progressDeleted !== false && $attemptsResult !== false) {
      $this->tokenService->deleteTokensForUserByType($userId, 'progress_anonymization');
      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Postęp dla wskazanego egzaminu został pomyślnie wyzerowany.'
      ];
      header('Location: ' . url('ustawienia'));
      exit();
    }

    $this->redirectWithFlash('error', ['Wystąpił błąd podczas usuwania postępu.'], 'profile');
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
      'change_name' => 'name',
      'change_password' => 'password',
      'change_email' => 'email',
      'anonymize_progress' => 'progress',
    ];

    $activeTab = $_GET['active'] ?? ($activeMapping[$action] ?? 'profile');

    $startedExams = $this->attemptModel->getStartedExamsByUser($this->currentUser->getId());

    $this->renderView('settings', [
      'activeForm' => $activeTab,
      'emailChangeRemaining' => max(0, 60 - $elapsed),
      'startedExams' => $startedExams
    ]);
  }

  private function redirectWithFlash(string $type, string|array $content, string $activeTab): void
  {
    $_SESSION['flash_message'] = $type === 'success'
      ? ['type' => 'success', 'text' => $content]
      : ['type' => 'error', 'errors' => (array) $content];

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
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
      $errors[] = 'Niepoprawny format e-mail.';
    if ($this->userModel->checkEmail($email))
      $errors[] = 'Ten e-mail jest już zajęty.';
    if (!$this->userModel->checkPassword($this->currentUser->getId(), $password))
      $errors[] = 'Hasło jest nieprawidłowe.';
    return $errors;
  }
}
