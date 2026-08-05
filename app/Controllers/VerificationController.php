<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\TokenService;

/**
 * Kontroler Weryfikacji Adresu E-mail.
 *
 * Klasa obsługuje procesy weryfikacji tożsamości użytkownika poprzez tokeny przesyłane drogą mailową.
 * Obsługuje zarówno wstępną aktywację konta, jak i zatwierdzanie zmiany adresu e-mail.
 *
 * @version 1.2.1
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class VerificationController extends BaseController
{
  /**
   * Instancja modelu użytkownika.
   * @var UserModel
   */
  private UserModel $userModel;

  /**
   * Serwis odpowiedzialny za zarządzanie tokenami bezpieczeństwa.
   * @var TokenService
   */
  private TokenService $tokenService;

  /**
   * Inicjalizuje kontroler weryfikacji.
   *
   * Ustawia wymaganie bycia gościem (requireGuest) oraz inicjalizuje
   * niezbędne modele i serwisy (UserModel, TokenService).
   */
  public function __construct()
  {
    parent::__construct();
    $this->requireGuest();
    $this->userModel = new UserModel();
    $this->tokenService = new TokenService();
  }

  /**
   * Przetwarza żądanie weryfikacji na podstawie tokenu z adresu URL.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Walidacja obecności i poprawności tokenu w bazie danych.
   * 2. Identyfikacja typu operacji (aktywacja konta vs zmiana e-maila).
   * 3. Wykonanie odpowiedniej akcji w modelu użytkownika.
   * 4. Sprzątanie zużytych tokenów w celu zachowania bezpieczeństwa.
   * 5. Automatyczne logowanie użytkownika po pomyślnej weryfikacji.
   *
   * @return void Przekierowuje na stronę główną lub wyświetla błąd.
   */
  public function handle(): void
  {
    // KROK 1: Walidacja obecności i poprawności tokenu w bazie danych
    $token = $_GET['token'] ?? null;
    if (!$token) {
      $this->getAnalyticsService()->trackValidationError(null, 'email_verification', 'missing_token');
      $this->renderErrorView('Brak tokenu weryfikacyjnego w żądaniu.');
      return;
    }

    $tokenRecord = $this->tokenService->getTokenRecord($token);
    if (!$tokenRecord) {
      $this->getAnalyticsService()->trackValidationError(null, 'email_verification', 'invalid_or_expired_token');
      $this->renderErrorView('Podany token jest nieprawidłowy, został już wykorzystany lub wygasł.');
      return;
    }

    $success = false;
    $userId = $tokenRecord['user_id'];
    $tokenType = $tokenRecord['type'];

    // KROK 2: Identyfikacja typu operacji (aktywacja vs zmiana e-maila)
    // KROK 3: Wykonanie odpowiedniej akcji w modelu użytkownika
    switch ($tokenType) {
      case 'email_verify':
        $success = $this->userModel->verifyUser($userId);
        break;

      case 'email_change':
        $newEmail = $tokenRecord['token_data'];
        $success = $this->userModel->updateAndVerifyEmail($userId, $newEmail);
        break;
    }

    if ($success) {
      // KROK 4: Sprzątanie zużytych tokenów (bezpieczeństwo One-Time Token)
      $this->tokenService->deleteTokensForUserByType($userId, $tokenType);
      $this->getAnalyticsService()->trackEmailVerified(
        (int) $userId,
        $tokenType === 'email_change' ? 'email_change_link' : 'registration_verification'
      );

      // KROK 5: Automatyczne logowanie użytkownika po pomyślnej weryfikacji
      $user = $this->userModel->getUserById($userId);
      if ($user) {
        // Zabezpieczenie przed Session Fixation
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        unset($_SESSION['verify_user_id']);

        $_SESSION['flash_message'] = [
          'type' => 'success',
          'text' => 'Twoje konto zostało zweryfikowane! Zostałeś automatycznie zalogowany.'
        ];

        header('Location: ' . url('/'));
        exit();
      }

      // Przypadek awaryjny (jeśli użytkownik zniknął z BD w międzyczasie)
      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Konto zweryfikowane pomyślnie! Możesz się teraz zalogować.'
      ];
      unset($_SESSION['verify_user_id']);
      header('Location: ' . url('logowanie'));
      exit();
    } else {
      $this->getAnalyticsService()->trackValidationError(
        (int) $userId,
        'email_verification',
        'verification_failed'
      );
      $this->renderErrorView('Wystąpił nieoczekiwany błąd serwera podczas przetwarzania procesu weryfikacji.');
    }
  }

  // ========================================================================
  // METODY POMOCNICZE (PRIVATE)
  // ========================================================================

  /**
   * Renderuje ustandaryzowany widok błędu dla procesu weryfikacji.
   *
   * @param string $message Treść komunikatu o błędzie do wyświetlenia użytkownikowi.
   * @return void
   */
  private function renderErrorView(string $message): void
  {
    $this->renderView('verify', [
      'message' => $message,
    ]);
  }
}
