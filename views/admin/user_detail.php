<?php
/**
 * @file user_detail.php
 * @description
 * Szczegóły użytkownika w panelu CMS.
 */

$noIndex = true;
$pageTitle = $pageTitle ?? 'Użytkownik | Panel administracyjny | Zdajnik';
$pageDescription = $pageDescription ?? 'Szczegóły użytkownika w panelu CMS Zdajnik.';
$user = $user ?? [];
$userStats = $userStats ?? [];
$attempts = $attempts ?? [];
$answerPreview = $answerPreview ?? [];
$userId = (int) ($user['id'] ?? 0);
$isActive = (int) ($user['is_active'] ?? 0) === 1 && empty($user['deleted_at']);
$isVerified = (int) ($user['is_verified'] ?? 0) === 1;
$date = static fn(?string $value): string => $value ? date('d.m.Y H:i', strtotime($value)) : 'Brak';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>

<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title">
          <span class="text-gradient">Użytkownik #<?= $userId ?></span>
        </h1>
        <p class="page-header__text">
          Pełny podgląd danych konta, statystyk i historii aktywności.
        </p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'users'; include __DIR__ . '/partials/navigation.php'; ?>

        <div class="admin-content">
          <?php include __DIR__ . '/partials/flash.php'; ?>

          <div class="admin-grid admin-grid--compact">
            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-user"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Konto</span>
                <strong class="admin-tile__value"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Brak nazwy') ?></strong>
                <span class="admin-tile__meta admin-break-anywhere"><?= htmlspecialchars($user['email'] ?? '') ?></span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-shield-halved"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Rola</span>
                <strong class="admin-tile__value"><?= htmlspecialchars($user['role'] ?? 'user') ?></strong>
                <span class="admin-tile__meta">Provider: <?= htmlspecialchars($user['auth_provider'] ?? 'local') ?></span>
                <span class="admin-tile__meta">Status: <?= $isActive ? 'Aktywny' : 'Nieaktywny' ?></span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-rocket"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Testy</span>
                <strong class="admin-tile__value"><?= (int) ($userStats['started_tests'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Ukończone: <?= (int) ($userStats['completed_tests'] ?? 0) ?></span>
                <span class="admin-tile__meta">
                  Średni wynik: <?= isset($userStats['avg_score']) ? htmlspecialchars((string) $userStats['avg_score']) . '%' : 'brak' ?>
                </span>
              </div>
            </div>

            <?php $single = $userStats['single'] ?? ['total' => 0, 'correct' => 0, 'accuracy' => null, 'last_answered_at' => null]; ?>
            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-bolt"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Jedno pytanie</span>
                <strong class="admin-tile__value"><?= (int) $single['total'] ?></strong>
                <span class="admin-tile__meta">
                  <?= $single['accuracy'] !== null ? 'Poprawne: ' . (int) $single['correct'] . ' (' . htmlspecialchars((string) $single['accuracy']) . '%)' : 'Brak odpowiedzi' ?>
                </span>
                <span class="admin-tile__meta">Ostatnio: <?= $date($single['last_answered_at'] ?? null) ?></span>
              </div>
            </div>
          </div>

          <div class="admin-section">
            <section class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-user-gear"></i>
                  <span>Zarządzanie kontem</span>
                </div>
                <a class="btn btn--secondary btn--sm" href="<?= url('admin/audyt?target_type=user&target_id=' . $userId) ?>">Audyt konta</a>
              </div>

              <div class="admin-account-summary">
                <div><span>Status konta</span><strong class="admin-status admin-status--<?= $isActive ? 'success' : 'danger' ?>"><?= $isActive ? 'Aktywne' : 'Nieaktywne' ?></strong></div>
                <div><span>Weryfikacja</span><strong class="admin-status admin-status--<?= $isVerified ? 'success' : 'warning' ?>"><?= $isVerified ? 'Zweryfikowane' : 'Niezweryfikowane' ?></strong></div>
                <div><span>Provider</span><strong class="admin-status admin-status--neutral"><?= htmlspecialchars((string) ($user['auth_provider'] ?? 'local')) ?></strong></div>
                <div><span>Ostatni login</span><strong><?= $date($user['last_login'] ?? null) ?></strong></div>
                <div><span>Ostatnia aktywność</span><strong><?= $date($user['last_activity'] ?? null) ?></strong></div>
                <div><span>Rejestracja</span><strong><?= $date($user['created_at'] ?? null) ?></strong></div>
              </div>

              <div class="admin-management-actions">
                <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/rola') ?>" class="admin-role-form">
                  <?= csrf_field() ?>
                  <label class="admin-field">
                    <span>Rola</span>
                    <select name="role">
                      <option value="user" <?= (($user['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>Użytkownik</option>
                      <option value="redaktor" <?= (($user['role'] ?? 'user') === 'redaktor') ? 'selected' : '' ?>>Redaktor</option>
                      <option value="admin" <?= (($user['role'] ?? 'user') === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                  </label>
                  <button type="submit" class="btn btn--primary">Zmień rolę</button>
                </form>

                <div class="admin-actions">
                  <?php if (!$isVerified): ?>
                    <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/zweryfikuj') ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn--secondary" type="submit">Zweryfikuj konto</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($isActive): ?>
                    <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/dezaktywuj') ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn--danger" type="submit" onclick="return confirm('Na pewno zdezaktywować to konto?')">Dezaktywuj konto</button>
                    </form>
                  <?php elseif (empty($user['deleted_at'])): ?>
                    <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/reaktywuj') ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn--primary" type="submit">Reaktywuj konto</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>

              <p class="admin-note">Panel nie udostępnia twardego usuwania kont.</p>
            </section>
          </div>

          <div class="admin-section admin-section--two-col">
            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-list-check"></i>
                  <span>Historia testów</span>
                </div>
              </div>

              <div class="admin-table admin-table--compact">
                <?php if (empty($attempts)): ?>
                  <div class="admin-empty">Brak prób użytkownika.</div>
                <?php else: ?>
                  <?php foreach ($attempts as $attempt): ?>
                    <div class="admin-table__row admin-table__row--stacked">
                      <div>
                        <strong><?= htmlspecialchars(($attempt['exam_code'] ?? '') . ' - ' . ($attempt['exam_name'] ?? '')) ?></strong>
                        <div class="admin-table__muted">
                          Start: <?= !empty($attempt['started_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $attempt['started_at']))) : 'Brak' ?>
                          |
                          Koniec: <?= !empty($attempt['completed_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $attempt['completed_at']))) : 'W trakcie' ?>
                        </div>
                      </div>
                      <div class="admin-table__meta">
                        <span class="badge badge--secondary"><?= htmlspecialchars($attempt['test_type'] ?? '') ?></span>
                        <span class="badge badge--accent"><?= htmlspecialchars((string) ($attempt['score_percent'] ?? '0')) ?>%</span>
                        <span class="badge badge--secondary">Poprawne: <?= (int) ($attempt['correct_count'] ?? 0) ?></span>
                        <span class="badge badge--secondary">Błędne: <?= (int) ($attempt['wrong_count'] ?? 0) ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-fire"></i>
                  <span>Najczęstsze błędy</span>
                </div>
              </div>

              <div class="admin-list">
                <?php if (empty($userStats['top_mistakes'])): ?>
                  <div class="admin-empty">Brak danych o błędach.</div>
                <?php else: ?>
                  <?php foreach ($userStats['top_mistakes'] as $mistake): ?>
                    <div class="admin-list__row">
                      <div class="admin-list__primary">
                        <strong><?= htmlspecialchars($mistake['topic_name'] ?? 'Bez tematu') ?></strong>
                        <span><?= htmlspecialchars(mb_strimwidth((string) ($mistake['question_text'] ?? ''), 0, 120, '...')) ?></span>
                      </div>
                      <div class="admin-list__secondary">
                        <span class="badge badge--secondary">Błędy: <?= (int) ($mistake['incorrect_count'] ?? 0) ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="stats-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-pen-to-square"></i>
                <span>Ostatnie odpowiedzi</span>
              </div>
              <span class="admin-table__muted">Testy i tryb jednego pytania</span>
            </div>

            <div class="admin-table admin-table--compact">
              <?php if (empty($answerPreview)): ?>
                <div class="admin-empty">Brak odpowiedzi do podglądu.</div>
              <?php else: ?>
                <?php foreach ($answerPreview as $row): ?>
                  <div class="admin-table__row admin-table__row--stacked">
                    <div>
                      <strong><?= htmlspecialchars(mb_strimwidth((string) ($row['question_text'] ?? ''), 0, 140, '...')) ?></strong>
                      <div class="admin-table__muted admin-table__muted--inline">
                        <span class="badge badge--secondary"><?= ($row['source'] ?? 'test') === 'single' ? 'Jedno pytanie' : 'Test' ?></span>
                        <?= !empty($row['answered_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $row['answered_at']))) : 'Brak daty' ?>
                      </div>
                    </div>

                    <div class="admin-answer-grid">
                      <div class="admin-answer-grid__item">
                        <span class="admin-answer-grid__label">Odpowiedź użytkownika</span>
                        <span>
                          <?php if ((int) ($row['answer_unknown'] ?? 0) === 1): ?>
                            <span class="admin-table__muted">Brak danych (odpowiedź sprzed zapisu historii)</span>
                          <?php else: ?>
                            <?= htmlspecialchars($row['user_answer'] ?? 'Pominięte') ?>
                          <?php endif; ?>
                        </span>
                      </div>
                      <div class="admin-answer-grid__item">
                        <span class="admin-answer-grid__label">Poprawna odpowiedź</span>
                        <span><?= htmlspecialchars($row['correct_answer'] ?? 'Brak') ?></span>
                      </div>
                      <div class="admin-answer-grid__item">
                        <span class="admin-answer-grid__label">Status</span>
                        <span class="badge <?= ((int) ($row['is_correct'] ?? 0) === 1) ? 'badge--accent' : 'badge--secondary' ?>">
                          <?= ((int) ($row['is_correct'] ?? 0) === 1) ? 'Poprawna' : 'Błędna' ?>
                        </span>
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
