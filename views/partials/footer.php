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
  <div class="footer__content">

    <!-- Sekcja 1: Brand & Description -->
    <div class="footer__section footer__section--brand">
      <h4 class="footer__heading">Examly</h4>
      <p class="footer__description">
        Twoja inteligentna platforma do nauki na egzaminy zawodowe INF.02 i INF.03.
        Tysiące pytań, szczegółowe statystyki i tryby nauki dopasowane do Ciebie.
      </p>
      <div class="footer__socials">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
        <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
      </div>
    </div>

    <!-- Sekcja 2: Nawigacja -->
    <div class="footer__section">
      <h4 class="footer__heading">Platforma</h4>
      <ul class="footer__list">
        <li class="footer__item"><a href="<?= url('/') ?>" class="footer__link">Strona główna</a></li>
        <li class="footer__item"><a href="<?= url('rejestracja') ?>" class="footer__link">Rejestracja</a></li>
        <li class="footer__item"><a href="<?= url('logowanie') ?>" class="footer__link">Logowanie</a></li>
        <li class="footer__item"><a href="<?= url('statystyki') ?>" class="footer__link">Statystyki</a></li>
      </ul>
    </div>

    <!-- Sekcja 3: Materiały -->
    <div class="footer__section">
      <h4 class="footer__heading">Nauka</h4>
      <ul class="footer__list">
        <li class="footer__item"><a href="<?= url('') ?>" class="footer__link">Kurs INF.03</a></li>
        <li class="footer__item"><a href="<?= url('jedno-pytanie/INF.03') ?>" class="footer__link">Pytania Wyzwania</a>
        </li>
        <li class="footer__item"><a href="<?= url('#egzaminy') ?>" class="footer__link">Symulacja Egzaminu</a></li>
        <li class="footer__item"><a href="<?= url('historia') ?>" class="footer__link">Twoja Historia</a></li>
      </ul>
    </div>

    <!-- Sekcja 4: Kontakt -->
    <div class="footer__section">
      <h4 class="footer__heading">Kontakt</h4>
      <ul class="footer__list">
        <li class="footer__item">
          <i class="footer__icon fas fa-envelope"></i>
          <a href="mailto:kontakt@examly.pl" class="footer__link">kontakt@examly.pl</a>
        </li>
        <li class="footer__item">
          <i class="footer__icon fas fa-phone"></i>
          <a href="tel:+48784803409" class="footer__link">+48 784 803 409</a>
        </li>
        <li class="footer__item">
          <i class="footer__icon fas fa-map-marker-alt"></i>
          <span>Osieczna, Polska</span>
        </li>
      </ul>
    </div>
  </div>

  <div class="footer__bottom">
    <p class="footer__copyright">&copy; <?= date('Y') ?> Examly. Stworzone dla przyszłych informatyków.</p>

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
</footer>
<!-- Koniec Komponentu Stopki -->

<!-- Skrypt do zmiany motywu strony -->
<script type="module" src="<?= url('js/components/ThemeSwitcher.js') ?>"></script>