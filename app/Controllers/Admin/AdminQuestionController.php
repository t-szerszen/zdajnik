<?php

namespace App\Controllers\Admin;

use App\Services\AdminDashboardService;
use App\Services\AdminQuestionService;

/**
 * Moduł pytań panelu admina — CRUD z wersjonowaniem treści.
 *
 * @package App\Controllers\Admin
 */
class AdminQuestionController extends AdminBaseController
{
  private AdminDashboardService $adminService;
  private AdminQuestionService $questionService;

  public function __construct()
  {
    parent::__construct();
    $this->adminService = new AdminDashboardService();
    $this->questionService = new AdminQuestionService();
  }

  protected function authorize(): void
  {
    $this->requireContentManager();
  }

  /**
   * Lista pytań.
   */
  public function questions(): void
  {
    ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'page' => $page] = $this->extractListParams();
    $result = $this->questionService->searchQuestions($filters, $page, 20, $sort, $dir);

    $this->renderView('admin/questions', [
      'pageTitle' => 'Pytania | Admin Panel | Zdajnik',
      'pageDescription' => 'Zarządzanie pytaniami z wersjonowaniem w panelu CMS Zdajnik.',
      'overview' => $this->adminService->getOverview(),
      'questions' => $result['items'],
      'filters' => $filters,
      'sort' => $sort,
      'dir' => $dir,
      'pagination' => $result,
      'exams' => $this->questionService->getExams(),
      'topics' => $this->questionService->getTopicsForFilter(
        ctype_digit((string) $filters['exam_id']) ? (int) $filters['exam_id'] : null
      ),
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Endpoint JSON listy pytań (filtry/sort/paginacja bez przeładowania).
   */
  public function search(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      $this->jsonResponse(['ok' => false, 'message' => 'Metoda niedozwolona.'], 405);
    }

    ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'page' => $page] = $this->extractListParams();
    $result = $this->questionService->searchQuestions($filters, $page, 20, $sort, $dir);

    $this->jsonResponse([
      'ok' => true,
      'html' => $this->renderPartialToString('admin/partials/questions_rows', ['questions' => $result['items']]),
      'meta' => [
        'total' => $result['total'],
        'page' => $result['page'],
        'perPage' => $result['perPage'],
        'totalPages' => $result['totalPages'],
      ],
    ]);
  }

  /**
   * Akcje zbiorcze na pytaniach (aktywacja/dezaktywacja).
   */
  public function bulk(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->jsonResponse(['ok' => false, 'message' => 'Metoda niedozwolona.'], 405);
    }

    if (!$this->validateAjaxCsrf()) {
      $this->jsonResponse(['ok' => false, 'message' => 'Nieprawidłowy token bezpieczeństwa.'], 403);
    }

    $payload = json_decode((string) file_get_contents('php://input'), true);
    $payload = is_array($payload) ? $payload : [];

    $action = (string) ($payload['action'] ?? '');
    if (!in_array($action, ['activate', 'deactivate'], true)) {
      $this->jsonResponse(['ok' => false, 'message' => 'Nieznana akcja zbiorcza.'], 400);
    }

    $ids = array_values(array_unique(array_map('intval', (array) ($payload['ids'] ?? []))));
    $ids = array_slice(array_filter($ids, static fn(int $id): bool => $id > 0), 0, 500);
    if (empty($ids)) {
      $this->jsonResponse(['ok' => false, 'message' => 'Nie zaznaczono żadnych pytań.'], 400);
    }

    $result = $this->questionService->bulkQuestionAction($ids, $action, $this->currentUser->getId());

