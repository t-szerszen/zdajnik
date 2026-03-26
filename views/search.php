<?php
/**
 * Widok Wyszukiwarki Pytań | Examly
 */
$pageTitle = 'Wyszukiwarka Pytań | Examly';
$pageDescription = 'Znajdź pytanie po treści lub numerze (ID) w bazie egzaminacyjnej INF.03, INF.02 i INF.04.';

// Dane z kontrolera
$exams = $exams ?? [];
$categories = $categories ?? [];
$filters = $filters ?? [];
$results = $results ?? [];
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="home-page-background">
  <?php include 'partials/navbar.php'; ?>

  <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">Wyszukiwarka</span> Pytań</h1>
      <p class="page-header__text">Potrzebujesz znaleźć konkretne zagadnienie lub sprawdzić wyjaśnienie do pytania o
        znanym ID? Skorzystaj z naszych filtrów.</p>
    </div>
  </header>

  <main class="search-page">
    <div class="search-page__container">

      <!-- Sekcja Formularza Filtrów -->
      <section class="search-page__filters">
        <form action="<?= url('search') ?>" method="GET" class="search-form">
          <div class="search-form__grid">

            <!-- Egzamin -->
            <div class="search-form__field">
              <label for="exam_id" class="search-form__label">Egzamin</label>
              <select name="exam_id" id="exam_id" class="search-form__select" onchange="this.form.submit()">
                <option value="">Wybierz egzamin</option>
                <?php foreach ($exams as $exam): ?>
                  <option value="<?= $exam['id'] ?>" <?= ($filters['exam_id'] == $exam['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($exam['code']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Kategoria (Temat) -->
            <div class="search-form__field">
              <label for="topic_id" class="search-form__label">Temat (Kategoria)</label>
              <select name="topic_id" id="topic_id" class="search-form__select" <?= empty($categories) ? 'disabled' : '' ?>>
                <option value="">Wszystkie tematy</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= ($filters['topic_id'] == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Treść / ID -->
            <div class="search-form__field search-form__field--full">
              <label for="query" class="search-form__label">ID pytania lub treść (min. 3 znaki)</label>
              <div class="search-form__input-wrapper">
                <input type="text" name="query" id="query" class="search-form__input"
                  placeholder="np. podzespoły komputerowe lub 402"
                  value="<?= htmlspecialchars($filters['query'] ?? '') ?>">
                <button type="submit" class="btn btn--primary">
                  <i class="fas fa-search"></i> Szukaj
                </button>
              </div>
            </div>

          </div>
        </form>
      </section>

      <!-- Sekcja Wyników -->
      <section class="search-page__results">
        <?php if (!empty($results)): ?>
          <div class="search-results">
            <h2 class="search-results__count">Znaleziono: <?= count($results) ?> pytań</h2>
            <div class="search-results__grid">
              <?php foreach ($results as $q): ?>
                <article class="search-result-card">
                  <div class="search-result-card__header">
                    <div class="search-result-card__badges">
                      <span class="badge badge--accent"><?= htmlspecialchars($q['exam_code'] ?? 'INF') ?></span>
                    </div>
                    <span class="search-result-card__id">ID: #<?= $q['question_id'] ?></span>
                  </div>
                  <div class="search-result-card__content">
                    <p class="search-result-card__text">
                      <?= mb_strimwidth(htmlspecialchars($q['question_text']), 0, 160, "...") ?>
                    </p>
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
                      Pokaż detal <i class="fas fa-chevron-right"></i>
                    </a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        <?php elseif (!empty($filters['exam_id']) || !empty($filters['topic_id']) || !empty($filters['query'])): ?>
          <div class="stats-card__empty">
            <div class="stats-card__empty-icon">🔍</div>
            <p>Nie znaleźliśmy pytań spełniających Twoje kryteria.</p>
            <p style="font-size: 0.8rem; margin-top: 0.5rem; opacity: 0.6;">Spróbuj wpisać inną frazę lub zmienić filtry.
            </p>
          </div>
        <?php else: ?>
          <div class="stats-card__empty">
            <div class="stats-card__empty-icon">✨</div>
            <p>Wybierz kryteria i rozpocznij przeszukiwanie bazy.</p>
          </div>
        <?php endif; ?>
      </section>

    </div>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>