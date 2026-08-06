<?php
/**
 * @file exam_detail.php
 * @description Szczegóły egzaminu i lista przypisanych pytań.
 */

$noIndex = true;
$pageTitle = $pageTitle ?? 'Egzamin | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Szczegóły egzaminu w panelu CMS Zdajnik.';
$exam = $exam ?? [];
$questions = $questions ?? [];
$topics = $topics ?? [];
$examId = (int) ($exam['id'] ?? 0);
$isActive = (int) ($exam['is_active'] ?? 0) === 1;
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
          <?php include __DIR__ . '/partials/flash.php'; ?>

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
                <strong class="admin-tile__value"><?= $isActive ? 'Aktywny' : 'Nieaktywny' ?></strong>
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
                <i class="fas fa-info-circle"></i>
                <span>Opis egzaminu</span>
              </div>
              <div class="admin-actions">
                <a href="<?= url('admin/egzaminy/' . $examId . '/edytuj') ?>" class="btn btn--secondary btn--sm">Edytuj</a>
                <a href="<?= url('admin/pytania/dodaj?exam_id=' . $examId) ?>" class="btn btn--primary btn--sm">Dodaj pytanie</a>
                <?php if ($isActive): ?>
                  <form method="POST" action="<?= url('admin/egzaminy/' . $examId . '/dezaktywuj') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Na pewno dezaktywować ten egzamin?')">Dezaktywuj</button>
                  </form>
                <?php else: ?>
                  <form method="POST" action="<?= url('admin/egzaminy/' . $examId . '/aktywuj') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary btn--sm">Aktywuj</button>
                  </form>
                <?php endif; ?>
                <a href="<?= url('admin/egzaminy') ?>" class="back-link"><i class="fas fa-arrow-left"></i> Wróć do listy</a>
              </div>
            </div>

            <div class="admin-lead">
              <?= !empty($exam['description']) ? nl2br(htmlspecialchars((string) $exam['description'])) : 'Brak opisu.' ?>
            </div>
          </div>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-layer-group"></i>
                <span>Tematy egzaminu</span>
              </div>
              <span class="badge badge--secondary">Razem: <?= count($topics) ?></span>
            </div>
            <?php if (empty($topics)): ?>
              <div class="admin-empty">Egzamin nie ma przypisanych tematów.</div>
            <?php else: ?>
              <div class="admin-table admin-table--exam-topics">
                <div class="admin-table__head"><span>Nazwa</span><span>Opis</span><span>Status</span><span>Pytania</span><span>Akcje</span></div>
                <?php foreach ($topics as $topic): ?>
                  <?php $topicId = (int) $topic['id']; $formId = 'exam-topic-form-' . $topicId; ?>
                  <div class="admin-table__row">
                    <form id="<?= $formId ?>" method="POST" action="<?= url('admin/tematy/' . $topicId . '/edytuj') ?>"><?= csrf_field() ?></form>
                    <div class="admin-table__cell" data-label="Nazwa"><input form="<?= $formId ?>" type="text" name="name" maxlength="200" value="<?= htmlspecialchars((string) $topic['name']) ?>" required></div>
                    <div class="admin-table__cell" data-label="Opis"><input form="<?= $formId ?>" type="text" name="description" maxlength="2000" value="<?= htmlspecialchars((string) ($topic['description'] ?? '')) ?>" placeholder="Opis (opcjonalnie)"></div>
                    <div class="admin-table__cell" data-label="Status">
                      <select form="<?= $formId ?>" name="is_active">
                        <option value="1" <?= (int) ($topic['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Aktywny</option>
                        <option value="0" <?= (int) ($topic['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Nieaktywny</option>
                      </select>
                    </div>
                    <div class="admin-table__cell" data-label="Pytania"><a href="<?= url('admin/pytania?exam_id=' . $examId . '&topic_id=' . $topicId) ?>"><?= (int) ($topic['questions_count'] ?? 0) ?> (aktywnych: <?= (int) ($topic['active_questions_count'] ?? 0) ?>)</a></div>
                    <div class="admin-table__cell" data-label="Akcje"><div class="admin-actions"><button form="<?= $formId ?>" class="btn btn--secondary btn--sm" type="submit">Zapisz</button></div></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-list-check"></i>
                <span>Ostatnio edytowane pytania</span>
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
                  <a class="admin-table__row admin-table__row--stacked admin-table__row--link" href="<?= url('admin/pytania/' . (int) $question['id']) ?>">
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
                  </a>
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
