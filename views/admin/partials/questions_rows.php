<?php

/**
 * Wiersze tabeli pytań panelu admina.
 *
 * Współdzielony przez pełny render strony (questions.php) oraz endpoint JSON
 * (Admin\AdminQuestionController::search()).
 *
 * Oczekuje: $questions (array).
 */

$questions = $questions ?? [];
$date = static fn(?string $value): string => $value ? date('d.m.Y H:i', strtotime($value)) : 'Brak';
?>
<?php if (empty($questions)): ?>
  <div class="admin-empty">Nie znaleziono pytań.</div>
<?php else: ?>
  <?php foreach ($questions as $question): ?>
    <?php $questionId = (int) $question['id']; $isActive = (int) ($question['is_active'] ?? 0) === 1; ?>
    <div class="admin-table__row">
      <div class="admin-table__cell admin-table__cell--check" data-label="Zaznacz"><input type="checkbox" data-row-check value="<?= $questionId ?>" aria-label="Zaznacz pytanie #<?= $questionId ?>"></div>
      <div class="admin-table__cell admin-table__cell--id" data-label="ID"><?= $questionId ?></div>
      <div class="admin-table__cell admin-truncate" data-label="Egzamin / temat"><strong><?= htmlspecialchars((string) ($question['exam_code'] ?? '')) ?></strong><small title="<?= htmlspecialchars((string) ($question['topic_name'] ?? 'Brak tematu')) ?>"><?= htmlspecialchars((string) ($question['topic_name'] ?? 'Brak tematu')) ?></small></div>
      <div class="admin-table__cell admin-clamp" data-label="Treść" title="<?= htmlspecialchars((string) ($question['question_text'] ?? '')) ?>"><?= htmlspecialchars((string) ($question['question_text'] ?? 'Brak treści')) ?></div>
      <div class="admin-table__cell" data-label="Jakość"><strong><?= (int) ($question['answers_count'] ?? 0) ?> odp.</strong><small>Poprawne: <?= (int) ($question['correct_answers_count'] ?? 0) ?></small></div>
      <div class="admin-table__cell" data-label="Trudność"><span class="badge <?= question_difficulty_badge_class($question['difficulty'] ?? null) ?>"><?= htmlspecialchars(question_difficulty_label($question['difficulty'] ?? null) ?: '—') ?></span></div>
      <div class="admin-table__cell" data-label="Status"><span class="admin-status admin-status--<?= $isActive ? 'success' : 'danger' ?>"><?= $isActive ? 'Aktywne' : 'Nieaktywne' ?></span></div>
      <div class="admin-table__cell" data-label="Obrazek"><span class="admin-status admin-status--neutral"><?= !empty($question['image_name']) ? 'Tak' : 'Nie' ?></span></div>
      <div class="admin-table__cell" data-label="Aktualizacja"><?= $date($question['question_updated_at'] ?? $question['version_updated_at'] ?? null) ?></div>
      <div class="admin-table__cell" data-label="Akcje">
        <div class="admin-actions">
          <a class="btn btn--secondary btn--sm" href="<?= url('admin/pytania/' . $questionId) ?>">Szczegóły</a>
          <a class="btn btn--secondary btn--sm" href="<?= url('admin/pytania/' . $questionId . '/edytuj') ?>">Edytuj</a>
          <?php if ($isActive): ?>
            <form method="POST" action="<?= url('admin/pytania/' . $questionId . '/dezaktywuj') ?>" data-ajax data-confirm="Na pewno dezaktywować to pytanie?" data-confirm-title="Dezaktywacja pytania" data-confirm-label="Dezaktywuj" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger btn--sm" type="submit">Dezaktywuj</button></form>
          <?php else: ?>
            <form method="POST" action="<?= url('admin/pytania/' . $questionId . '/aktywuj') ?>" data-ajax><?= csrf_field() ?><button class="btn btn--primary btn--sm" type="submit">Aktywuj</button></form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
