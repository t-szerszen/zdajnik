<?php
/**
 * @file analytics.php
 * @description Roboczy dashboard analityki produktowej dla administratorów.
 */

$pageTitle = $pageTitle ?? 'Analityka | Panel administracyjny | Examly';
$pageDescription = $pageDescription ?? 'Dane produktowe, edukacyjne i operacyjne Examly.';
$analytics = $analytics ?? [];
$filters = $filters ?? [];
$filterOptions = $filterOptions ?? [];
$availableRanges = $availableRanges ?? ($filterOptions['ranges'] ?? []);
$range = $analytics['range'] ?? ['value' => '30d', 'label' => 'Ostatnie 30 dni', 'from' => '', 'to' => ''];

$h = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$selected = static fn(mixed $actual, mixed $expected): string => (string) $actual === (string) $expected ? ' selected' : '';

$tabs = [
  'overview' => ['label' => 'Overview', 'icon' => 'fa-chart-line'],
  'acquisition' => ['label' => 'Acquisition', 'icon' => 'fa-user-plus'],
  'engagement' => ['label' => 'Engagement', 'icon' => 'fa-users'],
  'learning' => ['label' => 'Learning', 'icon' => 'fa-graduation-cap'],
  'content' => ['label' => 'Content', 'icon' => 'fa-list-check'],
  'retention' => ['label' => 'Retention', 'icon' => 'fa-rotate'],
  'users' => ['label' => 'Users', 'icon' => 'fa-address-card'],
];

