<?php
/**
 * @file login.php
 * @description
 * Widok logowania użytkownika.
 *
 * @variables
 * - $noIndex (bool)
 * - $pageTitle (string)
 * - $canonicalUrl (string)
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

/**
 * Konfiguracja komponentu head
 */
$noIndex = true;
$noFollow = true;
$pageTitle = 'Logowanie do Konta | Zdajnik';
$pageDescription = 'Zaloguj się na swoje konto w Zdajnik, aby uzyskać dostęp do zapisanych wyników, statystyk i kontynuować naukę do egzaminu zawodowego.';
$canonicalUrl = url('logowanie');

/**
 * ========================================================================
 * Plik Widoku: Logowanie Użytkownika
 * ========================================================================
 *
 * @description Renderuje formularz logowania. Ten widok jest odpowiedzialny
 * za wyświetlanie pól do wprowadzenia danych, obsługę i prezentację
 * błędów walidacji oraz wyświetlanie jednorazowych komunikatów
 * "flash" (np. po pomyślnej rejestracji).
 *
 * @dependencies 
 * - partials/head.php (head)
 * - partials/navbar.php (Nawigacja)          
 * - partials/footer.php (Stopka)            
 * - main.css (Główne style)     
 * - Font Awesome (Ikony)
 *
 * @state_variables 
 * - $_SESSION['flash_message'] (array|null): Jednorazowy komunikat
 *   przechowywany w sesji. Zawiera 'type' (np. 'success')
 *   i 'text'. Jest usuwany po wyświetleniu.         
 * - $errors (array): Tablica z błędami walidacji formularza,
 *   które są wyświetlane bezpośrednio nad polami.            
 * - $formData (array): Tablica z danymi przesłanymi przez
 *   użytkownika, używana do ponownego wypełnienia pól w przypadku błędu.
 */
?>
<!DOCTYPE html>
<html lang="pl">
<!-- Dołączenie reużywalnego komponentu head -->
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <!-- Dołączenie reużywalnego komponentu head -->
  <?php include 'partials/navbar.php'; ?>

  <main>
    <!-- 
      =====================================================================
      Komponent: Komunikat Flash (Flash Message)
      ---------------------------------------------------------------------
      Cel: Wyświetlanie jednorazowych powiadomień dla użytkownika, np.
           "Rejestracja przebiegła pomyślnie. Możesz się teraz zalogować."
           Komunikat jest odczytywany z sesji i natychmiast usuwany,
           aby nie pojawił się ponownie po odświeżeniu strony.
      =====================================================================
    -->
    <?php if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])): ?>
      <div class="centered">
        <div class="alert alert--<?= htmlspecialchars($_SESSION['flash_message']['type']) ?>" role="alert">
          <i class="fas fa-check-circle"></i>
          <?= htmlspecialchars($_SESSION['flash_message']['text']) ?>
        </div>
      </div>
      <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <!-- 
      =====================================================================
      Komponent: Formularz Logowania
      ---------------------------------------------------------------------
      Opis: Główny formularz umożliwiający użytkownikowi zalogowanie się
            do serwisu. Dane są wysyłane metodą POST.
      =====================================================================
    -->
    <form method="POST" class="form-card">
      <div class="form-card__header">
        <div class="form-card__icon-circle">
          <i class="fas fa-user-lock"></i>
        </div>
        <h1 class="form-card__title">Witaj ponownie!</h1>
        <p class="form-card__subtitle">Zaloguj się, aby kontynuować naukę.</p>
      </div>

      <!-- 
        Blok: Wyświetlanie błędów walidacji
        Cel: Jeśli kontroler zwróci błędy (np. pusty e-mail, złe hasło),
             są one tutaj iterowane i wyświetlane w widocznym miejscu.
      -->
      <?php if (!empty($errors)): ?>
        <div class="alert alert--error" role="alert">
          <ul class="alert__list">
            <?php foreach ($errors as $error): ?>
              <li class="alert__item"><i class="fas fa-exclamation-circle"></i><?= $error ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Grupa: Pole e-mail -->
      <div class="form-card__group">
        <label for="email" class="form-card__label">Adres e-mail</label>
        <div class="form-card__input-wrapper">
          <i class="fas fa-envelope form-card__input-icon"></i>
          <input type="email" id="email" name="email" class="form-card__input form-card__input--with-icon"
            value="<?= htmlspecialchars($formData['email'] ?? '') ?>" placeholder="Twój adres e-mail" required>
        </div>
      </div>

      <!-- Grupa: Pole hasła -->
      <div class="form-card__group">
        <label for="password" class="form-card__label">Hasło</label>
        <div class="password-wrapper">
          <i class="fas fa-lock form-card__input-icon"></i>
          <input type="password" id="password" name="password" class="form-card__input form-card__input--with-icon"
            placeholder="Twoje hasło" required>
          <i class="fas fa-eye password-toggle-icon"></i>
        </div>
      </div>

      <!-- Kontener z głównym przyciskiem akcji -->
      <div class="form-card__action-container">
        <button type="submit" class="btn btn--primary btn--full-width">
          Zaloguj się <i class="fas fa-arrow-right"></i>
        </button>
      </div>

      <div class="form-card__divider">lub</div>

      <div class="form-card__action-container form-card__action-container--google">
        <a href="<?= url('auth/google') ?>" class="form-card__google-btn">
          <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google">
          Zaloguj się przez Google
        </a>
      </div>

      <!-- Stopka formularza z dodatkowymi linkami -->
      <div class="form-card__footer">
        <p>Nie masz konta? <a href="<?= url('rejestracja') ?>">Stwórz je teraz</a></p>
        <p><a href="<?= url('reset-hasla') ?>" class="form-card__forgot-password">Nie pamiętasz hasła?</a></p>
      </div>
    </form>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
