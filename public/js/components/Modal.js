/**
 * @file Modal.js
 * @module Modal
 * @description
 * Dostępny (a11y) komponent modala potwierdzeń dla panelu admina.
 * Buduje własny markup on-demand, obsługuje focus trap, Escape,
 * przywraca fokus do elementu wywołującego i zwraca Promise<boolean>.
 *
 * ## Przykład użycia
 * ```javascript
 * import Modal from './Modal.js';
 *
 * const confirmed = await Modal.confirm({
 *   title: 'Dezaktywacja konta',
 *   body: 'Na pewno zdezaktywować to konto?',
 *   confirmLabel: 'Dezaktywuj',
 *   tone: 'danger',
 * });
 * ```
 *
 * @author Tobiasz Szerszeń
 */

const FOCUSABLE = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

class Modal {
  constructor() {
    this.overlay = null;
    this.resolver = null;
    this.previouslyFocused = null;
    this.handleKeydown = this.handleKeydown.bind(this);
  }

  /**
   * Pokazuje modal potwierdzenia.
   *
   * @param {object} options
   * @param {string} options.title Nagłówek modala.
   * @param {string} [options.body] Treść pytania.
   * @param {string} [options.confirmLabel] Etykieta przycisku potwierdzenia.
   * @param {string} [options.cancelLabel] Etykieta przycisku anulowania.
   * @param {'default'|'danger'} [options.tone] Ton przycisku potwierdzenia.
   * @returns {Promise<boolean>} true, jeśli użytkownik potwierdził.
   */
  confirm({ title, body = '', confirmLabel = 'Potwierdź', cancelLabel = 'Anuluj', tone = 'default' } = {}) {
    if (this.overlay) {
      this.close(false);
    }

    return new Promise((resolve) => {
      this.resolver = resolve;
      this.previouslyFocused = document.activeElement;

      const overlay = document.createElement('div');
      overlay.className = 'admin-modal';
      overlay.innerHTML = `
        <div class="admin-modal__backdrop" data-modal-cancel></div>
        <div class="admin-modal__panel" role="dialog" aria-modal="true" aria-labelledby="admin-modal-title">
          <h2 class="admin-modal__title" id="admin-modal-title"></h2>
          <p class="admin-modal__body"></p>
          <div class="admin-modal__actions">
            <button type="button" class="btn btn--secondary" data-modal-cancel></button>
            <button type="button" class="btn ${tone === 'danger' ? 'btn--danger' : 'btn--primary'}" data-modal-confirm></button>
          </div>
        </div>`;

      overlay.querySelector('.admin-modal__title').textContent = title || 'Potwierdzenie';
      overlay.querySelector('.admin-modal__body').textContent = body;
      overlay.querySelector('[data-modal-cancel].btn').textContent = cancelLabel;
      overlay.querySelector('[data-modal-confirm]').textContent = confirmLabel;

      overlay.querySelectorAll('[data-modal-cancel]').forEach((el) => {
        el.addEventListener('click', () => this.close(false));
      });
      overlay.querySelector('[data-modal-confirm]').addEventListener('click', () => this.close(true));

      document.body.appendChild(overlay);
      document.body.classList.add('admin-modal-open');
      document.addEventListener('keydown', this.handleKeydown, true);
      this.overlay = overlay;

      requestAnimationFrame(() => {
        overlay.classList.add('is-visible');
        const cancelBtn = overlay.querySelector('[data-modal-cancel].btn');
        if (cancelBtn) cancelBtn.focus();
      });
    });
  }

  handleKeydown(event) {
    if (!this.overlay) return;

    if (event.key === 'Escape') {
      event.preventDefault();
      this.close(false);
      return;
    }

    if (event.key === 'Tab') {
      const focusable = Array.from(this.overlay.querySelectorAll(FOCUSABLE))
        .filter((el) => !el.disabled && el.offsetParent !== null);
      if (focusable.length === 0) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      } else if (!this.overlay.contains(document.activeElement)) {
        event.preventDefault();
        first.focus();
      }
    }
  }

  close(result) {
    if (!this.overlay) return;

    const overlay = this.overlay;
    const resolver = this.resolver;
    this.overlay = null;
    this.resolver = null;

    document.removeEventListener('keydown', this.handleKeydown, true);
    document.body.classList.remove('admin-modal-open');
    overlay.classList.remove('is-visible');

    const remove = () => overlay.remove();
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReduced) {
      remove();
    } else {
      overlay.addEventListener('transitionend', remove, { once: true });
      window.setTimeout(remove, 300);
    }

    if (this.previouslyFocused && typeof this.previouslyFocused.focus === 'function') {
      this.previouslyFocused.focus();
    }
    this.previouslyFocused = null;

    if (resolver) resolver(Boolean(result));
  }
}

const modalInstance = new Modal();

export default modalInstance;
