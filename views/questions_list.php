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
$topics = $topics ?? [];
$filters = $filters ?? ['topic_id' => null, 'query' => null];

$examCode = (string) ($exam['code'] ?? '');
$examUrlCode = exam_code_to_url($examCode);
$hasActiveFilters = !empty($filters['topic_id']) || !empty($filters['query']);

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
            <a href="<?= url('test/' . exam_code_to_url($examCode)) ?>" class="btn btn--primary btn--sm">
              <i class="fas fa-file-pen"></i> Test próbny
            </a>
            <a href="<?= url('jedno-pytanie/' . exam_code_to_url($examCode)) ?>" class="btn btn--secondary btn--sm">
              <i class="fas fa-bolt"></i> Jedno pytanie
            </a>
          </div>
        </div>

        <form method="GET" action="<?= url('pytania/' . $examUrlCode) ?>" class="questions-filters" role="search"
          aria-label="Filtruj pytania <?= htmlspecialchars($examCode) ?>">
          <div class="search-form__field">
            <label for="temat" class="search-form__label">Temat</label>
            <select name="temat" id="temat" class="search-form__select">
              <option value="">Wszystkie tematy</option>
              <?php foreach ($topics as $topic): ?>
                <option value="<?= (int) $topic['id'] ?>" <?= ((int) ($filters['topic_id'] ?? 0) === (int) $topic['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars((string) $topic['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="search-form__field search-form__field--full">
            <label for="q" class="search-form__label">ID pytania lub treść (min. 3 znaki)</label>
            <div class="search-form__input-wrapper">
              <i class="fas fa-search search-form__input-icon"></i>
              <input type="text" name="q" id="q" class="search-form__input" minlength="3" autocomplete="off"
                placeholder="np. podzespoły komputerowe lub 402"
                value="<?= htmlspecialchars($filters['query'] ?? '') ?>">
            </div>
          </div>
          <?php if ($hasActiveFilters): ?>
            <a href="<?= url('pytania/' . $examUrlCode) ?>" class="btn btn--secondary btn--sm questions-filters__reset">
              <i class="fas fa-xmark"></i> Wyczyść filtry
            </a>
          <?php endif; ?>
        </form>

        <div class="questions-page__results" data-filterable aria-live="polite" aria-busy="false">
          <?php include __DIR__ . '/partials/questions_list_results_partial.php'; ?>
        </div>
      </div>
    </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
