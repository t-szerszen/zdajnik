<?php
/**
 * @file verify.php
 * @description
 * Widok potwierdzenia weryfikacji po kliknięciu w link z e-maila.
 *
 * @variables
 * - $message (string)
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 */

/**
 * Konfiguracja komponentu head
 */
$noIndex = true;
$noFollow = true;
$pageTitle = 'Weryfikacja Konta | Zdajnik';
$pageDescription = 'Proces weryfikacji konta w serwisie Zdajnik i jego wynik.';
$canonicalUrl = url('weryfikacja');

/**
 * ========================================================================
 * Plik Widoku: Wynik Weryfikacji E-mail
 * ========================================================================
 *
 * @description Renderuje stronę błędu po tym, jak użytkownik kliknie link
 * weryfikacyjny w swojej wiadomości e-mail.
 *
 * Widok obsługuje wyłącznie ścieżkę niepowodzenia (brak tokenu, token zły lub
 * wygasły, błąd serwera) - jest renderowany tylko przez renderErrorView()
 * w VerificationController. Udana weryfikacja od razu loguje użytkownika
 * i przekierowuje go na stronę główną z komunikatem flash, więc nigdy tu
 * nie trafia.
 *
 * @dependencies
 * - partials/head.php (head)
 * - main.css (Główne style)
 *
 * @state_variables
 * - $message (string): Konkretny komunikat o błędzie do wyświetlenia
 *   użytkownikowi, np. "Token weryfikacyjny wygasł.".
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

    </div>
  </main>
</body>

</html>