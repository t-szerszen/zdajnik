<?php

/**
 * @file sheet_notes.php
 * @description
 * Panel "Moje notatki" — wszystkie notatki użytkownika do arkuszy w jednym
 * miejscu: podsumowanie (liczba notatek, średnia ocena, arkusze ocenione
 * jako bardzo trudne), sortowanie oraz lista notatek z porównaniem oceny
 * subiektywnej z oficjalną trudnością arkusza.
 *
 * @variables
 * - $notes (array): Notatki użytkownika z danymi arkuszy.
 * - $summary (array): Podsumowanie (count, avg_difficulty, hard_count, hardest).
 * - $sort (string): Aktywne sortowanie ('najtrudniejsze' | 'najnowsze').
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

$notes = $notes ?? [];
$summary = $summary ?? ['count' => 0, 'avg_difficulty' => null, 'hard_count' => 0, 'hardest' => []];
$sort = $sort ?? 'najtrudniejsze';

// Mapowanie poziomu trudności (1-10) na grupę kolorystyczną
$difficultyGroup = static function (int $level): string {
  if ($level <= 3) {
    return 'easy';
  }
  if ($level <= 6) {
    return 'medium';
  }
  return 'hard';
};

$pageTitle = 'Moje notatki | Biblioteka Arkuszy | Zdajnik';
$pageDescription = 'Twoje notatki do arkuszy — oceny trudności i opisy problemów w jednym miejscu.';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title">Moje <span class="text-gradient">notatki</span></h1>
        <p class="page-header__text">Wszystkie Twoje notatki do arkuszy w jednym miejscu. Analizuj, które arkusze
          sprawiły Ci najwięcej trudności, i wracaj do nich, aż przestaną być problemem.</p>
      </div>
    </header>

    <section class="sheets-page sheet-notes-page">
      <div class="sheets-page__container">

        <a href="<?= url('arkusze') ?>" class="back-link">
          <i class="fas fa-arrow-left"></i> Wróć do biblioteki arkuszy
        </a>

        <?php if (empty($notes)): ?>
          <div class="empty-state">
            <p><i class="fas fa-note-sticky"></i> Nie masz jeszcze żadnych notatek. Otwórz dowolny arkusz w
              <a href="<?= url('arkusze') ?>">Bibliotece Arkuszy</a> i oceń jego trudność.</p>
          </div>
        <?php else: ?>

          <!-- Podsumowanie -->
          <div class="sheet-notes-summary">
            <div class="sheet-notes-summary__tile">
              <span class="sheet-notes-summary__value"><?= (int) $summary['count'] ?></span>
              <span class="sheet-notes-summary__label">Zapisanych notatek</span>
            </div>
            <div class="sheet-notes-summary__tile">
              <span class="sheet-notes-summary__value"><?= $summary['avg_difficulty'] !== null ? number_format((float) $summary['avg_difficulty'], 1, ',', '') : '—' ?></span>
              <span class="sheet-notes-summary__label">Średnia Twoich ocen (1-10)</span>
            </div>
            <div class="sheet-notes-summary__tile">
              <span class="sheet-notes-summary__value"><?= (int) $summary['hard_count'] ?></span>
              <span class="sheet-notes-summary__label">Arkusze ocenione na 8+</span>
            </div>
          </div>

          <!-- Sortowanie -->
          <div class="sheet-notes-toolbar">
            <span class="sheets-filters__label">Sortuj</span>
            <div class="sheets-filters__buttons">
              <a href="<?= url('moje-notatki') ?>"
                class="sheets-filters__btn <?= $sort === 'najtrudniejsze' ? 'is-active' : '' ?>">Najtrudniejsze</a>
              <a href="<?= url('moje-notatki') ?>?sortowanie=najnowsze"
                class="sheets-filters__btn <?= $sort === 'najnowsze' ? 'is-active' : '' ?>">Ostatnio aktualizowane</a>
            </div>
          </div>

          <!-- Lista notatek -->
          <ul class="sheet-notes-list">
            <?php foreach ($notes as $noteRow): ?>
              <?php
              $subjective = (int) $noteRow['subjective_difficulty'];
              $official = (int) $noteRow['official_difficulty'];
              $delta = $subjective - $official;
              $noteExamCode = (string) $noteRow['exam_code'];
              ?>
              <li class="sheet-note-card">
                <div class="sheet-note-card__header">
                  <?php if (!empty($noteRow['thumbnail_path'])): ?>
                    <img class="sheet-note-card__thumb"
                      src="<?= url('files/sheets/' . rawurlencode((string) $noteRow['thumbnail_path'])) ?>"
                      alt="Miniaturka arkusza <?= htmlspecialchars((string) $noteRow['sheet_title']) ?>" loading="lazy">
                  <?php else: ?>
                    <div class="sheet-note-card__thumb sheet-note-card__thumb--placeholder"><i class="fas fa-file-pdf"></i></div>
                  <?php endif; ?>
                  <div class="sheet-note-card__heading">
                    <div class="sheet-note-card__badges">
                      <span class="badge badge--accent"><?= htmlspecialchars($noteExamCode) ?></span>
                      <span class="badge badge--secondary"><?= htmlspecialchars((string) $noteRow['topic_name']) ?></span>
                    </div>
                    <a class="sheet-note-card__title"
                      href="<?= url('arkusze/' . rawurlencode($noteExamCode) . '/' . (int) $noteRow['sheet_id']) ?>">
                      <?= htmlspecialchars((string) $noteRow['sheet_title']) ?>
                    </a>
                  </div>
                  <div class="sheet-note-card__ratings">
                    <span class="difficulty-badge difficulty-badge--<?= $difficultyGroup($subjective) ?>"
                      title="Twoja subiektywna ocena">Twoja: <?= $subjective ?>/10</span>
                    <span class="difficulty-badge difficulty-badge--<?= $difficultyGroup($official) ?>"
                      title="Oficjalna trudność arkusza">Oficjalna: <?= $official ?>/10</span>
                    <?php if ($delta > 0): ?>
                      <span class="sheet-note-card__delta sheet-note-card__delta--harder"
                        title="Oceniłeś ten arkusz jako trudniejszy niż jego oficjalna trudność">
                        <i class="fas fa-arrow-trend-up"></i> +<?= $delta ?>
                      </span>
                    <?php elseif ($delta < 0): ?>
                      <span class="sheet-note-card__delta sheet-note-card__delta--easier"
                        title="Oceniłeś ten arkusz jako łatwiejszy niż jego oficjalna trudność">
                        <i class="fas fa-arrow-trend-down"></i> <?= $delta ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>

                <?php if (!empty($noteRow['problem_description'])): ?>
                  <p class="sheet-note-card__description"><?= nl2br(htmlspecialchars((string) $noteRow['problem_description'])) ?></p>
                <?php else: ?>
                  <p class="sheet-note-card__description sheet-note-card__description--empty">Brak opisu problemów —
                    sama ocena trudności.</p>
                <?php endif; ?>

                <div class="sheet-note-card__footer">
                  <span class="sheet-note-card__date"><i class="far fa-clock"></i> Aktualizacja:
                    <?= date('d.m.Y H:i', strtotime((string) $noteRow['updated_at'])) ?></span>
                  <a href="<?= url('arkusze/' . rawurlencode($noteExamCode) . '/' . (int) $noteRow['sheet_id']) ?>"
                    class="btn btn--secondary btn--sm">Otwórz arkusz</a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>

        <?php endif; ?>
      </div>
    </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
