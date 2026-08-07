<?php

/**
 * @file question_public.php
 * @description
 * Publiczna, indeksowalna strona pojedynczego pytania (/pytania/{kod}/{slug}-{id}).
 *
 * Cała treść — pytanie, wszystkie warianty odpowiedzi, wskazanie poprawnej oraz
 * wyjaśnienie — jest renderowana serwerowo i obecna w HTML bez udziału JavaScriptu.
 * Widok nie wymaga logowania i celowo nie ustawia $noIndex.
 *
 * @variables
 * - $question (array): Dane pytania wraz z exam_code, topic_name, difficulty, source.
 * - $answers (array): Warianty odpowiedzi w kolejności answer_order.
 * - $similar (array): Pytania z tego samego tematu (linkowanie wewnętrzne).
 * - $prevQuestion (array|null), $nextQuestion (array|null): Nawigacja liniowa.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

$question = $question ?? [];
$answers = $answers ?? [];
$similar = $similar ?? [];
$prevQuestion = $prevQuestion ?? null;
$nextQuestion = $nextQuestion ?? null;

$examCode = (string) ($question['exam_code'] ?? '');
$examUrlCode = exam_code_to_url($examCode);
$topicName = (string) ($question['topic_name'] ?? '');
$questionText = (string) ($question['question_text'] ?? '');
$explanation = (string) ($question['explanation'] ?? '');
$headline = question_excerpt($questionText, 110);

// Treść pytania w markdownie rozbita na nagłówek (<h1>) i ewentualne dalsze
// bloki (kod, listy), których nie wolno zagnieżdżać w nagłówku.
$questionParts = render_question_markdown_heading($questionText);

// Rozdzielenie wariantów na potrzeby danych strukturalnych: Google oczekuje
// poprawnej odpowiedzi w acceptedAnswer, a wyłącznie dystraktorów w suggestedAnswer.
$correctAnswers = [];
$incorrectAnswers = [];
foreach ($answers as $index => $answer) {
  $entry = [
    '@type' => 'Answer',
    'text' => question_excerpt((string) $answer['answer_text'], 400),
    'position' => $index + 1,
  ];

  if ((int) $answer['is_correct'] === 1) {
    if ($explanation !== '') {
      $entry['comment'] = ['@type' => 'Comment', 'text' => question_excerpt($explanation, 1000)];
    }
    $correctAnswers[] = $entry;
  } else {
    $incorrectAnswers[] = $entry;
  }
}

$questionSchema = [
  '@type' => 'Question',
  'eduQuestionType' => 'Multiple choice',
  'learningResourceType' => 'Practice problem',
  'name' => $headline,
  'text' => question_excerpt($questionText, 1500),
];

if (!empty($incorrectAnswers)) {
  $questionSchema['suggestedAnswer'] = $incorrectAnswers;
}

if (!empty($correctAnswers)) {
  $questionSchema['acceptedAnswer'] = count($correctAnswers) === 1 ? $correctAnswers[0] : $correctAnswers;
}

$extraSchemas = [
  [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
      ['@type' => 'ListItem', 'position' => 1, 'name' => 'Strona główna', 'item' => url('/')],
      ['@type' => 'ListItem', 'position' => 2, 'name' => 'Baza pytań', 'item' => url('pytania')],
      ['@type' => 'ListItem', 'position' => 3, 'name' => 'Pytania ' . $examCode, 'item' => url('pytania/' . $examUrlCode)],
      ['@type' => 'ListItem', 'position' => 4, 'name' => question_excerpt($questionText, 70), 'item' => $canonicalUrl ?? ''],
    ],
  ],
  [
    '@context' => 'https://schema.org',
    '@type' => 'Quiz',
    'name' => $headline,
    'url' => $canonicalUrl ?? '',
    'about' => ['@type' => 'Thing', 'name' => $topicName !== '' ? $topicName : $examCode],
    'educationalLevel' => 'Technikum / szkoła branżowa',
    'hasPart' => $questionSchema,
  ],
];
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="review-page">
  <?php include 'partials/navbar.php'; ?>

  <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">Podgląd</span> pytania</h1>
      <p class="page-header__text">Pojedyncze pytanie z bazy egzaminacyjnej wraz z poprawną odpowiedzią i
        wyjaśnieniem. Przeglądaj bez logowania, a gdy będziesz gotowy, sprawdź się w pełnym teście próbnym.</p>
    </div>
  </header>

  <main class="main-content-wrapper">
    <nav class="breadcrumbs" aria-label="Ścieżka nawigacji">
      <a href="<?= url('/') ?>">Strona główna</a>
      <span class="breadcrumbs__separator" aria-hidden="true">/</span>
      <a href="<?= url('pytania') ?>">Baza pytań</a>
      <span class="breadcrumbs__separator" aria-hidden="true">/</span>
      <a href="<?= url('pytania/' . $examUrlCode) ?>"><?= htmlspecialchars($examCode) ?></a>
      <span class="breadcrumbs__separator" aria-hidden="true">/</span>
      <span class="breadcrumbs__current"><?= htmlspecialchars(question_excerpt($questionText, 60)) ?></span>
    </nav>

    <section class="questions-page questions-page--detail">
      <div class="questions-page__container questions-page__container--narrow">

        <article class="quiz-card question-detail">
          <div class="quiz-card__header">
            <div class="quiz-card__header-left">
              <span class="quiz-card__id">ID: <?= (int) $question['question_id'] ?></span>
              <?php if ($topicName !== ''): ?>
                <span class="badge badge--secondary"><?= htmlspecialchars($topicName) ?></span>
              <?php endif; ?>
              <?php if (question_difficulty_label($question['difficulty'] ?? null) !== ''): ?>
                <span class="badge <?= question_difficulty_badge_class($question['difficulty']) ?>">
                  <?= question_difficulty_label($question['difficulty']) ?>
                </span>
              <?php endif; ?>
            </div>
            <div class="quiz-card__header-actions">
              <a href="<?= url('pytania/' . $examUrlCode) ?>" class="badge badge--accent"><?= htmlspecialchars($examCode) ?></a>
            </div>
          </div>

          <?php if (!empty($question['source'])): ?>
            <p class="quiz-card__source">
              <i class="fas fa-book" aria-hidden="true"></i> Źródło: <?= htmlspecialchars((string) $question['source']) ?>
            </p>
          <?php endif; ?>

          <div class="quiz-card__content">
            <h1 class="question-detail__title">
              <?= $questionParts['heading'] !== '' ? $questionParts['heading'] : htmlspecialchars($headline) ?>
            </h1>
            <?php if ($questionParts['rest'] !== ''): ?>
              <div class="quiz-card__question-text"><?= $questionParts['rest'] ?></div>
            <?php endif; ?>

            <?php if (!empty($question['image_name'])): ?>
              <div class="quiz-card__image-container">
                <img src="<?= url('images/questions/' . $question['image_name']) ?>"
                  alt="Ilustracja do pytania <?= htmlspecialchars($examCode) ?> nr <?= (int) $question['question_id'] ?>"
                  class="quiz-card__image" loading="lazy">
              </div>
            <?php endif; ?>
          </div>

          <h2 class="question-detail__subtitle">Warianty odpowiedzi</h2>
          <ol class="quiz-card__answers question-detail__answers">
            <?php foreach ($answers as $index => $answer): ?>
              <?php $isCorrect = (int) $answer['is_correct'] === 1; ?>
              <li class="quiz-card__answer <?= $isCorrect ? 'quiz-card__answer--correct' : '' ?>">
                <span class="quiz-card__answer-prefix"><?= chr(65 + $index) ?></span>
                <span class="quiz-card__answer-text">
                  <?= render_question_markdown((string) $answer['answer_text']) ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ol>

          <?php if ($explanation !== ''): ?>
            <section class="quiz-card__explanation question-detail__explanation">
              <h2 class="explanation-title">
                <i class="fas fa-lightbulb" aria-hidden="true"></i> Wyjaśnienie
              </h2>
              <div class="explanation-inner">
                <?= render_question_markdown($explanation) ?>
              </div>
            </section>
          <?php endif; ?>
        </article>

        <section class="questions-cta">
          <h2 class="questions-cta__title">Sprawdź się w praktyce</h2>
          <p class="questions-cta__text">Rozwiąż pełny test próbny <?= htmlspecialchars($examCode) ?> albo wylosuj
            pojedyncze pytanie na szybką powtórkę.</p>
          <div class="questions-cta__actions">
            <a href="<?= url('test/' . exam_code_to_url($examCode)) ?>" class="btn btn--primary btn--sm">
              <i class="fas fa-file-pen"></i> Test próbny <?= htmlspecialchars($examCode) ?>
            </a>
            <a href="<?= url('jedno-pytanie/' . exam_code_to_url($examCode)) ?>" class="btn btn--secondary btn--sm">
              <i class="fas fa-bolt"></i> Jedno pytanie
            </a>
            <a href="<?= url('personalizowany-test/' . exam_code_to_url($examCode)) ?>" class="btn btn--secondary btn--sm">
              <i class="fas fa-sliders"></i> Własny test
            </a>
          </div>
        </section>

        <?php if (!empty($similar)): ?>
          <section class="question-similar">
            <h2 class="question-similar__title">Podobne pytania<?= $topicName !== '' ? ' — ' . htmlspecialchars($topicName) : '' ?></h2>
            <ul class="question-similar__list">
              <?php foreach ($similar as $item): ?>
                <li class="question-similar__item">
                  <a href="<?= question_url($item) ?>">
                    <?= htmlspecialchars(question_excerpt((string) $item['question_text'], 130)) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>

        <nav class="question-adjacent" aria-label="Nawigacja między pytaniami">
          <?php if ($prevQuestion): ?>
            <a href="<?= question_url($prevQuestion) ?>" class="question-adjacent__link" rel="prev">
              <span class="question-adjacent__label"><i class="fas fa-chevron-left"></i> Poprzednie pytanie</span>
              <span class="question-adjacent__text">
                <?= htmlspecialchars(question_excerpt((string) $prevQuestion['question_text'], 80)) ?>
              </span>
            </a>
          <?php endif; ?>

          <?php if ($nextQuestion): ?>
            <a href="<?= question_url($nextQuestion) ?>" class="question-adjacent__link question-adjacent__link--next"
              rel="next">
              <span class="question-adjacent__label">Następne pytanie <i class="fas fa-chevron-right"></i></span>
              <span class="question-adjacent__text">
                <?= htmlspecialchars(question_excerpt((string) $nextQuestion['question_text'], 80)) ?>
              </span>
            </a>
          <?php endif; ?>
        </nav>

        <div class="questions-page__toolbar">
          <a href="<?= url('pytania/' . $examUrlCode) ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Wszystkie pytania <?= htmlspecialchars($examCode) ?>
          </a>
        </div>
      </div>
    </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
