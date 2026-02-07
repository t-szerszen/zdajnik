<?php

/**
 * Widok pełnej historii (egzaminy, błędy, krytyczne)
 */
$pageTitle = ($title ?? 'Historia') . ' | Examly';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <header class="page-header" id="top">
    <div class="page-header__content">
      <h1 class="page-header__title">
        <span class="text-gradient"><?= htmlspecialchars($selectedCategoryCode) ?></span> - Historia <?= htmlspecialchars($title) ?>
      </h1>
      <p class="page-header__text">Twoje postępy i zestawienia w jednym miejscu.</p>
    </div>
  </header>

  <main class="stats-page">
    <div class="stats-page__container stats-page__container--single-col">

      <div class="stats-page__back-nav">
        <a href="<?= url('statystyki') ?>?category=<?= $selectedCategoryId ?>#<?= $backTo ?>" class="review-page__back-link">
          <i class="fas fa-chevron-left"></i> Powrót
        </a>
      </div>

      <div class="stats-card stats-card--main">
        <div class="stats-card__header">
          <div class="stats-card__title">
            <?php if ($type === 'exams'): ?>
              <i class="fas fa-file-invoice"></i>
            <?php elseif ($type === 'critical'): ?>
              <i class="fas fa-exclamation-triangle"></i>
            <?php else: ?>
              <i class="fas fa-history"></i>
            <?php endif; ?>
            <span><?= htmlspecialchars('Historia ' . $title ?? 'Historia') ?></span>
          </div>

          <?php if (!empty($items) && in_array($type, ['errors', 'critical'])): ?>
            <?php
            $premiumParam = ($type === 'critical') ? 'toImprove' : 'lastMistakes';
            ?>
            <a href="<?= url('jedno-pytanie/' . $selectedCategoryCode . '?premium=' . $premiumParam . '#select-all-codes') ?>"
              class="btn btn--primary btn--sm">
              <i class="fas fa-bullseye" style="margin-right: 0.5rem;"></i> Ćwicz wszystkie (<?= count($items) ?>)
            </a>
          <?php endif; ?>
        </div>

        <div class="stats-card__content">
          <?php if (empty($items)): ?>
            <div class="stats-card__empty">
              <div class="stats-card__empty-icon">📭</div>
              <p>Brak danych do wyświetlenia w tej kategorii.</p>
              <a href="<?= url('statystyki') ?>" class="btn btn--secondary btn--sm" style="margin-top: 1.5rem;">Zacznij naukę</a>
            </div>
          <?php else: ?>
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
                          <i class="far fa-calendar-alt"></i> <?= date('d.m.Y', strtotime($item['completed_at'])) ?>
                        </span>
                        <span>
                          <i class="far fa-clock"></i> <?= date('H:i', strtotime($item['completed_at'])) ?>
                        </span>
                        <?php if (isset($item['badge_text'])): ?>
                          <span class="badge badge-sm stats-card__badge--subtle"><?= htmlspecialchars($item['badge_text']) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="stats-card__score-group">
                      <div class="stats-card__score-label">
                        <div class="stats-card__subtitle">WYNIK</div>
                        <strong class="<?= $item['score'] >= 50 ? 'text-success' : 'text-danger' ?>">
                          <?= $item['score'] ?>%
                        </strong>
                      </div>
                      <a href="<?= url('wyniki/' . $item['id']) ?>?source=history&type=<?= $type ?>&category=<?= $selectedCategoryId ?>" class="btn btn--primary btn--icon-only" title="Zobacz szczegóły">
                        <i class="fas fa-chevron-right"></i>
                      </a>
                    </div>
                  </div>

                <?php elseif ($type === 'critical'): ?>
                  <div class="stats-card__list-item stats-card__list-item--critical stats-card__list-item--history">
                    <div class="stats-card__item-row">
                      <span class="badge badge--danger stats-card__score-badge">
                        <?= $item['success_rate'] ?>%
                      </span>
                      <span class="stats-card__question-text">
                        <?php if (!empty($item['topic_name'])): ?>
                          <span class="stats-card__topic-badge">
                            <?= htmlspecialchars($item['topic_name']) ?>
                          </span>
                        <?php endif; ?>
                        <?= htmlspecialchars($item['question_text']) ?>
                      </span>
                    </div>
                    <div class="stats-card__item-actions">
                      <a href="<?= url('test-pytanie/' . $item['question_id']) ?>?source=history&type=<?= $type ?>&category=<?= $selectedCategoryId ?>" class="btn btn--secondary btn--sm">zobacz</a>
                    </div>
                  </div>

                <?php elseif ($type === 'errors'): ?>
                  <div class="stats-card__list-item stats-card__list-item--history">
                    <span class="stats-card__question-text">
                      <?php if (!empty($item['topic_name'])): ?>
                        <span class="stats-card__topic-badge">
                          <?= htmlspecialchars($item['topic_name']) ?>
                        </span>
                      <?php endif; ?>
                      <?= htmlspecialchars($item['question_text']) ?>
                    </span>
                    <div class="stats-card__item-actions">
                      <a href="<?= url('test-pytanie/' . $item['id']) ?>?source=history&type=<?= $type ?>&category=<?= $selectedCategoryId ?>" class="btn btn--secondary btn--sm">zobacz</a>
                    </div>
                  </div>
                <?php endif; ?>

              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div style="margin-top: 2rem; display: flex; justify-content: center;">
        <a href="#top" class="btn btn--primary btn--large">Przewiń na górę</a>
      </div>

    </div>
  </main>
  <?php include 'partials/footer.php'; ?>
</body>

</html>