<?php

/**
 * @file sheets.php
 * @description
 * Biblioteka Arkuszy danej kwalifikacji — lista arkuszy pogrupowana po
 * tematach (sekcje typu akordeon) z poziomem trudności, miniaturkami,
 * filtrowaniem po temacie i zakresie trudności oraz oznaczeniem arkuszy
 * z zapisaną notatką użytkownika.
 *
 * @variables
 * - $exam (array): Kwalifikacja (id, code, name).
 * - $topics (array): Tematy wraz z listą arkuszy (klucz 'sheets').
 * - $isUserLoggedIn (bool): Flaga stanu autoryzacji.
 *
 * @version 2.0.0
 * @author Tobiasz Szerszeń
 */

$exam = $exam ?? [];
$topics = $topics ?? [];
$examCode = (string) ($exam['code'] ?? '');

// Mapowanie poziomu trudności (1-10) na grupę i etykietę
$difficultyMeta = static function (int $level): array {
  if ($level <= 3) {
    return ['easy', 'Łatwy'];
  }
  if ($level <= 6) {
    return ['medium', 'Średni'];
  }
  return ['hard', 'Trudny'];
};

$pageTitle = $pageTitle ?? ('Arkusze ' . $examCode . ' | Zdajnik');
$pageDescription = $pageDescription ?? ('Arkusze praktyczne ' . $examCode . ' z poziomem trudności i notatkami.');
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title">Arkusze <span class="text-gradient"><?= htmlspecialchars($examCode) ?></span></h1>
        <p class="page-header__text"><?= htmlspecialchars((string) ($exam['name'] ?? '')) ?> — przeglądaj arkusze
          pogrupowane po tematach, pobieraj pliki wraz z zasobami i zapisuj własne notatki, aby śledzić co już
          przerobiłeś.</p>
      </div>
    </header>

    <section class="sheets-page">
      <div class="sheets-page__container">

        <a href="<?= url('arkusze') ?>" class="back-link">
          <i class="fas fa-arrow-left"></i> Wróć do wyboru kwalifikacji
        </a>

        <?php if (empty($topics)): ?>
          <div class="empty-state">
            <p><i class="fas fa-folder-open"></i> Brak arkuszy dla tej kwalifikacji. Zajrzyj tu wkrótce!</p>
          </div>
        <?php else: ?>

          <!-- Filtry: temat + zakres trudności (obsługa w JS, bez przeładowania) -->
          <div class="sheets-filters" data-sheets-filters>
            <label class="sheets-filters__field">
              <span class="sheets-filters__label">Temat</span>
              <select class="sheets-filters__select" data-topic-filter>
                <option value="">Wszystkie tematy</option>
                <?php foreach ($topics as $topic): ?>
                  <option value="<?= (int) $topic['id'] ?>"><?= htmlspecialchars((string) $topic['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>

            <div class="sheets-filters__group" role="group" aria-label="Filtr poziomu trudności">
              <span class="sheets-filters__label">Trudność</span>
              <div class="sheets-filters__buttons">
                <button type="button" class="sheets-filters__btn is-active" data-difficulty-filter="">Wszystkie</button>
                <button type="button" class="sheets-filters__btn" data-difficulty-filter="easy">Łatwe (1-3)</button>
                <button type="button" class="sheets-filters__btn" data-difficulty-filter="medium">Średnie (4-6)</button>
                <button type="button" class="sheets-filters__btn" data-difficulty-filter="hard">Trudne (7-10)</button>
              </div>
            </div>
          </div>

          <p class="sheets-page__no-results" data-sheets-empty hidden>Żaden arkusz nie pasuje do wybranych filtrów.</p>

          <?php foreach ($topics as $topic): ?>
            <section class="sheets-topic is-open" data-sheets-topic data-topic-id="<?= (int) $topic['id'] ?>">
              <button type="button" class="sheets-topic__header" data-sheets-topic-toggle aria-expanded="true">
                <div class="sheets-topic__heading">
                  <h2 class="sheets-topic__title"><?= htmlspecialchars((string) $topic['name']) ?></h2>
                  <?php if (!empty($topic['description'])): ?>
                    <p class="sheets-topic__description"><?= htmlspecialchars((string) $topic['description']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="sheets-topic__meta">
                  <span class="badge badge--secondary"><?= count($topic['sheets']) ?> ark.</span>
                  <i class="sheets-topic__arrow fas fa-chevron-down"></i>
                </div>
              </button>

              <div class="sheets-topic__body">
                <ul class="sheets-topic__list">
                  <?php foreach ($topic['sheets'] as $sheet): ?>
                    <?php
                    $difficulty = (int) $sheet['difficulty'];
                    [$difficultyGroup, $difficultyLabel] = $difficultyMeta($difficulty);
                    $hasNote = !empty($sheet['has_note']);
                    ?>
                    <li class="sheets-topic__item" data-sheet-item data-topic-id="<?= (int) $topic['id'] ?>"
                      data-difficulty-group="<?= $difficultyGroup ?>">
                      <a href="<?= url('arkusze/' . exam_code_to_url($examCode) . '/' . (int) $sheet['id']) ?>" class="sheet-card">
                        <?php if (!empty($sheet['thumbnail_path'])): ?>
                          <img class="sheet-card__thumb"
                            src="<?= url('files/sheets/' . rawurlencode((string) $sheet['thumbnail_path'])) ?>"
                            alt="Miniaturka arkusza <?= htmlspecialchars((string) $sheet['title']) ?>" loading="lazy">
                        <?php else: ?>
                          <div class="sheet-card__thumb sheet-card__thumb--placeholder"><i class="fas fa-file-pdf"></i></div>
                        <?php endif; ?>
                        <div class="sheet-card__main">
                          <span class="sheet-card__title"><?= htmlspecialchars((string) $sheet['title']) ?></span>
                          <div class="sheet-card__meta">
                            <?php if (!empty($sheet['has_zip'])): ?>
                              <span class="sheet-card__meta-item"><i class="fas fa-file-zipper"></i> Rozwiązanie</span>
                            <?php endif; ?>
                            <?php if (!empty($sheet['has_resources'])): ?>
                              <span class="sheet-card__meta-item"><i class="fas fa-box-archive"></i> Zasoby</span>
                            <?php endif; ?>
                            <span class="sheet-card__meta-item"><i class="far fa-calendar"></i>
                              <?= date('d.m.Y', strtotime((string) $sheet['created_at'])) ?></span>
                          </div>
                        </div>
                        <div class="sheet-card__side">
                          <?php if ($hasNote): ?>
                            <span class="sheet-card__note-badge" title="Masz zapisaną notatkę do tego arkusza (Twoja ocena: <?= (int) $sheet['user_difficulty'] ?>/10)">
                              <i class="fas fa-note-sticky"></i> Masz notatkę
                            </span>
                          <?php endif; ?>
                          <span class="difficulty-badge difficulty-badge--<?= $difficultyGroup ?>"
                            title="Poziom trudności: <?= $difficultyLabel ?>">
                            <?= $difficulty ?>/10
                          </span>
                          <i class="sheet-card__arrow fas fa-chevron-right"></i>
                        </div>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </section>
          <?php endforeach; ?>

        <?php endif; ?>
      </div>
    </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
