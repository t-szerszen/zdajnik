<?php
/**
 * @file exam_detail.php
 * @description Szczegóły egzaminu i lista przypisanych pytań.
 */

$pageTitle = $pageTitle ?? 'Egzamin | Admin Panel | Examly';
$pageDescription = $pageDescription ?? 'Szczegóły egzaminu w panelu CMS Examly.';
$exam = $exam ?? [];
$questions = $questions ?? [];
$examId = (int) ($exam['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>

<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient"><?= htmlspecialchars((string) ($exam['code'] ?? 'Egzamin')) ?></span></h1>
        <p class="page-header__text"><?= htmlspecialchars((string) ($exam['name'] ?? '')) ?></p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'exams'; include __DIR__ . '/partials/navigation.php'; ?>

        <div class="admin-content">
          <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert--<?= htmlspecialchars($_SESSION['flash_message']['type']) ?>">
              <?= htmlspecialchars((string) ($_SESSION['flash_message']['text'] ?? '')) ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
          <?php endif; ?>

          <div class="admin-grid admin-grid--compact">
            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-hashtag"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Kod</span>
                <strong class="admin-tile__value"><?= htmlspecialchars((string) ($exam['code'] ?? '')) ?></strong>
                <span class="admin-tile__meta">ID #<?= $examId ?></span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-puzzle-piece"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Pytania</span>
                <strong class="admin-tile__value"><?= (int) ($exam['questions_count'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Aktywnych: <?= (int) ($exam['active_questions_count'] ?? 0) ?></span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-toggle-on"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Status</span>
                <strong class="admin-tile__value"><?= ((int) ($exam['is_active'] ?? 1) === 1) ? 'Aktywny' : 'Nieaktywny' ?></strong>
                <span class="admin-tile__meta">
                  Utworzono: <?= !empty($exam['created_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $exam['created_at']))) : 'Brak' ?>
                </span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-pen-nib"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Aktualizacja</span>
                <strong class="admin-tile__value">
                  <?= !empty($exam['updated_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $exam['updated_at']))) : 'Brak' ?>
                </strong>
                <span class="admin-tile__meta">Ostatnia modyfikacja rekordu</span>
              </div>
            </div>
          </div>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-info-circle" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span>Opis egzaminu</span>
              </div>
              <div class="stats-card__header-actions" style="display:flex; flex-wrap:wrap; gap:.5rem;">
                <a href="<?= url('admin/egzaminy/' . $examId . '/edytuj') ?>" class="btn btn--secondary btn--sm">Edytuj</a>
                <?php if ((int) ($exam['is_active'] ?? 1) === 1): ?>
                  <form method="POST" action="<?= url('admin/egzaminy/' . $examId . '/dezaktywuj') ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Na pewno dezaktywować ten egzamin?')">Dezaktywuj</button>
                  </form>
                <?php endif; ?>
                <a href="<?= url('admin/egzaminy') ?>" class="btn btn--secondary btn--sm">Wróć do listy</a>
              </div>
            </div>

            <div class="admin-lead">
              <?= !empty($exam['description']) ? nl2br(htmlspecialchars((string) $exam['description'])) : 'Brak opisu.' ?>
            </div>
          </div>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-list-check" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span>Pytania przypisane do egzaminu</span>
              </div>
              <div class="stats-card__header-actions">
                <span class="badge badge--secondary">Razem: <?= count($questions) ?></span>
              </div>
            </div>

            <div class="admin-table admin-table--compact">
              <?php if (empty($questions)): ?>
                <div class="admin-empty">Ten egzamin nie ma jeszcze przypisanych pytań.</div>
              <?php else: ?>
                <?php foreach ($questions as $question): ?>
                  <div class="admin-table__row admin-table__row--stacked">
                    <div>
                      <strong>#<?= (int) $question['id'] ?></strong>
                      <div class="admin-table__muted">
                        <?= htmlspecialchars((string) ($question['topic_name'] ?? 'Bez tematu')) ?>
                        |
                        Wersji: <?= (int) ($question['version_count'] ?? 0) ?>
                      </div>
                    </div>

                    <div class="admin-answer-grid">
                      <div class="admin-answer-grid__item">
                        <span class="admin-answer-grid__label">Treść pytania</span>
                        <span><?= htmlspecialchars(mb_strimwidth((string) ($question['question_text'] ?? ''), 0, 140, '...')) ?></span>
                      </div>
                      <div class="admin-answer-grid__item">
                        <span class="admin-answer-grid__label">Status pytania</span>
                        <span class="badge <?= ((int) ($question['is_active'] ?? 1) === 1) ? 'badge--accent' : 'badge--secondary' ?>">
                          <?= ((int) ($question['is_active'] ?? 1) === 1) ? 'Aktywne' : 'Nieaktywne' ?>
                        </span>
                      </div>
                      <div class="admin-answer-grid__item">
                        <span class="admin-answer-grid__label">Data utworzenia</span>
                        <span><?= !empty($question['created_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $question['created_at']))) : 'Brak' ?></span>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>

</html>
