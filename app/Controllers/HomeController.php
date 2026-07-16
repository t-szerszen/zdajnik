<?php

namespace App\Controllers;

use App\Services\CsrfService;
use App\Services\DynamicDataService;
use App\Services\Mailer;

/**
 * Kontroler Strony Głównej (HomeController).
 *
 * Odpowiada za serwowanie głównego punktu wejściowego do aplikacji oraz 
 * stron informacyjnych i prawnych (Polityka Prywatności).
 * 
 * Głównym zadaniem kontrolera jest agregacja dostępnych kwalifikacji (egzaminów) 
 * i przypisanych do nich tematów, aby umożliwić użytkownikowi wybór ścieżki nauki 
 * bezpośrednio ze strony startowej.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class HomeController extends BaseController
{
  /** @var DynamicDataService Serwis dostarczający dane o strukturze egzaminów */
  private DynamicDataService $dynamicDataService;

  /** @var CsrfService Serwis weryfikujący formularze publiczne */
  private CsrfService $csrfService;

  /**
   * Projektuje i inicjalizuje komponenty strony głównej.
   */
  public function __construct()
  {
    parent::__construct();
    $this->dynamicDataService = new DynamicDataService();
    $this->csrfService = new CsrfService();
  }

  /**
   * Renderuje widok strony głównej (Landing Page).
   * 
   * @return void
   */
  public function show(): void
  {
    // KROK: Pobranie struktury kwalifikacji i ich kategorii tematycznych
    $examsCategories = $this->loadExamsAndTopics();

    // KROK: Wyświetlenie widoku startowego z dynamiczną listą egzaminów
    $this->renderView('home', [
      'examsCategories' => $examsCategories,
      'pageTitle' => 'Zdajnik - Inteligentna Nauka do Egzaminów Zawodowych'
    ]);
  }

  /**
   * Renderuje widok Polityki Prywatności i Regulaminu serwisu (GDPR compliance).
   * 
   * @return void
   */
  public function showPrivacyPolicy(): void
  {
    $this->renderView('privacy_policy', [
      'pageTitle' => 'Polityka Prywatności - Zdajnik'
    ]);
  }

  /**
   * Renderuje krótki widok informacyjny o projekcie Zdajnik.
   *
   * @return void
   */
  public function showAboutProject(): void
  {
    $this->renderView('about_project', [
      'pageTitle' => 'O projekcie - Zdajnik'
    ]);
  }

  /**
   * Renderuje publiczny widok kontaktu z formularzem.
   *
   * @return void
   */
  public function showContact(): void
  {
    $this->renderView('contact', [
      'pageTitle' => 'Kontakt - Zdajnik',
      'formData' => $_SESSION['contact_form_data'] ?? []
    ]);

    unset($_SESSION['contact_form_data']);
  }

  /**
   * Obsługuje wysyłkę formularza kontaktowego.
   *
   * @return void
   */
  public function handleContact(): void
  {
    $formData = [
      'name' => trim((string) ($_POST['name'] ?? '')),
      'email' => trim((string) ($_POST['email'] ?? '')),
      'subject' => trim((string) ($_POST['subject'] ?? '')),
      'message' => trim((string) ($_POST['message'] ?? ''))
    ];

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->redirectContact('error', ['Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.'], $formData);
    }

    if (!empty(trim((string) ($_POST['website'] ?? '')))) {
      $this->redirectContact('success', 'Dziękujemy za wiadomość. Odpowiemy tak szybko, jak będzie to możliwe.');
    }

    $errors = $this->validateContactForm($formData);
    if (!empty($errors)) {
      $this->redirectContact('error', $errors, $formData);
    }

    try {
      $sent = (new Mailer())->sendContactNotificationEmail($formData);
    } catch (\Throwable $e) {
      error_log('[Contact Form Error] ' . $e->getMessage());
      $sent = false;
    }

    if (!$sent) {
      $this->redirectContact(
        'error',
        ['Nie udało się wysłać wiadomości. Spróbuj ponownie później albo napisz bezpośrednio na ' . CONTACT_EMAIL . '.'],
        $formData
      );
    }

    $this->redirectContact('success', 'Dziękujemy za wiadomość. Odpowiemy tak szybko, jak będzie to możliwe.');
  }

  /**
   * Wewnętrzna logika agregacji struktury egzaminacyjnej.
   * 
   * Wykonuje iteracyjne zapytania po tematach dla każdego dostępnego typu egzaminu.
   * 
   * @return array<string, array> Tablica asocjacyjna [kod_egzaminu => [lista_kategorii]].
   */
  private function loadExamsAndTopics(): array
  {
    // KROK 1: Pobranie unikalnych kodów kwalifikacji (INF.02, INF.03, etc.)
    $exams = $this->dynamicDataService->getExams();

    $examsCategories = [];
    foreach ($exams as $exam) {
      // KROK 2: Pobranie podkategorii (Topików) dla każdej kwalifikacji
      $categories = $this->dynamicDataService->getCategoriesForExam($exam['id']);
      $examsCategories[$exam['code']] = $categories;
    }

    return $examsCategories;
  }

  /**
   * Waliduje dane formularza kontaktowego.
   *
   * @param array{name: string, email: string, subject: string, message: string} $formData
   * @return string[]
   */
  private function validateContactForm(array $formData): array
  {
    $errors = [];

    if (mb_strlen($formData['name']) < 2 || mb_strlen($formData['name']) > 80) {
      $errors[] = 'Podaj imię lub nazwę kontaktową od 2 do 80 znaków.';
    }

    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'Podaj poprawny adres e-mail.';
    }

    if (mb_strlen($formData['subject']) < 3 || mb_strlen($formData['subject']) > 120) {
      $errors[] = 'Temat powinien mieć od 3 do 120 znaków.';
    }

    if (mb_strlen($formData['message']) < 10 || mb_strlen($formData['message']) > 3000) {
      $errors[] = 'Wiadomość powinna mieć od 10 do 3000 znaków.';
    }

    return $errors;
  }

  /**
   * Ustawia komunikat flash i wraca do strony kontaktu.
   *
   * @param string $type
   * @param string|string[] $content
   * @param array{name?: string, email?: string, subject?: string, message?: string} $formData
   * @return never
   */
  private function redirectContact(string $type, string|array $content, array $formData = []): never
  {
    $_SESSION['flash_message'] = is_array($content)
      ? ['type' => $type, 'errors' => $content]
      : ['type' => $type, 'text' => $content];

    if ($type === 'error' && !empty($formData)) {
      $_SESSION['contact_form_data'] = $formData;
    }

    header('Location: ' . url('kontakt'));
    exit;
  }
}
