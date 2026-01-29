<?php

namespace App\Core;

/**
 * Klasa Router - Centralny mechanizm mapowania URL na akcje kontrolerów.
 *
 * Zastępuje hybrydowe podejście (Switch + Regex) jednolitym systemem rejestracji tras.
 * Obsługuje parametryzowane ścieżki, metody HTTP oraz automatyczne wstrzykiwanie
 * parametrów do metod kontrolerów.
 *
 * @version 2.0.0
 * @author Twoje Imię / Refactor by Examly Architect
 */
class Router
{
  /**
   * Tabela zarejestrowanych tras.
   * Struktura: ['METHOD' => ['pattern' => 'handler']]
   */
  private array $routes = [];

  /**
   * Konstruktor inicjalizujący definicje tras.
   * W większych aplikacjach definicje te wynosi się do pliku routes.php.
   */
  public function __construct()
  {
    $this->registerRoutes();
  }

  /**
   * Rejestruje trasę dla metody GET.
   *
   * @param string $path Ścieżka URL (np. 'test/{code}').
   * @param string $handler Format 'Controller@method'.
   */
  public function get(string $path, string $handler): void
  {
    $this->addRoute('GET', $path, $handler);
  }

  /**
   * Rejestruje trasę dla metody POST.
   *
   * @param string $path Ścieżka URL.
   * @param string $handler Format 'Controller@method'.
   */
  public function post(string $path, string $handler): void
  {
    $this->addRoute('POST', $path, $handler);
  }

  /**
   * Główna metoda przetwarzająca żądanie.
   */
  public function handleRequest(): void
  {
    // 1. Pobierz URI i Metodę
    $uri = trim($_GET['url'] ?? '', '/');
    $method = $_SERVER['REQUEST_METHOD'];

    // 2. Szukaj dopasowania
    foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
      if (preg_match($pattern, $uri, $matches)) {
        // Usuń dopasowania numeryczne, zostaw nazwane parametry
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        $this->dispatch($handler, $params);
        return;
      }
    }

    // 3. Fallback - 404
    $this->sendNotFound();
  }

  /**
   * Prywatna metoda rejestracji trasy i kompilacji Regex.
   */
  private function addRoute(string $method, string $path, string $handler): void
  {
    // Zamiana {param} na Regex named group (?P<param>[^/]+)
    // [^/]+ oznacza "wszystko do kolejnego ukośnika"
    $regexPath = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);

    // Dodanie anchorów początku i końca stringa
    $regexPath = '#^' . $regexPath . '$#';

    $this->routes[$method][$regexPath] = $handler;
  }

  /**
   * Uruchamia odpowiedni kontroler i metodę.
   */
  private function dispatch(string $handler, array $params): void
  {
    [$controllerName, $methodName] = explode('@', $handler);
    $fullControllerName = "App\\Controllers\\$controllerName";

    if (!class_exists($fullControllerName)) {
      $this->sendError("Controller class '$fullControllerName' not found.");
      return;
    }

    $controller = new $fullControllerName();

    if (!method_exists($controller, $methodName)) {
      $this->sendError("Method '$methodName' not found in '$controllerName'.");
      return;
    }

    // Wywołanie metody z rozpakowanymi parametrami
    // Np. showTestPage(string $code) otrzyma wartość z URL
    call_user_func_array([$controller, $methodName], $params);
  }

  private function sendNotFound(): void
  {
    http_response_code(404);
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
    } else {
      echo "404 - Strona nie została znaleziona.";
    }
    exit;
  }

  private function sendError(string $message): void
  {
    http_response_code(500);
    error_log($message);
    echo "Internal Server Error"; // Na produkcji nie pokazujemy szczegółów
    exit;
  }

  /**
   * Definicja wszystkich tras w aplikacji.
   * To jest "mapa" Twojej aplikacji.
   */
  private function registerRoutes(): void
  {
    // --- Strony Główne i Auth ---
    $this->get('', 'HomeController@show');
    $this->get('logowanie', 'LoginController@showLoginForm');
    $this->post('logowanie', 'LoginController@processLogin');
    $this->get('rejestracja', 'RegisterController@showRegistrationForm');
    $this->post('rejestracja', 'RegisterController@processRegistration');
    $this->get('weryfikacja', 'VerificationController@handle'); // Token w query string obsługiwany wewnątrz
    $this->get('wyloguj', 'UserController@logout');

    // Auth Google
    $this->get('auth/google', 'GoogleAuthController@redirectToGoogle');
    $this->get('auth/google/callback', 'GoogleAuthController@handleGoogleCallback');

    // Reset Hasła i Email Auth
    $this->get('autoryzacja-email', 'RegisterController@showVerificationPage'); // lub handleSendVerificationEmail zależnie od logiki query param, tu warto rozdzielić na osobne trasy w przyszłości
    // Rozdzielenie logiki autoryzacji email (przykład czystszego podejścia):
    // $this->get('autoryzacja-email/wyslij', 'RegisterController@handleSendVerificationEmail'); 

    $this->get('reset-hasla', 'PasswordResetController@showForgotPasswordForm');
    $this->post('reset-hasla', 'PasswordResetController@handleForgotPasswordRequest');
    $this->get('nowe-haslo', 'PasswordResetController@showNewPasswordForm');
    $this->post('nowe-haslo', 'PasswordResetController@handleNewPasswordRequest');

    // --- Użytkownik i Ustawienia ---
    $this->get('statystyki', 'DashboardController@showDashboard');
    $this->get('historia', 'HistoryController@showHistory');

    // Ustawienia
    $this->get('ustawienia', 'SettingsController@index');
    $this->get('ustawienia/{action}', 'SettingsController@index');
    $this->post('ustawienia/{action}', 'SettingsController@handleAction');
    $this->post('usun-konto', 'SettingsController@handleAccountDeletion');

    // --- Quizy i Nauka (Web) ---
    // Teraz parametry są czytelne w URL
    $this->get('jedno-pytanie/{code}', 'QuizPageController@showOneQuestionPage');
    $this->get('personalizowany-test/{code}', 'QuizPageController@showPersonalizedTestPage');
    $this->get('test/{code}', 'QuizPageController@showTestPage');

    // --- Review / Wyniki ---
    $this->get('test-pytanie/{id}', 'ReviewController@showQuestionReview');
    $this->get('wyniki/{id}', 'ReviewController@showExamReview');

    // --- API (JSON) ---
    $this->get('api/question/{examCode}', 'ApiController@getQuestion');
    $this->get('api/test/personalized/{examCode}', 'ApiController@getPersonalizedTest');
    $this->get('api/test/full/{examCode}', 'ApiController@getFullTest');
    $this->post('api/check-answer', 'ApiController@checkAnswer');
    $this->post('api/save-attempt', 'ApiController@saveAttempt');
    $this->post('api/save-progress-bulk', 'ApiController@saveProgressBulk');
  }
}
