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
      http_response_code(404);
      echo "Pytanie nie zostało znalezione.";
      return;
    }

    // KROK 3: Wyznaczenie adresu powrotnego (logika dynamicznych linków "Wróć")
    $source = $_GET['source'] ?? 'stats';
    if ($source === 'history') {
      $type = $_GET['type'] ?? 'errors';
      $cat = $_GET['category'] ?? 0;
      $backUrl = url('historia') . '?type=' . $type . '&category=' . $cat;
    } elseif ($source === 'search') {
      $query = $_GET['query'] ?? '';
      $exam = $_GET['exam_id'] ?? '';
      $topic = $_GET['topic_id'] ?? '';
      $backUrl = url('search') . "?exam_id=$exam&topic_id=$topic&query=" . urlencode($query);
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
      http_response_code(404);
      echo "Próba nie została znaleziona lub nie masz do niej dostępu.";
      return;
    }

    // KROK 4: Wyznaczenie adresu URL powrotu na podstawie źródła
    $source = $_GET['source'] ?? 'stats';
    if ($source === 'history') {
      $type = $_GET['type'] ?? 'exams';
      $cat = $_GET['category'] ?? 0;
      $backUrl = url('historia') . '?type=' . $type . '&category=' . $cat;
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
}
