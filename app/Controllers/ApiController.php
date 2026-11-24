<?php

namespace App\Controllers;

use App\Models\Question;
use App\Models\Answer;
use App\Models\UserProgress;
use App\Models\Attempt;
use App\Models\SavedQuestion;
use App\Models\QuestionReport;
use App\Models\Sheet;
use App\Models\SheetNote;
use App\Models\UserModel;
use App\Models\SingleQuestionAnswer;
use App\Services\AnalyticsService;
use App\Services\CsrfService;

/**
 * Kontroler API (ApiController).
 *
 * Centralna brama komunikacyjna (JSON REST API) dla interfejsu użytkownika (Frontend).
 * Odpowiada za dostarczanie dynamicznych danych quizu i serwowanie logiki biznesowej 
 * w trybie asynchronicznym (AJAX).
 *
 * Główne obszary odpowiedzialności:
 * - Pobieranie pytań z uwzględnieniem zaawansowanego filtrowania (Discovery, Spaced Repetition).
 * - Weryfikacja poprawności udzielanych odpowiedzi "w locie".
 * - Masowe utrwalanie wyników egzaminów i progresu nauki.
 * - Zarządzanie stanem schowka (zapisane pytania).
 * - Egzekwowanie polityki dostępu (Opcje Premium / Gatekeeper).
 *
 * @version 1.3.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class ApiController extends BaseController
{
  /** @var Question Model do operacji na bazie pytań */
  private Question $questionModel;

  /** @var Answer Model do operacji na odpowiedziach */
  private Answer $answerModel;

  /** @var UserProgress Model do utrwalania statystyk nauki */
  private UserProgress $userProgressModel;

  /** @var Attempt Model do zapisu historii podejść */
  private Attempt $attemptModel;

  /** @var SavedQuestion Model do zarządzania schowkiem użytkownika */
  private SavedQuestion $savedQuestionModel;

  /** @var UserModel Model do aktualizacji danych użytkownika */
  private UserModel $userModel;

  /** @var AnalyticsService Serwis analityki produktu */
  private AnalyticsService $analyticsService;

  /** @var SingleQuestionAnswer Historia odpowiedzi z trybu "jedno pytanie" */
  private SingleQuestionAnswer $singleAnswerModel;

  /** Maksymalna liczba pytań w zapisywanym podejściu (pełny egzamin ma 40). */
  private const MAX_ATTEMPT_QUESTIONS = 40;

  /** Maksymalny rozmiar awatara po zdekodowaniu (bajty). */
  private const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

  /** Maksymalna liczba tematów w jednym zapytaniu o pytania. */
  private const MAX_TOPICS_PER_REQUEST = 60;

  /**
   * Projektuje i inicjalizuje mosty do warstwy danych.
   */
  public function __construct()
  {
    parent::__construct();
    $this->questionModel = new Question();
    $this->answerModel = new Answer();
    $this->userProgressModel = new UserProgress();
    $this->attemptModel = new Attempt();
    $this->savedQuestionModel = new SavedQuestion();
    $this->userModel = new UserModel();
    $this->analyticsService = new AnalyticsService();
    $this->singleAnswerModel = new SingleQuestionAnswer();
  }

  // --- ENDPOINTY POBIERANIA PYTAŃ (READ) ---

  /**
   * Endpoint: Pobiera pojedyncze, przefiltrowane pytanie.
   * Obsługuje tryb "Jedno pytanie" oraz wyświetlanie progresu puli.
   * 
   * @param string $examCode Kod kwalifikacji (np. INF.03).
   */
  public function getQuestion(string $examCode): void
  {
    $this->ensureGetRequest();
    $this->enforceRateLimit('get_question', 120);

    // KROK: Pobranie fizycznych rekordów z bazy przez filtr centralny
    $result = $this->fetchFilteredQuestions($examCode, 1);

    if (!$result['success']) {
      return;
    }

    $question = $result['questions'][0] ?? null;
    $examTypeId = $this->questionModel->getExamTypeIdByCode($examCode);

    // Obsługa scenariusza, w którym brak pytań spełniających filtry (np. wszystko odkryte)
    if (!$question) {
      $this->analyticsService->trackNoQuestionsLeft(
        $this->currentUser?->getId(),
        $examTypeId ? (int) $examTypeId : null,
        null,
        'single_question_module',
        'empty_pool'
      );
      
      $specialFilter = $_GET['premium_option'] ?? null;
      $message = 'Brak pytań w wybranych kategoriach.';
      
      switch ($specialFilter) {
        case 'toDiscover':
            $message = 'Gratulacje! Przerobiłeś już wszystkie dostępne pytania w tej sekcji.';
            break;
        case 'toImprove':
            $message = 'Świetna robota! Nie masz obecnie żadnych słabych punktów w wybranych kategoriach.';
            break;
        case 'toRemind':
            $message = 'Na ten moment nie masz żadnych pytań do powtórki. Wróć tu później!';
            break;
        case 'lastMistakes':
            $message = 'Świetnie! Nie popełniłeś ostatnio żadnych błędów w tych kategoriach.';
            break;
        case 'saved':
            $message = 'Nie masz jeszcze żadnych zapisanych pytań w wybranych kategoriach.';
            break;
        default:
            $message = 'Gratulacje! Przerobiłeś już wszystkie dostępne pytania w tej sekcji.';
            break;
      }
      
      $this->sendJsonResponse([
        'success' => true,
        'status' => 'no_questions_left',
        'message' => $message,
      ]);
      return;
    }

    // Dołączenie wariantów odpowiedzi
    $answers = $this->answerModel->getAnswersByVersionId($question['question_version_id']);

    // Detekcja stanu zapisu dla zalogowanych
    if ($this->isUserLoggedIn) {
      $question['is_saved'] = $this->savedQuestionModel->isSaved($this->currentUser->getId(), $question['question_id']);
      $question['was_answered'] = $this->userProgressModel->hasAnsweredQuestion($this->currentUser->getId(), (int) $question['question_id']);
    }

    try {
      $this->analyticsService->trackSingleQuestionStarted(
        $this->isUserLoggedIn ? $this->currentUser->getId() : null,
        $examTypeId ? (int) $examTypeId : null,
        isset($question['question_id']) ? (int) $question['question_id'] : null,
        [
          'topic_id' => isset($question['topic_id']) ? (int) $question['topic_id'] : null,
          'source' => 'single_question_module',
        ]
      );
    } catch (\Throwable $e) {
      // Analityka nie może blokować pobierania pytania.
    }

    $responseData = [
      'success' => true,
      'question' => $question,
      'answers' => $answers
    ];

    if ($this->isUserLoggedIn && isset($result['pool_progress'])) {
      $responseData['pool_progress'] = $result['pool_progress'];
    }

    $this->sendJsonResponse($responseData);
  }

  /**
   * Endpoint: Pobiera zestaw pytań do testu personalizowanego.
   * 
   * @param string $examCode Kod kwalifikacji.
   */
  public function getPersonalizedTest(string $examCode): void
  {
    $this->ensureGetRequest();
    $this->enforceRateLimit('get_test', 30);

    // Walidacja limitu (bezpieczne granice 10-40)
    $limit = isset($_GET['question_count']) ? (int) $_GET['question_count'] : 20;
    $limit = max(10, min(40, $limit));
    $examTypeId = $this->questionModel->getExamTypeIdByCode($examCode);

    $result = $this->fetchFilteredQuestions($examCode, $limit);

    if (!$result['success']) {
      return;
    }

    $questions = $result['questions'];

    if (count($questions) < $limit) {
      $this->analyticsService->trackNoQuestionsLeft(
        $this->currentUser?->getId(),
        $examTypeId ? (int) $examTypeId : null,
        'personalized',
        'personalized_test',
        'insufficient_pool',
        count($questions)
      );
      
      $message = 'Baza posiada zbyt mało pytań spełniających Twoje kryteria filtrowania.';
      
      if (count($questions) === 0) {
        $specialFilter = $_GET['premium_option'] ?? null;
        switch ($specialFilter) {
          case 'toDiscover':
              $message = 'Gratulacje! Przerobiłeś już wszystkie dostępne pytania w tych kategoriach.';
              break;
          case 'toImprove':
              $message = 'Świetna robota! Nie masz obecnie żadnych słabych punktów w tych kategoriach.';
              break;
          case 'toRemind':
              $message = 'Na ten moment nie masz żadnych pytań do powtórki. Wróć tu później!';
              break;
          case 'lastMistakes':
              $message = 'Świetnie! Nie popełniłeś ostatnio żadnych błędów w tych kategoriach.';
              break;
          case 'saved':
              $message = 'Nie masz jeszcze żadnych zapisanych pytań w wybranych kategoriach.';
              break;
          default:
              $message = 'Brak pytań w wybranych kategoriach.';
              break;
        }
      }
      
      $this->sendJsonResponse([
        'success' => true,
        'status' => 'no_questions_left',
        'message' => $message,
      ]);
      return;
    }

    $this->analyticsService->trackTestStarted(
      $this->isUserLoggedIn ? $this->currentUser->getId() : null,
      (int) $examTypeId,
      [
        'test_type' => 'personalized',
        'count' => count($questions),
        'source' => 'personalized_test',
      ]
    );

    $questionsData = [];
    $savedIds = $this->isUserLoggedIn ? $this->savedQuestionModel->getUserSavedIds($this->currentUser->getId()) : [];

    // Jedno zapytanie o odpowiedzi do wszystkich pytań zamiast osobnego na każde pytanie.
    $answersByVersion = $this->answerModel->getAnswersForBatch(
      array_map(static fn(array $q): int => (int) $q['question_version_id'], $questions)
    );

    foreach ($questions as $question) {
      $answers = $answersByVersion[$question['question_version_id']] ?? [];
      $question['is_saved'] = in_array((int) $question['question_id'], $savedIds);
      $questionsData[] = ['question' => $question, 'answers' => $answers];
    }

    $this->sendJsonResponse(['success' => true, 'questions' => $questionsData]);
  }

  /**
   * Endpoint: Pobiera standardowy arkusz egzaminacyjny (40 losowych pytań).
   */
  public function getFullTest(string $examCode): void
  {
    $this->ensureGetRequest();
    $this->enforceRateLimit('get_test', 30);

    $examTypeId = $this->questionModel->getExamTypeIdByCode($examCode);

    if (!$examTypeId) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Nieprawidłowy kod egzaminu.'], 404);
      return;
    }

    // Wybieramy wszystkie tematy przypisane do tej kwalifikacji
    $allTopicIds = $this->questionModel->getTopicIdsByExamType($examTypeId);
    $questions = $this->questionModel->getQuestions($allTopicIds, 40, $examTypeId);

    if (empty($questions)) {
      $this->analyticsService->trackNoQuestionsLeft(
        $this->currentUser?->getId(),
        $examTypeId,
        'full_exam',
        'full_exam',
        'empty_pool'
      );
    } else {
      $this->analyticsService->trackTestStarted(
        $this->isUserLoggedIn ? $this->currentUser->getId() : null,
        $examTypeId,
        [
          'test_type' => 'full_exam',
          'count' => count($questions),
          'source' => 'full_exam',
        ]
      );
    }

    $questionsData = [];
    $savedIds = $this->isUserLoggedIn ? $this->savedQuestionModel->getUserSavedIds($this->currentUser->getId()) : [];

    // Jedno zapytanie o odpowiedzi do wszystkich pytań zamiast osobnego na każde pytanie.
    $answersByVersion = $this->answerModel->getAnswersForBatch(
      array_map(static fn(array $q): int => (int) $q['question_version_id'], $questions)
    );

    foreach ($questions as $question) {
      $answers = $answersByVersion[$question['question_version_id']] ?? [];
      $question['is_saved'] = in_array((int) $question['question_id'], $savedIds);
      $questionsData[] = ['question' => $question, 'answers' => $answers];
    }

    $this->sendJsonResponse(['success' => true, 'questions' => $questionsData]);
  }

  // --- ENDPOINTY ZAPISU I WERYFIKACJI (WRITE) ---

  /**
   * Endpoint: Weryfikuje poprawność pojedynczej odpowiedzi i natychmiast utrwala progres.
   */
  public function checkAnswer(): void
  {
    $this->guardWriteRequest('check_answer', 60);

    $qvId = (int) ($_POST['question_version_id'] ?? 0);
    $userAnswerId = (int) ($_POST['answer_id'] ?? 0);

    if ($qvId <= 0 || $userAnswerId <= 0) {
      $this->analyticsService->trackValidationError(
        $this->currentUser?->getId(),
        'single_question_answer',
        'missing_parameters'
      );
      $this->sendJsonResponse(['success' => false, 'message' => 'Brak wymaganych parametrów (version_id/answer_id).'], 400);
      return;
    }

    // Jedno zapytanie: wybrana odpowiedź musi należeć do tej wersji pytania.
    // Wcześniej przyjmowaliśmy dowolne answer_id i tylko porównywaliśmy je z poprawnym.
    $context = $this->answerModel->getAnswerContext($qvId, $userAnswerId);
    if (!$context || empty($context['correct_answer_id'])) {
      $this->analyticsService->trackValidationError(
        $this->currentUser?->getId(),
        'single_question_answer',
        'invalid_question_version'
      );
      $this->sendJsonResponse(['success' => false, 'message' => 'Błąd wersji pytania.'], 404);
      return;
    }

    $isCorrect = (int) $context['is_correct'] === 1;
    $questionId = (int) $context['question_id'];
    $examId = (int) $context['exam_type_id'];
    $response = [
      'success' => true,
      'is_correct' => $isCorrect,
      'correct_answer_id' => (int) $context['correct_answer_id'],
    ];

    // Podwójne kliknięcie / ponowione żądanie nie może podwójnie liczyć odpowiedzi
    // (liczniki postępu, historia, analityka) - zwracamy wynik bez ponownego zapisu.
    if ($this->isDuplicateSingleAnswer($qvId)) {
      $this->sendJsonResponse($response);
      return;
    }

    try {
      $this->analyticsService->trackSingleQuestionAnswered(
        $this->isUserLoggedIn ? $this->currentUser->getId() : null,
        $examId > 0 ? $examId : null,
        $questionId,
        $isCorrect,
        [
          'topic_id' => (int) $context['topic_id'],
          'source' => 'single_question_module',
        ]
      );
    } catch (\Throwable $e) {
      // Analityka nie może blokować sprawdzenia odpowiedzi.
    }

    // Utrwalenie progresu i historii odpowiedzi dla zalogowanych
    if ($this->isUserLoggedIn) {
      $userId = $this->currentUser->getId();
      $this->userProgressModel->saveProgress($userId, $questionId, (int) $isCorrect);

      try {
        $this->singleAnswerModel->record($userId, $questionId, $qvId, $userAnswerId, $isCorrect);
      } catch (\Throwable $e) {
        // Historia nie może blokować sprawdzenia odpowiedzi.
        error_log('[SingleQuestionAnswer] ' . $e->getMessage());
      }
    }

    $this->sendJsonResponse($response);
  }

  /**
   * Czy to ta sama odpowiedź wysłana przed chwilą (podwójne kliknięcie, ponowienie).
   * Zalogowani - wg historii w bazie, goście - wg sesji.
   */
  private function isDuplicateSingleAnswer(int $qvId): bool
  {
    if ($this->isUserLoggedIn) {
      return $this->singleAnswerModel->isRecentDuplicate($this->currentUser->getId(), $qvId);
    }

    $last = $_SESSION['last_single_answer'] ?? null;
    $now = time();
    $_SESSION['last_single_answer'] = ['qv' => $qvId, 'at' => $now];

    return is_array($last)
      && (int) ($last['qv'] ?? 0) === $qvId
      && $now - (int) ($last['at'] ?? 0) < SingleQuestionAnswer::DUPLICATE_WINDOW_SECONDS;
  }

  /**
   * Endpoint: Zapisuje kompletną historię zakończonego egzaminu.
   *
   * Metoda wykonuje operacje atomowe:
   * 1. Rejestracja nagłówka próby w modelu Attempt.
   * 2. Wykonanie Batch Progress Update (aktualizacja statystyk dla wielu pytań naraz).
   *
   * @return void
   */
  public function saveAttempt(): void
  {
    $this->guardWriteRequest('save_attempt', 10);

    if (!$this->isUserLoggedIn) {
      $this->analyticsService->trackValidationError(null, 'save_attempt', 'authentication_required');
      $this->sendJsonResponse(['error' => 'Wymagane uwierzytelnienie'], 403);
      return;
    }

    // Dekodowanie wejścia JSON Raw
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
      $this->analyticsService->trackValidationError(
        $this->currentUser->getId(),
        'save_attempt',
        'invalid_json'
      );
      $this->sendJsonResponse(['error' => 'Nieprawidłowy format danych'], 400);
      return;
    }
    $userId = $this->currentUser->getId();

    $examTypeId = $this->questionModel->getExamTypeIdByCode((string) ($input['examCode'] ?? ''));
    if (!$examTypeId) {
      $this->analyticsService->trackValidationError($userId, 'save_attempt', 'invalid_exam');
      $this->sendJsonResponse(['error' => 'Nieprawidłowy typ egzaminu'], 400);
      return;
    }

    // KROK 1: Weryfikacja odpowiedzi po stronie serwera.
    // Wcześniej zapisywaliśmy wynik (correctAnswers/totalQuestions) i poprawność
    // każdej odpowiedzi dokładnie tak, jak przysłała je przeglądarka - dało się
    // wpisać dowolny wynik i dowolne liczniki postępu. Teraz poprawność liczymy
    // z bazy, a odpowiedź musi należeć do swojej wersji pytania.
    $answersDetails = $this->normalizeAttemptAnswers($input['answers'] ?? null);
    if ($answersDetails === null) {
      $this->analyticsService->trackValidationError($userId, 'save_attempt', 'invalid_answers');
      $this->sendJsonResponse(['error' => 'Nieprawidłowe odpowiedzi w podejściu'], 400);
      return;
    }

    $correctCount = 0;
    foreach ($answersDetails as $detail) {
      $correctCount += $detail['is_correct'];
    }

    $attemptData = [
      'user_id' => $userId,
      'exam_type_id' => $examTypeId,
      'test_type' => ($input['isFullExam'] ?? false) ? 'full_exam' : 'personalized',
      'correct_count' => $correctCount,
      'total_questions' => count($answersDetails),
      // Maks. 4 h - pełny egzamin trwa 60 min, reszta to zapas na pauzy.
      'duration_seconds' => max(0, min(14400, (int) ($input['duration'] ?? 0))),
    ];

    $topicIds = array_values(array_unique(array_filter(
      array_map('intval', is_array($input['topicIds'] ?? null) ? $input['topicIds'] : []),
      static fn(int $id): bool => $id > 0
    )));
    $topicIds = $this->questionModel->filterTopicIdsForExam($topicIds, (int) $examTypeId);

    // KROK 2: Transakcyjny zapis w bazie danych
    $attemptId = $this->attemptModel->createAttempt($attemptData, $topicIds, $answersDetails);

    if ($attemptId) {
      // KROK 3: Masowa aktualizacja statystyk pytań
      $this->userProgressModel->updateBatchProgress($userId, $answersDetails);

      try {
        $score = $attemptData['total_questions'] > 0
          ? round(($attemptData['correct_count'] * 100) / $attemptData['total_questions'], 1)
          : 0;

        $this->analyticsService->trackTestCompleted($userId, $examTypeId, (int) $attemptId, [
          'score' => $score,
          'correct_answers' => (int) $attemptData['correct_count'],
          'wrong_answers' => max(0, (int) $attemptData['total_questions'] - (int) $attemptData['correct_count']),
          'total_questions' => (int) $attemptData['total_questions'],
          'duration_seconds' => (int) $attemptData['duration_seconds'],
          'test_type' => $attemptData['test_type'],
        ]);

      } catch (\Throwable $e) {
        // Analityka nie może blokować zapisu próby.
      }

      $this->sendJsonResponse(['success' => true, 'attempt_id' => $attemptId]);
    } else {
      $this->sendJsonResponse(['error' => 'Błąd krytyczny podczas zapisu próby.'], 500);
    }
  }

  /**
   * Weryfikuje odpowiedzi z podejścia na podstawie bazy.
   *
   * Zwraca listę w kolejności prezentacji: question_version_id, answer_id (null =
   * pominięte), is_correct wyliczone z bazy. Null, gdy dane są nieprawidłowe
   * (zła struktura, za dużo pytań, powtórzone pytanie, odpowiedź spoza pytania).
   *
   * @return array<int, array{question_version_id:int, answer_id:?int, is_correct:int}>|null
   */
  private function normalizeAttemptAnswers(mixed $rawAnswers): ?array
  {
    if (!is_array($rawAnswers) || $rawAnswers === [] || count($rawAnswers) > self::MAX_ATTEMPT_QUESTIONS) {
      return null;
    }

    $normalized = [];
    $pairs = [];
    $versionIds = [];
    foreach (array_values($rawAnswers) as $raw) {
      if (!is_array($raw)) {
        return null;
      }

      $versionId = (int) ($raw['question_version_id'] ?? 0);
      $answerId = isset($raw['answer_id']) && $raw['answer_id'] !== '' ? (int) $raw['answer_id'] : null;
      if ($versionId <= 0 || isset($versionIds[$versionId]) || ($answerId !== null && $answerId <= 0)) {
        return null;
      }

      $versionIds[$versionId] = true;
      if ($answerId !== null) {
        $pairs[$versionId] = $answerId;
      }
      $normalized[] = ['question_version_id' => $versionId, 'answer_id' => $answerId, 'is_correct' => 0];
    }

    // Każda wersja pytania musi istnieć (także te pominięte).
    if ($this->questionModel->countExistingVersions(array_keys($versionIds)) !== count($versionIds)) {
      return null;
    }

    $verified = $this->answerModel->verifyAnswersForVersions($pairs);
    if (count($verified) !== count($pairs)) {
      return null;
    }

    foreach ($normalized as &$item) {
      if ($item['answer_id'] !== null) {
        $item['is_correct'] = $verified[$item['question_version_id']]['is_correct'];
      }
    }
    unset($item);

    return $normalized;
  }

  /**
   * Endpoint: Przełącza stan zapisania pytania w schowku (Toggle).
   */
  public function toggleSavedQuestion(): void
  {
    $this->guardWriteRequest('toggle_saved', 60);

    if (!$this->isUserLoggedIn) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Zaloguj się, aby zarządzać zapisanymi pytaniami.'], 403);
      return;
    }

    $questionId = (int) ($_POST['question_id'] ?? 0);

    if ($questionId <= 0) {
      $this->analyticsService->trackValidationError(
        $this->currentUser->getId(),
        'saved_question',
        'missing_question_id'
      );
      $this->sendJsonResponse(['success' => false, 'message' => 'Brak identyfikatora pytania.'], 400);
      return;
    }

    // Zapisać można tylko istniejące pytanie (saved_questions nie ma klucza obcego).
    if (!$this->questionModel->questionExists($questionId)) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Pytanie nie istnieje.'], 404);
      return;
    }

    $success = $this->savedQuestionModel->toggle($this->currentUser->getId(), $questionId);

    if ($success) {
      $isSaved = $this->savedQuestionModel->isSaved($this->currentUser->getId(), $questionId);
      $question = $this->questionModel->getQuestionById($questionId);
      $this->analyticsService->trackSavedQuestion(
        $this->currentUser->getId(),
        $questionId,
        $question ? (int) ($question['exam_type_id'] ?? 0) : null,
        $question ? (int) ($question['topic_id'] ?? 0) : null,
        $isSaved,
        'question_save_button'
      );
      $this->sendJsonResponse([
        'success' => true,
        'is_saved' => $isSaved,
        'message' => $isSaved ? 'Dodano do zapisanych.' : 'Usunięto z zapisanych.'
      ]);
    } else {
      $this->sendJsonResponse(['success' => false, 'message' => 'Błąd po stronie serwera.'], 500);
    }
  }

  /**
   * Endpoint: Zapis lub aktualizacja notatki użytkownika do arkusza (upsert).
   *
   * Przyjmuje subiektywną ocenę trudności (1-10) oraz opcjonalny opis
   * napotkanych problemów. Wymaga zalogowanego użytkownika oraz istnienia
   * aktywnego arkusza o wskazanym ID.
   */
  public function saveSheetNote(): void
  {
    $this->guardWriteRequest('sheet_note', 20);

    // Biblioteka Arkuszy nie jest jeszcze udostępniona użytkownikom — endpoint tymczasowo wyłączony
    $this->sendJsonResponse(['success' => false, 'message' => 'Ta funkcja nie jest jeszcze dostępna.'], 404);
    return;

    if (!$this->isUserLoggedIn) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Zaloguj się, aby zapisywać notatki do arkuszy.'], 403);
      return;
    }

    $sheetId = (int) ($_POST['sheet_id'] ?? 0);
    $difficulty = (int) ($_POST['difficulty'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($sheetId <= 0) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Brak identyfikatora arkusza.'], 400);
      return;
    }

    if ($difficulty < 1 || $difficulty > 10) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Oceń trudność arkusza w skali od 1 do 10.'], 400);
      return;
    }

    if (mb_strlen($description) > 5000) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Opis notatki może mieć maksymalnie 5000 znaków.'], 400);
      return;
    }

    $sheetModel = new Sheet();
    if (!$sheetModel->findActiveById($sheetId)) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Arkusz nie istnieje lub jest niedostępny.'], 404);
      return;
    }

    $noteModel = new SheetNote();
    if (!$noteModel->upsert($this->currentUser->getId(), $sheetId, $difficulty, $description)) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Błąd po stronie serwera.'], 500);
      return;
    }

    $this->sendJsonResponse([
      'success' => true,
      'message' => 'Notatka została zapisana.',
      'difficulty' => $difficulty,
    ]);
  }

  /**
   * Endpoint: Aktualizacja awatara użytkownika (Custom Base64 lub Default).
   */
  public function updateAvatar(): void
  {
    $this->guardWriteRequest('avatar', 10);

    if (!$this->isUserLoggedIn) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Wymagane uwierzytelnienie.'], 403);
      return;
    }

    $rawInput = file_get_contents('php://input');
    // Base64 powiększa plik o ~1/3 - limit z zapasem na nagłówek data URL.
    if (!is_string($rawInput) || strlen($rawInput) > (int) (self::MAX_AVATAR_BYTES * 1.4)) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Plik jest za duży (maks. 2 MB).'], 413);
      return;
    }

    $input = json_decode($rawInput, true);
    if (!is_array($input) || !is_string($input['type'] ?? null) || !is_string($input['avatar'] ?? null)) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Brak wymaganych parametrów.'], 400);
      return;
    }

    $userId = $this->currentUser->getId();
    $type = $input['type']; // 'default' lub 'custom'
    $avatarData = $input['avatar'];

    $newAvatarPath = null;
    $avatarsDir = $_SERVER['DOCUMENT_ROOT'] . '/images/avatars/';

    // Upewnij się, że katalog istnieje
    if (!is_dir($avatarsDir)) {
      mkdir($avatarsDir, 0755, true);
    }

    if ($type === 'default') {
      // Walidacja czy plik domyślny istnieje
      $defaultPath = 'defaults/' . basename($avatarData);
      if (preg_match('/^[a-z0-9_-]+\.(png|jpe?g|webp|svg)$/i', basename($avatarData)) && is_file($avatarsDir . $defaultPath)) {
        $newAvatarPath = $defaultPath;
      } else {
        $this->sendJsonResponse(['success' => false, 'message' => 'Nieprawidłowy awatar domyślny.'], 400);
        return;
      }
    } elseif ($type === 'custom') {
      // Obsługa Base64 (WebP/JPEG/PNG). Typ i wymiary sprawdzamy na samych bajtach
      // pliku - wcześniej zapisywaliśmy dowolną zawartość z rozszerzeniem podanym
      // przez przeglądarkę i bez limitu rozmiaru.
      if (!preg_match('/^data:image\/[a-z]+;base64,/', $avatarData)) {
        $this->sendJsonResponse(['success' => false, 'message' => 'Nieprawidłowy format obrazu.'], 400);
        return;
      }

      $data = base64_decode(substr($avatarData, strpos($avatarData, ',') + 1), true);
      if ($data === false || $data === '') {
        $this->sendJsonResponse(['success' => false, 'message' => 'Błąd dekodowania obrazu.'], 400);
        return;
      }

      if (strlen($data) > self::MAX_AVATAR_BYTES) {
        $this->sendJsonResponse(['success' => false, 'message' => 'Plik jest za duży (maks. 2 MB).'], 413);
        return;
      }

      $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
      $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: '';
      $imageInfo = @getimagesizefromstring($data);
      if (
        !isset($allowedTypes[$mimeType])
        || $imageInfo === false
        || ($imageInfo['mime'] ?? '') !== $mimeType
        || $imageInfo[0] < 16 || $imageInfo[1] < 16
        || $imageInfo[0] > 4096 || $imageInfo[1] > 4096
      ) {
        $this->sendJsonResponse(['success' => false, 'message' => 'Dozwolone są obrazy JPG, PNG lub WebP do 4096 px.'], 400);
        return;
      }

      $filename = 'custom_' . $userId . '_' . time() . '.' . $allowedTypes[$mimeType];
      $newAvatarPath = $filename;

      if (file_put_contents($avatarsDir . $filename, $data) === false) {
        $this->sendJsonResponse(['success' => false, 'message' => 'Błąd zapisu pliku na serwerze.'], 500);
        return;
      }
    } else {
      $this->sendJsonResponse(['success' => false, 'message' => 'Nieznany typ operacji.'], 400);
      return;
    }

    // Usunięcie starego customowego awatara
    $oldAvatar = $this->currentUser->getAvatarPath();
    if ($oldAvatar && strpos($oldAvatar, 'custom_') === 0) {
      $oldFile = $avatarsDir . $oldAvatar;
      if (file_exists($oldFile)) {
        unlink($oldFile);
      }
    }

    // Aktualizacja w bazie
    if ($this->userModel->updateAvatar($userId, $newAvatarPath)) {
      $this->analyticsService->trackSettingsUpdated($userId, 'avatar_updated');
      $this->sendJsonResponse([
        'success' => true,
        'message' => 'Awatar zaktualizowany.',
        'avatar_path' => $newAvatarPath
      ]);
    } else {
      $this->sendJsonResponse(['success' => false, 'message' => 'Błąd aktualizacji bazy danych.'], 500);
    }
  }

  /**
   * Endpoint: Zgłoszenie błędu w pytaniu.
   *
   * Dostępny także dla gości (błąd może zauważyć każdy). Ochrona przed
   * nadużyciami: token CSRF w nagłówku X-CSRF-Token, limit zgłoszeń na sesję
   * oraz blokada ponownego zgłoszenia tego samego pytania (sesja / otwarte
   * zgłoszenie zalogowanego użytkownika).
   */
  public function reportQuestion(): void
  {
    // Wspólna ochrona endpointów zapisujących (POST + CSRF + limit na minutę);
    // niżej dodatkowy limit 10 zgłoszeń na 10 minut i jedno zgłoszenie pytania na sesję.
    $this->guardWriteRequest('report_question', 20);

    $rawInput = file_get_contents('php://input');
    if (!is_string($rawInput) || strlen($rawInput) > 8192) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Zgłoszenie jest zbyt długie.'], 413);
      return;
    }

    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Nieprawidłowe dane zgłoszenia.'], 400);
      return;
    }

    $questionId = (int) ($input['question_id'] ?? 0);
    $category = (string) ($input['category'] ?? '');
    $message = trim((string) ($input['message'] ?? ''));
    $context = (string) ($input['context'] ?? '');

    if (!isset(QuestionReport::CATEGORIES[$category])) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Wybierz, czego dotyczy błąd.'], 400);
      return;
    }
    if ($category === 'other' && $message === '') {
      $this->sendJsonResponse(['success' => false, 'message' => 'Opisz krótko, co jest nie tak.'], 400);
      return;
    }
    if (mb_strlen($message) > QuestionReport::MESSAGE_MAX_LENGTH) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Opis może mieć maksymalnie ' . QuestionReport::MESSAGE_MAX_LENGTH . ' znaków.'], 400);
      return;
    }

    $question = $questionId > 0 ? $this->questionModel->getQuestionById($questionId) : false;
    if (!$question) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Nie znaleziono pytania.'], 404);
      return;
    }

    // Jedno pytanie zgłaszamy raz na sesję - chroni przed podwójnym kliknięciem i spamem.
    $reportedInSession = is_array($_SESSION['reported_questions'] ?? null) ? $_SESSION['reported_questions'] : [];
    $userId = $this->currentUser?->getId();
    $reportModel = new QuestionReport();

    if (in_array($questionId, $reportedInSession, true) || ($userId && $reportModel->hasOpenReport($userId, $questionId))) {
      $this->sendJsonResponse(['success' => false, 'message' => 'To pytanie zostało już przez Ciebie zgłoszone. Dziękujemy!'], 409);
      return;
    }

    // Limit: 10 zgłoszeń na 10 minut w obrębie jednej sesji.
    $windowStart = (int) ($_SESSION['report_window'] ?? 0);
    $windowCount = (int) ($_SESSION['report_count'] ?? 0);
    if ($windowStart === 0 || time() - $windowStart >= 600) {
      $windowStart = time();
      $windowCount = 0;
    }
    if ($windowCount >= 10) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Wysłano zbyt wiele zgłoszeń. Spróbuj ponownie za kilka minut.'], 429);
      return;
    }

    $reportId = $reportModel->create(
      $questionId,
      isset($question['question_version_id']) ? (int) $question['question_version_id'] : null,
      $userId,
      $category,
      $message !== '' ? $message : null,
      isset(QuestionReport::CONTEXTS[$context]) ? $context : null
    );

    if ($reportId === null) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Nie udało się wysłać zgłoszenia. Spróbuj ponownie.'], 500);
      return;
    }

    $_SESSION['report_window'] = $windowStart;
    $_SESSION['report_count'] = $windowCount + 1;
    $reportedInSession[] = $questionId;
    $_SESSION['reported_questions'] = array_slice($reportedInSession, -200);

    $this->sendJsonResponse([
      'success' => true,
      'message' => 'Dziękujemy! Zgłoszenie trafiło do redakcji.',
    ]);
  }

  /**
   * Przyjmuje wyłącznie bezpieczne eventy interakcji z modułów JavaScript.
   */
  public function trackAnalyticsEventFromClient(): void
  {
    $this->ensurePostRequest();

    $rawInput = file_get_contents('php://input');
    if (!is_string($rawInput) || strlen($rawInput) > 4096) {
      $this->sendJsonResponse(['success' => false], 413);
      return;
    }

    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
      $this->sendJsonResponse(['success' => false], 400);
      return;
    }

    // navigator.sendBeacon nie pozwala ustawić nagłówka, więc token CSRF
    // może przyjść także w treści żądania.
    $this->requireCsrf(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null);
    $this->enforceRateLimit('analytics_event', 120);

    $eventType = trim((string) ($input['event_type'] ?? ''));
    $allowedClientEvents = [
      'test_exited',
      'explanation_viewed',
      'answer_changed',
      'validation_error',
      'test_completed',
    ];
    if (!in_array($eventType, $allowedClientEvents, true)) {
      $this->sendJsonResponse(['success' => false], 400);
      return;
    }

    // Ukończenie testu przez zalogowanego rejestruje serwer przy zapisie podejścia
    // (saveAttempt). Z przeglądarki przyjmujemy je tylko od gości, którzy nie mają
    // gdzie zapisać podejścia - inaczej zalogowani byliby liczeni podwójnie.
    if ($eventType === 'test_completed' && $this->isUserLoggedIn) {
      $this->sendJsonResponse(['success' => false], 400);
      return;
    }

    $metadata = is_array($input['metadata'] ?? null) ? array_slice($input['metadata'], 0, 20, true) : [];
    $success = $this->analyticsService->trackEvent(
      $eventType,
      $this->currentUser?->getId(),
      $metadata
    );

    $this->sendJsonResponse(['success' => $success], $success ? 200 : 202);
  }

  // --- LOGIKA WEWNĘTRZNA (PRIVATE HELPERS) ---

  /**
   * Centralna logika filtrowania i pobierania rekordów z bazy pytań.
   *
   * Realizuje:
   * 1. Detekcję filtrów tematycznych.
   * 2. Egzekwowanie polityki dostępu "Premium" (wymóg zalogowania).
   * 3. Obsługę mechanizmu Gatekeeper (blokada modów nauki do momentu zebrania 100 odpowiedzi).
   * 4. Wybór odpowiedniego algorytmu z modelu Question (Spaced Repetition, Discovery, Accuracy).
   * 5. Pobieranie danych o progresie ogólnym puli (Pool Progress).
   *
   * @param string $examCode Kod egzaminu.
   * @param int $limit Liczba pytań do pobrania.
   * @return array [success => bool, questions => array, pool_progress => array]
   */
  private function fetchFilteredQuestions(string $examCode, int $limit): array
  {
    $rawSubjects = $_GET['subject'] ?? [];
    $subjectIds = is_array($rawSubjects)
      ? array_values(array_unique(array_filter(
          array_map('intval', array_slice($rawSubjects, 0, self::MAX_TOPICS_PER_REQUEST)),
          static fn(int $id): bool => $id > 0
        )))
      : [];

    if (!$examCode || empty($subjectIds)) {
      $this->analyticsService->trackValidationError(
        $this->currentUser?->getId(),
        'question_filters',
        'missing_topics'
      );
      $this->sendJsonResponse(['success' => false, 'message' => 'Nie wybrano żadnego tematu egzaminacyjnego.'], 400);
      return ['success' => false, 'questions' => []];
    }

    $examTypeId = $this->questionModel->getExamTypeIdByCode($examCode);
    if (!$examTypeId) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Nieprawidłowy kod egzaminu.'], 404);
      return ['success' => false, 'questions' => []];
    }

    $allowedFilters = ['toDiscover', 'toImprove', 'toRemind', 'lastMistakes', 'saved'];
    $specialFilter = $_GET['premium_option'] ?? null;
    $specialFilter = is_string($specialFilter) && in_array($specialFilter, $allowedFilters, true) ? $specialFilter : null;

    // KROK: Weryfikacja uprawnień do filtrów zaawansowanych
    if ($specialFilter && !$this->isUserLoggedIn) {
      $this->analyticsService->trackValidationError(null, 'question_filters', 'authentication_required', [
        'exam_id' => $examTypeId,
      ]);
      $this->sendJsonResponse(['success' => false, 'message' => 'Filtry zaawansowane wymagają konta użytkownika.'], 403);
      return ['success' => false, 'questions' => []];
    }

    $userId = $this->isUserLoggedIn ? $this->currentUser->getId() : null;

    // KROK: Mechanizm Gatekeeper (Wymagana próbka danych dla precyzyjnych algorytmów)
    if ($specialFilter === 'toImprove' || $specialFilter === 'toRemind') {
      if ($userId) {
        $answeredCount = $this->userProgressModel->countAnsweredQuestionsForExam($userId, $examTypeId);
        if ($answeredCount < 100) {
          $this->analyticsService->trackValidationError($userId, 'question_filters', 'gatekeeper', [
            'exam_id' => $examTypeId,
            'count' => $answeredCount,
          ]);
          $gatekeeperMessage = $specialFilter === 'toRemind'
            ? 'Musisz odpowiedzieć na co najmniej 100 pytań, aby dobrać pytania dawno niepowtarzane.'
            : 'Musisz odpowiedzieć na co najmniej 100 pytań, aby przeanalizować Twoje słabe strony.';
          $this->sendJsonResponse([
            'success' => false,
            'status' => 'gatekeeper',
            'message' => $gatekeeperMessage
          ], 403);
          return ['success' => false, 'questions' => []];
        }
      }
    }

    // KROK: Wybór algorytmu losowania
    $questions = [];
    if ($specialFilter && $this->isUserLoggedIn) {
      $questions = match ($specialFilter) {
        'toDiscover' => $this->questionModel->getUndiscoveredQuestions($userId, $subjectIds, $limit, $examTypeId),
        'toImprove' => $this->questionModel->getLowerAccuracyQuestions($userId, $subjectIds, $limit, $examTypeId),
        'toRemind' => $this->questionModel->getQuestionsRepeatedAtTheLatest($userId, $subjectIds, $limit, $examTypeId),
        'lastMistakes' => $this->questionModel->getLastMistakes($userId, $subjectIds, $limit, $examTypeId),
        'saved' => $this->questionModel->getSavedQuestions($userId, $subjectIds, $limit, $examTypeId),
        default => $this->questionModel->getQuestions($subjectIds, $limit, $examTypeId),
      };
    } else {
      $questions = $this->questionModel->getQuestions($subjectIds, $limit, $examTypeId);
    }

    $poolProgress = null;
    if ($this->isUserLoggedIn) {
      $poolProgress = $this->questionModel->getPoolProgress($userId, $subjectIds, $examTypeId);
    }

    return ['success' => true, 'questions' => $questions, 'pool_progress' => $poolProgress];
  }

  /**
   * Zapewnia, że żądanie wywołano metodą POST.
   */
  private function ensurePostRequest(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->sendJsonResponse(['success' => false, 'message' => 'Metoda niedozwolona (Wymagany POST).'], 405);
      exit();
    }
  }

  /**
   * Zapewnia, że żądanie wywołano metodą GET.
   */
  private function ensureGetRequest(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      $this->sendJsonResponse(['success' => false, 'message' => 'Metoda niedozwolona (Wymagany GET).'], 405);
      exit();
    }
  }

  /**
   * Wspólna ochrona endpointów zapisujących: tylko POST, poprawny token CSRF
   * (nagłówek X-CSRF-Token) i limit żądań na sesję.
   *
   * Ciasteczko sesji ma SameSite=Lax, więc obce strony i tak nie wyślą go
   * w żądaniu POST - token to druga, niezależna warstwa.
   */
  private function guardWriteRequest(string $rateKey, int $maxPerMinute): void
  {
    $this->ensurePostRequest();
    $this->requireCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null));
    $this->enforceRateLimit($rateKey, $maxPerMinute);
  }

  /**
   * Przerywa żądanie z kodem 403, gdy token CSRF jest nieprawidłowy.
   */
  private function requireCsrf(?string $token): void
  {
    $token ??= $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!(new CsrfService())->validate(is_string($token) ? $token : null)) {
      $this->sendJsonResponse([
        'success' => false,
        'status' => 'csrf',
        'message' => 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.',
      ], 403);
    }
  }

  /**
   * Prosty limit żądań w oknie 60 s na sesję (chroni liczniki i analitykę
   * przed zalewaniem z jednej karty/skryptu). Przekroczenie = 429.
   */
  private function enforceRateLimit(string $key, int $maxPerMinute): void
  {
    $now = time();
    $bucket = $_SESSION['rate_limit'][$key] ?? null;
    if (!is_array($bucket) || $now - (int) ($bucket['start'] ?? 0) >= 60) {
      $bucket = ['start' => $now, 'count' => 0];
    }

    if ((int) $bucket['count'] >= $maxPerMinute) {
      $this->sendJsonResponse([
        'success' => false,
        'status' => 'rate_limited',
        'message' => 'Za dużo żądań w krótkim czasie. Odczekaj chwilę.',
      ], 429);
    }

    $bucket['count'] = (int) $bucket['count'] + 1;
    $_SESSION['rate_limit'][$key] = $bucket;
  }

  /**
   * Wysyła standardową odpowiedź JSON i kończy wykonanie skryptu.
   *
   * @param array $data Dane do wysłania.
   * @param int $statusCode Kod HTTP.
   */
  private function sendJsonResponse(array $data, int $statusCode = 200): void
  {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
  }
}
