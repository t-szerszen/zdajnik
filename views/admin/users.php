<?php
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
        <h1 class="page-header__title"><span class="text-gradient">Użytkownicy</span></h1>
        <p class="page-header__text">Wyszukiwanie, statusy kont i szybkie akcje administracyjne.</p>
      </div>
    </header>
    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'users'; include __DIR__ . '/partials/navigation.php'; ?>
        <div class="admin-content">
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
            <form class="admin-toolbar" method="GET" action="<?= url('admin/uzytkownicy') ?>">
              <label class="admin-field admin-field--search"><span>Szukaj</span><input type="text" name="q" value="<?= htmlspecialchars((string) ($filters['query'] ?? '')) ?>" placeholder="ID, e-mail, imię lub nazwisko"></label>
              <label class="admin-field"><span>Rola</span><select name="role"><option value="">Wszystkie</option><option value="user" <?= ($filters['role'] ?? '') === 'user' ? 'selected' : '' ?>>Użytkownik</option><option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option></select></label>
              <label class="admin-field"><span>Status</span><select name="status"><option value="">Wszystkie</option><option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktywny</option><option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Dezaktywowany</option></select></label>
              <label class="admin-field"><span>Weryfikacja</span><select name="verified"><option value="">Wszystkie</option><option value="verified" <?= ($filters['verified'] ?? '') === 'verified' ? 'selected' : '' ?>>Zweryfikowany</option><option value="unverified" <?= ($filters['verified'] ?? '') === 'unverified' ? 'selected' : '' ?>>Niezweryfikowany</option></select></label>
              <label class="admin-field"><span>Provider</span><select name="auth_provider"><option value="">Wszystkie</option><option value="local" <?= ($filters['auth_provider'] ?? '') === 'local' ? 'selected' : '' ?>>Local</option><option value="google" <?= ($filters['auth_provider'] ?? '') === 'google' ? 'selected' : '' ?>>Google</option></select></label>
              <label class="admin-field"><span>Aktywność</span><select name="activity"><option value="">Wszystkie</option><option value="active_recently" <?= ($filters['activity'] ?? '') === 'active_recently' ? 'selected' : '' ?>>Aktywni w 7 dni</option><option value="inactive_7d" <?= ($filters['activity'] ?? '') === 'inactive_7d' ? 'selected' : '' ?>>Brak aktywności 7 dni</option><option value="inactive_30d" <?= ($filters['activity'] ?? '') === 'inactive_30d' ? 'selected' : '' ?>>Brak aktywności 30 dni</option><option value="no_attempts" <?= ($filters['activity'] ?? '') === 'no_attempts' ? 'selected' : '' ?>>Bez ukończonych testów</option></select></label>
              <label class="admin-field"><span>Sortowanie</span><select name="sort"><option value="id" <?= $sort === 'id' ? 'selected' : '' ?>>ID</option><option value="email" <?= $sort === 'email' ? 'selected' : '' ?>>E-mail</option><option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Rejestracja</option><option value="last_activity" <?= $sort === 'last_activity' ? 'selected' : '' ?>>Aktywność</option></select></label>
              <label class="admin-field"><span>Kolejność</span><select name="dir"><option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Malejąco</option><option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Rosnąco</option></select></label>
              <div class="admin-toolbar__actions"><button class="btn btn--primary" type="submit">Filtruj</button><a class="btn btn--secondary" href="<?= url('admin/uzytkownicy') ?>">Wyczyść</a></div>
            </form>
          </section>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-users"></i><span>Lista użytkowników</span></div>
              <span class="badge badge--secondary">Wyniki: <?= (int) ($pagination['total'] ?? 0) ?></span>
            </div>
            <div class="admin-table admin-table--users">
              <div class="admin-table__head">
                <span>ID</span><span>Użytkownik</span><span>E-mail</span><span>Rola</span><span>Konto</span><span>Weryfikacja</span><span>Provider</span><span>Aktywność</span><span>Testy</span><span>Akcje</span>
              </div>
              <?php if (empty($users)): ?>
                <div class="admin-empty">Nie znaleziono użytkowników.</div>
              <?php else: ?>
                <?php foreach ($users as $user): ?>
                  <?php $userId = (int) $user['id']; ?>
                  <div class="admin-table__row">
                    <div class="admin-table__cell admin-table__cell--id" data-label="ID"><?= $userId ?></div>
                    <div class="admin-table__cell admin-truncate" data-label="Użytkownik" title="<?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>"><strong><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Brak nazwy') ?></strong><small>Rejestracja: <?= $date($user['created_at'] ?? null) ?></small></div>
                    <div class="admin-table__cell admin-break-anywhere" data-label="E-mail"><?= htmlspecialchars((string) ($user['email'] ?? '')) ?></div>
                    <div class="admin-table__cell" data-label="Rola"><span class="admin-status admin-status--<?= ($user['role'] ?? 'user') === 'admin' ? 'accent' : 'neutral' ?>"><?= htmlspecialchars((string) ($user['role'] ?? 'user')) ?></span></div>
                    <div class="admin-table__cell" data-label="Konto"><span class="admin-status admin-status--<?= (int) ($user['is_active'] ?? 0) === 1 ? 'success' : 'danger' ?>"><?= (int) ($user['is_active'] ?? 0) === 1 ? 'Aktywne' : 'Nieaktywne' ?></span></div>
                    <div class="admin-table__cell" data-label="Weryfikacja"><span class="admin-status admin-status--<?= (int) ($user['is_verified'] ?? 0) === 1 ? 'success' : 'warning' ?>"><?= (int) ($user['is_verified'] ?? 0) === 1 ? 'Tak' : 'Nie' ?></span></div>
                    <div class="admin-table__cell" data-label="Provider"><?= htmlspecialchars((string) ($user['auth_provider'] ?? 'local')) ?></div>
                    <div class="admin-table__cell" data-label="Aktywność"><span><?= $date($user['last_activity'] ?? null) ?></span><small>Login: <?= $date($user['last_login'] ?? null) ?></small></div>
                    <div class="admin-table__cell" data-label="Testy"><?= (int) ($user['solved_tests'] ?? 0) ?></div>
                    <div class="admin-table__cell" data-label="Akcje">
                      <div class="admin-actions">
                        <a class="btn btn--secondary btn--sm" href="<?= url('admin/uzytkownicy/' . $userId) ?>">Szczegóły</a>
                        <?php if ((int) ($user['is_verified'] ?? 0) === 0): ?>
                          <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/zweryfikuj') ?>"><?= csrf_field() ?><input type="hidden" name="return_to" value="list"><button class="btn btn--secondary btn--sm" type="submit">Zweryfikuj</button></form>
                        <?php endif; ?>
                        <?php if ((int) ($user['is_active'] ?? 0) === 1): ?>
                          <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/dezaktywuj') ?>"><?= csrf_field() ?><input type="hidden" name="return_to" value="list"><button class="btn btn--danger btn--sm" type="submit" onclick="return confirm('Na pewno zdezaktywować to konto?')">Dezaktywuj</button></form>
                        <?php else: ?>
                          <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/reaktywuj') ?>"><?= csrf_field() ?><input type="hidden" name="return_to" value="list"><button class="btn btn--primary btn--sm" type="submit">Reaktywuj</button></form>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
              <div class="admin-pagination">
                <span class="admin-pagination__status">Strona <?= (int) $pagination['page'] ?> / <?= (int) $pagination['totalPages'] ?></span>
                <div class="admin-actions">
                  <?php if ($pagination['page'] > 1): ?><a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl(['page' => $pagination['page'] - 1])) ?>">Poprzednia</a><?php endif; ?>
                  <?php if ($pagination['page'] < $pagination['totalPages']): ?><a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl(['page' => $pagination['page'] + 1])) ?>">Następna</a><?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </section>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
