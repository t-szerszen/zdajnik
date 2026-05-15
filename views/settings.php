<?php

/**
 * @file settings.php
 * @description
 * Widok ustawień użytkownika w architekturze MVC. 
 * Obsługuje dynamiczne renderowanie formularzy w zależności od dostawcy 
 * tożsamości (Local vs Google) oraz wyświetlanie komunikatów zwrotnych (Flash Messages).
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 */

$noIndex = true;
$noFollow = true;
$pageTitle = 'Ustawienia Konta | Examly';
$pageDescription = 'Zarządzaj swoimi preferencjami i danymi konta w serwisie Examly.';
$canonicalUrl = 'https://www.examly.pl/ustawienia';

// Sprawdzenie typu zalogowanego użytkownika dla logiki UI
$isGoogleUser = ($currentUser->getAuthProvider() === 'google');
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <header class="page-header">
    <div class="page-header__content">
      <h1 class="page-header__title"><span class="text-gradient">Ustawienia</span> Konta</h1>
      <p class="page-header__text">Zarządzaj swoim profilem i dbaj o bezpieczeństwo swoich danych w Examly.</p>
    </div>
  </header>

  <main class="settings-page container">

    <?php
    /**
     * Blok komunikatów Flash.
     * Wyświetla błędy walidacji lub potwierdzenia sukcesu operacji.
     */
    if (isset($_SESSION['flash_message'])):
      $flash = $_SESSION['flash_message'];
      unset($_SESSION['flash_message']);
      ?>
      <div class="alert alert--<?= htmlspecialchars($flash['type']) ?>" role="alert">
        <?php if (isset($flash['errors'])): ?>
          <ul class="alert__list">
            <?php foreach ($flash['errors'] as $error): ?>
              <li class="alert__item"><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <?= htmlspecialchars($flash['text']) ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="settings-accordion" id="settings-accordion">

      <!-- Sekcja: Zdjęcie Profilowe (Avatar) -->
      <div class="settings-card <?= ($activeForm === 'avatar' || empty($activeForm)) ? 'is-open' : '' ?>" id="avatar-uploader">
        <div class="settings-card__header">
          <div class="settings-card__title-group">
            <h3 class="settings-card__title">
              <i class="fas fa-camera"></i>
              <span>Zdjęcie profilowe</span>
            </h3>
            <p class="settings-card__description">Wgraj swoje zdjęcie lub wybierz gotowy awatar.</p>
          </div>
          <i class="fas fa-chevron-down settings-card__icon"></i>
        </div>
        <div class="settings-card__content avatar-section-content">
          
          <div class="avatar-manager">
            <div class="avatar-manager__preview">
              <div class="avatar-manager__img-wrapper" id="avatar-loader">
                <?php 
                  $currentAvatarPath = $currentUser->getAvatarPath();
                  $avatarSrc = $currentAvatarPath ? url('images/avatars/' . $currentAvatarPath) : url('images/default-avatar.png');
                ?>
                <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Twój profil" id="avatar-preview-img" class="avatar-manager__img">
                <div class="avatar-manager__spinner">
                  <i class="fas fa-circle-notch fa-spin"></i>
                </div>
              </div>
              <button class="btn btn--outline" id="avatar-upload-btn">
                <i class="fas fa-upload"></i> Wgraj własne zdjęcie
              </button>
              <input type="file" id="avatar-file-input" accept="image/jpeg, image/png, image/webp" style="display: none;">
            </div>

            <div class="avatar-manager__divider"><span>LUB</span></div>

            <div class="avatar-manager__defaults">
              <p class="avatar-manager__defaults-title">Wybierz gotowy awatar:</p>
              <div class="avatar-manager__grid">
                <!-- Przygotowane domyślne awatary -->
                <button class="default-avatar-btn" data-src="defaults/1.svg">
                  <img src="<?= url('images/avatars/defaults/1.svg') ?>" alt="Default 1">
                </button>
                <button class="default-avatar-btn" data-src="defaults/2.svg">
                  <img src="<?= url('images/avatars/defaults/2.svg') ?>" alt="Default 2">
                </button>
                <button class="default-avatar-btn" data-src="defaults/3.svg">
                  <img src="<?= url('images/avatars/defaults/3.svg') ?>" alt="Default 3">
                </button>
                <button class="default-avatar-btn" data-src="defaults/4.svg">
                  <img src="<?= url('images/avatars/defaults/4.svg') ?>" alt="Default 4">
                </button>
                <button class="default-avatar-btn" data-src="defaults/5.svg">
                  <img src="<?= url('images/avatars/defaults/5.svg') ?>" alt="Default 5">
                </button>
                <button class="default-avatar-btn" data-src="defaults/6.svg">
                  <img src="<?= url('images/avatars/defaults/6.svg') ?>" alt="Default 6">
                </button>
                <button class="default-avatar-btn" data-src="defaults/7.svg">
                  <img src="<?= url('images/avatars/defaults/7.svg') ?>" alt="Default 7">
                </button>
                <button class="default-avatar-btn" data-src="defaults/8.svg">
                  <img src="<?= url('images/avatars/defaults/8.svg') ?>" alt="Default 8">
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Sekcja: Dane Osobowe -->
      <div class="settings-card <?= ($activeForm === 'name') ? 'is-open' : '' ?>">
        <div class="settings-card__header">
          <div class="settings-card__title-group">
            <h3 class="settings-card__title">
              <i class="fas fa-id-card"></i>
              <span>Dane osobowe</span>
            </h3>
            <p class="settings-card__description">Zaktualizuj swoje imię i nazwisko widoczne w profilu.</p>
          </div>
          <i class="fas fa-chevron-down settings-card__icon"></i>
        </div>
        <div class="settings-card__content">
          <form method="POST" action="<?= url('ustawienia/change_name') ?>">
            <input type="hidden" name="form_type" value="change_name">

            <div class="form-card__grid-2">
              <div class="form-card__group">
                <label for="first_name" class="form-card__label">Imię</label>
                <div class="form-card__input-wrapper">
                  <i class="fas fa-user form-card__input-icon"></i>
                  <input type="text" id="first_name" name="first_name"
                    class="form-card__input form-card__input--with-icon"
                    value="<?= htmlspecialchars($currentUser->getFirstName()) ?>" required>
                </div>
              </div>
              <div class="form-card__group">
                <label for="last_name" class="form-card__label">Nazwisko</label>
                <div class="form-card__input-wrapper">
                  <i class="fas fa-user-tag form-card__input-icon"></i>
                  <input type="text" id="last_name" name="last_name"
                    class="form-card__input form-card__input--with-icon"
                    value="<?= htmlspecialchars($currentUser->getLastName()) ?>" required>
                </div>
              </div>
            </div>

            <?php if (!$isGoogleUser): ?>
              <div class="form-card__group">
                <label for="password_for_name" class="form-card__label">Potwierdź aktualnym hasłem</label>
                <div class="password-wrapper">
                  <i class="fas fa-lock form-card__input-icon"></i>
                  <input type="password" id="password_for_name" name="password"
                    class="form-card__input form-card__input--with-icon" placeholder="Wpisz hasło" required>
                  <i class="fas fa-eye password-toggle-icon"></i>
                </div>
              </div>
            <?php endif; ?>

            <button type="submit" class="btn btn--primary">
              <i class="fas fa-save" style="margin-right: 0.5rem;"></i> Zaktualizuj profil
            </button>
          </form>
        </div>
      </div>

      <!-- Sekcja: Adres E-mail -->
      <div class="settings-card <?= ($activeForm === 'email') ? 'is-open' : '' ?>">
        <div class="settings-card__header">
          <div class="settings-card__title-group">
            <h3 class="settings-card__title">
              <i class="fas fa-envelope"></i>
              <span>Adres e-mail</span>
            </h3>
            <p class="settings-card__description">Twój aktualny adres:
              <strong><?= htmlspecialchars($currentUser->getEmail()) ?></strong>
            </p>
          </div>
          <i class="fas fa-chevron-down settings-card__icon"></i>
        </div>
        <div class="settings-card__content">
          <?php if ($isGoogleUser): ?>
            <div class="info-card__message">
              <i class="fab fa-google" style="margin-right: 0.5rem; color: var(--accent);"></i>
              Twoje konto jest powiązane z usługą Google. Adres e-mail można zmienić tylko w ustawieniach konta Google.
            </div>
          <?php else: ?>
            <div class="info-card__message">
              <i class="fas fa-info-circle" style="margin-right: 0.5rem; color: var(--accent);"></i>
              Po przejściu procesu zmiany adresu e-mail, konto zostanie automatycznie zweryfikowane.
            </div>
            <form method="POST" action="<?= url('ustawienia/change_email') ?>">
              <input type="hidden" name="form_type" value="change_email">
              <div class="form-card__group">
                <label for="new_email" class="form-card__label">Nowy adres e-mail</label>
                <div class="form-card__input-wrapper">
                  <i class="fas fa-at form-card__input-icon"></i>
                  <input type="email" id="new_email" name="new_email" class="form-card__input form-card__input--with-icon"
                    placeholder="np. jan.nowak@gmail.com" required>
                </div>
              </div>
              <div class="form-card__group">
                <label for="password_for_email" class="form-card__label">Potwierdź hasłem</label>
                <div class="password-wrapper">
                  <i class="fas fa-lock form-card__input-icon"></i>
                  <input type="password" id="password_for_email" name="password"
                    class="form-card__input form-card__input--with-icon" placeholder="Wpisz hasło" required>
                  <i class="fas fa-eye password-toggle-icon"></i>
                </div>
              </div>
              <button type="submit" id="changeEmailBtn" class="btn btn--primary"
                data-remaining="<?= $emailChangeRemaining ?? 0 ?>"
                data-text="<i class='fas fa-paper-plane' style='margin-right: 0.5rem;'></i> Zmień e-mail"
                <?= ($emailChangeRemaining ?? 0) > 0 ? 'disabled' : '' ?>>
                <?php if (($emailChangeRemaining ?? 0) > 0): ?>
                  Wyślij ponownie za <span id="countdown"><?= $emailChangeRemaining ?></span>s
                <?php else: ?>
                  <i class="fas fa-paper-plane" style="margin-right: 0.5rem;"></i> Zmień e-mail
                <?php endif; ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Sekcja: Zmiana Hasła (tylko dla lokalnych kont) -->
      <?php if (!$isGoogleUser): ?>
        <div class="settings-card <?= ($activeForm === 'password') ? 'is-open' : '' ?>">
          <div class="settings-card__header">
            <div class="settings-card__title-group">
              <h3 class="settings-card__title">
                <i class="fas fa-key"></i>
                <span>Zmiana hasła</span>
              </h3>
              <p class="settings-card__description">Zadbaj o bezpieczeństwo swojego konta regularnie zmieniając hasło.</p>
            </div>
            <i class="fas fa-chevron-down settings-card__icon"></i>
          </div>
          <div class="settings-card__content">
            <div class="pwd-requirements">
              <p class="pwd-requirements__title">Hasło powinno zawierać:</p>
              <ul class="req-list">
                <li>Minimum 8 znaków</li>
                <li>Wielką literę</li>
                <li>Cyfrę</li>
                <li>Znak specjalny</li>
              </ul>
            </div>
            <form method="POST" action="<?= url('ustawienia/change_password') ?>">
              <input type="hidden" name="form_type" value="change_password">
              <div class="form-card__group">
                <label for="current_password" class="form-card__label">Obecne hasło</label>
                <div class="password-wrapper">
                  <i class="fas fa-unlock-alt form-card__input-icon"></i>
                  <input type="password" id="current_password" name="current_password"
                    class="form-card__input form-card__input--with-icon" placeholder="Wpisz obecne hasło" required>
                  <i class="fas fa-eye password-toggle-icon"></i>
                </div>
              </div>
              <div class="form-card__group">
                <label for="new_password" class="form-card__label">Nowe hasło</label>
                <div class="password-wrapper">
                  <i class="fas fa-lock form-card__input-icon"></i>
                  <input type="password" id="new_password" name="new_password"
                    class="form-card__input form-card__input--with-icon" placeholder="Wpisz nowe hasło" required>
                  <i class="fas fa-eye password-toggle-icon"></i>
                </div>
              </div>
              <div class="form-card__group">
                <label for="confirm_new_password" class="form-card__label">Powtórz nowe hasło</label>
                <div class="password-wrapper">
                  <i class="fas fa-shield-alt form-card__input-icon"></i>
                  <input type="password" id="confirm_new_password" name="confirm_new_password"
                    class="form-card__input form-card__input--with-icon" placeholder="Powtórz nowe hasło" required>
                  <i class="fas fa-eye password-toggle-icon"></i>
                </div>
              </div>
              <button type="submit" class="btn btn--primary">
                <i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i> Zmień hasło
              </button>
              <p><a href="<?= url('reset-hasla') ?>" class="form-card__forgot-password">Nie pamiętasz hasła?</a></p>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <!-- Sekcja: Zarządzanie Postępem -->
      <div class="settings-card <?= ($activeForm === 'progress') ? 'is-open' : '' ?>">
        <div class="settings-card__header">
          <div class="settings-card__title-group">
            <h3 class="settings-card__title">
              <i class="fas fa-history"></i>
              <span>Zarządzanie postępem</span>
            </h3>
            <p class="settings-card__description">Wyzeruj swój postęp i statystyki dla wybranego egzaminu.</p>
          </div>
          <i class="fas fa-chevron-down settings-card__icon"></i>
        </div>
        <div class="settings-card__content">
          <div class="alert alert--warning settings-card__alert">
            <i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i>
            <strong>Uwaga:</strong> Postęp we wskazanym egzaminie zostanie trwale wyzerowany. Anonimowe dane
            statystyczne z Twoich prób pozostaną w systemie w celach analitycznych.
          </div>
          <form method="POST" action="<?= url('ustawienia/anonymize_progress') ?>">
            <input type="hidden" name="form_type" value="anonymize_progress">

            <div class="form-card__group">
              <label for="exam_type_id" class="form-card__label">Wybierz egzamin</label>
              <div class="form-card__input-wrapper">
                <i class="fas fa-graduation-cap form-card__input-icon"></i>
                <select id="exam_type_id" name="exam_type_id" class="form-card__input form-card__input--with-icon"
                  required>
                  <option value="" disabled selected>-- Wybierz egzamin do wyzerowania --</option>
                  <?php if (!empty($startedExams)): ?>
                    <?php foreach ($startedExams as $exam): ?>
                      <option value="<?= htmlspecialchars($exam['id']) ?>"><?= htmlspecialchars($exam['code']) ?></option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="" disabled>Brak rozpoczętych egzaminów</option>
                  <?php endif; ?>
                </select>
              </div>
            </div>

            <?php if (!$isGoogleUser): ?>
              <div class="form-card__group">
                <label for="password_for_progress" class="form-card__label">Potwierdź hasłem</label>
                <div class="password-wrapper">
                  <i class="fas fa-lock form-card__input-icon"></i>
                  <input type="password" id="password_for_progress" name="password"
                    class="form-card__input form-card__input--with-icon" placeholder="Wpisz hasło dla bezpieczeństwa"
                    required>
                  <i class="fas fa-eye password-toggle-icon"></i>
                </div>
              </div>
            <?php endif; ?>
            <button type="submit" class="btn btn--danger btn--full-width" <?= empty($startedExams) ? 'disabled' : '' ?>>
              <i class="fas fa-eraser" style="margin-right: 0.5rem;"></i> Wyzeruj postęp
            </button>
          </form>
        </div>
      </div>

      <!-- Sekcja: Zarządzanie Kontem -->
      <div class="settings-card <?= ($activeForm === 'delete') ? 'is-open' : '' ?>">
        <div class="settings-card__header">
          <div class="settings-card__title-group">
            <h3 class="settings-card__title">
              <i class="fas fa-user-shield"></i>
              <span>Zarządzanie kontem</span>
            </h3>
            <p class="settings-card__description">Opcje związane z trwałym usunięciem konta.</p>
          </div>
          <i class="fas fa-chevron-down settings-card__icon"></i>
        </div>
        <div class="settings-card__content">
          <div class="alert alert--error settings-card__alert">
            <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
            <strong>Uwaga:</strong> Usunięcie konta spowoduje bezpowrotną utratę wszystkich postępów w nauce oraz
            statystyk egzaminacyjnych.
          </div>
          <form method="POST" action="<?= url('usun-konto') ?>">
            <?php if (!$isGoogleUser): ?>
              <div class="form-card__group">
                <label for="password_for_delete" class="form-card__label">Potwierdź hasłem, aby usunąć konto</label>
                <div class="password-wrapper">
                  <i class="fas fa-lock-open form-card__input-icon"></i>
                  <input type="password" id="password_for_delete" name="password"
                    class="form-card__input form-card__input--with-icon" placeholder="Wpisz hasło dla bezpieczeństwa"
                    required>
                  <i class="fas fa-eye password-toggle-icon"></i>
                </div>
              </div>
            <?php endif; ?>
            <button type="submit" class="btn btn--danger btn--full-width">
              <i class="fas fa-trash-alt" style="margin-right: 0.5rem;"></i> Usuń konto
            </button>
          </form>
        </div>
      </div>
  </main>

  <?php include 'partials/footer.php'; ?>

  <script type="module" src="<?= url('js/components/SettingsAccordion.js') ?>"></script>
  <script type="module" src="<?= url('js/components/show-password-toggle.js') ?>"></script>
  <script type="module">
    import CountdownButton from '<?= url('js/components/CountdownButton.js') ?>';
    import AvatarUploader from '<?= url('js/features/settings/AvatarUploader.js') ?>';
    
    <?php if (!$isGoogleUser): ?>
      new CountdownButton('changeEmailBtn');
    <?php endif; ?>
    
    // Inicjalizacja uploadera awatarów
    new AvatarUploader();
  </script>
</body>

</html>