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
$noIndex = true;
$pageTitle = 'Statystyki Twojej Nauki | Zdajnik';
$pageDescription = 'Śledź swoje postępy, analizuj wyniki i obserwuj swoją drogę do sukcesu w Zdajnik.';

// Dane przekazane z kontrolera
$categories = $categories ?? [];
$selectedCategoryId = $selectedCategoryId ?? null;
$hasSelectedCategory = $hasSelectedCategory ?? ($selectedCategoryId !== null && (int) $selectedCategoryId > 0);
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

// Chart.js jest już doładowywany leniwie przez loadScriptOnce() w main.js
// (patrz initStatisticsPage) - blokujący <script> w <head> byłby zbędny.
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
  <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">Statystyki</span> Nauki</h1>
      <p class="page-header__text">Zaawansowana analityka Twojej ścieżki edukacyjnej. Monitoruj tempo przyswajania
        wiedzy, identyfikuj obszary wymagające uwagi i konsekwentnie zmierzaj po wymarzony wynik.</p>
    </div>
  </header>

  <section class="stats-page" data-stats-tutorial-root>
    <div class="stats-page__container">

      <!-- SIDEBAR -->
      <aside class="stats-page__sidebar">

        <!-- Category Selector -->
        <div class="stats-page__category-selector" id="stats-category-selector">
          <form action="" method="GET">
            <label for="category-select" class="visually-hidden">Wybór kategorii</label>
            <select id="category-select" name="category" onchange="this.form.submit()" class="stats-page__select"
              aria-label="Wybierz kategorię egzaminu, aby zobaczyć statystyki">
              <option value="">wybór egzaminu</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $hasSelectedCategory && (int) $selectedCategoryId === (int) $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['code']) ?>
                </option>
                <?php
              endforeach; ?>
            </select>
          </form>
          <div class="stats-page__default-exam hidden" data-default-stats-exam>
            <label class="stats-page__default-exam-label">
              <input type="checkbox" data-default-stats-exam-checkbox>
              <span>Ustaw ten egzamin jako domyślny dla statystyk</span>
            </label>
            <p class="stats-page__default-exam-helper">Przy kolejnym wejściu od razu pokażemy statystyki tego egzaminu.</p>
          </div>
        </div>

        <?php if ($hasSelectedCategory): ?>
        <!-- Mastery Pie Chart -->
        <div class="stats-card stats-card--mastery" id="stats-mastery-card">
          <div class="stats-card__header-minimal">
            <h3 class="stats-card__title">Twoje Opanowanie</h3>
          </div>

          <div class="stats-card__pie-wrapper">
            <div class="stats-card__pie-chart"
              style="background: conic-gradient(var(--accent) 0%, var(--accent-hover) <?= $masteryStats['percent'] ?>%, var(--field-bg) <?= $masteryStats['percent'] ?>% 100%);">
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

          <p class="stats-card__mastery-activity stats-card__mastery-activity--mb">
            Opanowano: <strong>
              <?= $masteryStats['mastered'] ?>
            </strong> /
            <?= $masteryStats['total'] ?> pytań
          </p>
          <p class="stats-card__mastery-activity stats-card__mastery-activity--sm">
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
                <a href="<?= url('jedno-pytanie/' . exam_code_to_url($selectedCategoryCode)) ?>" class="btn btn--primary btn--sm"
                  >Wybierz temat</a>
              </div>
              <?php
            else: ?>
              <?php foreach ($topicStats as $topic): ?>
                <div class="stats-card__topic-item">
                  <div class="stats-card__topic-label stats-card__topic-label--top">
                    <span class="topic-name">
                      <?= mb_strimwidth(htmlspecialchars($topic['name']), 0, 30, '...') ?>
                    </span>
                    <div class="stats-card__topic-counts">
                      <span class="topic-count">Opanowane:
                        <?= $topic['mastered'] ?> /
                        <?= $topic['total'] ?>
                      </span>
                      <span class="stats-card__topic-attempted">Poznano:
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
        <?php endif; ?>

      </aside>

      <?php if (!$hasSelectedCategory): ?>
        <section class="stats-page__content">
          <div class="stats-card">
            <div class="stats-card__empty">
              <div class="stats-card__empty-icon">📊</div>
              <p>Wybierz egzamin, aby zobaczyć statystyki.</p>
            </div>
          </div>
        </section>
      <?php else: ?>
      <!-- Główna siatka treści -->
      <section class="stats-page__content">

        <!-- Efficiency Trend Chart -->
        <div class="stats-card stats-card--trend" id="stats-trend-card">
          <h2 class="stats-card__title">Trend Skuteczności (Średnia Krocząca)</h2>
          <div class="stats-card__chart">
            <?php if (empty($efficiencyTrend)): ?>
              <div class="stats-card__empty">
                <div class="stats-card__empty-icon">📈</div>
                <p>Twój wykres skuteczności jest jeszcze pusty.</p>
                <p class="stats-card__empty-hint">Rozwiąż pierwszy
                  egzamin, aby wygenerować Twój trend!</p>
                <a href="<?= url('test/' . exam_code_to_url($selectedCategoryCode)) ?>" class="btn btn--primary"
                  >Rozpocznij test</a>
              </div>
              <?php
            else: ?>

              <div class="stats-card__chart-container">
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
          <div class="stats-card stats-card--main" id="stats-key-metrics-card">
            <div class="stats-card__header">
              <h3 class="stats-card__title stats-card__title--no-margin"><i class="fas fa-trophy"></i> Kluczowe Wyniki</h3>
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

            <div class="stats-card__predicted <?= !$isPredictedUnlocked ? 'stats-card__predicted--locked' : '' ?>">

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
                <div class="stats-card__lock-row">
                  <div class="stats-card__lock-icon">🔒</div>
                  <div class="stats-card__lock-body">
                    <p class="stats-card__lock-text">
                      Przewidywany wynik dostępny po
                      <?= $minExams ?> pełnych egzaminach
                    </p>
                    <?php
                    $lockedPercent = min(100, round(($examsAnalyzed / $minExams) * 100));
                    ?>
                    <div class="stats-card__progress-bar stats-card__progress-bar--sm">
                      <div class="stats-card__progress-fill"
                        style="width: <?= $lockedPercent ?>%;">
                      </div>
                    </div>
                    <p class="stats-card__unlock-text">
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
            <div class="stats-card__header">
              <h3 class="stats-card__title stats-card__title--no-margin">🔥 Krytyczne Błędy</h3>
              <?php if ($criticalErrors['is_unlocked'] && ($criticalErrors['total_critical'] ?? 0) > 0): ?>
                <a href="<?= url('historia?type=critical&category=' . $selectedCategoryId) ?>"
                  class="btn btn--secondary btn--sm">
                  zobacz wszystkie (
                  <?= $criticalErrors['total_critical'] ?>) <i class="stats-card__see-all-icon fas fa-chevron-right"></i>
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
                        Zacznij rozwiązywać testy, aby wyłapać Twoje najczęstsze błędy.
                        <?php
                      else: ?>
                        Twoja skuteczność jest na najwyższym poziomie! Nie wykryto krytycznych błędów.
                        <?php
                      endif; ?>
                    </p>

                    <div class="stats-card__empty-progress">
                      <div class="stats-card__progress-bar stats-card__progress-bar--md">
                        <div class="stats-card__progress-fill"
                          style="width: <?= min(100, $criticalErrors['unlock_progress'] ?? 0) ?>%;">
                        </div>
                      </div>
                      <p class="stats-card__progress-label">
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
                      <div class="stats-card__item-btn-group">
                        <a href="<?= url('test-pytanie/' . $critical['question_id']) ?>?source=stats&category=<?= $selectedCategoryId ?>&anchor=criticalMistakes" class="btn btn--secondary btn--sm btn--compact"
                          title="Zobacz">zobacz</a>
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
            <div class="stats-card__header">
              <h3 class="stats-card__title stats-card__title--no-margin">Ostatnie błędy</h3>
              <?php if (($errorHistory['total'] ?? 0) > 0): ?>
                <a href="<?= url('historia?type=errors&category=' . $selectedCategoryId) ?>"
                  class="btn btn--secondary btn--sm">
                  zobacz wszystkie (
                  <?= $errorHistory['total'] ?? 0 ?>) <i class="stats-card__see-all-icon fas fa-chevron-right"></i>
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
                    <div class="stats-card__item-btn-group">
                      <a href="<?= url('test-pytanie/' . $error['id']) ?>?source=stats&category=<?= $selectedCategoryId ?>&anchor=lastMistakes" class="btn btn--secondary btn--sm btn--compact">zobacz</a>
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
            <div class="stats-card__header">
              <h3 class="stats-card__title stats-card__title--no-margin"><i class="stats-card__heart-icon fas fa-heart"></i> Zapisane</h3>
              <?php if (($savedQuestions['total'] ?? 0) > 0): ?>
                <a href="<?= url('historia?type=saved&category=' . $selectedCategoryId) ?>"
                  class="btn btn--secondary btn--sm">
                  zobacz wszystkie (
                  <?= $savedQuestions['total'] ?? 0 ?>) <i class="stats-card__see-all-icon fas fa-chevron-right"></i>
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
                    <div class="stats-card__item-btn-group">
                      <a href="<?= url('test-pytanie/' . $saved['id']) ?>?source=stats&category=<?= $selectedCategoryId ?>&anchor=savedQuestions" class="btn btn--secondary btn--sm btn--compact">zobacz</a>
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
            <div class="stats-card__header">
              <h3 class="stats-card__title stats-card__title--no-margin">Ostatnie Egzaminy</h3>
              <?php if (($recentExams['total'] ?? 0) > 0): ?>
                <a href="<?= url('historia?type=exams&category=' . $selectedCategoryId) ?>"
                  class="btn btn--secondary btn--sm">
                  zobacz wszystkie (
                  <?= $recentExams['total'] ?? 0 ?>) <i class="stats-card__see-all-icon fas fa-chevron-right"></i>
                </a>
                <?php
              endif; ?>
            </div>
            <ul class="stats-card__list">
              <?php if (empty($recentExams['items'])): ?>
                <li class="stats-card__empty">Nie masz jeszcze żadnych zapisanych egzaminów. Czas to zmienić!</li>
                <li class="stats-card__action">
                  <a href="<?= url('test/' . exam_code_to_url($selectedCategoryCode)) ?>" class="btn btn--primary">Podejdź do arkusza</a>
                </li>
                <?php
              else: ?>
                <?php foreach ($recentExams['items'] as $exam): ?>
                  <li class="stats-card__list-item">
                    <span class="stats-card__exam-info">
                      <?= htmlspecialchars($exam['display_name']) ?>
                      <strong class="<?= $exam['score'] >= 50 ? 'text-success' : 'text-danger' ?> stats-card__score-value">
                        <?= $exam['score'] ?>%
                      </strong>
                    </span>
                    <div class="stats-card__item-btn-group">
                      <a href="<?= url('wyniki/' . $exam['id']) ?>?source=stats&category=<?= $selectedCategoryId ?>" class="btn btn--secondary btn--sm btn--compact">zobacz</a>
                    </div>
                  </li>
                  <?php
                endforeach; ?>
                <?php
              endif; ?>
            </ul>

          </div>

        </div>

        <div class="stats-page__tutorial-restart">
          <button type="button" class="btn btn--secondary" data-restart-stats-tutorial>
            <i class="fas fa-route"></i> Uruchom samouczek statystyk ponownie
          </button>
        </div>

      </section>
      <?php endif; ?>
    </div>
  </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
