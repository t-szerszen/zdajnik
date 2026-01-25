<?php

class ReviewController extends BaseController
{
  public function __construct()
  {
    parent::__construct();
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

    $this->renderView('question_review', [
      'question' => $question,
      'examCode' => 'INF.03'
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

    $this->renderView('exam_review', [
      'exam' => $reviewData,
      'examCode' => 'INF.03'
    ]);
  }
}