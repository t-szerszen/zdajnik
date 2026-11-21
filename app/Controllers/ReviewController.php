<?php

namespace App\Controllers;

use App\Services\StatisticsService;

/**
 * Kontroler Podglądu (Review).
 *
 * Klasa odpowiada za wyświetlanie szczegółowych informacji o pytaniach oraz całych próbach egzaminacyjnych.
 * Pozwala użytkownikom na analizę poprawnych i błędnych odpowiedzi po zakończeniu testu,
 * a także na przeglądanie bazy pytań bezpośrednio z wyszukiwarki.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class ReviewController extends BaseController
{
  /** @var StatisticsService Serwis do pobierania szczegółowych danych analitycznych */
  private StatisticsService $statsService;

  /**
   * Inicjalizuje kontroler podglądu.
   *
   * Ustawia dostęp do serwisu statystyk i danych analitycznych.
   */
  public function __construct()
  {
    parent::__construct();
    $this->statsService = new StatisticsService();
  }

  /**
   * Wyświetla stronę podglądu konkretnego pytania (tryb "Zobacz").
   *
   * Metoda wykonuje następujące kroki:
   * 1. Pobranie szczegółowych danych o pytaniu (wraz z kontekstem użytkownika).
   * 2. Obsługa przypadku braku pytania w bazie danych.
   * 3. Obliczenie dynamicznego adresu URL powrotu na podstawie źródła (search, history, stats).
   * 4. Renderowanie widoku podglądu pytania.
   *
   * @param int $questionId Unikalny identyfikator pytania.
   * @return void
   */
  public function showQuestionReview(int $questionId): void
  {
    // KROK 1: Pobranie szczegółów pytania
    $userId = $this->isUserLoggedIn ? $this->currentUser->getId() : null;
    $question = $this->statsService->getQuestionDetails($questionId, $userId);

    // KROK 2: Weryfikacja istnienia pytania
    if (!$question) {
      (new \App\Controllers\ErrorController())->showError(404, "Pytanie nie zostało znalezione.");
      return;
    }

    // KROK 3: Wyznaczenie adresu powrotnego (logika dynamicznych linków "Wróć")
    $source = $_GET['source'] ?? 'stats';
    if ($source === 'history') {
      $backUrl = $this->buildHistoryBackUrl('errors');
    } elseif ($source === 'search') {
      $query = $_GET['query'] ?? '';
      $backUrl = url('search') . '?' . http_build_query([
        'exam_id' => $this->getIntParam('exam_id'),
        'topic_id' => $this->getIntParam('topic_id'),
        'query' => is_string($query) ? $query : '',
      ]);
    } else {
      $cat = $_GET['category'] ?? '';
      $anchor = $_GET['anchor'] ?? '';
      $backUrl = url('statystyki');

      if (ctype_digit((string) $cat) && (int) $cat > 0) {
        $backUrl .= '?category=' . (int) $cat;
      }

      if (is_string($anchor) && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $anchor)) {
        $backUrl .= '#' . $anchor;
      }
    }

    // KROK 4: Renderowanie widoku
    $this->renderView('question_review', [
      'question' => $question,
      'examCode' => $question['exam_code'],
      'backUrl' => $backUrl
    ]);
  }

  /**
   * Wyświetla stronę podglądu całej zakończonej próby egzaminacyjnej.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Wymaganie autoryzacji (tylko właściciel może widzieć swój egzamin).
   * 2. Wyciągnięcie kompletnego zestawu wyników danej próby z serwisu.
   * 3. Weryfikacja uprawnień i istnienia rekordu.
   * 4. Wyznaczenie kontekstowego adresu powrotnego.
   * 5. Renderowanie widoku podsumowania próby.
   *
   * @param int $attemptId Unikalny identyfikator próby egzaminacyjnej.
   * @return void
   */
  public function showExamReview(int $attemptId): void
  {
    // KROK 1: Autoryzacja dostępu
    $this->requireAuth();
    $userId = $this->currentUser->getId();

    // KROK 2: Pobranie szczegółów próby
    $reviewData = $this->statsService->getExamReviewDetails($attemptId, $userId);

    // KROK 3: Weryfikacja danych i uprawnień
    if (!$reviewData) {
      (new \App\Controllers\ErrorController())->showError(404, "Próba nie została znaleziona lub nie masz do niej dostępu.");
      return;
    }

    // KROK 4: Wyznaczenie adresu URL powrotu na podstawie źródła
    $source = $_GET['source'] ?? 'stats';
    if ($source === 'history') {
      $backUrl = $this->buildHistoryBackUrl('exams');
    } else {
      $cat = $_GET['category'] ?? '';
      $backUrl = url('statystyki');

      if (ctype_digit((string) $cat) && (int) $cat > 0) {
        $backUrl .= '?category=' . (int) $cat;
      }

      $backUrl .= '#examsHistory';
    }

    // KROK 5: Renderowanie widoku
    $this->renderView('exam_review', [
      'exam' => $reviewData,
      'examCode' => $reviewData['meta']['exam_code'],
      'backUrl' => $backUrl
    ]);
  }
  /**
   * Buduje adres powrotu do historii z parametrów GET.
   *
   * Parametry pochodzą z adresu URL, więc typ jest sprawdzany z białą listą,
   * a kategoria rzutowana na liczbę - inaczej spreparowany link mógłby
   * wstrzyknąć HTML/JS do atrybutu href przycisku "Wróć".
   *
   * @param string $defaultType Typ historii używany, gdy parametr jest pusty lub nieznany.
   * @return string Bezpieczny adres URL.
   */
  private function buildHistoryBackUrl(string $defaultType): string
  {
    $type = $_GET['type'] ?? $defaultType;
    if (!in_array($type, HistoryController::HISTORY_TYPES, true)) {
      $type = $defaultType;
    }

    return url('historia') . '?' . http_build_query([
      'type' => $type,
      'category' => $this->getIntParam('category'),
    ]);
  }

  /**
   * Zwraca parametr GET jako nieujemną liczbę całkowitą (0, gdy brak lub niepoprawny).
   *
   * @param string $name Nazwa parametru.
   * @return int
   */
  private function getIntParam(string $name): int
  {
    $value = $_GET[$name] ?? '';
    return ctype_digit((string) $value) ? (int) $value : 0;
  }
}
