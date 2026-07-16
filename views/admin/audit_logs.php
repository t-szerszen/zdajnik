<?php
$pageTitle = $pageTitle ?? 'Audyt administracji | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Dziennik operacji administracyjnych.';
$filters = $filters ?? [];
$logs = $logs ?? [];
$pagination = $pagination ?? ['page' => 1, 'totalPages' => 1];
$actions = $actions ?? [];
$admins = $admins ?? [];
$targetTypes = $targetTypes ?? [];

$valueToString = static function ($value): string {
  if ($value === null || $value === '') {
    return 'Brak';
  }
  $decoded = is_string($value) ? json_decode($value, true) : $value;
  return is_array($decoded)
    ? (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : (string) $value;
};
$buildUrl = static function (int $page) use ($filters): string {
  $params = array_filter(array_merge($filters, ['page' => $page]), static fn($value) => $value !== null && $value !== '');
  return url('admin/audyt') . '?' . http_build_query($params);
};
$date = static fn(?string $value): string => $value ? date('d.m.Y H:i', strtotime($value)) : 'Brak';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>
<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>
  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient">Audyt administracji</span></h1>
        <p class="page-header__text">Kto, kiedy i jaki rekord zmienił.</p>
      </div>
    </header>
    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'audit'; include __DIR__ . '/partials/navigation.php'; ?>
        <div class="admin-content">
          <?php include __DIR__ . '/partials/flash.php'; ?>
          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-filter"></i><span>Filtry</span></div>
            </div>
            <form class="admin-toolbar" method="GET" action="<?= url('admin/audyt') ?>">
              <label class="admin-field"><span>Administrator</span><select name="admin_user_id"><option value="">Wszyscy</option><?php foreach ($admins as $admin): ?><option value="<?= (int) $admin['id'] ?>" <?= (string) ($filters['admin_user_id'] ?? '') === (string) $admin['id'] ? 'selected' : '' ?>><?= htmlspecialchars(trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?: (string) $admin['email']) ?></option><?php endforeach; ?></select></label>
              <label class="admin-field"><span>Akcja</span><select name="action"><option value="">Wszystkie</option><?php foreach ($actions as $key => $label): ?><option value="<?= htmlspecialchars($key) ?>" <?= ($filters['action'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></label>
              <label class="admin-field"><span>Typ rekordu</span><select name="target_type"><option value="">Wszystkie</option><?php foreach ($targetTypes as $key => $label): ?><option value="<?= htmlspecialchars($key) ?>" <?= ($filters['target_type'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></label>
              <label class="admin-field"><span>ID rekordu</span><input type="number" min="1" name="target_id" value="<?= htmlspecialchars((string) ($filters['target_id'] ?? '')) ?>"></label>
              <label class="admin-field"><span>Data od</span><input type="date" name="date_from" value="<?= htmlspecialchars((string) ($filters['date_from'] ?? '')) ?>"></label>
              <label class="admin-field"><span>Data do</span><input type="date" name="date_to" value="<?= htmlspecialchars((string) ($filters['date_to'] ?? '')) ?>"></label>
              <div class="admin-toolbar__actions"><button class="btn btn--primary" type="submit">Filtruj</button><a class="btn btn--secondary" href="<?= url('admin/audyt') ?>">Wyczyść</a></div>
            </form>
          </section>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-clipboard-list"></i><span>Wpisy audytowe</span></div>
              <span class="badge badge--secondary">Wyniki: <?= (int) ($pagination['total'] ?? 0) ?></span>
            </div>
            <div class="admin-table admin-table--audit">
              <div class="admin-table__head"><span>Czas</span><span>Administrator</span><span>Akcja</span><span>Rekord</span><span>Stara wartość</span><span>Nowa wartość</span><span>Środowisko</span></div>
              <?php if (empty($logs)): ?>
                <div class="admin-empty">Brak wpisów audytowych spełniających kryteria.</div>
              <?php else: ?>
                <?php foreach ($logs as $log): ?>
                  <div class="admin-table__row">
                    <div class="admin-table__cell" data-label="Czas"><?= $date($log['created_at'] ?? null) ?></div>
                    <div class="admin-table__cell admin-truncate" data-label="Administrator"><strong><?= htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'Brak nazwy') ?></strong><small class="admin-break-anywhere"><?= htmlspecialchars((string) ($log['email'] ?? '')) ?></small></div>
                    <div class="admin-table__cell" data-label="Akcja"><span class="admin-status admin-status--accent"><?= htmlspecialchars($actions[$log['action']] ?? (string) $log['action']) ?></span></div>
                    <div class="admin-table__cell" data-label="Rekord"><?= htmlspecialchars((string) ($log['target_type'] ?? '')) ?><?php if (!empty($log['target_id'])): ?> #<?= (int) $log['target_id'] ?><?php endif; ?></div>
                    <div class="admin-table__cell" data-label="Stara wartość"><pre class="admin-code-block"><?= htmlspecialchars($valueToString($log['old_value'] ?? null)) ?></pre></div>
                    <div class="admin-table__cell" data-label="Nowa wartość"><pre class="admin-code-block"><?= htmlspecialchars($valueToString($log['new_value'] ?? null)) ?></pre></div>
                    <div class="admin-table__cell" data-label="Środowisko"><small>IP: <?= htmlspecialchars((string) ($log['ip_address'] ?? 'Brak')) ?></small><small class="admin-user-agent" title="<?= htmlspecialchars((string) ($log['user_agent'] ?? 'Brak')) ?>">UA: <?= htmlspecialchars((string) ($log['user_agent'] ?? 'Brak')) ?></small></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
              <div class="admin-pagination"><span class="admin-pagination__status">Strona <?= (int) $pagination['page'] ?> / <?= (int) $pagination['totalPages'] ?></span><div class="admin-actions"><?php if ($pagination['page'] > 1): ?><a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl($pagination['page'] - 1)) ?>">Poprzednia</a><?php endif; ?><?php if ($pagination['page'] < $pagination['totalPages']): ?><a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl($pagination['page'] + 1)) ?>">Następna</a><?php endif; ?></div></div>
            <?php endif; ?>
          </section>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
