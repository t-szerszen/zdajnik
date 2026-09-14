<?php
/**
 * @file home.php
 * @description
 * Strona główna platformy (Landing Page).
 *
 * @variables
 * - $pageTitle (string)
 * - $extraSchemas (array): Dane strukturalne
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

/**
 * Konfiguracja komponentu head
 */
$pageTitle = 'Zdajnik - nauka do egzaminów zawodowych INF.02, INF.03 i INF.04';
$pageDescription = 'Przygotuj się do egzaminu zawodowego z Zdajnik. Rozwiązuj pytania egzaminacyjne, ucz się według tematów, rób egzaminy próbne i sprawdzaj swoje postępy.';
$canonicalUrl = url('/');

// Dodajemy uporządkowane dane dla SEO (FAQ i Funkcje platformy)
$extraSchemas = [
  [
    "@context" => "https://schema.org",
    "@type" => "FAQPage",
    "mainEntity" => [
      [
        "@type" => "Question",
        "name" => "Z czego składa się egzamin?",
        "acceptedAnswer" => [
          "@type" => "Answer",
          "text" => "Egzamin podzielony jest na dwie kluczowe części: pisemną i praktyczną. Etap pisemny to test wyboru trwający 60 minut, składający się z 40 pytań z jedną prawidłową odpowiedzią. Etap praktyczny to zadanie do wykonania przy komputerze, na które przeznaczone jest 240 minut."
        ]
      ],
      [
        "@type" => "Question",
        "name" => "Jakie są progi zdawalności?",
        "acceptedAnswer" => [
          "@type" => "Answer",
          "text" => "Z części pisemnej: minimum 50% punktów. Z części praktycznej: minimum 75% punktów."
        ]
      ],
      [
        "@type" => "Question",
        "name" => "Co w przypadku niepowodzenia?",
        "acceptedAnswer" => [
          "@type" => "Answer",
          "text" => "Jeśli nie powiedzie Ci się tylko w jednej z części, możesz przystąpić do poprawki wyłącznie tej części. Oblanie obu etapów wiąże się z koniecznością ponownego zdawania całego egzaminu."
        ]
      ],
      [
        "@type" => "Question",
        "name" => "Czy muszę podchodzić do egzaminu?",
        "acceptedAnswer" => [
          "@type" => "Answer",
          "text" => "Dla uczniów technikum w nowej formule (od 2019 r., kwalifikacje INF.02 i INF.03), podejście do egzaminu jest obowiązkowe w celu ukończenia szkoły."
        ]
      ]
    ]
  ],
  [
    "@context" => "https://schema.org",
    "@type" => "WebApplication",
    "name" => "Zdajnik - Platforma do Egzaminów Zawodowych",
    "operatingSystem" => "All",
    "applicationCategory" => "EducationApplication",
    "featureList" => [
      "Symulacja egzaminu INF.02, INF.03, INF.04",
      "Panel postępów i historia wyników",
      "Wyszukiwarka pytań egzaminacyjnych po ID i treści",
      "Tryb nauki 'Jedno Pytanie' z wyjaśnieniami",
      "Nauka według tematów i powtórki błędów",
      "Kursy wideo dla technika informatyka (wkrótce!)"
    ],
    "offers" => [
      "@type" => "Offer",
      "price" => "0",
      "priceCurrency" => "PLN"
    ]
  ],
  [
    "@context" => "https://schema.org",
    "@type" => "Course",
    "name" => "Kurs przygotowawczy INF.03 - Wkrótce!",
    "description" => "Nadchodzący kompleksowy kurs wideo dla kwalifikacji INF.03 (Tworzenie stron i baz danych), uzupełniający dostępną już bazę pytań egzaminacyjnych.",
    "provider" => [
      "@type" => "Organization",
      "name" => "Zdajnik",
      "url" => "https://www.zdajnik.pl"
    ]
  ],
  [
    "@context" => "https://schema.org",
    "@type" => "Service",
    "name" => "Baza pytań INF.02",
    "description" => "Pełna baza pytań egzaminacyjnych dla kwalifikacji INF.02.",
    "provider" => [
      "@type" => "Organization",
      "name" => "Zdajnik",
      "url" => "https://www.zdajnik.pl"
    ]
  ]
];

