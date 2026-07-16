<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\TokenService;
use App\Services\Mailer;

/**
 * Kontroler Procesu Rejestracji i Weryfikacji Konta.
 *
 * Klasa odpowiada za pełny cykl zakładania konta przez użytkownika lokalnego:
 * 1. Prezentacja i walidacja formularza rejestracyjnego.
 * 2. Zarządzanie procesem weryfikacji adresu e-mail (Double Opt-In).
 * 3. Obsługa ponownej wysyłki linków aktywacyjnych z uwzględnieniem cooldownu.
 * 4. Komunikacja z serwisami uwierzytelniania, tokenów oraz poczty e-mail.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class RegisterController extends BaseController
{
  /** @var AuthService Serwis zawierający główną logikę biznesową rejestracji */
  private AuthService $auth;

  /** @var UserModel Model operacji na danych użytkowników */
  private UserModel $userModel;

  /** @var TokenService Serwis generujący bezpieczne tokeny weryfikacyjne */
  private TokenService $tokenService;

  /** @var Mailer Serwis do wysyłki powiadomień systemowych e-mail */
  private Mailer $mailer;

  /**
   * Inicjalizuje kontroler rejestracji i wymagane zależności.
   * 
   * Dostęp do kontrolera mają wyłącznie osoby niezalogowane (requireGuest).
   */
  public function __construct()
  {
    parent::__construct();
    $this->requireGuest();
    $this->auth = new AuthService();
    $this->userModel = new UserModel();
    $this->tokenService = new TokenService();
    $this->mailer = new Mailer();
  }

  /**
   * Wyświetla formularz rejestracji nowego użytkownika.
   * 
   * @return void
   */
  public function showRegistrationForm(): void
  {
    $this->renderView('register', [
      'errors' => [],
      'formData' => [],
    ]);
  }

  /**
   * Przetwarza dane przesłane przez formularz rejestracji (POST).
   *
   * Metoda wykonuje następujące kroki:
   * 1. Przekazanie danych do serwisu AuthService w celu walidacji i zapisu.
   * 2. Reakcja na pomyślną rejestrację (przekierowanie do strony weryfikacji).
   * 3. Obsługa błędów walidacji i powrót do widoku z komunikatami.
   *
   * @return void
   */
  public function processRegistration(): void
  {
    // KROK 1: Próba rejestracji przez serwis AuthService
    $formData = $_POST;
    $result = $this->auth->register($formData);

    // KROK 2: Obsługa sukcesu operacji
    if ($result['success']) {
      // Przekierowanie do strony informującej o wysłanym e-mailu
      header('Location: ' . url('autoryzacja-email?send=true'));
      exit();
    }

    // KROK 3: Obsługa błędów (np. zajęty e-mail, słabe hasło)
    $this->renderView('register', [
      'errors' => $result['errors'] ?? [],
      'formData' => $formData,
    ]);
  }

  /**
   * Wyświetla stronę informującą o konieczności weryfikacji / oczekiwaniu na e-mail.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Weryfikacja kontekstu sesji (czy użytkownik jest w procesie rejestracji).
   * 2. Opcjonalna wysyłka e-maila przy pierwszym wejściu (?send=true).
   * 3. Sprawdzenie, czy konto nie zostało już zweryfikowane (np. w innej karcie).
   * 4. Obliczenie pozostałego czasu blokady ponownej wysyłki (cooldown).
   * 5. Renderowanie widoku instrukcji weryfikacji.
   *
   * @return void
   */
  public function showVerificationPage(): void
  {
    // KROK 1: Sprawdzenie uprawnień do przebywania na stronie
    if (!isset($_SESSION['verify_user_id'])) {
      header('Location: ' . url('rejestracja'));
      exit();
    }

    // KROK 2: Automatyczna wysyłka przy inicjalizacji procesu
    if (isset($_GET['send']) && $_GET['send'] === 'true') {
      $this->handleSendVerificationEmail();
    }

    // KROK 3: Weryfikacja bieżącego statusu konta w bazie
    $user = $this->userModel->getUserById($_SESSION['verify_user_id']);

    if ($user && $user->isVerified()) {
      unset($_SESSION['verify_user_id'], $_SESSION['email_sent']);
      $_SESSION['flash_message'] = [
        'type' => 'info',
        'text' => 'Twoje konto jest już aktywne. Możesz się zalogować.',
      ];
      header('Location: ' . url('logowanie'));
      exit();
    }

    // KROK 4: Obliczenie cooldownu dla przycisku "Wyślij ponownie"
    $remainingCooldown = 0;
    if (isset($_SESSION['email_sent'])) {
      $elapsed = time() - $_SESSION['email_sent'];
      $remainingCooldown = max(0, 60 - $elapsed);
    }

    // KROK 5: Pobranie i wyczyszczenie komunikatów Flash
    $flashMessage = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);

    // KROK 6: Renderowanie widoku
    $this->renderView('verify_email', [
      'flashMessage' => $flashMessage,
      'remaining' => $remainingCooldown,
    ]);
  }

  /**
   * Finalizuje wysyłkę (lub ponowną wysyłkę) wiadomości weryfikacyjnej.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Weryfikacja obecności identyfikatora użytkownika w sesji.
   * 2. Kontrola Rate-Limit (zabezpieczenie przed spamem e-mailowym).
   * 3. Generowanie unikalnego tokena bezpieczeństwa.
   * 4. Przygotowanie treści i wysyłka e-maila przez serwis Mailer.
   * 5. Ustawienie komunikatów Flash i przekierowanie zwrotne.
   *
   * @return void
   */
  public function handleSendVerificationEmail(): void
  {
    // KROK 1: Walidacja stanu sesji
    if (!isset($_SESSION['verify_user_id'])) {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Sesja wygasła. Zaloguj się lub zarejestruj ponownie.',
      ];
      header('Location: ' . url('logowanie'));
      exit();
    }

    // KROK 2: Kontrola częstotliwości wysyłek (Cooldown 60s)
    if (isset($_SESSION['email_sent']) && time() - $_SESSION['email_sent'] < 60) {
      $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Kolejny e-mail można wysłać dopiero po minucie.'];
      header('Location: ' . url('autoryzacja-email'));
      exit();
    }

    $user = $this->userModel->getUserById($_SESSION['verify_user_id']);
    if (!$user || $user->isVerified()) {
      unset($_SESSION['verify_user_id']);
      $_SESSION['flash_message'] = ['type' => 'info', 'text' => 'Twoje konto jest już aktywne. Możesz się zalogować.'];
      header('Location: ' . url('logowanie'));
      exit();
    }

    // KROK 3: Generowanie tokenu bezpieczeństwa (Double Opt-In)
    $tokenData = $this->tokenService->generateToken($user->getId(), 'email_verify');
    $token = $tokenData['token'];
    $verifyLink = url("weryfikacja?token=$token");

    // KROK 4: Składanie treści i wysyłka e-maila
    if ($this->mailer->sendAccountVerificationEmail($user->getEmail(), $user->getFullName(), $verifyLink)) {
      // Sukces wysyłki
      $_SESSION['email_sent'] = time();
      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => "Nowa wiadomość weryfikacyjna została wysłana na adres {$user->getEmail()}.",
      ];
    } else {
      // Porażka wysyłki (problem z serwerem SMTP/API)
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Wystąpił błąd podczas wysyłania e-maila. Spróbuj ponownie później.',
      ];
    }

    // KROK 5: Przekierowanie do strony statusu
    header('Location: ' . url('autoryzacja-email'));
    exit();
  }
}
