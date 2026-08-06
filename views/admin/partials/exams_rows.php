<?php

/**
 * Wiersze tabeli egzaminów panelu admina.
 *
 * Współdzielony przez pełny render strony (exams.php) oraz endpoint JSON
 * (Admin\AdminExamController::search()).
 *
 * Oczekuje: $exams (array).
 */

$exams = $exams ?? [];
$date = static fn(?string $value): string => $value ? date('d.m.Y H:i', strtotime($value)) : 'Brak';
?>
<?php if (empty($exams)): ?>
  <div class="admin-empty">Nie znaleziono egzaminów.</div>
<?php else: ?>
  <?php foreach ($exams as $exam): ?>
    <?php $examId = (int) $exam['id']; $isActive = (int) ($exam['is_active'] ?? 0) === 1; ?>
    <div class="admin-table__row">
      <div class="admin-table__cell admin-table__cell--id" data-label="ID"><?= $examId ?></div>
      <div class="admin-table__cell admin-truncate" data-label="Egzamin" title="<?= htmlspecialchars((string) ($exam['name'] ?? '')) ?>"><strong><?= htmlspecialchars((string) ($exam['code'] ?? '')) ?></strong><small><?= htmlspecialchars((string) ($exam['name'] ?? '')) ?></small></div>
      <div class="admin-table__cell admin-clamp" data-label="Opis" title="<?= htmlspecialchars((string) ($exam['description'] ?? '')) ?>"><?= htmlspecialchars((string) ($exam['description'] ?: 'Brak opisu')) ?></div>
      <div class="admin-table__cell" data-label="Pytania"><strong><?= (int) ($exam['questions_count'] ?? 0) ?></strong><small>Aktywne: <?= (int) ($exam['active_questions_count'] ?? 0) ?></small></div>
      <div class="admin-table__cell" data-label="Status"><span class="admin-status admin-status--<?= $isActive ? 'success' : 'danger' ?>"><?= $isActive ? 'Aktywny' : 'Nieaktywny' ?></span></div>
      <div class="admin-table__cell" data-label="Aktualizacja"><?= $date($exam['updated_at'] ?? null) ?></div>
      <div class="admin-table__cell" data-label="Akcje">
        <div class="admin-actions">
          <a class="btn btn--secondary btn--sm" href="<?= url('admin/egzaminy/' . $examId) ?>">Szczegóły</a>
          <a class="btn btn--secondary btn--sm" href="<?= url('admin/egzaminy/' . $examId . '/edytuj') ?>">Edytuj</a>
          <a class="btn btn--secondary btn--sm" href="<?= url('admin/pytania/dodaj?exam_id=' . $examId) ?>">Dodaj pytanie</a>
          <?php if ($isActive): ?>
            <form method="POST" action="<?= url('admin/egzaminy/' . $examId . '/dezaktywuj') ?>" data-ajax data-confirm="Na pewno dezaktywować ten egzamin?" data-confirm-title="Dezaktywacja egzaminu" data-confirm-label="Dezaktywuj" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger btn--sm" type="submit">Dezaktywuj</button></form>
          <?php else: ?>
            <form method="POST" action="<?= url('admin/egzaminy/' . $examId . '/aktywuj') ?>" data-ajax><?= csrf_field() ?><button class="btn btn--primary btn--sm" type="submit">Aktywuj</button></form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
