<?php
/**
 * Konfiguracja komponentu head
 */
$pageTitle = 'Podgląd Egzaminu | Examly';
$pageDescription = 'Szczegółowa analiza Twojego podejścia do egzaminu.';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="home-page-background">
    <?php include 'partials/navbar.php'; ?>

    <div class="container">
        <!-- Professional summary matching post-test results screen -->
        <div class="results-container" style="display: block;">
            <h1 class="results-container__title">Podgląd Wyniku Egzaminu</h1>
            
            <div class="score-summary">
                <?php 
                    $score = round(($exam['meta']['correct_count'] / $exam['meta']['total_questions']) * 100);
                ?>
                <div class="score-circle" style="--score: <?= $score ?>">
                    <div class="score-circle__percent"><?= $score ?>%</div>
                </div>
                
                <div class="score-stats">
                    <div class="stat-item">
                        <i class="stat-item__icon fas fa-check"></i>
                        <div>
                            <p class="stat-item__label">Poprawne odpowiedzi</p>
                            <p class="stat-item__value"><?= $exam['meta']['correct_count'] ?> / <?= $exam['meta']['total_questions'] ?></p>
                        </div>
                    </div>
                    <?php if (isset($exam['meta']['duration_seconds'])): ?>
                    <div class="stat-item">
                        <i class="stat-item__icon fas fa-clock"></i>
                        <div>
                            <p class="stat-item__label">Czas ukończenia</p>
                            <p class="stat-item__value">
                                <?php 
                                    $min = floor($exam['meta']['duration_seconds'] / 60);
                                    $sec = $exam['meta']['duration_seconds'] % 60;
                                    echo sprintf('%02d:%02d', $min, $sec);
                                ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="stat-item">
                         <i class="stat-item__icon fas fa-calendar-alt"></i>
                         <div>
                             <p class="stat-item__label">Data podejścia</p>
                             <p class="stat-item__value"><?= date('d.m.Y H:i', strtotime($exam['meta']['completed_at'])) ?></p>
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <main class="test-container" style="min-height: auto; padding-bottom: 3rem;">
            
            <div class="results-details">
                <?php if (empty($exam['questions'])): ?>
                    <div class="alert alert--info" style="margin-top: 2rem; text-align: center;">
                        <p>Brak szczegółowych danych o odpowiedziach dla tej próby.</p>
                        <p style="font-size: 0.9em; margin-top: 0.5rem;">Może to dotyczyć starych egzaminów rozwiązanym przed wdrożeniem pełnego śledzenia historii.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($exam['questions'] as $index => $question): ?>
                        <section class="quiz-card" id="q-<?= $question['question_id'] ?>">
                            <header class="quiz-card__header">
                                <span class="quiz-card__question-number">Pytanie <?= $index + 1 ?></span>
                            </header>
                            
                            <div class="quiz-card__content">
                                <p class="quiz-card__question-text">
                                    <?= nl2br(htmlspecialchars($question['question_text'])) ?>
                                </p>
                                <?php if (!empty($question['image_path'])): ?>
                                    <div class="quiz-card__image-container">
                                        <img src="<?= url('images/questions/' . $question['image_path']) ?>" alt="Ilustracja do pytania" class="quiz-card__image">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="quiz-card__answers">
                                <?php if (!empty($question['was_skipped'])): ?>
                                    <!-- Pytanie pominięte - specjalna stylizacja -->
                                    <div style="background: var(--field-bg); border: 1px dashed var(--border); padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1rem; text-align: center;">
                                        <span style="color: var(--text-muted); font-weight: 600;">⏭️ Pytanie pominięte</span>
                                    </div>
                                    <?php foreach ($question['answers'] as $ansIndex => $answer): ?>
                                        <?php 
                                            $letter = chr(65 + $ansIndex);
                                            $isCorrect = $answer['is_correct'] == 1;
                                            $statusClass = $isCorrect ? 'quiz-card__answer--correct' : ''; // Pokazujemy tylko poprawną
                                        ?>
                                        <div class="quiz-card__answer <?= $statusClass ?>">
                                            <span class="quiz-card__answer-prefix"><?= $letter ?></span>
                                            <span class="quiz-card__answer-text"><?= nl2br(htmlspecialchars($answer['answer_text'])) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Pytanie z odpowiedzią - normalna logika -->
                                    <?php foreach ($question['answers'] as $ansIndex => $answer): ?>
                                        <?php 
                                            $letter = chr(65 + $ansIndex);
                                            $isCorrect = $answer['is_correct'] == 1;
                                            $isSelected = ($question['user_answer_id'] == $answer['id']);
                                            
                                            $statusClass = '';
                                            if ($isSelected && $isCorrect) {
                                                $statusClass = 'quiz-card__answer--correct';
                                            } elseif ($isSelected && !$isCorrect) {
                                                $statusClass = 'quiz-card__answer--incorrect';
                                            } elseif (!$isSelected && $isCorrect) {
                                                $statusClass = 'quiz-card__answer--correct'; // Pokazujemy poprawną nawet jak nie wybrana
                                            }
                                        ?>
                                        <div class="quiz-card__answer <?= $statusClass ?>">
                                            <span class="quiz-card__answer-prefix"><?= $letter ?></span>
                                            <span class="quiz-card__answer-text"><?= nl2br(htmlspecialchars($answer['answer_text'])) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="quiz-card__actions">
                                <?php if (!empty($question['explanation'])): ?>
                                    <div class="quiz-card__button-container">
                                        <button type="button" class="btn btn--secondary btn--small toggle-explanation-btn" data-target="explanation-<?= $question['version_id'] ?>">
                                            Pokaż wyjaśnienie
                                        </button>
                                    </div>
                                    <div class="quiz-card__explanation" id="explanation-<?= $question['version_id'] ?>">
                                        <?= nl2br(htmlspecialchars($question['explanation'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div style="margin-top: 2rem; display: flex; justify-content: center;">
                <a href="<?= url('statystyki') ?>" class="btn btn--primary">Powrót do statystyk</a>
            </div>
        </main>
    </div>

    <?php include 'partials/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtns = document.querySelectorAll('.toggle-explanation-btn');
            
            toggleBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const targetId = btn.getAttribute('data-target');
                    const content = document.getElementById(targetId);
                    
                    if (content) {
                        const isVisible = content.classList.toggle('quiz-card__explanation--visible');
                        btn.textContent = isVisible ? 'Ukryj wyjaśnienie' : 'Pokaż wyjaśnienie';
                    }
                });
            });
        });
    </script>
</body>
</html>
