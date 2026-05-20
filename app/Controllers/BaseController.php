<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\User;

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
    }
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
}
