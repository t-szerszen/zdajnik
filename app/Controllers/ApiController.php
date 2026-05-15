<?php

namespace App\Controllers;

use App\Models\Question;
use App\Models\Answer;
use App\Models\UserProgress;
use App\Models\Attempt;
use App\Models\SavedQuestion;
use App\Models\UserModel;

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

    // Obsługa scenariusza, w którym brak pytań spełniających filtry (np. wszystko odkryte)
    if (!$question) {
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

    $result = $this->fetchFilteredQuestions($examCode, $limit);

    if (!$result['success']) {
      return;
    }

    $questions = $result['questions'];

    if (count($questions) < $limit) {
      $this->sendJsonResponse([
        'success' => true,
        'status' => 'no_questions_left',
        'message' => 'Baza posiada zbyt mało pytań spełniających Twoje kryteria filtrowania.',
      ]);
      return;
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
      $this->sendJsonResponse(['success' => false, 'message' => 'Brak wymaganych parametrów (version_id/answer_id).'], 400);
      return;
    }

    // Pobranie wzorcowej poprawnej odpowiedzi dla danej wersji
    $correctAnswer = $this->answerModel->getCorrectAnswerForVersion($qvId);
    if (!$correctAnswer) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Błąd wersji pytania.'], 404);
      return;
    }

    $isCorrect = $userAnswerId === (int) $correctAnswer['id'];

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
      $this->sendJsonResponse(['error' => 'Wymagane uwierzytelnienie'], 403);
      return;
    }

    // Dekodowanie wejścia JSON Raw
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $this->currentUser->getId();

    $examTypeId = $this->questionModel->getExamTypeIdByCode($input['examCode'] ?? 'INF.03');
    if (!$examTypeId) {
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
      $this->sendJsonResponse(['success' => false, 'message' => 'Brak identyfikatora pytania.'], 400);
      return;
    }

    $success = $this->savedQuestionModel->toggle($this->currentUser->getId(), $questionId);

    if ($success) {
      $isSaved = $this->savedQuestionModel->isSaved($this->currentUser->getId(), $questionId);
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
      $this->sendJsonResponse([
        'success' => true, 
        'message' => 'Awatar zaktualizowany.',
        'avatar_path' => $newAvatarPath
      ]);
    } else {
      $this->sendJsonResponse(['success' => false, 'message' => 'Błąd aktualizacji bazy danych.'], 500);
    }
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
      $this->sendJsonResponse(['success' => false, 'message' => 'Nie wybrano żadnego tematu egzaminacyjnego.'], 400);
      return ['success' => false, 'questions' => []];
    }

    $examTypeId = $this->questionModel->getExamTypeIdByCode($examCode);
    $subjectIds = array_map('intval', $subjectIds);
    $specialFilter = $_GET['premium_option'] ?? null;

    // KROK: Weryfikacja uprawnień do filtrów zaawansowanych
    if ($specialFilter && !$this->isUserLoggedIn) {
      $this->sendJsonResponse(['success' => false, 'message' => 'Filtry zaawansowane wymagają konta użytkownika.'], 403);
      return ['success' => false, 'questions' => []];
    }

    $userId = $this->isUserLoggedIn ? $this->currentUser->getId() : null;

    // KROK: Mechanizm Gatekeeper (Wymagana próbka danych dla precyzyjnych algorytmów)
    if ($specialFilter === 'toImprove' || $specialFilter === 'toRemind') {
      if ($userId) {
        $answeredCount = $this->userProgressModel->countAnsweredQuestionsForExam($userId, $examTypeId);
        if ($answeredCount < 100) {
          $this->sendJsonResponse([
            'success' => false,
            'status' => 'gatekeeper',
            'message' => 'Musisz odpowiedzieć na co najmniej 100 pytań, abyśmy mogli przeanalizować Twoje słabe strony.'
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
