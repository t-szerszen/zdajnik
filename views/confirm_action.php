<?php
/**
 * @file confirm_action.php
 * @description Uniwersalny widok potwierdzenia destrukcyjnej akcji (usuwanie konta, reset postępu).
 * Zapobiega przypadkowemu wywołaniu przez skanery antywirusowe w e-mailach.
 */

$pageTitle = $actionTitle . ' | Zdajnik';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="home-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="page-container" style="display: flex; justify-content: center; align-items: center; min-height: 60vh; padding: 2rem;">
    <div class="form-card" style="max-width: 500px; width: 100%; text-align: center;">
      <div class="form-card__header">
        <h1 class="form-card__title"><?= htmlspecialchars($actionTitle) ?></h1>
      </div>
      
      <p style="margin-bottom: 2rem; color: #52645f; line-height: 1.6; font-size: 1.1rem;">
        <?= htmlspecialchars($actionMessage) ?>
      </p>

      <form method="POST" action="<?= htmlspecialchars($actionUrl) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        
        <div class="form-card__action-container" style="display: flex; flex-direction: column; gap: 1rem;">
          <button type="submit" class="btn <?= htmlspecialchars($btnClass) ?> btn--full">
            <?= htmlspecialchars($btnLabel) ?>
          </button>
          
          <a href="<?= url('ustawienia') ?>" class="btn btn--secondary btn--full">
            Anuluj i wróć do ustawień
          </a>
        </div>
      </form>
    </div>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>
</html>
