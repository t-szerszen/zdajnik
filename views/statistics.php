<?php
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

// Dodajemy Chart.js oraz nasz komponent
$extraScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js'
];
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="home-page-background">
    <?php include 'partials/navbar.php'; ?>

    <header class="page-header">
        <div class="page-header__content">
            <h1 class="page-header__title"><span class="text-gradient">Statystyki</span> Nauki</h1>
            <p class="page-header__text">Śledź swoje postępy, analizuj wyniki i obserwuj swoją drogę do sukcesu w Examly.</p>
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
                        <select id="category-select" name="category" onchange="this.form.submit()" class="stats-page__select">
                            <option value="">wybór kategorii</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $selectedCategoryId == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <!-- Mastery Pie Chart -->
                <div class="stats-card stats-card--mastery">
                    <h3 class="stats-card__title">Opanowanie Materiału</h3>
                    <div class="stats-card__pie-wrapper">
                        <div class="stats-card__pie-chart" 
                             style="background: conic-gradient(var(--accent) 0%, #8b5cf6 <?= $masteryStats['percent'] ?>%, var(--field-bg) <?= $masteryStats['percent'] ?>% 100%);">
                            <div class="stats-card__pie-center">
                                <span class="stats-card__pie-percentage"><?= $masteryStats['percent'] ?>%</span>
                            </div>
                        </div>
                    </div>
                    <p class="stats-card__mastery-legend">
                        <?= $masteryStats['mastered'] ?> z <?= $masteryStats['total'] ?> pytań
                    </p>

                    <!-- Topic Breakdown -->
                    <div class="stats-card__topics">
                        <h4 class="stats-card__subtitle">Podział na tematy</h4>
                        <?php if (empty($topicStats)): ?>
                            <p class="stats-card__empty">Brak danych dla tej kategorii.</p>
                        <?php else: ?>
                            <?php foreach ($topicStats as $topic): ?>
                                <div class="stats-card__topic-item">
                                    <div class="stats-card__topic-label">
                                        <span><?= htmlspecialchars($topic['name']) ?></span>
                                        <span><?= $topic['progress'] ?>%</span>
                                    </div>
                                    <div class="stats-card__progress-bar">
                                        <div class="stats-card__progress-fill" style="width: <?= $topic['progress'] ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
                            <p class="stats-card__empty">Rozwiąż swój pierwszy egzamin, aby zobaczyć trend!</p>
                        <?php else: ?>
                            <div style="height: 250px; width: 100%;">
                                <canvas id="efficiencyTrendChart" data-chart-data='<?= json_encode($efficiencyTrend, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'></canvas>
                            </div>
                            <!-- <p class="stats-card__chart-caption">Średnia krocząca z 5 ostatnich podejść (%)</p> -->
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cards Grid -->
                <div class="stats-page__cards-grid">
                    
                    <!-- Main Stats Card -->
                    <div class="stats-card stats-card--main">
                        <div class="stats-card__header">
                             <h3 class="stats-card__title" style="margin: 0;">Statystyki i Rekordy</h3>
                        </div>
                        <ul class="stats-card__list">
                            <li class="stats-card__list-item">
                                <span>Ostatnia aktywność:</span>
                                <strong><?= $records['last_activity'] ?></strong>
                            </li>
                            <li class="stats-card__list-item">
                                <span>Ostatni egzamin:</span>
                                <strong><?= $records['last_exam'] ?></strong>
                            </li>
                            <li class="stats-card__list-item">
                                <span>Najlepsza próba:</span>
                                <strong><?= $records['best_score'] ?>%</strong>
                            </li>
                            <li class="stats-card__list-item">
                                <span>Średni czas:</span>
                                <strong><?= $records['avg_duration'] ?>s</strong>
                            </li>
                            <li class="stats-card__list-item">
                                <span>Rozwiązanych testów:</span>
                                <strong><?= $records['total_exams'] ?></strong>
                            </li>
                        </ul>

                        <!-- Predicted Score -->
                        <?php 
                        $minExams = $predictedScore['min_exams_required'] ?? 5;
                        $examsAnalyzed = $predictedScore['exams_analyzed'] ?? 0;
                        $isPredictedUnlocked = $examsAnalyzed >= $minExams;
                        ?>

                        <div class="stats-card__predicted <?= !$isPredictedUnlocked ? 'stats-card__predicted--locked' : '' ?>" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--subtle-border); position: relative;">
                            
                            <?php if ($isPredictedUnlocked): ?>
                                <span class="stats-card__icon">📊</span>
                                <div>
                                    <p class="stats-card__predicted-label">Przewidywany wynik egzaminu:</p>
                                    <p class="stats-card__predicted-score">
                                        <?= $predictedScore['predicted_score'] ?>%
                                        <span class="stats-card__confidence stats-card__confidence--<?= $predictedScore['confidence_level'] ?>">
                                            (<?= $predictedScore['confidence_level'] ?>)
                                        </span>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div style="display: flex; align-items: flex-start; gap: 0.75rem; width: 100%;">
                                    <div class="stats-card__lock-icon" style="font-size: 1.25rem;">🔒</div>
                                    <div style="flex: 1;">
                                        <p class="stats-card__lock-text" style="font-size: 0.85rem; margin-bottom: 0.5rem; color: var(--text-muted);">
                                            Przewidywany wynik dostępny po <?= $minExams ?> pełnych egzaminach
                                        </p>
                                        <?php 
                                            $lockedPercent = min(100, round(($examsAnalyzed / $minExams) * 100));
                                        ?>
                                        <div class="stats-card__progress-bar" style="height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                                            <div class="stats-card__progress-fill" style="width: <?= $lockedPercent ?>%; height: 100%; background: var(--accent); transition: width 0.3s ease;"></div>
                                        </div>
                                        <p class="stats-card__unlock-text" style="text-align: right; font-size: 0.75rem; margin-top: 0.25rem; color: var(--text-muted);">
                                            <?= $examsAnalyzed ?>/<?= $minExams ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Critical Errors Card (with lock overlay) -->
                    <div class="stats-card stats-card--critical <?= !$criticalErrors['is_unlocked'] ? 'stats-card--locked' : '' ?>">
                        <div class="stats-card__header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 class="stats-card__title" style="margin-bottom: 0;">🔥 Krytyczne Błędy</h3>
                            <?php if ($criticalErrors['is_unlocked']): ?>
                                <a href="<?= url('historia?type=critical&category=' . $selectedCategoryId) ?>" 
                                   class="btn btn--secondary btn--sm">
                                   zobacz wszystkie <i class="fas fa-chevron-right" style="font-size: 0.8em;"></i>
                                </a>
                            <?php endif; ?>
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
                                        <div class="stats-card__progress-fill" style="width: <?= $criticalErrors['unlock_progress'] ?>%;"></div>
                                    </div>
                                    <p class="stats-card__unlock-text">
                                        <?= $criticalErrors['total_answered'] ?>/100 pytań
                                    </p>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Critical Errors List -->
                            <ul class="stats-card__list">
                                <?php if (empty($criticalErrors['items'])): ?>
                                    <li class="stats-card__empty">Twoja skuteczność jest na wysokim poziomie!</li>
                                <?php else: ?>
                                    <?php foreach ($criticalErrors['items'] as $critical): ?>
                                        <?php
                                        $questionIds = array_column($criticalErrors['items'], 'question_id');
                                        $practiceUrl = url('test/generate?source=critical&questions=' . implode(',', $questionIds));
                                        ?>
                                        <li class="stats-card__list-item stats-card__list-item--critical">
                                            <span class="stats-card__badge stats-card__badge--danger">
                                                <?= $critical['success_rate'] ?>%
                                            </span>
                                            <span class="stats-card__question-text">
                                                <?= mb_strimwidth(htmlspecialchars($critical['question_text']), 0, 35, "...") ?>
                                            </span>
                                            <div style="display: flex; gap: 0.5rem; white-space: nowrap;">
                                                <a href="<?= url('test-pytanie/' . $critical['question_id']) ?>" class="btn btn--primary btn--sm" title="Zobacz">zobacz</a>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                    <li class="stats-card__action" style="display:flex; gap:10px; justify-content:center;">
                                        <a href="<?= url('inf03-jedno-pytanie?premium=toImprove&selectAll=true') ?>" class="btn btn--primary">
                                            🎯 Ćwicz (wszystkie)
                                        </a>
                                        <!-- Placeholder for full view -->
                                        <!-- <a href="#" class="stats-card__action-btn stats-card__action-btn--secondary">Pełna lista</a> -->
                                    </li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Errors Card -->
                    <div class="stats-card stats-card--errors">
                        <div class="stats-card__header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 class="stats-card__title" style="margin-bottom: 0;">Historia Błędów</h3>
                            <a href="<?= url('historia?type=errors&category=' . $selectedCategoryId) ?>" 
                               class="btn btn--secondary btn--sm">
                               zobacz wszystkie <i class="fas fa-chevron-right" style="font-size: 0.8em;"></i>
                            </a>
                        </div>
                        <ul class="stats-card__list">
                            <?php if (empty($errorHistory)): ?>
                                <li class="stats-card__empty">Świetnie! Brak błędów do przejrzenia.</li>
                            <?php else: ?>
                                <?php foreach ($errorHistory as $error): ?>
                                    <li class="stats-card__list-item">
                                        <span class="stats-card__question-text">
                                            <?= mb_strimwidth(htmlspecialchars($error['question_text']), 0, 40, "...") ?>
                                        </span>
                                        <div style="display: flex; gap: 0.5rem; white-space: nowrap;">
                                            <a href="<?= url('test-pytanie/' . $error['id']) ?>" class="btn btn--primary btn--sm">zobacz</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Exam History Card -->
                    <div class="stats-card stats-card--history">
                        <div class="stats-card__header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 class="stats-card__title" style="margin-bottom: 0;">Ostatnie Egzaminy</h3>
                            <a href="<?= url('historia?type=exams&category=' . $selectedCategoryId) ?>" 
                               class="btn btn--secondary btn--sm">
                               zobacz wszystkie <i class="fas fa-chevron-right" style="font-size: 0.8em;"></i>
                            </a>
                        </div>
                        <ul class="stats-card__list">
                            <?php if (empty($recentExams)): ?>
                                <li class="stats-card__empty">Jeszcze nie rozwiązano żadnych testów.</li>
                            <?php else: ?>
                                <?php foreach ($recentExams as $exam): ?>
                                    <li class="stats-card__list-item">
                                        <span>
                                            <?= htmlspecialchars($exam['display_name']) ?>
                                            <strong class="<?= $exam['score'] >= 60 ? 'text-success' : 'text-danger' ?>" style="margin-left: 0.5rem;">
                                                <?= $exam['score'] ?>%
                                            </strong>
                                        </span>
                                        <div style="display: flex; gap: 0.5rem; white-space: nowrap;">
                                            <a href="<?= url('wyniki/' . $exam['id']) ?>" class="btn btn--primary btn--sm">zobacz</a>
                                            
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
