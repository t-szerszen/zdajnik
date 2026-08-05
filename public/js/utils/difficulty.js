/**
 * @file /utils/difficulty.js
 * @module Difficulty
 * @description
 * Wspólne mapowanie poziomu trudności pytania (questions.difficulty:
 * 'easy' | 'medium' | 'hard') na etykietę PL i klasę wizualną `.badge`.
 * Lustrzane odbicie helperów PHP `question_difficulty_label()` i
 * `question_difficulty_badge_class()` z `app/Core/helpers.php`.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

const DIFFICULTY_LABELS = {
  easy: 'Łatwe',
  medium: 'Średnie',
  hard: 'Trudne',
};

const DIFFICULTY_BADGE_CLASSES = {
  easy: 'badge--success',
  medium: 'badge--warning',
  hard: 'badge--danger',
};

/**
 * Buduje gotowy do wstawienia HTML znacznika `.badge` z poziomem trudności
 * pytania, albo pusty string, jeśli wartość jest nieznana/pusta.
 *
 * @param {string|null|undefined} difficulty - Wartość pola questions.difficulty.
 * @returns {string} Bezpieczny HTML znacznika badge lub pusty string.
 */
export function renderDifficultyBadge(difficulty) {
  const label = DIFFICULTY_LABELS[difficulty];
  if (!label) return '';

  const badgeClass = DIFFICULTY_BADGE_CLASSES[difficulty];
  return `<span class="badge ${badgeClass}">${label}</span>`;
}
