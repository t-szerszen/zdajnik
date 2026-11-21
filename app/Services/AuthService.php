<?php

namespace App\Services;

use App\Core\Database;
use App\Models\UserModel;
use App\Models\User;
use App\Services\Validator;
use App\Services\AnalyticsService;

/**
 * Serwis Uwierzytelniania (AuthService).
 *
 * Centralna usługa orkiestrująca wysokopoziomowe procesy bezpieczeństwa:
 * - Rejestracja kont lokalnych z automatyczną inicjalizacją procesu weryfikacji.
 * - Logowanie tradycyjne (Email/Password) z obsługą Soft Delete i reaktywacji.
 * - Zarządzanie stanem sesji użytkownika i kontrola statusu weryfikacji e-mail.
 *
 * Klasa łączy logikę walidacji (Validator) z operacjami bazodanowymi (UserModel), 
 * izolując kontrolery od detali implementacyjnych procesu auth.
 *
 * @version 1.3.0
 * @author Tobiasz Szerszeń
 * @package App\Services
 */
class AuthService
{
  /** @var string Komunikat dla konta zablokowanego przez administratora */
  public const DEACTIVATED_MESSAGE = 'To konto zostało zablokowane. Jeśli uważasz, że to pomyłka, skontaktuj się z nami.';

  /** @var Database Instancja połączenia z bazą dancyh */
  private Database $db;

  /** @var UserModel Model operacji na danych użytkowników */
  private UserModel $userModel;

  /** @var Validator Serwis walidujący reguły biznesowe danych wejściowych */
  private Validator $validator;

  /** @var User|null Obiekt aktualnie uwierzytelnionego użytkownika w ramach sesji */
  private ?User $loggedUser = null;

  /** @var AnalyticsService Serwis do śledzenia zdarzeń produktu */
  private AnalyticsService $analyticsService;

  /**
   * Inicjalizuje serwis autoryzacji i wymagane komponenty.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
    $this->userModel = new UserModel();
    $this->validator = new Validator();
    $this->analyticsService = new AnalyticsService();
  }

  /**
   * Zarządza kompletnym procesem rejestracji nowego użytkownika.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Detekcja błędów w danych formularza (Validator).
   * 2. Formatowanie danych (trim, sanitization) przed zapisem.
   * 3. Próba utworzenia rekordu w bazie danych (UserModel).
   * 4. Inicjalizacja sesji weryfikacyjnej (verify_user_id) dla procesu Double Opt-In.
   *
   * @param array<string, string> $formData Surowe dane z formularza rejestracji.
   * @return array<string, mixed> Wynik operacji [success => bool, errors => array].
   */
  public function register(array $formData): array
  {
    // KROK 1: Walidacja reguł biznesowych (np. unikalność e-mail, siła hasła)
    $errors = $this->validator->validateRegistrationData($formData);

    if (!empty($errors)) {
      $this->analyticsService->trackValidationError(null, 'registration', 'invalid_form', [
        'count' => count($errors),
      ]);
      return ['success' => false, 'errors' => $errors];
    }

    // KROK 2: Przygotowanie paczki danych do zapisu
    $userData = [
      'first_name' => trim($formData['first_name']),
      'last_name' => trim($formData['last_name']),
      'email' => trim($formData['email']),
      'password' => $formData['password'],
    ];

    // KROK 3: Fizyczna rejestracja w bazie
    $success = $this->userModel->register($userData);

    // KROK 4: Finalizacja etapu rejestracji i przygotowanie do weryfikacji e-mail
    if ($success) {
      // Zapamiętujemy ID użytkownika w sesji, aby umożliwić wysyłkę e-maila aktywacyjnego
      $newUserId = $this->db->lastInsertId();
      $_SESSION['verify_user_id'] = $newUserId;

      $this->analyticsService->trackEvent('registration', $newUserId ? (int) $newUserId : null, [
        'auth_provider' => 'local',
      ]);

      return ['success' => true, 'errors' => []];
    }

    $this->analyticsService->trackValidationError(null, 'registration', 'save_failed');
    return ['success' => false, 'errors' => ['Wystąpił nieoczekiwany błąd serwera podczas rejestracji.']];
  }

  /**
   * Zarządza procesem logowania użytkownika lokalnego.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Wstępna weryfikacja obecności wymaganych pól.
   * 2. Próba uwierzytelnienia (UserModel) - obsługa zahashowanych haseł i reaktywacji kont.
   * 3. Sprawdzenie statusu weryfikacji e-mail.
   * 4. Inicjalizacja sesji uwierzytelnionej ($_SESSION['user']).
   *
   * @param array<string, string> $formData Dane logowania (email, password).
   * @return array<string, mixed> Wynik z opcjonalnym error_type dla specyficznych błędów.
   */
  public function login(array $formData): array
  {
    // KROK 1: Walidacja wstępna (obecność pól)
    $email = $formData['email'] ?? '';
    $password = $formData['password'] ?? '';

    if (empty($email) || empty($password)) {
      $this->analyticsService->trackValidationError(null, 'login', 'missing_credentials');
      return ['success' => false, 'errors' => ['Podaj adres e-mail oraz hasło.']];
    }

    // KROK 2: Proces uwierzytelnienia w bazie danych
    $user = $this->userModel->login($formData);

    if ($user instanceof User) {
      // KROK 3a: Konto zablokowane przez administratora
      // (konta w trakcie samodzielnego usuwania są reaktywowane już w modelu).
      if (!$user->isActive()) {
        $this->analyticsService->trackValidationError($user->getId(), 'login', 'account_deactivated');
        return ['success' => false, 'errors' => [self::DEACTIVATED_MESSAGE]];
      }

      // KROK 3b: Kontrola bezpieczeństwa - weryfikacja Double Opt-In
      if (!$user->isVerified()) {
        // Blokada dostępu do czasu potwierdzenia adresu e-mail
        $_SESSION['verify_user_id'] = $user->getId();
        $this->analyticsService->trackValidationError($user->getId(), 'login', 'email_not_verified');
        return ['success' => false, 'error_type' => 'not_verified'];
      }

      // KROK 4: Ustanowienie sesji zalogowanej
      $this->loggedUser = $user;
      $_SESSION['user'] = $this->loggedUser;

      $this->analyticsService->trackLogin($user, $_SERVER);

      return ['success' => true];
    }

    // KROK 5: Fallback dla błędnych poświadczeń
    $this->analyticsService->trackValidationError(null, 'login', 'invalid_credentials');
    return ['success' => false, 'errors' => ['Nieprawidłowy adres e-mail lub hasło.']];
  }
}
