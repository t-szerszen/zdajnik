<?php
/**
 * @file exams.php
 * @description
 * Lista egzaminów w panelu CMS.
 */

$pageTitle = $pageTitle ?? 'Egzaminy | Panel administracyjny | Examly';
$pageDescription = $pageDescription ?? 'Zarządzanie egzaminami w panelu CMS Examly.';
$exams = $exams ?? [];
$filters = $filters ?? [];
$pagination = $pagination ?? ['page' => 1, 'totalPages' => 1];
$sort = $sort ?? 'id';
$dir = $dir ?? 'desc';

$buildUrl = static function (array $overrides = []) use ($filters, $sort, $dir): string {
  $params = array_filter([
    'q' => $filters['query'] ?? '',
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

  return url('admin/egzaminy') . '?' . http_build_query($params);
};

$sortLink = static function (string $column) use ($filters, $sort, $dir): string {
  $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
  return url('admin/egzaminy') . '?' . http_build_query(array_filter([
    'q' => $filters['query'] ?? '',
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
        <h1 class="page-header__title"><span class="text-gradient">Egzaminy</span></h1>
        <p class="page-header__text">Dodawaj, edytuj i dezaktywuj egzaminy oraz podglądaj liczbę przypisanych pytań.</p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'exams'; include __DIR__ . '/partials/navigation.php'; ?>

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
              <div class="stats-card__header-actions">
                <a href="<?= url('admin/egzaminy/dodaj') ?>" class="btn btn--primary btn--sm">Dodaj egzamin</a>
              </div>
            </div>

            <form class="admin-filters" method="GET" action="<?= url('admin/egzaminy') ?>">
              <div class="admin-filters__grid">
                <label class="admin-field">
                  <span>Szukaj</span>
                  <input type="text" name="q" value="<?= htmlspecialchars((string) ($filters['query'] ?? '')) ?>" placeholder="ID, kod, nazwa, opis">
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
                    <option value="code" <?= $sort === 'code' ? 'selected' : '' ?>>Kod</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nazwa</option>
                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Data utworzenia</option>
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
                <a href="<?= url('admin/egzaminy') ?>" class="btn btn--secondary">Wyczyść</a>
              </div>
            </form>
          </div>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-file-lines" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span>Lista egzaminów</span>
              </div>
              <div class="stats-card__header-actions">
                <span class="badge badge--secondary">Wyniki: <?= (int) ($pagination['total'] ?? 0) ?></span>
              </div>
            </div>

            <div class="admin-table admin-table--wide">
              <div class="admin-table__head" style="grid-template-columns: 70px 1.2fr 1.6fr 150px 130px 160px 140px;">
                <a href="<?= htmlspecialchars($sortLink('id')) ?>">ID</a>
                <a href="<?= htmlspecialchars($sortLink('code')) ?>">Kod / egzamin</a>
                <span>Tytuł / opis</span>
                <span>Pytania</span>
                <span>Status</span>
                <a href="<?= htmlspecialchars($sortLink('created_at')) ?>">Utworzenie</a>
                <span>Akcje</span>
              </div>

              <?php if (empty($exams)): ?>
                <div class="admin-empty">
                  Nie znaleziono egzaminów spełniających kryteria.
                </div>
              <?php else: ?>
                <?php foreach ($exams as $exam): ?>
                  <div class="admin-table__row admin-table__row--grid" style="grid-template-columns: 70px 1.2fr 1.6fr 150px 130px 160px 140px;">
                    <div class="admin-table__cell admin-table__cell--id">
                      <?= (int) $exam['id'] ?>
                    </div>

                    <div class="admin-table__cell">
                      <strong><?= htmlspecialchars((string) ($exam['code'] ?? '')) ?></strong>
                      <div class="admin-table__muted">
                        <?= htmlspecialchars((string) ($exam['name'] ?? '')) ?>
                      </div>
                    </div>

                    <div class="admin-table__cell">
                      <?= !empty($exam['description']) ? htmlspecialchars(mb_strimwidth((string) $exam['description'], 0, 120, '...')) : 'Brak opisu' ?>
                    </div>

                    <div class="admin-table__cell">
                      <strong><?= (int) ($exam['questions_count'] ?? 0) ?></strong>
                      <div class="admin-table__muted">
                        Aktywnych: <?= (int) ($exam['active_questions_count'] ?? 0) ?>
                      </div>
                    </div>

                    <div class="admin-table__cell">
                      <span class="badge <?= ((int) ($exam['is_active'] ?? 1) === 1) ? 'badge--accent' : 'badge--secondary' ?>">
                        <?= ((int) ($exam['is_active'] ?? 1) === 1) ? 'Aktywny' : 'Nieaktywny' ?>
                      </span>
                    </div>

                    <div class="admin-table__cell">
                      <?= !empty($exam['created_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $exam['created_at']))) : 'Brak' ?>
                    </div>

                    <div class="admin-table__cell admin-table__actions">
                      <div style="display:flex; flex-wrap:wrap; gap:.5rem; justify-content:flex-end;">
                        <a class="btn btn--secondary btn--sm" href="<?= url('admin/egzaminy/' . (int) $exam['id']) ?>">Szczegóły</a>
                        <a class="btn btn--secondary btn--sm" href="<?= url('admin/egzaminy/' . (int) $exam['id'] . '/edytuj') ?>">Edytuj</a>
                        <?php if ((int) ($exam['is_active'] ?? 1) === 1): ?>
                          <form method="POST" action="<?= url('admin/egzaminy/' . (int) $exam['id'] . '/dezaktywuj') ?>" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Na pewno dezaktywować ten egzamin?')">
                              Dezaktywuj
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
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
