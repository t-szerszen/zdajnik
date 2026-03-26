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
    if (!questionId) return;

    // Wizualny feedback
    btn.disabled = true;
    btn.style.opacity = '0.5';

    try {
      const result = await api.toggleSavedQuestion(questionId);

      if (result.success) {
        const icon = btn.querySelector('i');
        if (result.data.is_saved) {
          icon.classList.replace('far', 'fas');
          Toast.show('Pytanie zostało zapisane.', 'success');
        } else {
          icon.classList.replace('fas', 'far');
          Toast.show('Pytanie usunięte z zapisanych.', 'info');
        }
      } else {
        Toast.show(result.error || 'Błąd podczas zapisywania pytania.', 'error');
      }
    } catch (error) {
      Toast.show('Błąd połączenia.', 'error');
    } finally {
      btn.disabled = false;
      btn.style.opacity = '1';
    }
  }

  /**
   * Inicjalizuje delegację zdarzeń wewnątrz wskazanego kontenera.
   * @param {string} containerSelector - Selektor kontenera z pytaniami.
   */
  init(containerSelector = 'body') {
    const container = document.querySelector(containerSelector);
    if (!container) return;

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
