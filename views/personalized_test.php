<?php
/**
 * @file personalized_test.php
 * @description
 * Kreator i widok testu spersonalizowanego (wybrane działy).
 *
 * @variables
 * - $exam (array)
 * - $topics (array)
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

/**
 * Konfiguracja komponentu head
 */
$pageTitle = $exam['title_personalized'];
$pageDescription = $exam['desc_personalized'];
$canonicalUrl = url('personalizowany-test/' . $examCode);
$extraScripts = [
  'https://cdn.jsdelivr.net/npm/marked/marked.min.js',
  'https://cdn.jsdelivr.net/npm/dompurify/dist/purify.min.js'
];

/**
 * ========================================================================
 * Plik Widoku: Spersonalizowany Test
 * ========================================================================
 *
 * @description Renderuje interfejs do tworzenia i przeprowadzania
 * spersonalizowanego testu. Użytkownik przechodzi przez wieloetapowy
 * formularz (konfigurator), aby wybrać tematy, liczbę pytań
 * i opcjonalne tryby nauki.
 *
 * @dependencies 
 * - partials/head.php (head)
 * - partials/navbar.php (Nawigacja)
 * - partials/footer.php (Stopka)
 * - main.css (Główne style)
 * - Font Awesome (Ikony)
 * - marked.min.js, purify.min.js (Renderowanie treści pytań)
 * - slider-enhancer.js (Obsługa suwaka liczby pytań)
 * - topic-form-enhancer.js (Ulepszenia formularza wyboru tematów)
 * - personalized-test/index.js (Główna logika konfiguratora i testu)
 *
 * @state_variables 
 * - $examCode (string): Kod kwalifikacji (np. "inf03"),
 *   przekazywany do JS w celu pobrania właściwych pytań.
 */
?>
<!DOCTYPE html>
<html lang="pl">
<!-- Dołączenie reużywalnego komponentu head -->
<?php include 'partials/head.php'; ?>

