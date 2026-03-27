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
        
        this.init();
    }

    init() {
        if (!this.form || !this.resultsContainer) return;

        // Zapobiegamy domyślnemu wysyłaniu formularza
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
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
            this.input.addEventListener('input', () => resetPageAndSearch(true));
        }

        // Nasłuchujemy zmian na selectach - wyszukujemy od razu
        if (this.examSelect) {
            this.examSelect.addEventListener('change', () => resetPageAndSearch(false));
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

    scheduleSearch(delay) {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.performSearch();
        }, delay);
    }

    async performSearch() {
        // Zbieramy dane z formularza
        const formData = new FormData(this.form);
        const searchParams = new URLSearchParams(formData);
        
        // Pobieramy aktualny URL akcji formularza
        const actionUrl = this.form.getAttribute('action') || window.location.pathname;
        const requestUrl = `${actionUrl}?${searchParams.toString()}`;

        // Aktualizujemy pasek adresu (dla historii i linkowania) bez przeładowania
        window.history.replaceState({}, '', requestUrl);

        try {
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
            <div class="search-results">
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
            <div class="stats-card__empty">
                <div class="stats-card__empty-icon">⚠️</div>
                <p>Wystąpił błąd podczas wyszukiwania.</p>
                <p style="font-size: 0.8rem; margin-top: 0.5rem; opacity: 0.6;">Spróbuj odświeżyć stronę lub spróbować ponownie za chwilę.</p>
            </div>
        `;
    }
}

// Inicjalizacja, jeśli jesteśmy na stronie z wyszukiwaniem
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.search-page')) {
        new SearchHandler();
    }
});
