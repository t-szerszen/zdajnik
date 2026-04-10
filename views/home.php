<?php
/**
 * Konfiguracja komponentu head
 */
$pageTitle = 'INF.02 INF.03 - Testy Online i kursy | Examly';
$pageDescription = 'Zwiększ swoje szanse na zdanie egzaminu INF.03 i INF.02. Rozwiązuj testy online z tysięcy pytań, śledź postępy i ucz się mądrzej z Examly. Zacznij dziś!';
$canonicalUrl = 'https://www.examly.pl/';

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
    "name" => "Examly - Platforma do Egzaminów Zawodowych",
    "operatingSystem" => "All",
    "applicationCategory" => "EducationApplication",
    "featureList" => [
      "Symulacja egzaminu INF.02, INF.03, INF.04",
      "Inteligentny system statystyk i śledzenia postępów",
      "Wyszukiwarka pytań egzaminacyjnych po ID i treści",
      "Tryb nauki 'Jedno Pytanie' z wyjaśnieniami",
      "Personalizowane testy na podstawie błędów",
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
    "description" => "Nadchodzący kompleksowy kurs wideo oraz pełna baza pytań egzaminacyjnych dla kwalifikacji INF.03 (Tworzenie stron i baz danych).",
    "provider" => [
      "@type" => "Organization",
      "name" => "Examly",
      "url" => "https://www.examly.pl"
    ]
  ],
  [
    "@context" => "https://schema.org",
    "@type" => "Service",
    "name" => "Baza pytań INF.02 i INF.03 - Uzupelnianie!",
    "description" => "Trwają intensywne prace nad uzupełnieniem pełnej bazy pytań egzaminacyjnych dla kwalifikacji INF.02 oraz INF.03.",
    "provider" => [
      "@type" => "Organization",
      "name" => "Examly",
      "url" => "https://www.examly.pl"
    ]
  ]
];

