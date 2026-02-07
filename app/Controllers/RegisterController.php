<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\TokenService;
use App\Services\Mailer;

/**
 * Kontroler Procesu Rejestracji i Weryfikacji.
 *
 * Zarządza całym przepływem rejestracji nowego użytkownika. Jego odpowiedzialność
 * obejmuje trzy główne etapy:
 * 1. Wyświetlanie i obsługa formularza rejestracyjnego.
 * 2. Prezentacja strony informującej o konieczności weryfikacji adresu e-mail.
 * 3. Obsługa akcji wysłania e-maila weryfikacyjnego.
 *    Działa jako "klej" między żądaniami HTTP, serwisem `AuthController`, a widokami.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */
class RegisterController extends BaseController
{
  /**
   * Serwis uwierzytelniania, używany do logiki rejestracji.
   * @var AuthService
   */
  private AuthService $auth;

  private UserModel $userModel;
  private TokenService $tokenService;
  private Mailer $mailer;

  /**
   * Konstruktor Kontrolera Rejestracji.
   *
   * Wywołuje konstruktor klasy nadrzędnej (`BaseController`) w celu
   * inicjalizacji sesji i globalnego stanu aplikacji, a następnie
   * tworzy instancję serwisu `AuthController`.
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
   * Wyświetla formularz rejestracji.
   */
  public function showRegistrationForm(): void
  {
    $this->renderView('register', [
      'errors' => [],
      'formData' => [],
    ]);
  }

  /**
   * Przetwarza dane rejestracyjne (POST).
   */
  public function processRegistration(): void
  {
    $formData = $_POST;
    $result = $this->auth->register($formData);

    if ($result['success']) {
      header('Location: ' . url('autoryzacja-email?send=true'));
      exit();
    }

    $this->renderView('register', [
      'errors' => $result['errors'] ?? [],
      'formData' => $formData,
    ]);
  }

  /**
   * Endpoint: Wyświetla stronę z informacją o potrzebie weryfikacji e-mail (/autoryzacja-email).
   *
   * Logika działania:
   * 2. Sprawdza, czy użytkownik w bieżącej sesji jest już zweryfikowany. Jeśli tak,
   *    przekierowuje go na stronę logowania z odpowiednim komunikatem.
   * 3. Oblicza czas pozostały do możliwości ponownego wysłania e-maila (cooldown).
   * 4. Pobiera jednorazowe komunikaty (tzw. flash messages) z sesji i od razu je usuwa.
   * 5. Renderuje widok, przekazując do niego wszystkie potrzebne dane (czas, komunikaty).
   *
   * @return void
   */
  public function showVerificationPage(): void
  {
    // Krok 2: Sprawdź, czy w sesji jest ID użytkownika oczekującego na weryfikację.
    // Jeśli nie ma, to znaczy, że użytkownik nie jest w trakcie procesu rejestracji,
    // więc nie ma powodu, by przebywał na tej stronie.
    if (!isset($_SESSION['verify_user_id'])) {
      header('Location: ' . url('rejestracja'));
      exit();
    }

    // Krok 2.5: Jeśli w URL znajduje się flaga ?send=true (np. po rejestracji lub kliknięciu "Wyślij ponownie"),
    // wywołaj metodę wysyłającą e-mail.
    if (isset($_GET['send']) && $_GET['send'] === 'true') {
      $this->handleSendVerificationEmail();
    // Metoda handleSendVerificationEmail wykonuje przekierowanie i exit, więc kod poniżej się nie wykona.
    }

    // Krok 3: Pobierz dane użytkownika i sprawdź, czy w międzyczasie nie zweryfikował już konta
    // (np. klikając w link na innym urządzeniu).
    $user = $this->userModel->getUserById($_SESSION['verify_user_id']);

    // Jeśli konto jest już aktywne, posprzątaj sesję i przekieruj do logowania.
    if ($user && $user->isVerified()) {
      unset($_SESSION['verify_user_id'], $_SESSION['email_sent']);
      $_SESSION['flash_message'] = [
        'type' => 'info',
        'text' => 'Twoje konto jest już aktywne. Możesz się zalogować.',
      ];
      header('Location: ' . url('logowanie'));
      exit();
    }

    // Krok 4: Obliczenie pozostałego czasu do ponownej wysyłki e-maila (cooldown 60s).
    $remainingCooldown = 0;
    if (isset($_SESSION['email_sent'])) {
      $elapsed = time() - $_SESSION['email_sent'];
      $remainingCooldown = max(0, 60 - $elapsed); // Upewnij się, że czas nie jest ujemny.
    }

    // Krok 5: Obsługa jednorazowych komunikatów (flash messages).
    $flashMessage = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']); // Komunikat jest wyświetlany tylko raz.

    // Krok 6: Renderowanie widoku z przekazaniem danych.
    $this->renderView('verify_email', [
      'flashMessage' => $flashMessage,
      'remaining' => $remainingCooldown,
    ]);
  }

