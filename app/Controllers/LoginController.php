<?php

namespace App\Controllers;

use App\Services\AuthService;

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
    // KROK 1: Próba uwierzytelnienia przez AuthService
    $formData = $_POST;
    $result = $this->auth->login($formData);

    // KROK 2: Obsługa sukcesu logowania
    if ($result['success']) {
      // Bezpieczeństwo: Zapobieganie Session Fixation
      session_regenerate_id(true);
      unset($_SESSION['verify_user_id']);

      header('Location: ' . url('/'));
      exit();
    }

    // KROK 3: Obsługa błędów i specyficznych stanów (np. brak weryfikacji)
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