$exports = [
  'users' => 'Użytkownicy',
  'exams' => 'Egzaminy',
  'questions' => 'Pytania',
  'events' => 'Eventy',
  'retention' => 'Retencja',
];

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
        <p class="page-header__text">Dane o pozyskaniu, aktywności, nauce i jakości treści.</p>
      </div>
    </header>

    <section class="stats-page admin-dashboard">
      <div class="stats-page__container admin-shell">
        <?php $activeSection = 'analytics'; include __DIR__ . '/partials/navigation.php'; ?>

        <div
          class="admin-content admin-analytics"
          data-analytics-root
          data-endpoint="<?= $h(url('admin/analytics/data')) ?>"
          data-export-endpoint="<?= $h(url('admin/analytics/export')) ?>"
          aria-busy="false"
        >
          <script type="application/json" id="analytics-initial-data">
            <?= json_encode($analytics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
          </script>

          <section class="stats-card admin-analytics__filters" aria-label="Filtry analityki">
            <div class="admin-analytics__filter-heading">
              <div>
                <strong>Filtry</strong>
                <span data-range-label><?= $h($range['label'] ?? '') ?></span>
              </div>
              <div class="admin-analytics__request-status hidden" data-loading-status role="status">
                <span class="admin-analytics__spinner" aria-hidden="true"></span>
                Aktualizuję dane
              </div>
            </div>

            <form method="GET" action="<?= $h(url('admin/analytics')) ?>" data-analytics-form>
              <input type="hidden" name="range" value="<?= $h($range['value'] ?? '30d') ?>" data-range-input>
              <input type="hidden" name="page" value="<?= max(1, (int) ($filters['page'] ?? 1)) ?>" data-page-input>
              <input type="hidden" name="per_page" value="<?= (int) ($filters['per_page'] ?? 25) ?>">

              <div class="admin-analytics__range-buttons" aria-label="Szybki zakres dat">
                <?php foreach ($availableRanges as $key => $label): ?>
                  <button
                    type="button"
                    class="admin-analytics__range-btn <?= ($range['value'] ?? '30d') === $key ? 'is-active' : '' ?>"
                    data-range-value="<?= $h($key) ?>"
                  >
                    <?= $h($label) ?>
                  </button>
                <?php endforeach; ?>
              </div>

              <div class="admin-analytics__filter-grid">
                <label class="admin-field">
                  <span>Od</span>
                  <input type="date" name="from" value="<?= $h($range['from'] ?? '') ?>" data-from-input>
                </label>
                <label class="admin-field">
                  <span>Do</span>
                  <input type="date" name="to" value="<?= $h($range['to'] ?? '') ?>" data-to-input>
                </label>
                <label class="admin-field">
                  <span>Egzamin</span>
                  <select name="exam_type_id">
                    <option value="">Wszystkie</option>
                    <?php foreach ($filterOptions['examTypes'] ?? [] as $exam): ?>
                      <option value="<?= (int) $exam['id'] ?>"<?= $selected($filters['exam_type_id'] ?? '', $exam['id']) ?>>
                        <?= $h($exam['code'] . ' - ' . $exam['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="admin-field">
                  <span>Typ testu</span>
                  <select name="test_type">
                    <?php foreach ($filterOptions['testTypes'] ?? [] as $value => $label): ?>
                      <?php $optionValue = $value === 'all' ? '' : $value; ?>
                      <option value="<?= $h($optionValue) ?>"<?= $selected($filters['test_type'] ?? '', $optionValue) ?>>
                        <?= $h($label) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="admin-field">
                  <span>Provider</span>
                  <select name="auth_provider">
                    <?php foreach ($filterOptions['authProviders'] ?? [] as $value => $label): ?>
                      <?php $optionValue = $value === 'all' ? '' : $value; ?>
                      <option value="<?= $h($optionValue) ?>"<?= $selected($filters['auth_provider'] ?? '', $optionValue) ?>>
                        <?= $h($label) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="admin-field">
                  <span>Status użytkownika</span>
                  <select name="user_status">
                    <?php foreach ($filterOptions['userStatuses'] ?? [] as $value => $label): ?>
                      <?php $optionValue = $value === 'all' ? '' : $value; ?>
                      <option value="<?= $h($optionValue) ?>"<?= $selected($filters['user_status'] ?? '', $optionValue) ?>>
                        <?= $h($label) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="admin-field">
                  <span>Event</span>
                  <select name="event_type">
                    <option value="">Wszystkie</option>
                    <?php foreach ($filterOptions['eventTypes'] ?? [] as $value => $label): ?>
                      <option value="<?= $h($value) ?>"<?= $selected($filters['event_type'] ?? '', $value) ?>>
                        <?= $h($label) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="admin-field admin-field--search">
                  <span>Użytkownik</span>
                  <input
                    type="search"
                    name="search"
                    value="<?= $h($filters['search'] ?? '') ?>"
                    placeholder="E-mail, imię lub nazwisko"
                    autocomplete="off"
                    data-search-input
                  >
                </label>
                <div class="admin-analytics__filter-actions">
                  <button type="submit" class="btn btn--primary" data-filter-submit>Zastosuj</button>
                  <button type="button" class="btn btn--secondary" data-filter-reset>Wyczyść</button>
                </div>
              </div>
            </form>
          </section>

          <div class="admin-analytics__toolbar">
            <nav class="admin-analytics__tabs" aria-label="Sekcje analityki" role="tablist">
              <?php foreach ($tabs as $key => $tab): ?>
                <button
                  type="button"
                  class="admin-analytics__tab <?= $key === 'overview' ? 'is-active' : '' ?>"
                  data-analytics-tab="<?= $h($key) ?>"
                  id="analytics-tab-<?= $h($key) ?>"
                  aria-controls="analytics-section-<?= $h($key) ?>"
                  aria-selected="<?= $key === 'overview' ? 'true' : 'false' ?>"
                  role="tab"
                >
                  <i class="fas <?= $h($tab['icon']) ?>" aria-hidden="true"></i>
                  <span><?= $h($tab['label']) ?></span>
                </button>
              <?php endforeach; ?>
            </nav>

            <div class="admin-analytics__exports" aria-label="Eksport CSV">
              <?php foreach ($exports as $dataset => $label): ?>
                <a class="btn btn--secondary btn--sm" href="#" data-export-dataset="<?= $h($dataset) ?>">
                  <i class="fas fa-download" aria-hidden="true"></i>
                  <?= $h($label) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="admin-analytics__error hidden" data-analytics-error role="alert"></div>

          <section
            class="admin-analytics__section is-active"
            data-analytics-section="overview"
            id="analytics-section-overview"
            aria-labelledby="analytics-tab-overview"
            role="tabpanel"
          >
            <header class="admin-analytics__section-heading">
              <div><span>Overview</span><h2>Najważniejsze wskaźniki</h2></div>
              <p>Porównanie z poprzednim okresem o tej samej długości.</p>
            </header>
            <div class="admin-analytics__metric-grid" data-overview-kpis></div>
            <div class="admin-analytics__panel">
              <div class="admin-analytics__panel-heading">
                <h3>Trend dzienny</h3>
                <span>Odsłony, rejestracje i testy</span>
              </div>
              <div class="admin-analytics__chart-wrap">
                <div class="admin-empty hidden" data-chart-empty>Brak danych dla wybranego zakresu.</div>
                <canvas id="admin-analytics-chart" data-chart-canvas></canvas>
              </div>
            </div>
          </section>

          <section class="admin-analytics__section" data-analytics-section="acquisition" id="analytics-section-acquisition" aria-labelledby="analytics-tab-acquisition" role="tabpanel" hidden>
            <header class="admin-analytics__section-heading">
              <div><span>Acquisition</span><h2>Pozyskanie i konwersja</h2></div>
              <p>Od wejścia na stronę do ukończenia testu.</p>
            </header>
            <div class="admin-analytics__metric-grid" data-acquisition-kpis></div>
            <div class="admin-analytics__panel">
              <div class="admin-analytics__panel-heading"><h3>Lejek konwersji</h3><span>Unikalni użytkownicy lub sesje</span></div>
              <div data-conversion-funnel></div>
            </div>
            <div class="admin-analytics__split">
              <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Provider</h3></div><div data-auth-providers></div></div>
              <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Źródła ruchu</h3></div><div data-traffic-sources></div></div>
            </div>
            <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Ścieżki wejścia</h3></div><div data-entry-paths></div></div>
            <div class="admin-analytics__panel">
              <div class="admin-analytics__panel-heading"><h3>Wyszukiwania</h3><span>Frazy są zapisywane tylko po normalizacji i odrzuceniu wzorców PII</span></div>
              <div class="admin-analytics__metric-grid admin-analytics__metric-grid--compact" data-search-kpis></div>
              <div data-search-queries></div>
            </div>
          </section>

          <section class="admin-analytics__section" data-analytics-section="engagement" id="analytics-section-engagement" aria-labelledby="analytics-tab-engagement" role="tabpanel" hidden>
            <header class="admin-analytics__section-heading">
              <div><span>Engagement</span><h2>Aktywność produktu</h2></div>
              <p>DAU, WAU, MAU, sesje, odsłony i zdarzenia.</p>
            </header>
            <div class="admin-analytics__metric-grid" data-engagement-kpis></div>
            <div class="admin-analytics__split">
              <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Najczęstsze ścieżki</h3></div><div data-top-paths></div></div>
              <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Typy zdarzeń</h3></div><div data-event-types></div></div>
            </div>
          </section>

          <section class="admin-analytics__section" data-analytics-section="learning" id="analytics-section-learning" aria-labelledby="analytics-tab-learning" role="tabpanel" hidden>
            <header class="admin-analytics__section-heading">
              <div><span>Learning</span><h2>Skuteczność nauki</h2></div>
              <p>Rozpoczęcia, ukończenia, wyniki i czas pracy.</p>
            </header>
            <div class="admin-analytics__metric-grid" data-learning-kpis></div>
            <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Typ testu</h3></div><div data-learning-types></div></div>
            <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Porzucone testy</h3><span>Jawne wyjścia oraz starty bez wyniku po oknie 2-6 godzin</span></div><div data-abandoned-tests></div></div>
            <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Egzaminy</h3></div><div data-learning-exams></div></div>
          </section>

          <section class="admin-analytics__section" data-analytics-section="content" id="analytics-section-content" aria-labelledby="analytics-tab-content" role="tabpanel" hidden>
            <header class="admin-analytics__section-heading">
              <div><span>Content</span><h2>Jakość pytań i tematów</h2></div>
              <p>Trudność, skuteczność i miejsca utraty odpowiedzi.</p>
            </header>
            <div class="admin-analytics__panel">
              <div class="admin-analytics__panel-heading"><h3>Jakość pytań</h3><span>Posortowane od najwyższego udziału błędów</span></div>
              <div data-content-quality></div>
              <div class="admin-pagination admin-analytics__pagination" data-content-pagination></div>
            </div>
            <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Skuteczność tematów</h3></div><div data-topic-activity></div></div>
            <div class="admin-analytics__split">
              <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Najczęściej zapisywane</h3></div><div data-saved-questions></div></div>
              <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Najczęściej pomijane</h3></div><div data-dropoff-questions></div></div>
            </div>
            <div class="admin-analytics__split">
              <div class="admin-analytics__panel">
                <div class="admin-analytics__panel-heading"><h3>Zapisy per egzamin i temat</h3></div>
                <div data-saved-question-activity></div>
              </div>
              <div class="admin-analytics__panel">
                <div class="admin-analytics__panel-heading"><h3>Oglądanie wyjaśnień</h3><span>Porównanie ze stanem poprawności odpowiedzi</span></div>
                <div class="admin-analytics__metric-grid admin-analytics__metric-grid--compact" data-explanation-kpis></div>
              </div>
            </div>
          </section>

          <section class="admin-analytics__section" data-analytics-section="retention" id="analytics-section-retention" aria-labelledby="analytics-tab-retention" role="tabpanel" hidden>
            <header class="admin-analytics__section-heading">
              <div><span>Retention</span><h2>Powroty i kohorty</h2></div>
              <p>Retencja D1, D7 i D30 oraz konta bez aktywności.</p>
            </header>
            <div class="admin-analytics__metric-grid" data-retention-kpis></div>
            <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Kohorty rejestracji</h3></div><div data-retention-cohorts></div></div>
            <div class="admin-analytics__panel"><div class="admin-analytics__panel-heading"><h3>Nieaktywni użytkownicy</h3></div><div data-inactive-users></div></div>
          </section>

          <section class="admin-analytics__section" data-analytics-section="users" id="analytics-section-users" aria-labelledby="analytics-tab-users" role="tabpanel" hidden>
            <header class="admin-analytics__section-heading">
              <div><span>Users</span><h2>Aktywność użytkowników</h2></div>
              <p>Logowania, testy, średni wynik i status konta.</p>
            </header>
            <div class="admin-analytics__panel">
              <div data-users-table></div>
              <div class="admin-pagination admin-analytics__pagination" data-users-pagination></div>
            </div>
          </section>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="<?= $h(url('js/components/AdminAnalytics.js')) ?>"></script>
</body>
</html>