<body class="test-page">

  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
  <!-- Nagłówek wprowadzający w kontekst strony -->
  <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient"><?= htmlspecialchars($exam['formatted_name']) ?></span>
        - Spersonalizowany test</h1>
      <p class="page-header__text">
        Wygeneruj test dopasowany do Twoich aktualnych potrzeb. Skonfiguruj zakres tematyczny, określ liczbę pytań i
        systematycznie eliminuj swoje luki w wiedzy.
      </p>
      <p class="page-header__text">
        <strong class="text-gradient">Pro Tip:</strong> Rozwiąż kilka testów próbnych, aby algorytm mógł skutecznie
        kalibrować poziom na podstawie Twoich słabych stron.
      </p>
    </div>
  </header>

  <!-- Główny kontener strony, przekazujący kod egzaminu do JS -->
  <div class="container" id="personalized-test-page" data-exam-code="<?= htmlspecialchars($examCode) ?>"
    data-personalized-test-tutorial-root>

    <!-- 
      =====================================================================
      Komponent: Konfigurator Testu
      ---------------------------------------------------------------------
      Opis: Wieloetapowy formularz, który prowadzi użytkownika przez proces
            tworzenia testu. Każdy krok to osobny `<fieldset>`.
            Widoczność kroków jest zarządzana przez JavaScript.
      =====================================================================
    -->
    <div class="test-configurator">
      <form id="topic-form" class="topic-selector">

        <!-- Krok 1: Wybór tematów -->
        <fieldset class="config-step" data-step="1">
          <legend class="config-step__title">
            <span class="config-step__number">1</span>
            Wybierz tematy, z których chcesz się uczyć
          </legend>
          <div class="topic-selector__options">
            <label class="topic-selector__label">
              <input type="checkbox" value="<?= htmlspecialchars($examCode) ?>" id="select-all-codes">
              <span>Cały materiał <?= htmlspecialchars($exam['formatted_name']) ?></span>
            </label>
            <?php foreach ($categories as $category): ?>
              <label class="topic-selector__label">
                <input type="checkbox" name="subject[]" value="<?= htmlspecialchars($category['id']) ?>"
                  class="topic-checkbox" <?= ($selectedSubjectId === (int) $category['id']) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($category['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="config-step__actions">
            <button type="button" class="btn btn--primary" data-action="next">Dalej</button>
          </div>
        </fieldset>

        <!-- Krok 2: Wybór liczby pytań -->
        <fieldset class="config-step hidden" data-step="2">
          <legend class="config-step__title">
            <span class="config-step__number">2</span>
            Określ liczbę pytań
          </legend>
          <div class="slider-container">
            <label for="question-count" class="slider-container__label">
              Wybierz liczbę pytań: <output id="question-count-value">25</output>
            </label>
            <input type="range" id="question-count" name="question_count" min="10" max="40" value="25"
              class="slider-container__input">
          </div>
          <div class="config-step__actions">
            <button type="button" class="btn btn--secondary" data-action="prev">Wróć</button>
            <button type="button" class="btn btn--primary" data-action="next">Dalej</button>
          </div>
        </fieldset>

        <!-- Krok 3: Tryby nauki -->
        <fieldset class="config-step hidden" data-step="3" data-premium-filters-root>
          <legend class="config-step__title">
            <span class="config-step__number">3</span>
            Tryby nauki (opcjonalnie)
            <button type="button" class="topic-selector__help-btn hidden" data-premium-tutorial-trigger
              aria-label="Pokaż poradnik filtrów" title="Pokaż poradnik filtrów">
              <i class="fas fa-question" aria-hidden="true"></i>
            </button>
          </legend>
          <div class="topic-selector__options">
            <label class="topic-selector__label premium-option">
              <input type="checkbox" name="premium_option" value="toDiscover" class="premium-checkbox">
              <span>Nieodkryte</span>
            </label>
            <label class="topic-selector__label premium-option">
              <input type="checkbox" name="premium_option" value="toImprove" class="premium-checkbox">
              <span>Słabe punkty</span>
            </label>
            <label class="topic-selector__label premium-option">
              <input type="checkbox" name="premium_option" value="toRemind" class="premium-checkbox">
              <span>Powtórki</span>
            </label>
            <label class="topic-selector__label premium-option">
              <input type="checkbox" name="premium_option" value="lastMistakes" class="premium-checkbox">
              <span>Ostatnie błędy</span>
            </label>
            <label class="topic-selector__label premium-option">
              <input type="checkbox" name="premium_option" value="saved" class="premium-checkbox">
              <span>Zapisane</span>
            </label>
          </div>
          <div class="config-step__actions">
            <button type="button" class="btn btn--secondary" data-action="prev">Wróć</button>
            <button type="submit" class="btn btn--primary btn" id="start-test-btn">Rozpocznij Test!</button>
          </div>
        </fieldset>
      </form>
    </div>

    <!--
      Główny kontener na dynamicznie renderowany test.
      Początkowo pusty, zostaje wypełniony przez JS po wysłaniu formularza.
    -->
    <section id="quiz-container"></section>

    <!-- Stan 3: Ekran wyników (początkowo ukryty, poza głównym kontenerem) -->
    <section id="results-screen" class="hidden">
      <div class="review-page__main-wrapper">
        <div class="review-page__container">

          <div class="review-page__back-nav">
            <a href="<?= url('/') ?>" class="back-link">
              <i class="fas fa-arrow-left"></i> Wróć na stronę główną
            </a>
          </div>

          <section id="score-summary" class="review-page__summary">
            <!-- Wypełniane przez JS -->
          </section>

          <h2 id="review-section-title" class="review-page__section-title">Szczegóły pytań</h2>

          <section id="results-details" class="review-page__questions">
            <!-- Szczegóły generowane przez JS -->
          </section>

          <div class="centered--push-down">
            <a href="#top" class="btn btn--primary btn--large">Przewiń na górę</a>
          </div>

        </div>
      </div>
    </section>

    <div class="centered--push-down">
      <button type="button" class="btn btn--secondary" data-restart-personalized-test-tutorial>
        <i class="fas fa-route"></i> Uruchom samouczek ponownie
      </button>
    </div>
  </div>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
