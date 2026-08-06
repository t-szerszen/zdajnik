<?php

/**
 * Wiersze tabeli arkuszy panelu admina.
 *
 * Współdzielony przez pełny render strony (sheets.php) oraz endpoint JSON
 * (Admin\AdminSheetController::search()).
 *
 * Oczekuje: $sheets (array).
 */

$sheets = $sheets ?? [];
$date = static fn(?string $value): string => $value ? date('d.m.Y H:i', strtotime($value)) : 'Brak';
?>
<?php if (empty($sheets)): ?>
  <div class="admin-empty">Nie znaleziono arkuszy.</div>
<?php else: ?>
  <?php foreach ($sheets as $sheet): ?>
    <?php $sheetId = (int) $sheet['id']; $isActive = (int) ($sheet['is_active'] ?? 0) === 1; ?>
    <div class="admin-table__row">
      <div class="admin-table__cell admin-table__cell--check" data-label="Zaznacz"><input type="checkbox" data-row-check value="<?= $sheetId ?>" aria-label="Zaznacz arkusz #<?= $sheetId ?>"></div>
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
            <form method="POST" action="<?= url('admin/arkusze/' . $sheetId . '/dezaktywuj') ?>" data-ajax data-confirm="Na pewno dezaktywować ten arkusz?" data-confirm-title="Dezaktywacja arkusza" data-confirm-label="Dezaktywuj" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger btn--sm" type="submit">Dezaktywuj</button></form>
          <?php else: ?>
            <form method="POST" action="<?= url('admin/arkusze/' . $sheetId . '/aktywuj') ?>" data-ajax><?= csrf_field() ?><button class="btn btn--primary btn--sm" type="submit">Aktywuj</button></form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
