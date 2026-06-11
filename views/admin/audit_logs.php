<?php
/**
 * @file audit_logs.php
 * @description
 * Lista logów audytowych administracji.
 */

$pageTitle = $pageTitle ?? 'Audyt administracji | Panel administracyjny | Examly';
$pageDescription = $pageDescription ?? 'Lista ostatnich akcji administracyjnych w panelu administracyjnym Examly.';
$filters = $filters ?? [];
$logs = $logs ?? [];
$pagination = $pagination ?? ['page' => 1, 'totalPages' => 1];
$actions = $actions ?? [];
$admins = $admins ?? [];

$actionLabel = static function (string $action) use ($actions): string {
  return $actions[$action] ?? $action;
};

$valueToString = static function ($value): string {
  if ($value === null || $value === '') {
    return 'Brak';
  }

  if (is_string($value)) {
    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
      return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return $value;
  }

  return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>

<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient">Audyt</span> administracji</h1>
        <p class="page-header__text">Lista ostatnich akcji adminów z możliwością filtrowania i przeglądu zmian.</p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'audit'; include __DIR__ . '/partials/navigation.php'; ?>

        <div class="admin-content">
          <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert--<?= htmlspecialchars($_SESSION['flash_message']['type']) ?>">
              <?= htmlspecialchars((string) ($_SESSION['flash_message']['text'] ?? '')) ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
          <?php endif; ?>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-filter" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span>Filtry</span>
              </div>
            </div>

            <form class="admin-filters" method="GET" action="<?= url('admin/audyt') ?>">
              <div class="admin-filters__grid">
                <label class="admin-field">
                  <span>Administrator</span>
                  <select name="admin_user_id">
                    <option value="">Wszyscy</option>
                    <?php foreach ($admins as $admin): ?>
                      <option value="<?= (int) $admin['id'] ?>" <?= ((string) ($filters['admin_user_id'] ?? '') === (string) $admin['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars(trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))) ?>
                        (<?= htmlspecialchars($admin['email'] ?? '') ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <label class="admin-field">
                  <span>Akcja</span>
                  <select name="action">
                    <option value="">Wszystkie</option>
                    <?php foreach ($actions as $key => $label): ?>
                      <option value="<?= htmlspecialchars($key) ?>" <?= (($filters['action'] ?? '') === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <label class="admin-field">
                  <span>Data od</span>
                  <input type="date" name="date_from" value="<?= htmlspecialchars((string) ($filters['date_from'] ?? '')) ?>">
                </label>

                <label class="admin-field">
                  <span>Data do</span>
                  <input type="date" name="date_to" value="<?= htmlspecialchars((string) ($filters['date_to'] ?? '')) ?>">
                </label>
              </div>

              <div class="admin-filters__actions">
                <button type="submit" class="btn btn--primary">Filtruj</button>
                <a href="<?= url('admin/audyt') ?>" class="btn btn--secondary">Wyczyść</a>
              </div>
            </form>
          </div>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-clipboard-list" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span>Ostatnie akcje</span>
              </div>
              <div class="stats-card__header-actions">
                <span class="badge badge--secondary">Wyniki: <?= (int) ($pagination['total'] ?? 0) ?></span>
              </div>
            </div>

            <div class="admin-audit">
              <?php if (empty($logs)): ?>
                <div class="admin-empty">Brak wpisów audytowych spełniających kryteria.</div>
              <?php else: ?>
                <?php foreach ($logs as $log): ?>
                  <article class="admin-audit__item">
                    <div class="admin-audit__top">
                      <div>
                        <strong>
                          <?= htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))) ?>
                        </strong>
                        <div class="admin-table__muted">
                          <?= htmlspecialchars($log['email'] ?? '') ?>
                        </div>
                      </div>

                      <div class="admin-audit__meta">
                        <span class="badge badge--accent"><?= htmlspecialchars($actionLabel((string) ($log['action'] ?? ''))) ?></span>
                        <span class="badge badge--secondary"><?= htmlspecialchars((string) ($log['target_type'] ?? '')) ?></span>
                        <?php if (!empty($log['target_id'])): ?>
                          <span class="badge badge--secondary">ID #<?= (int) $log['target_id'] ?></span>
                        <?php endif; ?>
                        <span class="badge badge--secondary">
                          <?= !empty($log['created_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $log['created_at']))) : '' ?>
                        </span>
                      </div>
                    </div>

                    <div class="admin-audit__details">
                      <div class="admin-answer-grid">
                        <div class="admin-answer-grid__item">
                          <span class="admin-answer-grid__label">Stara wartość</span>
                          <span><pre class="admin-code-block"><?= htmlspecialchars($valueToString($log['old_value'] ?? null)) ?></pre></span>
                        </div>
                        <div class="admin-answer-grid__item">
                          <span class="admin-answer-grid__label">Nowa wartość</span>
                          <span><pre class="admin-code-block"><?= htmlspecialchars($valueToString($log['new_value'] ?? null)) ?></pre></span>
                        </div>
                        <div class="admin-answer-grid__item">
                          <span class="admin-answer-grid__label">Metadane</span>
                          <span class="admin-table__muted">
                            IP: <?= htmlspecialchars((string) ($log['ip_address'] ?? 'Brak')) ?><br>
                            UA: <?= htmlspecialchars((string) ($log['user_agent'] ?? 'Brak')) ?>
                          </span>
                        </div>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
              <div class="admin-pagination">
                <?php
                $query = [
                  'admin_user_id' => $filters['admin_user_id'] ?? '',
                  'action' => $filters['action'] ?? '',
                  'date_from' => $filters['date_from'] ?? '',
                  'date_to' => $filters['date_to'] ?? '',
                ];
                $base = function (int $page) use ($query): string {
                  $params = array_filter(array_merge($query, ['page' => $page]), static fn($v) => $v !== null && $v !== '');
                  return url('admin/audyt') . '?' . http_build_query($params);
                };
                ?>

                <?php if (($pagination['page'] ?? 1) > 1): ?>
                  <a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($base((int) $pagination['page'] - 1)) ?>">Poprzednia</a>
                <?php endif; ?>

                <span class="admin-pagination__status">
                  Strona <?= (int) ($pagination['page'] ?? 1) ?> / <?= (int) ($pagination['totalPages'] ?? 1) ?>
                </span>

                <?php if (($pagination['page'] ?? 1) < ($pagination['totalPages'] ?? 1)): ?>
                  <a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($base((int) $pagination['page'] + 1)) ?>">Następna</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>

</html>
