<?php

namespace App\Core;

/**
 * Klasa Router - Zaawansowany Mechanizm Kierowania Żądań (Routing).
 *
 * Klasa odpowiada za mapowanie czytelnych adresów URL na specyficzne działania 
 * w kontrolerach. Router obsługuje:
 * - Rejestrację tras dla metod GET oraz POST.
 * - Parametryzowane ścieżki w formacie {param} (np. test/{code}).
 * - Dynamiczne wstrzykiwanie parametrów z URL bezpośrednio do metod kontrolerów.
 * - Automatyczną obsługę błędów 404/500 przy braku trasy lub kontrolera.
 *
 * @version 2.1.0
 * @author Tobiasz Szerszeń
 * @package App\Core
 */
class Router
{
  /**
   * Tabela zarejestrowanych tras.
   * Struktura: ['METHOD' => ['#RegexPattern#' => 'Controller@method']]
   * @var array
   */
  private array $routes = [];

  /**
   * Konstruktor inicjalizujący definicje tras aplikacji.
   * 
   * Uruchamia metodę registerRoutes(), w której znajduje się kompletna mapa 
   * nawigacyjna systemu.
   */
  public function __construct()
  {
    $this->registerRoutes();
  }

  /**
   * Rejestruje trasę typu GET.
   *
   * @param string $path Ścieżka URL (np. 'rejestracja' lub 'test/{code}').
   * @param string $handler Nazwa kontrolera i metody w formacie 'Controller@method'.
   * @return void
   */
  public function get(string $path, string $handler): void
  {
    $this->addRoute('GET', $path, $handler);
  }

  /**
   * Rejestruje trasę typu POST.
   *
   * @param string $path Ścieżka URL.
   * @param string $handler Nazwa kontrolera i metody.
   * @return void
   */
  public function post(string $path, string $handler): void
  {
    $this->addRoute('POST', $path, $handler);
  }

