<?php
/**
 * @file sheets.php (admin)
 * @description Zarządzanie Biblioteką Arkuszy: lista arkuszy z filtrami oraz tematy arkuszy.
 */

$noIndex = true;
$pageTitle = $pageTitle ?? 'Arkusze | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Zarządzanie biblioteką arkuszy Zdajnik.';
$sheets = $sheets ?? [];
$sheetTopics = $sheetTopics ?? [];
$examTypes = $examTypes ?? [];
$filters = $filters ?? [];
$pagination = $pagination ?? ['page' => 1, 'totalPages' => 1];

$buildUrl = static function (array $overrides = []) use ($filters): string {
  $params = array_filter(array_merge([
    'q' => $filters['query'] ?? '',
    'topic_id' => $filters['topic_id'] ?? '',
    'exam_id' => $filters['exam_id'] ?? '',
    'status' => $filters['status'] ?? '',
    'page' => 1,
  ], $overrides), static fn($value) => $value !== null && $value !== '');
  return url('admin/arkusze') . '?' . http_build_query($params);
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
        <h1 class="page-header__title"><span class="text-gradient">Arkusze</span></h1>
        <p class="page-header__text">Biblioteka arkuszy: tematy, poziomy trudności oraz pliki PDF i ZIP.</p>
      </div>
    </header>
    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'sheets'; include __DIR__ . '/partials/navigation.php'; ?>
        <div class="admin-content">
          <?php include __DIR__ . '/partials/flash.php'; ?>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-filter"></i><span>Filtry</span></div>
              <a href="<?= url('admin/arkusze/dodaj') ?>" class="btn btn--primary btn--sm">Dodaj arkusz</a>
            </div>
            <form class="admin-toolbar" method="GET" action="<?= url('admin/arkusze') ?>">
              <label class="admin-field admin-field--search"><span>Szukaj</span><input type="text" name="q" value="<?= htmlspecialchars((string) ($filters['query'] ?? '')) ?>" placeholder="ID, tytuł lub opis"></label>
              <label class="admin-field"><span>Kwalifikacja</span><select name="exam_id"><option value="">Wszystkie</option><?php foreach ($examTypes as $examType): ?><option value="<?= (int) $examType['id'] ?>" <?= (string) ($filters['exam_id'] ?? '') === (string) $examType['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $examType['code']) ?></option><?php endforeach; ?></select></label>
              <label class="admin-field"><span>Temat</span><select name="topic_id"><option value="">Wszystkie</option><?php foreach ($sheetTopics as $topic): ?><option value="<?= (int) $topic['id'] ?>" <?= (string) ($filters['topic_id'] ?? '') === (string) $topic['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $topic['exam_code'] . ' — ' . (string) $topic['name']) ?></option><?php endforeach; ?></select></label>
              <label class="admin-field"><span>Status</span><select name="status"><option value="">Wszystkie</option><option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktywne</option><option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nieaktywne</option></select></label>
              <div class="admin-toolbar__actions"><button class="btn btn--primary" type="submit">Filtruj</button><a class="btn btn--secondary" href="<?= url('admin/arkusze') ?>">Wyczyść</a></div>
            </form>
          </section>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-file-pdf"></i><span>Lista arkuszy</span></div>
              <span class="badge badge--secondary">Wyniki: <?= (int) ($pagination['total'] ?? 0) ?></span>
            </div>
            <div class="admin-table admin-table--sheets">
              <div class="admin-table__head"><span>ID</span><span>Arkusz</span><span>Temat</span><span>Trudność</span><span>Pliki</span><span>Status</span><span>Aktualizacja</span><span>Akcje</span></div>
              <?php if (empty($sheets)): ?>
                <div class="admin-empty">Nie znaleziono arkuszy.</div>
              <?php else: ?>
                <?php foreach ($sheets as $sheet): ?>
                  <?php $sheetId = (int) $sheet['id']; $isActive = (int) ($sheet['is_active'] ?? 0) === 1; ?>
                  <div class="admin-table__row">
                    <div class="admin-table__cell admin-table__cell--id" data-label="ID"><?= $sheetId ?></div>
                    <div class="admin-table__cell admin-truncate" data-label="Arkusz" title="<?= htmlspecialchars((string) ($sheet['title'] ?? '')) ?>"><strong><?= htmlspecialchars((string) ($sheet['title'] ?? '')) ?></strong><small><?= htmlspecialchars((string) ($sheet['description'] ?: 'Brak opisu')) ?></small></div>
                    <div class="admin-table__cell" data-label="Temat"><strong><?= htmlspecialchars((string) ($sheet['exam_code'] ?? '')) ?></strong><small><?= htmlspecialchars((string) ($sheet['topic_name'] ?? '')) ?></small></div>
                    <div class="admin-table__cell" data-label="Trudność"><strong><?= (int) $sheet['difficulty'] ?>/10</strong></div>
                    <div class="admin-table__cell" data-label="Pliki"><span class="badge badge--accent">PDF</span> <?= !empty($sheet['zip_path']) ? '<span class="badge badge--secondary">ZIP</span>' : '' ?><?= !empty($sheet['resources_path']) ? ' <span class="badge badge--secondary">Zasoby</span>' : '' ?><?= !empty($sheet['thumbnail_path']) ? ' <span class="badge badge--secondary">Miniat.</span>' : '' ?></div>
                    <div class="admin-table__cell" data-label="Status"><span class="admin-status admin-status--<?= $isActive ? 'success' : 'danger' ?>"><?= $isActive ? 'Aktywny' : 'Nieaktywny' ?></span></div>
                    <div class="admin-table__cell" data-label="Aktualizacja"><?= $date($sheet['updated_at'] ?? null) ?></div>
                    <div class="admin-table__cell" data-label="Akcje">
                      <div class="admin-actions">
                        <a class="btn btn--secondary btn--sm" href="<?= url('arkusze/' . rawurlencode((string) ($sheet['exam_code'] ?? '')) . '/' . $sheetId) ?>">Podgląd</a>
                        <a class="btn btn--secondary btn--sm" href="<?= url('admin/arkusze/' . $sheetId . '/edytuj') ?>">Edytuj</a>
                        <?php if ($isActive): ?>
                          <form method="POST" action="<?= url('admin/arkusze/' . $sheetId . '/dezaktywuj') ?>"><?= csrf_field() ?><button class="btn btn--danger btn--sm" type="submit" onclick="return confirm('Na pewno dezaktywować ten arkusz?')">Dezaktywuj</button></form>
                        <?php else: ?>
                          <form method="POST" action="<?= url('admin/arkusze/' . $sheetId . '/aktywuj') ?>"><?= csrf_field() ?><button class="btn btn--primary btn--sm" type="submit">Aktywuj</button></form>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
              <div class="admin-pagination"><span class="admin-pagination__status">Strona <?= (int) $pagination['page'] ?> / <?= (int) $pagination['totalPages'] ?></span><div class="admin-actions"><?php if ($pagination['page'] > 1): ?><a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl(['page' => $pagination['page'] - 1])) ?>">Poprzednia</a><?php endif; ?><?php if ($pagination['page'] < $pagination['totalPages']): ?><a class="btn btn--secondary btn--sm" href="<?= htmlspecialchars($buildUrl(['page' => $pagination['page'] + 1])) ?>">Następna</a><?php endif; ?></div></div>
            <?php endif; ?>
          </section>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-tags"></i><span>Tematy arkuszy</span></div>
              <span class="badge badge--secondary">Łącznie: <?= count($sheetTopics) ?></span>
            </div>

            <div class="admin-table admin-table--sheet-topics">
              <div class="admin-table__head"><span>Kwalifikacja</span><span>Nazwa</span><span>Opis</span><span>Kolejność</span><span>Status</span><span>Arkusze</span><span>Akcje</span></div>
              <?php if (empty($sheetTopics)): ?>
                <div class="admin-empty">Brak tematów. Dodaj pierwszy temat poniżej.</div>
              <?php else: ?>
                <?php foreach ($sheetTopics as $topic): ?>
                  <?php $topicId = (int) $topic['id']; $formId = 'sheet-topic-form-' . $topicId; ?>
                  <div class="admin-table__row">
                    <form id="<?= $formId ?>" method="POST" action="<?= url('admin/arkusze/tematy/' . $topicId . '/edytuj') ?>"><?= csrf_field() ?></form>
                    <div class="admin-table__cell" data-label="Kwalifikacja">
                      <select form="<?= $formId ?>" name="exam_type_id">
                        <?php foreach ($examTypes as $examType): ?>
                          <option value="<?= (int) $examType['id'] ?>" <?= (int) $topic['exam_type_id'] === (int) $examType['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $examType['code']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="admin-table__cell" data-label="Nazwa"><input form="<?= $formId ?>" type="text" name="name" maxlength="200" value="<?= htmlspecialchars((string) $topic['name']) ?>"></div>
                    <div class="admin-table__cell" data-label="Opis"><input form="<?= $formId ?>" type="text" name="description" maxlength="2000" value="<?= htmlspecialchars((string) ($topic['description'] ?? '')) ?>" placeholder="Opis (opcjonalnie)"></div>
                    <div class="admin-table__cell" data-label="Kolejność"><input form="<?= $formId ?>" type="number" name="display_order" min="0" value="<?= (int) $topic['display_order'] ?>"></div>
                    <div class="admin-table__cell" data-label="Status"><select form="<?= $formId ?>" name="is_active"><option value="1" <?= (int) $topic['is_active'] === 1 ? 'selected' : '' ?>>Aktywny</option><option value="0" <?= (int) $topic['is_active'] === 0 ? 'selected' : '' ?>>Nieaktywny</option></select></div>
                    <div class="admin-table__cell" data-label="Arkusze"><strong><?= (int) ($topic['sheets_count'] ?? 0) ?></strong></div>
                    <div class="admin-table__cell" data-label="Akcje"><div class="admin-actions"><button form="<?= $formId ?>" class="btn btn--secondary btn--sm" type="submit">Zapisz</button></div></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <form class="admin-toolbar" method="POST" action="<?= url('admin/arkusze/tematy/dodaj') ?>">
              <?= csrf_field() ?>
              <label class="admin-field"><span>Kwalifikacja</span>
                <select name="exam_type_id">
                  <option value="">Wybierz</option>
                  <?php foreach ($examTypes as $examType): ?>
                    <option value="<?= (int) $examType['id'] ?>"><?= htmlspecialchars((string) $examType['code']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="admin-field admin-field--search"><span>Nowy temat</span><input type="text" name="name" maxlength="200" placeholder="Np. Bazy danych" required></label>
              <label class="admin-field"><span>Opis</span><input type="text" name="description" maxlength="2000" placeholder="Opcjonalny opis"></label>
              <label class="admin-field"><span>Kolejność</span><input type="number" name="display_order" min="0" value="0"></label>
              <div class="admin-toolbar__actions"><button class="btn btn--primary" type="submit">Dodaj temat</button></div>
            </form>
          </section>
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
