<?php
/**
 * @file analytics.php
 * @description Dashboard analityki produktowej dla administratorów.
 */

$pageTitle = $pageTitle ?? 'Analityka | Admin Panel | Examly';
$pageDescription = $pageDescription ?? 'Panel analityczny produktu Examly dla administratorów.';
$analytics = $analytics ?? [];
$filters = $filters ?? ['range' => '30d', 'from' => '', 'to' => ''];
$availableRanges = $availableRanges ?? [
  '7d' => 'Ostatnie 7 dni',
  '30d' => 'Ostatnie 30 dni',
  '90d' => 'Ostatnie 90 dni',
];

$range = $analytics['range'] ?? [
  'value' => '30d',
  'label' => 'Ostatnie 30 dni',
  'from' => '',
  'to' => '',
];

$summary = $analytics['summary'] ?? [];
$daily = $analytics['daily'] ?? ['labels' => []];
$examActivity = $analytics['examActivity'] ?? [];
$singleQuestionActivity = $analytics['singleQuestionActivity'] ?? [];
$hardestQuestions = $analytics['hardestQuestions'] ?? [];
$recentActivity = $analytics['recentActivity'] ?? ['logins' => [], 'registrations' => [], 'testsCompleted' => []];
$initialData = [
  'range' => $range,
  'summary' => $summary,
  'daily' => $daily,
  'examActivity' => $examActivity,
  'singleQuestionActivity' => $singleQuestionActivity,
  'hardestQuestions' => $hardestQuestions,
  'recentActivity' => $recentActivity,
];

$extraScripts = array_values(array_unique(array_merge($extraScripts ?? [], [
  'https://cdn.jsdelivr.net/npm/chart.js',
  url('js/components/AdminAnalytics.js'),
])));
?>
<!DOCTYPE html>
<html lang="pl">
<?php include __DIR__ . '/../partials/head.php'; ?>