  /**
   * Główna metoda przetwarzająca aktualne żądanie przychodzące.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Wyznaczenie czystego URI oraz metody żądania (GET/POST).
   * 2. Dopasowanie URI do zarejestrowanych wzorców tras (Regex).
   * 3. Ekstrakcja nazwanych parametrów z adresu (jeśli ścieżka była dynamiczna).
   * 4. Uruchomienie wybranego kontrolera przez metodę dispatch().
   * 5. Fallback - obsługa błędu 404 w przypadku braku dopasowania.
   *
   * @return void
   */
  public function handleRequest(): void
  {
    try {
      // KROK 1: Przygotowanie danych wejściowych z zapytania
      $uri = trim($_GET['url'] ?? '', '/');
      $method = $_SERVER['REQUEST_METHOD'];

      // KROK 2: Analiza zarejestrowanych tras w poszukiwaniu wzorca
      foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
        if (preg_match($pattern, $uri, $matches)) {
          // KROK 3: Ekstrakcja parametrów nazwanych z mapy Regex (np. code=INF.04)
          $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

          // KROK 4: Finalizacja żądania w wybranym kontrolerze
          $this->dispatch($handler, $params);
          return;
        }
      }

      // KROK 5: Protokół błędu dla nieznanego adresu
      $this->abort(404);
    } catch (\Throwable $e) {
      error_log('[Router Error] Nieobsłużony wyjątek: ' . $e->getMessage() . ' w ' . $e->getFile() . ':' . $e->getLine());
      $this->abort(500, 'Wystąpił nieoczekiwany błąd serwera.');
    }
  }

  /**
   * Rejestruje trasę i automatycznie kompiluje ją do wyrażenia regularnego.
   *
   * Metoda konwertuje przyjazną składnię {param} na bezpieczne, nazwane 
   * grupy przechwytujące Regex (?P<param>[^/]+).
   * 
   * @param string $method Metoda HTTP.
   * @param string $path Surowa ścieżka URL.
   * @param string $handler Handler kontrolera.
   * @return void
   */
  private function addRoute(string $method, string $path, string $handler): void
  {
    // Escapowanie kropek w części literalnej ścieżki (np. 'sitemap.xml'),
    // żeby nie działały jak regexowy "dowolny znak"
    $path = str_replace('.', '\.', $path);

    // Zamiana tagów {parametrycznych} na wzorceRegex
    $regexPath = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);

    // Kotwiczenie wzorca (wymuszenie dopasowania całego URI)
    $regexPath = '#^' . $regexPath . '$#';

    $this->routes[$method][$regexPath] = $handler;
  }

  /**
   * Instancjonuje odpowiedni kontroler i wywołuje na nim metodę akcji.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Rozbicie handlera na nazwy klas i metod.
   * 2. Sprawdzenie integralności systemu (istnienie klasy i metody).
   * 3. Uruchomienie akcji z dynamicznym przekazaniem parametrów (Injection).
   *
   * @param string $handler 'Controller@method'.
   * @param array $params Tablica parametrów wyciągniętych z adresu URL.
   * @return void
   */
  private function dispatch(string $handler, array $params): void
  {
    // KROK 1: Identyfikacja kontenera i akcji
    [$controllerName, $methodName] = explode('@', $handler);
    $fullControllerName = "App\\Controllers\\$controllerName";

    // KROK 2: Weryfikacja spójności składnika
    if (!class_exists($fullControllerName)) {
      $this->abort(500, "Błąd aplikacji: Nie znaleziono klasy '$fullControllerName'.");
      return;
    }

    $controller = new $fullControllerName();

    if (!method_exists($controller, $methodName)) {
      $this->abort(500, "Błąd aplikacji: Brak metody '$methodName' w kontrolerze '$controllerName'.");
      return;
    }

    // KROK 3: Wywołanie docelowej logiki (Action Trigger)
    call_user_func_array([$controller, $methodName], $params);
  }

  /**
   * Centralizuje przerwanie działania żądania z kodem błędu.
   *
   * @param int $code Kod stanu HTTP.
   * @param string $message Opcjonalny komunikat błędu logowany wewnętrznie.
   * @return void
   */
  private function abort(int $code, string $message = ''): void
  {
    if (!empty($message)) {
      error_log($message);
    }

    // Oddelegowanie renderowania widoku błędu do dedykowanego kontrolera
    $errorController = new \App\Controllers\ErrorController();
    $errorController->showError($code);
    exit;
  }

  /**
   * Kompletna mapa drogowa aplikacji Zdajnik.
   * 
   * Służy do konfiguracji wszystkich punktów wejścia (endpointów) systemu.
   * Grupuje trasy funkcjonalnie (Auth, Analityka, Nauka, API).
   * 
   * @return void
   */
  private function registerRoutes(): void
  {
    // --- STREFY GŁÓWNE I UWIERZYTELNIANIE ---
    $this->get('', 'HomeController@show');
    $this->get('o-projekcie', 'HomeController@showAboutProject');
    $this->get('kontakt', 'HomeController@showContact');
    $this->post('kontakt', 'HomeController@handleContact');
    $this->get('polityka-prywatnosci', 'HomeController@showPrivacyPolicy');
    $this->get('logowanie', 'LoginController@showLoginForm');
    $this->post('logowanie', 'LoginController@processLogin');
    $this->get('rejestracja', 'RegisterController@showRegistrationForm');
    $this->post('rejestracja', 'RegisterController@processRegistration');
    $this->get('weryfikacja', 'VerificationController@handle');
    $this->get('wyloguj', 'UserController@logout');

    // Integracja z Google API (OAuth 2.0)
    $this->get('auth/google', 'GoogleAuthController@redirectToGoogle');
    $this->get('auth/google/callback', 'GoogleAuthController@handleGoogleCallback');

    // Bezpieczeństwo konta: Reset hasła i aktywacja e-mail
    $this->get('autoryzacja-email', 'RegisterController@showVerificationPage');
    $this->get('reset-hasla', 'PasswordResetController@showForgotPasswordForm');
    $this->post('reset-hasla', 'PasswordResetController@handleForgotPasswordRequest');
    $this->get('nowe-haslo', 'PasswordResetController@showNewPasswordForm');
    $this->post('nowe-haslo', 'PasswordResetController@handleNewPasswordRequest');

    // --- ANALITYKA I PROFIL UŻYTKOWNIKA ---
    $this->get('statystyki', 'DashboardController@showDashboard');
    $this->get('admin', 'Admin\AdminDashboardController@index');
    $this->get('admin/dashboard/trendy', 'Admin\AdminDashboardController@trends');
    $this->get('admin/uzytkownicy', 'Admin\AdminUserController@users');
    $this->get('admin/uzytkownicy/api', 'Admin\AdminUserController@search');
    $this->post('admin/uzytkownicy/bulk', 'Admin\AdminUserController@bulk');
    $this->get('admin/uzytkownicy/{userId}', 'Admin\AdminUserController@showUser');
    $this->get('admin/egzaminy', 'Admin\AdminExamController@exams');
    $this->get('admin/egzaminy/api', 'Admin\AdminExamController@search');
    $this->get('admin/egzaminy/dodaj', 'Admin\AdminExamController@showCreateExamForm');
    $this->post('admin/egzaminy/dodaj', 'Admin\AdminExamController@storeExam');
    $this->get('admin/egzaminy/{examId}/edytuj', 'Admin\AdminExamController@showEditExamForm');
    $this->post('admin/egzaminy/{examId}/edytuj', 'Admin\AdminExamController@updateExam');
    $this->post('admin/egzaminy/{examId}/aktywuj', 'Admin\AdminExamController@activateExam');
    $this->post('admin/egzaminy/{examId}/dezaktywuj', 'Admin\AdminExamController@deactivateExam');
    $this->post('admin/tematy/{topicId}/edytuj', 'Admin\AdminExamController@updateTopic');
    $this->get('admin/egzaminy/{examId}', 'Admin\AdminExamController@showExam');
    $this->get('admin/pytania', 'Admin\AdminQuestionController@questions');
    $this->get('admin/pytania/api', 'Admin\AdminQuestionController@search');
    $this->post('admin/pytania/bulk', 'Admin\AdminQuestionController@bulk');
    $this->get('admin/pytania/dodaj', 'Admin\AdminQuestionController@showCreateQuestionForm');
    $this->post('admin/pytania/dodaj', 'Admin\AdminQuestionController@storeQuestion');
    $this->get('admin/pytania/{questionId}/edytuj', 'Admin\AdminQuestionController@showEditQuestionForm');
    $this->post('admin/pytania/{questionId}/edytuj', 'Admin\AdminQuestionController@updateQuestion');
    $this->post('admin/pytania/{questionId}/aktywuj', 'Admin\AdminQuestionController@activateQuestion');
    $this->post('admin/pytania/{questionId}/dezaktywuj', 'Admin\AdminQuestionController@deactivateQuestion');
    $this->get('admin/pytania/{questionId}', 'Admin\AdminQuestionController@showQuestion');
    $this->get('admin/arkusze', 'Admin\AdminSheetController@sheets');
    $this->get('admin/arkusze/api', 'Admin\AdminSheetController@search');
    $this->post('admin/arkusze/bulk', 'Admin\AdminSheetController@bulk');
    $this->get('admin/arkusze/dodaj', 'Admin\AdminSheetController@showCreateSheetForm');
    $this->post('admin/arkusze/dodaj', 'Admin\AdminSheetController@storeSheet');
    $this->post('admin/arkusze/tematy/dodaj', 'Admin\AdminSheetController@storeSheetTopic');
    $this->post('admin/arkusze/tematy/{topicId}/edytuj', 'Admin\AdminSheetController@updateSheetTopic');
    $this->get('admin/arkusze/{sheetId}/edytuj', 'Admin\AdminSheetController@showEditSheetForm');
    $this->post('admin/arkusze/{sheetId}/edytuj', 'Admin\AdminSheetController@updateSheet');
    $this->post('admin/arkusze/{sheetId}/aktywuj', 'Admin\AdminSheetController@activateSheet');
    $this->post('admin/arkusze/{sheetId}/dezaktywuj', 'Admin\AdminSheetController@deactivateSheet');
    $this->get('admin/analytics', 'Admin\AdminAnalyticsController@analytics');
    $this->get('admin/analytics/data', 'Admin\AdminAnalyticsController@analyticsData');
    $this->get('admin/analytics/export', 'Admin\AdminAnalyticsController@analyticsExport');
    $this->get('admin/statystyki', 'Admin\AdminAnalyticsController@analytics');
    $this->get('admin/audyt', 'Admin\AdminAuditController@auditLogs');
    $this->get('admin/zgloszenia', 'Admin\AdminReportController@reports');
    $this->post('admin/zgloszenia/{reportId}/status', 'Admin\AdminReportController@updateStatus');
    $this->post('admin/uzytkownicy/{userId}/rola', 'Admin\AdminUserController@updateUserRole');
    $this->post('admin/uzytkownicy/{userId}/zweryfikuj', 'Admin\AdminUserController@verifyUser');
    $this->post('admin/uzytkownicy/{userId}/dezaktywuj', 'Admin\AdminUserController@deactivateUser');
    $this->post('admin/uzytkownicy/{userId}/reaktywuj', 'Admin\AdminUserController@reactivateUser');
    $this->get('historia', 'HistoryController@showHistory');

    // Zarządzanie ustawieniami i danymi osobowymi
    $this->get('ustawienia', 'SettingsController@index');
    $this->get('ustawienia/{action}', 'SettingsController@index');
    $this->post('ustawienia/{action}', 'SettingsController@handleAction');
    $this->post('usun-konto', 'SettingsController@handleAccountDeletion');
    $this->get('potwierdz-usuniecie', 'SettingsController@showConfirmDeletion');
    $this->post('potwierdz-usuniecie', 'SettingsController@confirmDeletion');
    $this->get('potwierdz-anonimizacje', 'SettingsController@showConfirmProgressAnonymization');
    $this->post('potwierdz-anonimizacje', 'SettingsController@confirmProgressAnonymization');
    $this->get('zmiana-email', 'SettingsController@showVerifyEmailForm');
    $this->post('zmiana-email', 'SettingsController@handleVerifyEmailRequest');

    // --- BIBLIOTEKA ARKUSZY ---
    $this->get('arkusze', 'SheetController@index');
    $this->get('arkusze/{code}', 'SheetController@listByExam');
    $this->get('arkusze/{code}/{sheetId}', 'SheetController@show');
    $this->get('moje-notatki', 'SheetController@myNotes');

    // --- PUBLICZNA BAZA PYTAŃ (strony indeksowalne, renderowane serwerowo) ---
    $this->get('pytania', 'QuestionController@index');
    $this->get('pytania/{code}', 'QuestionController@listByExam');
    $this->get('pytania/{code}/{slugId}', 'QuestionController@show');

    // Mapa witryny generowana dynamicznie (brak statycznego pliku w public/)
    $this->get('sitemap.xml', 'SitemapController@index');

    // --- MODUŁY NAUKI I QUIZY (WEB INTERFACE) ---
    $this->get('jedno-pytanie/{code}', 'QuizPageController@showOneQuestionPage');
    $this->get('personalizowany-test/{code}', 'QuizPageController@showPersonalizedTestPage');
    $this->get('test/{code}', 'QuizPageController@showTestPage');

    // --- PRZEGLĄD WYNIKÓW I HISTORII ---
    $this->get('test-pytanie/{questionId}', 'ReviewController@showQuestionReview');
    $this->get('wyniki/{attemptId}', 'ReviewController@showExamReview');

    // --- INTERFEJSY API (JSON INTERACTION) ---
    $this->get('api/question/{examCode}', 'ApiController@getQuestion');
    $this->get('api/test/personalized/{examCode}', 'ApiController@getPersonalizedTest');
    $this->get('api/test/full/{examCode}', 'ApiController@getFullTest');
    $this->post('api/check-answer', 'ApiController@checkAnswer');
    $this->post('api/save-attempt', 'ApiController@saveAttempt');
    $this->post('api/toggle-saved', 'ApiController@toggleSavedQuestion');
    $this->post('api/zglos-blad', 'ApiController@reportQuestion');
    $this->post('api/sheet-note', 'ApiController@saveSheetNote');
    $this->post('api/user/avatar', 'ApiController@updateAvatar');
    $this->post('api/analytics/event', 'ApiController@trackAnalyticsEventFromClient');

    // --- WYSZUKIWARKA I FILTRACJA ---
    $this->get('search', 'SearchController@showSearch');
  }
}
