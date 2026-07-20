<?php
/**
 * @file exam_form.php
 * @description Formularz tworzenia i edycji egzaminu.
 */

$pageTitle = $pageTitle ?? 'Egzamin | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Formularz zarządzania egzaminem w panelu CMS Zdajnik.';
$mode = $mode ?? 'create';
$exam = $exam ?? [];
$errors = $errors ?? [];
$examId = (int) ($examId ?? ($exam['id'] ?? 0));
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? url('admin/egzaminy/' . $examId . '/edytuj') : url('admin/egzaminy/dodaj');
$title = $isEdit ? 'Edycja egzaminu' : 'Dodawanie egzaminu';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>

<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient"><?= htmlspecialchars($title) ?></span></h1>
        <p class="page-header__text">Zarządzaj kodem, nazwą, opisem i statusem aktywności egzaminu.</p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'exams'; include __DIR__ . '/partials/navigation.php'; ?>

        <div class="admin-content">
          <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
              <ul class="alert__list">
                <?php foreach ($errors as $error): ?>
                  <li class="alert__item"><?= htmlspecialchars((string) $error) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-file-circle-plus"></i>
                <span><?= htmlspecialchars($title) ?></span>
              </div>
              <div class="stats-card__header-actions">
                <?php if ($isEdit && $examId > 0): ?>
                  <a href="<?= url('admin/egzaminy/' . $examId) ?>" class="btn btn--secondary btn--sm">Szczegóły</a>
                <?php endif; ?>
              </div>
            </div>

            <form method="POST" action="<?= htmlspecialchars($actionUrl) ?>" class="admin-filters">
              <?= csrf_field() ?>
              <div class="admin-filters__grid">
                <label class="admin-field">
                  <span>Kod egzaminu</span>
                  <input type="text" name="code" maxlength="50" value="<?= htmlspecialchars((string) ($exam['code'] ?? '')) ?>" placeholder="INF.03">
                </label>

                <label class="admin-field">
                  <span>Nazwa egzaminu</span>
                  <input type="text" name="name" maxlength="200" value="<?= htmlspecialchars((string) ($exam['name'] ?? '')) ?>" placeholder="Tworzenie i administrowanie stronami">
                </label>

                <label class="admin-field admin-field--full">
                  <span>Opis</span>
                  <textarea name="description" rows="6"><?= htmlspecialchars((string) ($exam['description'] ?? '')) ?></textarea>
                </label>

                <label class="admin-field">
                  <span>Status</span>
                  <select name="is_active">
                    <option value="1" <?= ((int) ($exam['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Aktywny</option>
                    <option value="0" <?= ((int) ($exam['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>Nieaktywny</option>
                  </select>
                </label>
              </div>

              <div class="admin-filters__actions">
                <button type="submit" class="btn btn--primary">Zapisz</button>
                <a href="<?= url('admin/egzaminy') ?>" class="back-link"><i class="fas fa-arrow-left"></i> Wróć do listy</a>
              </div>
            </form>
          </div>

          <?php if ($isEdit): ?>
            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-link"></i>
                  <span>Relacja egzaminu</span>
                </div>
              </div>

              <div class="admin-answer-grid">
                <div class="admin-answer-grid__item">
                  <span class="admin-answer-grid__label">Łącznie pytań</span>
                  <strong><?= (int) ($questionsCount ?? 0) ?></strong>
                </div>
                <div class="admin-answer-grid__item">
                  <span class="admin-answer-grid__label">Aktywnych pytań</span>
                  <strong><?= (int) ($activeQuestionsCount ?? 0) ?></strong>
                </div>
                <div class="admin-answer-grid__item">
                  <span class="admin-answer-grid__label">Status</span>
                  <strong><?= ((int) ($exam['is_active'] ?? 1) === 1) ? 'Aktywny' : 'Nieaktywny' ?></strong>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>

</html>
