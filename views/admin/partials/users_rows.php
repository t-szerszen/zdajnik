<?php

/**
 * Wiersze tabeli użytkowników panelu admina.
 *
 * Współdzielony przez pełny render strony (users.php) oraz endpoint JSON
 * (Admin\AdminUserController::search()) — jedno źródło prawdy dla markupu.
 *
 * Oczekuje: $users (array).
 */

$users = $users ?? [];
$date = static fn(?string $value): string => $value ? date('d.m.Y H:i', strtotime($value)) : 'Brak';
?>
<?php if (empty($users)): ?>
  <div class="admin-empty">Nie znaleziono użytkowników.</div>
<?php else: ?>
  <?php foreach ($users as $user): ?>
    <?php $userId = (int) $user['id']; ?>
    <div class="admin-table__row">
      <div class="admin-table__cell admin-table__cell--check" data-label="Zaznacz"><input type="checkbox" data-row-check value="<?= $userId ?>" aria-label="Zaznacz użytkownika #<?= $userId ?>"></div>
      <div class="admin-table__cell admin-table__cell--id" data-label="ID"><?= $userId ?></div>
      <div class="admin-table__cell admin-truncate" data-label="Użytkownik" title="<?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>"><strong><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Brak nazwy') ?></strong><small>Rejestracja: <?= $date($user['created_at'] ?? null) ?></small></div>
      <div class="admin-table__cell admin-break-anywhere" data-label="E-mail"><?= htmlspecialchars((string) ($user['email'] ?? '')) ?></div>
      <div class="admin-table__cell" data-label="Rola"><span class="admin-status admin-status--<?= ($user['role'] ?? 'user') === 'admin' ? 'accent' : (($user['role'] ?? 'user') === 'redaktor' ? 'warning' : 'neutral') ?>"><?= htmlspecialchars((string) ($user['role'] ?? 'user')) ?></span></div>
      <div class="admin-table__cell" data-label="Konto"><span class="admin-status admin-status--<?= (int) ($user['is_active'] ?? 0) === 1 ? 'success' : 'danger' ?>"><?= (int) ($user['is_active'] ?? 0) === 1 ? 'Aktywne' : 'Nieaktywne' ?></span></div>
      <div class="admin-table__cell" data-label="Weryfikacja"><span class="admin-status admin-status--<?= (int) ($user['is_verified'] ?? 0) === 1 ? 'success' : 'warning' ?>"><?= (int) ($user['is_verified'] ?? 0) === 1 ? 'Tak' : 'Nie' ?></span></div>
      <div class="admin-table__cell" data-label="Provider"><?= htmlspecialchars((string) ($user['auth_provider'] ?? 'local')) ?></div>
      <div class="admin-table__cell" data-label="Aktywność"><span><?= $date($user['last_activity'] ?? null) ?></span><small>Login: <?= $date($user['last_login'] ?? null) ?></small></div>
      <div class="admin-table__cell" data-label="Testy"><?= (int) ($user['solved_tests'] ?? 0) ?></div>
      <div class="admin-table__cell" data-label="Akcje">
        <div class="admin-actions">
          <a class="btn btn--secondary btn--sm" href="<?= url('admin/uzytkownicy/' . $userId) ?>">Szczegóły</a>
          <?php if ((int) ($user['is_verified'] ?? 0) === 0): ?>
            <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/zweryfikuj') ?>" data-ajax><?= csrf_field() ?><input type="hidden" name="return_to" value="list"><button class="btn btn--secondary btn--sm" type="submit">Zweryfikuj</button></form>
          <?php endif; ?>
          <?php if ((int) ($user['is_active'] ?? 0) === 1): ?>
            <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/dezaktywuj') ?>" data-ajax data-confirm="Na pewno zdezaktywować to konto?" data-confirm-title="Dezaktywacja konta" data-confirm-label="Dezaktywuj" data-confirm-tone="danger"><?= csrf_field() ?><input type="hidden" name="return_to" value="list"><button class="btn btn--danger btn--sm" type="submit">Dezaktywuj</button></form>
          <?php else: ?>
            <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/reaktywuj') ?>" data-ajax><?= csrf_field() ?><input type="hidden" name="return_to" value="list"><button class="btn btn--primary btn--sm" type="submit">Reaktywuj</button></form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
