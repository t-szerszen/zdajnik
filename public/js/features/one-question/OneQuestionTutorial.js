export class OneQuestionTutorial {
  constructor() {
    this.root = document.querySelector('[data-one-question-tutorial-root]');
    this.restartButton = document.querySelector('[data-restart-one-question-tutorial]');
    this.storageKey = 'zdajnik.one_question.tutorial.v1.completed';
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
        selector: '#topic-main-categories',
        title: 'Wybór tematu',
        description: 'Zaznacz co najmniej jeden dział albo wybierz "Wszystkie pytania", żeby zacząć naukę pojedynczych pytań z wybranego zakresu.'
      },
      {
        selector: '[data-premium-filters-root]',
        title: 'Tryby nauki',
        description: 'Zalogowani użytkownicy mogą dodatkowo dobierać pytania: nieodkryte, słabe punkty, powtórki, ostatnie błędy lub zapisane. Ikona "?" obok pokazuje szczegółowy opis każdego trybu.'
      },
      {
        selector: '#start-learning-btn',
        title: 'Start nauki',
        description: 'Kliknij, aby załadować pierwsze pytanie z wybranych tematów.'
      },
      {
        selector: '#quiz-container',
        title: 'Obszar pytania',
        description: 'Tu pojawi się wylosowane pytanie wraz z odpowiedziami i wyjaśnieniem po sprawdzeniu odpowiedzi.'
      }
    ];

    return steps.filter((step) => document.querySelector(step.selector));
  }

  createTutorialElements() {
    const overlay = document.createElement('div');
    overlay.className = 'oneq-tutorial__overlay hidden';

    const spotlight = document.createElement('div');
    spotlight.className = 'oneq-tutorial__spotlight hidden';

    const panel = document.createElement('aside');
    panel.className = 'oneq-tutorial__panel hidden';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-labelledby', 'oneq-tutorial-title');
    panel.innerHTML = `
      <div class="oneq-tutorial__panel-head">
        <p class="oneq-tutorial__step-counter" id="oneq-tutorial-counter"></p>
        <button type="button" class="oneq-tutorial__close" data-tutorial-close aria-label="Zamknij samouczek">×</button>
      </div>
      <h2 class="oneq-tutorial__title" id="oneq-tutorial-title"></h2>
      <p class="oneq-tutorial__description" id="oneq-tutorial-description"></p>
      <div class="oneq-tutorial__actions">
        <button type="button" class="btn btn--secondary btn--sm" data-tutorial-prev>Wstecz</button>
        <button type="button" class="btn btn--primary btn--sm" data-tutorial-next>Dalej</button>
      </div>
    `;

    document.body.append(overlay, spotlight, panel);

    return {
      overlay,
      spotlight,
      panel,
      counter: panel.querySelector('#oneq-tutorial-counter'),
      title: panel.querySelector('#oneq-tutorial-title'),
      description: panel.querySelector('#oneq-tutorial-description'),
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
    document.body.classList.add('oneq-tutorial-active');

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
    document.body.classList.remove('oneq-tutorial-active');
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
    target.classList.add('oneq-tutorial-target');
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });

    this.elements.counter.textContent = `Krok ${this.currentStep + 1} z ${this.steps.length}`;
    this.elements.title.textContent = step.title;
    this.elements.description.textContent = step.description;
    this.elements.prevBtn.disabled = this.currentStep === 0;
    this.elements.nextBtn.textContent = this.currentStep === this.steps.length - 1 ? 'Zakończ' : 'Dalej';

    this.positionSpotlight(target);
  }

  clearHighlights() {
    const highlighted = document.querySelectorAll('.oneq-tutorial-target');
    highlighted.forEach((item) => item.classList.remove('oneq-tutorial-target'));
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

export function initOneQuestionTutorial() {
  return new OneQuestionTutorial();
}
