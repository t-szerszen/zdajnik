<?php
/**
 * @file activity.php
 * @description Widok aktywności CMS.
 */

$pageTitle = $pageTitle ?? 'Statystyki i aktywność | Admin Panel | Examly';
$pageDescription = $pageDescription ?? 'Statystyki i aktywność panelu CMS Examly.';
$overview = $overview ?? [];
$recentActivity = $recentActivity ?? [];
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>

<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient">Statystyki</span> i aktywność</h1>
        <p class="page-header__text">Zestawienie podstawowych metryk panelu i ostatnich zakończonych prób.</p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'activity'; include __DIR__ . '/partials/navigation.php'; ?>

        <div class="admin-content">
          <div class="admin-grid admin-grid--compact">
            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-users"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Użytkownicy</span>
                <strong class="admin-tile__value"><?= (int) ($overview['users'] ?? 0) ?></strong>
              </div>
            </div>
            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-file-lines"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Egzaminy</span>
                <strong class="admin-tile__value"><?= (int) ($overview['exams'] ?? 0) ?></strong>
              </div>
            </div>
            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-circle-question"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Pytania</span>
                <strong class="admin-tile__value"><?= (int) ($overview['questions'] ?? 0) ?></strong>
              </div>
            </div>
            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-chart-line"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Próby</span>
                <strong class="admin-tile__value"><?= (int) ($overview['attempts'] ?? 0) ?></strong>
              </div>
            </div>
          </div>

          <div class="admin-section">
            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-bolt" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                  <span>Ostatnia aktywność</span>
                </div>
              </div>
              <div class="admin-list">
                <?php if (empty($recentActivity)): ?>
                  <div class="admin-empty">Brak danych aktywności.</div>
                <?php else: ?>
                  <?php foreach ($recentActivity as $activity): ?>
                    <div class="admin-list__row">
                      <div class="admin-list__primary">
                        <strong><?= htmlspecialchars(trim(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? ''))) ?></strong>
                        <span><?= htmlspecialchars(($activity['exam_code'] ?? '') . ' - ' . ($activity['exam_name'] ?? '')) ?></span>
                      </div>
                      <div class="admin-list__secondary">
                        <span class="badge badge--secondary"><?= htmlspecialchars($activity['test_type'] ?? '') ?></span>
                        <span class="badge badge--accent"><?= htmlspecialchars($activity['completed_at'] ?? '') ?></span>
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
