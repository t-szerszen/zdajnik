export class PersonalizedTestTutorial {
  constructor() {
    this.root = document.querySelector('[data-personalized-test-tutorial-root]');
    this.restartButton = document.querySelector('[data-restart-personalized-test-tutorial]');
    this.configSteps = this.root ? [...this.root.querySelectorAll('.config-step')] : [];
    this.storageKey = 'zdajnik.personalized_test.tutorial.v1.completed';
    this.currentStep = 0;
    this.isOpen = false;
    this.elements = this.createTutorialElements();
    this.steps = this.buildSteps();

    if (!this.root || !this.restartButton || this.configSteps.length === 0 || this.steps.length === 0) {
      return;
    }

    this.bindEvents();
    this.autoStartIfFirstVisit();
  }

  buildSteps() {
    const steps = [
      {
        wizardStep: 1,
        selector: '[data-step="1"]',
        title: 'Wybór tematów',
        description: 'Zaznacz co najmniej jeden dział materiału albo wybierz "Cały materiał", aby objąć nim całą kwalifikację.'
      },
      {
        wizardStep: 2,
        selector: '[data-step="2"]',
        title: 'Liczba pytań',
        description: 'Suwakiem ustaw długość testu - od 10 do 40 pytań.'
      },
      {
        wizardStep: 3,
        selector: '[data-step="3"]',
        title: 'Tryby nauki (opcjonalnie)',
        description: 'Zalogowani użytkownicy mogą dodatkowo dobrać pytania: nieodkryte, słabe punkty, powtórki, ostatnie błędy lub zapisane. Szczegóły każdego trybu pokazuje ikona "?" obok tytułu.'
      },
      {
        wizardStep: 3,
        selector: '#start-test-btn',
        title: 'Start testu',
        description: 'Kliknij "Rozpocznij Test!", aby wygenerować pytania na podstawie wybranych ustawień.'
      }
    ];

    return steps.filter((step) => document.querySelector(step.selector));
  }

  createTutorialElements() {
    const overlay = document.createElement('div');
    overlay.className = 'pt-tutorial__overlay hidden';

    const spotlight = document.createElement('div');
    spotlight.className = 'pt-tutorial__spotlight hidden';

    const panel = document.createElement('aside');
    panel.className = 'pt-tutorial__panel hidden';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-labelledby', 'pt-tutorial-title');
    panel.innerHTML = `
      <div class="pt-tutorial__panel-head">
        <p class="pt-tutorial__step-counter" id="pt-tutorial-counter"></p>
        <button type="button" class="pt-tutorial__close" data-tutorial-close aria-label="Zamknij samouczek">×</button>
      </div>
      <h2 class="pt-tutorial__title" id="pt-tutorial-title"></h2>
      <p class="pt-tutorial__description" id="pt-tutorial-description"></p>
      <div class="pt-tutorial__actions">
        <button type="button" class="btn btn--secondary btn--sm" data-tutorial-prev>Wstecz</button>
        <button type="button" class="btn btn--primary btn--sm" data-tutorial-next>Dalej</button>
      </div>
    `;

    document.body.append(overlay, spotlight, panel);

    return {
      overlay,
      spotlight,
      panel,
      counter: panel.querySelector('#pt-tutorial-counter'),
      title: panel.querySelector('#pt-tutorial-title'),
      description: panel.querySelector('#pt-tutorial-description'),
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
    document.body.classList.add('pt-tutorial-active');

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
    document.body.classList.remove('pt-tutorial-active');
    this.clearHighlights();
    // Kreator wraca do stanu początkowego (widoczny tylko krok 1), niezależnie
    // od tego, który krok pokazywał samouczek w momencie zamknięcia.
    this.showOnlyWizardStep(1);
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

  /**
   * Samouczek prowadzi po krokach kreatora, które normalnie są ukrywane/odkrywane
   * dopiero po kliknięciu "Dalej"/"Wróć" (i walidacji) w PersonalizedTestPage.
   * Żeby pokazać dany krok bez wywoływania tamtej walidacji, samouczek sam
   * przełącza widoczność `.config-step` na czas swojego kroku.
   */
  showOnlyWizardStep(wizardStep) {
    this.configSteps.forEach((el) => {
      el.classList.toggle('hidden', Number(el.dataset.step) !== wizardStep);
    });
  }

  renderStep() {
    const step = this.steps[this.currentStep];
    if (!step) return;

    this.showOnlyWizardStep(step.wizardStep);

    const target = document.querySelector(step.selector);
    if (!target) {
      this.nextStep();
      return;
    }

    this.clearHighlights();
    target.classList.add('pt-tutorial-target');
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });

    this.elements.counter.textContent = `Krok ${this.currentStep + 1} z ${this.steps.length}`;
    this.elements.title.textContent = step.title;
    this.elements.description.textContent = step.description;
    this.elements.prevBtn.disabled = this.currentStep === 0;
    this.elements.nextBtn.textContent = this.currentStep === this.steps.length - 1 ? 'Zakończ' : 'Dalej';

    this.positionSpotlight(target);
  }

  clearHighlights() {
    const highlighted = document.querySelectorAll('.pt-tutorial-target');
    highlighted.forEach((item) => item.classList.remove('pt-tutorial-target'));
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

export function initPersonalizedTestTutorial() {
  return new PersonalizedTestTutorial();
}
