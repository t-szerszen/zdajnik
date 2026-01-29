<?php

namespace App\Controllers;

use App\Services\AuthService;

/**
 * Kontroler Strony Logowania.
 *
 * Odpowiada za logikę związaną z wyświetlaniem i obsługą formularza logowania.
 * Działa jako "klej" między żądaniem HTTP użytkownika, serwisem uwierzytelniania
 * a widokiem HTML. Dziedziczy po `BaseController`, aby mieć dostęp do
 * globalnego stanu aplikacji (np. statusu logowania).
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */
class LoginController extends BaseController
{
  /**
   * Serwis uwierzytelniania, który zawiera główną logikę biznesową logowania.
   * @var AuthService
   */
  private AuthService $auth;

  /**
   * Konstruktor Kontrolera Logowania.
   * Wywołuje konstruktor klasy nadrzędnej (`BaseController`) w celu
   * inicjalizacji sesji i sprawdzenia statusu logowania, a następnie
   * tworzy instancję serwisu `AuthController`.
   */
  public function __construct()
  {
    parent::__construct();
    $this->requireGuest();
    $this->auth = new AuthService();
  }

  /**
   * Wyświetla formularz logowania.
   */
  public function showLoginForm(): void
  {
    $this->renderView('login', [
      'errors' => [],
      'formData' => [],
    ]);
  }

  /**
   * Przetwarza dane logowania (POST).
   */
  public function processLogin(): void
  {
    $formData = $_POST;
    $result = $this->auth->login($formData);

    if ($result['success']) {
      session_regenerate_id(true);
      unset($_SESSION['verify_user_id']);
      header('Location: ' . url('/'));
      exit();
    }

    $errors = $result['errors'] ?? [];
    if (isset($result['error_type']) && $result['error_type'] === 'not_verified') {
      $resendLink = '<a href="' . url('autoryzacja-email?send=true') . '">Kliknij tutaj, aby wysłać link weryfikacyjny ponownie.</a>';
      $errors[] = 'Konto nie zostało jeszcze zweryfikowane. ' . $resendLink;
    }

    $this->renderView('login', [
      'errors' => $errors,
      'formData' => $formData,
    ]);
  }
}
