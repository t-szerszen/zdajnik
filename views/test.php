<?php
/**
 * @file test.php
 * @description
 * Główny interfejs rozwiązywania pełnego egzaminu próbnego (40 pytań).
 *
 * @variables
 * - $exam (array)
 * - $examCode (string)
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

/**
 * Konfiguracja komponentu head
 */
$pageTitle = $exam['title_test'];
$pageDescription = $exam['desc_test'];
$canonicalUrl = url('test/' . exam_code_to_url($examCode));
$extraScripts = [
  'https://cdn.jsdelivr.net/npm/marked@15.0.12/marked.min.js',
  'https://cdn.jsdelivr.net/npm/dompurify@3.4.18/dist/purify.min.js'
];

/**
 * ========================================================================
 * Plik Widoku: Pełny Egzamin Próbny
 * ========================================================================
 *
 * @description Renderuje interfejs dla pełnego egzaminu próbnego.
 * Ten widok zarządza trzema głównymi stanami: ekranem ładowania,
 * aktywnym testem oraz ekranem wyników. Logika przełączania
 * między tymi stanami oraz dynamiczne ładowanie pytań
 * i obliczanie wyników jest obsługiwana przez JavaScript.
 *
 * @dependencies 
 * - partials/head.php (head)
 * - partials/navbar.php (Nawigacja)
 * - partials/footer.php (Stopka)
 * - main.css (Główne style)
 * - Font Awesome (Ikony)
 * - marked.min.js, purify.min.js (Renderowanie treści pytań)
 * - test/index.js (Główny skrypt zarządzający logiką testu)
 *
 * @state_variables 
 * - $examCode (string): Kod kwalifikacji (np. "inf03"),
 *   używany przez JS do pobrania 40 losowych pytań.
 */
?>
<!DOCTYPE html>
<html lang="pl">

<?php include 'partials/head.php'; ?>

<body class="test-page">

  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
    <!-- Nagłówek wprowadzający w kontekst strony -->
    <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">
          <?= htmlspecialchars($exam['formatted_name']) ?>
        </span> - Pełny Egzamin Próbny</h1>
      <p class="page-header__text">
        Podejdź do pełnego egzaminu próbnego. Zmierz się z symulacją zawierającą 40 losowo dobranych pytań. Weryfikuj
        swoją wiedzę w warunkach zbliżonych do rzeczywistych i precyzyjnie identyfikuj obszary do poprawy.
      </p>
      <p class="page-header__text">
        <strong class="text-gradient">Pro Tip:</strong> Po teście skup się na analizie błędnych odpowiedzi.
        Zrozumienie, dlaczego popełniłeś błąd, jest kluczem do sukcesu na prawdziwym egzaminie.
      </p>
    </div>
  </header>

  <div class="container">
    <!-- 
      =====================================================================
      Główny Kontener Testu (State Machine)
      ---------------------------------------------------------------------
      Opis: Ten kontener pełni rolę "maszyny stanów" dla interfejsu testu.
            Zawiera trzy pod-kontenery reprezentujące różne stany:
            1. #loading-screen - widoczny podczas pobierania pytań.
            2. #test-view - widoczny podczas rozwiązywania testu.
            3. #results-screen - widoczny po zakończeniu testu.
            Logika przełączania widoczności jest w całości w JS.
      =====================================================================
    -->
    <!-- Dekoracyjne elementy tła -->
    <div class="stats-page__decorations">
      <div class="stats-page__blob stats-page__blob--1"></div>
      <div class="stats-page__blob stats-page__blob--2"></div>
    </div>

    <section id="test-container" class="test-container" data-exam-code="<?= htmlspecialchars($examCode) ?>">

      <!-- Stan 1: Ekran ładowania -->
      <div id="loading-screen" class="test-loading">
        <div class="spinner"></div>
        <h2>Trwa przygotowywanie testu...</h2>
        <p>Proszę czekać, trwa losowanie pytań z całej puli specjalnie dla Ciebie.</p>
      </div>

      <!-- Stan 2: Widok aktywnego testu (początkowo ukryty) -->
      <section id="test-view" class="hidden">
        <div class="test-header">
          <div class="test-header__content-left">
            <h2 class="test-header__title">Egzamin <span class="text-gradient">
                <?= htmlspecialchars($examCode) ?>
              </span></h2>
            <div id="test-progress-text" class="test-header__progress-info">
              Postęp: <span>0 / 40</span>
            </div>
          </div>

          <div class="test-header__progress-container">
            <div id="test-progress-bar" class="test-header__progress-bar" style="width: 0%"></div>
          </div>

          <div class="test-header__meta">
            <!-- Minutnik, zarządzany przez JS -->
            <div id="timer" class="timer">60:00</div>
            <!-- Przycisk pauzy -->
            <button id="pause-test-btn" class="test-header__pause-btn" title="Zapauzuj egzamin">
              <i class="fas fa-pause"></i>
            </button>
          </div>
        </div>

        <!-- Kontener, do którego JS dynamicznie wstrzykuje pytania -->
        <div id="questions-wrapper" class="questions-wrapper"></div>

        <div class="test-footer">
          <button id="finish-test-btn" class="btn btn--primary">Zakończ i sprawdź test</button>
        </div>
      </section>

    </section>
  </div>

  <!-- Stan 2.5: Nakładka PAUZY -->
  <div id="pause-overlay" class="test-pause-overlay hidden">
    <div class="test-pause-overlay__content">
      <div class="test-pause-overlay__icon">
        <i class="fas fa-mug-hot"></i>
      </div>
      <h2>Test Zapauzowany</h2>
      <p>Czas został zatrzymany. Możesz spokojnie zająć się innymi sprawami i wrócić do testu, gdy będziesz gotowy.</p>
      <button id="resume-test-btn" class="btn btn--primary btn--large">Wznów egzamin</button>
    </div>
  </div>

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

  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
