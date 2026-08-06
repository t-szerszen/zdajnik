/**
 * @file AdminResourceTable.js
 * @module AdminResourceTable
 * @description
 * Współdzielony kontroler list panelu admina: filtrowanie, sortowanie,
 * paginacja i akcje (pojedyncze oraz zbiorcze) bez przeładowania strony.
 *
 * Wzorzec "HTML over the wire": endpoint JSON zwraca wiersze wyrenderowane
 * tym samym partialem PHP co pierwszy render strony, więc markup ma jedno
 * źródło prawdy, a JS jedynie podmienia zawartość i zarządza stanem.
 *
 * ## Kontrakt HTML
 * Kontener `[data-admin-table-root]` z atrybutami:
 * - `data-endpoint` — adres GET zwracający `{ok, html, meta}`,
 * - `data-bulk-endpoint` — (opcjonalnie) adres POST akcji zbiorczych.
 * Wewnątrz: `[data-admin-filter-form]`, `[data-admin-rows]`,
 * `[data-admin-pagination]`, `[data-admin-total]`, `[data-admin-bulk-bar]`,
 * `[data-check-all]`, wiersze z `[data-row-check][value=ID]`.
 *
 * @author Tobiasz Szerszeń
 */

import Toast from '../../components/Toast.js';
import Modal from '../../components/Modal.js';

export default class AdminResourceTable {
  constructor(root) {
    this.root = root;
    this.endpoint = root.dataset.endpoint || '';
    this.bulkEndpoint = root.dataset.bulkEndpoint || '';
    this.form = root.querySelector('[data-admin-filter-form]');
    this.rowsContainer = root.querySelector('[data-admin-rows]');
    this.paginationEl = root.querySelector('[data-admin-pagination]');
    this.totalEl = root.querySelector('[data-admin-total]');
    this.bulkBar = root.querySelector('[data-admin-bulk-bar]');
    this.checkAll = root.querySelector('[data-check-all]');
    this.statusEl = root.querySelector('[data-admin-status]');

    this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    this.abortController = null;
    this.searchTimer = null;
    this.selected = new Set();
    this.page = Number(root.dataset.page || 1);
    this.totalPages = Number(root.dataset.totalPages || 1);
  }

  init() {
    if (!this.endpoint || !this.form || !this.rowsContainer) return;

    this.bindFilters();
    this.bindPagination();
    this.bindRowActions();
    if (this.bulkEndpoint && this.bulkBar) {
      this.bindSelection();
      this.bindBulkActions();
    }
    this.renderPagination();
  }

  // --- Filtry ---------------------------------------------------------------

  bindFilters() {
    this.form.addEventListener('submit', (event) => {
      event.preventDefault();
      this.page = 1;
      this.refresh();
    });

    this.form.querySelectorAll('select').forEach((select) => {
      select.addEventListener('change', () => {
        this.page = 1;
        this.refresh();
      });
    });

    this.form.querySelectorAll('input[type="text"], input[type="search"], input[type="date"]').forEach((input) => {
      input.addEventListener('input', () => {
        window.clearTimeout(this.searchTimer);
        this.searchTimer = window.setTimeout(() => {
          this.page = 1;
          this.refresh();
        }, 300);
      });
    });

    const resetLink = this.root.querySelector('[data-admin-reset]');
    if (resetLink) {
      resetLink.addEventListener('click', (event) => {
        event.preventDefault();
        this.form.reset();
        this.form.querySelectorAll('input, select').forEach((field) => {
          if (field.type === 'checkbox' || field.type === 'radio') return;
          if (field.tagName === 'SELECT') {
            field.selectedIndex = 0;
          } else {
            field.value = '';
          }
        });
        this.page = 1;
        this.refresh();
      });
    }
  }

  buildParams() {
    const params = new URLSearchParams();
    const formData = new FormData(this.form);
    formData.forEach((value, key) => {
      const text = String(value).trim();
      if (text !== '') params.set(key, text);
    });
    if (this.page > 1) params.set('page', String(this.page));
    return params;
  }

  // --- Pobieranie i render --------------------------------------------------

