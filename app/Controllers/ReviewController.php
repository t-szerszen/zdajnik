<?php
namespace App\Controllers;

use App\Services\StatisticsService;

class ReviewController extends BaseController
{

  private StatisticsService $statsService;

  public function __construct()
  {
    parent::__construct();
    $this->requireAuth();
    $this->statsService = new StatisticsService();
  }

  /**
   * Wyświetla stronę podglądu konkretnego pytania (tryb "Zobacz").
   */
  public function showQuestionReview(int $questionId)
  {
    $question = $this->statsService->getQuestionDetails($questionId);

    if (!$question) {
      http_response_code(404);
      echo "Pytanie nie zostało znalezione.";
      return;
    }

    $source = $_GET['source'] ?? 'stats';
    if ($source === 'history') {
      $type = $_GET['type'] ?? 'errors';
      $cat = $_GET['category'] ?? 0;
      $backUrl = url('historia') . '?type=' . $type . '&category=' . $cat;
    } else {
      $backUrl = url('statystyki');
    }

    $this->renderView('question_review', [
      'question' => $question,
      'examCode' => $question['exam_code'],
      'backUrl' => $backUrl
    ]);
  }

  /**
   * Wyświetla stronę podglądu całego egzaminu.
   */
  public function showExamReview(int $attemptId)
  {
    $userId = $this->currentUser->getId();
    $reviewData = $this->statsService->getExamReviewDetails($attemptId, $userId);

    if (!$reviewData) {
      http_response_code(404);
      echo "Próba nie została znaleziona lub nie masz do niej dostępu.";
      return;
    }

    $source = $_GET['source'] ?? 'stats';
    if ($source === 'history') {
      $type = $_GET['type'] ?? 'exams';
      $cat = $_GET['category'] ?? 0;
      $backUrl = url('historia') . '?type=' . $type . '&category=' . $cat;
    } else {
      $backUrl = url('statystyki') . '#examsHistory';
    }

    $this->renderView('exam_review', [
      'exam' => $reviewData,
      'examCode' => $reviewData['meta']['exam_code'],
      'backUrl' => $backUrl
    ]);
  }
}