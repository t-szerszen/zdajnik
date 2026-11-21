<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\User;
use App\Services\AnalyticsService;

/**
 * Podstawowy Kontroler Aplikacji (BaseController).
 *
 * Klasa nadrzędna dla wszystkich kontrolerów w systemie. Implementuje mechanizmy
 * współdzielone przez całą aplikację, takie jak:
 * - Automatyczna detekcja statusu sesji i uwierzytelnienia użytkownika.
 * - Standaryzowane renderowanie widoków z wstrzykiwaniem danych globalnych.
 * - Strażnicy dostępu (Middleware-like helpers) dla gości i zalogowanych użytkowników.
 *
 * Wszystkie kontrolery funkcjonalne POWINNY dziedziczyć po tej klasie.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class BaseController
{
  /** @var bool Flaga określająca, czy żądanie pochodzi od zalogowanego użytkownika */
  protected bool $isUserLoggedIn = false;

  /** @var User|null Obiekt-encja zalogowanego użytkownika lub null dla gości */
  protected ?User $currentUser = null;

  /** @var AnalyticsService|null Serwis do śledzenia aktywności produktu */
  private ?AnalyticsService $analyticsService = null;

  /**
   * Konstruktor bazowy - uruchamiany przy każdym żądaniu do aplikacji.
   * 
   * Automatycznie sprawdza stan sesji PHP i inicjalizuje właściwości 
   * uwierzytelnienia, które są następnie dostępne we wszystkich 
   * kontrolerach pochodnych.
   */
  public function __construct()
  {
    // Inicjalizacja stanu użytkownika na podstawie danych z superglobalnej sesji
    if (isset($_SESSION['user']) && $_SESSION['user'] instanceof User) {
      $this->isUserLoggedIn = true;
      $this->currentUser = $_SESSION['user'];
      $this->revalidateSessionUser();
    }
  }

  /** @var int Co ile sekund sesja jest ponownie sprawdzana z bazą danych */
  private const SESSION_REVALIDATE_INTERVAL = 300;

  /**
   * Okresowo sprawdza, czy zalogowany użytkownik nadal ma aktywne konto.
   *
   * Sesja przechowuje kopię obiektu User, więc bez tego zablokowanie konta
   * przez administratora nie wylogowałoby już zalogowanej osoby. Sprawdzenie
   * jest wykonywane najwyżej raz na SESSION_REVALIDATE_INTERVAL sekund,
   * żeby nie dokładać zapytania do każdego żądania.
   *
   * @return void
   */
  private function revalidateSessionUser(): void
  {
    $lastCheck = (int) ($_SESSION['user_checked_at'] ?? 0);
    if (time() - $lastCheck < self::SESSION_REVALIDATE_INTERVAL) {
      return;
    }

    // getUserById() zwraca wyłącznie aktywne konta (is_active = 1, bez deleted_at).
    $freshUser = (new UserModel())->getUserById($this->currentUser->getId());

    if ($freshUser === null) {
      unset($_SESSION['user'], $_SESSION['user_checked_at']);
      session_regenerate_id(true);
      $this->isUserLoggedIn = false;
      $this->currentUser = null;
      return;
    }

    $_SESSION['user'] = $freshUser;
    $_SESSION['user_checked_at'] = time();
    $this->currentUser = $freshUser;
  }

  /**
   * Renderuje wskazany widok i dostarcza do niego dane.
   *
   * Automatycznie udostępnia w każdym widoku zmienne globalne:
   * - $isUserLoggedIn: bool
   * - $currentUser: User|null
   *
   * @param string $viewName Relatywna ścieżka do pliku widoku w folderze views/ (bez .php).
   * @param array<string, mixed> $data Asocjacyjna tablica danych do wypakowania w widoku.
   * @return void
   */
  protected function renderView(string $viewName, array $data = []): void
  {
    // KROK 1: Udostępnienie zmiennych globalnych kontekstu aplikacji
    $isUserLoggedIn = $this->isUserLoggedIn;
    $currentUser = $this->currentUser;

    // KROK 2: Eksportowanie danych lokalnych do zmiennych symbolicznych
    extract($data);

    // KROK 3: Dołączenie fizycznego pliku szablonu
    include __DIR__ . "/../../views/{$viewName}.php";

    // KROK 4: Opcjonalny zapis page view po poprawnym wyrenderowaniu widoku
    $this->trackPageView($viewName);
  }

  /**
   * Strażnik Gościa: Wymusza, aby użytkownik NIE był zalogowany.
   * 
   * Używane na stronach takich jak logowanie czy rejestracja, aby 
   * zalogowani użytkownicy nie mieli do nich dostępu.
   * 
   * @return void Wykonuje przekierowanie i przerywa skrypt, jeśli warunek nie jest spełniony.
   */
  protected function requireGuest(): void
  {
    if ($this->isUserLoggedIn) {
      header('Location: ' . url('/'));
      exit();
    }
  }

  /**
   * Strażnik Autoryzacji: Wymusza, aby użytkownik BYŁ zalogowany.
   * 
   * Używane na stronach chronionych (np. profil, statystyki, testy).
   * 
   * @return void Wykonuje przekierowanie do logowania, jeśli użytkownik jest gościem.
   */
  protected function requireAuth(): void
  {
    if (!$this->isUserLoggedIn) {
      header('Location: ' . url('logowanie'));
      exit();
    }
  }

  /**
   * Strażnik Administracyjny: wymusza dostęp wyłącznie dla kont admin.
   *
   * Najpierw wymaga logowania, a następnie sprawdza rolę.
   * Brak uprawnień kończy się odpowiedzią 403.
   *
   * @return void
   */
  protected function requireAdmin(): void
  {
    $this->requireAuth();

    $freshUser = (new UserModel())->getUserById($this->currentUser->getId());

    if ($freshUser === null || $freshUser->isAdmin() === false) {
      http_response_code(403);
      $errorController = new ErrorController();
      $errorController->showError(403);
      exit();
    }

    $this->currentUser = $freshUser;
    $_SESSION['user'] = $freshUser;
  }

  /**
   * Nieblokujący zapis zdarzenia dla kontrolerów potomnych.
   */
  protected function trackAnalyticsEvent(string $eventType, array $metadata = [], ?int $userId = null): bool
  {
    try {
      $this->analyticsService ??= new AnalyticsService();
      return $this->analyticsService->trackEvent(
        $eventType,
        $userId ?? $this->currentUser?->getId(),
        $metadata
      );
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Udostępnia serwis metodom trackującym z własną normalizacją danych.
   */
  protected function getAnalyticsService(): AnalyticsService
  {
    $this->analyticsService ??= new AnalyticsService();
    return $this->analyticsService;
  }

  /**
   * Rejestruje page view dla publicznych widoków.
   */
  private function trackPageView(string $viewName): void
  {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
      return;
    }

    if (!$this->shouldTrackPageView($viewName)) {
      return;
    }

    $this->trackAnalyticsEvent('page_view', [
      'path' => $this->getAnalyticsPath(),
      'source' => $this->getAnalyticsTrafficSource(),
    ]);
  }

  /**
   * Sprawdza, czy widok powinien być liczony jako publiczny page view.
   */
  private function shouldTrackPageView(string $viewName): bool
  {
    if (str_starts_with($viewName, 'admin/')) {
      return false;
    }

    if (str_starts_with($viewName, 'errors/')) {
      return false;
    }

    return true;
  }

  /**
   * Zapisuje wyłącznie ścieżkę bez query stringa, aby parametry z tokenami
   * lub danymi użytkownika nie trafiły do metadata.
   */
  private function getAnalyticsPath(): string
  {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = parse_url($requestUri, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
      $path = '/' . ltrim((string) ($_GET['url'] ?? ''), '/');
    }

    return mb_substr($path, 0, 255);
  }

  /**
   * Źródło ruchu jest klasyfikowane po hoście referera. Pełny URL nie jest
   * przechowywany, dzięki czemu query string nie trafia do analityki.
   */
  private function getAnalyticsTrafficSource(): string
  {
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer === '') {
      return 'direct';
    }

    $refererHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
    $currentHost = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));

    if ($refererHost === '' || $refererHost === $currentHost) {
      return 'internal';
    }

    if (preg_match('/(^|\.)google\.|(^|\.)bing\.|(^|\.)duckduckgo\./', $refererHost)) {
      return 'organic-search';
    }

    if (preg_match('/facebook\.|instagram\.|tiktok\.|linkedin\.|x\.com$|twitter\./', $refererHost)) {
      return 'social';
    }

    return mb_substr($refererHost, 0, 100);
  }
}
