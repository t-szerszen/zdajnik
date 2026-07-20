<?php

namespace App\Controllers;

use App\Models\Question;
use App\Models\Answer;
use App\Models\UserProgress;
use App\Models\Attempt;
use App\Models\SavedQuestion;
use App\Models\Sheet;
use App\Models\SheetNote;
use App\Models\UserModel;
use App\Services\AnalyticsService;

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
      $this->sendJsonResponse([
        'success' => true,
        'status' => 'no_questions_left',
        'message' => 'Gratulacje! Przerobiłeś już wszystkie dostępne pytania w tej sekcji.',
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
      $this->sendJsonResponse([
        'success' => true,
        'status' => 'no_questions_left',
        'message' => 'Baza posiada zbyt mało pytań spełniających Twoje kryteria filtrowania.',
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

    foreach ($questions as $question) {
      $answers = $this->answerModel->getAnswersByVersionId($question['question_version_id']);
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

    foreach ($questions as $question) {
      $answers = $this->answerModel->getAnswersByVersionId($question['question_version_id']);
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
    $this->ensurePostRequest();

    $qvId = (int) ($_POST['question_version_id'] ?? 0);
    $userAnswerId = (int) ($_POST['answer_id'] ?? 0);

    if (!$qvId || !$userAnswerId) {
      $this->analyticsService->trackValidationError(
        $this->currentUser?->getId(),
        'single_question_answer',
        'missing_parameters'
      );
      $this->sendJsonResponse(['success' => false, 'message' => 'Brak wymaganych parametrów (version_id/answer_id).'], 400);
      return;
    }

    // Pobranie wzorcowej poprawnej odpowiedzi dla danej wersji
    $correctAnswer = $this->answerModel->getCorrectAnswerForVersion($qvId);
    if (!$correctAnswer) {
      $this->analyticsService->trackValidationError(
        $this->currentUser?->getId(),
        'single_question_answer',
        'invalid_question_version'
      );
      $this->sendJsonResponse(['success' => false, 'message' => 'Błąd wersji pytania.'], 404);
      return;
    }

    $isCorrect = $userAnswerId === (int) $correctAnswer['id'];

    try {
      $questionId = $this->questionModel->getQuestionIdByVersionId($qvId);
      if ($questionId) {
        $question = $this->questionModel->getQuestionById((int) $questionId);
        $examId = (int) ($question['exam_type_id'] ?? 0);
        $this->analyticsService->trackSingleQuestionAnswered(
          $this->isUserLoggedIn ? $this->currentUser->getId() : null,
          $examId > 0 ? $examId : null,
          (int) $questionId,
          $isCorrect,
          [
            'topic_id' => isset($question['topic_id']) ? (int) $question['topic_id'] : null,
            'source' => 'single_question_module',
          ]
        );
      }
    } catch (\Throwable $e) {
      // Analityka nie może blokować sprawdzenia odpowiedzi.
    }

    // Utrwalenie progresu dla zalogowanych (Micro-save)
    if ($this->isUserLoggedIn) {
      $questionId = $this->questionModel->getQuestionIdByVersionId($qvId);
      if ($questionId) {
        $this->userProgressModel->saveProgress($this->currentUser->getId(), $questionId, (int) $isCorrect);
      }
    }

    $this->sendJsonResponse([
      'success' => true,
      'is_correct' => $isCorrect,
      'correct_answer_id' => (int) $correctAnswer['id'],
    ]);
  }

  /**
   * Endpoint: Wykonuje masowy zapis progresu (Bulk Save).
   * Używane głównie przy synchronizacji stanu po trybach offline lub długich sesjach.
   */
  public function saveProgressBulk(): void
  {
    $this->ensurePostRequest();

    if (!$this->isUserLoggedIn) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Funkcja dostępna tylko dla zalogowanych.'], 403);
      return;
    }

    $progressData = json_decode(file_get_contents('php://input'), true);
    if (!is_array($progressData)) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Nieprawidłowy format danych JSON.'], 400);
      return;
    }

    $userId = $this->currentUser->getId();

    foreach ($progressData as $progressItem) {
      if (isset($progressItem['questionId'], $progressItem['isCorrect'])) {
        $this->userProgressModel->saveProgress($userId, (int) $progressItem['questionId'], $progressItem['isCorrect'] ? 1 : 0);
      }
    }

    $this->sendJsonResponse(['success' => true, 'message' => 'Postęp zaktualizowany pomyślnie.']);
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
    $this->ensurePostRequest();

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

    $examTypeId = $this->questionModel->getExamTypeIdByCode($input['examCode'] ?? 'INF.03');
    if (!$examTypeId) {
      $this->analyticsService->trackValidationError($userId, 'save_attempt', 'invalid_exam');
      $this->sendJsonResponse(['error' => 'Nieprawidłowy typ egzaminu'], 400);
      return;
    }

    // KROK 1: Przygotowanie paczki danych historycznych
    $attemptData = [
      'user_id' => $userId,
      'exam_type_id' => $examTypeId,
      'test_type' => ($input['isFullExam'] ?? false) ? 'full_exam' : 'personalized',
      'correct_count' => (int) ($input['correctAnswers'] ?? 0),
      'total_questions' => (int) ($input['totalQuestions'] ?? 0),
      'duration_seconds' => (int) ($input['duration'] ?? 0)
    ];

    $answersDetails = $input['answers'] ?? [];

    // KROK 2: Transakcyjny zapis w bazie danych
    $attemptId = $this->attemptModel->createAttempt($attemptData, $input['topicIds'] ?? [], $answersDetails);

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

        $this->analyticsService->trackQuestionAnsweredBatch(
          $userId,
          $examTypeId,
          (int) $attemptId,
          $answersDetails,
          $attemptData['test_type']
        );
      } catch (\Throwable $e) {
        // Analityka nie może blokować zapisu próby.
      }

      $this->sendJsonResponse(['success' => true, 'attempt_id' => $attemptId]);
    } else {
      $this->sendJsonResponse(['error' => 'Błąd krytyczny podczas zapisu próby.'], 500);
    }
  }

  /**
   * Endpoint: Przełącza stan zapisania pytania w schowku (Toggle).
   */
  public function toggleSavedQuestion(): void
  {
    $this->ensurePostRequest();

    if (!$this->isUserLoggedIn) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Zaloguj się, aby zarządzać zapisanymi pytaniami.'], 403);
      return;
    }

    $questionId = (int) ($_POST['question_id'] ?? 0);

    if (!$questionId) {
      $this->analyticsService->trackValidationError(
        $this->currentUser->getId(),
        'saved_question',
        'missing_question_id'
      );
      $this->sendJsonResponse(['success' => false, 'message' => 'Brak identyfikatora pytania.'], 400);
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
    $this->ensurePostRequest();

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
    $this->ensurePostRequest();

    if (!$this->isUserLoggedIn) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Wymagane uwierzytelnienie.'], 403);
      return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['type']) || !isset($input['avatar'])) {
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
      if (file_exists($avatarsDir . $defaultPath)) {
        $newAvatarPath = $defaultPath;
      } else {
        $this->sendJsonResponse(['success' => false, 'message' => 'Nieprawidłowy awatar domyślny.'], 400);
        return;
      }
    } elseif ($type === 'custom') {
      // Obsługa Base64 (WebP/JPEG)
      if (preg_match('/^data:image\/(\w+);base64,/', $avatarData, $typeMatch)) {
        $data = substr($avatarData, strpos($avatarData, ',') + 1);
        $data = base64_decode($data);

        if ($data === false) {
          $this->sendJsonResponse(['success' => false, 'message' => 'Błąd dekodowania obrazu.'], 400);
          return;
        }

        // Generowanie unikalnej nazwy
        $extension = $typeMatch[1] === 'jpeg' ? 'jpg' : $typeMatch[1];
        if (!in_array($extension, ['jpg', 'png', 'webp'])) {
          $extension = 'webp'; // fallback
        }

        $filename = 'custom_' . $userId . '_' . time() . '.' . $extension;
        $newAvatarPath = $filename;

        if (file_put_contents($avatarsDir . $filename, $data) === false) {
          $this->sendJsonResponse(['success' => false, 'message' => 'Błąd zapisu pliku na serwerze.'], 500);
          return;
        }
      } else {
        $this->sendJsonResponse(['success' => false, 'message' => 'Nieprawidłowy format obrazu.'], 400);
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

    $windowStart = (int) ($_SESSION['analytics_event_window'] ?? 0);
    $windowCount = (int) ($_SESSION['analytics_event_count'] ?? 0);
    if ($windowStart === 0 || time() - $windowStart >= 60) {
      $windowStart = time();
      $windowCount = 0;
    }
    if ($windowCount >= 120) {
      $this->sendJsonResponse(['success' => false], 429);
      return;
    }
    $_SESSION['analytics_event_window'] = $windowStart;
    $_SESSION['analytics_event_count'] = $windowCount + 1;

    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
      $this->sendJsonResponse(['success' => false], 400);
      return;
    }

    $eventType = trim((string) ($input['event_type'] ?? ''));
    $allowedClientEvents = [
      'test_exited',
      'explanation_viewed',
      'answer_changed',
      'validation_error',
    ];
    if (!in_array($eventType, $allowedClientEvents, true)) {
      $this->sendJsonResponse(['success' => false], 400);
      return;
    }

    $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
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
    $subjectIds = $_GET['subject'] ?? [];

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
    $subjectIds = array_map('intval', $subjectIds);
    $specialFilter = $_GET['premium_option'] ?? null;

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
