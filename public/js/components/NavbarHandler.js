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
    this.lastScrollY = window.scrollY || 0;

    if (this.toggleBtn && this.drawer) {
      this.init();
    }

    // .page-header (jeśli jest na stronie) podciąga się pod to, co jest nad nim,
    // zmienną CSS --dynamic-nav-height - ani navbar (np. profil zalogowanego
    // użytkownika jest wyższy niż przyciski logowania na szerokich ekranach),
    // ani okruszki nawigacyjne przed page-headerem (questions_hub.php,
    // questions_list.php) nie mają stałej wysokości, więc trzeba je zmierzyć -
    // inaczej zostaje szczelina, przez którą prześwituje zwykłe tło strony.
    if (this.nav) {
      this.syncNavHeight();
      window.addEventListener('resize', () => this.syncNavHeight(), { passive: true });
    }
  }

  syncNavHeight() {
    let totalHeight = this.nav.offsetHeight;

    const breadcrumbs = document.querySelector('.page-header')?.previousElementSibling;
    if (breadcrumbs?.classList.contains('breadcrumbs')) {
      totalHeight += breadcrumbs.offsetHeight;
    }

    document.documentElement.style.setProperty('--dynamic-nav-height', `${totalHeight}px`);
  }

  init() {
    this.toggleBtn.addEventListener('click', () => this.toggleMenu());

    if (this.overlay) {
      this.overlay.addEventListener('click', () => this.closeMenu());
    }

    // Obsługa dropdownów na mobile (Akordeon) i Tablet (Touch).
    // Zagnieżdżone podmenu (np. Teoria > INF.04 > tryby nauki) mają tę samą
    // klasę .dropdown co poziom pierwszy, więc ten sam mechanizm obsługuje
    // oba poziomy akordeonu.
    const dropdowns = document.querySelectorAll('.main-nav .dropdown');

    dropdowns.forEach(dropdown => {
      // ':scope >' ogranicza wyszukiwanie do bezpośredniego triggera TEGO
      // dropdownu, żeby nie złapać triggera zagnieżdżonego dziecka.
      const trigger = dropdown.querySelector(':scope > .main-nav__link, :scope > .dropdown__link');
      if (trigger) {
        trigger.addEventListener('click', (e) => {
          // Jeśli szerokość ekranu jest <= 992px (Mobile menu)
          // LUB jeśli urządzenie wspiera touch (np. iPad w trybie desktopowym)
          const isTouchCapability = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
          const isMobileMenu = window.innerWidth <= 992;

          if (isMobileMenu || isTouchCapability) {
            // Zapobiegaj przejściu, dopóki dropdown nie jest otwarty
            if (!dropdown.classList.contains('is-open')) {
              e.preventDefault();

              // Zamknij tylko dropdowny na TYM SAMYM poziomie (rodzeństwo
              // w obrębie tej samej listy) - nigdy przodków, bo otwarcie
              // zagnieżdżonego podmenu nie powinno zwijać menu nadrzędnego.
              Array.from(dropdown.parentElement.children)
                .filter((el) => el !== dropdown && el.classList.contains('dropdown'))
                .forEach((sibling) => {
                  sibling.classList.remove('is-open');
                  sibling.querySelectorAll('.dropdown.is-open').forEach((nested) => nested.classList.remove('is-open'));
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

    // Wykluczamy triggery rozwijające submenu (np. zagnieżdżony "INF.04" pod
    // Teoria/Praktyka) z listy linków zamykających drawer - inaczej pierwsze
    // dotknięcie triggera otwierałoby submenu i JEDNOCZEŚNIE zamykałoby cały
    // drawer (dwa niezależne listenery na tym samym elemencie).
    const links = this.drawer.querySelectorAll(
      '.main-nav__link:not(.dropdown > .main-nav__link), .dropdown__link:not(.dropdown__link--trigger), .nav-button'
    );
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
    const currentScrollY = window.scrollY;
    const isScrollingUp = currentScrollY < this.lastScrollY;
    const isAtTop = currentScrollY <= 20;

    if (this.nav) {
      // 1. Logika 'is-scrolled' (Bańka)
      // Dodajemy klasę bańki TYLKO jeśli jedziemy w górę i nie jesteśmy na samej górze.
      // Przy jeździe w dół nie chcemy, aby nav "skakał" w tryb bańki przed schowaniem się.
      if (currentScrollY > 20 && isScrollingUp) {
        this.nav.classList.add('is-scrolled');
        this.body.classList.add('is-scrolled');
      } else if (isAtTop) {
        this.nav.classList.remove('is-scrolled');
        this.body.classList.remove('is-scrolled');
      }

      // 2. Smart Navbar logic (Hiding/Showing)
      if (currentScrollY > 100) {
        if (currentScrollY > this.lastScrollY) {
          // Przewijanie w dół - chowamy nawigację
          this.nav.classList.add('is-hidden');
        } else {
          // Przewijanie w górę - pokazujemy nawigację
          this.nav.classList.remove('is-hidden');
        }
      } else {
        // Blisko góry zawsze pokazujemy
        this.nav.classList.remove('is-hidden');
      }
    }

    this.lastScrollY = currentScrollY;
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

export function initNavbarHandler() {
  return new NavbarHandler();
}
