<?php
/**
 * @file reset_email.php
 * @description
 * Widok weryfikacji zmiany adresu e-mail.
 * Ten widok pozwala użytkownikowi na sfinalizowanie procesu zmiany adresu e-mail
 * poprzez wprowadzenie 6-cyfrowego kodu bezpieczeństwa wysłanego na nowy adres.
 * Zabezpiecza przed literówkami w nowych adresach oraz zapewnia autoryzację.
 *
 * @variables
 * - $token (string): Zabezpieczony token autoryzacyjny przekazany w adresie URL.
 * - $_SESSION['flash_message'] (array|null): Komunikaty błędów (np. błędny kod) lub sukcesu.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

$pageTitle = 'Weryfikacja Nowego Adresu E-mail | Examly';
$noIndex = true;
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>
  <main>
    <form method="POST" class="form-card">
      <div class="form-card__header">
        <h1 class="form-card__title">Potwierdź zmianę e-maila</h1>
        <p class="form-card__subtitle">Wpisz kod bezpieczeństwa, który wysłaliśmy na Twój nowy adres.</p>
      </div>

      <?php if (isset($_SESSION['flash_message'])):
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        ?>
        <div class="alert alert--<?= htmlspecialchars($flash['type']) ?>">
          <?php if (isset($flash['errors'])): ?>
            <ul class="alert__list">
              <?php foreach ($flash['errors'] as $error): ?>
                <li>
                  <?= htmlspecialchars($error) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <?= $flash['text'] // Allow HTML for formatting ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <div class="form-card__group">
        <label for="auth_code" class="form-card__label">Kod bezpieczeństwa</label>
        <input type="text" id="auth_code" name="auth_code" class="form-card__input" placeholder="Wpisz 6-cyfrowy kod"
          required autofocus>
      </div>

      <div class="form-card__action-container">
        <button type="submit" class="btn btn--primary">Zatwierdź zmianę e-maila</button>
      </div>
    </form>
  </main>
  <?php include 'partials/footer.php'; ?>
</body>

</html>