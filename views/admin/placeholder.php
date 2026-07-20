<?php
/**
 * @file placeholder.php
 * @description Widok zastępczy dla modułów CMS przygotowanych na kolejne etapy.
 */

$pageTitle = $pageTitle ?? 'Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Sekcja panelu CMS.';
$title = $title ?? 'Sekcja';
$description = $description ?? 'Sekcja przygotowana pod kolejne etapy.';
$icon = $icon ?? 'fas fa-layer-group';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>

<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient"><?= htmlspecialchars($title) ?></span></h1>
        <p class="page-header__text"><?= htmlspecialchars($description) ?></p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php
        $activeSection = strtolower($title) === 'egzaminy' ? 'exams' : 'questions';
        include __DIR__ . '/partials/navigation.php';
        ?>

        <div class="admin-content">
          <div class="stats-card admin-placeholder">
            <div class="admin-placeholder__icon">
              <i class="<?= htmlspecialchars($icon) ?>"></i>
            </div>
            <h2><?= htmlspecialchars($title) ?></h2>
            <p><?= htmlspecialchars($description) ?></p>
            <a href="<?= url('admin') ?>" class="back-link"><i class="fas fa-arrow-left"></i> Wróć do pulpitu</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
