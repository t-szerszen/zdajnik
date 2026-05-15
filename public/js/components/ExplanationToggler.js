class ExplanationToggler {
  /**
   * Inicjalizuje instancję i automatycznie nasłuchuje na gotowość DOM.
   * @param {string} selector - Selektor CSS dla przycisków (domyślnie .toggle-explanation-btn)
   */
  constructor(selector = '.toggle-explanation-btn') {
    this.selector = selector;

    // Automatyczna inicjalizacja po załadowaniu DOM
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.init());
    } else {
      this.init();
    }
  }

  /**
   * Wyszukuje przyciski w DOM i przypina zdarzenia.
   */
  init() {
    const buttons = document.querySelectorAll(this.selector);
    buttons.forEach(btn => {
      const targetId = btn.dataset.target;
      const content = document.getElementById(targetId);
      if (content) {
        this.attach(btn, content);
      }
    });
  }

  /**
   * Metoda do ręcznego podpinania funkcjonalności pod parę przycisk-kontener.
   * Przydatna w dynamicznie generowanych widokach (np. SPA).
   * 
   * @param {HTMLElement} button - Element przycisku
   * @param {HTMLElement} content - Element z treścią wyjaśnienia
   */
  attach(button, content) {
    if (!button || !content) return;

    // Jeśli przycisk nie ma jeszcze ikony, upewnijmy się, że ma domyślny stan
    if (!button.querySelector('i')) {
      button.innerHTML = '<i class="fas fa-lightbulb"></i> Pokaż wyjaśnienie';
    }

    // Zabezpieczenie przed wielokrotnym podpięciem
    if (button.dataset.explanationToggledInit) return;
    button.dataset.explanationToggledInit = 'true';

    button.addEventListener('click', (e) => {
      e.preventDefault();
      const isVisible = content.classList.toggle('quiz-card__explanation--visible');
      this.updateButtonState(button, isVisible);
    });
  }

  /**
   * Aktualizuje wygląd przycisku w zależności od stanu widoczności.
   * 
   * @param {HTMLElement} button 
   * @param {boolean} isVisible 
   */
  updateButtonState(button, isVisible) {
    if (isVisible) {
      button.innerHTML = '<i class="fas fa-eye-slash"></i> Ukryj wyjaśnienie';
    } else {
      button.innerHTML = '<i class="fas fa-lightbulb"></i> Pokaż wyjaśnienie';
    }
  }
}

/**
 * Eksportujemy gotową instancję (Singleton), podobnie jak w Toast.js.
 * Dzięki temu po zaimportowaniu skrypt automatycznie zacznie działać
 * (nasłuchując na DOMContentLoaded).
 */
export default new ExplanationToggler();
