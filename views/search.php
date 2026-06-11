<?php
/**
 * @file search.php
 * @description
 * Wyszukiwarka pytań z zaawansowanym filtrowaniem.
 *
 * @variables
 * - $categories (array)
 * - $totalCount (int)
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

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

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
	  <header class="page-header">
	    <div class="page-header__content">
	      <h1 class="page-header__title"><span class="text-gradient">Znajdź pytanie</span> po treści lub ID</h1>
	      <p class="page-header__text">Wpisz fragment treści albo numer pytania. Możesz też zawęzić wyniki do egzaminu i tematu,
	        żeby szybciej trafić w to, czego szukasz.</p>
	    </div>
	  </header>

  <section class="search-page">
    <div class="search-page__container">

      <section class="search-page__filters" aria-labelledby="search-filters-title">
        <h2 id="search-filters-title" class="visually-hidden">Filtry wyszukiwania</h2>
        <form action="<?= url('search') ?>" method="GET" class="search-form" role="search" aria-label="Wyszukiwarka pytań">
          <input type="hidden" name="page" id="search-page-input" value="<?= htmlspecialchars($page ?? 1) ?>">
          <div class="search-form__grid">

            <!-- Egzamin -->
            <div class="search-form__field">
              <label for="exam_id" class="search-form__label">Egzamin</label>
              <select name="exam_id" id="exam_id" class="search-form__select">
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
	              <label for="topic_id" class="search-form__label">Temat</label>
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
	              <label for="query" class="search-form__label">Treść pytania lub ID (min. 3 znaki)</label>
              <div class="search-form__input-wrapper">
                <i class="fas fa-search search-form__input-icon"></i>
                <input type="text" name="query" id="query" class="search-form__input"
                  placeholder="np. podzespoły komputerowe lub 402"
                  value="<?= htmlspecialchars($filters['query'] ?? '') ?>" autocomplete="off" minlength="3">
              </div>
            </div>

          </div>
        </form>
      </section>

      <!-- Sekcja Wyników -->
      <section class="search-page__results" aria-live="polite" aria-busy="false" role="region" aria-label="Wyniki wyszukiwania">
        <?php include __DIR__ . '/partials/search_results_partial.php'; ?>
      </section>

    </div>
  </section>
  </main>

  <?php include 'partials/footer.php'; ?>

  <script type="module" src="<?= url('js/features/search/SearchHandler.js') ?>"></script>
</body>

</html>
