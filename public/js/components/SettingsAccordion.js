/**
 * @file SettingsAccordion.js
 * @description
 * Zarządza działaniem akordeonu na stronie ustawień.
 * Odpowiada za otwieranie i zamykanie sekcji profilu, e-maila oraz hasła.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */
export class SettingsAccordion {
  constructor(accordionContainerId) {
    this.container = document.getElementById(accordionContainerId);
    if (!this.container) return;

    this.cards = this.container.querySelectorAll('.settings-card');
    this.init();
  }

  init() {
    this.cards.forEach(card => {
      const header = card.querySelector('.settings-card__header');
      header.addEventListener('click', () => this.toggleCard(card));
    });
  }

  toggleCard(clickedCard) {
    const isOpen = clickedCard.classList.contains('is-open');

    // Najpierw zamknij wszystkie otwarte karty
    this.cards.forEach(card => card.classList.remove('is-open'));

    // Jeśli kliknięta karta nie była już otwarta, otwórz ją
    if (!isOpen) {
      clickedCard.classList.add('is-open');
    }
  }
}

export function initSettingsAccordion(accordionContainerId = 'settings-accordion') {
  return new SettingsAccordion(accordionContainerId);
}
