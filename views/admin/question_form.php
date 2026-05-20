<?php
/**
 * @file question_form.php
 * @description Formularz tworzenia i edycji pytania.
 */

$pageTitle = $pageTitle ?? 'Pytanie | Admin Panel | Examly';
$pageDescription = $pageDescription ?? 'Formularz zarządzania pytaniem w panelu CMS Examly.';
$mode = $mode ?? 'create';
$question = $question ?? [];
$errors = $errors ?? [];
$questionId = (int) ($questionId ?? ($question['id'] ?? 0));
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? url('admin/pytania/' . $questionId . '/edytuj') : url('admin/pytania/dodaj');
$title = $isEdit ? 'Edycja pytania' : 'Dodawanie pytania';
$examGroups = $examGroups ?? [];
$answers = $question['answers'] ?? [];

$topicsByExam = [];
foreach ($examGroups as $group) {
  $topicsByExam[(int) $group['exam_id']] = $group['topics'] ?? [];
}

$selectedExamId = (int) ($question['exam_type_id'] ?? 0);
$selectedTopicId = (int) ($question['topic_id'] ?? 0);
$currentImage = $question['image_name'] ?? null;
$correctAnswerIndex = null;
foreach ($answers as $idx => $answer) {
  if (!empty($answer['is_correct'])) {
    $correctAnswerIndex = (int) $idx;
    break;
  }
}
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
        <p class="page-header__text">Zarządzaj wersjonowaną treścią pytania, odpowiedziami i obrazkiem.</p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'questions'; include __DIR__ . '/partials/navigation.php'; ?>

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
                <i class="fas fa-circle-question" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span><?= htmlspecialchars($title) ?></span>
              </div>
              <div class="stats-card__header-actions" style="display:flex; flex-wrap:wrap; gap:.5rem;">
                <?php if ($isEdit && $questionId > 0): ?>
                  <a href="<?= url('admin/pytania/' . $questionId) ?>" class="btn btn--secondary btn--sm">Szczegóły</a>
                <?php endif; ?>
                <a href="<?= url('admin/pytania') ?>" class="btn btn--secondary btn--sm">Lista pytań</a>
              </div>
            </div>

            <form method="POST" action="<?= htmlspecialchars($actionUrl) ?>" class="admin-question-form" enctype="multipart/form-data">
              <?= csrf_field() ?>
              <div class="admin-filters__grid">
                <label class="admin-field">
                  <span>Egzamin</span>
                  <select name="exam_type_id" id="question-exam-select" required>
                    <option value="">Wybierz egzamin</option>
                    <?php foreach ($examGroups as $group): ?>
                      <option value="<?= (int) $group['exam_id'] ?>" <?= $selectedExamId === (int) $group['exam_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['code'] . ' - ' . $group['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <label class="admin-field">
                  <span>Temat</span>
                  <select name="topic_id" id="question-topic-select" required>
                    <option value="">Wybierz temat</option>
                    <?php foreach ($examGroups as $group): ?>
                      <optgroup label="<?= htmlspecialchars($group['code'] . ' - ' . $group['name']) ?>">
                        <?php foreach ($group['topics'] as $topic): ?>
                          <option value="<?= (int) $topic['id'] ?>" data-exam="<?= (int) $group['exam_id'] ?>" <?= $selectedTopicId === (int) $topic['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($topic['name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                </label>

                <label class="admin-field">
                  <span>Status</span>
                  <select name="is_active">
                    <option value="1" <?= ((int) ($question['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Aktywne</option>
                    <option value="0" <?= ((int) ($question['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>Nieaktywne</option>
                  </select>
                </label>

                <label class="admin-field">
                  <span>Obrazek</span>
                  <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                </label>
              </div>

              <div class="admin-question-form__preview">
                <?php if (!empty($currentImage)): ?>
                  <div class="admin-question-image">
                    <img src="<?= htmlspecialchars(url('images/questions/' . $currentImage)) ?>" alt="Aktualny obrazek pytania">
                  </div>
                <?php else: ?>
                  <div class="admin-empty">Brak aktualnego obrazka.</div>
                <?php endif; ?>

                <label class="admin-question-remove">
                  <input type="checkbox" name="remove_image" value="1" <?= !empty($question['remove_image']) ? 'checked' : '' ?>>
                  Usuń obrazek w nowej wersji
                </label>
              </div>

              <div class="admin-question-form__full">
                <label class="admin-field">
                  <span>Treść pytania</span>
                  <textarea name="question_text" rows="7" required><?= htmlspecialchars((string) ($question['question_text'] ?? '')) ?></textarea>
                </label>
              </div>

              <div class="admin-question-form__full">
                <label class="admin-field">
                  <span>Wyjaśnienie</span>
                  <textarea name="explanation" rows="5"><?= htmlspecialchars((string) ($question['explanation'] ?? '')) ?></textarea>
                </label>
              </div>

              <div class="stats-card" style="margin-top: 0;">
                <div class="stats-card__header">
                  <div class="stats-card__title">
                    <i class="fas fa-list-ol" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                    <span>Odpowiedzi</span>
                  </div>
                </div>

                <div class="admin-answer-editors">
                  <?php foreach ($answers as $index => $answer): ?>
                    <div class="admin-answer-editor">
                      <div class="admin-answer-editor__index">Odpowiedź <?= $index + 1 ?></div>
                      <div class="admin-answer-editor__content">
                        <textarea name="answers[<?= $index ?>][content]" rows="3" placeholder="Treść odpowiedzi"><?= htmlspecialchars((string) ($answer['content'] ?? '')) ?></textarea>
                      </div>
                      <label class="admin-answer-editor__correct">
                        <input type="radio" name="correct_answer_index" value="<?= $index ?>" <?= ((int) ($answer['is_correct'] ?? 0) === 1 || ($correctAnswerIndex === null && $index === 0)) ? 'checked' : '' ?>>
                        Poprawna
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>

                <p class="admin-note">
                  Wymagane są co najmniej 2 odpowiedzi i dokładnie 1 poprawna. Wersja pytania zostanie zapisana jako nowy rekord w historii.
                </p>
              </div>

              <div class="admin-filters__actions">
                <button type="submit" class="btn btn--primary">Zapisz pytanie</button>
                <a href="<?= url('admin/pytania') ?>" class="btn btn--secondary">Anuluj</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>

  <script>
    (function () {
      const examSelect = document.getElementById('question-exam-select');
      const topicSelect = document.getElementById('question-topic-select');
      if (!examSelect || !topicSelect) return;

      const allOptions = Array.from(topicSelect.querySelectorAll('option[data-exam]'));

      function syncTopics() {
        const selectedExam = examSelect.value;
        allOptions.forEach((option) => {
          option.hidden = selectedExam && option.dataset.exam !== selectedExam;
        });

        const visibleOptions = allOptions.filter((option) => !option.hidden);
        if (!visibleOptions.some((option) => option.selected)) {
          topicSelect.value = '';
        }
      }

      examSelect.addEventListener('change', syncTopics);
      syncTopics();
    })();
  </script>
</body>

</html>