/**
 * ========================================================================
 * Plik Widoku: Strona Główna (home.php)
 * ========================================================================
 *
 * @description Główny plik widoku dla strony startowej aplikacji Examly.
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

  <main class="home-page">
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
      <div class="hero-section__content">
        <h1 class="hero-section__title"><span class="text-gradient">Zdaj</span> egzamin zawodowy bez stresu.</h1>
        <p class="hero-section__subtitle">Opanuj cały materiał dzięki naszej inteligentnej platformie z tysiącami pytań
          i zdobądź wynik o którym marzysz.</p>
        <div class="hero-section__cta-group">
          <a href="<?= url('#egzaminy') ?>" class="btn btn--primary btn--large">Rozpocznij egzamin próbny</a>

          <!-- CTA widoczne tylko dla gości -->
          <?php if (!$isUserLoggedIn): ?>
            <a href="<?= url('rejestracja') ?>" class="btn btn--secondary btn--large">
              <i class="fas fa-user-plus" style="margin-right: 0.5rem;"></i> Załóż darmowe konto
            </a>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- 
      =====================================================================
      Sekcja 2: Features Section
      ---------------------------------------------------------------------
      Cel: Przedstawienie kluczowych funkcjonalności i zalet platformy
           w przystępnej formie kart.
      =====================================================================
    -->
    <section class="features-section">
      <h2 class="section-title">Wszystko, czego potrzebujesz w jednym miejscu</h2>
      <div class="features-grid">
        <!-- Przykładowa karta funkcji -->
        <div class="feature-card">
          <div class="feature-card__header">
            <i class="feature-card__icon fas fa-database"></i>
            <h3 class="feature-card__title">Ogromna Baza Pytań</h3>
          </div>
          <p class="feature-card__text">Korzystaj z tysięcy pytań z oficjalnych arkuszy egzaminacyjnych z poprzednich
            lat.</p>
          <div class="feature-card__details">
            <p>Nasza baza jest stale aktualizowana, co gwarantuje Ci dostęp do najbardziej kompletnego zbioru materiałów
              przygotowawczych.</p>
          </div>
        </div>
        <div class="feature-card">
          <div class="feature-card__header">
            <i class="feature-card__icon fas fa-lightbulb"></i>
            <h3 class="feature-card__title">Inteligentna Nauka <span class="sparkling">✨</span></h3>
          </div>
          <p class="feature-card__text">
            Nasz system śledzi Twoje postępy i oferuje inteligentne funkcje doboru pytań.
          </p>
          <div class="feature-card__details">
            <p>
              Ucz się mądrzej z naszymi inteligentnymi funkcjami:
            </p>
            <ul class="feature-card__list">
              <li>
                <strong>Pięć filtrów nauki:</strong>
                Aplikacja oferuje wyjątkowe tryby nauki maksymalizujące efekty Twojej pracy.
              </li>
              <li>
                <strong>Gotowe wyjaśnienia:</strong>
                Każde pytanie zawiera szczegółowe wyjaśnienie,
                dzięki czemu nie tracisz cennego czasu na szukanie odpowiedzi.
              </li>
            </ul>
          </div>
        </div>
        <div class="feature-card">
          <div class="feature-card__header">
            <i class="feature-card__icon fas fa-chart-line"></i>
            <h3 class="feature-card__title">Szczegółowe Statystyki</h3>
          </div>
          <p class="feature-card__text">Analizuj swoje wyniki i śledź postępy, by wiedzieć, na czym się skupić.</p>
          <div class="feature-card__details">
            <p>
              Analizuj swoją skuteczność w każdym dziale dzięki czytelnym wykresom i tabelom. Obserwuj,
              jak rośnie Twoja gotowość do egzaminu – wszystkie te funkcje są dostępne już w darmowym koncie!
            </p>
          </div>
        </div>
        <div class="feature-card">
          <div class="feature-card__header">
            <i class="feature-card__icon fas fa-keyboard"></i>
            <div class="feature-card__title-group">
              <h3 class="feature-card__title">Arkusze Praktyczne</h3>
              <span class="soon-badge">Wkrótce!</span>
            </div>
          </div>
          <p class="feature-card__text">
            Wyszukuj dowolne arkusze praktyczne i filtruj po występujących w nich zagadnieniach.
          </p>
          <div class="feature-card__details">
            <p>
              Każdy arkusz to kompletny zestaw zadań praktycznych, który pomoże Ci oswoić się z formą egzaminu.
            </p>
          </div>
        </div>
        <div class="feature-card">
          <div class="feature-card__header">
            <i class="feature-card__icon fas fa-graduation-cap"></i>
            <div class="feature-card__title-group">
              <h3 class="feature-card__title">Kursy</h3>
              <span class="soon-badge">Wkrótce!</span>
            </div>
          </div>
          <p class="feature-card__text">
            Skorzystaj z naszych kompleksowych kursów wideo, które przeprowadzą Cię przez każdy temat.
          </p>
          <div class="feature-card__details">
            <p>
              Najtrudniejsze zagadnienia wytłumaczone w prosty i zrozumiały sposób, krok po kroku.
            </p>
          </div>
        </div>
        <div class="feature-card">
          <div class="feature-card__header">
            <i class="feature-card__icon fas fa-feather-alt"></i>
            <h3 class="feature-card__title">Przyjazny Interfejs</h3>
          </div>
          <p class="feature-card__text">
            Ucz się bez przeszkód dzięki prostemu i intuicyjnemu interfejsowi na każdym urządzeniu.
          </p>
          <div class="feature-card__details">
            <p>
              Zaprojektowaliśmy Examly tak, aby nic nie odciągało Twojej uwagi od tego,
              co najważniejsze – efektywnej nauki.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="search-feature-section">
      <div class="search-feature-card">
        <div class="search-feature-card__content">
          <h2 class="search-feature-card__title">Zapomniałeś jakiegoś pytania? <span class="text-gradient">Znajdź je
              teraz.</span></h2>
          <p class="search-feature-card__text">
            Nasza wyszukiwarka pozwala na błyskawiczne znalezienie pytania po treści lub jego unikalnym numerze ID.
            Dzięki temu szybko sprawdzisz poprawne odpowiedzi i obszerne wyjaśnienia.
          </p>
          <div class="search-feature-card__cta">
            <a href="<?= url('search') ?>" class="btn btn--primary btn--large">
              <i class="fas fa-search" style="margin-right: 0.5rem;"></i> Wypróbuj wyszukiwarkę
            </a>
          </div>
        </div>
        <div class="search-feature-card__image-preview">
          <div class="search-preview-mockup">
            <div class="search-preview-mockup__dots">
              <span></span><span></span><span></span>
            </div>
            <div class="search-preview-mockup__content">
              <div class="search-preview-mockup__bar">402</div>
              <div class="search-preview-mockup__item">
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
      Sekcja 3: Exams Section
      ---------------------------------------------------------------------
      Cel: Bezpośrednie skierowanie użytkownika do dostępnych egzaminów.
           Używa wizualnych kart do reprezentowania każdej kwalifikacji.
           Identyfikator `id="exams-section"` służy jako kotwica dla linków.
      =====================================================================
    -->
    <section class="exams-section" id="egzaminy">
      <h2 class="section-title">Rozwiąż test z swojej kwalifikacji już teraz</h2>
      <p class="section-subtitle">Wybierz swoją specjalizację i sprawdź się w symulacji prawdziwego egzaminu
        państwowego.</p>
      <div class="exams-grid">
        <a href="#" class="exam-card exam-card--disabled" onclick="return false;">
          <img src="https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=2070&auto=format&fit=crop"
            alt="Praca przy komputerze i nauka programowania webowego INF.03" class="exam-card__background">
          <div class="exam-card__overlay"></div>
          <div class="exam-card__content">
            <h3 class="exam-card__title">INF.03 (dawniej E.14)</h3>
            <p class="exam-card__subtitle">
              Programowanie webowe<br>
              <span class="soon-badge"
                style="margin-left: 0; margin-top: 0.5rem; display: inline-block;">Wkrótce!</span>
            </p>
          </div>
        </a>
        <!-- Karta nieaktywna, z modyfikatorem BEM `--disabled` -->
        <a href="#" class="exam-card exam-card--disabled" onclick="return false;">
          <img src="https://images.unsplash.com/photo-1544197150-b99a580bb7a8?q=80&w=2070&auto=format&fit=crop"
            alt="Systemy i sieci komputerowe - przygotowanie do INF.02" class="exam-card__background">
          <div class="exam-card__overlay"></div>
          <div class="exam-card__content">
            <h3 class="exam-card__title">INF.02 (dawniej EE.08)</h3>
            <p class="exam-card__subtitle">
              Sprzęt, systemy i sieci<br>
              <span class="soon-badge"
                style="margin-left: 0; margin-top: 0.5rem; display: inline-block;">Wkrótce!</span>
            </p>
          </div>
        </a>
        <a href="<?= url('test/INF.04') ?>" class="exam-card">
          <img src="https://images.unsplash.com/photo-1605379399642-870262d3d051?q=80&w=2106&auto=format&fit=crop"
            alt="Pisanie kodu i projektowanie aplikacji mobilnych INF.04" class="exam-card__background">
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
      Sekcja 4: Exam Info Section (FAQ)
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
      Sekcja 5: Topics Section
      ---------------------------------------------------------------------
      Cel: Umożliwienie użytkownikom nauki z konkretnych działów tematycznych,
           co stanowi alternatywną ścieżkę nawigacji.
      =====================================================================
    -->
    <section class="topics-section" id="topics-section">
      <h2 class="section-title">Ucz się z konkretnych działów</h2>
      <p class="section-subtitle">Skup się na wybranych zagadnieniach i szlifuj wiedzę tam, gdzie potrzebujesz tego
        najbardziej.</p>
      <div class="topics-wrapper">
        <?php
        $icons = [
          'INF.02' => 'fas fa-microchip',
          'INF.03' => 'fas fa-laptop-code',
          'INF.04' => 'fas fa-code'
        ];
        foreach ($examsCategories as $examCode => $categories):
          $icon = $icons[$examCode] ?? 'fas fa-laptop-code';
          $isSoon = $examCode !== 'INF.04';
          ?>
          <div class="topics-group">
            <h3 class="topics-group__subheading">
              <i class="topics-group__icon <?= $icon ?>"></i>
              <span>Kwalifikacja <?= htmlspecialchars((string) $examCode) ?></span>
              <?php if ($isSoon): ?>
                <span class="soon-badge">Wkrótce!</span>
              <?php endif; ?>
            </h3>
            <div class="topics-grid">
              <?php if ($isSoon): ?>
                <?php if ($examCode === 'INF.02'): ?>
                  <a href="#" class="topic-card topic-card--disabled">Sprzęt komputerowy</a>
                  <a href="#" class="topic-card topic-card--disabled">Systemy operacyjne</a>
                  <a href="#" class="topic-card topic-card--disabled">Sieci komputerowe</a>
                <?php elseif ($examCode === 'INF.03'): ?>
                  <a href="#" class="topic-card topic-card--disabled">Tworzenie stron internetowych</a>
                  <a href="#" class="topic-card topic-card--disabled">Bazy danych</a>
                  <a href="#" class="topic-card topic-card--disabled">Aplikacje internetowe</a>
                <?php else: ?>
                  <p>Brak działów.</p>
                <?php endif; ?>
              <?php else: ?>
                <?php foreach ($categories as $category): ?>
                  <a href="<?= url("jedno-pytanie/{$examCode}?subject[]=" . $category['id']) ?>" class="topic-card">
                    <?= htmlspecialchars($category['name']) ?>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <!-- Dołączenie reużywalnego komponentu stopki -->
  <?php include 'partials/footer.php'; ?>
</body>

</html>