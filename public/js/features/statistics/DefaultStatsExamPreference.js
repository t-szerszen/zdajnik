export class DefaultStatsExamPreference {
  constructor() {
    this.storageKey = 'zdajnik.defaultStatsExamId';
    this.select = document.getElementById('category-select');
    this.root = document.querySelector('[data-default-stats-exam]');
    this.checkbox = document.querySelector('[data-default-stats-exam-checkbox]');

    if (!this.select || !this.root || !this.checkbox) {
      return;
    }

    this.availableExamIds = this.getAvailableExamIds();
    this.init();
  }

  init() {
    const savedExamId = this.getSavedExamId();

    if (savedExamId && !this.availableExamIds.has(savedExamId)) {
      this.clearSavedExamId();
    }

    const currentExamId = this.select.value;
    const validSavedExamId = this.getSavedExamId();

    if (!currentExamId && validSavedExamId && this.availableExamIds.has(validSavedExamId)) {
      this.redirectToExam(validSavedExamId);
      return;
    }

    this.updateState();
    this.bindEvents();
  }

  bindEvents() {
    this.select.addEventListener('change', () => this.updateState());
    this.checkbox.addEventListener('change', () => this.handleCheckboxChange());
  }

  getAvailableExamIds() {
    return new Set(
      [...this.select.options]
        .map((option) => option.value)
        .filter((value) => value !== '')
    );
  }

  getSavedExamId() {
    try {
      return window.localStorage.getItem(this.storageKey);
    } catch (error) {
      return null;
    }
  }

  saveExamId(examId) {
    if (!this.availableExamIds.has(examId)) {
      return;
    }

    try {
      window.localStorage.setItem(this.storageKey, examId);
    } catch (error) {
      // Brak dostępu do localStorage nie powinien blokować korzystania ze statystyk.
    }
  }

  clearSavedExamId() {
    try {
      window.localStorage.removeItem(this.storageKey);
    } catch (error) {
      // Ignorujemy środowiska bez dostępu do localStorage.
    }
  }

  handleCheckboxChange() {
    const currentExamId = this.select.value;

    if (!this.availableExamIds.has(currentExamId)) {
      this.checkbox.checked = false;
      return;
    }

    if (this.checkbox.checked) {
      this.saveExamId(currentExamId);
    } else {
      this.clearSavedExamId();
      this.clearCurrentUrlSelection();
    }

    this.updateState();
  }

  updateState() {
    const currentExamId = this.select.value;
    const hasRealExamSelected = this.availableExamIds.has(currentExamId);
    const savedExamId = this.getSavedExamId();

    this.root.classList.toggle('hidden', !hasRealExamSelected);
    this.checkbox.checked = hasRealExamSelected && savedExamId === currentExamId;
  }

  redirectToExam(examId) {
    const url = new URL(window.location.href);
    url.searchParams.set('category', examId);
    window.location.replace(url.toString());
  }

  clearCurrentUrlSelection() {
    const url = new URL(window.location.href);
    url.searchParams.delete('category');
    url.hash = '';
    window.history.replaceState({}, '', url.toString());
  }
}

export function initDefaultStatsExamPreference() {
  return new DefaultStatsExamPreference();
}
