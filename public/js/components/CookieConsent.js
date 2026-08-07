/**
 * Baner zgody na cookies analityczne - ładuje Google Analytics (GA4) i
 * Microsoft Clarity dopiero po akceptacji użytkownika, zamiast bezwarunkowo
 * na każdym wejściu.
 */
const STORAGE_KEY = 'cookie_consent';
const GA_MEASUREMENT_ID = 'G-VW9T6RRTHZ';
const CLARITY_PROJECT_ID = 'xyr8iau2cz';

function loadGoogleAnalytics() {
  // Już załadowane w tej wizycie (np. druga inicjalizacja po powrocie do banera) - nie dubluj.
  if (window.dataLayer) return;

  window.dataLayer = window.dataLayer || [];
  window.gtag = function gtag() {
    window.dataLayer.push(arguments);
  };
  window.gtag('js', new Date());
  window.gtag('config', GA_MEASUREMENT_ID);

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_MEASUREMENT_ID}`;
  document.head.appendChild(script);
}

function loadMicrosoftClarity() {
  if (window.clarity) return;

  (function (c, l, a, r, i) {
    c[a] = c[a] || function () {
      (c[a].q = c[a].q || []).push(arguments);
    };
    const t = l.createElement(r);
    t.async = 1;
    t.src = 'https://www.clarity.ms/tag/' + i;
    const y = l.getElementsByTagName(r)[0];
    y.parentNode.insertBefore(t, y);
  })(window, document, 'clarity', 'script', CLARITY_PROJECT_ID);
}

function loadAnalytics() {
  loadGoogleAnalytics();
  loadMicrosoftClarity();
}

export class CookieConsent {
  constructor() {
    this.banner = document.getElementById('cookie-banner');
    this.init();
  }

  init() {
    const consent = this.getStoredConsent();

    if (consent === 'accepted') {
      loadAnalytics();
    } else if (consent === null && this.banner) {
      this.banner.hidden = false;
    }

    this.banner?.querySelector('[data-cookie-accept]')?.addEventListener('click', () => this.setConsent('accepted'));
    this.banner?.querySelector('[data-cookie-reject]')?.addEventListener('click', () => this.setConsent('rejected'));

    // Link w stopce ("Ustawienia cookies") - pozwala wrócić do decyzji w każdej chwili.
    document.querySelectorAll('[data-cookie-manage]').forEach((button) => {
      button.addEventListener('click', () => {
        if (this.banner) this.banner.hidden = false;
      });
    });
  }

  getStoredConsent() {
    try {
      return localStorage.getItem(STORAGE_KEY);
    } catch (e) {
      // Brak dostępu do localStorage (np. tryb prywatny w niektórych przeglądarkach) -
      // nie pokazuj banera w kółko na każdej stronie, po prostu nie ładuj GA.
      return 'rejected';
    }
  }

  setConsent(value) {
    try {
      localStorage.setItem(STORAGE_KEY, value);
    } catch (e) {
      // Decyzja obowiązuje wtedy tylko do końca tej wizyty.
    }

    if (this.banner) {
      this.banner.hidden = true;
    }

    if (value === 'accepted') {
      loadAnalytics();
    }
  }
}

export function initCookieConsent() {
  return new CookieConsent();
}
