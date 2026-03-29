/**
 * Zarządza zmianą motywu (jasny/ciemny) i zapisuje wybór użytkownika.
 */
class ThemeSwitcher {
  constructor(checkboxId) {
    this.checkbox = document.getElementById(checkboxId);
    
    if (!this.checkbox) {
      console.error(`ThemeSwitcher: Nie znaleziono elementu o ID "${checkboxId}".`);
      return;
    }
    this.init();
  }

  init() {
    // 1. Ustaw stan suwaka zgodnie z tym, co ustawił skrypt w <head>
    this.checkbox.checked = document.documentElement.classList.contains('dark');

    // 2. Nasłuchuj na kliknięcie w przełącznik
    this.checkbox.addEventListener('change', () => {
      const newTheme = this.checkbox.checked ? 'dark' : 'light';
      this.setTheme(newTheme);
    });
  }

  /**
   * Ustawia motyw, aktualizuje klasę na <html> i zapisuje wybór w localStorage.
   * @param {'light' | 'dark'} theme - Nazwa motywu do ustawienia.
   */
  setTheme(theme) {
    // Zastosuj zmianę wizualną
    document.documentElement.classList.toggle('dark', theme === 'dark');
    
    // Zapisz wybór użytkownika w pamięci przeglądarki, aby przetrwał odświeżenie
    try {
      localStorage.setItem('theme', theme);
    } catch (e) {
      console.warn('ThemeSwitcher: Brak dostępu do localStorage. Motyw został zmieniony, ale nie zostanie zapamiętany na przyszłość.', e);
    }
  }
}

// Skrypty importowane jako 'module' (<script type="module">) są zawsze odraczane (defer),
// co oznacza, że parser DOM już skończył przepracowywać cały dokument HTML.
// Zabezpieczenia takie jak asynchronicznie zawiązane zdarzenie 'DOMContentLoaded' zazwyczaj
// powodują usterki wyścigów czasowych na szybszych sieciach i na stronach statycznych.
const initThemeSwitcher = () => new ThemeSwitcher('theme-toggle-checkbox');
initThemeSwitcher();