<body class="auth-page-background">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="main-content-wrapper admin-panel">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient">Analityka</span> produktu</h1>
        <p class="page-header__text">Widok metryk produktowych, aktywności dziennej i zachowania użytkowników.</p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'analytics'; include __DIR__ . '/partials/navigation.php'; ?>

        <div class="admin-content admin-analytics" data-analytics-root data-endpoint="<?= url('admin/analytics/data') ?>">
          <script type="application/json" id="analytics-initial-data">
            <?= json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
          </script>

          <div class="stats-card admin-analytics__filters">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-sliders" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span>Zakres czasu</span>
              </div>
            </div>

            <form class="admin-analytics__range-form" method="GET" action="<?= url('admin/analytics') ?>" data-analytics-form>
              <div class="admin-analytics__range-buttons">
                <?php foreach ($availableRanges as $key => $label): ?>
                  <button type="button"
                    class="btn btn--secondary btn--sm admin-analytics__range-btn <?= ($range['value'] ?? '30d') === $key ? 'is-active' : '' ?>"
                    data-range-value="<?= htmlspecialchars($key) ?>">
                    <?= htmlspecialchars($label) ?>
                  </button>
                <?php endforeach; ?>
              </div>

              <input type="hidden" name="range" value="<?= htmlspecialchars((string) ($range['value'] ?? '30d')) ?>" data-range-input>

              <div class="admin-filters__grid admin-analytics__custom-grid">
                <label class="admin-field">
                  <span>Od</span>
                  <input type="date" name="from" value="<?= htmlspecialchars((string) ($range['from'] ?? '')) ?>" data-from-input>
                </label>

                <label class="admin-field">
                  <span>Do</span>
                  <input type="date" name="to" value="<?= htmlspecialchars((string) ($range['to'] ?? '')) ?>" data-to-input>
                </label>

                <div class="admin-field admin-field--ghost">
                  <span>Aktualny zakres</span>
                  <strong data-range-label><?= htmlspecialchars((string) ($range['label'] ?? 'Ostatnie 30 dni')) ?></strong>
                </div>
              </div>

              <div class="admin-filters__actions">
                <button type="submit" class="btn btn--primary">Odśwież</button>
                <a href="<?= url('admin/analytics') ?>" class="btn btn--secondary">Reset</a>
              </div>
            </form>
          </div>

          <div class="admin-grid admin-analytics__kpi-grid">
            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="totalUsers">
              <div class="admin-tile__icon"><i class="fas fa-users"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Użytkownicy</span>
                <strong class="admin-tile__value"><?= (int) ($summary['totalUsers'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Wszyscy aktywni i nieusunięci</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="newRegistrationsToday">
              <div class="admin-tile__icon"><i class="fas fa-user-plus"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Rejestracje dziś</span>
                <strong class="admin-tile__value"><?= (int) ($summary['newRegistrationsToday'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Nowe konta utworzone dzisiaj</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="loginsToday">
              <div class="admin-tile__icon"><i class="fas fa-right-to-bracket"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Logowania dziś</span>
                <strong class="admin-tile__value"><?= (int) ($summary['loginsToday'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Udane sesje autoryzacji</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="pageViewsToday">
              <div class="admin-tile__icon"><i class="fas fa-eye"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Odwiedziny dziś</span>
                <strong class="admin-tile__value"><?= (int) ($summary['pageViewsToday'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Publiczne odsłony aplikacji</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="testsStartedToday">
              <div class="admin-tile__icon"><i class="fas fa-play"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Testy rozpoczęte</span>
                <strong class="admin-tile__value"><?= (int) ($summary['testsStartedToday'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Starty dzisiaj</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="testsCompletedToday">
              <div class="admin-tile__icon"><i class="fas fa-flag-checkered"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Testy ukończone</span>
                <strong class="admin-tile__value"><?= (int) ($summary['testsCompletedToday'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Zapisane wyniki dzisiaj</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="averageScore">
              <div class="admin-tile__icon"><i class="fas fa-bullseye"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Średni wynik</span>
                <strong class="admin-tile__value"><?= number_format((float) ($summary['averageScore'] ?? 0), 1, ',', ' ') ?>%</strong>
                <span class="admin-tile__meta">Wynik w wybranym zakresie</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="questionsAnsweredToday">
              <div class="admin-tile__icon"><i class="fas fa-circle-check"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Rozwiązane pytania</span>
                <strong class="admin-tile__value"><?= (int) ($summary['questionsAnsweredToday'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Zdarzenia odpowiedzi dziś</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="singleQuestionStartedToday">
              <div class="admin-tile__icon"><i class="fas fa-bolt"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Jedno pytanie dziś</span>
                <strong class="admin-tile__value"><?= (int) ($summary['singleQuestionStartedToday'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Uruchomienia modułu dzisiaj</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="singleQuestionStartedInRange">
              <div class="admin-tile__icon"><i class="fas fa-layer-group"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Jedno pytanie w zakresie</span>
                <strong class="admin-tile__value"><?= (int) ($summary['singleQuestionStartedInRange'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Uruchomienia w aktywnym zakresie</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="singleQuestionAnsweredToday">
              <div class="admin-tile__icon"><i class="fas fa-reply"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Odpowiedzi dziś</span>
                <strong class="admin-tile__value"><?= (int) ($summary['singleQuestionAnsweredToday'] ?? 0) ?></strong>
                <span class="admin-tile__meta">Odpowiedzi w module dzisiaj</span>
              </div>
            </div>

            <div class="stats-card admin-tile admin-analytics__kpi" data-kpi="singleQuestionAccuracy">
              <div class="admin-tile__icon"><i class="fas fa-crosshairs"></i></div>
              <div class="admin-tile__content">
                <span class="admin-tile__label">Skuteczność modułu</span>
                <strong class="admin-tile__value"><?= number_format((float) ($summary['singleQuestionAccuracy'] ?? 0), 1, ',', ' ') ?>%</strong>
                <span class="admin-tile__meta">Poprawne odpowiedzi w zakresie</span>
              </div>
            </div>
          </div>

          <div class="stats-card admin-analytics__chart-card">
            <div class="stats-card__header">
              <div class="stats-card__title">
                <i class="fas fa-chart-column" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                <span>Analityka dzienna</span>
              </div>
            </div>

            <div class="admin-analytics__chart-wrap">
              <div class="admin-analytics__empty hidden" data-chart-empty>
                <div class="admin-empty">Brak danych dla wybranego zakresu.</div>
              </div>
              <canvas id="admin-analytics-chart" data-chart-canvas></canvas>
            </div>
          </div>

          <div class="admin-analytics__grid-two">
            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-file-lines" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                  <span>Aktywność egzaminów</span>
                </div>
              </div>

              <div class="admin-table admin-table--wide admin-analytics__table">
                <div class="admin-table__head admin-analytics__table-head">
                  <span>Egzamin</span>
                  <span>Rozpoczęte</span>
                  <span>Ukończone</span>
                  <span>Średni wynik</span>
                  <span>Użytkownicy</span>
                </div>

                <div data-exam-activity>
                  <?php if (empty($examActivity)): ?>
                    <div class="admin-empty">Brak aktywności egzaminów dla wybranego zakresu.</div>
                  <?php else: ?>
                    <?php foreach ($examActivity as $row): ?>
                      <div class="admin-table__row admin-analytics__table-row">
                        <div>
                          <strong><?= htmlspecialchars((string) ($row['code'] ?? '')) ?></strong>
                          <div class="admin-table__muted"><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></div>
                        </div>
                        <span><?= (int) ($row['started_tests'] ?? 0) ?></span>
                        <span><?= (int) ($row['completed_tests'] ?? 0) ?></span>
                        <span><?= number_format((float) ($row['avg_score'] ?? 0), 1, ',', ' ') ?>%</span>
                        <span><?= (int) ($row['unique_users'] ?? 0) ?></span>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-lightbulb" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                  <span>Aktywność modułu jednego pytania</span>
                </div>
              </div>

              <div class="admin-table admin-table--wide admin-analytics__table">
                <div class="admin-table__head admin-analytics__table-head admin-analytics__table-head--single-question">
                  <span>Egzamin</span>
                  <span>Uruchomienia</span>
                  <span>Odpowiedzi</span>
                  <span>Użytkownicy</span>
                  <span>Skuteczność</span>
                  <span>Najczęstsze pytanie</span>
                </div>

                <div data-single-question-activity>
                  <?php if (empty($singleQuestionActivity)): ?>
                    <div class="admin-empty">Brak aktywności modułu jednego pytania dla wybranego zakresu.</div>
                  <?php else: ?>
                    <?php foreach ($singleQuestionActivity as $row): ?>
                      <div class="admin-table__row admin-analytics__table-row admin-analytics__table-row--single-question">
                        <div>
                          <strong><?= htmlspecialchars((string) ($row['code'] ?: 'Brak przypisanego egzaminu')) ?></strong>
                          <div class="admin-table__muted"><?= htmlspecialchars((string) ($row['name'] ?? 'Brak przypisanego egzaminu')) ?></div>
                        </div>
                        <span><?= (int) ($row['started_count'] ?? 0) ?></span>
                        <span><?= (int) ($row['answered_count'] ?? 0) ?></span>
                        <span><?= (int) ($row['unique_users'] ?? 0) ?></span>
                        <span><?= number_format((float) ($row['accuracy'] ?? 0), 1, ',', ' ') ?>%</span>
                        <span><?= htmlspecialchars(mb_strimwidth((string) ($row['top_question_text'] ?? 'Brak danych'), 0, 80, '...')) ?></span>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-triangle-exclamation" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                  <span>Najtrudniejsze pytania</span>
                </div>
              </div>

              <div class="admin-table admin-table--wide admin-analytics__table">
                <div class="admin-table__head admin-analytics__table-head admin-analytics__table-head--hardest">
                  <span>Pytanie</span>
                  <span>Egzamin</span>
                  <span>Odpowiedzi</span>
                  <span>Błędne</span>
                  <span>Wynik błędów</span>
                </div>

                <div data-hardest-questions>
                  <?php if (empty($hardestQuestions)): ?>
                    <div class="admin-empty">Brak danych dla wybranego zakresu.</div>
                  <?php else: ?>
                    <?php foreach ($hardestQuestions as $row): ?>
                      <div class="admin-table__row admin-analytics__table-row admin-analytics__table-row--hardest">
                        <div>
                          <strong><?= htmlspecialchars(mb_strimwidth((string) ($row['question_text'] ?? ''), 0, 120, '...')) ?></strong>
                        </div>
                        <span><?= htmlspecialchars((string) ($row['exam_code'] ?? '')) ?></span>
                        <span><?= (int) ($row['total_answers'] ?? 0) ?></span>
                        <span><?= (int) ($row['wrong_answers'] ?? 0) ?></span>
                        <span><?= number_format((float) ($row['wrong_percent'] ?? 0), 1, ',', ' ') ?>%</span>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="admin-analytics__grid-three">
            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-right-to-bracket" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                  <span>Ostatnie logowania</span>
                </div>
              </div>
              <div class="admin-list" data-recent-logins>
                <?php if (empty($recentActivity['logins'] ?? [])): ?>
                  <div class="admin-empty">Brak danych.</div>
                <?php else: ?>
                  <?php foreach ($recentActivity['logins'] as $item): ?>
                    <div class="admin-list__row">
                      <div class="admin-list__primary">
                        <strong><?= htmlspecialchars((string) ($item['display_name'] ?? trim((string) ($item['first_name'] ?? '') . ' ' . (string) ($item['last_name'] ?? '')) ?: 'Użytkownik nieznany')) ?></strong>
                        <span><?= htmlspecialchars((string) ($item['email'] ?? '')) ?></span>
                      </div>
                      <div class="admin-list__secondary">
                        <span class="badge badge--secondary"><?= htmlspecialchars((string) ($item['ip_address'] ?? 'Brak IP')) ?></span>
                        <span class="badge badge--secondary"><?= htmlspecialchars((string) ($item['browser_label'] ?? 'Brak danych')) ?></span>
                        <span class="badge badge--secondary">login</span>
                        <span class="badge badge--accent"><?= !empty($item['event_date']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $item['event_date']))) : 'Brak' ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-user-plus" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                  <span>Ostatnie rejestracje</span>
                </div>
              </div>
              <div class="admin-list" data-recent-registrations>
                <?php if (empty($recentActivity['registrations'] ?? [])): ?>
                  <div class="admin-empty">Brak danych.</div>
                <?php else: ?>
                  <?php foreach ($recentActivity['registrations'] as $item): ?>
                    <div class="admin-list__row">
                      <div class="admin-list__primary">
                        <strong><?= htmlspecialchars(trim((string) ($item['first_name'] ?? '') . ' ' . (string) ($item['last_name'] ?? ''))) ?></strong>
                        <span><?= htmlspecialchars((string) ($item['email'] ?? '')) ?></span>
                      </div>
                      <div class="admin-list__secondary">
                        <span class="badge badge--secondary"><?= htmlspecialchars((string) ($item['auth_provider'] ?? 'local')) ?></span>
                        <span class="badge badge--accent"><?= !empty($item['created_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $item['created_at']))) : 'Brak' ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="stats-card">
              <div class="stats-card__header">
                <div class="stats-card__title">
                  <i class="fas fa-flag-checkered" style="color: var(--accent); background: rgba(var(--accent-rgb), 0.1);"></i>
                  <span>Ostatnio ukończone testy</span>
                </div>
              </div>
              <div class="admin-list" data-recent-tests>
                <?php if (empty($recentActivity['testsCompleted'] ?? [])): ?>
                  <div class="admin-empty">Brak danych.</div>
                <?php else: ?>
                  <?php foreach ($recentActivity['testsCompleted'] as $item): ?>
                    <div class="admin-list__row">
                      <div class="admin-list__primary">
                        <strong><?= htmlspecialchars(trim((string) ($item['first_name'] ?? '') . ' ' . (string) ($item['last_name'] ?? ''))) ?></strong>
                        <span><?= htmlspecialchars((string) ($item['exam_code'] ?? '')) ?> - <?= htmlspecialchars((string) ($item['exam_name'] ?? '')) ?></span>
                      </div>
                      <div class="admin-list__secondary">
                        <span class="badge badge--secondary"><?= (int) ($item['correct_count'] ?? 0) ?>/<?= (int) ($item['total_questions'] ?? 0) ?></span>
                        <span class="badge badge--accent"><?= !empty($item['completed_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $item['completed_at']))) : 'Brak' ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>

</html>
