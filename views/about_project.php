<?php
/**
 * @file about_project.php
 * @description Strona informacyjna o historii, twórcy i rozwoju projektu Zdajnik.
 */

$pageTitle = 'O projekcie | Zdajnik';
$pageDescription = 'Poznaj historię powstania Zdajnika, projekt tworzonego z myślą o uczniach przygotowujących się do egzaminów zawodowych.';

$features = [
  [
    'icon' => 'fa-list-check',
    'title' => 'Baza pytań egzaminacyjnych',
    'text' => 'Pytania zebrane w jednym miejscu i uporządkowane według kwalifikacji.'
  ],
  [
    'icon' => 'fa-circle-question',
    'title' => 'Wyjaśnienia do odpowiedzi',
    'text' => 'Po odpowiedzi możesz sprawdzić, dlaczego dana odpowiedź jest poprawna.'
  ],
  [
    'icon' => 'fa-filter',
    'title' => 'Filtry nauki',
    'text' => 'Możesz wracać do nieodkrytych pytań, błędów i słabszych tematów.'
  ],
  [
    'icon' => 'fa-clipboard-check',
    'title' => 'Egzaminy próbne',
    'text' => 'Sprawdzasz się w trybie zbliżonym do części pisemnej egzaminu.'
  ],
  [
    'icon' => 'fa-chart-simple',
    'title' => 'Statystyki postępów',
    'text' => 'Widzisz wyniki nauki i tematy, które warto jeszcze powtórzyć.'
  ],
  [
    'icon' => 'fa-magnifying-glass',
    'title' => 'Wyszukiwarka pytań',
    'text' => 'Szybko odnajdujesz konkretne zagadnienia i pytania.'
  ],
  [
    'icon' => 'fa-file-pen',
    'title' => 'Arkusze praktyczne',
    'text' => 'Materiały do samodzielnego ćwiczenia zadań praktycznych.',
    'status' => 'Wkrótce'
  ],
  [
    'icon' => 'fa-graduation-cap',
    'title' => 'Kursy',
    'text' => 'Uporządkowane materiały pomagające przygotować się krok po kroku.',
    'status' => 'Wkrótce'
  ],
];

