export class StatisticsTutorial {
  constructor() {
    this.root = document.querySelector('[data-stats-tutorial-root]');
    this.restartButton = document.querySelector('[data-restart-stats-tutorial]');
    this.storageKey = 'zdajnik.statistics.tutorial.v1.completed';
    this.currentStep = 0;
    this.isOpen = false;
    this.elements = this.createTutorialElements();
    this.steps = this.buildSteps();

    if (!this.root || !this.restartButton || this.steps.length === 0) {
      return;
    }

    this.bindEvents();
    this.autoStartIfFirstVisit();
  }

  buildSteps() {
    const steps = [
      {
        selector: '#stats-category-selector',
        title: 'Wybór egzaminu',
        description: 'Wybierz kwalifikację. Metryki i wykresy pokażą wyniki tylko dla tego egzaminu.'
      },
      {
        selector: '#stats-mastery-card',
        title: 'Twoje opanowanie materiału',
        description: 'Sprawdź, ile materiału masz już opanowane i które tematy wymagają powtórki.'
      },
      {
        selector: '#stats-trend-card',
        title: 'Trend skuteczności',
        description: 'Zobacz, czy Twoja skuteczność rośnie w kolejnych sesjach nauki.'
      },
      {
        selector: '#stats-key-metrics-card',
        title: 'Kluczowe wyniki',
        description: 'Tu znajdziesz najlepszy wynik, średni czas, liczbę testów i ostatni rezultat.'
      },
      {
        selector: '#criticalMistakes',
        title: 'Krytyczne błędy',
        description: 'To pytania, które najczęściej obniżają wynik. Zacznij powtórkę właśnie od nich.'
      },
      {
        selector: '#lastMistakes',
        title: 'Ostatnie błędy',
        description: 'Wróć do świeżych pomyłek, zanim utrwalą się złe odpowiedzi.'
      },
      {
        selector: '#savedQuestions',
        title: 'Zapisane pytania',
        description: 'To Twoja lista ważnych pytań. Używaj jej do szybkiej powtórki.'
      },
      {
        selector: '#examsHistory',
        title: 'Historia egzaminów',
        description: 'Porównuj wyniki testów i sprawdzaj, czy robisz stabilny postęp.'
      }
    ];

    return steps.filter((step) => document.querySelector(step.selector));
  }

  createTutorialElements() {
    const overlay = document.createElement('div');
    overlay.className = 'stats-tutorial__overlay hidden';

    const spotlight = document.createElement('div');
    spotlight.className = 'stats-tutorial__spotlight hidden';

    const panel = document.createElement('aside');
    panel.className = 'stats-tutorial__panel hidden';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-labelledby', 'stats-tutorial-title');
    panel.innerHTML = `
      <div class="stats-tutorial__panel-head">
        <p class="stats-tutorial__step-counter" id="stats-tutorial-counter"></p>
        <button type="button" class="stats-tutorial__close" data-tutorial-close aria-label="Zamknij samouczek">×</button>
      </div>
      <h2 class="stats-tutorial__title" id="stats-tutorial-title"></h2>
      <p class="stats-tutorial__description" id="stats-tutorial-description"></p>
      <div class="stats-tutorial__actions">
        <button type="button" class="btn btn--secondary btn--sm" data-tutorial-prev>Wstecz</button>
        <button type="button" class="btn btn--primary btn--sm" data-tutorial-next>Dalej</button>
      </div>
    `;

    document.body.append(overlay, spotlight, panel);

    return {
      overlay,
      spotlight,
      panel,
      counter: panel.querySelector('#stats-tutorial-counter'),
      title: panel.querySelector('#stats-tutorial-title'),
      description: panel.querySelector('#stats-tutorial-description'),
      closeBtn: panel.querySelector('[data-tutorial-close]'),
      prevBtn: panel.querySelector('[data-tutorial-prev]'),
      nextBtn: panel.querySelector('[data-tutorial-next]')
    };
  }

  bindEvents() {
    this.restartButton.addEventListener('click', () => this.start(true));
    this.elements.overlay.addEventListener('click', () => this.close());
    this.elements.closeBtn.addEventListener('click', () => this.close());
    this.elements.prevBtn.addEventListener('click', () => this.prevStep());
    this.elements.nextBtn.addEventListener('click', () => this.nextStep());

    document.addEventListener('keydown', (event) => {
      if (!this.isOpen) return;
      if (event.key === 'Escape') this.close();
      if (event.key === 'ArrowRight') this.nextStep();
      if (event.key === 'ArrowLeft') this.prevStep();
    });

    window.addEventListener('resize', () => {
      if (this.isOpen) this.renderStep();
    });
  }

  autoStartIfFirstVisit() {
    const completed = window.localStorage.getItem(this.storageKey) === '1';
    if (!completed) {
      this.start(false);
    }
  }

  start(isManualStart) {
    if (this.steps.length === 0) return;

    this.currentStep = 0;
    this.isOpen = true;
    this.elements.overlay.classList.remove('hidden');
    this.elements.spotlight.classList.remove('hidden');
    this.elements.panel.classList.remove('hidden');
    document.body.classList.add('stats-tutorial-active');

    this.renderStep();

    if (isManualStart) {
      window.localStorage.removeItem(this.storageKey);
    }
  }

  close() {
    this.isOpen = false;
    this.elements.overlay.classList.add('hidden');
    this.elements.spotlight.classList.add('hidden');
    this.elements.panel.classList.add('hidden');
    document.body.classList.remove('stats-tutorial-active');
    this.clearHighlights();
    window.localStorage.setItem(this.storageKey, '1');
  }

  prevStep() {
    if (this.currentStep === 0) return;
    this.currentStep -= 1;
    this.renderStep();
  }

  nextStep() {
    const isLastStep = this.currentStep === this.steps.length - 1;
    if (isLastStep) {
      this.close();
      return;
    }
    this.currentStep += 1;
    this.renderStep();
  }

  renderStep() {
    const step = this.steps[this.currentStep];
    if (!step) return;

    const target = document.querySelector(step.selector);
    if (!target) {
      this.nextStep();
      return;
    }

    this.clearHighlights();
    target.classList.add('stats-tutorial-target');
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });

    this.elements.counter.textContent = `Krok ${this.currentStep + 1} z ${this.steps.length}`;
    this.elements.title.textContent = step.title;
    this.elements.description.textContent = step.description;
    this.elements.prevBtn.disabled = this.currentStep === 0;
    this.elements.nextBtn.textContent = this.currentStep === this.steps.length - 1 ? 'Zakończ' : 'Dalej';

    this.positionSpotlight(target);
  }

  clearHighlights() {
    const highlighted = document.querySelectorAll('.stats-tutorial-target');
    highlighted.forEach((item) => item.classList.remove('stats-tutorial-target'));
  }

  positionSpotlight(target) {
    const rect = target.getBoundingClientRect();
    const padding = 10;

    this.elements.spotlight.style.top = `${rect.top - padding + window.scrollY}px`;
    this.elements.spotlight.style.left = `${rect.left - padding + window.scrollX}px`;
    this.elements.spotlight.style.width = `${rect.width + padding * 2}px`;
    this.elements.spotlight.style.height = `${rect.height + padding * 2}px`;
  }
}

export function initStatisticsTutorial() {
  return new StatisticsTutorial();
}
