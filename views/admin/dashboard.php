<?php
$noIndex = true;
$pageTitle = $pageTitle ?? 'Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Operacyjny panel administracyjny Zdajnik.';
$overview = $overview ?? [];
$attention = $attention ?? [];
$recentAuditActions = $recentAuditActions ?? [];
$recentUserActivity = $recentUserActivity ?? [];
$auditActions = $auditActions ?? [];
$isFullAdmin = $isFullAdmin ?? true;

$date = static fn(?string $value): string => $value
  ? date('d.m.Y H:i', strtotime($value))
  : 'Brak';
$actionLabel = static fn(string $action): string => $auditActions[$action] ?? $action;
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>
<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>
  <main class="main-content-wrapper admin-panel">
    <header class="page-header" id="top">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient">Panel administracyjny</span></h1>
        <p class="page-header__text">Stan kont, treści i ostatnich operacji w Zdajnik.</p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'dashboard'; include __DIR__ . '/partials/navigation.php'; ?>
        <div class="admin-content">
          <?php include __DIR__ . '/partials/flash.php'; ?>

          <div class="admin-kpi-grid" data-trends-endpoint="<?= url('admin/dashboard/trendy') ?>">
            <?php if ($isFullAdmin): ?>
              <a class="admin-kpi" href="<?= url('admin/uzytkownicy?status=active') ?>" data-sparkline-metric="newUsers">
                <span class="admin-kpi__label">Aktywni użytkownicy</span>
                <strong class="admin-kpi__value"><?= (int) ($overview['users_active'] ?? 0) ?></strong>
                <canvas class="admin-kpi__sparkline" aria-hidden="true"></canvas>
              </a>
              <a class="admin-kpi admin-kpi--warning" href="<?= url('admin/uzytkownicy?verified=0') ?>">
                <span class="admin-kpi__label">Niezweryfikowani</span>
                <strong class="admin-kpi__value"><?= (int) ($overview['users_unverified'] ?? 0) ?></strong>
              </a>
              <a class="admin-kpi" href="<?= url('admin/uzytkownicy?status=inactive') ?>">
                <span class="admin-kpi__label">Dezaktywowani</span>
                <strong class="admin-kpi__value"><?= (int) ($overview['users_inactive'] ?? 0) ?></strong>
              </a>
              <a class="admin-kpi" href="<?= url('admin/uzytkownicy?role=admin') ?>">
                <span class="admin-kpi__label">Aktywni admini</span>
                <strong class="admin-kpi__value"><?= (int) ($overview['admins'] ?? 0) ?></strong>
              </a>
              <a class="admin-kpi" href="<?= url('admin/egzaminy?status=active') ?>" data-sparkline-metric="attempts">
                <span class="admin-kpi__label">Aktywne egzaminy</span>
                <strong class="admin-kpi__value"><?= (int) ($overview['exams_active'] ?? 0) ?></strong>
                <span class="admin-kpi__meta">Nieaktywne: <?= (int) ($overview['exams_inactive'] ?? 0) ?></span>
                <canvas class="admin-kpi__sparkline" aria-hidden="true"></canvas>
              </a>
            <?php endif; ?>
            <a class="admin-kpi" href="<?= url('admin/pytania?status=active') ?>"<?= $isFullAdmin ? ' data-sparkline-metric="activity"' : '' ?>>
              <span class="admin-kpi__label">Aktywne pytania</span>
              <strong class="admin-kpi__value"><?= (int) ($overview['questions_active'] ?? 0) ?></strong>
              <span class="admin-kpi__meta">Nieaktywne: <?= (int) ($overview['questions_inactive'] ?? 0) ?></span>
              <?php if ($isFullAdmin): ?><canvas class="admin-kpi__sparkline" aria-hidden="true"></canvas><?php endif; ?>
            </a>
          </div>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title stats-card__title--warning">
                <i class="fas fa-triangle-exclamation"></i>
                <span>Wymaga uwagi</span>
              </div>
            </div>
            <div class="admin-attention-grid">
              <?php if ($isFullAdmin): ?>
              <div class="admin-attention">
                <div class="admin-attention__header">
                  <strong>Niezweryfikowane konta</strong>
                  <a href="<?= url('admin/uzytkownicy?verified=0') ?>">Pokaż wszystkie</a>
                </div>
                <?php if (empty($attention['unverified_users'])): ?>
                  <div class="admin-empty admin-empty--compact">Brak kont do weryfikacji.</div>
                <?php else: ?>
                  <?php foreach ($attention['unverified_users'] as $user): ?>
                    <a class="admin-attention__row" href="<?= url('admin/uzytkownicy/' . (int) $user['id']) ?>">
                      <span class="admin-truncate"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Brak nazwy') ?></span>
                      <small><?= htmlspecialchars((string) ($user['auth_provider'] ?? 'local')) ?></small>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <?php endif; ?>

              <div class="admin-attention">
                <div class="admin-attention__header">
                  <strong>Nieaktywne egzaminy z pytaniami</strong>
                  <a href="<?= url('admin/egzaminy?status=inactive') ?>">Pokaż wszystkie</a>
                </div>
                <?php if (empty($attention['inactive_exams_with_questions'])): ?>
                  <div class="admin-empty admin-empty--compact">Brak problemów.</div>
                <?php else: ?>
                  <?php foreach ($attention['inactive_exams_with_questions'] as $exam): ?>
                    <a class="admin-attention__row" href="<?= url('admin/egzaminy/' . (int) $exam['id']) ?>">
                      <span class="admin-truncate"><?= htmlspecialchars((string) $exam['code']) ?> · <?= htmlspecialchars((string) $exam['name']) ?></span>
                      <small><?= (int) $exam['questions_count'] ?> pytań</small>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <div class="admin-attention">
                <div class="admin-attention__header">
                  <strong>Niekompletne pytania</strong>
                  <a href="<?= url('admin/pytania?status=active') ?>">Przejdź do pytań</a>
                </div>
                <?php if (empty($attention['invalid_questions'])): ?>
                  <div class="admin-empty admin-empty--compact">Brak problemów.</div>
                <?php else: ?>
                  <?php foreach ($attention['invalid_questions'] as $question): ?>
                    <a class="admin-attention__row" href="<?= url('admin/pytania/' . (int) $question['id']) ?>">
                      <span class="admin-truncate">#<?= (int) $question['id'] ?> · <?= htmlspecialchars((string) ($question['question_text'] ?? 'Brak treści')) ?></span>
                      <small><?= (int) $question['answers_count'] ?> odp.</small>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <?php if ($isFullAdmin): ?>
              <div class="admin-attention">
                <div class="admin-attention__header">
                  <strong>Błędy operacyjne w audycie</strong>
                  <a href="<?= url('admin/audyt') ?>">Otwórz audyt</a>
                </div>
                <?php if (empty($attention['operation_errors'])): ?>
                  <div class="admin-empty admin-empty--compact">Brak zarejestrowanych błędów.</div>
                <?php else: ?>
                  <?php foreach ($attention['operation_errors'] as $error): ?>
                    <div class="admin-attention__row">
                      <span class="admin-truncate"><?= htmlspecialchars((string) $error['action']) ?></span>
                      <small><?= $date($error['created_at'] ?? null) ?></small>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </div>
          </section>

          <?php if ($isFullAdmin): ?>
          <div class="admin-section admin-section--two-col">
            <section class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title"><i class="fas fa-clock-rotate-left"></i><span>Ostatnie akcje audytowe</span></div>
                <a class="btn btn--secondary btn--sm" href="<?= url('admin/audyt') ?>">Pełny audyt</a>
              </div>
              <div class="admin-list">
                <?php if (empty($recentAuditActions)): ?>
                  <div class="admin-empty">Brak wpisów audytowych.</div>
                <?php else: ?>
                  <?php foreach ($recentAuditActions as $log): ?>
                    <div class="admin-list__row">
                      <div class="admin-list__primary">
                        <strong><?= htmlspecialchars($actionLabel((string) $log['action'])) ?></strong>
                        <span class="admin-break-anywhere"><?= htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))) ?> · <?= $date($log['created_at'] ?? null) ?></span>
                      </div>
                      <span class="badge badge--secondary"><?= htmlspecialchars((string) $log['target_type']) ?> #<?= (int) ($log['target_id'] ?? 0) ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </section>

            <section class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title"><i class="fas fa-user-clock"></i><span>Ostatnia aktywność użytkowników</span></div>
                <a class="btn btn--secondary btn--sm" href="<?= url('admin/uzytkownicy?sort=last_activity&dir=desc') ?>">Lista</a>
              </div>
              <div class="admin-list">
                <?php if (empty($recentUserActivity)): ?>
                  <div class="admin-empty">Brak aktywności.</div>
                <?php else: ?>
                  <?php foreach ($recentUserActivity as $user): ?>
                    <a class="admin-list__row admin-list__row--link" href="<?= url('admin/uzytkownicy/' . (int) $user['id']) ?>">
                      <div class="admin-list__primary">
                        <strong><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Brak nazwy') ?></strong>
                        <span class="admin-break-anywhere"><?= htmlspecialchars((string) $user['email']) ?></span>
                      </div>
                      <span class="admin-list__date"><?= $date($user['last_activity'] ?? null) ?></span>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </section>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
