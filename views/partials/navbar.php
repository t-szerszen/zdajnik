<?php
/**
 * @file navbar.php
 * @description
 * Komponent górnego paska nawigacyjnego (Header). Zarządza menu głównym, 
 * wyszukiwarką pytań oraz dynamicznym stanem sesji użytkownika 
 * (przełączanie widoku logowania/rejestracji na menu profilu).
 *
 * @variables
 * - $currentUser (User|null): Obiekt aktualnie zalogowanego użytkownika.
 * - $isUserLoggedIn (bool): Flaga stanu autoryzacji.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 */

use App\Models\User;

?>

<!-- 
  =======================================================================
  Komponent: Główny pasek nawigacyjny (main-nav)
  -----------------------------------------------------------------------
  Opis: Centralny element nawigacyjny strony, widoczny na górze każdej 
        podstrony. Zawiera logo, główne linki nawigacyjne (w tym menu
        rozwijane) oraz dynamicznie renderowane przyciski akcji 
        (logowanie/rejestracja lub profil użytkownika) w zależności 
        od stanu zalogowania.
  Struktura: Używa metodologii BEM (np. .main-nav__item, .dropdown__menu).
  =======================================================================
-->
<nav class="main-nav">
  <!-- Sekcja 1: Logo i link do strony głównej -->
  <div class="main-nav__logo">
    <a href="<?= url() ?>" aria-label="Strona główna Examly">
      <img src="<?= url('images/company/examly.svg') ?>" alt="Examly" class="logo">
    </a>
  </div>

  <!-- Dynamic Test Info (Visible on scroll during tests) -->
  <div class="main-nav__test-info" id="nav-test-info" aria-live="polite">
    <div class="nav-test-meta">
      <span id="nav-test-progress-text">0/0</span>
      <div class="nav-test-progress-bar-wrap">
        <div id="nav-test-progress-bar" class="nav-test-progress-bar"></div>
      </div>
    </div>
    <div id="nav-timer" class="nav-test-timer">00:00</div>
    <button id="nav-pause-btn" class="nav-test-pause-btn" title="Zapauzuj egzamin" aria-label="Zapauzuj egzamin">
      <i class="fas fa-pause"></i>
    </button>
  </div>

  <!-- Przycisk Hamburgera (Widoczny tylko na mobile) -->
  <button class="main-nav__hamburger" id="nav-toggle" aria-label="Menu" data-toggle-nav>
    <span></span>
    <span></span>
    <span></span>
  </button>

  <!-- Sekcja 2: Główna lista linków nawigacyjnych (Drawer na mobile) -->
  <div class="main-nav__drawer" data-nav-drawer>
    <!-- Drawer Header (Tylko na mobile) -->
    <div class="main-nav__drawer-header">
      <div class="main-nav__logo">
        <a href="<?= url() ?>">
          <img src="<?= url('images/company/examly.svg') ?>" alt="Examly" class="logo">
        </a>
      </div>
    </div>

    <ul class="main-nav__links">

      <!-- Element nawigacji z menu rozwijanym (dropdown) -->
      <li class="main-nav__item dropdown">
        <span class="main-nav__link main-nav__link--disabled" aria-disabled="true">
          <i class="main-nav__icon fas fa-microchip"></i>
          <div class="main-nav__link-content">
            <span>INF.02</span>
            <span class="badge badge--soon">Wkrótce</span>
          </div>
        </span>
      </li>

      <li class="main-nav__item dropdown">
        <span class="main-nav__link main-nav__link--disabled" aria-disabled="true">
          <i class="main-nav__icon fas fa-laptop-code"></i>
          <div class="main-nav__link-content">
            <span>INF.03</span>
            <span class="badge badge--soon">Wkrótce</span>
          </div>
        </span>
      </li>

      <li class="main-nav__item dropdown">
        <a href="<?= url('#inf04') ?>" class="main-nav__link">
          <i class="main-nav__icon fas fa-code"></i>
          <span>INF.04</span>
          <i class="dropdown__arrow fas fa-chevron-down"></i>
        </a>
        <ul class="dropdown__menu">
          <li class="dropdown__item">
            <a href="<?= url('jedno-pytanie/INF.04') ?>" class="dropdown__link">
              <i class="dropdown__icon fas fa-question-circle"></i> Jedno Pytanie
            </a>
          </li>
          <li class="dropdown__item">
            <a href="<?= url('personalizowany-test/INF.04') ?>" class="dropdown__link">
              <i class="dropdown__icon fas fa-sliders-h"></i> Spersonalizowany Test
            </a>
          </li>
          <li class="dropdown__item">
            <a href="<?= url('test/INF.04') ?>" class="dropdown__link">
              <i class="dropdown__icon fas fa-file-alt"></i> Egzamin próbny
            </a>
          </li>
        </ul>
      </li>

      <li class="main-nav__item dropdown">
        <span class="main-nav__link main-nav__link--disabled" aria-disabled="true">
          <i class="main-nav__icon fas fa-graduation-cap"></i>
          <div class="main-nav__link-content">
            <span>Kursy</span>
            <span class="badge badge--soon">Wkrótce</span>
          </div>
        </span>
      </li>

      <?php if (!$isUserLoggedIn): ?>
        <li class="main-nav__item main-nav__item--auth">
          <div class="nav-auth">
            <a href="<?= url('rejestracja') ?>" class="nav-button nav-button--register">
              <i class="fas fa-user-plus"></i>
              <span>Zarejestruj się</span>
            </a>
            <a href="<?= url('logowanie') ?>" class="nav-button nav-button--login">
              <i class="fas fa-sign-in-alt"></i>
              <span>Zaloguj się</span>
            </a>
          </div>
        </li>
        <?php
      else: ?>
        <li class="main-nav__item dropdown main-nav__item--user">
          <a href="<?= url('statystyki') ?>" class="main-nav__link main-nav__link--user" aria-label="Profil użytkownika">
            <div class="main-nav__user-info-mobile">
              <?php
              $navAvatarSrc = $currentUser->getAvatarPath() ? url('images/avatars/' . $currentUser->getAvatarPath()) : url('images/user.png');
              ?>
              <img src="<?= htmlspecialchars($navAvatarSrc) ?>" alt="Avatar" class="main-nav__user-avatar navbar__avatar">
              <div class="main-nav__user-text">
                <span class="main-nav__user-name">Mój Profil</span>
                <span class="main-nav__user-status">Zalogowano</span>
              </div>
            </div>
            <i class="dropdown__arrow fas fa-chevron-down"></i>
          </a>
          <ul class="dropdown__menu dropdown__menu--right">
            <li class="dropdown__item">
              <a href="<?= url('statystyki') ?>" class="dropdown__link">
                <i class="dropdown__icon fas fa-chart-bar"></i> Statystyki
              </a>
            </li>
            <li class="dropdown__item">
              <a href="<?= url('ustawienia') ?>" class="dropdown__link">
                <i class="dropdown__icon fas fa-cog"></i> Ustawienia
              </a>
            </li>
            <li class="dropdown__item dropdown__item--logout">
              <a href="<?= url('wyloguj') ?>" class="dropdown__link">
                <i class="dropdown__icon fas fa-sign-out-alt"></i> Wyloguj się
              </a>
            </li>
          </ul>
        </li>
        <?php
      endif; ?>
    </ul>

    <!-- Drawer Footer (Tylko na mobile) -->
    <div class="main-nav__drawer-footer">
      <p>&copy;
        <?= date('Y') ?> Examly.pl
      </p>
    </div>
  </div>
</nav>
<!-- Overlay dla nawigacji mobilnej -->
<div class="nav-overlay" data-nav-overlay></div>
<!-- /Koniec Komponentu Nawigacji -->

<script type="module" src="<?= url('js/components/NavbarHandler.js') ?>"></script>

<!-- 
  =======================================================================
  Skrypt: Inicjalizacja stanu aplikacji po stronie klienta
  -----------------------------------------------------------------------
  Cel: Przekazuje kluczowe dane z PHP (backend) do JavaScript (frontend).
       Utworzenie globalnego obiektu `window.examlyAppState` pozwala 
       na łatwy i spójny dostęp do stanu zalogowania użytkownika 
       w różnych skryptach JS bez potrzeby dodatkowych zapytań AJAX.
  =======================================================================
-->
<script>
  window.examlyAppState = {
    isUserLoggedIn: <?= isset($_SESSION['user']) && $_SESSION['user'] instanceof User ? 'true' : 'false' ?>,
    baseUrl: '<?= BASE_URL ?>'
  };
</script>
