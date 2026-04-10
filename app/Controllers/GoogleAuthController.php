<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\User;
use Google\Client;
use Google\Service\Oauth2;
use Exception;

/**
 * Kontroler Autoryzacji Google (OAuth 2.0).
 *
 * Klasa obsługuje proces logowania i rejestracji użytkowników za pomocą 
 * zewnętrznego dostawcy tożsamości Google. Zarządza przekierowaniem do 
 * serwera autoryzacji Google, obsługą odpowiedzi zwrotnej (callback), 
 * wymianą kodów na tokeny oraz synchronizacją danych użytkownika z lokalną bazą danych.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class GoogleAuthController extends BaseController
{
  /** @var Client Instancja klienta Google API */
  private Client $googleClient;

  /** @var UserModel Model do operacji na danych użytkownika */
  private UserModel $userModel;

  /**
   * Inicjalizuje kontroler i konfiguruje klienta Google OAuth.
   *
   * Wykorzystuje zmienne środowiskowe ($_ENV) do konfiguracji poświadczeń 
   * klienta API Google. Dostęp do kontrolera mają wyłącznie osoby niezalogowane.
   */
  public function __construct()
  {
    parent::__construct();
    // Zabezpieczenie przed ponownym logowaniem zalogowanych użytkowników
    $this->requireGuest();
    $this->userModel = new UserModel();

    // Konfiguracja parametrów połączenia z Google Cloud Console
    $this->googleClient = new Client();
    $this->googleClient->setClientId($_ENV['GOOGLE_CLIENT_ID']);
    $this->googleClient->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
    $this->googleClient->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
    $this->googleClient->addScope('email');
    $this->googleClient->addScope('profile');
  }

  /**
   * Generuje URL i przekierowuje użytkownika do formularza zgody Google.
   * 
   * @return void
   */
  public function redirectToGoogle(): void
  {
    $authUrl = $this->googleClient->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit();
  }

  /**
   * Obsługuje odpowiedź zwrotną (callback) od serwera Google po autoryzacji.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Walidacja obecności kodu autoryzacyjnego w żądaniu GET.
   * 2. Wymiana kodu na Access Token (Token Dostępu).
   * 3. Pobranie informacji o profilu (ID, e-mail, imię, nazwisko) przez serwis OAuth2.
   * 4. Synchronizacja danych z lokalną bazą (Find or Create User).
   * 5. Inicjalizacja sesji użytkownika w systemie lokalnym.
   * 6. Przekierowanie do panelu głównego.
   *
   * @return void
   */
  public function handleGoogleCallback(): void
  {
    // KROK 1: Walidacja błędów i obecności kodu od Google
    if (isset($_GET['error'])) {
      $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Logowanie przez Google nie powiodło się.'];
      header('Location: ' . url('logowanie'));
      exit();
    }

    if (!isset($_GET['code'])) {
      $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Brak autoryzacji od Google.'];
      header('Location: ' . url('logowanie'));
      exit();
    }

    try {
      // KROK 2: Wymiana kodu na Token Dostępu
      $token = $this->googleClient->fetchAccessTokenWithAuthCode($_GET['code']);

      if (isset($token['error'])) {
        throw new Exception('Błąd tokenu Google: ' . $token['error_description']);
      }

      if (!is_array($token) || empty($token)) {
        throw new Exception('Otrzymano nieprawidłowy token dostępu.');
      }

      $this->googleClient->setAccessToken($token);

      // KROK 3: Pobranie ustandaryzowanych danych profilowych użytkownika
      $oauth2 = new Oauth2($this->googleClient);
      $googleUserInfo = $oauth2->userinfo->get();

      $googleId = $googleUserInfo->getId();
      $email = $googleUserInfo->getEmail();
      $firstName = $googleUserInfo->getGivenName();
      $lastName = $googleUserInfo->getFamilyName();

      // KROK 4: Lokalna integracja użytkownika (Model-level Sync)
      // Metoda findOrCreateGoogleUser zapewnia, że konto zostanie utworzone lub powiązane (linkage).
      $user = $this->userModel->findOrCreateGoogleUser($googleId, $email, $firstName, $lastName);

      if ($user instanceof User) {
        // KROK 5: Finalizacja lokalnej sesji uwierzytelnionej
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        unset($_SESSION['verify_user_id']);

        // KROK 6: Przekierowanie do wnętrza aplikacji
        header('Location: ' . url('/'));
        exit();
      } else {
        throw new Exception('Problem z integracją użytkownika w lokalnej bazie danych.');
      }
    } catch (Exception $e) {
      // Obsługa błędów zewnętrznych serwerów API lub bazy danych
      error_log('[GoogleAuth Error] ' . $e->getMessage());
      $_SESSION['flash_message'] = [
        'type' => 'error', 
        'text' => 'Wystąpił błąd podczas logowania przez Google. Spróbuj ponownie później.'
      ];
      header('Location: ' . url('logowanie'));
      exit();
    }
  }
}
