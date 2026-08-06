<?php
$noIndex = true;
$pageTitle = $pageTitle ?? 'Egzaminy | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Zarządzanie egzaminami Zdajnik.';
$exams = $exams ?? [];
$filters = $filters ?? [];
$pagination = $pagination ?? ['page' => 1, 'totalPages' => 1];
$sort = $sort ?? 'id';
$dir = $dir ?? 'desc';

$buildUrl = static function (array $overrides = []) use ($filters, $sort, $dir): string {
  $params = array_filter(array_merge([
    'q' => $filters['query'] ?? '',
    'status' => $filters['status'] ?? '',
    'sort' => $sort,
    'dir' => $dir,
    'page' => 1,
  ], $overrides), static fn($value) => $value !== null && $value !== '');
  return url('admin/egzaminy') . '?' . http_build_query($params);
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
        <h1 class="page-header__title"><span class="text-gradient">Egzaminy</span></h1>
        <p class="page-header__text">Statusy egzaminów, liczba pytań i szybkie przejście do edycji treści.</p>
      </div>
    </header>
    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'exams'; include __DIR__ . '/partials/navigation.php'; ?>
        <div class="admin-content"
          data-admin-table-root
          data-endpoint="<?= url('admin/egzaminy/api') ?>"
          data-page="<?= (int) ($pagination['page'] ?? 1) ?>"
          data-total-pages="<?= (int) ($pagination['totalPages'] ?? 1) ?>">
          <?php include __DIR__ . '/partials/flash.php'; ?>
          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-filter"></i><span>Filtry</span></div>
              <a href="<?= url('admin/egzaminy/dodaj') ?>" class="btn btn--primary btn--sm">Dodaj egzamin</a>
            </div>
            <form class="admin-toolbar" method="GET" action="<?= url('admin/egzaminy') ?>" data-admin-filter-form>
              <label class="admin-field admin-field--search"><span>Szukaj</span><input type="text" name="q" value="<?= htmlspecialchars((string) ($filters['query'] ?? '')) ?>" placeholder="ID, kod, nazwa lub opis"></label>
              <label class="admin-field"><span>Status</span><select name="status"><option value="">Wszystkie</option><option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktywne</option><option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nieaktywne</option></select></label>
              <label class="admin-field"><span>Sortowanie</span><select name="sort"><option value="id" <?= $sort === 'id' ? 'selected' : '' ?>>ID</option><option value="code" <?= $sort === 'code' ? 'selected' : '' ?>>Kod</option><option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nazwa</option><option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Utworzenie</option></select></label>
              <label class="admin-field"><span>Kolejność</span><select name="dir"><option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Malejąco</option><option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Rosnąco</option></select></label>
              <div class="admin-toolbar__actions"><button class="btn btn--primary" type="submit">Filtruj</button><a class="btn btn--secondary" href="<?= url('admin/egzaminy') ?>" data-admin-reset>Wyczyść</a><span class="admin-analytics__request-status hidden" data-admin-status><span class="admin-analytics__spinner"></span>Wczytywanie…</span></div>
            </form>
          </section>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-file-lines"></i><span>Lista egzaminów</span></div>
              <span class="badge badge--secondary" data-admin-total>Wyniki: <?= (int) ($pagination['total'] ?? 0) ?></span>
            </div>
            <div class="admin-table admin-table--exams">
              <div class="admin-table__head"><span>ID</span><span>Egzamin</span><span>Opis</span><span>Pytania</span><span>Status</span><span>Aktualizacja</span><span>Akcje</span></div>
              <div class="admin-table__body" data-admin-rows>
                <?php include __DIR__ . '/partials/exams_rows.php'; ?>
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
