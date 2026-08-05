<?php

/**
 * @file questions_list.php
 * @description
 * Stronicowany listing pytań jednej kwalifikacji (/pytania/{kod}). Każda karta
 * linkuje do pełnej strony pytania — to główna ścieżka, którą robot indeksujący
 * odkrywa pojedyncze pytania.
 *
 * @variables
 * - $exam (array): Dane kwalifikacji (code, name).
 * - $questions (array): Pytania na bieżącej stronie.
 * - $page (int), $totalPages (int), $totalCount (int): Dane paginacji.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

$exam = $exam ?? [];
$questions = $questions ?? [];
$page = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$totalCount = (int) ($totalCount ?? 0);

$examCode = (string) ($exam['code'] ?? '');
$examUrlCode = exam_code_to_url($examCode);

/**
 * Buduje adres konkretnej strony listingu (strona 1 bez parametru — kanoniczna).
 */
$pageUrl = static function (int $targetPage) use ($examUrlCode): string {
  $base = url('pytania/' . $examUrlCode);
  return $targetPage > 1 ? $base . '?strona=' . $targetPage : $base;
};

$extraSchemas = [
  [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
      ['@type' => 'ListItem', 'position' => 1, 'name' => 'Strona główna', 'item' => url('/')],
      ['@type' => 'ListItem', 'position' => 2, 'name' => 'Baza pytań', 'item' => url('pytania')],
      ['@type' => 'ListItem', 'position' => 3, 'name' => 'Pytania ' . $examCode, 'item' => url('pytania/' . $examUrlCode)],
    ],
  ],
  [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Pytania ' . $examCode,
    'url' => $canonicalUrl ?? url('pytania/' . $examUrlCode),
    'description' => $pageDescription ?? '',
    'mainEntity' => [
      '@type' => 'ItemList',
      'numberOfItems' => count($questions),
      'itemListElement' => array_map(static function (array $question, int $index): array {
        return [
          '@type' => 'ListItem',
          'position' => $index + 1,
          'name' => question_excerpt((string) $question['question_text'], 110),
          'url' => question_url($question),
        ];
      }, $questions, array_keys($questions)),
    ],
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
      <a href="<?= url('pytania') ?>">Baza pytań</a>
      <span class="breadcrumbs__separator" aria-hidden="true">/</span>
      <span class="breadcrumbs__current"><?= htmlspecialchars($examCode) ?></span>
    </nav>

    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title">Pytania <span class="text-gradient"><?= htmlspecialchars($examCode) ?></span></h1>
        <p class="page-header__text"><?= htmlspecialchars((string) ($exam['name'] ?? '')) ?> — <?= $totalCount ?>
          pytań z poprawnymi odpowiedziami i wyjaśnieniami. Kliknij dowolne pytanie, aby zobaczyć pełne rozwiązanie.</p>
      </div>
    </header>

    <section class="questions-page">
      <div class="questions-page__container">

        <div class="questions-page__toolbar">
          <a href="<?= url('pytania') ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Wszystkie kwalifikacje
          </a>
          <div class="questions-page__toolbar-actions">
            <a href="<?= url('test/' . rawurlencode($examCode)) ?>" class="btn btn--primary btn--sm">
              <i class="fas fa-file-pen"></i> Test próbny
            </a>
            <a href="<?= url('jedno-pytanie/' . rawurlencode($examCode)) ?>" class="btn btn--secondary btn--sm">
              <i class="fas fa-bolt"></i> Jedno pytanie
            </a>
          </div>
        </div>

        <?php if (empty($questions)): ?>
          <div class="empty-state">
            <p><i class="fas fa-folder-open"></i> Brak pytań dla tej kwalifikacji.</p>
          </div>
        <?php else: ?>
          <p class="questions-page__lead">
            Strona <strong><?= $page ?></strong> z <strong><?= $totalPages ?></strong>
            · łącznie <strong><?= $totalCount ?></strong> pytań
          </p>

          <ol class="questions-list">
            <?php foreach ($questions as $question): ?>
              <li class="questions-list__item">
                <article class="question-card">
                  <div class="question-card__badges">
                    <span class="badge badge--accent"><?= htmlspecialchars((string) $question['exam_code']) ?></span>
                    <?php if (!empty($question['topic_name'])): ?>
                      <span class="badge badge--secondary"><?= htmlspecialchars((string) $question['topic_name']) ?></span>
                    <?php endif; ?>
                    <?php if (question_difficulty_label($question['difficulty'] ?? null) !== ''): ?>
                      <span class="badge <?= question_difficulty_badge_class($question['difficulty']) ?>">
                        <?= question_difficulty_label($question['difficulty']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <h2 class="question-card__title">
                    <a href="<?= question_url($question) ?>">
                      <?= htmlspecialchars(question_excerpt((string) $question['question_text'], 160)) ?>
                    </a>
                  </h2>
                </article>
              </li>
            <?php endforeach; ?>
          </ol>

          <?php if ($totalPages > 1): ?>
            <nav class="questions-pagination" aria-label="Paginacja listy pytań">
              <?php if ($page > 1): ?>
                <a href="<?= $pageUrl($page - 1) ?>" class="btn btn--secondary btn--sm" rel="prev">
                  <i class="fas fa-chevron-left"></i> Poprzednia
                </a>
              <?php endif; ?>

              <?php
              // Okno stron wokół bieżącej + zawsze pierwsza i ostatnia (linki dla robota)
              $window = range(max(1, $page - 2), min($totalPages, $page + 2));
              $pagesToShow = array_values(array_unique(array_merge([1], $window, [$totalPages])));
              sort($pagesToShow);
              $previousPage = 0;
              ?>
              <ul class="questions-pagination__pages">
                <?php foreach ($pagesToShow as $pageNumber): ?>
                  <?php if ($previousPage > 0 && $pageNumber > $previousPage + 1): ?>
                    <li class="questions-pagination__gap" aria-hidden="true">…</li>
                  <?php endif; ?>
                  <li>
                    <?php if ($pageNumber === $page): ?>
                      <span class="questions-pagination__page questions-pagination__page--current"
                        aria-current="page"><?= $pageNumber ?></span>
                    <?php else: ?>
                      <a href="<?= $pageUrl($pageNumber) ?>" class="questions-pagination__page"><?= $pageNumber ?></a>
                    <?php endif; ?>
                  </li>
                  <?php $previousPage = $pageNumber; ?>
                <?php endforeach; ?>
              </ul>

              <?php if ($page < $totalPages): ?>
                <a href="<?= $pageUrl($page + 1) ?>" class="btn btn--secondary btn--sm" rel="next">
                  Następna <i class="fas fa-chevron-right"></i>
                </a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
