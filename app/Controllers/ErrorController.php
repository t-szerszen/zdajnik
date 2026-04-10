<?php

namespace App\Controllers;

/**
 * Kontroler Obsługi Błędów HTTP.
 *
 * Klasa odpowiada za centralne zarządzanie stronami błędów w aplikacji.
 * Obsługuje kody stanu HTTP (np. 400, 403, 404, 500) i wyświetla ujednolicone
 * widoki graficzne dla użytkowników końcowych oraz odpowiedzi JSON dla żądań API.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class ErrorController extends BaseController
{
  /**
   * Wyświetla uniwersalną stronę błędu lub odpowiedź JSON na podstawie kodu HTTP.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Ustawienie nagłówka kodu odpowiedzi HTTP.
   * 2. Wykrycie żądania API i wysłanie sformatowanej odpowiedzi JSON.
   * 3. Przygotowanie mapowania treści i ikon dla typowych błędów (404, 500, etc.).
   * 4. Agregacja danych do widoku i renderowanie strony błędu.
   *
   * @param int $code Kod stanu HTTP (np. 404, 500, 403).
   * @param string|null $customMessage Opcjonalna niestandardowa wiadomość błędu do wyświetlenia.
   * @return void
   */
  public function showError(int $code, ?string $customMessage = null): void
  {
    // KROK 1: Ustawienie statusu odpowiedzi serwera
    http_response_code($code);

    // KROK 2: Obsługa żądań API (odpowiedź w formacie JSON)
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
        'success' => false,
        'message' => $customMessage ?? ($code === 404 ? 'Endpoint not found' : 'Internal Server Error')
      ]);
      exit;
    }

    // KROK 3: Konfiguracja treści błędów dla interfejsu graficznego (UI)
    $errors = [
      404 => [
        'title' => 'Strona nie znaleziona',
        'message' => 'Ups! Wygląda na to, że strona, której szukasz, nie istnieje lub została przeniesiona.',
        'icon' => 'fas fa-road-barrier'
      ],
      500 => [
        'title' => 'Błąd serwera',
        'message' => 'Coś poszło nie tak po naszej stronie. Nasz zespół już nad tym pracuje.',
        'icon' => 'fas fa-server'
      ],
      403 => [
        'title' => 'Brak dostępu',
        'message' => 'Nie masz wystarczających uprawnień do przeglądania tej strony.',
        'icon' => 'fas fa-lock'
      ],
      400 => [
        'title' => 'Nieprawidłowe żądanie',
        'message' => 'Twoje żądanie zawiera błędy i nie może zostać przetworzone przez serwer.',
        'icon' => 'fas fa-exclamation-triangle'
      ]
    ];

    // Pobierz konfigurację dla danego kodu lub zastosuj domyślny fallback
    $errorConfig = $errors[$code] ?? [
      'title' => 'Wystąpił błąd',
      'message' => 'Wystąpił nieoczekiwany błąd aplikacji. Spróbuj ponownie później.',
      'icon' => 'fas fa-exclamation-circle'
    ];

    // Nadpisz wiadomość jeśli dostarczono treść z zewnątrz (np. z routera)
    if ($customMessage) {
      $errorConfig['message'] = $customMessage;
    }

    // KROK 4: Agregacja danych i renderowanie widoku końcowego
    $data = [
      'errorCode' => $code,
      'errorTitle' => $errorConfig['title'],
      'errorMessage' => $errorConfig['message'],
      'errorIcon' => $errorConfig['icon'],
      'pageTitle' => "Błąd $code | Examly"
    ];

    $this->renderView('errors/index', $data);
  }
}
