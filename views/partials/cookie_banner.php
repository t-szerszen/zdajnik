<?php
/**
 * @file cookie_banner.php
 * @description
 * Baner zgody na cookies analityczne (Google Analytics). Domyślnie ukryty
 * atrybutem `hidden` - CookieConsent.js (public/js/components/CookieConsent.js)
 * pokazuje go tylko wtedy, gdy w localStorage nie ma jeszcze zapisanej decyzji
 * użytkownika, i steruje ładowaniem GA4 dopiero po kliknięciu "Akceptuj".
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */
?>
<div class="cookie-banner" id="cookie-banner" hidden>
  <p class="cookie-banner__text">
    Używamy plików cookies analitycznych (Google Analytics, Microsoft Clarity), żeby sprawdzać, co
    w Zdajniku działa, a co warto poprawić. Możesz je zaakceptować albo odrzucić - więcej informacji w
    <a href="<?= url('polityka-prywatnosci') ?>">polityce prywatności</a>.
  </p>
  <div class="cookie-banner__actions">
    <button type="button" class="btn btn--secondary btn--sm" data-cookie-reject>Odrzuć</button>
    <button type="button" class="btn btn--primary btn--sm" data-cookie-accept>Akceptuj</button>
  </div>
</div>
