<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\CsrfService;
use App\Services\ThrottleService;

/**
 * Kontroler Logowania.
 *
 * Klasa zarządza procesem uwierzytelniania użytkowników lokalnych (login/hasło).
 * Odpowiada za wyświetlanie formularza wejściowego oraz przetwarzanie prób logowania,
 * w tym obsługę błędów autoryzacji i przekierowania po pomyślnym zalogowaniu.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class LoginController extends BaseController
{
  /** Okno limitu nieudanych logowań (s). */
  private const THROTTLE_WINDOW = 900;

  /** Nieudane logowania z jednego IP w oknie (z zapasem na szkolne sieci za NAT-em). */
  private const MAX_ATTEMPTS_PER_IP = 30;

  /** Nieudane logowania na jeden adres e-mail w oknie. */
  private const MAX_ATTEMPTS_PER_EMAIL = 8;

  /** @var AuthService Serwis zawierający główną logikę biznesową uwierzytelniania */
  private AuthService $auth;

  /**
   * Inicjalizuje kontroler logowania.
   * 
   * Ustawia wymaganie bycia gościem (requireGuest) oraz tworzy instancję 
   * serwisu AuthService.
   */
  public function __construct()
  {
    parent::__construct();
    // Zabezpieczenie: Zalogowani użytkownicy nie mają dostępu do strony logowania
    $this->requireGuest();
    $this->auth = new AuthService();
  }

  /**
   * Wyświetla formularz logowania.
   * 
   * @return void
   */
  public function showLoginForm(): void
  {
    $this->renderView('login', [
      'errors' => [],
      'formData' => [],
    ]);
  }

  /**
   * Przetwarza dane przesłane przez formularz logowania (POST).
   *
   * Metoda wykonuje następujące kroki:
   * 1. Przekazanie danych formularza do serwisu AuthService w celu weryfikacji.
   * 2. Reakcja na pomyślne uwierzytelnienie (regeneracja sesji, przekierowanie).
   * 3. Obsługa specyficznych stanów konta (np. brak weryfikacji e-mail).
   * 4. Powrót do widoku logowania z listą błędów w przypadku niepowodzenia.
   *
   * @return void
   */
  public function processLogin(): void
  {
    $formData = $_POST;
    $email = is_string($formData['email'] ?? null) ? $formData['email'] : '';
    $throttle = new ThrottleService();
    $ip = ThrottleService::clientIp();

    // KROK 0: CSRF i limit prób (ochrona przed zgadywaniem haseł) - po IP
    // i po adresie e-mail, w oknie 15 minut.
    if (!(new CsrfService())->validate($formData['csrf_token'] ?? null)) {
      $this->renderView('login', [
        'errors' => ['Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.'],
        'formData' => $formData,
      ]);
      return;
    }

    if (
      $throttle->tooManyAttempts('login_ip', $ip, self::MAX_ATTEMPTS_PER_IP, self::THROTTLE_WINDOW)
      || ($email !== '' && $throttle->tooManyAttempts('login_email', $email, self::MAX_ATTEMPTS_PER_EMAIL, self::THROTTLE_WINDOW))
    ) {
      $this->renderView('login', [
        'errors' => ['Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za kilkanaście minut albo zresetuj hasło.'],
        'formData' => $formData,
      ]);
      return;
    }

    // KROK 1: Próba uwierzytelnienia przez AuthService
    $result = $this->auth->login($formData);

    // KROK 2: Obsługa sukcesu logowania
    if ($result['success']) {
      if ($email !== '') {
        $throttle->clear('login_email', $email);
      }

      // Bezpieczeństwo: Zapobieganie Session Fixation
      session_regenerate_id(true);
      unset($_SESSION['verify_user_id']);

      header('Location: ' . url('/'));
      exit();
    }

    // KROK 3: Obsługa błędów i specyficznych stanów (np. brak weryfikacji)
    $throttle->hit('login_ip', $ip, self::THROTTLE_WINDOW);
    if ($email !== '') {
      $throttle->hit('login_email', $email, self::THROTTLE_WINDOW);
    }

    $errors = $result['errors'] ?? [];
    if (isset($result['error_type']) && $result['error_type'] === 'not_verified') {
      $resendLink = '<a href="' . url('autoryzacja-email?send=true') . '">Kliknij tutaj, aby wysłać link weryfikacyjny ponownie.</a>';
      $errors[] = 'Twoje konto nie zostało jeszcze zweryfikowane. ' . $resendLink;
    }

    // KROK 4: Powrót do widoku z błędami
    $this->renderView('login', [
      'errors' => $errors,
      'formData' => $formData,
    ]);
  }
}
