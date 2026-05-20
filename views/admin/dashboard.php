<?php

/**
 * @file dashboard.php
 * @description
 * Główny dashboard panelu CMS/Admin.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

$pageTitle = $pageTitle ?? 'Admin Panel | Examly';
$pageDescription = $pageDescription ?? 'Panel administracyjny Examly.';
$overview = $overview ?? [];
$recentUsers = $recentUsers ?? [];
$hasActivityData = $hasActivityData ?? false;
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>

<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="main-content-wrapper admin-panel">
    <header class="page-header" id="top">
      <div class="page-header__content">
        <h1 class="page-header__title">
          <span class="text-gradient">Admin Panel</span>
        </h1>
        <p class="page-header__text">
          Bezpieczny punkt wejścia do modułów zarządzania treścią i strukturą aplikacji Examly.
        </p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'dashboard';
        include __DIR__ . '/partials/navigation.php'; ?>

        <div class="admin-content">
          <div class="admin-grid">
            <a href="<?= url('admin/uzytkownicy') ?>" class="stats-card admin-tile">
              <div class="admin-tile__icon"><i class="fas fa-users"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Użytkownicy</span>
                <strong class="admin-tile__value"><?= (int) ($overview['users'] ?? 0) ?></strong>
                <span class="admin-tile__meta">W tym admini: <?= (int) ($overview['admins'] ?? 0) ?></span>
              </div>
            </a>

            <a href="<?= url('admin/egzaminy') ?>" class="stats-card admin-tile">
              <div class="admin-tile__icon"><i class="fas fa-file-lines"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Egzaminy</span>
                <strong class="admin-tile__value"><?= (int) ($overview['exams'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Zarządzanie kodami, opisami i statusem</span>
              </div>
            </a>

            <a href="<?= url('admin/pytania') ?>" class="stats-card admin-tile">
              <div class="admin-tile__icon"><i class="fas fa-circle-question"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Pytania</span>
                <strong class="admin-tile__value"><?= (int) ($overview['questions'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Baza pytań do dalszego zarządzania</span>
              </div>
            </a>

            <a href="<?= url('admin/statystyki') ?>" class="stats-card admin-tile">
              <div class="admin-tile__icon"><i class="fas fa-chart-line"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Aktywność</span>
                <strong class="admin-tile__value"><?= (int) ($overview['attempts'] ?? 0) ?></strong>
                <span class="admin-tile__meta">
                  <?= $hasActivityData ? 'Dane aktywności są dostępne' : 'Brak danych aktywności' ?>
                </span>
              </div>
            </a>

            <a href="<?= url('/') ?>" class="stats-card admin-tile admin-tile--back">
              <div class="admin-tile__icon"><i class="fas fa-arrow-left"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Powrót do aplikacji</span>
                <strong class="admin-tile__value">Examly</strong>
                <span class="admin-tile__meta">Wyjdź z panelu administracyjnego</span>
              </div>
            </a>
          </div>

          <div class="admin-section">
            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-shield-halved" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                  <span>Dashboard CMS</span>
                </div>
              </div>

              <div class="stats-card__content">
                <p class="admin-lead">
                  Jesteś zalogowany jako administrator. Ten panel stanowi punkt wejścia do zarządzania treścią i strukturą aplikacji.
                </p>
              </div>
            </div>

            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-clock" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                  <span>Ostatni użytkownicy</span>
                </div>
              </div>

              <div class="admin-list">
                <?php if (empty($recentUsers)): ?>
                  <div class="admin-empty">
                    Brak danych do wyświetlenia.
                  </div>
                <?php else: ?>
                  <?php foreach ($recentUsers as $user): ?>
                    <div class="admin-list__row">
                      <div class="admin-list__primary">
                        <strong><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></strong>
                        <span><?= htmlspecialchars($user['email'] ?? '') ?></span>
                      </div>
                      <div class="admin-list__secondary">
                        <span class="badge badge--secondary"><?= htmlspecialchars($user['role'] ?? 'user') ?></span>
                        <span class="badge badge--accent"><?= htmlspecialchars($user['auth_provider'] ?? 'local') ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>

</html>