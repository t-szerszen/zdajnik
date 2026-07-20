<?php
/**
 * @file verify.php
 * @description
 * Widok potwierdzenia weryfikacji po kliknięciu w link z e-maila.
 *
 * @variables
 * - $status (string)
 * - $message (string)
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

/**
 * Konfiguracja komponentu head
 */
$noIndex = true;
$noFollow = true;
$pageTitle = 'Weryfikacja Konta | Zdajnik';
$pageDescription = 'Proces weryfikacji konta w serwisie Zdajnik i jego wynik.';
$canonicalUrl = 'https://www.zdajnik.pl/weryfikacja';

/**
 * ========================================================================
 * Plik Widoku: Wynik Weryfikacji E-mail
 * ========================================================================
 *
 * @description Renderuje finalną stronę statusu po tym, jak użytkownik
 * kliknie link weryfikacyjny w swojej wiadomości e-mail. Widok ten
 * dynamicznie wyświetla jeden z dwóch wariantów (sukces lub błąd)
 * w zależności od wyniku procesu weryfikacji obsłużonego
 * w kontrolerze.
 *
 * @dependencies
 * - partials/head.php (head)
 * - main.css (Główne style)
 *
 * @state_variables 
 * - $status (string): Wynik weryfikacji przekazany z kontrolera.
 *   Oczekiwana wartość to 'success' lub jakakolwiek inna oznaczająca błąd.
 * - $message (string): Konkretny komunikat do wyświetlenia
 *   użytkownikowi, np. "Twoje konto jest aktywne." lub "Token weryfikacyjny wygasł.".
 */
?>
<!DOCTYPE html>
<html lang="pl">
<!-- Dołączenie reużywalnego komponentu head -->
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <main>
    <!-- 
      =====================================================================
      Komponent: Karta Wyniku Weryfikacji (styl Form Card)
      =====================================================================
    -->
    <div class="form-card">

      <!-- WARUNEK: SUKCES -->
      <?php if ($status === 'success'): ?>

        <div class="form-card__header">
          <div class="form-card__icon-circle form-card__icon-circle--success">
            <i class="fas fa-check-circle"></i>
          </div>
          <h1 class="form-card__title">Weryfikacja zakończona!</h1>
          <p class="form-card__subtitle"><?= htmlspecialchars($message) ?></p>
        </div>

        <div class="form-card__action-container">
          <a href="<?= url('logowanie') ?>" class="btn btn--primary btn--full-width">
            Przejdź do logowania <i class="fas fa-arrow-right"></i>
          </a>
        </div>

        <!-- WARUNEK: BŁĄD -->
      <?php else: ?>

        <div class="form-card__header">
          <div class="form-card__icon-circle form-card__icon-circle--error">
            <i class="fas fa-times-circle"></i>
          </div>
          <h1 class="form-card__title">Wystąpił błąd</h1>
          <p class="form-card__subtitle"><?= htmlspecialchars($message) ?></p>
        </div>

        <div class="form-card__action-container">
          <a href="<?= url('/') ?>" class="back-link back-link--center">
            <i class="fas fa-arrow-left"></i> Wróć na stronę główną
          </a>
        </div>

      <?php endif; ?>

    </div>
  </main>
</body>

</html>