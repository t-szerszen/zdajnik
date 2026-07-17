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
        description:
          'Wszystko na tej stronie liczy się osobno dla każdej kwalifikacji. Wybierz tu egzamin, żeby zobaczyć wykresy i liczby tylko dla niego.'
      },
      {
        selector: '#stats-mastery-card',
        title: 'Twoje opanowanie materiału',
        description:
          'Pytanie liczy się jako „opanowane”, gdy odpowiadasz na nie poprawnie w co najmniej 60% prób. „Poznano” to po prostu liczba pytań, na które w ogóle już odpowiadałeś — nawet jeśli jeszcze ich nie umiesz. Niżej zobaczysz ten sam podział osobno dla każdego działu, żeby wiedzieć, gdzie brakuje Ci powtórek.'
      },
      {
        selector: '#stats-trend-card',
        title: 'Trend skuteczności',
        description:
          'Każdy punkt na wykresie to wynik jednego rozwiązanego testu, a gruba linia to średnia z ostatnich 5 podejść — dzięki niej widać, czy naprawdę robisz postępy, a nie tylko trafiłeś dobry dzień. Oś pozioma to kolejne testy, a nie kalendarz.'
      },
      {
        selector: '#stats-key-metrics-card',
        title: 'Kluczowe wyniki',
        description:
          'Najlepszy wynik i średni czas liczą się z Twoich pełnych testów, a „Ostatnia aktywność” to data dowolnej odpowiedzi — nawet jednego pytania w trybie „Jedno pytanie”. Przewidywany wynik egzaminu odblokuje się po 5 pełnych egzaminach — to średnia z ostatnich 5 podejść, w której najnowsze liczy się najbardziej.'
      },
      {
        selector: '#criticalMistakes',
        title: 'Krytyczne błędy',
        description:
          'To pytania, w których wypadasz poniżej 60% skuteczności — dokładnie ta sama zasada, co w trybie nauki „Słabe punkty”. Tutaj lista jest posortowana od najgorszego wyniku i odblokuje się dopiero po 100 odpowiedziach w tym egzaminie, bo dopiero wtedy widać Twoje prawdziwe słabe punkty, a nie przypadek.'
      },
      {
        selector: '#lastMistakes',
        title: 'Ostatnie błędy',
        description:
          'Tu widzisz pytania, w których Twoja ostatnia odpowiedź była błędna — to ten sam warunek, na którym działa tryb nauki „Ostatnie błędy”. Różnica jest taka, że tutaj lista jest posortowana od najświeższej pomyłki. Popraw błąd tu i teraz, zanim się utrwali.'
      },
      {
        selector: '#savedQuestions',
        title: 'Zapisane pytania',
        description:
          'To Twoja własna lista — pytania, które sam odłożyłeś przyciskiem „Zapisz” (serduszko przy pytaniu). To dokładnie ta sama lista, z której korzysta tryb nauki „Zapisane”. Wróć tu przed egzaminem, żeby powtórzyć to, co uznałeś za trudne.'
      },
      {
        selector: '#examsHistory',
        title: 'Historia egzaminów',
        description:
          'Ostatnie ukończone testy razem z wynikiem procentowym. Porównuj je, żeby sprawdzić, czy Twoja skuteczność naprawdę rośnie z czasem — pełną listę zobaczysz pod przyciskiem „zobacz wszystkie”.'
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