  /**
   * Endpoint: Obsługuje wysłanie (lub ponowne wysłanie) e-maila weryfikacyjnego.
   *
   * Ten endpoint jest wywoływany przez przekierowanie i nie renderuje widoku. Jego
   * zadaniem jest wykonanie akcji i ponowne przekierowanie na stronę weryfikacji.
   *
   * Logika działania:
   * 1. Sprawdza, czy w sesji istnieje użytkownik do weryfikacji.
   * 2. Weryfikuje, czy nie upłynął 60-sekundowy limit czasu na ponowną wysyłkę.
   * 3. Pobiera dane użytkownika z bazy i sprawdza, czy nie jest już zweryfikowany.
   * 4. Generuje unikalny token weryfikacyjny.
   * 5. Wysyła e-mail z linkiem weryfikacyjnym.
   * 6. Zapisuje w sesji stosowny komunikat (o sukcesie lub porażce).
   * 7. Zawsze na końcu przekierowuje z powrotem na `/autoryzacja-email`, aby wyświetlić komunikat.
   *
   * @return void
   */
  public function handleSendVerificationEmail(): void
  {
    // Krok 1: Sprawdzenie, czy proces weryfikacji został poprawnie zainicjowany.
    if (!isset($_SESSION['verify_user_id'])) {
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Sesja wygasła. Zaloguj się lub zarejestruj ponownie.',
      ];
      header('Location: ' . url('logowanie'));
      exit();
    }

    // Krok 2: Zabezpieczenie przed spamem - limit czasowy na ponowną wysyłkę.
    if (isset($_SESSION['email_sent']) && time() - $_SESSION['email_sent'] < 60) {
      $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Kolejny e-mail można wysłać dopiero po minucie.'];
      header('Location: ' . url('autoryzacja-email'));
      exit();
    }

    // Krok 3: Pobranie danych użytkownika i weryfikacja jego aktualnego statusu.
    $user = $this->userModel->getUserById($_SESSION['verify_user_id']);
    if (!$user || $user->isVerified()) {
      unset($_SESSION['verify_user_id']); // Sprzątanie sesji.
      $_SESSION['flash_message'] = ['type' => 'info', 'text' => 'Twoje konto jest już aktywne. Możesz się zalogować.'];
      header('Location: ' . url('logowanie'));
      exit();
    }

    // Krok 4: Generowanie tokenu i linku weryfikacyjnego.
    // Odbieramy tablicę i wyciągamy z niej sam token.
    $tokenData = $this->tokenService->generateToken($user->getId(), 'email_verify');
    $token = $tokenData['token'];

    $verifyLink = url("weryfikacja?token=$token");

    // Krok 5: Przygotowanie i wysłanie e-maila.
    $body =
      "<p>Witaj {$user->getFullName()},</p>" .
      '<p>Kliknij poniższy link, aby zweryfikować swój adres e-mail:</p>' .
      "<p><a href='$verifyLink'>$verifyLink</a></p>";

    if ($this->mailer->send($user->getEmail(), 'Weryfikacja adresu e-mail', $body)) {
      // Sukces: zapisz czas wysyłki i komunikat o powodzeniu.
      $_SESSION['email_sent'] = time();
      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => "Nowa wiadomość weryfikacyjna została wysłana na adres {$user->getEmail()}.",
      ];
    }
    else {
      // Porażka: zapisz komunikat o błędzie.
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Wystąpił błąd podczas wysyłania e-maila. Spróbuj ponownie.',
      ];
    }

    // Krok 6: Zawsze przekieruj z powrotem, aby wyświetlić wynik operacji.
    header('Location: ' . url('autoryzacja-email'));
    exit();
  }
}