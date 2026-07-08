<?php
$pageTitle = $pageTitle ?? 'Pytania | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Zarządzanie pytaniami Zdajnik.';
$questions = $questions ?? [];
$filters = $filters ?? [];
$pagination = $pagination ?? ['page' => 1, 'totalPages' => 1];
$sort = $sort ?? 'id';
$dir = $dir ?? 'desc';
$exams = $exams ?? [];
$topics = $topics ?? [];

$buildUrl = static function (array $overrides = []) use ($filters, $sort, $dir): string {
  $params = array_filter(array_merge([
    'q' => $filters['query'] ?? '',
    'exam_id' => $filters['exam_id'] ?? '',
    'topic_id' => $filters['topic_id'] ?? '',
    'status' => $filters['status'] ?? '',
    'sort' => $sort,
    'dir' => $dir,
    'page' => 1,
  ], $overrides), static fn($value) => $value !== null && $value !== '');
  return url('admin/pytania') . '?' . http_build_query($params);
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
        <h1 class="page-header__title"><span class="text-gradient">Pytania</span></h1>
        <p class="page-header__text">Treść, jakość odpowiedzi, wersje i status publikacji.</p>
      </div>
    </header>
    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'questions'; include __DIR__ . '/partials/navigation.php'; ?>
        <div class="admin-content">
          <?php include __DIR__ . '/partials/flash.php'; ?>
          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-filter"></i><span>Filtry</span></div>
              <a href="<?= url('admin/pytania/dodaj') ?>" class="btn btn--primary btn--sm">Dodaj pytanie</a>
            </div>
            <form class="admin-toolbar" method="GET" action="<?= url('admin/pytania') ?>">
              <label class="admin-field admin-field--search"><span>Szukaj</span><input type="text" name="q" value="<?= htmlspecialchars((string) ($filters['query'] ?? '')) ?>" placeholder="ID, treść, egzamin, temat"></label>
              <label class="admin-field"><span>Egzamin</span><select name="exam_id"><option value="">Wszystkie</option><?php foreach ($exams as $exam): ?><option value="<?= (int) $exam['id'] ?>" <?= (string) ($filters['exam_id'] ?? '') === (string) $exam['id'] ? 'selected' : '' ?>><?= htmlspecialchars($exam['code'] . ' - ' . $exam['name']) ?></option><?php endforeach; ?></select></label>
              <label class="admin-field"><span>Temat</span><select name="topic_id"><option value="">Wszystkie</option><?php foreach ($topics as $topic): ?><option value="<?= (int) $topic['id'] ?>" <?= (string) ($filters['topic_id'] ?? '') === (string) $topic['id'] ? 'selected' : '' ?>><?= htmlspecialchars($topic['exam_code'] . ' - ' . $topic['name']) ?></option><?php endforeach; ?></select></label>
              <label class="admin-field"><span>Status</span><select name="status"><option value="">Wszystkie</option><option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktywne</option><option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nieaktywne</option></select></label>
              <label class="admin-field"><span>Sortowanie</span><select name="sort"><option value="id" <?= $sort === 'id' ? 'selected' : '' ?>>ID</option><option value="exam" <?= $sort === 'exam' ? 'selected' : '' ?>>Egzamin</option><option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Utworzenie</option><option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Edycja</option></select></label>
              <label class="admin-field"><span>Kolejność</span><select name="dir"><option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Malejąco</option><option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Rosnąco</option></select></label>
              <div class="admin-toolbar__actions"><button class="btn btn--primary" type="submit">Filtruj</button><a class="btn btn--secondary" href="<?= url('admin/pytania') ?>">Wyczyść</a></div>
            </form>
          </section>

          <section class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title"><i class="fas fa-circle-question"></i><span>Lista pytań</span></div>
              <span class="badge badge--secondary">Wyniki: <?= (int) ($pagination['total'] ?? 0) ?></span>
            </div>
            <div class="admin-table admin-table--questions">
              <div class="admin-table__head"><span>ID</span><span>Egzamin / temat</span><span>Treść</span><span>Jakość</span><span>Status</span><span>Obrazek</span><span>Aktualizacja</span><span>Akcje</span></div>
              <?php if (empty($questions)): ?>
                <div class="admin-empty">Nie znaleziono pytań.</div>
              <?php else: ?>
                <?php foreach ($questions as $question): ?>
                  <?php $questionId = (int) $question['id']; $isActive = (int) ($question['is_active'] ?? 0) === 1; ?>
                  <div class="admin-table__row">
                    <div class="admin-table__cell admin-table__cell--id" data-label="ID"><?= $questionId ?></div>
                    <div class="admin-table__cell admin-truncate" data-label="Egzamin / temat"><strong><?= htmlspecialchars((string) ($question['exam_code'] ?? '')) ?></strong><small title="<?= htmlspecialchars((string) ($question['topic_name'] ?? 'Brak tematu')) ?>"><?= htmlspecialchars((string) ($question['topic_name'] ?? 'Brak tematu')) ?></small></div>
                    <div class="admin-table__cell admin-clamp" data-label="Treść" title="<?= htmlspecialchars((string) ($question['question_text'] ?? '')) ?>"><?= htmlspecialchars((string) ($question['question_text'] ?? 'Brak treści')) ?></div>
                    <div class="admin-table__cell" data-label="Jakość"><strong><?= (int) ($question['answers_count'] ?? 0) ?> odp.</strong><small>Poprawne: <?= (int) ($question['correct_answers_count'] ?? 0) ?></small></div>
                    <div class="admin-table__cell" data-label="Status"><span class="admin-status admin-status--<?= $isActive ? 'success' : 'danger' ?>"><?= $isActive ? 'Aktywne' : 'Nieaktywne' ?></span></div>
                    <div class="admin-table__cell" data-label="Obrazek"><span class="admin-status admin-status--neutral"><?= !empty($question['image_name']) ? 'Tak' : 'Nie' ?></span></div>
                    <div class="admin-table__cell" data-label="Aktualizacja"><?= $date($question['question_updated_at'] ?? $question['version_updated_at'] ?? null) ?></div>
                    <div class="admin-table__cell" data-label="Akcje">
                      <div class="admin-actions">
                        <a class="btn btn--secondary btn--sm" href="<?= url('admin/pytania/' . $questionId) ?>">Szczegóły</a>
                        <a class="btn btn--secondary btn--sm" href="<?= url('admin/pytania/' . $questionId . '/edytuj') ?>">Edytuj</a>
                        <?php if ($isActive): ?>
                          <form method="POST" action="<?= url('admin/pytania/' . $questionId . '/dezaktywuj') ?>"><?= csrf_field() ?><button class="btn btn--danger btn--sm" type="submit" onclick="return confirm('Na pewno dezaktywować to pytanie?')">Dezaktywuj</button></form>
                        <?php else: ?>
                          <form method="POST" action="<?= url('admin/pytania/' . $questionId . '/aktywuj') ?>"><?= csrf_field() ?><button class="btn btn--primary btn--sm" type="submit">Aktywuj</button></form>
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
        </div>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
