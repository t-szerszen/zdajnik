const rotatingWords = ['spokojnie', 'skutecznie', 'metodycznie'];

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

function initSpotlight() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const landing = document.querySelector('.home-page');
  if (!landing) return;

  let raf = null;
  const onMove = (event) => {
    if (raf) return;
    raf = requestAnimationFrame(() => {
      const x = (event.clientX / window.innerWidth) * 100;
      const y = (event.clientY / window.innerHeight) * 100;
      landing.style.setProperty('--spotlight-x', `${Math.max(0, Math.min(100, x))}%`);
      landing.style.setProperty('--spotlight-y', `${Math.max(0, Math.min(100, y))}%`);
      raf = null;
    });
  };

  landing.addEventListener('pointermove', onMove, { passive: true });
  landing.addEventListener('pointerleave', () => {
    landing.style.setProperty('--spotlight-x', '50%');
    landing.style.setProperty('--spotlight-y', '16%');
  });
}

function initMagneticPrimaryCta() {
  const cta = document.querySelector('.hero-section__cta-primary');
  if (!cta || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  cta.addEventListener('pointermove', (event) => {
    const rect = cta.getBoundingClientRect();
    const x = event.clientX - rect.left - rect.width / 2;
    const y = event.clientY - rect.top - rect.height / 2;
    cta.style.transform = `translate(${x * 0.08}px, ${y * 0.12}px)`;
  });

  cta.addEventListener('pointerleave', () => {
    cta.style.transform = 'translate(0, 0)';
  });
}

function initSearchMockupPulse() {
  const term = document.querySelector('[data-search-demo-term]');
  const item = document.querySelector('[data-search-demo-item]');
  if (!term || !item) return;

  const values = ['402', 'router', 'baza danych'];
  let idx = 0;

  setInterval(() => {
    idx = (idx + 1) % values.length;
    term.textContent = values[idx];
    item.classList.add('is-highlighted');
    window.setTimeout(() => item.classList.remove('is-highlighted'), 520);
  }, 2600);
}

function initFeatureCards() {
  const cards = document.querySelectorAll('.feature-card');
  if (!cards.length || !window.matchMedia('(pointer: coarse)').matches) return;

  cards.forEach((card) => {
    card.addEventListener('click', () => {
      cards.forEach((item) => {
        if (item !== card) item.classList.remove('is-active');
      });
      card.classList.toggle('is-active');
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initRotatingWord();
  initSpotlight();
  initMagneticPrimaryCta();
  initSearchMockupPulse();
  initFeatureCards();
});
