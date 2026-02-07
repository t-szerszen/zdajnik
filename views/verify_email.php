<?php

/**
 * Konfiguracja komponentu head
 */
$noIndex = true;
$noFollow = true;
$pageTitle = 'Autoryzacja adresu e-mail | Examly';
$pageDescription = 'Proces weryfikacji konta w serwisie Examly.';
$canonicalUrl = 'https://www.examly.pl/autoryzacja-email';

/**
 * ========================================================================
 * Plik Widoku: Weryfikacja Adresu E-mail
 * ========================================================================
 *
 * @description Wyświetla stronę informacyjną dla użytkownika po rejestracji,
 * instruując go, aby sprawdził swoją skrzynkę e-mail w celu
 * aktywacji konta. Kluczową funkcją tego widoku jest przycisk
 * "Wyślij ponownie", który posiada mechanizm cooldown
 * (odliczania), zarządzany wspólnie przez PHP i JavaScript.
 *
 * @dependencies 
 * - partials/head.php (head)
 * - partials/navbar.php (Nawigacja)
 * - partials/footer.php (Stopka)
 * - main.css (Główne style)
 * - Font Awesome (Ikony)
 * - verification/index.js (Skrypt JS do obsługi odliczania)
 *
 * @state_variables 
 * - $flashMessage (array|null): Jednorazowy komunikat z sesji,
 *   np. o pomyślnym ponownym wysłaniu e-maila.
 * - $remaining (int): Liczba sekund pozostała do możliwości
 *   ponownego wysłania e-maila. Przekazywana z kontrolera.
 */
?>
<!DOCTYPE html>
<html lang="pl">
<!-- Dołączenie reużywalnego komponentu head -->
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <!-- Dołączenie reużywalnego komponentu nawigacji -->
  <?php include 'partials/navbar.php'; ?>

  <main>
    <!-- 
      =====================================================================
      Komponent: Karta Weryfikacji (styl Form Card)
      =====================================================================
    -->
    <div class="form-card">
      <div class="form-card__header">
        <div class="form-card__icon-circle">
          <i class="fas fa-envelope-open-text"></i>
        </div>
        <h1 class="form-card__title">Sprawdź skrzynkę</h1>
        <p class="form-card__subtitle">
          Aby zakończyć rejestrację, kliknij w link aktywacyjny, który wysłaliśmy na Twój adres e-mail.
        </p>
      </div>

      <!-- Blok na jednorazowe komunikaty (np. "E-mail wysłano ponownie.") -->
      <?php if (isset($flashMessage) && is_array($flashMessage)): ?>
        <div class="alert alert--<?= htmlspecialchars($flashMessage['type']) ?>" role="alert" style="margin-bottom: 2rem;">
          <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>
          <?= htmlspecialchars($flashMessage['text']) ?>
        </div>
      <?php endif; ?>

      <!-- Akcje karty: Formularz do ponownego wysłania e-maila -->
      <div class="form-card__action-container" style="margin-top: 0;">
        <form method="GET">
          <input type="hidden" name="send" value="true">
          <!-- 
            Przycisk z logiką cooldown:
            Zarządzany przez verification/index.js
          -->
          <button
            type="submit"
            id="resendButton"
            class="btn btn--primary btn--full-width"
            data-text="Wyślij ponownie e-mail"
            data-remaining="<?= $remaining ?>"
            <?= $remaining > 0 ? 'disabled' : '' ?>>
            <?php if ($remaining > 0): ?>
              Wyślij ponownie za <span id="countdown"><?= $remaining ?></span>s
            <?php else: ?>
              Wyślij ponownie e-mail
            <?php endif; ?>
          </button>
        </form>
      </div>

      <div class="form-card__footer">
        <p class="form-card__subtitle" style="font-size: 0.9rem; opacity: 0.8;">
          Nie widzisz wiadomości? Sprawdź folder SPAM.
        </p>
      </div>
    </div>
  </main>

  <?php include 'partials/footer.php'; ?>

  <!-- Skrypt odpowiedzialny za logikę odliczania na przycisku "Wyślij ponownie" -->
  <script type="module">
    import CountdownButton from '<?= url('js/components/CountdownButton.js') ?>';
    new CountdownButton('resendButton');
  </script>
</body>

</html>