<?php
/**
 * @file head.php
 * @description
 * Główny blok <head> dokumentu HTML. Odpowiada za inicjalizację metadanych SEO
 * (Meta Tags, Open Graph, Schema.org), import zasobów zewnętrznych (Google Fonts, 
 * FontAwesome) oraz ładowanie skompilowanych stylów SCSS i skryptów systemowych.
 *
 * @variables
 * - $pageTitle (string): Tytuł strony (wyświetlany w karcie przeglądarki).
 * - $pageDescription (string): Opis meta description dla wyszukiwarek.
 * - $canonicalUrl (string): Kanoniczny adres URL strony.
 * - $extraScripts (array|null): Dodatkowe ścieżki do skryptów JS.
 * - $extraSchemas (array|null): Dodatkowe dane strukturalne JSON-LD.
 * - $noIndex (bool|null): Jeśli true, strona otrzyma tag 'noindex'.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 */
?>

<head>
  <!-- ================================================================== -->
  <!-- 1. Podstawowa konfiguracja i metadane -->
  <!-- ================================================================== -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="referrer" content="strict-origin-when-cross-origin">

  <!-- 
    @property theme-color
    @description Ustawia kolor paska narzędzi przeglądarki na urządzeniach mobilnych,
                 co poprawia spójność wizualną marki i postrzeganie strony.
  -->
  <meta name="theme-color" content="#7928CA">
  <meta name="color-scheme" content="light dark">

  <!-- ================================================================== -->
  <!-- 2. Kluczowe Tagi SEO (Tytuł, Opis, Kanoniczny URL) -->
  <!-- ================================================================== -->
  <title><?= htmlspecialchars($pageTitle ?? 'Examly - Egzaminy Zawodowe INF.03') ?></title>

  <!-- 
    @property description
    @description Kluczowy dla SEO. To jest tekst, który Google najczęściej
                 pokazuje pod tytułem strony w wynikach wyszukiwania.
                 Powinien być zwięzły (ok. 155 znaków) i zachęcający.
  -->
  <meta name="description"
    content="<?= htmlspecialchars($pageDescription ?? 'Pytania z arkuszy, własne zestawy i statystyki do nauki przed egzaminem zawodowym.') ?>">

  <!-- 
    @property canonical
    @description Absolutnie kluczowy dla unikania problemu duplikacji treści.
                 Wskazuje wyszukiwarkom, który URL jest "oryginalną" wersją
                 danej strony, nawet jeśli jest dostępna pod wieloma adresami.
  -->
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl ?? url($_SERVER['REQUEST_URI'] ?? '/')) ?>">

  <!-- 
    @property author, robots
    @description Informacje dla robotów wyszukiwarek. 'index, follow' to
                 standardowe ustawienie, które pozwala na indeksowanie
                 strony i podążanie za linkami.
  -->
  <meta name="author" content="Examly.pl">
  <?php
  // Ustaw domyślne wartości
  $index_directive = 'index';
  $follow_directive = 'follow';

  // Sprawdź, czy strona ma być wyłączona z indeksowania
  if (isset($noIndex) && $noIndex === true) {
    $index_directive = 'noindex';
  }

  // Sprawdź, czy roboty nie mają podążać za linkami
  if (isset($noFollow) && $noFollow === true) {
    $follow_directive = 'nofollow';
  }
  ?>
  <meta name="robots" content="<?= $index_directive ?>, <?= $follow_directive ?>">
  <meta name="googlebot"
    content="<?= $index_directive ?>, <?= $follow_directive ?>, max-snippet:-1, max-image-preview:large, max-video-preview:-1">

  <!-- ================================================================== -->
  <!-- 3. Podglądy w mediach społecznościowych (Open Graph & Twitter) -->
  <!-- ================================================================== -->
  <!-- Protokół Open Graph (Facebook, LinkedIn, Pinterest, etc.) -->
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'Examly - Egzaminy Zawodowe INF.03') ?>">
  <meta property="og:description"
    content="<?= htmlspecialchars($pageDescription ?? 'Ćwicz na pytaniach z arkuszy i sprawdzaj swoje postępy.') ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl ?? url($_SERVER['REQUEST_URI'] ?? '/')) ?>">
  <meta property="og:image" content="<?= url('images/company/examly-og-1200x630.png') ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="Examly - platforma do nauki i testów egzaminów zawodowych">
  <meta property="og:locale" content="pl_PL">
  <meta property="og:site_name" content="Examly">

  <!-- Twitter Cards -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? 'Examly - Egzaminy Zawodowe INF.03') ?>">
  <meta name="twitter:description"
    content="<?= htmlspecialchars($pageDescription ?? 'Sprawdź darmowe testy i materiały edukacyjne.') ?>">
  <meta name="twitter:site" content="@examlypl">
  <meta name="twitter:image" content="<?= url('images/company/examly-twitter-1200x600.png') ?>">

  <!-- ================================================================== -->
  <!-- 4. Ikony i zasoby (Assets) -->
  <!-- ================================================================== -->
  <link rel="icon" href="<?= url('images/company/favicon.ico') ?>" type="image/x-icon">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= url('images/company/favicon-32x32.png') ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= url('images/company/favicon-16x16.png') ?>">
  <link rel="icon" type="image/png" sizes="48x48" href="<?= url('images/company/favicon-48x48.png') ?>">
  <link rel="manifest" href="<?= url('site.webmanifest') ?>">

  <!-- 
    @property apple-touch-icon
    @description Ikona wyświetlana na ekranie głównym urządzeń Apple (iPhone, iPad),
                 gdy użytkownik doda skrót do Twojej strony. Ważne dla brandingu.
  -->
  <link rel="apple-touch-icon" sizes="180x180" href="<?= url('images/company/apple-touch-icon.png') ?>">

  <link rel="stylesheet"
    href="<?= url('scss/main.css?v=' . filemtime($_SERVER['DOCUMENT_ROOT'] . '/scss/main.css')) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- ================================================================== -->
  <!-- 5. Uporządkowane dane (Structured Data - Schema.org) - ZAAWANSOWANE SEO -->
  <!-- ================================================================== -->
  <!-- 
    @description To jest "język", którym mówisz do Google, czym dokładnie
                 jest Twoja strona. Pomaga to w uzyskaniu tzw. "rich snippets"
                 (np. gwiazdki, FAQ) w wynikach wyszukiwania.
                 Poniżej przykład dla strony edukacyjnej.
  -->
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "Examly",
      "url": "<?= url('/') ?>",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "<?= url('search') ?>?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
  </script>
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Examly",
      "url": "<?= url('/') ?>",
      "logo": "<?= url('images/company/examly-logo-512.png') ?>"
    }
  </script>

  <?php if (isset($extraSchemas) && is_array($extraSchemas)): ?>
    <?php foreach ($extraSchemas as $schema): ?>
      <script type="application/ld+json">
            <?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
          </script>
    <?php endforeach; ?>
  <?php endif; ?>


  <script>
    (function () {
      try {
        // Sprawdź zapisany motyw w localStorage
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
          document.documentElement.classList.add('dark');
        } else if (savedTheme === 'light') {
          document.documentElement.classList.remove('dark');
        } else {
          // Jeśli brak zapisanego motywu, sprawdź preferencje systemowe
          if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
          }
        }
      } catch (e) {
        // W razie problemów z localStorage, nic nie rób
      }
    })();
  </script>

  <!-- ================================================================== -->
  <!-- 6. Opcjonalne skrypty JavaScript dla konkretnych widoków -->
  <!-- ================================================================== -->
  <?php if (isset($extraScripts) && is_array($extraScripts)): ?>
    <?php foreach ($extraScripts as $scriptUrl): ?>
      <script src="<?= htmlspecialchars($scriptUrl) ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</head>
