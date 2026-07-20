/**
 * @file features/sheets/index.js
 * @description
 * Interakcje Biblioteki Arkuszy:
 * - lista: akordeon sekcji tematów oraz filtrowanie po temacie i zakresie
 *   trudności (bez przeładowania strony),
 * - szczegóły: zapis notatki użytkownika (trudność 1-10 + opis) przez AJAX
 *   z potwierdzeniem w formie toasta.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

import api from '../../modules/ApiClient.js';
import Toast from '../../components/Toast.js';

/**
 * Inicjalizuje akordeon tematów i filtry na liście arkuszy.
 */
export function initSheetsListPage() {
  const filtersRoot = document.querySelector('[data-sheets-filters]');

  // Akordeon sekcji tematów
  document.querySelectorAll('[data-sheets-topic-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const section = toggle.closest('[data-sheets-topic]');
      if (!section) return;

      const isOpen = section.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  });

  if (!filtersRoot) return;

  const topicSelect = filtersRoot.querySelector('[data-topic-filter]');
  const difficultyButtons = Array.from(filtersRoot.querySelectorAll('[data-difficulty-filter]'));
  const emptyMessage = document.querySelector('[data-sheets-empty]');

  const applyFilters = () => {
    const topicId = topicSelect ? topicSelect.value : '';
    const activeButton = difficultyButtons.find((btn) => btn.classList.contains('is-active'));
    const difficultyGroup = activeButton ? activeButton.dataset.difficultyFilter : '';

    let anyVisible = false;

    document.querySelectorAll('[data-sheets-topic]').forEach((section) => {
      // Filtr tematu ukrywa całe sekcje
      if (topicId && section.dataset.topicId !== topicId) {
        section.hidden = true;
        return;
      }

      // Filtr trudności ukrywa pojedyncze arkusze wewnątrz sekcji
      let visibleInSection = 0;
      section.querySelectorAll('[data-sheet-item]').forEach((item) => {
        const matches = !difficultyGroup || item.dataset.difficultyGroup === difficultyGroup;
        item.hidden = !matches;
        if (matches) visibleInSection += 1;
      });

      section.hidden = visibleInSection === 0;
      if (visibleInSection > 0) anyVisible = true;
    });

    if (emptyMessage) {
      emptyMessage.hidden = anyVisible;
    }
  };

  if (topicSelect) {
    topicSelect.addEventListener('change', applyFilters);
  }

  difficultyButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      difficultyButtons.forEach((other) => other.classList.remove('is-active'));
      btn.classList.add('is-active');
      applyFilters();
    });
  });
}

/**
 * Inicjalizuje formularz notatki na stronie szczegółów arkusza.
 */
export function initSheetDetailPage() {
  const form = document.querySelector('[data-sheet-note-form]');
  if (!form) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const sheetId = form.dataset.sheetId;
    const difficultyInput = form.querySelector('input[name="difficulty"]:checked');
    const description = form.querySelector('textarea[name="description"]')?.value ?? '';

    if (!difficultyInput) {
      Toast.show('Oceń trudność arkusza w skali od 1 do 10.', 'error');
      return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;

    try {
      const result = await api.saveSheetNote(sheetId, difficultyInput.value, description.trim());

      if (result.success) {
        Toast.show(result.data.message || 'Notatka została zapisana.', 'success');
      } else {
        Toast.show(result.error || 'Błąd podczas zapisywania notatki.', 'error');
      }
    } catch (error) {
      Toast.show('Błąd połączenia.', 'error');
    } finally {
      if (submitButton) submitButton.disabled = false;
    }
  });
}
