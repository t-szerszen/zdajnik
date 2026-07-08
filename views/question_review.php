<?php
/**
 * @file question_review.php
 * @description
 * Podgląd szczegółów pojedynczego pytania (identyfikator, statystyki, zgłoszenia błędu).
 *
 * @variables
 * - $question (array)
 * - $answers (array)
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

/**
 * Konfiguracja komponentu head
 */
$pageTitle = 'Pytanie ID: ' . $question['id'] . ' | Zdajnik';
$pageDescription = mb_strimwidth(strip_tags($question['question_text']), 0, 155, '...');

// Schema.org dla pytania (Quiz/Question)
$extraSchemas = [
  [
    "@context" => "https://schema.org",
    "@type" => "Quiz",
    "name" => "Egzamin Zawodowy - Pytanie " . $question['id'],
    "hasPart" => [
      [
        "@type" => "Question",
        "name" => strip_tags($question['question_text']),
        "eduQuestionType" => "Multiple choice",
        "suggestedAnswer" => array_map(function ($a) {
          return [
            "@type" => "Answer",
            "text" => strip_tags($a['answer_text'])
          ];
        }, $question['answers'])
      ]
    ]
  ]
];
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="review-page">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
  <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">Podgląd</span> pytania</h1>
      <p class="page-header__text">Indywidualna weryfikacja zagadnienia. Przeanalizuj odpowiedź oraz zapoznaj się ze
        szczegółowym wyjaśnieniem, aby dogłębnie zrozumieć omawiany problem.</p>
    </div>
  </header>

  <div class="review-page__main-wrapper">
    <div class="review-page__container">
      <div class="review-page__back-nav">
        <a href="<?= $backUrl ?>" class="review-page__back-link">
          <i class="fas fa-chevron-left"></i> Powrót
        </a>
      </div>

      <section class="review-page__main">
        <article class="quiz-card">
          <div class="quiz-card__header">
            <div class="quiz-card__header-left">
              <h2 class="quiz-card__question-number">Pytanie <span class="quiz-card__id">ID:
                  <?= $question['id'] ?></span></h2>
            </div>
            <div class="quiz-card__header-actions">
              <span class="status-badge status-badge--success"><i class="fas fa-eye"></i> Tryb przeglądu</span>
              <?php if ($isUserLoggedIn): ?>
                <button class="quiz-card__save-btn" data-question-id="<?= $question['id'] ?>" title="Zapisz pytanie"
                  aria-label="<?= $question['is_saved'] ? 'Usuń z zapisanych' : 'Zapisz pytanie' ?>">
                  <i class="<?= $question['is_saved'] ? 'fas' : 'far' ?> fa-heart"></i>
                </button>
              <?php endif; ?>
            </div>
          </div>

          <div class="quiz-card__content">
            <p class="quiz-card__question-text">
              <?= nl2br(htmlspecialchars($question['question_text'])) ?>
            </p>
            <?php if (!empty($question['image_name'])): ?>
              <div class="quiz-card__image-container">
                <img src="<?= url('images/questions/' . $question['image_name']) ?>" alt="Ilustracja do pytania"
                  class="quiz-card__image">
              </div>
              <?php
            endif; ?>
          </div>

          <div class="quiz-card__answers" data-answered="true">
            <?php foreach ($question['answers'] as $index => $answer): ?>
              <?php
              $letter = chr(65 + $index);
              $isCorrect = (int) $answer['is_correct'] === 1;
              $statusClass = $isCorrect ? 'quiz-card__answer--correct' : '';
              ?>
              <div class="quiz-card__answer <?= $statusClass ?>">
                <span class="quiz-card__answer-prefix">
                  <?= $letter ?>
                </span>
                <span class="quiz-card__answer-text">
                  <?= nl2br(htmlspecialchars($answer['answer_text'])) ?>
                </span>
              </div>
              <?php
            endforeach; ?>
          </div>

          <div class="quiz-card__actions">
            <?php if (!empty($question['explanation'])): ?>
              <div class="quiz-card__button-container">
                <button type="button" class="btn btn--secondary btn--small toggle-explanation-btn"
                  data-target="explanation-content">
                  <i class="fas fa-lightbulb"></i> Pokaż wyjaśnienie
                </button>
              </div>
              <div class="quiz-card__explanation" id="explanation-content">
                <div class="explanation-title">
                  <i class="fas fa-magic"></i> Wyjaśnienie
                </div>
                <div class="explanation-inner">
                  <?= nl2br(htmlspecialchars($question['explanation'])) ?>
                </div>
              </div>
              <?php
            endif; ?>
          </div>
        </article>
      </section>
    </div>
  </div>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
