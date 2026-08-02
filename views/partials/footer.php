<?php
/**
 * @file footer.php
 * @description
 * Główny komponent stopki strony. Zawiera linki nawigacyjne, informacje kontaktowe,
 * przełącznik motywu (ThemeSwitcher) oraz globalny kontener na powiadomienia Toast.
 *
 * @variables
 * - Brak specyficznych zmiennych (korzysta z URL helperów).
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */
?>

<!-- 
  =======================================================================
  Komponent: Kontener na powiadomienia (Toast)
  -----------------------------------------------------------------------
  Przeznaczenie: Globalny kontener do dynamicznego renderowania 
                 powiadomień typu "toast" (np. o sukcesie, błędzie).
                 Umieszczony poza główną strukturą stopki, aby zapewnić 
                 poprawne pozycjonowanie i separację logiki.
  =======================================================================
-->
<div id="toast-container" class="toast-container"></div>

<!-- 
  =======================================================================
  Komponent: Główna stopka strony (Footer)
  -----------------------------------------------------------------------
  Opis: Spokojna stopka z marką, krótką informacją o projekcie,
        podstawowymi linkami i danymi kontaktowymi.
  =======================================================================
-->
<footer class="footer">
  <div class="footer__content">
    <div class="footer__brand">
      <div class="footer__logo-container">
        <h5 class="footer__logo">ZDAJNIK</h5>
      </div>
      <p class="footer__description">
        Przygotowanie do egzaminów zawodowych: pytania, testy, statystyki i materiały do nauki.
      </p>
      <div class="footer__badges" aria-label="Co znajdziesz w Zdajniku">
        <span>Pytania egzaminacyjne</span>
        <span>Egzaminy próbne</span>
        <span>Statystyki postępów</span>
      </div>
    </div>

    <div class="footer__groups">
      <nav class="footer__group" aria-label="Linki w stopce">
        <h6 class="footer__heading">Informacje</h6>
        <a href="<?= url('o-projekcie') ?>" class="footer__link">O projekcie</a>
        <a href="<?= url('kontakt') ?>" class="footer__link">Kontakt</a>
        <a href="<?= url('polityka-prywatnosci') ?>" class="footer__link">Polityka prywatności</a>
      </nav>

      <div class="footer__group footer__contact">
        <h6 class="footer__heading">Kontakt</h6>
        <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL) ?>" class="footer__contact-link">
          <i class="fas fa-envelope"></i>
          <?= htmlspecialchars(CONTACT_EMAIL) ?>
        </a>
        <span class="footer__contact-link">
          <i class="fas fa-location-dot"></i>
          Leszno, Polska
        </span>
      </div>
    </div>
  </div>

  <div class="footer__bottom">
    <div class="footer__bottom-container">
      <div>
        <div class="footer__copyright">
          &copy; 2026 Zdajnik. Wszystkie prawa zastrzeżone.
        </div>
        <p class="footer__note">Projekt tworzony z myślą o uczniach przygotowujących się do kwalifikacji zawodowych.</p>
      </div>

      <div class="footer__theme-action">
        <div class="theme-switcher">
          <input type="checkbox" id="theme-toggle-checkbox">
          <label for="theme-toggle-checkbox" class="theme-toggle-label" aria-label="Zmień motyw">
            <span class="cloud cloud--1"></span>
            <span class="cloud cloud--2"></span>
            <span class="star star--1"></span>
            <span class="star star--2"></span>
            <span class="star star--3"></span>
            <span class="ball"></span>
          </label>
        </div>
      </div>
    </div>
  </div>
</footer>
<!-- Koniec Komponentu Stopki -->

<?php
$jsVersion = filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/main.js');
$jsFiles = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($_SERVER['DOCUMENT_ROOT'] . '/js', FilesystemIterator::SKIP_DOTS)
);
foreach ($jsFiles as $jsFile) {
  if ($jsFile->isFile() && $jsFile->getExtension() === 'js') {
    $jsVersion = max($jsVersion, $jsFile->getMTime());
  }
}
?>
<!-- Ścieżka celowo względna (nie url()/BASE_URL): przeglądarka pobiera <script type="module">
     zawsze w trybie CORS, więc gdyby BASE_URL wskazywał na inny host niż ten, z którego
     faktycznie serwowana jest strona (np. lokalnie: BASE_URL=localhost, a wejście przez
     domenę OrbStacka .local), moduł zostałby zablokowany przez przeglądarkę i cały JS
     na stronie by nie działał, dopóki nawigacja (np. po zalogowaniu) nie sprowadziłaby
     z powrotem na domenę z BASE_URL. -->
<script type="module" src="/js/main.js?v=<?= $jsVersion ?>"></script>
