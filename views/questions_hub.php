<?php

/**
 * @file questions_hub.php
 * @description
 * Hub publicznej bazy pytań (/pytania) — wybór kwalifikacji z licznikiem pytań.
 * Punkt wejścia, z którego roboty indeksujące odkrywają listingi, a te z kolei
 * pojedyncze strony pytań.
 *
 * @variables
 * - $exams (array): Kwalifikacje z licznikiem pytań (exam_code, exam_name, questions_count).
 * - $totalQuestions (int): Łączna liczba publicznie dostępnych pytań.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

$exams = $exams ?? [];
$totalQuestions = (int) ($totalQuestions ?? 0);

$extraSchemas = [
  [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
      ['@type' => 'ListItem', 'position' => 1, 'name' => 'Strona główna', 'item' => url('/')],
      ['@type' => 'ListItem', 'position' => 2, 'name' => 'Baza pytań', 'item' => url('pytania')],
    ],
  ],
  [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Baza pytań egzaminacyjnych',
    'url' => url('pytania'),
    'description' => $pageDescription ?? '',
    'hasPart' => array_map(static function (array $exam): array {
      return [
        '@type' => 'CollectionPage',
        'name' => 'Pytania ' . $exam['exam_code'],
        'url' => url('pytania/' . exam_code_to_url((string) $exam['exam_code'])),
      ];
    }, $exams),
  ],
];
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
    <nav class="breadcrumbs" aria-label="Ścieżka nawigacji">
      <a href="<?= url('/') ?>">Strona główna</a>
      <span class="breadcrumbs__separator" aria-hidden="true">/</span>
      <span class="breadcrumbs__current">Baza pytań</span>
    </nav>

    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title">Baza <span class="text-gradient">pytań</span> egzaminacyjnych</h1>
        <p class="page-header__text">Wszystkie pytania z naszej bazy w jednym miejscu — z pełną treścią, wariantami
          odpowiedzi, zaznaczoną poprawną odpowiedzią i wyjaśnieniem. Przeglądaj bez logowania, a gdy zechcesz
          sprawdzić się na czas, przejdź do testu próbnego.</p>
      </div>
    </header>

    <section class="questions-page">
      <div class="questions-page__container">
        <?php if (empty($exams)): ?>
          <div class="empty-state">
            <p><i class="fas fa-folder-open"></i> Baza pytań jest jeszcze pusta. Zajrzyj tu wkrótce!</p>
          </div>
        <?php else: ?>
          <p class="questions-page__lead">Łącznie dostępnych pytań: <strong><?= $totalQuestions ?></strong></p>

          <div class="questions-hub">
            <?php foreach ($exams as $exam): ?>
              <?php $examCode = (string) $exam['exam_code']; ?>
              <a href="<?= url('pytania/' . exam_code_to_url($examCode)) ?>" class="questions-hub-card">
                <div class="questions-hub-card__icon"><i class="fas fa-circle-question"></i></div>
                <div class="questions-hub-card__content">
                  <h2 class="questions-hub-card__code"><?= htmlspecialchars($examCode) ?></h2>
                  <p class="questions-hub-card__name"><?= htmlspecialchars((string) $exam['exam_name']) ?></p>
                </div>
                <div class="questions-hub-card__meta">
                  <span class="badge badge--accent"><?= (int) $exam['questions_count'] ?> pytań</span>
                  <i class="questions-hub-card__arrow fas fa-chevron-right"></i>
                </div>
              </a>
            <?php endforeach; ?>
          </div>

          <div class="questions-cta">
            <h2 class="questions-cta__title">Wolisz się sprawdzić niż czytać?</h2>
            <p class="questions-cta__text">Rozwiąż pełny test próbny albo wylosuj jedno pytanie na szybką powtórkę.</p>
            <div class="questions-cta__actions">
              <?php foreach ($exams as $exam): ?>
                <a href="<?= url('test/' . exam_code_to_url((string) $exam['exam_code'])) ?>" class="btn btn--primary btn--sm">
                  <i class="fas fa-file-pen"></i> Test próbny <?= htmlspecialchars((string) $exam['exam_code']) ?>
                </a>
              <?php endforeach; ?>
              <a href="<?= url('search') ?>" class="btn btn--secondary btn--sm">
                <i class="fas fa-magnifying-glass"></i> Wyszukiwarka pytań
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
