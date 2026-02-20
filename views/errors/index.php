<?php
// Wartości domyślne, jeśli nie przekazano ich z kontrolera
$pageTitle = $pageTitle ?? 'Błąd | Examly';
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

  <main class="error-page"
    style="min-height: 80vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 2rem;">
    <div class="error-page__content">
      <h1 class="gradient-text" style="font-size: 8rem; margin: 0; line-height: 1;">
        <?= htmlspecialchars($errorCode) ?>
      </h1>
      <div style="font-size: 4rem; color: var(--accent); margin-bottom: 1rem;">
        <i class="<?= htmlspecialchars($errorIcon) ?>"></i>
      </div>
      <h2 style="font-size: 2rem; margin-bottom: 1rem; color: var(--text-color);">
        <?= htmlspecialchars($errorTitle) ?>
      </h2>
      <p
        style="margin-bottom: 2rem; color: var(--text-muted); max-width: 600px; margin-left: auto; margin-right: auto; line-height: 1.6;">
        <?= htmlspecialchars($errorMessage) ?>
      </p>
      <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <a href="<?= url('/') ?>" class="btn btn--secondary">
          <i class="fas fa-home"></i> Strona główna
        </a>
        <a href="mailto:examlyacademy@gmail.com" class="btn btn--primary">
          <i class="fas fa-envelope"></i> Zgłoś problem
        </a>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>

</html>