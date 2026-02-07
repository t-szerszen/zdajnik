<?php

/**
 * Konfiguracja komponentu head
 */
$noIndex = true;
$noFollow = true;
$pageTitle = 'Weryfikacja Konta | Examly';
$pageDescription = 'Proces weryfikacji konta w serwisie Examly i jego wynik.';
$canonicalUrl = 'https://www.examly.pl/weryfikacja';

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
          <div class="form-card__icon-circle">
            <i class="fas fa-check-circle" style="color: var(--success-color, #16a34a);"></i>
          </div>
          <h1 class="form-card__title">Weryfikacja zakończona!</h1>
          <p class="form-card__subtitle"><?= htmlspecialchars($message) ?></p>
        </div>

        <div class="form-card__action-container">
          <a href="<?= url('logowanie') ?>" class="btn btn--primary btn--full-width">
            Przejdź do logowania <i class="fas fa-arrow-right" style="margin-left: 0.5rem;"></i>
          </a>
        </div>

      <!-- WARUNEK: BŁĄD -->
      <?php else: ?>

        <div class="form-card__header">
          <div class="form-card__icon-circle">
            <i class="fas fa-times-circle" style="color: var(--error-color, #dc2626);"></i>
          </div>
          <h1 class="form-card__title">Wystąpił błąd</h1>
          <p class="form-card__subtitle"><?= htmlspecialchars($message) ?></p>
        </div>

        <div class="form-card__action-container">
          <a href="<?= url('/') ?>" class="btn btn--secondary btn--full-width">
            Wróć na stronę główną
          </a>
        </div>

      <?php endif; ?>

    </div>
  </main>
</body>

</html>