/**
 * @file NavbarHandler.js
 * @description Obsługuje zachowanie paska nawigacyjnego na urządzeniach mobilnych,
 *              w tym przełączanie menu hamburgera i animacje drawera.
 */

export class NavbarHandler {
  constructor() {
    this.toggleBtn = document.querySelector('[data-toggle-nav]');
    this.drawer = document.querySelector('[data-nav-drawer]');
    this.overlay = document.querySelector('[data-nav-overlay]');
    this.nav = document.querySelector('.main-nav');
    this.body = document.body;

    if (this.toggleBtn && this.drawer) {
      this.init();
    }
  }

  init() {
    this.toggleBtn.addEventListener('click', () => this.toggleMenu());

    if (this.overlay) {
      this.overlay.addEventListener('click', () => this.closeMenu());
    }

    // Obsługa dropdownów na mobile (Akordeon) i Tablet (Touch)
    const dropdowns = document.querySelectorAll('.main-nav .dropdown');
    
    dropdowns.forEach(dropdown => {
      const link = dropdown.querySelector('.main-nav__link');
      if (link) {
        link.addEventListener('click', (e) => {
          // Jeśli szerokość ekranu jest <= 992px (Mobile menu)
          // LUB jeśli urządzenie wspiera touch (np. iPad w trybie desktopowym)
          const isTouchCapability = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
          const isMobileMenu = window.innerWidth <= 992;

          if (isMobileMenu || isTouchCapability) {
            // Zapobiegaj przejściu, dopóki dropdown nie jest otwarty
            if (!dropdown.classList.contains('is-open')) {
              e.preventDefault();
              
              // Zamknij inne otwarte dropdowny
              dropdowns.forEach(d => {
                if (d !== dropdown) d.classList.remove('is-open');
              });
              
              dropdown.classList.add('is-open');
            }
          }
        });
      }
    });

    // Zamknij dropdowny przy kliknięciu poza nimi (dla touch)
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.dropdown')) {
        dropdowns.forEach(d => d.classList.remove('is-open'));
      }
    });

    const links = this.drawer.querySelectorAll('.main-nav__link:not(.dropdown > .main-nav__link), .dropdown__link, .nav-button');
    links.forEach(link => {
      link.addEventListener('click', () => {
        if (this.drawer.classList.contains('is-active')) {
          this.toggleMenu();
        }
      });
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 992 && this.drawer.classList.contains('is-active')) {
        this.closeMenu();
      }
    });

    // Obsługa scrolla - efekt przezroczystości na górze
    this.nav = document.querySelector('.main-nav');
    if (this.nav) {
      this.handleScroll(); // Inicjalne sprawdzenie
      window.addEventListener('scroll', () => this.handleScroll(), { passive: true });
    }
  }

  handleScroll() {
    const isScrolled = window.scrollY > 20;
    if (this.nav) this.nav.classList.toggle('is-scrolled', isScrolled);
    this.body.classList.toggle('is-scrolled', isScrolled);
  }

  toggleMenu() {
    const isActive = this.drawer.classList.toggle('is-active');
    this.toggleBtn.classList.toggle('is-active');
    this.body.classList.toggle('nav-open', isActive);

    if (this.overlay) {
      this.overlay.classList.toggle('is-active');
    }

    if (isActive) {
      this.body.style.overflow = 'hidden'; // Blokada przewijania tła
    } else {
      this.body.style.overflow = '';
    }
  }

  closeMenu() {
    this.drawer.classList.remove('is-active');
    this.toggleBtn.classList.remove('is-active');
    this.body.classList.remove('nav-open');
    if (this.overlay) {
      this.overlay.classList.remove('is-active');
    }
    this.body.style.overflow = '';
  }
}

// Inicjalizacja
document.addEventListener('DOMContentLoaded', () => {
  new NavbarHandler();
});
