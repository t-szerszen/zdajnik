<?php
/**
 * Konfiguracja komponentu head
 */
$pageTitle = 'Podgląd pytania | Examly';
$pageDescription = 'Analiza pytania z bazy egzaminacyjnej.';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="home-page-background">
    <?php include 'partials/navbar.php'; ?>

    <header class="page-header">
        <div class="page-header__content">
            <h1 class="page-header__title">Podgląd <span class="text-gradient">pytania</span></h1>
            <p class="page-header__text">Przeanalizuj to pytanie na spokojnie. Zobacz poprawną odpowiedź i przeczytaj wyjaśnienie.</p>
        </div>
    </header>

    <div class="container">
        <main class="test-container" style="min-height: auto; padding-bottom: 3rem;">
            <div class="results-details">
                <section class="quiz-card">
                    <header class="quiz-card__header">
                        <span class="quiz-card__question-number">Podgląd pytania</span>
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
                        <?php foreach ($question['answers'] as $index => $answer): ?>
                            <?php 
                                $letter = chr(65 + $index);
                                $isCorrect = $answer['is_correct'] == 1;
                                $statusClass = $isCorrect ? 'correct' : '';
                            ?>
                            <div class="quiz-card__answer <?= $statusClass ?>">
                                <span class="quiz-card__answer-prefix"><?= $letter ?></span>
                                <span class="quiz-card__answer-text"><?= nl2br(htmlspecialchars($answer['answer_text'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="quiz-card__actions">
                        <?php if (!empty($question['explanation'])): ?>
                            <div class="quiz-card__button-container">
                                <button type="button" class="btn btn--secondary btn--small" id="toggle-explanation-btn">
                                    Pokaż wyjaśnienie
                                </button>
                            </div>
                            <div class="quiz-card__explanation" id="explanation-content">
                                <?= nl2br(htmlspecialchars($question['explanation'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div style="margin-top: 2rem; display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                <a href="<?= url('statystyki') ?>" class="btn btn--secondary">← Powrót do statystyk</a>
                <a href="<?= url('inf03-jedno-pytanie?premium=toImprove') ?>" class="btn btn--primary">Rozwiąż podobne</a>
            </div>
        </main>
    </div>

    <?php include 'partials/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('toggle-explanation-btn');
            const explanationContent = document.getElementById('explanation-content');

            if (toggleBtn && explanationContent) {
                toggleBtn.addEventListener('click', () => {
                    // Toggle visibility class used in main.css/TestRunner logic
                    const isVisible = explanationContent.classList.toggle('quiz-card__explanation--visible');
                    toggleBtn.textContent = isVisible ? 'Ukryj wyjaśnienie' : 'Pokaż wyjaśnienie';
                });
            }
        });
    </script>
</body>
</html>
