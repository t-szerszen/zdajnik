class PremiumOptionsTutorial {
  constructor(config = {}) {
    this.mode = config.mode || 'one-question';
    this.form = document.getElementById('topic-form');
    this.root = document.querySelector('[data-premium-filters-root]');
    this.trigger = document.querySelector('[data-premium-tutorial-trigger]');
    this.premiumCheckboxes = this.form ? [...this.form.querySelectorAll('.premium-checkbox')] : [];
    this.isOpen = false;
    this.currentStep = 0;
    this.storageKey = `examly.premium.tutorial.${this.mode}.completed`;
    this.elements = this.createUI();
    this.steps = this.buildSteps();
    this.pendingAutoStart = true;

    if (!this.form || !this.root || !this.trigger || this.steps.length === 0) return;

    this.bindEvents();
    this.updateTriggerState();
  }

  buildSteps() {
    const descriptions = {
      toDiscover: 'Pokazuje pytania, których jeszcze nie widziałeś. Idealny filtr, gdy chcesz szybko poszerzać zakres materiału.',
      toImprove: 'Kieruje Cię do pytań, w których skuteczność jest niższa. Najlepsza opcja, gdy chcesz podnieść wynik punktowo.',
      toRemind: 'Wybiera pytania najdawniej powtarzane, żeby odświeżać wiedzę zanim wypadnie z pamięci.',
      lastMistakes: 'Skupia się na ostatnio popełnionych błędach. Szybka pętla naprawcza po sesji testowej.',
      saved: 'Ładuje pytania zapisane przez Ciebie ręcznie. Dobre do pracy na własnej liście najtrudniejszych zagadnień.'
    };

    const orderedValues = ['toDiscover', 'toImprove', 'toRemind', 'lastMistakes', 'saved'];
    const steps = [];

    orderedValues.forEach((value) => {
      const checkbox = this.form.querySelector(`.premium-checkbox[value="${value}"]`);
      if (!checkbox) return;
      const label = checkbox.closest('.topic-selector__label');
      if (!label) return;

      const title = label.textContent.trim();
      steps.push({
        target: label,
        title,
        description: descriptions[value] || 'Opcja specjalna wspierająca inteligentny dobór pytań.'
      });
    });

    return steps;
  }

  createUI() {
    const overlay = document.createElement('div');
    overlay.className = 'premium-tutorial__overlay hidden';

    const spotlight = document.createElement('div');
    spotlight.className = 'premium-tutorial__spotlight hidden';

    const panel = document.createElement('aside');
    panel.className = 'premium-tutorial__panel hidden';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.innerHTML = `
      <div class="premium-tutorial__head">
        <p class="premium-tutorial__counter" data-premium-counter></p>
        <button type="button" class="premium-tutorial__close" data-premium-close aria-label="Zamknij poradnik">×</button>
      </div>
      <h3 class="premium-tutorial__title" data-premium-title></h3>
      <p class="premium-tutorial__description" data-premium-description></p>
      <div class="premium-tutorial__actions">
        <button type="button" class="btn btn--secondary btn--sm" data-premium-prev>Wstecz</button>
        <button type="button" class="btn btn--primary btn--sm" data-premium-next>Dalej</button>
      </div>
    `;

    document.body.append(overlay, spotlight, panel);

    return {
      overlay,
      spotlight,
      panel,
      counter: panel.querySelector('[data-premium-counter]'),
      title: panel.querySelector('[data-premium-title]'),
      description: panel.querySelector('[data-premium-description]'),
      closeBtn: panel.querySelector('[data-premium-close]'),
      prevBtn: panel.querySelector('[data-premium-prev]'),
      nextBtn: panel.querySelector('[data-premium-next]')
    };
  }

  bindEvents() {
    this.trigger.addEventListener('click', () => this.start(true));
    this.elements.overlay.addEventListener('click', () => this.close());
    this.elements.closeBtn.addEventListener('click', () => this.close());
    this.elements.prevBtn.addEventListener('click', () => this.prevStep());
    this.elements.nextBtn.addEventListener('click', () => this.nextStep());

    this.form.addEventListener('premiumoptions:statechange', () => this.updateTriggerState());
    this.observeRootVisibility();
    window.addEventListener('resize', () => {
      if (this.isOpen) this.renderStep();
    });
  }

  observeRootVisibility() {
    const observer = new MutationObserver(() => this.updateTriggerState());
    observer.observe(this.root, { attributes: true, attributeFilter: ['class'] });
  }

  updateTriggerState() {
    const isUserLoggedIn = window.examlyAppState?.isUserLoggedIn === true;
    const isAnyOptionEnabled = this.premiumCheckboxes.some((checkbox) => !checkbox.disabled);
    const isSectionVisible = this.isPremiumSectionVisible();
    const shouldShowTrigger = isUserLoggedIn && isSectionVisible;

    this.trigger.classList.toggle('hidden', !shouldShowTrigger);

    if (this.pendingAutoStart && shouldShowTrigger) {
      this.pendingAutoStart = false;
      const completed = window.localStorage.getItem(this.storageKey) === '1';
      if (!completed && isAnyOptionEnabled) {
        this.start(false);
      }
    }
  }

  isPremiumSectionVisible() {
    if (!this.root) return false;
    return !this.root.classList.contains('hidden');
  }

  start(isManualStart = false) {
    if (!this.steps.length) return;

    this.isOpen = true;
    this.currentStep = 0;
    document.body.classList.add('premium-tutorial-active');
    this.elements.overlay.classList.remove('hidden');
    this.elements.spotlight.classList.remove('hidden');
    this.elements.panel.classList.remove('hidden');

    if (isManualStart) {
      window.localStorage.removeItem(this.storageKey);
    }

    this.renderStep();
  }

  close() {
    this.isOpen = false;
    document.body.classList.remove('premium-tutorial-active');
    this.elements.overlay.classList.add('hidden');
    this.elements.spotlight.classList.add('hidden');
    this.elements.panel.classList.add('hidden');
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
    if (!step || !step.target) return;

    this.clearHighlights();
    step.target.classList.add('premium-tutorial-target');
    step.target.scrollIntoView({ behavior: 'smooth', block: 'center' });

    this.elements.counter.textContent = `Krok ${this.currentStep + 1} z ${this.steps.length}`;
    this.elements.title.textContent = step.title;
    this.elements.description.textContent = step.description;
    this.elements.prevBtn.disabled = this.currentStep === 0;
    this.elements.nextBtn.textContent = this.currentStep === this.steps.length - 1 ? 'Zakończ' : 'Dalej';

    const rect = step.target.getBoundingClientRect();
    const padding = 8;
    this.elements.spotlight.style.top = `${rect.top - padding + window.scrollY}px`;
    this.elements.spotlight.style.left = `${rect.left - padding + window.scrollX}px`;
    this.elements.spotlight.style.width = `${rect.width + padding * 2}px`;
    this.elements.spotlight.style.height = `${rect.height + padding * 2}px`;
  }

  clearHighlights() {
    document.querySelectorAll('.premium-tutorial-target').forEach((el) => {
      el.classList.remove('premium-tutorial-target');
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const config = window.examlyPremiumTutorialConfig || {};
  new PremiumOptionsTutorial(config);
});
