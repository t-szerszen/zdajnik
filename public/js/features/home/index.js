const rotatingWords = ['spokojnie', 'krok po kroku', 'bez zgadywania', 'na 100%'];

function initRotatingWord() {
  const el = document.querySelector('[data-hero-rotating-word]');
  if (!el) return;

  let index = 0;
  setInterval(() => {
    index = (index + 1) % rotatingWords.length;
    el.style.opacity = '0';
    el.style.transform = 'translateY(8px)';
    window.setTimeout(() => {
      el.textContent = rotatingWords[index];
      el.style.opacity = '1';
      el.style.transform = 'translateY(0)';
    }, 180);
  }, 2500);
}

function initSearchMockupPulse() {
  const term = document.querySelector('[data-search-demo-term]');
  const item = document.querySelector('[data-search-demo-item]');
  if (!term || !item) return;

 const values = [
  'document.getElementById()',
  'querySelector()',
  'addEventListener()',
  'mysqli_connect()',
  'SELECT',
  'JOIN',
  'INSERT INTO',
  'pętla for',
  'formularz HTML',
  'flexbox'
  ];

  let idx = 0;

  setInterval(() => {
    idx = (idx + 1) % values.length;
    term.textContent = values[idx];
    item.classList.add('is-highlighted');
    window.setTimeout(() => item.classList.remove('is-highlighted'), 520);
  }, 2600);
}

function initFeatureModal() {
  const modal = document.querySelector('[data-feature-modal]');
  const dialog = modal?.querySelector('.hero-feature-modal__dialog');
  const title = modal?.querySelector('[data-feature-modal-title]');
  const text = modal?.querySelector('[data-feature-modal-text]');
  const icon = modal?.querySelector('[data-feature-modal-icon]');
  const cards = document.querySelectorAll('[data-feature-card]');

  if (!modal || !dialog || !title || !text || !icon || !cards.length) return;

  // Modal (position: fixed, z-index: --z-modal) jest w znaczniku zagnieżdżony wewnątrz
  // .hero-section/.home-page, które mają `isolation: isolate` (dla dekoracyjnego tła).
  // To tworzy lokalny kontekst stackowania, w którym wysoki z-index modala jest
  // porównywany tylko z rodzeństwem wewnątrz tej sekcji - w efekcie sticky .main-nav
  // (z-index: --z-nav) renderuje się NAD modalem zamiast pod jego przyciemnionym tłem.
  // Przeniesienie modala bezpośrednio pod <body> wyprowadza go poza te konteksty.
  document.body.appendChild(modal);

  let activeTrigger = null;

  const close = () => {
    modal.hidden = true;
    document.body.classList.remove('has-open-modal');
    activeTrigger?.focus();
    activeTrigger = null;
  };

  const open = (card) => {
    const details = card.querySelector('.hero-feature-card__details');
    activeTrigger = card;
    title.textContent = card.dataset.featureTitle || '';
    text.textContent = details?.textContent?.trim() || '';
    icon.innerHTML = `<i class="${card.dataset.featureIcon || 'fas fa-circle-info'}"></i>`;
    modal.hidden = false;
    document.body.classList.add('has-open-modal');
    dialog.focus();
  };

  cards.forEach((card) => {
    card.addEventListener('click', () => open(card));
  });

  modal.querySelectorAll('[data-feature-modal-close]').forEach((closeButton) => {
    closeButton.addEventListener('click', close);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) {
      close();
    }
  });
}

const UPDATE_SEEN_KEY = 'zdajnik:update-seen';

// localStorage bywa zablokowany (tryb prywatny, przeglądarki blokujące
// ciasteczka) - wtedy kropka "nowe" po prostu świeci przy każdej wizycie.
function readSeenVersion() {
  try {
    return window.localStorage.getItem(UPDATE_SEEN_KEY);
  } catch {
    return null;
  }
}

function saveSeenVersion(version) {
  try {
    window.localStorage.setItem(UPDATE_SEEN_KEY, version);
  } catch {
    // brak dostępu do storage - nic nie szkodzi
  }
}

function initUpdateAnnouncement() {
  const trigger = document.querySelector('[data-update-open]');
  const dialog = document.querySelector('[data-update-dialog]');
  if (!trigger || !dialog || typeof dialog.showModal !== 'function') return;

  const version = trigger.dataset.updateVersion || '';
  if (version && readSeenVersion() === version) {
    trigger.classList.add('is-seen');
  }

  const close = () => {
    if (dialog.open) dialog.close();
  };

  trigger.addEventListener('click', () => {
    dialog.showModal();
    document.body.classList.add('has-open-modal');
    trigger.classList.add('is-seen');
    if (version) saveSeenVersion(version);
  });

  dialog.querySelectorAll('[data-update-close]').forEach((button) => {
    button.addEventListener('click', close);
  });

  // Kliknięcie w przyciemnione tło (poza panelem) zamyka okno.
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) close();
  });

  dialog.addEventListener('close', () => {
    document.body.classList.remove('has-open-modal');
    trigger.focus();
  });
}

export function initHomePage() {
  initRotatingWord();
  initSearchMockupPulse();
  initFeatureModal();
  initUpdateAnnouncement();
}
