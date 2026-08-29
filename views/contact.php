<?php
/**
 * @file contact.php
 * @description Publiczny widok kontaktu z formularzem wiadomości.
 *
 * @var array<string, string> $formData Dane przywracane po błędnej walidacji formularza.
 */

$pageTitle = 'Kontakt | Zdajnik';
$pageDescription = 'Skontaktuj się ze mną w sprawie pytań, błędów lub współpracy przy Zdajniku.';
$formData = $formData ?? [];
?>
<!DOCTYPE html>
<html lang="pl">
<?php include 'partials/head.php'; ?>

<body class="auth-page-background">
  <?php include 'partials/navbar.php'; ?>

  <main class="main-content-wrapper">
    <header class="page-header">
      <div class="page-header__content">
        <h1 class="page-header__title"><span class="text-gradient">Kontakt</span></h1>
        <p class="page-header__text">
          Masz pytanie, znalazłeś błąd albo chcesz podzielić się pomysłem? Napisz do mnie przez formularz.
        </p>
      </div>
    </header>

    <form method="POST" action="<?= url('kontakt') ?>" class="form-card form-card--wide contact-form" novalidate>
      <?= csrf_field() ?>

      <div class="contact-form__honeypot" aria-hidden="true">
        <label for="website">Strona internetowa</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
      </div>

      <div class="form-card__header">
        <div class="form-card__icon-circle">
          <i class="fas fa-envelope-open-text"></i>
        </div>
        <h2 class="form-card__title">Napisz do mnie</h2>
        <p class="form-card__subtitle">Odpowiadam na wiadomości dotyczące działania platformy i materiałów do nauki.</p>
      </div>

      <?php if (isset($_SESSION['flash_message'])):
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        ?>
        <div class="alert alert--<?= htmlspecialchars((string) ($flash['type'] ?? 'info')) ?>" role="alert">
          <?php if (isset($flash['errors']) && is_array($flash['errors'])): ?>
            <ul class="alert__list">
              <?php foreach ($flash['errors'] as $error): ?>
                <li><?= htmlspecialchars((string) $error) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <?= htmlspecialchars((string) ($flash['text'] ?? '')) ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="form-card__grid-2">
        <div class="form-card__group">
          <label for="name" class="form-card__label">Imię lub nazwa</label>
          <input
            type="text"
            id="name"
            name="name"
            class="form-card__input"
            value="<?= htmlspecialchars((string) ($formData['name'] ?? '')) ?>"
            placeholder="Np. Jan Kowalski"
            autocomplete="name"
            required>
        </div>

        <div class="form-card__group">
          <label for="email" class="form-card__label">Adres e-mail</label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-card__input"
            value="<?= htmlspecialchars((string) ($formData['email'] ?? '')) ?>"
            placeholder="twoj@email.pl"
            autocomplete="email"
            required>
        </div>
      </div>

      <div class="form-card__group">
        <label for="category" class="form-card__label">Kategoria wiadomości <span class="required">*</span></label>
        <select
          id="category"
          name="category"
          class="form-card__input"
          required>
          <option value="" disabled <?= empty($formData['category']) ? 'selected' : '' ?>>Wybierz kategorię...</option>
          <option value="Zgłoszenie błędu" <?= ($formData['category'] ?? '') === 'Zgłoszenie błędu' ? 'selected' : '' ?>>Zgłoszenie błędu na platformie</option>
          <option value="Problem z kontem" <?= ($formData['category'] ?? '') === 'Problem z kontem' ? 'selected' : '' ?>>Problem techniczny z kontem</option>
          <option value="Współpraca" <?= ($formData['category'] ?? '') === 'Współpraca' ? 'selected' : '' ?>>Współpraca / Biznes</option>
          <option value="Sugestia rozwoju" <?= ($formData['category'] ?? '') === 'Sugestia rozwoju' ? 'selected' : '' ?>>Sugestia / Pomysł na rozwój platformy</option>
          <option value="Inne" <?= ($formData['category'] ?? '') === 'Inne' ? 'selected' : '' ?>>Inne</option>
        </select>
      </div>

      <div class="form-card__group">
        <label for="subject" class="form-card__label">Krótki temat <span class="required">*</span></label>
        <input
          type="text"
          id="subject"
          name="subject"
          class="form-card__input"
          value="<?= htmlspecialchars((string) ($formData['subject'] ?? '')) ?>"
          placeholder="np. Pytanie nr 45 z INF.03 nie ładuje obrazka"
          required>
      </div>

      <div class="form-card__group">
        <label for="message" class="form-card__label">Wiadomość</label>
        <textarea
          id="message"
          name="message"
          class="form-card__input contact-form__textarea"
          placeholder="Opisz krótko sprawę."
          required><?= htmlspecialchars((string) ($formData['message'] ?? '')) ?></textarea>
      </div>

      <?php if (!empty($_ENV['TURNSTILE_SITE_KEY'])): ?>
      <div class="form-card__group" style="display: flex; justify-content: center; margin-top: 20px;">
        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($_ENV['TURNSTILE_SITE_KEY']) ?>" data-theme="auto" data-language="pl"></div>
      </div>
      <?php endif; ?>

      <div class="form-card__action-container">
        <button type="submit" class="btn btn--primary btn--full">
          <i class="fas fa-paper-plane"></i>
          Wyślij wiadomość
        </button>
      </div>

      <div class="form-card__footer">
        <p class="form-card__subtitle form-card__subtitle--small">
          Możesz też napisać bezpośrednio:
          <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL) ?>"><?= htmlspecialchars(CONTACT_EMAIL) ?></a>
        </p>
        <p class="form-card__subtitle form-card__subtitle--small">Lokalizacja: Leszno, Polska</p>
      </div>
    </form>
  </main>

  <?php include 'partials/footer.php'; ?>
  
  <?php if (!empty($_ENV['TURNSTILE_SITE_KEY'])): ?>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  <?php endif; ?>
</body>
</html>