$trustItems = [
  [
    'icon' => 'fa-user-graduate',
    'title' => 'Tworzone przez ucznia technikum',
    'text' => 'Projekt wyrósł z realnych przygotowań do egzaminu zawodowego.'
  ],
  [
    'icon' => 'fa-file-circle-check',
    'title' => 'Oparte o rzeczywiste pytania egzaminacyjne',
    'text' => 'Układ nauki jest dopasowany do tego, z czym uczeń spotyka się przed egzaminem.'
  ],
  [
    'icon' => 'fa-comments',
    'title' => 'Rozwijane na podstawie doświadczeń uczniów',
    'text' => 'Kolejne funkcje powstają z myślą o codziennej nauce i powtórkach.'
  ],
  [
    'icon' => 'fa-location-dot',
    'title' => 'Projekt rozwijany w Polsce',
    'text' => 'Zdajnik jest tworzony dla uczniów polskich techników i szkół branżowych.'
  ],
];
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="about-project">
    <section class="about-project__hero">
      <div class="about-project__container about-project__hero-grid">
        <div class="about-project__hero-copy">
          <p class="about-project__eyebrow">O projekcie</p>
          <h1>Dlaczego powstał Zdajnik?</h1>
          <p>
            Zdajnik powstał podczas przygotowań do egzaminów zawodowych. Celem projektu jest stworzenie miejsca, które
            pomaga uczniom skuteczniej uczyć się do kwalifikacji zawodowych.
          </p>
          <a href="<?= url('/') ?>" class="btn btn--primary btn--large">Przejdź do platformy</a>
        </div>

        <aside class="about-project__note" aria-label="Krótko o Zdajniku">
          <i class="fas fa-seedling"></i>
          <h2>Projekt tworzony z potrzeby</h2>
          <p>
            Nie jako anonimowa strona z testami, tylko jako narzędzie do nauki, którego brakowało podczas własnych
            przygotowań do egzaminu.
          </p>
        </aside>
      </div>
    </section>

    <section class="about-project__section">
      <div class="about-project__container about-project__story">
        <div class="about-project__section-heading">
          <span>Historia projektu</span>
          <h2>Od ucznia dla uczniów</h2>
        </div>

        <div class="about-project__story-content">
          <p>
            Projekt został stworzony przez ucznia technikum informatycznego. Podczas przygotowań do egzaminów zawodowych
            brakowało miejsca, które pomaga uczyć się spokojnie, konkretnie i bez zbędnego chaosu.
          </p>

          <div class="about-project__missing-box">
            <h3>Brakowało miejsca, które:</h3>
            <ul>
              <li>zbiera pytania egzaminacyjne w jednym miejscu,</li>
              <li>tłumaczy poprawne odpowiedzi,</li>
              <li>pokazuje postępy w nauce,</li>
              <li>pomaga wracać do trudnych zagadnień.</li>
            </ul>
          </div>

          <p>Z tego powodu powstał Zdajnik.</p>
        </div>
      </div>
    </section>

    <section class="about-project__section about-project__section--muted">
      <div class="about-project__container">
        <div class="about-project__section-heading about-project__section-heading--center">
          <span>Co oferuje Zdajnik</span>
          <h2>Narzędzia do codziennej nauki</h2>
        </div>

        <div class="about-project__feature-grid">
          <?php foreach ($features as $feature): ?>
            <article class="about-project__feature-card <?= isset($feature['status']) ? 'about-project__feature-card--soon' : '' ?>">
              <div class="about-project__card-icon">
                <i class="fas <?= htmlspecialchars($feature['icon']) ?>"></i>
              </div>
              <div>
                <div class="about-project__card-title-row">
                  <h3><?= htmlspecialchars($feature['title']) ?></h3>
                  <?php if (isset($feature['status'])): ?>
                    <span><?= htmlspecialchars($feature['status']) ?></span>
                  <?php endif; ?>
                </div>
                <p><?= htmlspecialchars($feature['text']) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="about-project__section">
      <div class="about-project__container">
        <div class="about-project__section-heading">
          <span>Dlaczego można nam zaufać</span>
          <h2>Bez wymyślonych liczb i pustych obietnic</h2>
        </div>

        <div class="about-project__trust-grid">
          <?php foreach ($trustItems as $item): ?>
            <article class="about-project__trust-item">
              <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
              <div>
                <h3><?= htmlspecialchars($item['title']) ?></h3>
                <p><?= htmlspecialchars($item['text']) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="about-project__section about-project__section--muted">
      <div class="about-project__container about-project__future">
        <div class="about-project__section-heading">
          <span>Wizja rozwoju</span>
          <h2>Co dalej?</h2>
        </div>

        <div class="about-project__future-card">
          <p>
            Zdajnik ma stopniowo rozwijać się o nowe kwalifikacje zawodowe, arkusze praktyczne, kursy przygotowawcze i
            dodatkowe materiały edukacyjne.
          </p>
          <ul>
            <li>nowe kwalifikacje zawodowe,</li>
            <li>arkusze praktyczne,</li>
            <li>kursy przygotowawcze,</li>
            <li>dodatkowe materiały edukacyjne.</li>
          </ul>
          <p>
            Celem jest stworzenie kompletnego miejsca do nauki egzaminów zawodowych.
          </p>
        </div>
      </div>
    </section>

    <section class="about-project__contact">
      <div class="about-project__container about-project__contact-card">
        <div>
          <span>Kontakt</span>
          <h2>Masz pomysł, uwagi lub chcesz nawiązać współpracę?</h2>
          <p>Napisz bezpośrednio na adres <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL) ?>"><?= htmlspecialchars(CONTACT_EMAIL) ?></a>.</p>
        </div>
        <a href="<?= url('kontakt') ?>" class="btn btn--secondary btn--large">Otwórz formularz</a>
      </div>
    </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>
</html>
