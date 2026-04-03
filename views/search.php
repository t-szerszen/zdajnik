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

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">Wyszukiwarka</span> Pytań</h1>
      <p class="page-header__text">Szybko zlokalizuj interesujące Cię zagadnienia. Filtruj bazę tysięcy pytań po treści lub unikalnym numerze ID, aby błyskawicznie sprawdzić poprawne rozwiązania i przypisane wyjaśnienia.</p>
    </div>
  </header>

  <main class="search-page">
    <div class="search-page__container">

      <section class="search-page__filters">
        <form action="<?= url('search') ?>" method="GET" class="search-form">
          <input type="hidden" name="page" id="search-page-input" value="<?= htmlspecialchars($page ?? 1) ?>">
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
                <i class="fas fa-search search-form__input-icon"></i>
                <input type="text" name="query" id="query" class="search-form__input"
                  placeholder="np. podzespoły komputerowe lub 402"
                  value="<?= htmlspecialchars($filters['query'] ?? '') ?>" autocomplete="off">
              </div>
            </div>

          </div>
        </form>
      </section>

      <!-- Sekcja Wyników -->
      <section class="search-page__results">
          <?php include __DIR__ . '/partials/search_results_partial.php'; ?>
      </section>

    </div>
  </main>

  <?php include 'partials/footer.php'; ?>

  <script type="module" src="<?= url('js/features/search/SearchHandler.js') ?>"></script>
</body>

</html>