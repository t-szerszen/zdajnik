/**
 * @file show-password-toggle.js
 * @description
 * Prosty skrypt obsługujący przełączanie widoczności haseł (ikona oka) w formularzach.
 * Działa globalnie na wszystkich elementach z klasą .password-toggle-icon.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */
export function initPasswordToggles(root = document) {
  root.querySelectorAll('.password-toggle-icon').forEach((icon) => {
    if (icon.dataset.passwordToggleInit === 'true') return;
    icon.dataset.passwordToggleInit = 'true';

    icon.addEventListener('click', () => {
      const input = icon.previousElementSibling;
      if (!input) return;

      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    });
  });
}
