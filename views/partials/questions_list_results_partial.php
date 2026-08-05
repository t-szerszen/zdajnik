<?php

/**
 * @file questions_list_results_partial.php
 * @description
 * Fragment listy pytań + paginacji dla /pytania/{kod}. Wydzielony z
 * questions_list.php, żeby ten sam markup dało się wyrenderować zarówno
 * przy pełnym przeładowaniu strony, jak i przy podmianie AJAX-em po
 * zmianie filtrów (temat/tekst) - patrz QuestionController::listByExam().
 *
 * @variables
 * - $exam (array), $examUrlCode (string)
 * - $questions (array)
 * - $filters (array): topic_id, query
 * - $page (int), $totalPages (int), $totalCount (int)
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

$exam = $exam ?? [];
$examUrlCode = $examUrlCode ?? exam_code_to_url((string) ($exam['code'] ?? ''));
$questions = $questions ?? [];
$filters = $filters ?? ['topic_id' => null, 'query' => null];
$page = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$totalCount = (int) ($totalCount ?? 0);

$hasActiveFilters = !empty($filters['topic_id']) || !empty($filters['query']);

/**
 * Buduje adres konkretnej strony listingu, zachowując aktywne filtry
 * (strona 1 bez parametru strona= — kanoniczna dla danej kombinacji filtrów).
 */
$pageUrl = static function (int $targetPage) use ($examUrlCode, $filters): string {
  $params = array_filter([
    'temat' => $filters['topic_id'] ?? null,
    'q' => $filters['query'] ?? null,
  ]);
  if ($targetPage > 1) {
    $params['strona'] = $targetPage;
  }
  $base = url('pytania/' . $examUrlCode);
  return $params ? $base . '?' . http_build_query($params) : $base;
};
?>
<?php if (empty($questions)): ?>
  <div class="empty-state">
    <?php if ($hasActiveFilters): ?>
      <p><i class="fas fa-magnifying-glass"></i> Brak pytań spełniających wybrane kryteria.</p>
      <a href="<?= url('pytania/' . $examUrlCode) ?>" class="btn btn--secondary btn--sm">Wyczyść filtry</a>
    <?php else: ?>
      <p><i class="fas fa-folder-open"></i> Brak pytań dla tej kwalifikacji.</p>
    <?php endif; ?>
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
        <a href="<?= $pageUrl($page - 1) ?>" class="btn btn--secondary btn--sm" rel="prev" data-page-link data-page="<?= $page - 1 ?>">
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
              <a href="<?= $pageUrl($pageNumber) ?>" class="questions-pagination__page" data-page-link data-page="<?= $pageNumber ?>"><?= $pageNumber ?></a>
            <?php endif; ?>
          </li>
          <?php $previousPage = $pageNumber; ?>
        <?php endforeach; ?>
      </ul>

      <?php if ($page < $totalPages): ?>
        <a href="<?= $pageUrl($page + 1) ?>" class="btn btn--secondary btn--sm" rel="next" data-page-link data-page="<?= $page + 1 ?>">
          Następna <i class="fas fa-chevron-right"></i>
        </a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