/**
 * ========================================================================
 * Plik Widoku: Strona Główna (home.php)
 * ========================================================================
 *
 * @description Główny plik widoku dla strony startowej aplikacji Zdajnik.
 * Prezentuje kluczowe informacje o platformie, jej funkcje
 * oraz zachęca użytkowników do interakcji (call-to-action).
 * Struktura pliku opiera się na modułowym dołączaniu
 * komponentów (navbar, footer) oraz sekcjach semantycznych.
 *
 * @dependencies 
 * - partials/head.php (head)
 * - partials/navbar.php (nawigacja)
 * - partials/footer.php (stopka)
 * - main.css (główne style)
 * - Font Awesome (ikony)
 *
 * @state_variables 
 * - $isUserLoggedIn (bool): Zmienna z logiki serwera,
 *   która określa, czy użytkownik jest zalogowany.
 *   Używana do warunkowego renderowania elementów UI.
 */
?>
<!DOCTYPE html>
<html lang="pl">
<!-- Dołączenie reużywalnego komponentu head -->
<?php include 'partials/head.php'; ?>

<body class="home-page-background">
  <!-- Dołączenie reużywalnego komponentu paska nawigacyjnego -->
  <?php include 'partials/navbar.php'; ?>

  <main class="home-page" data-home-experience>
    <!-- 
      =====================================================================
      Sekcja 1: Hero Section
      ---------------------------------------------------------------------
      Cel: Pierwsze wrażenie. Ma za zadanie przyciągnąć uwagę użytkownika,
           jasno zakomunikować główną wartość platformy i skierować go
           do kluczowych akcji (CTA).
      =====================================================================
    -->
    <section class="hero-section">
      <div class="hero-section__grid">
        <div class="hero-section__content">
          <p class="hero-section__lockup">
            <span class="hero-section__lockup-badge">Zdajnik</span>
            <span class="hero-section__lockup-text">Od ucznia dla uczniów ;)</span>
          </p>
          <h1 class="hero-section__title">
            Przygotuj się do egzaminu zawodowego
            <span class="hero-section__title-accent" data-hero-rotating-word>spokojnie</span>
          </h1>
          <p class="hero-section__subtitle">Rozwiązuj pytania z arkuszy, ucz się według tematów i sprawdzaj, które działy
            warto jeszcze powtórzyć przed egzaminem.</p>
          <div class="hero-section__cta-group">
            <a href="<?= url('#egzaminy') ?>" class="btn btn--primary btn--large hero-section__cta-primary">Rozpocznij egzamin próbny</a>
            <a href="<?= url('#topics-section') ?>" class="btn btn--secondary btn--large hero-section__cta-secondary">Zobacz tematy</a>
            <?php if (!$isUserLoggedIn): ?>
              <a href="<?= url('rejestracja') ?>" class="btn btn--secondary btn--large">
                <i class="fas fa-user-plus"></i> Załóż darmowe konto
              </a>
            <?php endif; ?>
          </div>
        </div>
        <?php
        $features = include 'partials/data/hero_features.php';
        $featureGridTag = 'aside';
        $featureGridAriaLabel = 'Możliwości Zdajnika';
        include 'partials/feature_grid.php';
        ?>
      </div>
    </section>

    <section class="search-feature-section">
      <div class="search-feature-card">
        <div class="search-feature-card__content">
          <p class="search-feature-card__eyebrow">Wyszukiwarka</p>
          <h2 class="search-feature-card__title">Znajdź pytanie po treści albo numerze ID</h2>
          <p class="search-feature-card__text">
            Wpisz fragment pytania, numer albo wybierz kwalifikację i temat. To przydatne, gdy chcesz wrócić do
            konkretnego zadania z lekcji, kartkówki lub egzaminu próbnego.
          </p>
          <div class="search-feature-card__cta">
            <a href="<?= url('search') ?>" class="btn btn--primary btn--large">
              <i class="fas fa-search"></i> Wypróbuj wyszukiwarkę
            </a>
          </div>
        </div>
        <div class="search-feature-card__image-preview">
          <div class="search-preview-mockup">
            <p class="search-preview-mockup__label">Przykład wyszukiwania</p>
            <div class="search-preview-mockup__content">
              <div class="search-preview-mockup__bar" data-search-demo-term>402</div>
              <div class="search-preview-mockup__item" data-search-demo-item>
                <strong>Pytanie egzaminacyjne</strong>
                <div class="search-preview-mockup__line" style="width: 80%"></div>
                <div class="search-preview-mockup__line" style="width: 60%"></div>
                <div class="search-preview-mockup__line search-preview-mockup__line--short"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 
      =====================================================================
      Sekcja 2: Exams Section
      ---------------------------------------------------------------------
      Cel: Bezpośrednie skierowanie użytkownika do dostępnych egzaminów.
           Używa wizualnych kart do reprezentowania każdej kwalifikacji.
           Identyfikator `id="exams-section"` służy jako kotwica dla linków.
      =====================================================================
    -->
    <section class="exams-section" id="egzaminy">
      <h2 class="section-title">Wybierz kwalifikację i zacznij ćwiczyć</h2>
      <p class="section-subtitle">Dostępne są kwalifikacje INF.02, INF.03 i INF.04. Kolejne działy będziemy dodawać stopniowo.</p>
      <div class="exams-grid">
        <a href="<?= url('test/inf03') ?>" class="exam-card">
          <img src="<?= url('images/exams/inf03-programowanie-webowe-800.webp') ?>"
            srcset="<?= url('images/exams/inf03-programowanie-webowe-400.webp') ?> 400w, <?= url('images/exams/inf03-programowanie-webowe-800.webp') ?> 800w, <?= url('images/exams/inf03-programowanie-webowe.webp') ?> 900w"
            sizes="(max-width: 768px) 100vw, (max-width: 1100px) 50vw, 400px"
            alt="Praca przy komputerze i nauka programowania webowego INF.03" class="exam-card__background"
            width="900" height="600" loading="lazy">
          <div class="exam-card__overlay"></div>
          <div class="exam-card__content">
            <h3 class="exam-card__title">INF.03 (dawniej E.14)</h3>
            <p class="exam-card__subtitle">
              Programowanie webowe
            </p>
          </div>
        </a>
        <a href="<?= url('test/inf02') ?>" class="exam-card">
          <img src="<?= url('images/exams/inf02-systemy-i-sieci-800.webp') ?>"
            srcset="<?= url('images/exams/inf02-systemy-i-sieci-400.webp') ?> 400w, <?= url('images/exams/inf02-systemy-i-sieci-800.webp') ?> 800w, <?= url('images/exams/inf02-systemy-i-sieci.webp') ?> 900w"
            sizes="(max-width: 768px) 100vw, (max-width: 1100px) 50vw, 400px"
            alt="Systemy i sieci komputerowe - przygotowanie do INF.02" class="exam-card__background"
            width="900" height="600" loading="lazy">
          <div class="exam-card__overlay"></div>
          <div class="exam-card__content">
            <h3 class="exam-card__title">INF.02 (dawniej EE.08)</h3>
            <p class="exam-card__subtitle">
              Sprzęt, systemy i sieci
            </p>
          </div>
        </a>
        <a href="<?= url('test/inf04') ?>" class="exam-card">
          <img src="<?= url('images/exams/inf04-aplikacje-800.webp') ?>"
            srcset="<?= url('images/exams/inf04-aplikacje-400.webp') ?> 400w, <?= url('images/exams/inf04-aplikacje-800.webp') ?> 800w, <?= url('images/exams/inf04-aplikacje.webp') ?> 900w"
            sizes="(max-width: 768px) 100vw, (max-width: 1100px) 50vw, 400px"
            alt="Pisanie kodu i projektowanie aplikacji mobilnych INF.04" class="exam-card__background"
            width="900" height="577" loading="lazy">
          <div class="exam-card__overlay"></div>
          <div class="exam-card__content">
            <h3 class="exam-card__title">INF.04 (dawniej E.14)</h3>
            <p class="exam-card__subtitle">
              Projektowanie i programowanie aplikacji
            </p>
          </div>
        </a>
      </div>
    </section>

    <!-- 
      =====================================================================
      Sekcja 3: Exam Info Section (FAQ)
      ---------------------------------------------------------------------
      Cel: Odpowiedź na najczęściej zadawane pytania dotyczące egzaminu,
           budowanie zaufania i dostarczanie wartościowej treści.
      =====================================================================
    -->
    <section class="exam-info-section">
      <h2 class="section-title">Podstawowe informacje o egzaminie zawodowym</h2>
      <p class="section-subtitle">To musisz wiedzieć o egzaminie!</p>
      <div class="info-grid">
        <div class="info-block">
          <div class="info-block__header">
            <i class="info-block__icon fas fa-file-invoice"></i>
            <h3 class="info-block__question">Z czego składa się egzamin?</h3>
          </div>
          <p class="info-block__answer">
            Egzamin podzielony jest na dwie kluczowe części: pisemną i praktyczną. Etap pisemny to test
            wyboru trwający <strong>60 minut</strong>, składający się z 40 pytań z jedną prawidłową odpowiedzią.
            Etap praktyczny to zadanie do wykonania przy komputerze,
            na które przeznaczone jest <strong>240 minut</strong>.
          </p>
        </div>
        <div class="info-block">
          <div class="info-block__header">
            <i class="info-block__icon fas fa-bullseye"></i>
            <h3 class="info-block__question">Jakie są progi zdawalności?</h3>
          </div>
          <div class="info-block__answer">
            <p>Aby uzyskać kwalifikację zawodową, musisz osiągnąć dwa progi punktowe:</p>
            <ul class="info-block__list">
              <li>Z części pisemnej: minimum <strong>50%</strong> punktów.</li>
              <li>Z części praktycznej: minimum <strong>75%</strong> punktów.</li>
            </ul>
          </div>
        </div>
        <div class="info-block">
          <div class="info-block__header">
            <i class="info-block__icon fas fa-rotate-right"></i>
            <h3 class="info-block__question">Co w przypadku niepowodzenia?</h3>
          </div>
          <p class="info-block__answer">
            Warunkiem zdania całego egzaminu jest zaliczenie obu jego części.
            Jeśli nie powiedzie Ci się tylko w jednej z nich,
            możesz przystąpić do <strong>poprawki wyłącznie tej części</strong>.
            Oblanie obu etapów wiąże się z koniecznością ponownego zdawania całego egzaminu.
          </p>
        </div>
        <div class="info-block">
          <div class="info-block__header">
            <i class="info-block__icon fas fa-graduation-cap"></i>
            <h3 class="info-block__question">Czy muszę podchodzić do egzaminu?</h3>
          </div>
          <p class="info-block__answer">
            Dla uczniów technikum w nowej formule (od 2019 r., kwalifikacje INF.02 i INF.03),
            <strong>podejście do egzaminu jest obowiązkowe</strong> w celu ukończenia szkoły.
            Pamiętaj jednak, że jego wynik (nawet 0%) <strong>nie wpływa na promocję</strong>
            do następnej klasy ani na ukończenie szkoły.
          </p>
        </div>
      </div>
    </section>

    <!-- 
      =====================================================================
      Sekcja 4: Topics Section
      ---------------------------------------------------------------------
      Cel: Umożliwienie użytkownikom nauki z konkretnych działów tematycznych,
           co stanowi alternatywną ścieżkę nawigacji.
      =====================================================================
    -->
    <section class="topics-section" id="topics-section">
      <h2 class="section-title">Ucz się z konkretnych działów</h2>
      <p class="section-subtitle">Wybierz temat, który przerabiacie w szkole albo który chcesz powtórzyć przed egzaminem.</p>
      <div class="topics-wrapper">
        <?php
        $icons = [
          'INF.02' => 'fas fa-microchip',
          'INF.03' => 'fas fa-laptop-code',
          'INF.04' => 'fas fa-code'
        ];
        foreach ($examsCategories as $examCode => $categories):
          $icon = $icons[$examCode] ?? 'fas fa-laptop-code';
          $isSoon = false;
          ?>
          <div class="topics-group">
            <h3 class="topics-group__subheading">
              <i class="topics-group__icon <?= $icon ?>"></i>
              <span>Kwalifikacja <?= htmlspecialchars((string) $examCode) ?></span>
              <?php if ($isSoon): ?>
                <span class="soon-badge">Wkrótce!</span>
              <?php endif; ?>
            </h3>
            <?php if (!$isSoon): ?>
              <div class="topics-grid">
                <?php 
                  $canonicalExamCode = exam_code_to_url((string) $examCode);
                  foreach ($categories as $category): 
                ?>
                  <a href="<?= url("jedno-pytanie/{$canonicalExamCode}?subject[]=" . $category['id']) ?>" class="topic-card">
                    <?= htmlspecialchars($category['name']) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <!-- Dołączenie reużywalnego komponentu stopki -->
  <?php include 'partials/footer.php'; ?>
</body>

</html>
