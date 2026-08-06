<?php
$noIndex = true;
$pageTitle = $pageTitle ?? 'Użytkownicy | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Zarządzanie użytkownikami Zdajnik.';
$users = $users ?? [];
$filters = $filters ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['page' => 1, 'totalPages' => 1];
$sort = $sort ?? 'id';
$dir = $dir ?? 'desc';

$params = static function (array $overrides = []) use ($filters, $sort, $dir): array {
  return array_filter(array_merge([
    'q' => $filters['query'] ?? '',
    'role' => $filters['role'] ?? '',
    'status' => $filters['status'] ?? '',
    'verified' => $filters['verified'] ?? '',
    'auth_provider' => $filters['auth_provider'] ?? '',
    'activity' => $filters['activity'] ?? '',
    'sort' => $sort,
    'dir' => $dir,
    'page' => 1,
  ], $overrides), static fn($value) => $value !== null && $value !== '');
};
$buildUrl = static fn(array $overrides = []): string => url('admin/uzytkownicy') . '?' . http_build_query($params($overrides));
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>
<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>
  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient">Użytkownicy</span></h1>
        <p class="page-header__text">Wyszukiwanie, statusy kont i szybkie akcje administracyjne.</p>
      </div>
    </header>
    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'users'; include __DIR__ . '/partials/navigation.php'; ?>
        <div class="admin-content"
          data-admin-table-root
          data-endpoint="<?= url('admin/uzytkownicy/api') ?>"
          data-bulk-endpoint="<?= url('admin/uzytkownicy/bulk') ?>"
          data-page="<?= (int) ($pagination['page'] ?? 1) ?>"
          data-total-pages="<?= (int) ($pagination['totalPages'] ?? 1) ?>">
          <?php include __DIR__ . '/partials/flash.php'; ?>

          <div class="admin-summary-strip">
            <a href="<?= url('admin/uzytkownicy?status=active') ?>"><span>Aktywni</span><strong><?= (int) ($summary['active_users'] ?? 0) ?></strong></a>
            <a href="<?= url('admin/uzytkownicy?verified=0') ?>"><span>Niezweryfikowani</span><strong><?= (int) ($summary['unverified_users'] ?? 0) ?></strong></a>
            <a href="<?= url('admin/uzytkownicy?status=inactive') ?>"><span>Dezaktywowani</span><strong><?= (int) ($summary['inactive_users'] ?? 0) ?></strong></a>
            <a href="<?= url('admin/uzytkownicy?role=admin') ?>"><span>Aktywni admini</span><strong><?= (int) ($summary['active_admins'] ?? 0) ?></strong></a>
          </div>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-filter"></i><span>Filtry</span></div>
            </div>
            <form class="admin-toolbar" method="GET" action="<?= url('admin/uzytkownicy') ?>" data-admin-filter-form>
              <label class="admin-field admin-field--search"><span>Szukaj</span><input type="text" name="q" value="<?= htmlspecialchars((string) ($filters['query'] ?? '')) ?>" placeholder="ID, e-mail, imię lub nazwisko"></label>
              <label class="admin-field"><span>Rola</span><select name="role"><option value="">Wszystkie</option><option value="user" <?= ($filters['role'] ?? '') === 'user' ? 'selected' : '' ?>>Użytkownik</option><option value="redaktor" <?= ($filters['role'] ?? '') === 'redaktor' ? 'selected' : '' ?>>Redaktor</option><option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option></select></label>
              <label class="admin-field"><span>Status</span><select name="status"><option value="">Wszystkie</option><option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktywny</option><option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Dezaktywowany</option></select></label>
              <label class="admin-field"><span>Weryfikacja</span><select name="verified"><option value="">Wszystkie</option><option value="verified" <?= ($filters['verified'] ?? '') === 'verified' ? 'selected' : '' ?>>Zweryfikowany</option><option value="unverified" <?= ($filters['verified'] ?? '') === 'unverified' ? 'selected' : '' ?>>Niezweryfikowany</option></select></label>
              <label class="admin-field"><span>Provider</span><select name="auth_provider"><option value="">Wszystkie</option><option value="local" <?= ($filters['auth_provider'] ?? '') === 'local' ? 'selected' : '' ?>>Local</option><option value="google" <?= ($filters['auth_provider'] ?? '') === 'google' ? 'selected' : '' ?>>Google</option></select></label>
              <label class="admin-field"><span>Aktywność</span><select name="activity"><option value="">Wszystkie</option><option value="active_recently" <?= ($filters['activity'] ?? '') === 'active_recently' ? 'selected' : '' ?>>Aktywni w 7 dni</option><option value="inactive_7d" <?= ($filters['activity'] ?? '') === 'inactive_7d' ? 'selected' : '' ?>>Brak aktywności 7 dni</option><option value="inactive_30d" <?= ($filters['activity'] ?? '') === 'inactive_30d' ? 'selected' : '' ?>>Brak aktywności 30 dni</option><option value="no_attempts" <?= ($filters['activity'] ?? '') === 'no_attempts' ? 'selected' : '' ?>>Bez ukończonych testów</option></select></label>
              <label class="admin-field"><span>Sortowanie</span><select name="sort"><option value="id" <?= $sort === 'id' ? 'selected' : '' ?>>ID</option><option value="email" <?= $sort === 'email' ? 'selected' : '' ?>>E-mail</option><option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Rejestracja</option><option value="last_activity" <?= $sort === 'last_activity' ? 'selected' : '' ?>>Aktywność</option></select></label>
              <label class="admin-field"><span>Kolejność</span><select name="dir"><option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Malejąco</option><option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Rosnąco</option></select></label>
              <div class="admin-toolbar__actions"><button class="btn btn--primary" type="submit">Filtruj</button><a class="btn btn--secondary" href="<?= url('admin/uzytkownicy') ?>" data-admin-reset>Wyczyść</a><span class="admin-analytics__request-status hidden" data-admin-status><span class="admin-analytics__spinner"></span>Wczytywanie…</span></div>
            </form>
          </section>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-users"></i><span>Lista użytkowników</span></div>
              <span class="badge badge--secondary" data-admin-total>Wyniki: <?= (int) ($pagination['total'] ?? 0) ?></span>
            </div>
            <div class="admin-table admin-table--users">
              <div class="admin-table__head">
                <span><input type="checkbox" data-check-all aria-label="Zaznacz wszystkich na stronie"></span><span>ID</span><span>Użytkownik</span><span>E-mail</span><span>Rola</span><span>Konto</span><span>Weryfikacja</span><span>Provider</span><span>Aktywność</span><span>Testy</span><span>Akcje</span>
              </div>
              <div class="admin-table__body" data-admin-rows>
                <?php include __DIR__ . '/partials/users_rows.php'; ?>
              </div>
            </div>

            <div class="admin-bulk-bar" data-admin-bulk-bar>
              <span class="admin-bulk-bar__info">Zaznaczono: <strong data-bulk-count>0</strong></span>
              <div class="admin-bulk-bar__actions">
                <button type="button" class="btn btn--secondary btn--sm" data-bulk-action="verify" data-confirm="Zweryfikować zaznaczone konta?" data-confirm-title="Weryfikacja zbiorcza">Zweryfikuj</button>
                <button type="button" class="btn btn--primary btn--sm" data-bulk-action="reactivate" data-confirm="Reaktywować zaznaczone konta?" data-confirm-title="Reaktywacja zbiorcza">Reaktywuj</button>
                <button type="button" class="btn btn--danger btn--sm" data-bulk-action="deactivate" data-confirm="Zdezaktywować zaznaczone konta?" data-confirm-title="Dezaktywacja zbiorcza" data-confirm-tone="danger">Dezaktywuj</button>
                <button type="button" class="btn btn--secondary btn--sm" data-bulk-clear>Odznacz wszystko</button>
              </div>
            </div>

            <div class="admin-pagination" data-admin-pagination>
              <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
                <span class="admin-pagination__status">Strona <?= (int) $pagination['page'] ?> / <?= (int) $pagination['totalPages'] ?></span>
                <div class="admin-actions">
                  <?php if ($pagination['page'] > 1): ?><a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl(['page' => $pagination['page'] - 1])) ?>">Poprzednia</a><?php endif; ?>
                  <?php if ($pagination['page'] < $pagination['totalPages']): ?><a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl(['page' => $pagination['page'] + 1])) ?>">Następna</a><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
