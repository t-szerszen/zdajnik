<?php

/**
 * @file sheets_hub.php
 * @description
 * Hub Biblioteki Arkuszy — wybór kwalifikacji. Każda kwalifikacja prowadzi
 * do własnej biblioteki arkuszy (arkusze/{code}); kwalifikacje bez arkuszy
 * są oznaczone jako "Wkrótce".
 *
 * @variables
 * - $exams (array): Kwalifikacje z licznikiem arkuszy (sheets_count).
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

$exams = $exams ?? [];

$pageTitle = 'Biblioteka Arkuszy | Zdajnik';
$pageDescription = 'Arkusze praktyczne do egzaminów zawodowych INF — wybierz kwalifikację.';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title">Biblioteka <span class="text-gradient">Arkuszy</span></h1>
        <p class="page-header__text">Arkusze praktyczne pogrupowane po kwalifikacjach. Wybierz swoją kwalifikację,
          pobieraj arkusze z zasobami i przykładowymi rozwiązaniami oraz zapisuj notatki z tego, co sprawiło Ci
          trudność.</p>
      </div>
    </header>

    <section class="sheets-page">
      <div class="sheets-page__container">
        <?php if (empty($exams)): ?>
          <div class="empty-state">
            <p><i class="fas fa-folder-open"></i> Biblioteka arkuszy jest jeszcze pusta. Zajrzyj tu wkrótce!</p>
          </div>
        <?php else: ?>
          <div class="sheets-hub">
            <?php foreach ($exams as $exam): ?>
              <?php $sheetsCount = (int) ($exam['sheets_count'] ?? 0); ?>
              <?php if ($sheetsCount > 0): ?>
                <a href="<?= url('arkusze/' . rawurlencode((string) $exam['code'])) ?>" class="sheets-hub-card">
                  <div class="sheets-hub-card__icon"><i class="fas fa-file-pdf"></i></div>
                  <div class="sheets-hub-card__content">
                    <h2 class="sheets-hub-card__code"><?= htmlspecialchars((string) $exam['code']) ?></h2>
                    <p class="sheets-hub-card__name"><?= htmlspecialchars((string) $exam['name']) ?></p>
                  </div>
                  <div class="sheets-hub-card__meta">
                    <span class="badge badge--accent"><?= $sheetsCount ?> ark.</span>
                    <i class="sheets-hub-card__arrow fas fa-chevron-right"></i>
                  </div>
                </a>
              <?php else: ?>
                <div class="sheets-hub-card sheets-hub-card--disabled" aria-disabled="true">
                  <div class="sheets-hub-card__icon"><i class="fas fa-file-pdf"></i></div>
                  <div class="sheets-hub-card__content">
                    <h2 class="sheets-hub-card__code"><?= htmlspecialchars((string) $exam['code']) ?></h2>
                    <p class="sheets-hub-card__name"><?= htmlspecialchars((string) $exam['name']) ?></p>
                  </div>
                  <div class="sheets-hub-card__meta">
                    <span class="badge badge--soon">Wkrótce</span>
                  </div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
