<?php
/**
 * @file sheet_form.php
 * @description Formularz tworzenia i edycji arkusza (Biblioteka Arkuszy).
 */

$noIndex = true;
$pageTitle = $pageTitle ?? 'Arkusz | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Formularz zarządzania arkuszem w panelu CMS Zdajnik.';
$mode = $mode ?? 'create';
$sheet = $sheet ?? [];
$sheetTopics = $sheetTopics ?? [];
$errors = $errors ?? [];
$sheetId = (int) ($sheetId ?? ($sheet['id'] ?? 0));
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? url('admin/arkusze/' . $sheetId . '/edytuj') : url('admin/arkusze/dodaj');
$title = $isEdit ? 'Edycja arkusza' : 'Dodawanie arkusza';
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
        <p class="page-header__text">Zarządzaj tematem, tytułem, poziomem trudności oraz plikami PDF i ZIP arkusza.</p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'sheets'; include __DIR__ . '/partials/navigation.php'; ?>

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
                <?php if ($isEdit && $sheetId > 0 && !empty($sheet['exam_code'])): ?>
                  <a href="<?= url('arkusze/' . rawurlencode((string) $sheet['exam_code']) . '/' . $sheetId) ?>" class="btn btn--secondary btn--sm">Podgląd</a>
                <?php endif; ?>
              </div>
            </div>

            <form method="POST" action="<?= htmlspecialchars($actionUrl) ?>" class="admin-filters" enctype="multipart/form-data">
              <?= csrf_field() ?>
              <div class="admin-filters__grid">
                <label class="admin-field">
                  <span>Temat (kwalifikacja — temat)</span>
                  <select name="sheet_topic_id">
                    <option value="">Wybierz temat</option>
                    <?php foreach ($sheetTopics as $topic): ?>
                      <option value="<?= (int) $topic['id'] ?>" <?= (int) ($sheet['sheet_topic_id'] ?? 0) === (int) $topic['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $topic['exam_code'] . ' — ' . (string) $topic['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <label class="admin-field">
                  <span>Tytuł arkusza</span>
                  <input type="text" name="title" maxlength="200" value="<?= htmlspecialchars((string) ($sheet['title'] ?? '')) ?>" placeholder="Np. Arkusz INF.03 — czerwiec 2025">
                </label>

                <label class="admin-field">
                  <span>Poziom trudności (1-10)</span>
                  <select name="difficulty">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                      <option value="<?= $i ?>" <?= (int) ($sheet['difficulty'] ?? 5) === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                  </select>
                </label>

                <label class="admin-field">
                  <span>Status</span>
                  <select name="is_active">
                    <option value="1" <?= ((int) ($sheet['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Aktywny</option>
                    <option value="0" <?= ((int) ($sheet['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>Nieaktywny</option>
                  </select>
                </label>

                <label class="admin-field admin-field--full">
                  <span>Opis (opcjonalnie)</span>
                  <textarea name="description" rows="4"><?= htmlspecialchars((string) ($sheet['description'] ?? '')) ?></textarea>
                </label>

                <label class="admin-field">
                  <span>Plik PDF arkusza<?= $isEdit ? ' (zostaw puste, aby zachować obecny)' : '' ?></span>
                  <input type="file" name="pdf_file" accept="application/pdf">
                  <?php if ($isEdit && !empty($sheet['pdf_path'])): ?>
                    <small>Obecny plik: <?= htmlspecialchars((string) $sheet['pdf_path']) ?></small>
                  <?php endif; ?>
                </label>

                <label class="admin-field">
                  <span>Przykładowe rozwiązanie ZIP (opcjonalnie)</span>
                  <input type="file" name="zip_file" accept=".zip,application/zip">
                  <?php if ($isEdit): ?>
                    <small><?= !empty($sheet['zip_path']) ? 'Obecny plik: ' . htmlspecialchars((string) $sheet['zip_path']) : 'Brak pliku ZIP.' ?></small>
                  <?php endif; ?>
                </label>

                <label class="admin-field">
                  <span>Miniaturka arkusza (opcjonalnie)</span>
                  <input type="file" name="thumbnail_file" accept="image/jpeg,image/png,image/webp">
                  <?php if ($isEdit): ?>
                    <small><?= !empty($sheet['thumbnail_path']) ? 'Obecny plik: ' . htmlspecialchars((string) $sheet['thumbnail_path']) : 'Brak miniaturki.' ?></small>
                  <?php endif; ?>
                </label>

                <label class="admin-field">
                  <span>Zasoby do egzaminu ZIP (opcjonalnie)</span>
                  <input type="file" name="resources_file" accept=".zip,application/zip">
                  <?php if ($isEdit): ?>
                    <small><?= !empty($sheet['resources_path']) ? 'Obecny plik: ' . htmlspecialchars((string) $sheet['resources_path']) : 'Brak pliku zasobów.' ?></small>
                  <?php endif; ?>
                </label>
              </div>

              <div class="admin-filters__actions">
                <button type="submit" class="btn btn--primary">Zapisz</button>
                <a href="<?= url('admin/arkusze') ?>" class="back-link"><i class="fas fa-arrow-left"></i> Wróć do listy</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>

</html>
