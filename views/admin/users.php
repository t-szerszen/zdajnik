<?php
/**
 * @file users.php
 * @description
 * Widok listy użytkowników w panelu CMS.
 */

$pageTitle = $pageTitle ?? 'Użytkownicy | Panel administracyjny | Examly';
$pageDescription = $pageDescription ?? 'Lista użytkowników w panelu CMS Examly.';
$users = $users ?? [];
$filters = $filters ?? [];
$pagination = $pagination ?? ['page' => 1, 'totalPages' => 1];
$sort = $sort ?? 'id';
$dir = $dir ?? 'desc';

$buildUrl = static function (array $overrides = []) use ($filters, $sort, $dir): string {
  $params = array_filter([
    'q' => $filters['query'] ?? '',
    'role' => $filters['role'] ?? '',
    'status' => $filters['status'] ?? '',
    'sort' => $sort,
    'dir' => $dir,
    'page' => 1,
  ], static fn($value) => $value !== null && $value !== '');

  foreach ($overrides as $key => $value) {
    if ($value === null || $value === '') {
      unset($params[$key]);
    } else {
      $params[$key] = $value;
    }
  }

  return url('admin/uzytkownicy') . '?' . http_build_query($params);
};

$sortLink = static function (string $column) use ($filters, $sort, $dir): string {
  $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
  return url('admin/uzytkownicy') . '?' . http_build_query(array_filter([
    'q' => $filters['query'] ?? '',
    'role' => $filters['role'] ?? '',
    'status' => $filters['status'] ?? '',
    'sort' => $column,
    'dir' => $nextDir,
  ], static fn($value) => $value !== null && $value !== ''));
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
        <h1 class="page-header__title"><span class="text-gradient">Użytkownicy</span></h1>
        <p class="page-header__text">Wyszukuj, filtruj i zarządzaj kontami użytkowników w jednym miejscu.</p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'users'; include __DIR__ . '/partials/navigation.php'; ?>

        <div class="admin-content">
          <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert--<?= htmlspecialchars($_SESSION['flash_message']['type']) ?>">
              <?php if (isset($_SESSION['flash_message']['errors'])): ?>
                <ul class="alert__list">
                  <?php foreach ((array) $_SESSION['flash_message']['errors'] as $error): ?>
                    <li class="alert__item"><?= htmlspecialchars((string) $error) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <?= htmlspecialchars((string) ($_SESSION['flash_message']['text'] ?? '')) ?>
              <?php endif; ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
          <?php endif; ?>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-filter" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span>Filtry i wyszukiwanie</span>
              </div>
            </div>

            <form class="admin-filters" method="GET" action="<?= url('admin/uzytkownicy') ?>">
              <div class="admin-filters__grid">
                <label class="admin-field">
                  <span>Szukaj</span>
                  <input type="text" name="q" value="<?= htmlspecialchars((string) ($filters['query'] ?? '')) ?>" placeholder="ID, e-mail, imię">
                </label>

                <label class="admin-field">
                  <span>Rola</span>
                  <select name="role">
                    <option value="">Wszystkie</option>
                    <option value="user" <?= (($filters['role'] ?? '') === 'user') ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= (($filters['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                  </select>
                </label>

                <label class="admin-field">
                  <span>Status</span>
                  <select name="status">
                    <option value="">Wszystkie</option>
                    <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Aktywny</option>
                    <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Nieaktywny</option>
                  </select>
                </label>

                <label class="admin-field">
                  <span>Sortowanie</span>
                  <select name="sort">
                    <option value="id" <?= $sort === 'id' ? 'selected' : '' ?>>ID</option>
                    <option value="email" <?= $sort === 'email' ? 'selected' : '' ?>>E-mail</option>
                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Data rejestracji</option>
                  </select>
                </label>

                <label class="admin-field">
                  <span>Kolejność</span>
                  <select name="dir">
                    <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Malejąco</option>
                    <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Rosnąco</option>
                  </select>
                </label>
              </div>

              <div class="admin-filters__actions">
                <button type="submit" class="btn btn--primary">Filtruj</button>
                <a href="<?= url('admin/uzytkownicy') ?>" class="btn btn--secondary">Wyczyść</a>
              </div>
            </form>
          </div>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-users" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span>Lista użytkowników</span>
              </div>
              <div class="stats-card__header-actions">
                <span class="badge badge--secondary">Wyniki: <?= (int) ($pagination['total'] ?? 0) ?></span>
              </div>
            </div>

            <div class="admin-table admin-table--wide">
              <div class="admin-table__head">
                <a href="<?= htmlspecialchars($sortLink('id')) ?>">ID</a>
                <a href="<?= htmlspecialchars($sortLink('email')) ?>">Użytkownik</a>
                <span>E-mail</span>
                <span>Rola</span>
                <a href="<?= htmlspecialchars($sortLink('created_at')) ?>">Rejestracja</a>
                <span>Aktywność</span>
                <span>Testy</span>
                <span>Akcje</span>
              </div>

              <?php if (empty($users)): ?>
                <div class="admin-empty">
                  Nie znaleziono użytkowników spełniających kryteria.
                </div>
              <?php else: ?>
                <?php foreach ($users as $user): ?>
                  <div class="admin-table__row admin-table__row--grid">
                    <div class="admin-table__cell admin-table__cell--id">
                      <?= (int) $user['id'] ?>
                    </div>
                    <div class="admin-table__cell">
                      <strong><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Brak nazwy') ?></strong>
                      <div class="admin-table__muted">
                        <?= htmlspecialchars($user['auth_provider'] ?? 'local') ?>
                      </div>
                    </div>
                    <div class="admin-table__cell">
                      <?= htmlspecialchars($user['email'] ?? '') ?>
                    </div>
                    <div class="admin-table__cell">
                      <span class="badge <?= (($user['role'] ?? 'user') === 'admin') ? 'badge--accent' : 'badge--secondary' ?>">
                        <?= htmlspecialchars($user['role'] ?? 'user') ?>
                      </span>
                      <div class="admin-table__muted">
                        <?= ((int) ($user['is_active'] ?? 1) === 1 && empty($user['deleted_at'])) ? 'Aktywny' : 'Nieaktywny' ?>
                      </div>
                    </div>
                    <div class="admin-table__cell">
                      <?= !empty($user['created_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $user['created_at']))) : 'Brak' ?>
                    </div>
                    <div class="admin-table__cell">
                      <?= !empty($user['last_activity']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $user['last_activity']))) : 'Brak' ?>
                    </div>
                    <div class="admin-table__cell">
                      <?= (int) ($user['solved_tests'] ?? 0) ?>
                    </div>
                    <div class="admin-table__cell admin-table__actions">
                      <a class="btn btn--secondary btn--sm" href="<?= url('admin/uzytkownicy/' . (int) $user['id']) ?>">Szczegóły</a>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
              <div class="admin-pagination">
                <?php if (($pagination['page'] ?? 1) > 1): ?>
                  <a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl(['page' => (int) $pagination['page'] - 1])) ?>">Poprzednia</a>
                <?php endif; ?>

                <span class="admin-pagination__status">
                  Strona <?= (int) ($pagination['page'] ?? 1) ?> / <?= (int) ($pagination['totalPages'] ?? 1) ?>
                </span>

                <?php if (($pagination['page'] ?? 1) < ($pagination['totalPages'] ?? 1)): ?>
                  <a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl(['page' => (int) $pagination['page'] + 1])) ?>">Następna</a>
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
