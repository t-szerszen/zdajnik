<?php
/**
 * @file statistics.php
 * @description
 * Panel statystyk i analizy postępów nauki użytkownika.
 *
 * @variables
 * - $masteryStats (array)
 * - $topicStats (array)
 * - $recentExams (array)
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */

/**
 * Konfiguracja komponentu head
 */
$pageTitle = 'Statystyki Twojej Nauki | Examly';
$pageDescription = 'Śledź swoje postępy, analizuj wyniki i obserwuj swoją drogę do sukcesu w Examly.';

// Dane przekazane z kontrolera
$categories = $categories ?? [];
$selectedCategoryId = $selectedCategoryId ?? 0;
$masteryStats = $masteryStats ?? ['total' => 0, 'mastered' => 0, 'percent' => 0];
$topicStats = $topicStats ?? [];
$records = $records ?? ['best_score' => 0, 'last_exam' => 'Brak', 'last_activity' => 'Brak', 'total_exams' => 0, 'avg_duration' => 0];
$predictedScore = $predictedScore ?? ['predicted_score' => 0, 'confidence_level' => 'none', 'exams_analyzed' => 0];
$errorHistory = $errorHistory ?? [];
$criticalErrors = $criticalErrors ?? ['items' => [], 'total_answered' => 0, 'unlock_progress' => 0, 'is_unlocked' => false];
$recentExams = $recentExams ?? [];
$efficiencyTrend = $efficiencyTrend ?? [];
$savedQuestions = $savedQuestions ?? ['items' => [], 'total' => 0];

// Znajdź kod aktualnie wybranej kategorii do linków
$selectedCategoryCode = '';
foreach ($categories as $cat) {
  if ($cat['id'] == $selectedCategoryId) {
    $selectedCategoryCode = $cat['code'];
    break;
  }
}

