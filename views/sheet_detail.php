<?php

/**
 * @file sheet_detail.php
 * @description
 * Szczegóły pojedynczego arkusza z Biblioteki Arkuszy: podgląd PDF, pliki
 * do pobrania (arkusz PDF, zasoby ZIP, przykładowe rozwiązanie ZIP —
 * z przyjaznymi nazwami pobieranych plików) oraz formularz prywatnej notatki
 * użytkownika (subiektywna trudność 1-10 + opis problemów) zapisywanej przez AJAX.
 *
 * @variables
 * - $sheet (array): Dane arkusza wraz z tematem ('topic_name') i kwalifikacją ('exam_code').
 * - $note (array|null): Notatka zalogowanego użytkownika lub null.
 * - $isUserLoggedIn (bool): Flaga stanu autoryzacji.
 *
 * @version 2.0.0
 * @author Tobiasz Szerszeń
 */

$sheet = $sheet ?? [];
$note = $note ?? null;
$examCode = (string) ($sheet['exam_code'] ?? '');

$difficulty = (int) ($sheet['difficulty'] ?? 1);
if ($difficulty <= 3) {
  $difficultyGroup = 'easy';
  $difficultyLabel = 'Łatwy';
} elseif ($difficulty <= 6) {
  $difficultyGroup = 'medium';
  $difficultyLabel = 'Średni';
} else {
  $difficultyGroup = 'hard';
  $difficultyLabel = 'Trudny';
}

$userDifficulty = (int) ($note['subjective_difficulty'] ?? 0);

// Przyjazne nazwy pobieranych plików wyprowadzone z tytułu arkusza
$downloadSlug = slugify($examCode . ' ' . (string) ($sheet['title'] ?? 'arkusz'));
$pdfUrl = url('files/sheets/' . rawurlencode((string) $sheet['pdf_path']));

