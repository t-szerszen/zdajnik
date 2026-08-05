/**
 * @file FilterableList.js
 * @module FilterableList
 * @description
 * Generyczny handler dla wzorca "formularz filtrów + kontener wyników
 * renderowany po stronie serwera + podmiana AJAX-em" - wyodrębniony z
 * dawnego SearchHandler.js, żeby ta sama, sprawdzona logika (debounce,
 * anulowanie nieaktualnych zapytań, szkielety ładowania, historia URL-a,
 * delegacja kliknięć w paginację) obsługiwała każdą tego typu listę w
 * aplikacji - dziś: filtrowany listing pytań /pytania/{kod}.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */
export default class FilterableList {
  /**
   * @param {object} opts
   * @param {string} opts.formSelector - Selektor formularza z polami filtrów.
   * @param {string} opts.resultsSelector - Selektor kontenera podmienianego wynikami.
   * @param {string[]} [opts.debouncedFieldSelectors] - Pola tekstowe odpytywane z opóźnieniem (300ms).
   * @param {string[]} [opts.immediateFieldSelectors] - Pola (np. select) odpytywane natychmiast po zmianie.
   * @param {string} [opts.pageParam='strona'] - Nazwa parametru GET z numerem strony.
   * @param {number} [opts.debounceMs=300]
   */
  constructor({
    formSelector,
    resultsSelector,
    debouncedFieldSelectors = [],
    immediateFieldSelectors = [],
    pageParam = 'strona',
    debounceMs = 300,
  }) {
    this.form = document.querySelector(formSelector);
    this.resultsContainer = document.querySelector(resultsSelector);
    this.debouncedFieldSelectors = debouncedFieldSelectors;
    this.immediateFieldSelectors = immediateFieldSelectors;
    this.pageParam = pageParam;
    this.debounceMs = debounceMs;

    this.debounceTimeout = null;
    this.fetchAbortController = null;
  }

  init() {
    if (!this.form || !this.resultsContainer) return;

    this.form.addEventListener('submit', (e) => {
      e.preventDefault();
      this.performFetch();
    });

    this.debouncedFieldSelectors.forEach((selector) => {
      const field = this.form.querySelector(selector);
      if (!field) return;
      field.addEventListener('input', () => this.scheduleFetch());
    });

    this.immediateFieldSelectors.forEach((selector) => {
      const field = this.form.querySelector(selector);
      if (!field) return;
      field.addEventListener('change', () => this.performFetch());
    });

    // Delegacja kliknięć w linki paginacji wewnątrz kontenera wyników
    // (kontener jest podmieniany w całości, więc nie da się podpiąć się
    // pod pojedyncze linki raz na zawsze).
    this.resultsContainer.addEventListener('click', (e) => {
      const pageLink = e.target.closest('[data-page-link]');
      if (!pageLink) return;

      e.preventDefault();
      const page = pageLink.dataset.page;
      if (!page) return;

      this.performFetch(page);

      const offset = this.form.getBoundingClientRect().top + window.scrollY - 20;
      window.scrollTo({ top: offset, behavior: 'smooth' });
    });
  }

  scheduleFetch() {
    clearTimeout(this.debounceTimeout);
    this.debounceTimeout = setTimeout(() => this.performFetch(), this.debounceMs);
  }

  /**
   * @param {string|number|null} page - Numer strony do wczytania; null = strona 1 (reset po zmianie filtra).
   */
  async performFetch(page = null) {
    const params = new URLSearchParams(new FormData(this.form));

    if (page) {
      params.set(this.pageParam, String(page));
    } else {
      params.delete(this.pageParam);
    }

    // Puste pola (np. wyczyszczone pole tekstowe) nie powinny zaśmiecać URL-a.
    Array.from(params.keys()).forEach((key) => {
      if (params.get(key) === '') params.delete(key);
    });

    const actionUrl = this.form.getAttribute('action') || window.location.pathname;
    const queryString = params.toString();
    const requestUrl = queryString ? `${actionUrl}?${queryString}` : actionUrl;

    // Aktualizujemy pasek adresu (bookmarkowalność, odświeżenie renderuje
    // identyczny wynik po stronie serwera) bez dodawania wpisu do historii.
    window.history.replaceState({}, '', requestUrl);

    // Anulujemy poprzednie, jeszcze nie zakończone zapytanie - inaczej szybkie
    // pisanie mogłoby nadpisać nowszą odpowiedź starszą (odwrócona kolejność powrotu).
    if (this.fetchAbortController) {
      this.fetchAbortController.abort();
    }
    this.fetchAbortController = new AbortController();

    try {
      this.resultsContainer.setAttribute('aria-busy', 'true');
      this.showSkeletons();

      const response = await fetch(requestUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        signal: this.fetchAbortController.signal,
      });

      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const data = await response.json();

      if (data.status === 'success') {
        this.updateResults(data.html);
      } else {
        this.showError();
      }
    } catch (error) {
      if (error.name === 'AbortError') return;
      console.error('Błąd filtrowania listy:', error);
      this.showError();
    } finally {
      this.resultsContainer.setAttribute('aria-busy', 'false');
    }
  }

  updateResults(html) {
    this.resultsContainer.innerHTML = html;

    this.resultsContainer.style.opacity = 0;
    requestAnimationFrame(() => {
      this.resultsContainer.style.transition = 'opacity 0.3s ease-in-out';
      this.resultsContainer.style.opacity = 1;
    });
  }

  showSkeletons() {
    const rows = Array(6).fill(0).map(() => `
      <li class="questions-list__item">
        <article class="question-card">
          <div class="question-card__badges">
            <div class="skeleton-text skeleton-text--badge"></div>
            <div class="skeleton-text skeleton-text--badge" style="width: 120px;"></div>
          </div>
          <div class="skeleton-text skeleton-text--line"></div>
        </article>
      </li>
    `).join('');

    this.resultsContainer.innerHTML = `
      <p class="questions-page__lead skeleton-text skeleton-text--title"></p>
      <ol class="questions-list">${rows}</ol>
    `;
  }

  showError() {
    this.resultsContainer.innerHTML = `
      <div class="empty-state" role="alert">
        <p><i class="fas fa-triangle-exclamation"></i> Wystąpił błąd podczas filtrowania listy.</p>
        <p style="font-size: 0.8rem; margin-top: 0.5rem; opacity: 0.6;">Spróbuj odświeżyć stronę lub spróbować ponownie za chwilę.</p>
      </div>
    `;
  }
}
