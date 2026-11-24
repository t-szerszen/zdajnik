/**
 * @file tutorialSpotlight.js
 * @description
 * Wspólne pozycjonowanie samouczków (OneQuestion, PersonalizedTest, Statistics,
 * PremiumOptions): przewijanie do podświetlanego elementu i ramka "spotlight",
 * która podąża za nim.
 *
 * Dlaczego osobny moduł: wcześniej każdy samouczek liczył pozycję ramki RAZ,
 * zaraz po `scrollIntoView({ behavior: 'smooth' })`. Gdy element leżał w
 * przewijanym kontenerze (np. sidebar z `overflow-y: auto`, poziomy pasek
 * kategorii na mobile), kontener przewijał się już PO pomiarze i ramka
 * zostawała w starym miejscu. Do tego `block: 'center'` na telefonie chował
 * element pod panelem samouczka przyklejonym do dołu ekranu.
 *
 * Teraz:
 * - ramka ma `position: fixed` i jest aktualizowana co klatkę, dopóki
 *   samouczek jest otwarty - zawsze trafia w element, niezależnie od tego,
 *   co i jak długo się przewija,
 * - strona przewija się tak, żeby element zmieścił się w obszarze nad
 *   panelem samouczka (a nie na środku ekranu, pod panelem).
 */

const EDGE_GAP = 16;

function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}

function getNavOffset() {
  const nav = document.querySelector('.main-nav');
  return nav ? nav.offsetHeight : 0;
}

function isScrollable(node, axis) {
  const style = window.getComputedStyle(node);
  const overflow = axis === 'x' ? style.overflowX : style.overflowY;
  if (!/(auto|scroll)/.test(overflow)) return false;

  return axis === 'x'
    ? node.scrollWidth > node.clientWidth + 1
    : node.scrollHeight > node.clientHeight + 1;
}

/**
 * Przewija wewnętrzne kontenery (poziomy pasek chipów, sidebar z własnym
 * scrollem), tak aby cel był w nich widoczny.
 */
function revealInsideScrollContainers(target, behavior) {
  let node = target.parentElement;

  while (node && node !== document.body && node !== document.documentElement) {
    if (isScrollable(node, 'x')) {
      const box = node.getBoundingClientRect();
      const rect = target.getBoundingClientRect();
      node.scrollBy({ left: rect.left - box.left - (box.width - rect.width) / 2, behavior });
    }

    if (isScrollable(node, 'y')) {
      const box = node.getBoundingClientRect();
      const rect = target.getBoundingClientRect();
      // Pionowo zawsze natychmiast: od wyniku zależy, gdzie przewinąć całe okno.
      node.scrollBy({ top: rect.top - box.top - Math.max(0, (box.height - rect.height) / 2), behavior: 'auto' });
    }

    node = node.parentElement;
  }
}

/**
 * Przewija stronę tak, aby `target` był widoczny w obszarze pomiędzy navbarem
 * a panelem samouczka (jeśli panel zasłania cel - na wąskich ekranach panel
 * zajmuje całą szerokość u dołu). Element wyższy niż dostępny obszar jest
 * wyrównywany do góry.
 *
 * Panel musi mieć już wpisaną treść bieżącego kroku - liczy się jego wysokość.
 */
export function scrollToTutorialTarget(target, panel) {
  const behavior = prefersReducedMotion() ? 'auto' : 'smooth';

  revealInsideScrollContainers(target, behavior);

  const rect = target.getBoundingClientRect();
  const topLimit = getNavOffset() + EDGE_GAP;
  let bottomLimit = window.innerHeight - EDGE_GAP;

  if (panel && !panel.classList.contains('hidden')) {
    const panelRect = panel.getBoundingClientRect();
    const coversTarget = panelRect.left < rect.right && panelRect.right > rect.left;
    if (coversTarget) {
      bottomLimit = panelRect.top - EDGE_GAP;
    }
  }

  const available = Math.max(0, bottomLimit - topLimit);
  const offsetInArea = rect.height < available ? (available - rect.height) / 2 : 0;
  const desiredTop = window.scrollY + rect.top - (topLimit + offsetInArea);

  window.scrollTo({ top: Math.max(0, desiredTop), behavior });
}

/**
 * Utrzymuje ramkę podświetlenia (element z `position: fixed`) na celu,
 * aktualizując ją w każdej klatce animacji, dopóki nie zostanie zatrzymana.
 */
export class SpotlightTracker {
  constructor(spotlight, padding = 10) {
    this.spotlight = spotlight;
    this.padding = padding;
    this.target = null;
    this.frame = null;
    this.lastRect = '';
    this.tick = this.tick.bind(this);
  }

  follow(target) {
    this.target = target;
    if (!this.frame) {
      this.frame = window.requestAnimationFrame(this.tick);
    }
  }

  stop() {
    this.target = null;
    this.lastRect = '';
    if (this.frame) {
      window.cancelAnimationFrame(this.frame);
      this.frame = null;
    }
  }

  tick() {
    if (!this.target || !this.target.isConnected) {
      this.frame = null;
      return;
    }

    const rect = this.target.getBoundingClientRect();
    const p = this.padding;
    const key = `${rect.top}|${rect.left}|${rect.width}|${rect.height}`;

    if (key !== this.lastRect) {
      this.lastRect = key;
      // W poziomie ramka nie wychodzi poza ekran (element na całą szerokość
      // telefonu + padding ramki ucinał jej prawą krawędź).
      const viewportWidth = document.documentElement.clientWidth;
      const left = Math.max(2, rect.left - p);
      const right = Math.min(viewportWidth - 2, rect.right + p);
      const style = this.spotlight.style;
      style.top = `${rect.top - p}px`;
      style.left = `${left}px`;
      style.width = `${Math.max(0, right - left)}px`;
      style.height = `${rect.height + p * 2}px`;
    }

    this.frame = window.requestAnimationFrame(this.tick);
  }
}

/**
 * Wywołuje `callback` tylko przy zmianie SZEROKOŚCI okna. Na iOS/Androidzie
 * `resize` strzela też przy chowaniu się paska adresu w trakcie przewijania -
 * ponowne przewijanie do celu w takiej chwili powodowało szarpanie.
 */
export function onViewportWidthChange(callback) {
  let lastWidth = window.innerWidth;

  window.addEventListener('resize', () => {
    if (window.innerWidth === lastWidth) return;
    lastWidth = window.innerWidth;
    callback();
  }, { passive: true });
}
