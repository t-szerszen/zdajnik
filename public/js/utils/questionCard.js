/**
 * @file questionCard.js
 * @module questionCard
 * @description
 * Wspólne fragmenty karty pytania (`.quiz-card`) dzielone przez wszystkie
 * tryby renderujące pytanie po stronie klienta (jedno pytanie, test, wyniki
 * testu) - tak, aby kolejność i wygląd etykiet (ID, temat, trudność) oraz
 * źródła pytania były identyczne niezależnie od trybu.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

import { escapeHTML } from './sanitize.js';
import { renderDifficultyBadge } from './difficulty.js';

/**
 * Buduje etykiety nagłówka karty pytania w ujednoliconej kolejności:
 * ID, temat, trudność.
 *
 * @param {object} question - Dane pytania (question_id, topic_name, difficulty).
 * @returns {string} HTML etykiet do wstawienia w `.quiz-card__header-left`.
 */
export function renderQuestionBadgesHTML(question, showTopic = false) {
  let topicBadge = '';
  if (question.topic_name) {
    topicBadge = showTopic
      ? `<span class="badge badge--secondary badge--topic">${escapeHTML(question.topic_name)}</span>`
      : `<span class="badge badge--secondary badge--topic" style="display: none;">${escapeHTML(question.topic_name)}</span>`;
  }

  return `
    <span class="quiz-card__id">ID: ${question.question_id}</span>
    ${topicBadge}
    ${renderDifficultyBadge(question.difficulty)}
  `;
}

/**
 * Buduje wiersz ze źródłem pytania (arkusz/materiał), wyświetlany pod
 * wyjaśnieniem - albo pusty string, gdy pytanie nie ma przypisanego źródła.
 *
 * @param {string|null|undefined} source - Wartość pola questions.source.
 * @returns {string} Bezpieczny HTML lub pusty string.
 */
export function renderQuestionSourceHTML(source) {
  const trimmed = String(source || '').trim();
  if (!trimmed) return '';

  return `<p class="quiz-card__source"><i class="fas fa-book" aria-hidden="true"></i> Źródło: ${escapeHTML(trimmed)}</p>`;
}

/**
 * Buduje przycisk "Zgłoś błąd" (flaga) do `.quiz-card__header-actions`.
 * Kliknięcia obsługuje globalnie main.js (leniwie ładuje QuestionReportDialog).
 *
 * @param {number|string} questionId - ID pytania.
 * @param {'one_question'|'test'|'test_results'} context - Tryb, z którego wysłano zgłoszenie.
 * @returns {string} HTML przycisku.
 */
export function renderReportButtonHTML(questionId, context) {
  return `<button type="button" class="quiz-card__report-btn" data-report-question="${escapeHTML(String(questionId))}"
    data-report-context="${escapeHTML(context)}" title="Zgłoś błąd w pytaniu" aria-label="Zgłoś błąd w pytaniu">
    <i class="far fa-flag" aria-hidden="true"></i>
  </button>`;
}