    $label = $action === 'activate' ? 'aktywowano' : 'zdezaktywowano';
    $this->jsonResponse([
      'ok' => true,
      'succeeded' => $result['succeeded'],
      'failed' => $result['failed'],
      'message' => sprintf('Pomyślnie %s %d z %d pytań.', $label, count($result['succeeded']), count($ids)),
    ]);
  }

  /**
   * Wspólna ekstrakcja filtrów listy z query stringa.
   *
   * @return array{filters: array<string, string>, sort: string, dir: string, page: int}
   */
  private function extractListParams(): array
  {
    return [
      'filters' => [
        'query' => trim((string) ($_GET['q'] ?? '')),
        'exam_id' => trim((string) ($_GET['exam_id'] ?? '')),
        'topic_id' => trim((string) ($_GET['topic_id'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'difficulty' => trim((string) ($_GET['difficulty'] ?? '')),
      ],
      'sort' => (string) ($_GET['sort'] ?? 'id'),
      'dir' => (string) ($_GET['dir'] ?? 'desc'),
      'page' => max(1, (int) ($_GET['page'] ?? 1)),
    ];
  }

  /**
   * Formularz dodawania pytania.
   */
  public function showCreateQuestionForm(): void
  {
    $examId = max(0, (int) ($_GET['exam_id'] ?? 0));
    $topicId = max(0, (int) ($_GET['topic_id'] ?? 0));
    $this->renderQuestionForm('create', [
      'exam_type_id' => $examId,
      'topic_id' => $topicId,
      'source' => '',
      'difficulty' => 'medium',
      'question_text' => '',
      'explanation' => '',
      'is_active' => 1,
      'remove_image' => 0,
      'answers' => $this->buildDefaultAnswers(),
    ]);
  }

  /**
   * Zapisuje nowe pytanie.
   */
  public function storeQuestion(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/pytania'));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->renderQuestionForm('create', $this->extractQuestionPayload($_POST), ['csrf' => 'Nieprawidłowy token bezpieczeństwa.']);
      return;
    }

    $payload = $this->extractQuestionPayload($_POST);
    $result = $this->questionService->createQuestion($payload, $_FILES['image'] ?? null, $this->currentUser->getId());
    if (!($result['success'] ?? false)) {
      $this->renderQuestionForm('create', $payload, $result['errors'] ?? []);
      return;
    }

    $this->setFlash('success', 'Pytanie zostało utworzone.');
    header('Location: ' . url('admin/pytania/' . (int) $result['question_id']));
    exit();
  }

  /**
   * Szczegóły pytania.
   */
  public function showQuestion(int $questionId): void
  {
    $question = $this->questionService->getQuestionById($questionId);
    if (!$question) {
      $this->renderNotFound('Pytanie nie zostało znalezione.');
      return;
    }

    $this->renderView('admin/question_detail', [
      'pageTitle' => 'Pytanie #' . $questionId . ' | Admin Panel | Zdajnik',
      'pageDescription' => 'Szczegóły pytania w panelu CMS Zdajnik.',
      'question' => $question,
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Formularz edycji pytania.
   */
  public function showEditQuestionForm(int $questionId): void
  {
    $question = $this->questionService->getQuestionById($questionId);
    if (!$question) {
      $this->renderNotFound('Pytanie nie zostało znalezione.');
      return;
    }

    $currentVersion = $question['current_version'] ?? [];
    $answers = $question['answers'] ?? [];
    $this->renderQuestionForm('edit', [
      'id' => $questionId,
      'exam_type_id' => (int) ($question['exam_type_id'] ?? 0),
      'topic_id' => (int) ($question['topic_id'] ?? 0),
      'source' => (string) ($question['source'] ?? ''),
      'difficulty' => (string) ($question['difficulty'] ?? 'medium'),
      'question_text' => (string) ($currentVersion['question_text'] ?? ''),
      'explanation' => (string) ($currentVersion['explanation'] ?? ''),
      'is_active' => (int) ($question['is_active'] ?? 1),
      'remove_image' => 0,
      'image_name' => $currentVersion['image_name'] ?? null,
      'answers' => $this->normalizeAnswersForForm($answers),
    ], [], $questionId);
  }

  /**
   * Aktualizuje pytanie.
   */
  public function updateQuestion(int $questionId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/pytania/' . $questionId));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $currentQuestion = $this->questionService->getQuestionById($questionId);
      $payload = $this->extractQuestionPayload($_POST);
      if ($currentQuestion && !isset($payload['image_name'])) {
        $payload['image_name'] = $currentQuestion['current_version']['image_name'] ?? null;
      }
      $this->renderQuestionForm('edit', $payload, ['csrf' => 'Nieprawidłowy token bezpieczeństwa.'], $questionId);
      return;
    }

    $payload = $this->extractQuestionPayload($_POST);
    $currentQuestion = $this->questionService->getQuestionById($questionId);
    if ($currentQuestion && !isset($payload['image_name'])) {
      $payload['image_name'] = $currentQuestion['current_version']['image_name'] ?? null;
    }

    $result = $this->questionService->updateQuestion($questionId, $payload, $_FILES['image'] ?? null, $this->currentUser->getId());
    if (!($result['success'] ?? false)) {
      $this->renderQuestionForm('edit', $payload, $result['errors'] ?? [], $questionId);
      return;
    }

    $this->setFlash('success', 'Pytanie zostało zaktualizowane.');
    header('Location: ' . url('admin/pytania/' . $questionId));
    exit();
  }

  /**
   * Dezaktywuje pytanie.
   */
  public function deactivateQuestion(int $questionId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/pytania/' . $questionId));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->respondQuestionAction($questionId, 'error', 'Nieprawidłowy token bezpieczeństwa.');
    }

    $result = $this->questionService->deactivateQuestion($questionId, $this->currentUser->getId());
    if (!($result['success'] ?? false)) {
      $this->respondQuestionAction($questionId, 'error', (string) ($result['errors']['general'] ?? 'Nie udało się dezaktywować pytania.'));
    }

    $this->respondQuestionAction($questionId, 'success', 'Pytanie zostało zdezaktywowane.');
  }

  public function activateQuestion(int $questionId): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . url('admin/pytania/' . $questionId));
      exit();
    }

    if (!$this->csrfService->validate($_POST['csrf_token'] ?? null)) {
      $this->respondQuestionAction($questionId, 'error', 'Nieprawidłowy token bezpieczeństwa.');
    }

    $result = $this->questionService->activateQuestion($questionId, $this->currentUser->getId());
    if (!($result['success'] ?? false)) {
      $this->respondQuestionAction($questionId, 'error', (string) ($result['errors']['general'] ?? 'Nie udało się aktywować pytania.'));
    }

    $this->respondQuestionAction($questionId, 'success', 'Pytanie zostało aktywowane.');
  }

  /**
   * Kończy akcję statusu pytania: JSON dla AJAX, flash+redirect dla klasycznych.
   */
  private function respondQuestionAction(int $questionId, string $type, string $message): never
  {
    if ($this->wantsJson()) {
      $this->jsonResponse([
        'ok' => $type !== 'error',
        'type' => $type,
        'message' => $message,
      ], $type === 'error' ? 422 : 200);
    }

    $this->setFlash($type, $message);
    header('Location: ' . url('admin/pytania/' . $questionId));
    exit();
  }

  /**
   * Renderuje formularz pytania.
   *
   * @param array<string, mixed> $question
   * @param array<string, string> $errors
   */
  private function renderQuestionForm(string $mode, array $question, array $errors = [], ?int $questionId = null): void
  {
    $question['answers'] = $this->normalizeAnswersForForm($question['answers'] ?? []);

    $this->renderView('admin/question_form', [
      'pageTitle' => ($mode === 'edit' ? 'Edycja pytania' : 'Dodawanie pytania') . ' | Admin Panel | Zdajnik',
      'pageDescription' => 'Formularz zarządzania pytaniem w panelu CMS Zdajnik.',
      'mode' => $mode,
      'questionId' => $questionId,
      'question' => $question,
      'errors' => $errors,
      'examGroups' => $this->questionService->getExamTopicGroups(),
      'csrfToken' => $this->csrfService->token(),
    ]);
  }

  /**
   * Buduje domyślne pola odpowiedzi.
   *
   * @return array<int, array<string, mixed>>
   */
  private function buildDefaultAnswers(int $count = 4): array
  {
    $answers = [];
    for ($i = 0; $i < $count; $i++) {
      $answers[] = [
        'id' => null,
        'content' => '',
        'is_correct' => $i === 0 ? 1 : 0,
      ];
    }

    return $answers;
  }

  /**
   * Normalizuje odpowiedzi do formularza.
   *
   * @param array<int, array<string, mixed>> $answers
   * @return array<int, array<string, mixed>>
   */
  private function normalizeAnswersForForm(array $answers): array
  {
    if (empty($answers)) {
      return $this->buildDefaultAnswers();
    }

    $normalized = [];
    foreach ($answers as $index => $answer) {
      $normalized[] = [
        'id' => $answer['id'] ?? null,
        'content' => $answer['answer_text'] ?? ($answer['content'] ?? ''),
        'is_correct' => (int) ($answer['is_correct'] ?? 0),
      ];
    }

    while (count($normalized) < 4) {
      $normalized[] = [
        'id' => null,
        'content' => '',
        'is_correct' => 0,
      ];
    }

    return $normalized;
  }

  /**
   * Wydobywa dane pytania z requestu.
   *
   * @param array<string, mixed> $input
   * @return array<string, mixed>
   */
  private function extractQuestionPayload(array $input): array
  {
    $rawAnswers = $input['answers'] ?? [];
    if (!is_array($rawAnswers)) {
      $rawAnswers = [];
    }

    $correctIndex = isset($input['correct_answer_index']) && $input['correct_answer_index'] !== ''
      ? (int) $input['correct_answer_index']
      : null;

    $answers = [];
    foreach ($rawAnswers as $index => $answer) {
      $isCorrect = $correctIndex !== null && (int) $index === $correctIndex;
      $answers[] = [
        'id' => $answer['id'] ?? null,
        'content' => trim((string) ($answer['content'] ?? '')),
        'is_correct' => $isCorrect ? 1 : 0,
      ];
    }

    if (empty($answers)) {
      $answers = $this->buildDefaultAnswers();
    }

    return [
      'exam_type_id' => (int) ($input['exam_type_id'] ?? 0),
      'topic_id' => (int) ($input['topic_id'] ?? 0),
      'source' => trim((string) ($input['source'] ?? '')),
      'difficulty' => (string) ($input['difficulty'] ?? 'medium'),
      'question_text' => trim((string) ($input['question_text'] ?? '')),
      'explanation' => trim((string) ($input['explanation'] ?? '')),
      'is_active' => (int) (($input['is_active'] ?? 1) ? 1 : 0),
      'remove_image' => !empty($input['remove_image']) ? 1 : 0,
      'image_name' => $input['image_name'] ?? null,
      'correct_answer_index' => $correctIndex,
      'answers' => $answers,
    ];
  }
}
