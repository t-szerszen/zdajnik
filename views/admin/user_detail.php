<?php
/**
 * @file user_detail.php
 * @description
 * Szczegóły użytkownika w panelu CMS.
 */

$pageTitle = $pageTitle ?? 'Użytkownik | Admin Panel | Examly';
$pageDescription = $pageDescription ?? 'Szczegóły użytkownika w panelu CMS Examly.';
$user = $user ?? [];
$userStats = $userStats ?? [];
$attempts = $attempts ?? [];
$answerPreview = $answerPreview ?? [];
$userId = (int) ($user['id'] ?? 0);
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
          <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert--<?= htmlspecialchars($_SESSION['flash_message']['type']) ?>">
              <?= htmlspecialchars((string) ($_SESSION['flash_message']['text'] ?? '')) ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
          <?php endif; ?>

          <div class="admin-grid admin-grid--compact">
            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-user"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Konto</span>
                <strong class="admin-tile__value"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Brak nazwy') ?></strong>
                <span class="admin-tile__meta"><?= htmlspecialchars($user['email'] ?? '') ?></span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-shield-halved"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Rola</span>
                <strong class="admin-tile__value"><?= htmlspecialchars($user['role'] ?? 'user') ?></strong>
                <span class="admin-tile__meta">Provider: <?= htmlspecialchars($user['auth_provider'] ?? 'local') ?></span>
                <span class="admin-tile__meta">
                  Status: <?= ((int) ($user['is_active'] ?? 1) === 1 && empty($user['deleted_at'])) ? 'Aktywny' : 'Nieaktywny' ?>
                </span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-rocket"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Testy rozpoczęte</span>
                <strong class="admin-tile__value"><?= (int) ($userStats['started_tests'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Ukończone: <?= (int) ($userStats['completed_tests'] ?? 0) ?></span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-tile--static">
              <div class="admin-tile__icon"><i class="fas fa-percentage"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Średni wynik</span>
                <strong class="admin-tile__value">
                  <?= isset($userStats['avg_score']) ? htmlspecialchars((string) $userStats['avg_score']) . '%' : 'Brak' ?>
                </strong>
                <span class="admin-tile__meta">W oparciu o zakończone próby</span>
              </div>
            </div>
          </div>

          <div class="admin-section">
            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-cogs" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                  <span>Akcje administracyjne</span>
                </div>
              </div>

              <form method="POST" action="<?= url('admin/uzytkownicy/' . $userId . '/rola') ?>" class="admin-role-form">
                <?= csrf_field() ?>
                <label class="admin-field">
                  <span>Zmień rolę</span>
                  <select name="role">
                    <option value="user" <?= (($user['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>user</option>
                    <option value="admin" <?= (($user['role'] ?? 'user') === 'admin') ? 'selected' : '' ?>>admin</option>
                  </select>
                </label>
                <button type="submit" class="btn btn--primary">Zapisz zmiany</button>
              </form>

              <div class="admin-note">
                Twarde usuwanie kont nie jest dostępne. Projekt wykorzystuje soft delete i anonimizację.
              </div>
            </div>
          </div>

          <div class="admin-section admin-section--two-col">
            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-list-check" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
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
                  <i class="fas fa-fire" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
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
                <i class="fas fa-pen-to-square" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span>Podgląd odpowiedzi</span>
              </div>
            </div>

            <div class="admin-table admin-table--compact">
              <?php if (empty($answerPreview)): ?>
                <div class="admin-empty">Brak odpowiedzi do podglądu.</div>
              <?php else: ?>
                <?php foreach ($answerPreview as $row): ?>
                  <div class="admin-table__row admin-table__row--stacked">
                    <div>
                      <strong><?= htmlspecialchars(mb_strimwidth((string) ($row['question_text'] ?? ''), 0, 140, '...')) ?></strong>
                      <div class="admin-table__muted">
                        <?= !empty($row['answered_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $row['answered_at']))) : 'Brak daty' ?>
                      </div>
                    </div>

                    <div class="admin-answer-grid">
                      <div class="admin-answer-grid__item">
                        <span class="admin-answer-grid__label">Odpowiedź użytkownika</span>
                        <span><?= htmlspecialchars($row['user_answer'] ?? 'Pominięte') ?></span>
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
