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

export function initHomePage() {
  initRotatingWord();
  initSearchMockupPulse();
  initFeatureModal();
}
