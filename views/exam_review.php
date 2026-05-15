<?php
/**
 * @file exam_review.php
 * @description
 * Podsumowanie zakończonego egzaminu z analizą odpowiedzi.
 *
 * @variables
 * - $attempt (array)
 * - $score (int)
 * - $details (array)
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

/**
 * Konfiguracja komponentu head
 */
$score = round(($exam['meta']['correct_count'] / $exam['meta']['total_questions']) * 100);
$pageTitle = 'Wynik: ' . $score . '% | Egzamin z ' . date('d.m.Y', strtotime($exam['meta']['completed_at'])) . ' | Examly';
$pageDescription = 'Szczegółowa analiza podejścia do egzaminu zakończonego wynikiem ' . $score . '%. Przejrzyj swoje błędy i przygotuj się lepiej.';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="review-page">
  <?php include 'partials/navbar.php'; ?>

  <header class="page-header" id="top">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">Podgląd</span> egzaminu</h1>
      <p class="page-header__text">Kompleksowa analiza rozwiązanego testu. Przeanalizuj swoje odpowiedzi, zapoznaj się z
        opracowaniami w ramach wiedzy eksperckiej i skutecznie eliminuj powtarzające się błędy.</p>
    </div>
  </header>

  <div class="review-page__main-wrapper">
    <div class="review-page__container">
      <div class="review-page__back-nav">
        <a href="<?= $backUrl ?>" class="review-page__back-link">
          <i class="fas fa-chevron-left"></i> Powrót
        </a>
      </div>

      <section class="review-page__summary">
        <div class="review-page__score-display">
          <div class="score-circle" style="--score: <?= $score ?>">
            <div class="score-circle__percent">
              <?= $score ?>%
            </div>
          </div>
          <p class="label-text">Twój wynik</p>
        </div>

        <div class="review-page__stats-grid">
          <div class="review-page__stat-box">
            <span class="stat-label">Wynik punktowy</span>
            <span class="stat-value text-gradient">
              <i class="fas fa-bullseye" style="font-size: 0.8em; margin-right: 0.25rem;"></i>
              <?= $exam['meta']['correct_count'] ?> /
              <?= $exam['meta']['total_questions'] ?>
            </span>
          </div>

          <?php if (isset($exam['meta']['duration_seconds'])): ?>
            <div class="review-page__stat-box">
              <span class="stat-label">Czas pracy</span>
              <span class="stat-value">
                <i class="fas fa-clock" style="font-size: 0.8em; margin-right: 0.25rem;"></i>
                <?php
                $min = floor($exam['meta']['duration_seconds'] / 60);
                $sec = $exam['meta']['duration_seconds'] % 60;
                echo sprintf('%02d:%02d', $min, $sec);
                ?>
              </span>
            </div>
            <?php
          endif; ?>

          <div class="review-page__stat-box">
            <span class="stat-label">Data podejścia</span>
            <span class="stat-value" style="font-size: 1.1rem; line-height: 1.2;">
              <i class="fas fa-calendar-alt" style="font-size: 0.8em; margin-right: 0.25rem; color: var(--accent);"></i>
              <?= date('d.m.Y', strtotime($exam['meta']['completed_at'])) ?>
              <small style="display: block; opacity: 0.6; font-size: 0.8rem; margin-top: 2px;">
                Godzina
                <?= date('H:i', strtotime($exam['meta']['completed_at'])) ?>
              </small>
            </span>
          </div>
        </div>
      </section>

      <h2 class="review-page__section-title">Szczegóły pytań</h2>

      <main class="review-page__questions">
        <?php if (empty($exam['questions'])): ?>
          <div class="review-page__empty-state">
            <i class="fas fa-history"></i>
            <p>Brak szczegółowych danych o odpowiedziach dla tej próby.</p>
            <p style="font-size: 0.9em; margin-top: 0.5rem; opacity: 0.7;">Może to dotyczyć starych egzaminów rozwiązanych
              przed wdrożeniem pełnego śledzenia historii.</p>
          </div>
          <?php
        else: ?>
          <?php foreach ($exam['questions'] as $index => $question): ?>
            <article class="quiz-card" id="q-<?= $question['question_id'] ?>" style="animation-delay: <?= $index * 0.1 ?>s">
              <header class="quiz-card__header">
                <div class="quiz-card__header-left">
                  <h2 class="quiz-card__question-number">Pytanie <?= $index + 1 ?> <span class="quiz-card__id">ID:
                      <?= $question['question_id'] ?></span></h2>
                </div>
                <div class="quiz-card__header-actions">
                  <?php if (!empty($question['was_skipped'])): ?>
                    <span class="status-badge status-badge--skipped"><i class="fas fa-share"></i> Pominięte</span>
                  <?php elseif ($question['is_user_correct']): ?>
                    <span class="status-badge status-badge--success"><i class="fas fa-check"></i> Poprawne</span>
                  <?php else: ?>
                    <span class="status-badge status-badge--error"><i class="fas fa-times"></i> Błąd</span>
                  <?php endif; ?>

                  <?php if ($isUserLoggedIn): ?>
                    <button class="quiz-card__save-btn" data-question-id="<?= $question['question_id'] ?>"
                      title="Zapisz pytanie"
                      aria-label="<?= $question['is_saved'] ? 'Usuń z zapisanych' : 'Zapisz pytanie' ?>">
                      <i class="<?= $question['is_saved'] ? 'fas' : 'far' ?> fa-heart"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </header>

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
                <?php foreach ($question['answers'] as $ansIndex => $answer): ?>
                  <?php
                  $letter = chr(65 + $ansIndex);
                  $isCorrect = (int) $answer['is_correct'] === 1;
                  $isSelected = ((int) $question['user_answer_id'] === (int) $answer['id']);

                  $statusClass = '';
                  if ($isCorrect) {
                    if (!empty($question['was_skipped'])) {
                      $statusClass = 'quiz-card__answer--skipped';
                    } else {
                      $statusClass = 'quiz-card__answer--correct';
                    }
                  } elseif ($isSelected && !$isCorrect) {
                    $statusClass = 'quiz-card__answer--incorrect';
                  }
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
                      data-target="explanation-<?= $question['version_id'] ?>">
                      <i class="fas fa-lightbulb"></i> Pokaż wyjaśnienie
                    </button>
                  </div>
                  <div class="quiz-card__explanation" id="explanation-<?= $question['version_id'] ?>">
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
            <?php
          endforeach; ?>
          <?php
        endif; ?>
      </main>

      <div class="centered" style="margin-top: 4rem;">
        <a href="#top" class="btn btn--primary btn--large">Przewiń na górę</a>
      </div>
    </div>
  </div>

  <?php include 'partials/footer.php'; ?>

  <script type="module" src="<?= url('js/components/ExplanationToggler.js') ?>"></script>
  <script type="module">
    import saveToggle from '<?= url('js/components/SaveToggleHandler.js') ?>';
    saveToggle.init('.review-page__questions');
  </script>
</body>

</html>