/**
 * Obsługuje logikę wyszukiwania w czasie rzeczywistym (AJAX).
 */
export default class SearchHandler {
  constructor() {
    this.form = document.querySelector('.search-form');
    this.input = document.getElementById('query');
    this.examSelect = document.getElementById('exam_id');
    this.topicSelect = document.getElementById('topic_id');
    this.pageInput = document.getElementById('search-page-input');
    this.resultsContainer = document.querySelector('.search-page__results');

    this.searchTimeout = null;
    this.topicsAbortController = null;

    this.init();
  }

  init() {
    if (!this.form || !this.resultsContainer) return;

    // Zapobiegamy domyślnemu wysyłaniu formularza
    this.form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (!this.isQueryValid(true)) return;
      this.performSearch();
    });

    const resetPageAndSearch = (isInput = false) => {
      if (this.pageInput) this.pageInput.value = 1;
      if (isInput) {
        this.scheduleSearch(300);
      } else {
        this.performSearch();
      }
    };

    // Nasłuchujemy zmian na inputach z opóźnieniem (debounce)
    if (this.input) {
      this.input.addEventListener('input', () => {
        this.isQueryValid(false);
        resetPageAndSearch(true);
      });
    }

    // Nasłuchujemy zmian na selectach - wyszukujemy od razu
    if (this.examSelect) {
      this.examSelect.addEventListener('change', async () => {
        if (this.pageInput) this.pageInput.value = 1;
        await this.loadTopicsForSelectedExam();
        this.performSearch();
      });
    }

    if (this.topicSelect) {
      this.topicSelect.addEventListener('change', () => resetPageAndSearch(false));
    }

    // Nasłuchujemy kliknięcia paginacji (Event Delegation)
    this.resultsContainer.addEventListener('click', (e) => {
      const pageLink = e.target.closest('.pagination__link');
      if (pageLink && !pageLink.classList.contains('pagination__link--disabled')) {
        e.preventDefault();
        const url = new URL(pageLink.href);
        const page = url.searchParams.get('page');

        if (page && this.pageInput) {
          this.pageInput.value = page;
          this.performSearch();

          // Płynny powrót na górę wyników
          const offset = this.form.getBoundingClientRect().top + window.scrollY - 20;
          window.scrollTo({ top: offset, behavior: 'smooth' });
        }
      }
    });
  }

  async loadTopicsForSelectedExam() {
    if (!this.examSelect || !this.topicSelect) return;

    const examId = this.examSelect.value;
    this.resetTopicSelect('Wszystkie tematy', !examId);

    if (!examId) return;

    if (this.topicsAbortController) {
      this.topicsAbortController.abort();
    }

    this.topicsAbortController = new AbortController();

    try {
      this.topicSelect.disabled = true;
      this.topicSelect.classList.add('is-loading');

      const baseUrl = window.zdajnikAppState?.baseUrl || '';
      const response = await fetch(`${baseUrl}/search/topics?exam_id=${encodeURIComponent(examId)}`, {
        headers: {
          'Accept': 'application/json'
        },
        signal: this.topicsAbortController.signal
      });

      if (!response.ok) {
        throw new Error('Topics response was not ok');
      }

      const data = await response.json();
      const topics = Array.isArray(data.topics) ? data.topics : [];

      this.resetTopicSelect('Wszystkie tematy', topics.length === 0);

      topics.forEach((topic) => {
        const option = document.createElement('option');
        option.value = String(topic.id);
        option.textContent = topic.name;
        this.topicSelect.append(option);
      });

      this.topicSelect.disabled = topics.length === 0;
    } catch (error) {
      if (error.name !== 'AbortError') {
        console.error('Błąd ładowania tematów:', error);
        this.resetTopicSelect('Nie udało się załadować tematów', true);
      }
    } finally {
      this.topicSelect.classList.remove('is-loading');
    }
  }

  resetTopicSelect(label, disabled) {
    if (!this.topicSelect) return;

    this.topicSelect.innerHTML = '';
    const option = document.createElement('option');
    option.value = '';
    option.textContent = label;
    this.topicSelect.append(option);
    this.topicSelect.value = '';
    this.topicSelect.disabled = disabled;
  }

  scheduleSearch(delay) {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => {
      this.performSearch();
    }, delay);
  }

  isQueryValid(showValidationMessage = false) {
    if (!this.input) return true;

    const query = this.input.value.trim();
    const hasShortQuery = query.length > 0 && query.length < 3;

    if (hasShortQuery) {
      this.input.setCustomValidity('Wpisz co najmniej 3 znaki, aby rozpocząć wyszukiwanie.');
      if (showValidationMessage) {
        this.input.reportValidity();
      }
      return false;
    }

    this.input.setCustomValidity('');
    return true;
  }

  async performSearch() {
    if (!this.isQueryValid(false)) return;

    // Zbieramy dane z formularza
    const formData = new FormData(this.form);
    const searchParams = new URLSearchParams(formData);

    // Pobieramy aktualny URL akcji formularza
    const actionUrl = this.form.getAttribute('action') || window.location.pathname;
    const requestUrl = `${actionUrl}?${searchParams.toString()}`;

    // Aktualizujemy pasek adresu (dla historii i linkowania) bez przeładowania
    window.history.replaceState({}, '', requestUrl);

    try {
      this.resultsContainer.setAttribute('aria-busy', 'true');
      this.showSkeletons();

      // Używamy fetch z nagłówkiem oznaczającym AJAX (częsty standard, na którym opiera się PHP $_SERVER['HTTP_X_REQUESTED_WITH'])
      const response = await fetch(requestUrl, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
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
      console.error('Błąd wyszukiwania:', error);
      this.showError();
    } finally {
      this.resultsContainer.setAttribute('aria-busy', 'false');
    }
  }

  updateResults(html) {
    // Wstrzykujemy wyrenderowany na serwerze HTML partialu
    this.resultsContainer.innerHTML = html;

    // Płynne wejście (fade in)
    this.resultsContainer.style.opacity = 0;
    requestAnimationFrame(() => {
      this.resultsContainer.style.transition = 'opacity 0.3s ease-in-out';
      this.resultsContainer.style.opacity = 1;
    });
  }

  showSkeletons() {
    // Jeśli szukamy ID, często jest 1 wynik, jeśli wpisujemy tekst - wiele.
    // Pokażmy np. 6 szkieletów zastępczo.
    const skeletonsHtml = `
            <div class="search-results" role="status" aria-live="polite">
                <h2 class="search-results__count skeleton-text skeleton-text--title"></h2>
                <div class="search-results__grid">
                    ${Array(6).fill(0).map(() => `
                        <article class="search-result-card skeleton-card">
                            <div class="search-result-card__main">
                                <div class="search-result-card__header">
                                    <div class="search-result-card__badges">
                                        <div class="skeleton-text skeleton-text--badge"></div>
                                        <div class="skeleton-text skeleton-text--badge" style="width: 120px;"></div>
                                    </div>
                                    <div class="skeleton-text skeleton-text--id"></div>
                                </div>
                                <div class="search-result-card__content">
                                    <div class="skeleton-text skeleton-text--line"></div>
                                    <div class="skeleton-text skeleton-text--line" style="width: 60%"></div>
                                </div>
                            </div>
                            <div class="search-result-card__footer">
                                <div class="skeleton-button" style="width: 100px; height: 36px; border-radius: 8px;"></div>
                            </div>
                        </article>
                    `).join('')}
                </div>
            </div>
        `;
    this.resultsContainer.innerHTML = skeletonsHtml;
  }

  showError() {
    this.resultsContainer.innerHTML = `
            <div class="stats-card__empty" role="alert">
                <div class="stats-card__empty-icon">⚠️</div>
                <p>Wystąpił błąd podczas wyszukiwania.</p>
                <p style="font-size: 0.8rem; margin-top: 0.5rem; opacity: 0.6;">Spróbuj odświeżyć stronę lub spróbować ponownie za chwilę.</p>
            </div>
        `;
  }
}

export function initSearchPage() {
  if (!document.querySelector('.search-page')) return null;
  return new SearchHandler();
}
