<?php

/**
 * Konfiguracja komponentu head
 */
$pageTitle = 'Podgląd pytania | Examly';
$pageDescription = 'Analiza pytania z bazy egzaminacyjnej.';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="review-page">
  <?php include 'partials/navbar.php'; ?>

  <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">Podgląd</span> pytania</h1>
      <p class="page-header__text">Przeanalizuj to pytanie na spokojnie. Zobacz poprawną odpowiedź i przeczytaj
        wyjaśnienie.</p>
    </div>
  </header>

  <div class="review-page__main-wrapper">
    <div class="review-page__container">
      <div class="review-page__back-nav">
        <a href="<?= $backUrl ?>" class="review-page__back-link">
          <i class="fas fa-chevron-left"></i> Powrót
        </a>
      </div>

      <main class="review-page__main">
        <article class="quiz-card">
          <header class="quiz-card__header">
            <div>
              <span class="quiz-card__question-number">Pytanie</span>
              <span class="status-badge status-badge--success"><i class="fas fa-eye"></i> Tryb przeglądu</span>
            </div>
            <?php if ($isUserLoggedIn): ?>
              <button class="quiz-card__save-btn" data-question-id="<?= $question['id'] ?>" title="Zapisz pytanie">
                <i class="<?= $question['is_saved'] ? 'fas' : 'far' ?> fa-heart"></i>
              </button>
            <?php endif; ?>
          </header>

          <div class="quiz-card__content">
            <p class="quiz-card__question-text">
              <?= nl2br(htmlspecialchars($question['question_text'])) ?>
            </p>
            <?php if (!empty($question['image_path'])): ?>
              <div class="quiz-card__image-container">
                <img src="<?= url('images/questions/' . $question['image_path']) ?>" alt="Ilustracja do pytania"
                  class="quiz-card__image">
              </div>
            <?php endif; ?>
          </div>

          <div class="quiz-card__answers" data-answered="true">
            <?php foreach ($question['answers'] as $index => $answer): ?>
              <?php
              $letter = chr(65 + $index);
              $isCorrect = (int) $answer['is_correct'] === 1;
              $statusClass = $isCorrect ? 'quiz-card__answer--correct' : '';
              ?>
              <div class="quiz-card__answer <?= $statusClass ?>">
                <span class="quiz-card__answer-prefix"><?= $letter ?></span>
                <span class="quiz-card__answer-text"><?= nl2br(htmlspecialchars($answer['answer_text'])) ?></span>
              </div>
            <?php endforeach; ?>
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
            <?php endif; ?>

            <div class="review-page__actions-container"
              style="display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; width: 100%;">
              <a href="<?= url('jedno-pytanie/' . $examCode . '#select-all-codes') ?>"
                class="btn btn--secondary btn--large">
                <i class="fas fa-bolt"></i> Ćwicz słabe strony
              </a>
            </div>
          </div>
        </article>
      </main>
    </div>
  </div>

  <?php include 'partials/footer.php'; ?>

  <script type="module" src="<?= url('js/components/ExplanationToggler.js') ?>"></script>
  <script type="module">
    import saveToggle from '<?= url('js/components/SaveToggleHandler.js') ?>';
    saveToggle.init('.review-page__main');
  </script>
</body>

</html>