$pageTitle = $pageTitle ?? 'Arkusz | Biblioteka Arkuszy | Zdajnik';
$pageDescription = $pageDescription ?? 'Szczegóły arkusza w Bibliotece Arkuszy Zdajnik.';
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
    <section class="sheets-page sheet-detail">
      <div class="sheets-page__container">

        <a href="<?= url('arkusze/' . rawurlencode($examCode)) ?>" class="back-link">
          <i class="fas fa-arrow-left"></i> Wróć do arkuszy <?= htmlspecialchars($examCode) ?>
        </a>

        <article class="sheet-detail__card">
          <header class="sheet-detail__header">
            <?php if (!empty($sheet['thumbnail_path'])): ?>
              <img class="sheet-detail__thumb"
                src="<?= url('files/sheets/' . rawurlencode((string) $sheet['thumbnail_path'])) ?>"
                alt="Miniaturka arkusza <?= htmlspecialchars((string) ($sheet['title'] ?? '')) ?>">
            <?php endif; ?>
            <div class="sheet-detail__heading">
              <div class="sheet-detail__badges">
                <span class="badge badge--accent"><?= htmlspecialchars($examCode) ?></span>
                <span class="badge badge--secondary"><?= htmlspecialchars((string) ($sheet['topic_name'] ?? '')) ?></span>
              </div>
              <h1 class="sheet-detail__title"><?= htmlspecialchars((string) ($sheet['title'] ?? '')) ?></h1>
            </div>
            <span class="difficulty-badge difficulty-badge--<?= $difficultyGroup ?>"
              title="Oficjalny poziom trudności arkusza">
              Trudność: <?= $difficulty ?>/10 · <?= $difficultyLabel ?>
            </span>
          </header>

          <?php if (!empty($sheet['description'])): ?>
            <p class="sheet-detail__description"><?= nl2br(htmlspecialchars((string) $sheet['description'])) ?></p>
          <?php endif; ?>

          <div class="sheet-detail__downloads">
            <a href="<?= $pdfUrl ?>" class="btn btn--primary" download="<?= $downloadSlug ?>.pdf">
              <i class="fas fa-file-pdf"></i> Pobierz PDF
            </a>
            <?php if (!empty($sheet['resources_path'])): ?>
              <a href="<?= url('files/sheets/' . rawurlencode((string) $sheet['resources_path'])) ?>"
                class="btn btn--secondary" download="<?= $downloadSlug ?>-zasoby.zip">
                <i class="fas fa-box-archive"></i> Pobierz zasoby (ZIP)
              </a>
            <?php endif; ?>
            <?php if (!empty($sheet['zip_path'])): ?>
              <a href="<?= url('files/sheets/' . rawurlencode((string) $sheet['zip_path'])) ?>"
                class="btn btn--secondary" download="<?= $downloadSlug ?>-rozwiazanie.zip">
                <i class="fas fa-file-zipper"></i> Pobierz przykładowe rozwiązanie (ZIP)
              </a>
            <?php else: ?>
              <span class="sheet-detail__no-zip"><i class="far fa-circle-question"></i> Ten arkusz nie ma jeszcze
                przykładowego rozwiązania.</span>
            <?php endif; ?>
          </div>

          <footer class="sheet-detail__meta">
            <span><i class="far fa-calendar"></i> Dodano:
              <?= date('d.m.Y', strtotime((string) ($sheet['created_at'] ?? 'now'))) ?></span>
          </footer>
        </article>

        <section class="sheet-preview" aria-labelledby="sheet-preview-title">
          <h2 id="sheet-preview-title" class="sheet-preview__title"><i class="fas fa-eye"></i> Podgląd arkusza</h2>
          <div class="sheet-preview__frame">
            <object data="<?= $pdfUrl ?>" type="application/pdf" aria-label="Podgląd PDF arkusza">
              <p class="sheet-preview__fallback">Twoja przeglądarka nie obsługuje podglądu PDF.
                <a href="<?= $pdfUrl ?>" download="<?= $downloadSlug ?>.pdf">Pobierz arkusz</a>, aby go otworzyć.
              </p>
            </object>
          </div>
        </section>

        <section class="sheet-note" aria-labelledby="sheet-note-title">
          <h2 id="sheet-note-title" class="sheet-note__title"><i class="fas fa-note-sticky"></i> Twoja notatka</h2>

          <?php if (!$isUserLoggedIn): ?>
            <div class="sheet-note__login-prompt">
              <p>Zaloguj się, aby ocenić trudność arkusza i zapisać notatkę o tym, co sprawiło Ci problem.</p>
              <a href="<?= url('logowanie') ?>" class="btn btn--primary"><i class="fas fa-sign-in-alt"></i> Zaloguj się</a>
            </div>
          <?php else: ?>
            <form class="sheet-note-form" data-sheet-note-form data-sheet-id="<?= (int) $sheet['id'] ?>">
              <fieldset class="sheet-note-form__scale">
                <legend class="sheet-note-form__label">Jak trudny był dla Ciebie ten arkusz? (1 — banalny, 10 — bardzo
                  trudny)</legend>
                <div class="difficulty-scale" role="radiogroup" aria-label="Subiektywna ocena trudności">
                  <?php for ($i = 1; $i <= 10; $i++): ?>
                    <label class="difficulty-scale__option">
                      <input type="radio" name="difficulty" value="<?= $i ?>" required
                        <?= $userDifficulty === $i ? 'checked' : '' ?>>
                      <span><?= $i ?></span>
                    </label>
                  <?php endfor; ?>
                </div>
              </fieldset>

              <label class="sheet-note-form__field">
                <span class="sheet-note-form__label">Co sprawiło Ci problem?</span>
                <textarea name="description" rows="5" maxlength="5000"
                  placeholder="Np. konfiguracja bazy danych, walidacja formularza, sekcja z JavaScript..."><?= htmlspecialchars((string) ($note['problem_description'] ?? '')) ?></textarea>
              </label>

              <div class="sheet-note-form__actions">
                <button type="submit" class="btn btn--primary"><i class="fas fa-floppy-disk"></i> Zapisz notatkę</button>
                <?php if ($note): ?>
                  <span class="sheet-note-form__saved-at">Ostatnia aktualizacja:
                    <?= date('d.m.Y H:i', strtotime((string) $note['updated_at'])) ?></span>
                <?php endif; ?>
              </div>
            </form>
          <?php endif; ?>
        </section>

      </div>
    </section>
  </main>

  <?php include 'partials/footer.php'; ?>
</body>

</html>