// Dodajemy Chart.js oraz nasz komponent
$extraScripts = [
  'https://cdn.jsdelivr.net/npm/chart.js'
];
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">Statystyki</span> Nauki</h1>
      <p class="page-header__text">Zaawansowana analityka Twojej ścieżki edukacyjnej. Monitoruj tempo przyswajania
        wiedzy, identyfikuj obszary wymagające uwagi i konsekwentnie zmierzaj po wymarzony wynik.</p>
    </div>
  </header>

  <main class="stats-page">
    <div class="stats-page__container">

      <!-- SIDEBAR -->
      <aside class="stats-page__sidebar">

        <!-- Category Selector -->
        <div class="stats-page__category-selector">
          <form action="" method="GET">
            <label for="category-select" class="sr-only">Wybór kategorii</label>
            <select id="category-select" name="category" onchange="this.form.submit()" class="stats-page__select"
              aria-label="Wybierz kategorię egzaminu, aby zobaczyć statystyki">
              <option value="">wybór egzaminu</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $selectedCategoryId == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['code']) ?>
                </option>
                <?php
              endforeach; ?>
            </select>
          </form>
        </div>

        <!-- Mastery Pie Chart -->
        <div class="stats-card stats-card--mastery">
          <div class="stats-card__header-minimal">
            <h3 class="stats-card__title">Twoje Opanowanie</h3>
          </div>

          <div class="stats-card__pie-wrapper">
            <div class="stats-card__pie-chart"
              style="background: conic-gradient(var(--accent) 0%, #8b5cf6 <?= $masteryStats['percent'] ?>%, var(--field-bg) <?= $masteryStats['percent'] ?>% 100%);">
              <div class="stats-card__pie-center">
                <div class="stats-card__pie-data">
                  <span class="stats-card__pie-percentage">
                    <?= $masteryStats['percent'] ?>%
                  </span>
                  <span class="stats-card__pie-label">Ukończono</span>
                </div>
              </div>
            </div>
          </div>

          <div class="stats-card__mastery-stats">
            <div class="mastery-item">
              <span class="mastery-item__value">
                <?= $masteryStats['mastered'] ?>
              </span>
              <span class="mastery-item__label">Opanowane</span>
            </div>
            <div class="mastery-item mastery-item--divider"></div>
            <div class="mastery-item">
              <span class="mastery-item__value">
                <?= $masteryStats['total'] ?>
              </span>
              <span class="mastery-item__label">Wszystkie</span>
            </div>
          </div>

          <p class="stats-card__mastery-activity" style="margin-bottom: 0.25rem;">
            Opanowano: <strong>
              <?= $masteryStats['mastered'] ?>
            </strong> /
            <?= $masteryStats['total'] ?> pytań
          </p>
          <p class="stats-card__mastery-activity" style="font-size: 0.75rem; opacity: 0.7; margin-top: 0;">
            Poznano:
            <?= ($masteryStats['attempted'] ?? 0) ?> /
            <?= $masteryStats['total'] ?> pytań
          </p>

          <!-- Topic Breakdown -->
          <div class="stats-card__topics">
            <div class="stats-card__header-minimal">
              <h4 class="stats-card__subtitle">Postęp tematyczny</h4>
            </div>

            <?php if (empty($topicStats)): ?>
              <div class="stats-card__empty stats-card__empty--compact">
                <p>Rozpocznij naukę, aby zobaczyć postęp.</p>
                <a href="<?= url('jedno-pytanie/' . $selectedCategoryCode) ?>" class="btn btn--primary btn--sm"
                  style="margin-top: 1rem;">Wybierz temat</a>
              </div>
              <?php
            else: ?>
              <?php foreach ($topicStats as $topic): ?>
                <div class="stats-card__topic-item">
                  <div class="stats-card__topic-label" style="align-items: flex-start;">
                    <span class="topic-name" style="margin-top: 4px;">
                      <?= mb_strimwidth(htmlspecialchars($topic['name']), 0, 30, '...') ?>
                    </span>
                    <div style="text-align: right;">
                      <span class="topic-count" style="display: block; margin-bottom: 4px;">Opanowane:
                        <?= $topic['mastered'] ?> /
                        <?= $topic['total'] ?>
                      </span>
                      <span
                        style="font-size: 0.70rem; color: var(--muted-text); font-weight: 600; display: block; padding-right: 4px;">Poznano:
                        <?= $topic['attempted'] ?> /
                        <?= $topic['total'] ?>
                      </span>
                    </div>
                  </div>
                  <div class="stats-card__progress-bar">
                    <div class="stats-card__progress-fill" style="width: <?= $topic['progress'] ?>%;"></div>
                  </div>
                </div>
                <?php
              endforeach; ?>
              <?php
            endif; ?>
          </div>
        </div>

      </aside>

      <!-- MAIN CONTENT GRID -->
      <section class="stats-page__content">

        <!-- Efficiency Trend Chart -->
        <div class="stats-card stats-card--trend">
          <h2 class="stats-card__title">Trend Skuteczności (Średnia Krocząca)</h2>
          <div class="stats-card__chart">
            <?php if (empty($efficiencyTrend)): ?>
              <div class="stats-card__empty">
                <div class="stats-card__empty-icon">📈</div>
                <p>Twój wykres skuteczności jest jeszcze pusty.</p>
                <p style="font-size: 0.85rem; opacity: 0.7; margin-top: 0.5rem; font-style: italic;">Rozwiąż pierwszy
                  egzamin, abyśmy mogli wygenerować Twój trend!</p>
                <a href="<?= url('test/' . $selectedCategoryCode) ?>" class="btn btn--primary"
                  style="margin-top: 1.5rem;">Rozpocznij test</a>
              </div>
              <?php
            else: ?>

              <div style="height: 250px; width: 100%;">
                <canvas id="efficiencyTrendChart"
                  data-chart-data='<?= json_encode($efficiencyTrend, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'></canvas>
              </div>
              <!-- <p class="stats-card__chart-caption">Średnia krocząca z 5 ostatnich podejść (%)</p> -->
              <?php
            endif; ?>
          </div>
        </div>

        <!-- Cards Grid -->
        <div class="stats-page__cards-grid">

          <!-- Main Stats Card -->
          <div class="stats-card stats-card--main">
            <div class="stats-card__header">
              <h3 class="stats-card__title" style="margin: 0;"><i class="fas fa-trophy"></i> Kluczowe Wyniki</h3>
            </div>

            <div class="stats-card__grid-items">
              <div class="metric-box">
                <span class="metric-box__label">Najlepszy Wynik</span>
                <span class="metric-box__value text-gradient">
                  <?= $records['best_score'] ?>%
                </span>
                <i class="fas fa-star metric-box__icon"></i>
              </div>
              <div class="metric-box">
                <span class="metric-box__label">Średni Czas</span>
                <span class="metric-box__value">
                  <?= $records['avg_duration'] ?>s
                </span>
                <i class="fas fa-bolt metric-box__icon"></i>
              </div>
              <div class="metric-box">
                <span class="metric-box__label">Rozwiązane Testy</span>
                <span class="metric-box__value">
                  <?= $records['total_exams'] ?>
                </span>
                <i class="fas fa-file-alt metric-box__icon"></i>
              </div>
              <div class="metric-box">
                <span class="metric-box__label">Ostatni Wynik</span>
                <span class="metric-box__value">
                  <?= $records['last_exam'] ?>
                </span>
                <i class="fas fa-history metric-box__icon"></i>
              </div>
            </div>

            <div class="stats-card__activity-footer">
              <p><i class="fas fa-calendar-check"></i> Ostatnia aktywność: <strong>
                  <?= $records['last_activity'] ?>
                </strong></p>
            </div>

            <!-- Predicted Score -->
            <?php
            $minExams = $predictedScore['min_exams_required'] ?? 5;
            $examsAnalyzed = $predictedScore['exams_analyzed'] ?? 0;
            $isPredictedUnlocked = $examsAnalyzed >= $minExams;
            ?>

            <div class="stats-card__predicted <?= !$isPredictedUnlocked ? 'stats-card__predicted--locked' : '' ?>"
              style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--subtle-border); position: relative;">

              <?php if ($isPredictedUnlocked): ?>
                <span class="stats-card__icon">📊</span>
                <div>
                  <p class="stats-card__predicted-label">Przewidywany wynik egzaminu:</p>
                  <p class="stats-card__predicted-score">
                    <?= $predictedScore['predicted_score'] ?>%
                    <span
                      class="stats-card__confidence stats-card__confidence--<?= $predictedScore['confidence_level'] ?>">
                      (
                      <?= $predictedScore['confidence_level'] ?>)
                    </span>
                  </p>
                </div>
                <?php
              else: ?>
                <div style="display: flex; align-items: flex-start; gap: 0.75rem; width: 100%;">
                  <div class="stats-card__lock-icon" style="font-size: 1.25rem;">🔒</div>
                  <div style="flex: 1;">
                    <p class="stats-card__lock-text"
                      style="font-size: 0.85rem; margin-bottom: 0.5rem; color: var(--text-muted);">
                      Przewidywany wynik dostępny po
                      <?= $minExams ?> pełnych egzaminach
                    </p>
                    <?php
                    $lockedPercent = min(100, round(($examsAnalyzed / $minExams) * 100));
                    ?>
                    <div class="stats-card__progress-bar"
                      style="height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                      <div class="stats-card__progress-fill"
                        style="width: <?= $lockedPercent ?>%; height: 100%; background: var(--accent); transition: width 0.3s ease;">
                      </div>
                    </div>
                    <p class="stats-card__unlock-text"
                      style="text-align: right; font-size: 0.75rem; margin-top: 0.25rem; color: var(--text-muted);">
                      <?= $examsAnalyzed ?>/
                      <?= $minExams ?>
                    </p>
                  </div>
                </div>
                <?php
              endif; ?>
            </div>
          </div>

          <!-- Critical Errors Card (with lock overlay) -->
          <div class="stats-card stats-card--critical <?= !$criticalErrors['is_unlocked'] ? 'stats-card--locked' : '' ?>"
            id="criticalMistakes">
            <div class="stats-card__header"
              style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h3 class="stats-card__title" style="margin-bottom: 0;">🔥 Krytyczne Błędy</h3>
              <?php if ($criticalErrors['is_unlocked'] && ($criticalErrors['total_critical'] ?? 0) > 0): ?>
                <a href="<?= url('historia?type=critical&category=' . $selectedCategoryId) ?>"
                  class="btn btn--secondary btn--sm">
                  zobacz wszystkie (
                  <?= $criticalErrors['total_critical'] ?>) <i class="fas fa-chevron-right" style="font-size: 0.8em;"></i>
                </a>
                <?php
              endif; ?>
            </div>

            <?php if (!$criticalErrors['is_unlocked']): ?>
              <!-- Lock Overlay -->
              <div class="stats-card__locked-overlay">
                <div class="stats-card__lock-icon">🔒</div>
                <p class="stats-card__lock-text">
                  Funkcja dostępna po rozwiązaniu 100 pytań
                </p>
                <div class="stats-card__unlock-progress">
                  <div class="stats-card__progress-bar">
                    <div class="stats-card__progress-fill" style="width: <?= $criticalErrors['unlock_progress'] ?>%;">
                    </div>
                  </div>
                  <p class="stats-card__unlock-text">
                    <?= $criticalErrors['total_answered'] ?>/100 pytań
                  </p>
                </div>
              </div>
              <?php
            else: ?>
              <ul class="stats-card__list">
                <?php if (empty($criticalErrors['items'])): ?>
                  <li class="stats-card__empty">
                    <p>
                      <?php if ($records['total_exams'] == 0): ?>
                        Zacznij rozwiązywać testy, abyśmy mogli wyłapać Twoje najczęstsze błędy.
                        <?php
                      else: ?>
                        Twoja skuteczność jest na najwyższym poziomie! Nie wykryto krytycznych błędów.
                        <?php
                      endif; ?>
                    </p>

                    <div style="width: 100%; max-width: 250px; margin-top: 1.5rem;">
                      <div class="stats-card__progress-bar"
                        style="height: 8px; background: rgba(var(--text-rgb), 0.1); border-radius: 4px; overflow: hidden; margin-bottom: 1rem;">
                        <div class="stats-card__progress-fill"
                          style="width: <?= min(100, $criticalErrors['unlock_progress'] ?? 0) ?>%; height: 100%; background: var(--accent); transition: width 0.6s ease;">
                        </div>
                      </div>
                      <p
                        style="font-size: 0.75rem; color: var(--muted-text); font-weight: 700; margin: 0; text-align: center;">
                        Postęp analizy:
                        <?= $criticalErrors['total_answered'] ?? 0 ?>/100 pytań
                      </p>
                    </div>
                  </li>
                  <?php
                else: ?>
                  <?php foreach ($criticalErrors['items'] as $critical): ?>
                    <?php
                    $questionIds = array_column($criticalErrors['items'], 'question_id');
                    $practiceUrl = url('test/generate?source=critical&questions=' . implode(',', $questionIds));
                    ?>
                    <li class="stats-card__list-item stats-card__list-item--critical">
                      <span class="stats-card__badge stats-card__badge--danger">
                        <?= $critical['success_rate'] ?>%
                      </span>
                      <span class="stats-card__question-text" title="<?= htmlspecialchars($critical['question_text']) ?>">
                        <?= mb_strimwidth(htmlspecialchars($critical['question_text']), 0, 120, "...") ?>
                      </span>
                      <div style="display: flex; gap: 0.5rem; white-space: nowrap; flex-shrink: 0;">
                        <a href="<?= url('test-pytanie/' . $critical['question_id']) ?>" class="btn btn--secondary btn--sm"
                          title="Zobacz" style="padding: 0.4rem 1rem;">zobacz</a>
                      </div>
                    </li>
                    <?php
                  endforeach; ?>
                  <?php
                endif; ?>
              </ul>
              <?php
            endif; ?>

          </div>



          <!-- Recent Errors Card -->
          <div class="stats-card stats-card--errors" id="lastMistakes">
            <div class="stats-card__header"
              style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h3 class="stats-card__title" style="margin-bottom: 0;">Ostatnie błędy</h3>
              <?php if (($errorHistory['total'] ?? 0) > 0): ?>
                <a href="<?= url('historia?type=errors&category=' . $selectedCategoryId) ?>"
                  class="btn btn--secondary btn--sm">
                  zobacz wszystkie (
                  <?= $errorHistory['total'] ?? 0 ?>) <i class="fas fa-chevron-right" style="font-size: 0.8em;"></i>
                </a>
                <?php
              endif; ?>

            </div>
            <ul class="stats-card__list">
              <?php if (empty($errorHistory['items'])): ?>
                <?php if ($records['total_exams'] == 0): ?>
                  <li class="stats-card__empty">Twoja historia błędów jest pusta – to świetny moment na pierwszy test!</li>
                  <?php
                else: ?>
                  <li class="stats-card__empty">Nie masz żadnych błędów do przejrzenia. Tak trzymaj!</li>
                  <?php
                endif; ?>
                <?php
              else: ?>
                <?php foreach ($errorHistory['items'] as $error): ?>
                  <li class="stats-card__list-item">
                    <span class="stats-card__question-text" title="<?= htmlspecialchars($error['question_text']) ?>">
                      <?= mb_strimwidth(htmlspecialchars($error['question_text']), 0, 120, "...") ?>
                    </span>
                    <div style="display: flex; gap: 0.5rem; white-space: nowrap;">
                      <a href="<?= url('test-pytanie/' . $error['id']) ?>" class="btn btn--secondary btn--sm"
                        style="padding: 0.4rem 1rem;">zobacz</a>
                    </div>
                  </li>
                  <?php
                endforeach; ?>
                <?php
              endif; ?>
            </ul>


          </div>

          <!-- Saved Questions Card -->
          <div class="stats-card stats-card--saved" id="savedQuestions">
            <div class="stats-card__header"
              style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h3 class="stats-card__title" style="margin-bottom: 0;"><i class="fas fa-heart"
                  style="color: var(--accent); margin-right: 8px;"></i> Zapisane</h3>
              <?php if (($savedQuestions['total'] ?? 0) > 0): ?>
                <a href="<?= url('historia?type=saved&category=' . $selectedCategoryId) ?>"
                  class="btn btn--secondary btn--sm">
                  zobacz wszystkie (
                  <?= $savedQuestions['total'] ?? 0 ?>) <i class="fas fa-chevron-right" style="font-size: 0.8em;"></i>
                </a>
                <?php
              endif; ?>
            </div>
            <ul class="stats-card__list">
              <?php if (empty($savedQuestions['items'])): ?>
                <li class="stats-card__empty">Nie masz jeszcze żadnych zapisanych pytań. Kliknij serduszko przy trudnym
                  pytaniu, aby je tu dodać!</li>
                <?php
              else: ?>
                <?php foreach ($savedQuestions['items'] as $saved): ?>
                  <li class="stats-card__list-item">
                    <span class="stats-card__question-text" title="<?= htmlspecialchars($saved['question_text']) ?>">
                      <?= mb_strimwidth(htmlspecialchars($saved['question_text']), 0, 120, "...") ?>
                    </span>
                    <div style="display: flex; gap: 0.5rem; white-space: nowrap;">
                      <a href="<?= url('test-pytanie/' . $saved['id']) ?>" class="btn btn--secondary btn--sm"
                        style="padding: 0.4rem 1rem;">zobacz</a>
                    </div>
                  </li>
                  <?php
                endforeach; ?>
                <?php
              endif; ?>
            </ul>
          </div>

          <!-- Exam History Card -->
          <div class="stats-card stats-card--history" id="examsHistory">
            <div class="stats-card__header"
              style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h3 class="stats-card__title" style="margin-bottom: 0;">Ostatnie Egzaminy</h3>
              <?php if (($recentExams['total'] ?? 0) > 0): ?>
                <a href="<?= url('historia?type=exams&category=' . $selectedCategoryId) ?>"
                  class="btn btn--secondary btn--sm">
                  zobacz wszystkie (
                  <?= $recentExams['total'] ?? 0 ?>) <i class="fas fa-chevron-right" style="font-size: 0.8em;"></i>
                </a>
                <?php
              endif; ?>
            </div>
            <ul class="stats-card__list">
              <?php if (empty($recentExams['items'])): ?>
                <li class="stats-card__empty">Nie masz jeszcze żadnych zapisanych egzaminów. Czas to zmienić!</li>
                <li class="stats-card__action">
                  <a href="<?= url('test/' . $selectedCategoryCode) ?>" class="btn btn--primary">Podejdź do arkusza</a>
                </li>
                <?php
              else: ?>
                <?php foreach ($recentExams['items'] as $exam): ?>
                  <li class="stats-card__list-item">
                    <span>
                      <?= htmlspecialchars($exam['display_name']) ?>
                      <strong class="<?= $exam['score'] >= 50 ? 'text-success' : 'text-danger' ?>"
                        style="margin-left: 0.5rem;">
                        <?= $exam['score'] ?>%
                      </strong>
                    </span>
                    <div style="display: flex; gap: 0.5rem; white-space: nowrap;">
                      <a href="<?= url('wyniki/' . $exam['id']) ?>" class="btn btn--secondary btn--sm"
                        style="padding: 0.4rem 1rem;">zobacz</a>
                    </div>
                  </li>
                  <?php
                endforeach; ?>
                <?php
              endif; ?>
            </ul>

          </div>

        </div>

      </section>
    </div>
  </main>

  <?php include 'partials/footer.php'; ?>
  <script type="module" src="<?= url('js/components/EfficiencyChart.js') ?>"></script>
</body>

</html>