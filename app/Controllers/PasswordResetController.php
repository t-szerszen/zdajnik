<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\TokenService;
use App\Services\CsrfService;
use App\Services\ThrottleService;
use App\Services\Mailer;
use App\Services\Validator;

/**
 * Kontroler Resetowania Hasła.
 *
 * Klasa obsługuje pełny przepływ odzyskiwania dostępu do konta przez użytkownika.
 * Obejmuje żądanie zresetowania hasła, wysyłkę bezpiecznych linków i kodów OTP
 * oraz finalną autoryzację i zmianę hasła w bazie danych.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class PasswordResetController extends BaseController
{
  /** @var UserModel Model operacji na użytkownikach */
  private UserModel $userModel;

  /** @var TokenService Serwis zarządzania tokenami bezpieczeństwa */
  private TokenService $tokenService;

  /** @var Validator Serwis walidacji danych wejściowych */
  private Validator $validator;

  /** @var Mailer Serwis do wysyłki wiadomości e-mail */
  private Mailer $mailer;

  /**
   * Inicjalizuje kontroler resetowania hasła i wstrzykuje zależności.
   */
  public function __construct()
  {
    parent::__construct();
    $this->userModel = new UserModel();
    $this->tokenService = new TokenService();
    $this->validator = new Validator();
    $this->mailer = new Mailer();
  }

  /**
   * Wyświetla formularz wprowadzania adresu e-mail do resetu hasła.
   *
   * @return void
   */
  public function showForgotPasswordForm(): void
  {
    $remainingCooldown = 0;
    if (isset($_SESSION['password_reset_sent'])) {
      $elapsed = time() - $_SESSION['password_reset_sent'];
      $remainingCooldown = max(0, 60 - $elapsed);
    }

    $this->renderView('forgot_password', ['remaining' => $remainingCooldown]);
  }

  /**
   * Obsługuje żądanie wysłania linku i kodu resetującego hasło.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Ochrona przed nadmiarowymi żądaniami (60s cooldown).
   * 2. Identyfikacja użytkownika na podstawie adresu e-mail.
   * 3. Generowanie tokena weryfikacyjnego i kodu OTP.
   * 4. Przygotowanie i wysyłka wiadomości e-mail z instrukcją.
   * 5. Wyświetlenie ustandaryzowanego komunikatu (Security Policy).
   *
   * @return void
   */
  public function handleForgotPasswordRequest(): void
  {
    // KROK 1: Kontrola częstotliwości żądań (Rate Limiting)
    if (isset($_SESSION['password_reset_sent']) && time() - $_SESSION['password_reset_sent'] < 60) {
      $this->getAnalyticsService()->trackValidationError(
        null,
        'password_reset_request',
        'cooldown_active'
      );
      $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Kolejny e-mail można wysłać dopiero po minucie.'];
      header('Location: ' . url('reset-hasla'));
      exit();
    }

    // KROK 1b: CSRF i limit w bazie (sesyjny cooldown wyżej omija się bez ciasteczka):
    // 10 próśb na godzinę z jednego IP i 3 na jeden adres e-mail.
    if (!(new CsrfService())->validate($_POST['csrf_token'] ?? null)) {
      $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.'];
      header('Location: ' . url('reset-hasla'));
      exit();
    }

    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $throttle = new ThrottleService();
    $ip = ThrottleService::clientIp();
    if (
      $throttle->tooManyAttempts('reset_ip', $ip, 10, 3600)
      || ($email !== '' && $throttle->tooManyAttempts('reset_email', $email, 3, 3600))
    ) {
      $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Zbyt wiele próśb o reset hasła. Spróbuj ponownie za godzinę.'];
      header('Location: ' . url('reset-hasla'));
      exit();
    }
    $throttle->hit('reset_ip', $ip, 3600);
    if ($email !== '') {
      $throttle->hit('reset_email', $email, 3600);
    }

    // KROK 2: Identyfikacja użytkownika
    $user = $email !== '' ? $this->userModel->getUserByEmail($email) : null;
    $this->getAnalyticsService()->trackPasswordResetRequested($user?->getId());

    if ($user) {
      // KROK 3: Generowanie danych bezpieczeństwa
      $tokenData = $this->tokenService->generateToken($user->getId(), 'password_reset');

      if ($tokenData) {
        $token = $tokenData['token'];
        $code = $tokenData['code'];

        // KROK 4: Składanie i wysyłka wiadomości e-mail
        $resetLink = url("nowe-haslo?token=$token");
        if ($this->mailer->sendPasswordResetEmail($user->getEmail(), $resetLink)) {
          $_SESSION['password_reset_sent'] = time();
        }

        // Zapisz kod do sesji pomocniczo dla testów/ux
        $_SESSION['password_reset_code'] = $code;
      }
    }

    // KROK 5: Wyświetlenie neutralnego komunikatu (zapobieganie User Enumeration)
    $_SESSION['flash_message'] = [
      'type' => 'info',
      'text' => "Jeśli konto o podanym adresie e-mail ($email) istnieje, wysłano na nie instrukcję resetowania hasła."
    ];

    header('Location: ' . url('reset-hasla'));
    exit();
  }

  /**
   * Wyświetla formularz wprowadzania nowego hasła na podstawie tokenu.
   *
   * @return void
   */
  public function showNewPasswordForm(): void
  {
    $token = $_GET['token'] ?? null;
    $tokenRecord = $this->tokenService->getTokenRecord($token, 'password_reset');

    // Walidacja poprawności i daty wygaśnięcia tokenu
    if (!$token || !$tokenRecord || strtotime($tokenRecord['expires_at']) < time()) {
      $this->getAnalyticsService()->trackValidationError(
        null,
        'password_reset',
        'invalid_or_expired_token'
      );
      $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Link do resetowania hasła jest nieprawidłowy lub wygasł.'];
      header('Location: ' . url('logowanie'));
      exit();
    }

    $this->renderView('reset_password', ['token' => $token]);
  }

  /**
   * Przetwarza żądanie ustanowienia nowego hasła.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Walidacja sesji resetowania (token).
   * 2. Weryfikacja kodu bezpieczeństwa (OTP).
   * 3. Sprawdzenie siły nowego hasła.
   * 4. Aktualizacja rekordu w bazie danych i sprzątanie tokenów.
   *
   * @return void
   */
  public function handleNewPasswordRequest(): void
  {
    // KROK 1: Walidacja tokenu weryfikacyjnego
    $token = $_POST['token'] ?? null;
    $tokenRecord = $this->tokenService->getTokenRecord($token, 'password_reset');

    if (!$tokenRecord) {
      $this->getAnalyticsService()->trackValidationError(null, 'password_reset', 'expired_session');
      $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Sesja wygasła.'];
      header('Location: ' . url('logowanie'));
      exit();
    }

    // KROK 2: Weryfikacja kodu bezpieczeństwa (OTP)
    $authErrors = $this->validator->validateAuthCode($_POST['auth_code'] ?? '', $tokenRecord['auth_code'] ?? '');

    // KROK 3: Walidacja zgodności i siły hasła
    $passwordErrors = $this->validator->validatePasswordStrength($_POST['new_password'] ?? '', $_POST['confirm_new_password'] ?? '');

    $errors = array_merge($authErrors, $passwordErrors);

    if (!empty($errors)) {
      $this->getAnalyticsService()->trackValidationError(
        (int) $tokenRecord['user_id'],
        'password_reset',
        'invalid_form',
        ['count' => count($errors)]
      );
      $_SESSION['flash_message'] = ['type' => 'error', 'errors' => $errors];
      header('Location: ' . url("nowe-haslo?token=$token"));
      exit();
    }

    // KROK 4: Aktualizacja hasła i usunięcie zużytych tokenów
    $this->userModel->updatePassword($tokenRecord['user_id'], $_POST['new_password']);
    $this->tokenService->deleteTokensForUserByType($tokenRecord['user_id'], 'password_reset');
    $this->getAnalyticsService()->trackSettingsUpdated(
      (int) $tokenRecord['user_id'],
      'password_reset_completed'
    );

    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Hasło zostało zresetowane!'];
    header('Location: ' . url('logowanie'));
    exit();
  }
}
