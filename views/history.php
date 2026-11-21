<?php
/**
 * @file history.php
 * @description
 * Historia podejść do egzaminów i rozwiązanych testów.
 *
 * @variables
 * - $history (array): Lista poprzednich prób egzaminacyjnych
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

/**
 * Widok pełnej historii (egzaminy, błędy, krytyczne)
 */
$noIndex = true;
$pageTitle = ($title ?? 'Historia') . ' | Zdajnik';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
  <header class="page-header" id="top">
    <div class="page-header__content">
      <h1 class="page-header__title">
        <span class="text-gradient">
          <?= htmlspecialchars($selectedCategoryCode) ?>
        </span> - Historia
        <?= htmlspecialchars($title) ?>
      </h1>
      <p class="page-header__text">Pełna historia Twojej aktywności edukacyjnej. Analizuj dotychczasowe wyniki, wyciągaj
        wnioski z poprzednich podejść i mierz swoje postępy w czasie.</p>
    </div>
  </header>

  <section class="stats-page">
    <div class="stats-page__container stats-page__container--single-col">

      <div class="stats-page__back-nav">
        <a href="<?= (string) url('statystyki') ?>?category=<?= (string) $selectedCategoryId ?>#<?= (string) ($backTo ?? '') ?>"
          class="back-link">
          <i class="fas fa-arrow-left"></i> Wróć do Statystyk
        </a>
      </div>

      <div class="stats-card stats-card--main stats-card--<?= htmlspecialchars($type, ENT_QUOTES) ?>">
        <div class="stats-card__header">
          <div class="stats-card__title">
            <?php if ($type === 'exams'): ?>
              <i class="fas fa-file-invoice" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
              <?php
            elseif ($type === 'saved'): ?>
              <i class="fas fa-heart" style="color: var(--accent-hover); background: rgba(var(--accent-rgb), 0.1);"></i>
              <?php
            elseif ($type === 'critical'): ?>
              <i class="fas fa-fire" style="color: #ef4444; background: rgba(239, 68, 68, 0.1);"></i>
              <?php
            else: ?>
              <i class="fas fa-history" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
              <?php
            endif; ?>
            <span>Historia
              <?= htmlspecialchars(($title ?? '')) ?>
            </span>
          </div>

          <?php if (!empty($items) && in_array($type, ['errors', 'critical', 'saved'])): ?>
            <?php
            $premiumParam = ($type === 'critical') ? 'toImprove' : (($type === 'saved') ? 'saved' : 'lastMistakes');
            ?>
            <a href="<?= url('jedno-pytanie/' . exam_code_to_url($selectedCategoryCode) . '?premium=' . $premiumParam . '&selectAll=true#select-all-codes') ?>"
              class="btn btn--primary btn--sm">
              <i class="fas fa-play"></i> Ćwicz wszystkie (
              <?= count($items) ?>)
            </a>
            <?php
          endif; ?>
        </div>

        <div class="stats-card__content">
          <?php if (empty($items)): ?>
            <div class="stats-card__empty">
              <div class="stats-card__empty-icon">
                <?php if ($type === 'exams'): ?>
                  📑
                  <?php
                elseif ($type === 'saved'): ?>
                  💖
                  <?php
                else: ?>
                  ✨
                  <?php
                endif; ?>
              </div>
              <h3 class="stats-card__empty-title">
                <?php if ($type === 'exams'): ?>
                  Twoja historia jest pusta
                  <?php
                elseif ($type === 'saved'): ?>
                  Twoja lista zapisanych pytań jest pusta
                  <?php
                else: ?>
                  Brak błędów do przejrzenia
                  <?php
                endif; ?>
              </h3>
              <p class="stats-card__empty-message">
                <?php if ($type === 'exams'): ?>
                  Czas zmierzyć się z pierwszym arkuszem i sprawdzić swoje umiejętności!
                  <?php
                elseif ($type === 'saved'): ?>
                  Dodawaj trudne pytania do zapisanych, aby móc do nich wrócić w dowolnym momencie.
                  <?php
                else: ?>
                  Świetna robota! Wygląda na to, że radzisz sobie doskonale w tej kategorii.
                  <?php
                endif; ?>
              </p>
              <a href="<?= url('/') ?>" class="btn btn--primary">Rozpocznij Naukę</a>
            </div>
            <?php
          else: ?>

            <div class="stats-card__list">
              <?php foreach ($items as $item): ?>

                <?php if ($type === 'exams'): ?>
                  <div class="stats-card__list-item stats-card__list-item--history">
                    <div class="stats-card__item-content">
                      <div class="stats-card__item-name">
                        <?= htmlspecialchars($item['display_name']) ?>
                      </div>
                      <div class="stats-card__meta">
                        <span>
                          <i class="far fa-calendar-alt"></i>
                          <?= date('d.m.Y', strtotime($item['completed_at'])) ?>
                        </span>
                        <span>
                          <i class="far fa-clock"></i>
                          <?= date('H:i', strtotime($item['completed_at'])) ?>
                        </span>
                        <?php if (isset($item['badge_text'])): ?>
                          <span class="badge badge-sm stats-card__badge--subtle">
                            <?= htmlspecialchars($item['badge_text']) ?>
                          </span>
                          <?php
                        endif; ?>
                      </div>
                    </div>

                    <div class="stats-card__score-group">
                      <div class="stats-card__score-label">
                        <div class="stats-card__subtitle">WYNIK</div>
                        <strong class="<?= $item['score'] >= 50 ? 'text-success' : 'text-danger' ?>">
                          <?= $item['score'] ?>%
                        </strong>
                      </div>
                      <a href="<?= url('wyniki/' . $item['id']) ?>?source=history&type=<?= urlencode($type) ?>&category=<?= (int) $selectedCategoryId ?>"
                        class="btn btn--primary btn--icon-only" title="Zobacz szczegóły">
                        <i class="fas fa-chevron-right"></i>
                      </a>
                    </div>
                  </div>

                  <?php
                elseif ($type === 'critical'): ?>
                  <div class="stats-card__list-item stats-card__list-item--critical stats-card__list-item--history">
                    <div class="stats-card__item-row">
                      <span class="badge badge--danger stats-card__score-badge">
                        <?= $item['success_rate'] ?>%
                      </span>
                      <div class="stats-card__question-summary">
                        <?php if (!empty($item['topic_name'])): ?>
                          <span class="stats-card__question-category" title="<?= htmlspecialchars($item['topic_name']) ?>">
                            <?= htmlspecialchars($item['topic_name']) ?>
                          </span>
                          <?php
                        endif; ?>
                        <span class="stats-card__question-text" title="<?= htmlspecialchars($item['question_text']) ?>">
                          <?= htmlspecialchars($item['question_text']) ?>
                        </span>
                      </div>
                    </div>
                    <div class="stats-card__item-actions">
                      <a href="<?= url('test-pytanie/' . $item['question_id']) ?>?source=history&type=<?= urlencode($type) ?>&category=<?= (int) $selectedCategoryId ?>"
                        class="btn btn--secondary btn--sm">zobacz</a>
                    </div>
                  </div>

                  <?php
                elseif ($type === 'errors' || $type === 'saved'): ?>
                  <div class="stats-card__list-item stats-card__list-item--history">
                    <div class="stats-card__question-summary">
                      <?php if (!empty($item['topic_name'])): ?>
                        <span class="stats-card__question-category" title="<?= htmlspecialchars($item['topic_name']) ?>">
                          <?= htmlspecialchars($item['topic_name']) ?>
                        </span>
                        <?php
                      endif; ?>
                      <span class="stats-card__question-text stats-card__question-text--strong" title="<?= htmlspecialchars($item['question_text']) ?>">
                        <?= htmlspecialchars($item['question_text']) ?>
                      </span>
                    </div>
                    <div class="stats-card__item-actions">
                      <a href="<?= url('test-pytanie/' . $item['id']) ?>?source=history&type=<?= urlencode($type) ?>&category=<?= (int) $selectedCategoryId ?>"
                        class="btn btn--primary btn--sm">zobacz</a>
                    </div>
                  </div>
                  <?php
                endif; ?>

                <?php
              endforeach; ?>
            </div>
            <?php
          endif; ?>
        </div>
      </div>

      <div class="centered--push-down">
        <a href="#top" class="btn btn--primary btn--large">Przewiń na górę</a>
      </div>

    </div>
  </section>
  </main>
  <?php include 'partials/footer.php'; ?>
</body>

</html>
