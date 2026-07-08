<?php
/**
 * @file question_detail.php
 * @description Szczegóły pytania z wersjonowaniem.
 */

$pageTitle = $pageTitle ?? 'Pytanie | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Szczegóły pytania w panelu CMS Zdajnik.';
$question = $question ?? [];
$questionId = (int) ($question['id'] ?? 0);
$currentVersion = is_array($question['current_version'] ?? null) ? $question['current_version'] : [];
$answers = $question['answers'] ?? [];
$versions = $question['versions'] ?? [];
$correctAnswersCount = 0;
foreach ($answers as $answer) {
  if ((int) ($answer['is_correct'] ?? 0) === 1) {
    $correctAnswersCount++;
  }
}
$isActive = (int) ($question['is_active'] ?? 0) === 1;
$hasQualityWarning = count($answers) < 2 || $correctAnswersCount === 0;
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>

<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient">Pytanie #<?= $questionId ?></span></h1>
        <p class="page-header__text">
          <?= htmlspecialchars((string) ($question['exam_code'] ?? '')) ?>
          -
          <?= htmlspecialchars((string) ($question['exam_name'] ?? '')) ?>
        </p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'questions'; include __DIR__ . '/partials/navigation.php'; ?>

        <div class="admin-content">
          <?php include __DIR__ . '/partials/flash.php'; ?>

          <div class="admin-grid admin-grid--compact">
            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-book"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Egzamin</span>
                <strong class="admin-tile__value"><?= htmlspecialchars((string) ($question['exam_code'] ?? '')) ?></strong>
                <span class="admin-tile__meta"><?= htmlspecialchars((string) ($question['topic_name'] ?? 'Brak tematu')) ?></span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-toggle-on"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Status</span>
                <strong class="admin-tile__value"><?= $isActive ? 'Aktywne' : 'Nieaktywne' ?></strong>
                <span class="admin-tile__meta">Wersji: <?= count($versions) ?></span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-image"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Obrazek</span>
                <strong class="admin-tile__value"><?= !empty($currentVersion['image_name']) ? 'Tak' : 'Nie' ?></strong>
                <span class="admin-tile__meta">Aktualna wersja: v<?= (int) ($currentVersion['version_num'] ?? 0) ?></span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-list"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Odpowiedzi</span>
                <strong class="admin-tile__value"><?= count($answers) ?></strong>
                <span class="admin-tile__meta">Poprawnych: <?= $correctAnswersCount ?></span>
              </div>
            </div>
          </div>

          <?php if ($hasQualityWarning): ?>
            <div class="alert alert--error">
              Rekord wymaga uwagi:
              <?= count($answers) < 2 ? 'pytanie ma mniej niż 2 odpowiedzi.' : '' ?>
              <?= $correctAnswersCount === 0 ? 'Brakuje poprawnej odpowiedzi.' : '' ?>
            </div>
          <?php endif; ?>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-align-left"></i>
                <span>Aktualna treść pytania</span>
              </div>
              <div class="admin-actions">
                <a href="<?= url('admin/pytania/' . $questionId . '/edytuj') ?>" class="btn btn--secondary btn--sm">Edytuj</a>
                <?php if ($isActive): ?>
                  <form method="POST" action="<?= url('admin/pytania/' . $questionId . '/dezaktywuj') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--danger btn--sm" onclick="return confirm('Na pewno dezaktywować to pytanie?')">Dezaktywuj</button>
                  </form>
                <?php else: ?>
                  <form method="POST" action="<?= url('admin/pytania/' . $questionId . '/aktywuj') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary btn--sm">Aktywuj</button>
                  </form>
                <?php endif; ?>
                <a href="<?= url('admin/pytania') ?>" class="btn btn--secondary btn--sm">Wróć do listy</a>
              </div>
            </div>

            <div class="admin-question-detail">
              <div class="admin-question-detail__meta">
                <span class="badge badge--secondary">Wersja v<?= (int) ($currentVersion['version_num'] ?? 0) ?></span>
                <span class="badge <?= ((int) ($currentVersion['is_active'] ?? 1) === 1) ? 'badge--accent' : 'badge--secondary' ?>">
                  <?= ((int) ($currentVersion['is_active'] ?? 1) === 1) ? 'Aktywna wersja' : 'Nieaktywna wersja' ?>
                </span>
              </div>

              <div class="admin-lead">
                <?= nl2br(htmlspecialchars((string) ($currentVersion['question_text'] ?? 'Brak treści.'))) ?>
              </div>

              <?php if (!empty($currentVersion['explanation'])): ?>
                <div class="admin-question-detail__box">
                  <strong>Wyjaśnienie</strong>
                  <p><?= nl2br(htmlspecialchars((string) $currentVersion['explanation'])) ?></p>
                </div>
              <?php endif; ?>

              <?php if (!empty($currentVersion['image_name'])): ?>
                <div class="admin-question-image admin-question-image--large">
                  <img src="<?= htmlspecialchars(url('images/questions/' . $currentVersion['image_name'])) ?>" alt="Obrazek pytania">
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-rectangle-list"></i>
                <span>Odpowiedzi aktualnej wersji</span>
              </div>
            </div>

            <div class="admin-table admin-table--compact">
              <?php if (empty($answers)): ?>
                <div class="admin-empty">Brak odpowiedzi w aktualnej wersji pytania.</div>
              <?php else: ?>
                <?php foreach ($answers as $index => $answer): ?>
                  <div class="admin-table__row admin-table__row--stacked">
                    <div>
                      <strong><?= chr(65 + $index) ?>.</strong>
                      <span><?= htmlspecialchars((string) ($answer['answer_text'] ?? '')) ?></span>
                    </div>
                    <div class="admin-table__meta">
                      <span class="badge <?= ((int) ($answer['is_correct'] ?? 0) === 1) ? 'badge--accent' : 'badge--secondary' ?>">
                        <?= ((int) ($answer['is_correct'] ?? 0) === 1) ? 'Poprawna' : 'Błędna' ?>
                      </span>
                      <span class="badge badge--secondary">Kolejność: <?= (int) ($answer['answer_order'] ?? 0) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-history"></i>
                <span>Historia wersji</span>
              </div>
            </div>

            <div class="admin-audit">
              <?php if (empty($versions)): ?>
                <div class="admin-empty">Brak historii wersji.</div>
              <?php else: ?>
                <?php foreach ($versions as $version): ?>
                  <article class="admin-audit__item">
                    <div class="admin-audit__top">
                      <div>
                        <strong>Wersja v<?= (int) $version['version_num'] ?></strong>
                        <div class="admin-table__muted">
                          Utworzono: <?= !empty($version['created_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $version['created_at']))) : 'Brak' ?>
                        </div>
                      </div>
                      <div class="admin-audit__meta">
                        <span class="badge <?= ((int) ($version['is_active'] ?? 1) === 1) ? 'badge--accent' : 'badge--secondary' ?>">
                          <?= ((int) ($version['is_active'] ?? 1) === 1) ? 'Aktywna' : 'Archiwalna' ?>
                        </span>
                        <span class="badge badge--secondary">Odpowiedzi: <?= (int) ($version['answers_count'] ?? 0) ?></span>
                        <span class="badge badge--secondary">Poprawnych: <?= (int) ($version['correct_answers_count'] ?? 0) ?></span>
                      </div>
                    </div>

                    <div class="admin-audit__details">
                      <div class="admin-answer-grid">
                        <div class="admin-answer-grid__item">
                          <span class="admin-answer-grid__label">Treść</span>
                          <span><?= htmlspecialchars(mb_strimwidth((string) ($version['question_text'] ?? ''), 0, 140, '...')) ?></span>
                        </div>
                        <div class="admin-answer-grid__item">
                          <span class="admin-answer-grid__label">Obrazek</span>
                          <span><?= !empty($version['image_name']) ? htmlspecialchars((string) $version['image_name']) : 'Brak' ?></span>
                        </div>
                        <div class="admin-answer-grid__item">
                          <span class="admin-answer-grid__label">Aktualizacja</span>
                          <span><?= !empty($version['updated_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $version['updated_at']))) : 'Brak' ?></span>
                        </div>
                      </div>
                    </div>
                  </article>
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
