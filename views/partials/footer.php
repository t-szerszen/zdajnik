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
  Opis: Centralny element stopki, zawierający kluczowe linki, dane 
        kontaktowe i informacje o prawach autorskich. Struktura oparta 
        na metodologii BEM (np. .footer__section) dla zachowania 
        przejrzystości i unikania konfliktów w stylach.
  =======================================================================
-->
<footer class="footer">
  <!-- Dynamiczny znak wodny w tle -->
  <div class="footer__watermark" aria-hidden="true">EXAMLY</div>

  <div class="footer__content">
    <!-- Sekcja 1: Brand & Socials -->
    <div class="footer__section footer__section--brand">
      <div class="footer__logo-container">
        <h5 class="footer__logo">EXAMLY</h5>
      </div>
      <p class="footer__description">
        Kształtujemy przyszłość edukacji zawodowej. Nowoczesna platforma, która zmienia sposób, w jaki przygotowujesz
        się do egzaminów. Bezpieczeństwo, Szybkość, Zdany Egzamin.
      </p>
    </div>

    <!-- Sekcja 2: Platforma -->
    <div class="footer__section">
      <h5 class="footer__heading">Platforma</h5>
      <ul class="footer__list">
        <li class="footer__item"><a href="<?= url('/') ?>" class="footer__link">Strona główna</a></li>
        <li class="footer__item"><a href="<?= url('rejestracja') ?>" class="footer__link">Rejestracja</a></li>
        <li class="footer__item"><a href="<?= url('ustawienia') ?>" class="footer__link">Zarządzaj kontem</a></li>
        <li class="footer__item"><a href="<?= url('search') ?>" class="footer__link">Baza pytań</a></li>
      </ul>
    </div>

    <!-- Sekcja 3: Nauka -->
    <div class="footer__section">
      <h5 class="footer__heading">Edukacja</h5>
      <ul class="footer__list">
        <li class="footer__item"><a href="<?= url('jedno-pytanie/INF.04') ?>" class="footer__link">Jedno pytanie</a>
        </li>
        <li class="footer__item"><a href="<?= url('personalizowany-test/INF.04') ?>"
            class="footer__link">Personalizowany test</a></li>
        <li class="footer__item"><a href="<?= url('test/INF.04') ?>" class="footer__link">Test</a></li>
        <li class="footer__item"><a href="<?= url('statystyki') ?>" class="footer__link">Statystyki</a></li>
      </ul>
    </div>

    <!-- Sekcja 4: Kontakt -->
    <div class="footer__section">
      <h5 class="footer__heading">Kontakt</h5>
      <div class="footer__contact-info">
        <div class="footer__contact-item">
          <div class="footer__contact-icon">
            <i class="fas fa-envelope"></i>
          </div>
          <div class="footer__contact-text">
            <span class="footer__contact-label">Napisz do nas</span>
            <a href="mailto:examlyacademy@gmail.com" class="footer__contact-link">examlyacademy@gmail.com</a>
          </div>
        </div>

        <div class="footer__contact-item">
          <div class="footer__contact-icon">
            <i class="fas fa-map-marker-alt"></i>
          </div>
          <div class="footer__contact-text">
            <span class="footer__contact-label">Nasza Lokalizacja</span>
            <span class="footer__contact-link">Leszno, Polska</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="footer__bottom">
    <div class="footer__bottom-container">
      <div class="footer__copyright">
        &copy; <?= date('Y') ?> Examly Studio. Wszelkie prawa zastrzeżone.
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

<!-- Skrypt do zmiany motywu strony -->
<script type="module" src="<?= url('js/components/ThemeSwitcher.js') ?>"></script>