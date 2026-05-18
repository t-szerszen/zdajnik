<?php
/**
 * @file search_results_partial.php
 * @description
 * Częściowy widok (partial) odpowiedzialny za renderowanie listy wyników 
 * wyszukiwania pytań. Wykorzystywany zarówno przy pełnym przeładowaniu strony 
 * (search.php), jak i w dynamicznych żądaniach asynchronicznych (AJAX).
 *
 * @variables
 * - $results (array): Tablica z wynikami wyszukiwania (pytania).
 * - $totalCount (int): Całkowita liczba znalezionych rekordów.
 * - $filters (array|null): Aktualnie zastosowane filtry.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 */

$results = $results ?? [];
$filters = $filters ?? [];
?>

<?php if (!empty($results)): ?>
  <div class="search-results">
    <h2 class="search-results__count">Znaleziono: <?= $totalCount ?? count($results) ?> pytań</h2>
    <div class="search-results__grid">
      <?php foreach ($results as $q): ?>
        <article class="search-result-card">
          <div class="search-result-card__main">
            <div class="search-result-card__header">
              <div class="search-result-card__badges">
                <span class="badge badge--accent"><?= htmlspecialchars($q['exam_code'] ?? 'INF') ?></span>
                <?php if (!empty($q['topic_name'])): ?>
                  <span class="badge badge--secondary"><?= htmlspecialchars($q['topic_name']) ?></span>
                <?php endif; ?>
                <span class="search-result-card__id">ID: <?= $q['question_id'] ?></span>
              </div>
            </div>
            <div class="search-result-card__content">
              <p class="search-result-card__text">
                <?= mb_strimwidth(htmlspecialchars($q['question_text']), 0, 160, "...") ?>
              </p>
            </div>
          </div>
          <div class="search-result-card__footer">
            <?php
            $reviewParams = [
              'source' => 'search',
              'query' => $filters['query'] ?? '',
              'exam_id' => $filters['exam_id'] ?? '',
              'topic_id' => $filters['topic_id'] ?? ''
            ];
            $reviewLink = url('test-pytanie/' . $q['question_id']) . '?' . http_build_query($reviewParams);
            ?>
            <a href="<?= $reviewLink ?>" class="btn btn--secondary btn--sm">
              <span>Pokaż</span> <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- Paginacja -->
    <?php if (($totalPages ?? 1) > 1): ?>
      <?php
      // Dodaj/zamień stronę w złączeniu URL
      $buildUrl = function ($newPage) {
        $params = array_merge($_GET, ['page' => $newPage]);
        return url('search') . '?' . http_build_query($params);
      };
      $currentPage = $page ?? 1;
      ?>
      <div class="search-pagination">
        <?php if ($currentPage > 1): ?>
          <a href="<?= $buildUrl($currentPage - 1) ?>" class="btn btn--secondary btn--sm pagination__btn pagination__link"><i
              class="fas fa-chevron-left"></i> Poprzednia</a>
        <?php else: ?>
          <span class="btn btn--secondary btn--sm pagination__btn pagination__link--disabled"><i
              class="fas fa-chevron-left"></i> Poprzednia</span>
        <?php endif; ?>

        <div class="search-pagination__numbers">
          <?php
          $visiblePages = 5;
          $startPage = max(1, $currentPage - floor($visiblePages / 2));
          $endPage = min($totalPages, $currentPage + floor($visiblePages / 2));

          if ($endPage - $startPage + 1 < $visiblePages) {
            if ($startPage == 1) {
              $endPage = min($totalPages, $startPage + $visiblePages - 1);
            } elseif ($endPage == $totalPages) {
              $startPage = max(1, $endPage - $visiblePages + 1);
            }
          }
          ?>

          <?php if ($startPage > 1): ?>
            <a href="<?= $buildUrl(1) ?>" class="pagination__number pagination__link">1</a>
            <?php if ($startPage > 2): ?>
              <span class="pagination__dots">...</span>
            <?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <?php if ($i == $currentPage): ?>
              <span class="pagination__number pagination__number--active"><?= $i ?></span>
            <?php else: ?>
              <a href="<?= $buildUrl($i) ?>" class="pagination__number pagination__link"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
              <span class="pagination__dots">...</span>
            <?php endif; ?>
            <a href="<?= $buildUrl($totalPages) ?>" class="pagination__number pagination__link"><?= $totalPages ?></a>
          <?php endif; ?>
        </div>

        <?php if ($currentPage < $totalPages): ?>
          <a href="<?= $buildUrl($currentPage + 1) ?>"
            class="btn btn--secondary btn--sm pagination__btn pagination__link">Następna <i
              class="fas fa-chevron-right"></i></a>
        <?php else: ?>
          <span class="btn btn--secondary btn--sm pagination__btn pagination__link--disabled">Następna <i
              class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
<?php elseif (!empty($filters['exam_id']) || !empty($filters['topic_id']) || !empty($filters['query'])): ?>
  <div class="stats-card__empty">
    <div class="stats-card__empty-icon">🔍</div>
    <p>Nie znaleźliśmy pytań spełniających Twoje kryteria.</p>
    <p class="stats-card__empty-hint">Spróbuj wpisać inną frazę lub zmienić filtry.
    </p>
  </div>
<?php else: ?>
  <div class="stats-card__empty">
    <div class="stats-card__empty-icon">✨</div>
    <p>Wybierz kryteria i rozpocznij przeszukiwanie bazy.</p>
  </div>
<?php endif; ?>