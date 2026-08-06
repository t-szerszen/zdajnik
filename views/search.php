<?php

/**
 * @file search.php
 * @description
 * Lekki punkt wejścia do bazy pytań. Wybór kwalifikacji (opcjonalnie z
 * wpisaną wcześniej frazą) prowadzi wprost do filtrowanego listingu
 * /pytania/{kod}, gdzie mieszka cała logika przeglądania/filtrowania -
 * patrz questions_list.php. Ta strona odpowiada wyłącznie za sam wybór
 * kwalifikacji oraz krótkie wyszukiwanie międzykwalifikacyjne (po
 * treści/ID), zanim użytkownik zdecyduje, której kwalifikacji szuka.
 *
 * @variables
 * - $exams (array): Kwalifikacje z dostępną bazą pytań [id, code].
 * - $query (string): Aktualnie wpisana fraza (jeśli jest).
 * - $crossExamResults (array): Krótka lista wyników międzykwalifikacyjnych dla $query.
 *
 * @version 2.0.0
 * @author Tobiasz Szerszeń
 */

$exams = $exams ?? [];
$query = $query ?? '';
$crossExamResults = $crossExamResults ?? [];
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient">Wyszukiwarka</span> Pytań</h1>
        <p class="page-header__text">Wybierz kwalifikację, aby przejść do pełnej listy jej pytań z filtrami po temacie
          i treści, albo od razu wpisz frazę lub numer ID, jeśli szukasz w całej bazie.</p>
      </div>
    </header>

    <section class="search-page">
      <div class="search-page__container">

        <section class="search-page__picker" aria-labelledby="search-picker-title">
          <h2 id="search-picker-title" class="visually-hidden">Wybór kwalifikacji</h2>
          <form method="GET" action="<?= url('search') ?>" class="search-picker-form" role="search"
            aria-label="Wyszukiwarka pytań">
            <div class="search-form__field search-form__field--full">
              <label for="q" class="search-form__label">ID pytania lub treść (min. 3 znaki, opcjonalnie)</label>
              <div class="search-form__input-wrapper">
                <i class="fas fa-search search-form__input-icon"></i>
                <input type="text" name="q" id="q" class="search-form__input" minlength="3" autocomplete="off"
                  placeholder="np. echo lub 402"
                  value="<?= htmlspecialchars($query) ?>">
              </div>
            </div>
          </form>

          <div class="search-picker-grid" data-search-picker-grid>
            <?php foreach ($exams as $exam): ?>
              <?php
              $examUrlCode = exam_code_to_url((string) $exam['code']);
              $href = url('pytania/' . $examUrlCode) . ($query !== '' ? '?q=' . rawurlencode($query) : '');
              ?>
              <a class="search-picker-card" data-search-picker-link data-exam-url-code="<?= htmlspecialchars($examUrlCode) ?>"
                href="<?= $href ?>">
                <?= htmlspecialchars((string) $exam['code']) ?>
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
              </a>
            <?php endforeach; ?>
          </div>
        </section>

        <?php if ($query !== '' && !empty($crossExamResults)): ?>
          <section class="search-page__cross-results" aria-live="polite">
            <h2 class="search-page__cross-results-title">Znalezione pytania</h2>
            <ul class="search-page__cross-results-list">
              <?php foreach ($crossExamResults as $result): ?>
                <li>
                  <a href="<?= question_url($result) ?>">
                    <span class="badge badge--accent"><?= htmlspecialchars((string) $result['exam_code']) ?></span>
                    <?= htmlspecialchars(question_excerpt((string) $result['question_text'], 120)) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php elseif ($query !== ''): ?>
          <div class="empty-state">
            <p><i class="fas fa-magnifying-glass"></i> Brak pytań pasujących do „<?= htmlspecialchars($query) ?>”.</p>
          </div>
        <?php endif; ?>

      </div>
    </section>
  </main>

  <!--
    Drobne ulepszenie progresywne: linki-karty kwalifikacji działają w pełni
    przez zwykłe href (bez JS-a to zwykła nawigacja z ewentualnym ?q= z
    ostatniego submitu formularza). Ten skrypt tylko aktualizuje ?q= na
    żywo w trakcie pisania, żeby nie trzeba było klikać "Szukaj".
  -->
  <script>
    (function() {
      var input = document.getElementById('q');
      var grid = document.querySelector('[data-search-picker-grid]');
      if (!input || !grid) return;

      var baseUrl = window.zdajnikAppState ? window.zdajnikAppState.baseUrl : '';
      var links = grid.querySelectorAll('[data-search-picker-link]');

      input.addEventListener('input', function() {
        var query = input.value.trim();
        links.forEach(function(link) {
          var examUrlCode = link.getAttribute('data-exam-url-code');
          var href = baseUrl + '/pytania/' + examUrlCode;
          if (query !== '') {
            href += '?q=' + encodeURIComponent(query);
          }
          link.setAttribute('href', href);
        });
      });
    })();
  </script>

  <?php include 'partials/footer.php'; ?>
</body>

</html>