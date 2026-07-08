<?php
/**
 * @file index.php
 * @description
 * Widok strony błędu HTTP (np. 404, 500).
 *
 * @variables
 * - $errorCode (int)
 * - $errorMessage (string)
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

// Wartości domyślne, jeśli nie przekazano ich z kontrolera
$pageTitle = $pageTitle ?? 'Błąd | Zdajnik';
$errorCode = $errorCode ?? 404;
$errorTitle = $errorTitle ?? 'Strona nie znaleziona';
$errorMessage = $errorMessage ?? 'Przepraszamy, ale strona której szukasz nie istnieje.';
$errorIcon = $errorIcon ?? 'fas fa-exclamation-circle';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>

<body class="home-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="error-page">
    <div class="error-page__content">
      <h1 class="gradient-text error-page__code">
        <?= htmlspecialchars($errorCode) ?>
      </h1>
      <div class="error-page__icon">
        <i class="<?= htmlspecialchars($errorIcon) ?>"></i>
      </div>
      <h2 class="error-page__title">
        <?= htmlspecialchars($errorTitle) ?>
      </h2>
      <p class="error-page__message">
        <?= htmlspecialchars($errorMessage) ?>
      </p>
      <div class="error-page__actions">
        <a href="<?= url('/') ?>" class="btn btn--secondary">
          <i class="fas fa-home"></i> Strona główna
        </a>
        <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL) ?>" class="btn btn--primary">
          <i class="fas fa-envelope"></i> Zgłoś problem
        </a>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>

</html>
