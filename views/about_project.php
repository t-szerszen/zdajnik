<?php
/**
 * @file about_project.php
 * @description Strona informacyjna o historii, twórcy i rozwoju projektu Zdajnik.
 */

$pageTitle = 'O projekcie | Zdajnik';
$pageDescription = 'Poznaj historię powstania Zdajnika, projekt tworzonego z myślą o uczniach przygotowujących się do egzaminów zawodowych.';

$collaborationItems = [
  [
    'icon' => 'fa-bug',
    'title' => 'Zgłoś błąd w pytaniu',
    'text' => 'Trafiłeś na nieaktualną odpowiedź, literówkę albo złą treść pytania? Daj znać - poprawki wchodzą od ręki.'
  ],
  [
    'icon' => 'fa-lightbulb',
    'title' => 'Zaproponuj funkcję',
    'text' => 'Brakuje Ci czegoś w nauce albo masz pomysł na usprawnienie? Twoja sugestia może trafić do najbliższej aktualizacji.'
  ],
  [
    'icon' => 'fa-code',
    'title' => 'Pomóż programistycznie',
    'text' => 'Znasz PHP, JavaScript albo SCSS? Zdajnik cały czas rośnie i przyda się dodatkowa para rąk do kodu.'
  ],
  [
    'icon' => 'fa-book-open',
    'title' => 'Podziel się materiałami',
    'text' => 'Masz notatki, pytania z kartkówek albo pomysł na treści do kolejnej kwalifikacji (INF.02)? Chętnie je wykorzystam.'
  ],
];

$trustItems = [
  [
    'icon' => 'fa-user-graduate',
    'title' => 'Zbudowane ze złości na kiepskie bazy pytań',
    'text' => 'Zdajnik nie powstał w agencji marketingowej, tylko z frustracji ucznia, który sam uczył się do egzaminu.'
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

<body class="home-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="about-project">
    <section class="about-project__hero">
      <div class="about-project__container about-project__hero-grid">
        <div class="about-project__hero-copy">
          <p class="about-project__eyebrow">O projekcie</p>
          <h1>To samo pytanie <span class="text-gradient">dziesiąty raz</span>? Miało tak nie być.</h1>
          <p>
            Zdajnik powstał z frustracji ucznia technikum, który szukał dobrego miejsca do nauki teorii przed
            egzaminem zawodowym - i nie znalazł. Zamiast kolejnej bazy pytań rzucającej losowo, zbudowałem narzędzie,
            które samo pilnuje Twojej nauki, żebyś w dniu egzaminu czuł/a pewność siebie, a nie stres i zgadywanie.
          </p>
          <a href="<?= url('/') ?>" class="btn btn--primary btn--large">Przejdź do platformy</a>
        </div>

        <aside class="about-project__note" aria-label="Krótko o Zdajniku">
          <i class="fas fa-seedling"></i>
          <h2>Nie kolejna baza pytań</h2>
          <p>
            Suchy zbiór pytań to za mało. Zdajnik wie, co już umiesz, a co wymaga powtórki - żebyś nie tracił/a
            czasu i motywacji na pytania, które opanowałeś/aś dawno temu.
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
            Historia Zdajnika zaczyna się bardzo zwyczajnie - od zwykłej frustracji. Jako uczeń technikum
            informatycznego szukałem sposobu, żeby solidnie przygotować się do części pisemnej egzaminu zawodowego.
            Trafiałem na bazy pytań, ale każda miała ten sam problem: rzucała pytaniami losowo, bez żadnego pomysłu
            na to, jak się naprawdę uczyć.
          </p>
          <p>
            Najgorsze było to, że w jednej sesji nauki potrafiłem zobaczyć to samo pytanie nawet dziesięć razy -
            mimo że dawno je opanowałem. Zamiast realnej nauki dostawałem czystą loterię. A z każdym powtórzonym,
            znanym już pytaniem traciłem chęci do nauki, zamiast je zyskiwać.
          </p>

          <div class="about-project__missing-box">
            <h3>Brakowało miejsca, które:</h3>
            <ul>
              <li>nie pokazuje w kółko tych samych, dawno opanowanych pytań,</li>
              <li>wie, co już umiesz, a co wymaga powtórki,</li>
              <li>tłumaczy, dlaczego dana odpowiedź jest poprawna,</li>
              <li>pokazuje realny postęp zamiast przypadkowych cyfr.</li>
            </ul>
          </div>

          <p>Z tego powodu powstał Zdajnik - żebyś w dniu egzaminu, tym ważnym i stresującym dniu, miał/a za sobą
            naukę zarządzaną z sensem, a nie zgadywanie na loterii pytań.</p>
        </div>
      </div>
    </section>

    <section class="about-project__section about-project__section--muted">
      <div class="about-project__container">
        <div class="about-project__section-heading about-project__section-heading--center">
          <span>Co oferuje Zdajnik</span>
          <h2>Narzędzia do codziennej nauki</h2>
        </div>

        <?php
        $features = include 'partials/data/hero_features.php';
        $featureGridTag = 'div';
        $featureGridAriaLabel = 'Funkcje Zdajnika';
        include 'partials/feature_grid.php';
        ?>
      </div>
    </section>

    <section class="about-project__section">
      <div class="about-project__container">
        <div class="about-project__section-heading">
          <span>Dlaczego można mi zaufać</span>
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
            Cel od początku jest ten sam: żebyś w dniu egzaminu wiedział/a, że jesteś gotowy/a - niezależnie od
            tego, jaką kwalifikację zdajesz.
          </p>
        </div>
      </div>
    </section>

    <section class="about-project__section">
      <div class="about-project__container">
        <div class="about-project__section-heading">
          <span>Współpraca</span>
          <h2>Jak możesz pomóc?</h2>
        </div>

        <div class="about-project__trust-grid">
          <?php foreach ($collaborationItems as $item): ?>
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

    <section class="about-project__contact">
      <div class="about-project__container about-project__contact-card">
        <div>
          <span>Kontakt</span>
          <h2>Masz pomysł, uwagi lub chcesz nawiązać współpracę?</h2>
          <p>
            Niezależnie od tego, czy chcesz zgłosić błąd, zaproponować funkcję, czy dołączyć do rozwoju Zdajnika -
            napisz bezpośrednio na adres <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL) ?>"><?= htmlspecialchars(CONTACT_EMAIL) ?></a>
            albo skorzystaj z formularza. Odpisuję osobiście na każdą wiadomość.
          </p>
        </div>
        <a href="<?= url('kontakt') ?>" class="btn btn--secondary btn--large">Otwórz formularz</a>
      </div>
    </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>
</html>
