/**
 * @file QuestionReportDialog.js
 * @module QuestionReportDialog
 * @description
 * Minimalistyczne okienko "Zgłoś błąd" dla kart pytań. Oparte o natywny
 * `<dialog>` (focus trap, Escape i tło za darmo). ID pytania i kontekst
 * (tryb aplikacji) bierze z przycisku, użytkownik wybiera tylko kategorię
 * i opcjonalnie dopisuje opis.
 *
 * Przycisk wyzwalający (generowany przez renderReportButtonHTML() lub w PHP):
 * ```html
 * <button class="quiz-card__report-btn" data-report-question="123" data-report-context="test">...</button>
 * ```
 *
 * Ładowany leniwie z main.js przy pierwszym kliknięciu w przycisk.
 *
 * @author Tobiasz Szerszeń
 */

import Toast from './Toast.js';

/** Kategorie - klucze muszą odpowiadać QuestionReport::CATEGORIES w PHP. */
const CATEGORIES = [
  ['wrong_answer', 'Zła poprawna odpowiedź'],
  ['content_error', 'Błąd w treści pytania lub odpowiedzi'],
  ['explanation', 'Błędne lub niejasne wyjaśnienie'],
  ['display', 'Problem z obrazkiem lub formatowaniem'],
  ['other', 'Inny problem'],
];

const MESSAGE_MAX_LENGTH = 1000;

class QuestionReportDialog {
  constructor() {
    this.dialog = null;
    this.trigger = null;
  }

  /**
   * Buduje markup okienka przy pierwszym użyciu.
   * @private
   */
  build() {
    const dialog = document.createElement('dialog');
    dialog.className = 'report-dialog';
    dialog.setAttribute('aria-labelledby', 'report-dialog-title');
    dialog.innerHTML = `
      <form class="report-dialog__panel" novalidate>
        <div class="report-dialog__header">
          <h2 class="report-dialog__title" id="report-dialog-title">
            <i class="fas fa-flag" aria-hidden="true"></i> Zgłoś błąd
          </h2>
          <span class="quiz-card__id" data-report-id></span>
          <button type="button" class="report-dialog__close" data-report-close aria-label="Zamknij">
            <i class="fas fa-xmark" aria-hidden="true"></i>
          </button>
        </div>

        <label class="report-dialog__field">
          <span class="report-dialog__label">Czego dotyczy błąd?</span>
          <select name="category" class="form-field__select" required>
            <option value="" disabled selected>Wybierz…</option>
            ${CATEGORIES.map(([value, label]) => `<option value="${value}">${label}</option>`).join('')}
          </select>
        </label>

        <label class="report-dialog__field">
          <span class="report-dialog__label">
            Opis <small data-report-optional>(opcjonalnie)</small>
          </span>
          <textarea name="message" class="form-field__input" rows="3" maxlength="${MESSAGE_MAX_LENGTH}"
            placeholder="Np. poprawna jest odpowiedź C, bo…"></textarea>
        </label>

        <p class="report-dialog__error" role="alert" hidden></p>

        <div class="report-dialog__actions">
          <button type="button" class="btn btn--secondary" data-report-close>Anuluj</button>
          <button type="submit" class="btn btn--primary" data-report-submit>Wyślij</button>
        </div>
      </form>`;

    dialog.querySelectorAll('[data-report-close]').forEach((btn) => {
      btn.addEventListener('click', () => dialog.close());
    });

    // Kliknięcie w tło (poza panelem) zamyka okienko.
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });

    dialog.addEventListener('close', () => {
      document.body.classList.remove('report-dialog-open');
      if (this.trigger && document.contains(this.trigger)) this.trigger.focus();
      this.trigger = null;
    });

    const form = dialog.querySelector('form');
    const select = form.elements.category;
    select.addEventListener('change', () => {
      const isOther = select.value === 'other';
      dialog.querySelector('[data-report-optional]').textContent = isOther ? '(wymagane)' : '(opcjonalnie)';
      this.setError('');
    });
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      this.submit();
    });

    document.body.appendChild(dialog);
    this.dialog = dialog;
  }

  /**
   * Otwiera okienko dla wskazanego przycisku.
   * @param {HTMLElement} trigger
   */
  open(trigger) {
    if (!this.dialog) this.build();

    this.trigger = trigger;
    const form = this.dialog.querySelector('form');
    form.reset();
    this.dialog.querySelector('[data-report-optional]').textContent = '(opcjonalnie)';
    this.dialog.querySelector('[data-report-id]').textContent = `ID: ${trigger.dataset.reportQuestion}`;
    this.setError('');
    this.setBusy(false);

    document.body.classList.add('report-dialog-open');
    this.dialog.showModal();
    form.elements.category.focus();
  }

  /** @private */
  setError(text) {
    const error = this.dialog.querySelector('.report-dialog__error');
    error.textContent = text;
    error.hidden = !text;
  }

  /** @private */
  setBusy(isBusy) {
    const submit = this.dialog.querySelector('[data-report-submit]');
    submit.disabled = isBusy;
    submit.textContent = isBusy ? 'Wysyłanie…' : 'Wyślij';
  }

  /** @private */
  async submit() {
    const form = this.dialog.querySelector('form');
    const category = form.elements.category.value;
    const message = form.elements.message.value.trim();

    if (!category) {
      this.setError('Wybierz, czego dotyczy błąd.');
      form.elements.category.focus();
      return;
    }
    if (category === 'other' && !message) {
      this.setError('Opisz krótko, co jest nie tak.');
      form.elements.message.focus();
      return;
    }

    const trigger = this.trigger;
    this.setBusy(true);
    const result = await sendReport({
      question_id: trigger.dataset.reportQuestion,
      category,
      message,
      context: trigger.dataset.reportContext || '',
    });
    this.setBusy(false);

    // 409 = już zgłoszone - z punktu widzenia użytkownika to też sukces.
    if (result.success || result.httpStatus === 409) {
      this.dialog.close();
      markReported(trigger.dataset.reportQuestion);
      Toast.show(result.success ? result.data.message : result.error, result.success ? 'success' : 'info');
      return;
    }

    this.setError(result.error || 'Nie udało się wysłać zgłoszenia.');
  }
}

