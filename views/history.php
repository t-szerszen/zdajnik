<?php
/**
 * Widok pełnej historii (egzaminy, błędy, krytyczne)
 */
$pageTitle = ($title ?? 'Historia') . ' | Examly';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>
<body class="home-page-background">
    <?php include 'partials/navbar.php'; ?>

    <header class="page-header">
        <div class="page-header__content">
            <h1 class="page-header__title"><span class="text-gradient">Historia</span><?= htmlspecialchars($title) ?></h1>
            <p class="page-header__text">
                <a href="<?= url('statystyki?category=' . $selectedCategoryId) ?>" class="btn btn--secondary">← Wróć do Statystyk</a>
            </p>
        </div>
    </header>

    <main class="stats-page">
        <div class="stats-page__container stats-page__container--single-col">
            
            <div class="stats-card">
                <div class="stats-card__header-content">
                    <div class="stats-card__title-group">
                        <div class="stats-card__title-row">
                            <h2 class="stats-card__title stats-card__title--lg"><?= htmlspecialchars($title ?? 'Historia') ?></h2>
                        </div>
                        <p class="stats-card__description">
                            <?php if ($type === 'exams'): ?>
                                Przeglądaj swoje ostatnie podejścia do egzaminów.
                            <?php elseif ($type === 'critical'): ?>
                                Pytania, które sprawiają Ci najwięcej trudności.
                            <?php else: ?>
                                Lista pytań, na które ostatnio udzielono błędnej odpowiedzi.
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($items) && in_array($type, ['errors', 'critical'])): ?>
                         <?php 
                            $premiumParam = ($type === 'critical') ? 'toImprove' : 'lastMistakes';
                         ?>
                         <a href="<?= url('inf03-jedno-pytanie?premium=' . $premiumParam . '&selectAll=true') ?>" 
                            class="stats-card__action-btn stats-card__action-btn--primary stats-card__action-btn--sm"
                            style="padding: 0.5rem 1.2rem;">
                            <span>🎯</span> Ćwicz wszystkie (<?= count($items) ?>)
                         </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($items)): ?>
                    <div class="stats-card__empty-state">
                        <div class="stats-card__empty-icon">📭</div>
                        <p class="stats-card__empty-text">Brak danych do wyświetlenia w tej kategorii.</p>
                        <a href="<?= url('statystyki') ?>" class="btn-text">Wróć do statystyk</a>
                    </div>
                <?php else: ?>
                    <ul class="stats-card__list">
                        <?php foreach ($items as $item): ?>
                            
                            <?php if ($type === 'exams'): ?>
                                <li class="stats-card__list-item stats-card__list-item--history-item">
                                    <div class="stats-card__item-content">
                                        <span class="stats-card__item-title">
                                            <?= htmlspecialchars($item['display_name']) ?> 
                                        </span>
                                        <div class="stats-card__meta">
                                            <span>
                                                <i class="far fa-calendar-alt"></i> <?= date('d.m.Y', strtotime($item['completed_at'])) ?>
                                            </span>
                                            <span>
                                                <i class="far fa-clock"></i> <?= date('H:i', strtotime($item['completed_at'])) ?>
                                            </span>
                                            <?php if(isset($item['badge_text'])): ?>
                                                <span class="badge badge-sm stats-card__badge--subtle"><?= htmlspecialchars($item['badge_text']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <strong class="<?= $item['score'] >= 50 ? 'text-success' : 'text-danger' ?>" style="font-size: 1.5rem;">
                                            <?= $item['score'] ?>%
                                        </strong>
                                        <a href="<?= url('wyniki/' . $item['id']) ?>" class="stats-card__action-btn stats-card__action-btn--primary" title="Zobacz szczegóły">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </div>
                                </li>

                            <?php elseif ($type === 'critical'): ?>
                                <li class="stats-card__list-item stats-card__list-item--critical stats-card__list-item--history-item">
                                    <div class="stats-card__item-row">
                                        <span class="stats-card__badge stats-card__badge--danger stats-card__badge--lg">
                                            <?= $item['success_rate'] ?>%
                                        </span>
                                        <span class="stats-card__question-text">
                                            <?= htmlspecialchars($item['question_text']) ?>
                                        </span>
                                    </div>
                                    <div class="stats-card__item-actions">
                                        <a href="<?= url('test-pytanie/' . $item['question_id']) ?>" class="stats-card__action-btn stats-card__action-btn--primary stats-card__action-btn--sm" title="Podgląd">zobacz</a>
                                    </div>
                                </li>

                            <?php elseif ($type === 'errors'): ?>
                                <li class="stats-card__list-item stats-card__list-item--history-item">
                                    <span class="stats-card__question-text">
                                        <?= htmlspecialchars($item['question_text']) ?>
                                    </span>
                                    <div class="stats-card__item-actions">
                                        <a href="<?= url('test-pytanie/' . $item['id']) ?>" class="stats-card__action-btn stats-card__action-btn--primary stats-card__action-btn--sm" title="Podgląd">zobacz</a>
                                    </div>
                                </li>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <?php include 'partials/footer.php'; ?>
</body>
</html>
