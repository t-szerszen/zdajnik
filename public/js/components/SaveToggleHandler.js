/**
 * @file SaveToggleHandler.js
 * @description Obsługuje logikę przełączania zapisu pytania na różnych stronach.
 * Eksportuje gotową instancję obiektu (Singleton).
 */

import api from '../modules/ApiClient.js';
import Toast from './Toast.js';

class SaveToggleHandler {
  /**
   * Wykonuje fizyczną operację przełączenia zapisu dla danego przycisku.
   * Może być wywoływane ręcznie z innych kontrolerów.
   * @param {HTMLElement} btn - Przycisk serca (.quiz-card__save-btn)
   */
  async toggle(btn) {
    const questionId = btn.dataset.questionId;
    if (!questionId) return null;

    // Wizualny feedback
    const relatedButtons = this.getButtons(questionId);
    relatedButtons.forEach((relatedBtn) => {
      relatedBtn.disabled = true;
      relatedBtn.style.opacity = '0.5';
    });

    try {
      const result = await api.toggleSavedQuestion(questionId);

      if (result.success) {
        this.updateButtons(questionId, result.data.is_saved);
        Toast.show(
          result.data.is_saved ? 'Pytanie zostało zapisane.' : 'Pytanie usunięte z zapisanych.',
          result.data.is_saved ? 'success' : 'info'
        );
        return result.data;
      } else {
        Toast.show(result.error || 'Błąd podczas zapisywania pytania.', 'error');
      }
    } catch (error) {
      Toast.show('Błąd połączenia.', 'error');
    } finally {
      relatedButtons.forEach((relatedBtn) => {
        relatedBtn.disabled = false;
        relatedBtn.style.opacity = '1';
      });
    }

    return null;
  }

  getButtons(questionId) {
    return Array.from(document.querySelectorAll('.quiz-card__save-btn'))
      .filter((btn) => btn.dataset.questionId === String(questionId));
  }

  updateButtons(questionId, isSaved) {
    const nextIconType = isSaved ? 'fas' : 'far';
    const previousIconType = isSaved ? 'far' : 'fas';
    const label = isSaved ? 'Usuń z zapisanych' : 'Zapisz pytanie';

    this.getButtons(questionId)
      .forEach((btn) => {
        const icon = btn.querySelector('i');
        if (icon) {
          icon.classList.replace(previousIconType, nextIconType);
        }
        btn.setAttribute('aria-label', label);
        btn.setAttribute('aria-pressed', isSaved ? 'true' : 'false');
        btn.title = label;
      });
  }

  /**
   * Inicjalizuje delegację zdarzeń wewnątrz wskazanego kontenera.
   * @param {string} containerSelector - Selektor kontenera z pytaniami.
   */
  init(containerSelector = 'body') {
    const container = document.querySelector(containerSelector);
    if (!container) return;
    if (container.dataset.saveToggleInit === 'true') return;
    container.dataset.saveToggleInit = 'true';

    container.addEventListener('click', (event) => {
      const btn = event.target.closest('.quiz-card__save-btn');
      if (btn) {
        this.toggle(btn);
      }
    });
  }
}

// Eksportujemy instancję obiektu
export default new SaveToggleHandler();
