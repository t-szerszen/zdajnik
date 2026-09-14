<?php

namespace App\Controllers;

use App\Models\Answer;
use App\Models\Question;
use App\Services\DynamicDataService;

/**
 * Kontroler Publicznej Bazy Pytań.
 *
 * Serwuje w pełni server-renderowane, indeksowalne strony pytań pod adresami
 * /pytania/{kod}/{slug}-{id}. W odróżnieniu od trybów nauki (renderowanych po
 * stronie klienta z API) cała treść - pytanie, warianty odpowiedzi, poprawna
 * odpowiedź i wyjaśnienie - trafia do HTML-a bez udziału JavaScriptu.
 *
 * Główne obszary odpowiedzialności:
 * - Strona-hub kwalifikacji oraz stronicowane listingi (ścieżki odkrycia dla robotów).
 * - Kanonikalizacja adresów (rozjazd slug <-> treść kończy się przekierowaniem 301).
 * - Linkowanie wewnętrzne: podobne pytania, nawigacja liniowa, mostki do trybów nauki.
 *
 * Żadna z akcji nie wymaga logowania.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class QuestionController extends BaseController
{
  /** @var int Liczba pytań na jednej stronie listingu */
  private const QUESTIONS_PER_PAGE = 50;

  /** @var int Liczba propozycji w bloku "Podobne pytania" */
  private const SIMILAR_QUESTIONS_LIMIT = 6;

  /** @var Question Model do operacji na bazie pytań */
  private Question $questionModel;

  /** @var Answer Model do operacji na odpowiedziach */
  private Answer $answerModel;

  /** @var DynamicDataService Serwis danych o kwalifikacjach */
  private DynamicDataService $dynamicData;

  /**
   * Inicjalizuje kontroler publicznej bazy pytań.
   */
  public function __construct()
  {
    parent::__construct();
    $this->questionModel = new Question();
    $this->answerModel = new Answer();
    $this->dynamicData = new DynamicDataService();
  }

  /**
   * Strona-hub: lista kwalifikacji z licznikami pytań (/pytania).
   *
   * @return void
   */
  public function index(): void
  {
    $exams = $this->questionModel->getPublicExamSummary();
    $totalQuestions = array_sum(array_column($exams, 'questions_count'));

    $this->renderView('questions_hub', [
      'pageTitle' => 'Baza pytań egzaminacyjnych INF.02, INF.03 i INF.04 | Zdajnik',
      'pageDescription' => 'Przeglądaj ' . $totalQuestions . ' pytań z egzaminów zawodowych INF.02, INF.03 i INF.04 — '
        . 'każde z pełną treścią, wariantami odpowiedzi, poprawnym rozwiązaniem i wyjaśnieniem.',
      'canonicalUrl' => url('pytania'),
      'exams' => $exams,
      'totalQuestions' => $totalQuestions,
    ]);
  }

  /**
   * Stronicowany listing pytań jednej kwalifikacji (/pytania/{kod}).
   *
   * @param string $code Segment adresu z kodem kwalifikacji (np. "inf03").
   * @return void
   */
  public function listByExam(string $code): void
  {
    $exam = $this->dynamicData->resolveExamByUrlCode($code);

    if (!$exam) {
      $this->notFound('Kwalifikacja nie została znaleziona.');
      return;
    }

    // Niekanoniczny wariant segmentu (np. "INF03", "inf.03") - 301 na znormalizowaną
    // formę, zachowując aktywne filtry/stronę z query stringa. "url" trzeba pominąć -
    // to wewnętrzny parametr, którym .htaccess przekazuje ścieżkę do routera (QSA),
    // nie faktyczny parametr wpisany przez użytkownika.
    $canonicalSegment = exam_code_to_url((string) $exam['code']);
    if ($code !== $canonicalSegment) {
      $queryParams = $_GET;
      unset($queryParams['url']);

      $redirectUrl = url('pytania/' . $canonicalSegment);
      if ($queryParams) {
        $redirectUrl .= '?' . http_build_query($queryParams);
      }
      header('Location: ' . $redirectUrl, true, 301);
      exit;
    }

    $examTypeId = (int) $exam['id'];

    $filters = [
      'topic_id' => !empty($_GET['temat']) ? (int) $_GET['temat'] : null,
      'query' => isset($_GET['q']) && trim((string) $_GET['q']) !== '' ? trim((string) $_GET['q']) : null,
    ];
    $hasActiveFilters = !empty($filters['topic_id']) || !empty($filters['query']);

    $totalCount = $this->questionModel->countPublicByExam($examTypeId, $filters);
    $totalPages = max(1, (int) ceil($totalCount / self::QUESTIONS_PER_PAGE));

    $page = isset($_GET['strona']) ? max(1, (int) $_GET['strona']) : 1;
    if ($page > $totalPages) {
      $page = $totalPages;
    }

    $questions = $this->questionModel->getPublicPageByExam(
      $examTypeId,
      self::QUESTIONS_PER_PAGE,
      ($page - 1) * self::QUESTIONS_PER_PAGE,
      $filters
    );

    $topics = $this->dynamicData->getCategoriesForExam($examTypeId);

    if ($page === 1 && $hasActiveFilters) {
      try {
        $this->getAnalyticsService()->trackSearchPerformed(
          $this->currentUser?->getId(),
          $examTypeId,
          $filters['topic_id'],
          $filters['query'],
          $totalCount,
          'questions_list'
        );
      } catch (\Throwable $e) {
        // Wyszukiwanie nie może zależeć od dostępności analityki.
      }
    }

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
      && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
      ob_start();
      $this->renderPartial('questions_list_results_partial', [
        'exam' => $exam,
        'examUrlCode' => exam_code_to_url($exam['code']),
        'questions' => $questions,
        'filters' => $filters,
        'page' => $page,
        'totalPages' => $totalPages,
        'totalCount' => $totalCount,
      ]);
      $html = ob_get_clean();

      header('Content-Type: application/json');
      echo json_encode([
        'status' => 'success',
        'html' => $html,
        'count' => count($questions),
        'totalCount' => $totalCount,
        'totalPages' => $totalPages,
        'page' => $page,
      ]);
      exit;
    }

    // Adres kanoniczny wskazuje na bieżącą stronę wraz z filtrami (self-canonical);
    // strony z aktywnym filtrem lub numerem > 1 dostają noindex (jak dawny /search).
    $queryParams = array_filter([
      'temat' => $filters['topic_id'],
      'q' => $filters['query'],
      'strona' => $page > 1 ? $page : null,
    ]);
    $canonicalUrl = url('pytania/' . exam_code_to_url($exam['code']));
    if ($queryParams) {
      $canonicalUrl .= '?' . http_build_query($queryParams);
    }
    $noIndex = $hasActiveFilters || $page > 1;

    $pageSuffix = $page > 1 ? ' — strona ' . $page . ' z ' . $totalPages : '';

    $this->renderView('questions_list', [
      'pageTitle' => 'Pytania ' . $exam['code'] . ' — baza ' . $totalCount . ' pytań egzaminacyjnych' . $pageSuffix . ' | Zdajnik',
      'pageDescription' => 'Pełna lista pytań z kwalifikacji ' . $exam['code'] . ' (' . $totalCount . ' pytań) '
        . 'wraz z poprawnymi odpowiedziami i wyjaśnieniami' . ($page > 1 ? ' — strona ' . $page . '.' : '.'),
      'canonicalUrl' => $canonicalUrl,
      'noIndex' => $noIndex,
      'exam' => $exam,
      'topics' => $topics,
      'filters' => $filters,
      'questions' => $questions,
      'page' => $page,
      'totalPages' => $totalPages,
      'totalCount' => $totalCount,
    ]);
  }

  /**
   * Strona pojedynczego pytania (/pytania/{kod}/{slug}-{id}).
   *
   * @param string $code Segment adresu z kodem kwalifikacji.
   * @param string $slugId Segment "slug-id" identyfikujący pytanie.
   * @return void
   */
  public function show(string $code, string $slugId): void
  {
    $exam = $this->dynamicData->resolveExamByUrlCode($code);

    if (!$exam) {
      $this->notFound('Kwalifikacja nie została znaleziona.');
      return;
    }

    $questionId = $this->extractQuestionId($slugId);

    if ($questionId === null) {
      $this->notFound('Pytanie nie zostało znalezione.');
      return;
    }

    $question = $this->questionModel->findPublicById($questionId);

    // Pytanie musi istnieć, być publiczne i należeć do kwalifikacji wskazanej w adresie
    if (!$question || strcasecmp((string) $question['exam_code'], (string) $exam['code']) !== 0) {
      $this->notFound('Pytanie nie zostało znalezione.');
      return;
    }

    // Slug jest wyprowadzany z treści pytania, więc po jej edycji stary adres
    // przestaje być kanoniczny - kierujemy na aktualny, zachowując moc linków.
    $canonicalUrl = question_url($question);
    $expectedSlugId = question_slug((string) $question['question_text']) . '-' . $questionId;
    $canonicalSegment = exam_code_to_url((string) $exam['code']);

    // Przekierowanie obejmuje zarówno przestarzały slug (treść pytania się zmieniła),
    // jak i niekanoniczny segment kwalifikacji (np. "INF03" zamiast "inf03") - oba
    // przypadki mają ten sam, poprawny cel: question_url($question).
    if ($code !== $canonicalSegment || $slugId !== $expectedSlugId) {
      header('Location: ' . $canonicalUrl, true, 301);
      exit;
    }

    $answers = $this->answerModel->getPublicAnswersByVersionId((int) $question['question_version_id']);
    $adjacent = $this->questionModel->getPrevNextPublic($questionId, (int) $exam['id']);

    // 40 znaków + stały sufiks " | INF.0X | Zdajnik" (19 znaków) mieści tytuł w ~60 znakach,
    // żeby Google nie obcinał go w wynikach wyszukiwania (patrz question_excerpt() niżej dla opisu).
    $excerpt = question_excerpt((string) $question['question_text'], 40);
    $description = question_excerpt((string) $question['question_text'], 150);

    // Bardzo krótkie pytania nie wypełniają snippetu - dopinamy kontekst z danych pytania
    if (mb_strlen($description) < 80) {
      $description .= ' Poprawna odpowiedź i wyjaśnienie — ' . $question['exam_code'] . ', ' . $question['topic_name'] . '.';
    }

    $this->renderView('question_public', [
      'pageTitle' => $excerpt . ' | ' . $question['exam_code'] . ' | Zdajnik',
      'pageDescription' => $description,
      'canonicalUrl' => $canonicalUrl,
      'question' => $question,
      'answers' => $answers,
      'similar' => $this->questionModel->getSimilarPublicQuestions(
        $questionId,
        (int) $question['topic_id'],
        self::SIMILAR_QUESTIONS_LIMIT
      ),
      'prevQuestion' => $adjacent['prev'],
      'nextQuestion' => $adjacent['next'],
    ]);
  }

  /**
   * Wyciąga ID pytania z segmentu "slug-id".
   *
   * @param string $slugId Segment adresu URL.
   * @return int|null ID pytania lub null, gdy segment nie kończy się liczbą.
   */
  private function extractQuestionId(string $slugId): ?int
  {
    $lastDash = strrpos($slugId, '-');
    $candidate = $lastDash === false ? $slugId : substr($slugId, $lastDash + 1);

    if ($candidate === '' || !ctype_digit($candidate) || (int) $candidate <= 0) {
      return null;
    }

    return (int) $candidate;
  }

  /**
   * Centralizuje odpowiedź 404 dla publicznych stron pytań.
   *
   * @param string $message Komunikat dla widoku błędu.
   * @return void
   */
  private function notFound(string $message): void
  {
    http_response_code(404);
    $errorController = new ErrorController();
    $errorController->showError(404, $message);
  }

  /**
   * Renderuje tylko fragment HTML bez głównego layoutu aplikacji.
   *
   * Używane przez mechanizm AJAX filtrowania listingu, żeby zaktualizować
   * samą listę wyników i paginację bez przeładowania nawigacji/stopki.
   *
   * @param string $partialName Nazwa pliku widoku partial (bez rozszerzenia .php).
   * @param array $data Dane wejściowe przekazywane do widoku.
   * @return void
   */
  private function renderPartial(string $partialName, array $data = []): void
  {
    extract($data);
    include __DIR__ . "/../../views/partials/{$partialName}.php";
  }
}
