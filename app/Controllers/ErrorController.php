<?php

namespace App\Controllers;

/**
 * Kontroler obsługujący strony błędów (404, 500).
 */
class ErrorController extends BaseController
{
  /**
   * Wyświetla uniwersalną stronę błędu na podstawie kodu HTTP.
   *
   * @param int $code Kod błędu HTTP (np. 404, 500, 403).
   * @param string|null $customMessage Opcjonalna niestandardowa wiadomość błędu.
   */
  public function showError(int $code, ?string $customMessage = null): void
  {
    http_response_code($code);

    // Jeśli to żądanie API, zwróć błąd w formacie JSON
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
        'success' => false,
        'message' => $customMessage ?? ($code === 404 ? 'Endpoint not found' : 'Internal Server Error')
      ]);
      exit;
    }

    // Domyślne konfiguracje dla typowych błędów
    $errors = [
      404 => [
        'title' => 'Strona nie znaleziona',
        'message' => 'Ups! Wygląda na to, że strona, której szukasz, nie istnieje lub została przeniesiona.',
        'icon' => 'fas fa-road-barrier' // Ikona szlabanu
      ],
      500 => [
        'title' => 'Błąd serwera',
        'message' => 'Coś poszło nie tak po naszej stronie. Nasz zespół już nad tym pracuje.',
        'icon' => 'fas fa-server'
      ],
      403 => [
        'title' => 'Brak dostępu',
        'message' => 'Nie masz uprawnień do przeglądania tej strony.',
        'icon' => 'fas fa-lock'
      ],
      400 => [
        'title' => 'Nieprawidłowe żądanie',
        'message' => 'Twoje żądanie zawiera błędy i nie może zostać przetworzone.',
        'icon' => 'fas fa-exclamation-triangle'
      ]
    ];

    // Pobierz konfigurację dla danego kodu lub użyj domyślnej
    $errorConfig = $errors[$code] ?? [
      'title' => 'Wystąpił błąd',
      'message' => 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie później.',
      'icon' => 'fas fa-exclamation-circle'
    ];

    // Nadpisz wiadomość jeśli podano customową
    if ($customMessage) {
      $errorConfig['message'] = $customMessage;
    }

    // Przekaż dane do widoku
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
