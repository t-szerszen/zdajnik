<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\Attempt;
use App\Models\UserProgress;
use App\Services\TokenService;
use App\Services\Mailer;
use App\Services\Validator;

/**
 * Kontroler Ustawień Profilu.
 *
 * Klasa zarządza wszystkimi operacjami związanymi z konfiguracją konta użytkownika,
 * w tym zmianą danych osobowych (imię, nazwisko), zarządzaniem zabezpieczeniami 
 * (zmiana hasła, zmiana adresu e-mail) oraz administracją postępem nauki 
 * (zerowanie/anonimizacja wyników). Klasa obsługuje również proces usuwania konta.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class SettingsController extends BaseController
{
  /** @var UserModel Model do operacji na danych użytkownika */
  private UserModel $userModel;

  /** @var Attempt Model do zarządzania historią prób egzaminacyjnych */
  private Attempt $attemptModel;

  /** @var UserProgress Model do śledzenia i zarządzania postępem pytań */
  private UserProgress $userProgressModel;

  /** @var TokenService Serwis generujący i weryfikujący tokeny bezpieczeństwa */
  private TokenService $tokenService;

  /** @var Mailer Serwis do wysyłki powiadomień i kodów weryfikacyjnych */
  private Mailer $mailer;

  /** @var Validator Serwis odpowiedzialny za walidację danych wejściowych */
  private Validator $validator;

  /**
   * Inicjalizuje kontroler ustawień.
   * 
   * Ustawia wymaganie autoryzacji dla wszystkich akcji w tym kontrolerze
   * oraz wstrzykuje niezbędne zależności.
   */
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
   * Wyświetla główny widok profilu użytkownika.
   *
   * @param string|null $action Opcjonalna nazwa akcji do określenia aktywnej zakładki.
   * @return void
   */
  public function index(?string $action = null): void
  {
    $this->renderSettingsView($action);
  }

  /**
   * Główny dyspozytor akcji typu POST dla ustawień.
   *
   * @param string $action Nazwa akcji przekazana w adresie URL.
   * @return void
   */
  public function handleAction(string $action): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->index();
      return;
    }
    
    if (!(new \App\Services\CsrfService())->validate($_POST['csrf_token'] ?? null)) {
      $this->redirectWithFlash('error', ['Sesja wygasła lub żądanie jest nieprawidłowe (Błąd CSRF).'], 'profile');
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
   * Inicjuje proces usuwania konta użytkownika.
   * 
   * Metoda wykonuje następujące kroki:
   * 1. Weryfikacja hasła dla użytkowników lokalnych.
   * 2. Generowanie unikalnego tokena operacji usuwania.
   * 3. Wysyłka e-maila z linkiem potwierdzającym.
   *
   * @return void
   */
  public function handleAccountDeletion(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->index();
      return;
    }
    
    if (!(new \App\Services\CsrfService())->validate($_POST['csrf_token'] ?? null)) {
      $this->redirectWithFlash('error', ['Sesja wygasła lub żądanie jest nieprawidłowe (Błąd CSRF).'], 'profile');
    }

    $password = $_POST['password'] ?? '';
    $isGoogleUser = ($this->currentUser->getAuthProvider() === 'google');

    // KROK 1: Walidacja bezpieczeństwa i weryfikacja hasła
    if (!$isGoogleUser && !$this->userModel->checkPassword($this->currentUser->getId(), $password)) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'account_deletion',
        'invalid_password'
      );
      $this->redirectWithFlash('error', ['Podane hasło jest nieprawidłowe.'], 'delete');
    }

    // KROK 2: Generowanie tokena weryfikacyjnego dla operacji usuwania
    $tokenData = $this->tokenService->generateToken($this->currentUser->getId(), 'account_deletion');

    if ($tokenData) {
      // KROK 3: Przygotowanie linku i wysyłka wiadomości e-mail
      $link = url('potwierdz-usuniecie?token=' . $tokenData['token']);
      $this->mailer->sendAccountDeletionConfirmationEmail($this->currentUser->getEmail(), $link);

      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Na Twój adres e-mail wysłano link potwierdzający. Kliknij go, aby dokończyć usuwanie konta.'
      ];
      $this->getAnalyticsService()->trackSettingsUpdated(
        $this->currentUser->getId(),
        'account_deletion_requested'
      );
    } else {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'account_deletion',
        'token_generation_failed'
      );
      $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => 'Wystąpił błąd podczas generowania tokena. Spróbuj ponownie.'
      ];
    }

    $this->redirectWithFlash('success', 'Sprawdź skrzynkę odbiorczą.', 'delete');
  }

  /**
   * Finalizuje proces usuwania konta na podstawie tokenu email.
   * 
   * Metoda wykonuje następujące kroki:
   * 1. Walidacja poprawności i typu tokenu.
   * 2. Oznaczenie konta jako przeznaczone do usunięcia (Soft Delete).
   * 3. Sprzątanie zużytego tokenu.
   * 4. Zakończenie sesji użytkownika.
   *
   * @return void
   */
  public function confirmDeletion(): void
  {
    // KROK 1: Pobranie rekordu tokenu i walidacja jego typu
    $token = $_GET['token'] ?? '';
    $tokenRecord = $this->tokenService->getTokenRecord($token);

    if (!$tokenRecord || $tokenRecord['type'] !== 'account_deletion') {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser?->getId(),
        'account_deletion',
        'invalid_or_expired_token'
      );
      $this->redirectWithFlash('error', ['Link weryfikacyjny wygasł lub jest nieprawidłowy.'], 'profile');
    }

    $userId = $tokenRecord['user_id'];

    // KROK 2: Wykonanie operacji Soft Delete w modelu
    if ($this->userModel->requestDeletion($userId)) {
      // KROK 3: Usunięcie zużytego tokenu
      $this->tokenService->deleteTokensForUserByType($userId, 'account_deletion');
      $this->getAnalyticsService()->trackAccountDeleted();

      // KROK 4: Wylogowanie użytkownika (zniszczenie sesji)
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

  /**
   * Proces technologiczny aktualizacji danych osobowych (imię, nazwisko).
   * 
   * @return void
   */
  private function processNameChange(): void
  {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';

    // Walidacja struktury imion i nazwisk przez serwis walidatora
    $errors = $this->validator->validateNames($firstName, $lastName);

    // Weryfikacja hasła dla kont lokalnych przed zmianą danych wrażliwych
    if ($this->currentUser->getAuthProvider() === 'local') {
      if (empty($password)) {
        $errors[] = 'Musisz podać hasło, aby zatwierdzić zmiany.';
      } elseif (!$this->userModel->checkPassword($this->currentUser->getId(), $password)) {
        $errors[] = 'Podane hasło jest nieprawidłowe.';
      }
    }

    if (!empty($errors)) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'settings_name',
        'invalid_form',
        ['count' => count($errors)]
      );
      $this->redirectWithFlash('error', $errors, 'profile');
    }

    // Wykonanie aktualizacji i odświeżenie danych w sesji
    if ($this->userModel->updateName($this->currentUser->getId(), $firstName, $lastName)) {
      $this->getAnalyticsService()->trackSettingsUpdated($this->currentUser->getId(), 'name_updated');
      $this->refreshSessionUser();
      $this->redirectWithFlash('success', 'Twoje dane zostały zaktualizowane.', 'profile');
    }

    $this->redirectWithFlash('error', ['Błąd zapisu danych.'], 'profile');
  }

  /**
   * Zarządza procesem zmiany hasła logowania.
   * 
   * @return void
   */
  private function processPasswordChange(): void
  {
    if ($this->currentUser->getAuthProvider() === 'google') {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'settings_password',
        'provider_has_no_password'
      );
      $this->redirectWithFlash('error', ['Konto Google nie posiada hasła.'], 'password');
    }

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_new_password'] ?? '';

    // Weryfikacja obecnego hasła
    if (!$this->userModel->checkPassword($this->currentUser->getId(), $current)) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'settings_password',
        'invalid_current_password'
      );
      $this->redirectWithFlash('error', ['Obecne hasło jest nieprawidłowe.'], 'password');
    }

    // Walidacja siły nowego hasła
    $errors = $this->validator->validatePasswordStrength($new, $confirm);
    if ($current === $new) {
      $errors[] = 'Nowe hasło musi różnić się od obecnego.';
    }

    if (!empty($errors)) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'settings_password',
        'invalid_new_password',
        ['count' => count($errors)]
      );
      $this->redirectWithFlash('error', $errors, 'password');
    }

    // Zapis nowego hasła (zaszyfrowanego wewnątrz modelu)
    if ($this->userModel->updatePassword($this->currentUser->getId(), $new)) {
      $this->getAnalyticsService()->trackSettingsUpdated($this->currentUser->getId(), 'password_updated');
      $this->redirectWithFlash('success', 'Hasło zostało zmienione.', 'password');
    }
  }

  /**
   * Inicjuje proces zmiany adresu e-mail.
   * 
   * Metoda wysyła kod weryfikacyjny na nowy adres przed dokonaniem zmiany.
   *
   * @return void
   */
  private function processEmailChange(): void
  {
    // Ochrona przed nadmiarowym wysyłaniem e-maili (cooldown)
    if ($this->isEmailCooldownActive()) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'settings_email',
        'cooldown_active'
      );
      $this->redirectWithFlash('error', ['Odczekaj minutę przed kolejną próbą.'], 'email');
    }

    $newEmail = trim($_POST['new_email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Walidacja nowego adresu i hasła przez zewnętrzny serwis
    $errors = $this->validator->validateEmailChange($newEmail, $password, $this->currentUser->getId());

    if (!empty($errors)) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'settings_email',
        'invalid_form',
        ['count' => count($errors)]
      );
      $this->redirectWithFlash('error', $errors, 'email');
    }

    // Generowanie tokenu i kodu weryfikacyjnego (OTP)
    $tokenData = $this->tokenService->generateToken($this->currentUser->getId(), 'email_change', $newEmail);
    $token = $tokenData['token'];
    $code = $tokenData['code'];

    // Wysyłka kodu weryfikacyjnego
    $this->mailer->sendEmailChangeVerificationEmail($newEmail, $code);

    $_SESSION['email_change_sent'] = time();
    $_SESSION['flash_message'] = [
      'type' => 'success',
      'text' => "Na nowy adres e-mail wysłano kod. Wpisz go poniżej."
    ];
    $this->getAnalyticsService()->trackSettingsUpdated(
      $this->currentUser->getId(),
      'email_change_requested'
    );

    header('Location: ' . url("zmiana-email?token=$token"));
    exit();
  }

  /**
   * Wyświetla formularz weryfikacji kodu dla zmiany adresu e-mail.
   * 
   * @return void
   */
  public function showVerifyEmailForm(): void
  {
    $token = $_GET['token'] ?? null;
    $tokenRecord = $this->tokenService->getTokenRecord($token);

    if (!$token || !$tokenRecord) {
      $this->redirectWithFlash('error', ['Link weryfikacyjny wygasł lub jest nieprawidłowy.'], 'email');
    }

    $this->renderView('reset_email', ['token' => $token]);
  }

  /**
   * Przetwarza wpisany kod i finalizuje zmianę adresu e-mail w bazie danych.
   * 
   * @return void
   */
  public function handleVerifyEmailRequest(): void
  {
    $token = $_POST['token'] ?? null;
    $authCode = $_POST['auth_code'] ?? '';

    $tokenRecord = $this->tokenService->getTokenRecord($token);

    if (!$tokenRecord) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'settings_email',
        'expired_session'
      );
      $this->redirectWithFlash('error', ['Sesja wygasła. Spróbuj ponownie.'], 'email');
    }

    // Weryfikacja zgodności kodu OTP
    $errors = $this->validator->validateAuthCode($authCode, $tokenRecord['auth_code']);

    if (!empty($errors)) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'settings_email',
        'invalid_verification_code'
      );
      $_SESSION['flash_message'] = ['type' => 'error', 'errors' => $errors];
      header('Location: ' . url("zmiana-email?token=$token"));
      exit();
    }

    // Pomyślna zmiana w modelu i sprzątanie tokenów
    $newEmail = $tokenRecord['token_data'];
    if ($this->userModel->updateAndVerifyEmail($this->currentUser->getId(), $newEmail)) {
      $this->tokenService->deleteTokensForUserByType($this->currentUser->getId(), 'email_change');
      $this->getAnalyticsService()->trackEmailVerified(
        $this->currentUser->getId(),
        'settings_email_change'
      );
      $this->getAnalyticsService()->trackSettingsUpdated($this->currentUser->getId(), 'email_updated');
      $this->refreshSessionUser();
      $this->redirectWithFlash('success', 'Adres e-mail został pomyślnie zmieniony.', 'email');
    } else {
      $this->redirectWithFlash('error', ['Wystąpił błąd podczas aktualizacji adresu e-mail.'], 'email');
    }
  }

  /**
   * Inicjuje procedurę zerowania postępu użytkownika dla wybranego egzaminu.
   * 
   * Metoda generuje i wysyła link potwierdzający operację na adres e-mail.
   *
   * @return void
   */
  private function processProgressAnonymization(): void
  {
    $examTypeId = $_POST['exam_type_id'] ?? null;
    $password = $_POST['password'] ?? '';
    $isGoogleUser = ($this->currentUser->getAuthProvider() === 'google');

    // Walidacja wyboru egzaminu i hasła (dla kont lokalnych)
    if (!$examTypeId) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'progress_anonymization',
        'missing_exam'
      );
      $this->redirectWithFlash('error', ['Musisz wybrać egzamin z listy.'], 'progress');
    }

    if (!$isGoogleUser && !$this->userModel->checkPassword($this->currentUser->getId(), $password)) {
      $this->getAnalyticsService()->trackValidationError(
        $this->currentUser->getId(),
        'progress_anonymization',
        'invalid_password',
        ['exam_id' => (int) $examTypeId]
      );
      $this->redirectWithFlash('error', ['Podane hasło jest nieprawidłowe.'], 'progress');
    }

    $userId = $this->currentUser->getId();

    // Weryfikacja czy użytkownik rzeczywiście posiada postęp w danym egzaminie
    $startedExams = $this->attemptModel->getStartedExamsByUser($userId);
    $examCode = '';
    foreach ($startedExams as $exam) {
      if ((int) $exam['id'] === (int) $examTypeId) {
        $examCode = $exam['code'];
        break;
      }
    }

    if (!$examCode) {
      $this->getAnalyticsService()->trackValidationError(
        $userId,
        'progress_anonymization',
        'exam_not_started',
        ['exam_id' => (int) $examTypeId]
      );
      $this->redirectWithFlash('error', ['Wybrany egzamin nie istnieje lub nie został rozpoczęty.'], 'progress');
    }

    // Generowanie tokena bezpieczeństwa dla operacji zerowania postępu
    $tokenData = $this->tokenService->generateToken($userId, 'progress_anonymization', (string) $examTypeId);

    if ($tokenData) {
      $link = url('potwierdz-anonimizacje?token=' . $tokenData['token']);
      $this->mailer->sendProgressAnonymizationEmail($this->currentUser->getEmail(), $link, $examCode);

      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Na Twój adres e-mail wysłano link potwierdzający. Kliknij go, aby wyzerować postęp wybranego egzaminu.'
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
   * Finalizuje proces wyzerowania postępu nauki na podstawie tokenu email.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Walidacja tokenu i pobranie danych operacyjnych (typ egzaminu).
   * 2. Analiza liczby udzielonych odpowiedzi w celu wyboru strategii (usunięcie vs anonimizacja).
   * 3. Przetworzenie historii prób egzaminacyjnych w bazie danych zgodnie z wybraną strategią.
   * 4. Całkowite usunięcie powiązania (relacji) użytkownika z indywidualnymi pytaniami.
   * 5. Sprzątanie tokenów bezpieczeństwa i powrót do panelu ustawień.
   *
   * @return void
   */
  public function confirmProgressAnonymization(): void
  {
    // KROK 1: Pobranie i walidacja tokenu weryfikacyjnego
    $token = $_GET['token'] ?? '';
    $tokenRecord = $this->tokenService->getTokenRecord($token);

    if (!$tokenRecord || $tokenRecord['type'] !== 'progress_anonymization') {
      $this->redirectWithFlash('error', ['Link weryfikacyjny wygasł lub jest nieprawidłowy.'], 'profile');
      return;
    }

    $userId = (int) $tokenRecord['user_id'];
    $examTypeId = (int) $tokenRecord['token_data'];

    // KROK 2: Analiza ilości danych (answeredCount) w celu optymalizacji bazy danych
    $answeredCount = $this->userProgressModel->countAnsweredQuestionsForExam($userId, $examTypeId);

    // KROK 3: Przetwarzanie historii prób (attempts) w zależności od stopnia progresu
    if ($answeredCount <= 40) {
      // Dla małej ilości danych wykonujemy twarde usunięcie (mniej śmieci w DB)
      $attemptsResult = $this->attemptModel->deleteAttempts($userId, $examTypeId);
    } else {
      // Dla dużych zbiorów danych zachowujemy je dla statystyk zbiorczych, ale bez powiązania z UserID
      $attemptsResult = $this->attemptModel->anonymizeAttempts($userId, $examTypeId);
    }

    // KROK 4: Bezpowrotne usunięcie mapowania postępu konkretnego użytkownika
    $progressDeleted = $this->userProgressModel->deleteProgress($userId, $examTypeId);

    // KROK 5: Sprzątanie i przekierowanie z komunikatem o sukcesie
    if ($progressDeleted !== false && $attemptsResult !== false) {
      $this->tokenService->deleteTokensForUserByType($userId, 'progress_anonymization');
      $this->getAnalyticsService()->trackSettingsUpdated($userId, 'progress_anonymized', [
        'exam_id' => $examTypeId,
        'count' => $answeredCount,
      ]);
      $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => 'Postęp dla wskazanego egzaminu został pomyślnie wyzerowany.'
      ];
      header('Location: ' . url('ustawienia'));
      exit();
    }

    $this->redirectWithFlash('error', ['Wystąpił błąd podczas usuwania postępu.'], 'profile');
  }

  /**
   * Procedura bezpiecznego czyszczenia sesji (np. po wylogowaniu lub usunięciu konta).
   * 
   * @return void
   */
  private function terminateSession(): void
  {
    session_unset();
    session_destroy();
    session_start();
  }

  /**
   * Renderuje widok panelu ustawień, przygotowując niezbędne mapowania i dane.
   * 
   * @param string|null $action Parametr akcji określający domyślnie aktywną zakładkę.
   * @return void
   */
  private function renderSettingsView(?string $action = null): void
  {
    // Obliczenie pozostałego czasu blokady e-mail (cooldown)
    $elapsed = time() - ($_SESSION['email_change_sent'] ?? 0);

    // Mapowanie technicznych nazw akcji na przyjazne klucze widoku
    $activeMapping = [
      'change_name' => 'name',
      'change_password' => 'password',
      'change_email' => 'email',
      'anonymize_progress' => 'progress',
    ];

    $activeTab = $_GET['active'] ?? ($activeMapping[$action] ?? 'profile');

    // Pobranie listy egzaminów, w których użytkownik posiada aktywny postęp
    $startedExams = $this->attemptModel->getStartedExamsByUser($this->currentUser->getId());

    $this->renderView('settings', [
      'activeForm' => $activeTab,
      'emailChangeRemaining' => max(0, 60 - $elapsed),
      'startedExams' => $startedExams
    ]);
  }

  /**
   * Standaryzowana metoda przekierowań z komunikatami typu Flash.
   * 
   * @param string $type Typ komunikatu ('success' lub 'error').
   * @param string|array $content Treść komunikatu (tekst lub tablica błędów).
   * @param string $activeTab Klucz zakładki, na którą ma wrócić użytkownik.
   * @return void
   */
  private function redirectWithFlash(string $type, string|array $content, string $activeTab): void
  {
    $_SESSION['flash_message'] = $type === 'success'
      ? ['type' => 'success', 'text' => $content]
      : ['type' => 'error', 'errors' => (array) $content];

    header('Location: ' . url("ustawienia?active=$activeTab"));
    exit();
  }

  /**
   * Synchronizuje obiekt użytkownika w sesji z aktualnym stanem w bazie danych.
   * 
   * @return void
   */
  private function refreshSessionUser(): void
  {
    $this->currentUser = $this->userModel->getUserById($this->currentUser->getId());
    $_SESSION['user'] = $this->currentUser;
  }

  /**
   * Sprawdza stan blokady czasowej na operacje wysyłki e-maili w sesji.
   * 
   * @return bool True jeśli cooldown jest aktywny.
   */
  private function isEmailCooldownActive(): bool
  {
    return isset($_SESSION['email_change_sent']) && (time() - $_SESSION['email_change_sent'] < 60);
  }

}