  async refresh() {
    if (this.abortController) this.abortController.abort();
    this.abortController = new AbortController();

    const params = this.buildParams();
    this.setLoading(true);

    try {
      const response = await fetch(`${this.endpoint}?${params.toString()}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: this.abortController.signal,
      });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Nie udało się pobrać danych.');
      }

      this.rowsContainer.innerHTML = data.html;
      this.page = Number(data.meta?.page || 1);
      this.totalPages = Number(data.meta?.totalPages || 1);
      if (this.totalEl && data.meta) {
        this.totalEl.textContent = `Wyniki: ${data.meta.total}`;
      }
      this.renderPagination();
      this.clearSelection();
      this.syncUrl(params);
    } catch (error) {
      if (error.name === 'AbortError') return;
      Toast.show(error.message || 'Błąd połączenia z serwerem.', 'error');
    } finally {
      this.setLoading(false);
    }
  }

  setLoading(isLoading) {
    this.root.classList.toggle('is-loading', isLoading);
    if (this.statusEl) this.statusEl.classList.toggle('hidden', !isLoading);
  }

  syncUrl(params) {
    const query = params.toString();
    const url = query ? `${window.location.pathname}?${query}` : window.location.pathname;
    window.history.replaceState(null, '', url);
  }

  // --- Paginacja ------------------------------------------------------------

  bindPagination() {
    if (!this.paginationEl) return;
    this.paginationEl.addEventListener('click', (event) => {
      const button = event.target.closest('[data-page]');
      if (!button) return;
      event.preventDefault();
      const target = Number(button.dataset.page);
      if (!Number.isFinite(target) || target < 1 || target > this.totalPages || target === this.page) return;
      this.page = target;
      this.refresh();
      this.root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  renderPagination() {
    if (!this.paginationEl) return;

    if (this.totalPages <= 1) {
      this.paginationEl.innerHTML = '';
      this.paginationEl.hidden = true;
      return;
    }

    this.paginationEl.hidden = false;
    const prevDisabled = this.page <= 1 ? 'disabled' : '';
    const nextDisabled = this.page >= this.totalPages ? 'disabled' : '';
    this.paginationEl.innerHTML = `
      <span class="admin-pagination__status">Strona ${this.page} / ${this.totalPages}</span>
      <div class="admin-actions">
        <button type="button" class="btn btn--secondary btn--sm" data-page="${this.page - 1}" ${prevDisabled}>Poprzednia</button>
        <button type="button" class="btn btn--secondary btn--sm" data-page="${this.page + 1}" ${nextDisabled}>Następna</button>
      </div>`;
  }

  // --- Akcje pojedyncze (formularze w wierszach) ----------------------------

  bindRowActions() {
    this.root.addEventListener('submit', async (event) => {
      const form = event.target.closest('form[data-ajax]');
      if (!form) return;
      event.preventDefault();

      const confirmText = form.dataset.confirm;
      if (confirmText) {
        const confirmed = await Modal.confirm({
          title: form.dataset.confirmTitle || 'Potwierdzenie',
          body: confirmText,
          confirmLabel: form.dataset.confirmLabel || 'Potwierdź',
          tone: form.dataset.confirmTone || 'default',
        });
        if (!confirmed) return;
      }

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        Toast.show(data.message || (data.ok ? 'Zapisano.' : 'Operacja nie powiodła się.'), data.ok ? 'success' : (data.type === 'info' ? 'info' : 'error'));
        if (data.ok) this.refresh();
      } catch (error) {
        Toast.show('Błąd połączenia z serwerem.', 'error');
      }
    });
  }

  // --- Zaznaczanie wierszy --------------------------------------------------

  bindSelection() {
    this.root.addEventListener('change', (event) => {
      const checkbox = event.target;
      if (checkbox.matches('[data-check-all]')) {
        const rowChecks = this.rowsContainer.querySelectorAll('[data-row-check]');
        rowChecks.forEach((rowCheck) => {
          rowCheck.checked = checkbox.checked;
          this.toggleSelected(rowCheck);
        });
        this.updateBulkBar();
        return;
      }
      if (checkbox.matches('[data-row-check]')) {
        this.toggleSelected(checkbox);
        this.updateBulkBar();
      }
    });
  }

  toggleSelected(checkbox) {
    const id = Number(checkbox.value);
    if (!Number.isFinite(id)) return;
    if (checkbox.checked) {
      this.selected.add(id);
    } else {
      this.selected.delete(id);
    }
    checkbox.closest('.admin-table__row')?.classList.toggle('is-selected', checkbox.checked);
  }

  clearSelection() {
    this.selected.clear();
    if (this.checkAll) this.checkAll.checked = false;
    this.updateBulkBar();
  }

  updateBulkBar() {
    if (!this.bulkBar) return;
    const count = this.selected.size;
    this.bulkBar.classList.toggle('is-visible', count > 0);
    const counter = this.bulkBar.querySelector('[data-bulk-count]');
    if (counter) counter.textContent = String(count);
  }

  // --- Akcje zbiorcze -------------------------------------------------------

  bindBulkActions() {
    this.bulkBar.addEventListener('click', async (event) => {
      const clearButton = event.target.closest('[data-bulk-clear]');
      if (clearButton) {
        this.rowsContainer.querySelectorAll('[data-row-check]').forEach((rowCheck) => {
          rowCheck.checked = false;
          rowCheck.closest('.admin-table__row')?.classList.remove('is-selected');
        });
        this.clearSelection();
        return;
      }

      const actionButton = event.target.closest('[data-bulk-action]');
      if (!actionButton || this.selected.size === 0) return;

      const action = actionButton.dataset.bulkAction;
      const confirmText = actionButton.dataset.confirm;
      if (confirmText) {
        const confirmed = await Modal.confirm({
          title: actionButton.dataset.confirmTitle || 'Akcja zbiorcza',
          body: `${confirmText} (zaznaczono: ${this.selected.size})`,
          confirmLabel: actionButton.textContent.trim(),
          tone: actionButton.dataset.confirmTone || 'default',
        });
        if (!confirmed) return;
      }

      try {
        const response = await fetch(this.bulkEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': this.csrfToken,
          },
          body: JSON.stringify({ action, ids: Array.from(this.selected) }),
        });
        const data = await response.json();
        Toast.show(data.message || (data.ok ? 'Wykonano.' : 'Operacja nie powiodła się.'), data.ok ? 'success' : 'error');

        const failed = data.failed && typeof data.failed === 'object' ? Object.entries(data.failed) : [];
        if (data.ok && failed.length > 0) {
          const preview = failed.slice(0, 3).map(([id, reason]) => `#${id}: ${reason}`).join(' · ');
          Toast.show(`Pominięto ${failed.length}: ${preview}`, 'info', 8000);
        }

        if (data.ok) this.refresh();
      } catch (error) {
        Toast.show('Błąd połączenia z serwerem.', 'error');
      }
    });
  }
}

/**
 * Inicjalizuje wszystkie tabele admina obecne na stronie.
 */
export function initAdminResourceTables() {
  document.querySelectorAll('[data-admin-table-root]').forEach((root) => {
    new AdminResourceTable(root).init();
  });
}