/**
 * Wysyła zgłoszenie do API. Osobno od ApiClient.js, bo potrzebny jest kod HTTP
 * (409 = już zgłoszone) i nagłówek CSRF, których wspólny _request() nie obsługuje.
 *
 * @param {{question_id: string, category: string, message: string, context: string}} report
 * @returns {Promise<{success: boolean, data?: object, error?: string, httpStatus?: number}>}
 */
async function sendReport(report) {
  try {
    const response = await fetch(`${window.zdajnikAppState.baseUrl}/api/zglos-blad`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.zdajnikAppState.csrfToken || '',
      },
      body: JSON.stringify(report),
    });
    const data = await response.json().catch(() => ({}));

    return response.ok
      ? { success: true, data }
      : { success: false, error: data.message || `Błąd serwera: ${response.status}`, httpStatus: response.status };
  } catch (error) {
    return { success: false, error: 'Błąd połączenia. Sprawdź swoje połączenie z internetem.' };
  }
}

/**
 * Oznacza wszystkie przyciski zgłoszenia danego pytania jako wysłane.
 * @param {string|number} questionId
 */
function markReported(questionId) {
  document.querySelectorAll(`[data-report-question="${CSS.escape(String(questionId))}"]`).forEach((btn) => {
    btn.classList.add('is-reported');
    btn.disabled = true;
    btn.title = 'Zgłoszono - dziękujemy!';
    btn.setAttribute('aria-label', 'Błąd w tym pytaniu został zgłoszony');
    btn.querySelector('i')?.classList.replace('far', 'fas');
  });
}

export default new QuestionReportDialog();
