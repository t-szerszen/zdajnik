export class AdminAnalytics {
  constructor() {
    this.root = document.querySelector('[data-analytics-root]');
    if (!this.root) {
      return;
    }

    this.endpoint = this.root.dataset.endpoint || '';
    this.exportEndpoint = this.root.dataset.exportEndpoint || '';
    this.form = this.root.querySelector('[data-analytics-form]');
    this.chartCanvas = this.root.querySelector('[data-chart-canvas]');
    this.chartEmpty = this.root.querySelector('[data-chart-empty]');
    this.errorBox = this.root.querySelector('[data-analytics-error]');
    this.loadingStatus = this.root.querySelector('[data-loading-status]');
    this.chartInstance = null;
    this.abortController = null;
    this.searchTimer = null;
    this.activeTab = this.getInitialTab();
    this.data = this.readInitialData();

    this.bindEvents();
    this.render(this.data);
  }

  getInitialTab() {
    const tab = window.location.hash.replace('#', '');
    const available = [...this.root.querySelectorAll('[data-analytics-tab]')]
      .some((button) => button.dataset.analyticsTab === tab);
    return available ? tab : 'overview';
  }

  readInitialData() {
    const node = document.getElementById('analytics-initial-data');
    try {
      return JSON.parse(node?.textContent || '{}');
    } catch (error) {
      console.error('AdminAnalytics: invalid initial payload', error);
      return {};
    }
  }

  bindEvents() {
    this.root.querySelectorAll('[data-analytics-tab]').forEach((button) => {
      button.addEventListener('click', () => this.activateTab(button.dataset.analyticsTab || 'overview'));
      button.addEventListener('keydown', (event) => this.handleTabKeyboard(event));
    });

    if (!this.form) {
      return;
    }

    const rangeInput = this.form.querySelector('[data-range-input]');
    const fromInput = this.form.querySelector('[data-from-input]');
    const toInput = this.form.querySelector('[data-to-input]');
    const searchInput = this.form.querySelector('[data-search-input]');

    this.form.querySelectorAll('[data-range-value]').forEach((button) => {
      button.addEventListener('click', () => {
        if (rangeInput) rangeInput.value = button.dataset.rangeValue || '30d';
        if (fromInput) fromInput.value = '';
        if (toInput) toInput.value = '';
        this.resetPage();
        this.submitForm();
      });
    });

    this.form.querySelectorAll('select').forEach((select) => {
      select.addEventListener('change', () => {
        this.resetPage();
        this.submitForm();
      });
    });

    [fromInput, toInput].filter(Boolean).forEach((input) => {
      input.addEventListener('change', () => {
        if (fromInput?.value && toInput?.value) {
          if (rangeInput) rangeInput.value = 'custom';
          this.resetPage();
          this.submitForm();
        } else if (!fromInput?.value && !toInput?.value) {
          if (rangeInput) rangeInput.value = '30d';
          this.resetPage();
          this.submitForm();
        }
      });
    });

    searchInput?.addEventListener('input', () => {
      window.clearTimeout(this.searchTimer);
      this.searchTimer = window.setTimeout(() => {
        this.resetPage();
        this.submitForm();
      }, 450);
    });

    this.form.addEventListener('submit', (event) => {
      event.preventDefault();
      this.resetPage();
      this.submitForm();
    });

    this.root.querySelector('[data-filter-reset]')?.addEventListener('click', () => {
      this.resetFilters();
      this.submitForm();
    });

    this.root.addEventListener('click', (event) => {
      const paginationButton = event.target.closest('[data-analytics-page]');
      if (!paginationButton || paginationButton.disabled) {
        return;
      }

      const targetTab = paginationButton.dataset.paginationTab;
      if (targetTab) {
        this.activeTab = targetTab;
      }
      const pageInput = this.form?.querySelector('[data-page-input]');
      if (pageInput) {
        pageInput.value = paginationButton.dataset.analyticsPage || '1';
        this.submitForm();
      }
    });
  }

  handleTabKeyboard(event) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
      return;
    }

    event.preventDefault();
    const tabs = [...this.root.querySelectorAll('[data-analytics-tab]')];
    const currentIndex = tabs.indexOf(event.currentTarget);
    let nextIndex = currentIndex;

    if (event.key === 'ArrowLeft') nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
    if (event.key === 'ArrowRight') nextIndex = (currentIndex + 1) % tabs.length;
    if (event.key === 'Home') nextIndex = 0;
    if (event.key === 'End') nextIndex = tabs.length - 1;

    tabs[nextIndex]?.focus();
    tabs[nextIndex]?.click();
  }

  activateTab(tab, updateHistory = true) {
    this.activeTab = tab;
    this.root.querySelectorAll('[data-analytics-tab]').forEach((button) => {
      const active = button.dataset.analyticsTab === tab;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
      button.tabIndex = active ? 0 : -1;
    });
    this.root.querySelectorAll('[data-analytics-section]').forEach((section) => {
      const active = section.dataset.analyticsSection === tab;
      section.classList.toggle('is-active', active);
      section.hidden = !active;
    });

    if (updateHistory) {
      this.replaceHistory();
    }
  }

  async submitForm() {
    if (!this.form || !this.endpoint) {
      return;
    }

    this.abortController?.abort();
    const controller = new AbortController();
    this.abortController = controller;
    const params = new URLSearchParams(new FormData(this.form));
    this.setLoading(true);
    this.hideError();

    try {
      const response = await fetch(`${this.endpoint}?${params.toString()}`, {
        headers: { Accept: 'application/json' },
        signal: controller.signal,
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || `Błąd serwera: ${response.status}`);
      }

      this.data = payload.data || {};
      this.render(this.data);
      this.replaceHistory(params);
    } catch (error) {
      if (error.name !== 'AbortError') {
        console.error('AdminAnalytics: request failed', error);
        this.showError(error.message || 'Nie udało się pobrać danych analitycznych.');
      }
    } finally {
      if (this.abortController === controller) {
        this.setLoading(false);
      }
    }
  }

  render(data) {
    if (!data || typeof data !== 'object') {
      this.showError('Odpowiedź analityki ma nieprawidłowy format.');
      return;
    }

    this.applyRange(data.range || {});
    this.renderOverview(data.comparison?.metrics || data.overview?.kpis || {}, data.daily || data.overview?.daily || {});
    this.renderAcquisition(data.acquisition || {}, data.funnel || data.acquisition?.funnel || {});
    this.renderEngagement(data.engagement || {}, data.activeUsers || {});
    this.renderLearning(data.learning || {});
    this.renderContent(data.contentQuality || {}, data.topicActivity || []);
    this.renderRetention(data.retention || {});
    this.renderUsers(data.userActivity || data.users || {});
    this.renderExports(data.exportLinks || {});
    this.activateTab(this.activeTab, false);
  }

  renderOverview(kpis, daily) {
    this.renderMetricCards('[data-overview-kpis]', [
      this.metric('Nowi użytkownicy', kpis.newUsers, 'fa-user-plus'),
      this.metric('Aktywni użytkownicy', kpis.activeUsers, 'fa-users'),
      this.metric('Odsłony', kpis.pageViews, 'fa-eye'),
      this.metric('Wejścia (strona główna)', kpis.entries, 'fa-door-open'),
      this.metric('Rozpoczęte testy', kpis.testsStarted, 'fa-play', 'number',
        this.audienceDetail(kpis.testsStartedGuests, kpis.testsStartedLoggedIn)),
      this.metric('Ukończone testy', kpis.testsCompleted, 'fa-flag-checkered', 'number',
        this.audienceDetail(kpis.testsCompletedGuests, kpis.testsCompletedLoggedIn)),
      this.metric('Completion rate', kpis.completionRate, 'fa-percent', 'percent'),
      this.metric('Średni wynik (zalogowani)', kpis.averageScore, 'fa-bullseye', 'percent'),
    ], true);
    this.renderChart(daily);
  }

  renderAcquisition(section, funnel) {
    const searches = section.searches || this.data.searchActivity || {};
    this.renderMetricCards('[data-acquisition-kpis]', [
      this.simpleMetric('Rejestracje', section.summary?.registrations, 'fa-user-plus'),
      this.simpleMetric('Logowania', section.summary?.logins, 'fa-right-to-bracket'),
    ]);
    this.renderFunnel(funnel.stages || []);
    this.renderBreakdown('[data-auth-providers]', section.authProviders || [], 'Provider');
    this.renderBreakdown('[data-traffic-sources]', section.sources || [], 'Źródło');
    this.renderBreakdown('[data-entry-paths]', section.entryPaths || [], 'Ścieżka', true);
    this.renderMetricCards('[data-search-kpis]', [
      this.simpleMetric('Wyszukiwania', searches.summary?.searches, 'fa-magnifying-glass'),
      this.simpleMetric('Użytkownicy', searches.summary?.uniqueUsers, 'fa-users'),
      this.simpleMetric('Bezpieczne frazy', searches.summary?.searchesWithSafeTerm, 'fa-shield-halved'),
      this.simpleMetric('Bez zapisanej frazy', searches.summary?.searchesWithoutStoredTerm, 'fa-eye-slash'),
    ]);
    this.renderTable('[data-search-queries]',
      ['Zapytanie', 'Wyszukiwania', 'Użytkownicy'],
      (searches.topQueries || []).map((row) => [
        row.query || '',
        this.number(row.searches),
        this.number(row.unique_users),
      ]),
      true
    );
  }

  renderEngagement(section, activeUsers) {
    const summary = { ...activeUsers, ...(section.summary || {}) };
    this.renderMetricCards('[data-engagement-kpis]', [
      this.simpleMetric('DAU', summary.dau, 'fa-user-clock'),
      this.simpleMetric('WAU', summary.wau, 'fa-calendar-week'),
      this.simpleMetric('MAU', summary.mau, 'fa-calendar'),
      this.simpleMetric('DAU / MAU', summary.dauMauStickiness, 'fa-chart-pie', 'percent'),
      this.simpleMetric('Aktywni w zakresie', summary.activeUsersInRange, 'fa-users'),
      this.simpleMetric('Sesje', summary.sessions, 'fa-window-restore'),
      this.simpleMetric('Pokrycie sesji', summary.sessionCoveragePercent, 'fa-database', 'percent'),
      this.simpleMetric('Odsłony', summary.pageViews, 'fa-eye'),
    ]);
    this.renderBreakdown('[data-top-paths]', section.topPaths || [], 'Ścieżka', true);
    this.renderBreakdown('[data-event-types]', section.eventTypes || [], 'Event');
  }

  renderLearning(section) {
    const summary = section.summary || {};
    this.renderMetricCards('[data-learning-kpis]', [
      this.simpleMetric('Rozpoczęte', summary.started, 'fa-play'),
      this.simpleMetric('Ukończone', summary.completed, 'fa-flag-checkered'),
      this.simpleMetric('Completion rate', summary.completionRate, 'fa-percent', 'percent'),
      this.simpleMetric('Porzucone', summary.abandoned, 'fa-door-open'),
      this.simpleMetric('Abandonment rate', summary.abandonmentRate, 'fa-arrow-trend-down', 'percent'),
      this.simpleMetric('Średni wynik', summary.averageScore, 'fa-bullseye', 'percent'),
      this.simpleMetric('Średni czas', summary.averageDurationSeconds, 'fa-clock', 'duration'),
      this.simpleMetric('Unikalni uczący się', summary.uniqueLearners, 'fa-graduation-cap'),
    ]);

    this.renderTable('[data-learning-types]',
      ['Typ', 'Starty', 'Ukończenia', 'Completion', 'Wynik', 'Czas'],
      (section.byTestType || []).map((row) => [
        this.testTypeLabel(row.test_type),
        this.number(row.started),
        this.number(row.completed),
        this.percent(row.completion_rate),
        this.percent(row.average_score),
        this.duration(row.average_duration_seconds),
      ])
    );

    this.renderTable('[data-learning-exams]',
      ['Egzamin', 'Starty', 'Ukończenia', 'Użytkownicy', 'Wynik', 'Czas'],
      (section.byExam || []).map((row) => [
        `${row.code || ''} - ${row.name || ''}`,
        this.number(row.started_tests),
        this.number(row.completed_tests),
        this.number(row.unique_users),
        this.percent(row.avg_score),
        this.duration(row.avg_duration_seconds),
      ]),
      true
    );

    this.renderTable('[data-abandoned-tests]',
      ['Typ', 'Starty', 'Ukończone', 'Jawne wyjścia', 'Brak wyniku', 'Porzucone', 'Udział'],
      (section.abandonedTests?.byTestType || []).map((row) => [
        this.testTypeLabel(row.test_type),
        this.number(row.started),
        this.number(row.matched_completed),
        this.number(row.explicit_exits),
        this.number(row.inferred_abandoned),
        this.number(row.abandoned),
        this.percent(row.abandonment_rate),
      ]),
      true
    );
  }

  renderContent(section, topics) {
    this.renderTable('[data-content-quality]',
      ['Pytanie', 'Egzamin', 'Temat', 'Odpowiedzi', 'Poprawne', 'Błędne', 'Pominięte', 'Skuteczność'],
      (section.items || []).map((row) => [
        this.truncate(row.question_text, 160),
        row.exam_code || '',
        row.topic_name || '',
        this.number(row.total_answers),
        this.number(row.correct_answers),
        this.number(row.wrong_answers),
        this.number(row.skipped_answers),
        this.percent(row.accuracy),
      ]),
      true
    );
    this.renderPagination('[data-content-pagination]', section, 'content', 'pytań');

    this.renderTable('[data-topic-activity]',
      ['Temat', 'Egzamin', 'Odpowiedzi', 'Poprawne', 'Skuteczność'],
      (topics || section.weakestTopics || []).map((row) => [
        row.topic_name || '',
        row.exam_code || '',
        this.number(row.total_answers),
        this.number(row.correct_answers),
        this.percent(row.accuracy),
      ])
    );

    this.renderTable('[data-saved-questions]',
      ['Pytanie', 'Egzamin', 'Temat', 'Zapisy'],
      (section.mostSavedQuestions || []).map((row) => [
        this.truncate(row.question_text, 110),
        row.exam_code || '',
        row.topic_name || '',
        this.number(row.saves),
      ]),
      true
    );

    this.renderTable('[data-dropoff-questions]',
      ['Pytanie', 'Egzamin', 'Wyświetlenia', 'Pominięcia', 'Udział'],
      (section.dropOffQuestions || []).map((row) => [
        this.truncate(row.question_text, 110),
        row.exam_code || '',
        this.number(row.appearances),
        this.number(row.skipped),
        this.percent(row.drop_off_percent),
      ]),
      true
    );

    this.renderTable('[data-saved-question-activity]',
      ['Egzamin', 'Temat', 'Dodane', 'Usunięte', 'Użytkownicy'],
      (section.savedQuestionActivity?.byExamTopic || []).map((row) => [
        row.exam_code || '',
        row.topic_name || '',
        this.number(row.added),
        this.number(row.removed),
        this.number(row.unique_users),
      ]),
      true
    );

    const explanations = section.explanationInsights || {};
    this.renderMetricCards('[data-explanation-kpis]', [
      this.simpleMetric('Wyświetlenia', explanations.views, 'fa-lightbulb'),
      this.simpleMetric('Po błędnej odpowiedzi', explanations.afterIncorrect, 'fa-circle-xmark'),
      this.simpleMetric('Po poprawnej odpowiedzi', explanations.afterCorrect, 'fa-circle-check'),
      this.simpleMetric('Udział po błędzie', explanations.afterIncorrectRate, 'fa-percent', 'percent'),
    ]);
  }

  renderRetention(section) {
    const summary = section.summary || {};
    this.renderMetricCards('[data-retention-kpis]', [
      this.simpleMetric('Powrót D1', summary.d1Rate, 'fa-rotate', 'percent'),
      this.simpleMetric('Powrót D7', summary.d7Rate, 'fa-calendar-week', 'percent'),
      this.simpleMetric('Powrót D30', summary.d30Rate, 'fa-calendar', 'percent'),
      this.simpleMetric('Brak aktywności 7 dni', summary.inactiveUsers7d, 'fa-user-clock'),
      this.simpleMetric('Brak aktywności 14 dni', summary.inactiveUsers14d, 'fa-user-minus'),
      this.simpleMetric('Nieaktywni 30 dni', summary.inactiveUsers30d, 'fa-user-slash'),
    ]);

    this.renderTable('[data-retention-cohorts]',
      ['Kohorta', 'Użytkownicy', 'D1', 'D1 %', 'D7', 'D7 %', 'D30', 'D30 %'],
      (section.cohorts || []).map((row) => [
        row.cohort || '',
        this.number(row.users),
        this.number(row.d1_users),
        this.percent(row.d1_rate),
        this.number(row.d7_users),
        this.percent(row.d7_rate),
        this.number(row.d30_users),
        this.percent(row.d30_rate),
      ])
    );

    this.renderTable('[data-inactive-users]',
      ['Użytkownik', 'E-mail', 'Provider', 'Rejestracja', 'Ostatni event', 'Ostatni test'],
      (section.inactiveUsers || []).map((row) => [
        this.fullName(row) || `#${row.id}`,
        row.email || '',
        row.auth_provider || '',
        this.date(row.created_at),
        this.date(row.last_event),
        this.date(row.last_test),
      ]),
      true
    );
  }

  renderUsers(section) {
    this.renderTable('[data-users-table]',
      ['Użytkownik', 'E-mail', 'Provider', 'Status', 'Rejestracja', 'Ostatni login', 'Ostatnia aktywność', 'Testy', 'Śr. wynik'],
      (section.items || []).map((row) => [
        `${this.fullName(row) || `#${row.id}`} (${row.role || 'user'})`,
        row.email || '',
        row.auth_provider || '',
        `${row.account_status || ''} / ${Number(row.is_verified) === 1 ? 'verified' : 'unverified'}`,
        this.date(row.created_at),
        this.date(row.last_login),
        this.date(row.last_activity),
        this.number(row.tests_completed),
        this.percent(row.average_score),
      ]),
      true
    );
    this.renderPagination('[data-users-pagination]', section, 'users', 'użytkowników');
  }

  renderFunnel(stages) {
    const container = this.root.querySelector('[data-conversion-funnel]');
    if (!container) {
      return;
    }
    if (!stages.length) {
      container.innerHTML = this.emptyState();
      return;
    }

    container.innerHTML = `<div class="admin-analytics__funnel">
      ${stages.map((stage, index) => {
        const width = Math.max(2, Math.min(100, Number(stage.conversionFromFirst) || 0));
        return `<div class="admin-analytics__funnel-row">
          <div class="admin-analytics__funnel-label">
            <strong>${this.escapeHTML(this.eventLabel(stage.eventType))}</strong>
            <span>${this.escapeHTML(this.number(stage.actors))} użytkowników / sesji</span>
          </div>
          <div class="admin-analytics__funnel-track" aria-hidden="true">
            <span style="width:${width}%"></span>
          </div>
          <div class="admin-analytics__funnel-value">
            ${index === 0 ? '100%' : this.escapeHTML(this.percent(stage.conversionFromPrevious))}
          </div>
        </div>`;
      }).join('')}
    </div>`;
  }

  renderExports(exportLinks) {
    const formParams = this.form ? new URLSearchParams(new FormData(this.form)) : new URLSearchParams();
    this.root.querySelectorAll('[data-export-dataset]').forEach((link) => {
      const dataset = link.dataset.exportDataset || 'users';
      if (exportLinks[dataset]) {
        link.href = exportLinks[dataset];
        return;
      }
      const params = new URLSearchParams(formParams);
      params.set('dataset', dataset);
      link.href = `${this.exportEndpoint}?${params.toString()}`;
    });
  }

  renderMetricCards(selector, metrics, showComparison = false) {
    const container = this.root.querySelector(selector);
    if (!container) {
      return;
    }

    const available = metrics.filter(Boolean);
    if (!available.length) {
      container.innerHTML = this.emptyState();
      return;
    }

    container.innerHTML = available.map((metric) => `
      <article class="admin-analytics__metric">
        <div class="admin-analytics__metric-top">
          <span>${this.escapeHTML(metric.label)}</span>
          <i class="fas ${this.escapeHTML(metric.icon)}" aria-hidden="true"></i>
        </div>
        <strong>${this.escapeHTML(this.metricValue(metric.value, metric.format))}</strong>
        ${metric.detail ? `<small class="admin-analytics__metric-detail">${this.escapeHTML(metric.detail)}</small>` : ''}
        ${showComparison ? this.comparison(metric.data) : ''}
      </article>
    `).join('');
  }

  renderBreakdown(selector, rows, firstColumn, wide = false) {
    this.renderTable(selector,
      [firstColumn, 'Zdarzenia', 'Użytkownicy'],
      rows.map((row) => [
        wide ? this.truncate(row.label || 'Brak danych', 120) : (row.label || 'Brak danych'),
        this.number(row.total),
        this.number(row.unique_users),
      ]),
      wide
    );
  }

  renderTable(selector, headers, rows, wide = false) {
    const container = this.root.querySelector(selector);
    if (!container) {
      return;
    }
    if (!rows.length) {
      container.innerHTML = this.emptyState();
      return;
    }

    container.innerHTML = `
      <div class="admin-analytics__table-scroll">
        <table class="admin-analytics__data-table${wide ? ' is-wide' : ''}">
          <thead>
            <tr>${headers.map((header) => `<th scope="col">${this.escapeHTML(header)}</th>`).join('')}</tr>
          </thead>
          <tbody>
            ${rows.map((row) => `<tr>${row.map((cell, index) => `
              <td data-label="${this.escapeHTML(headers[index] || '')}">${this.escapeHTML(cell ?? '')}</td>
            `).join('')}</tr>`).join('')}
          </tbody>
        </table>
      </div>
    `;
  }

  renderPagination(selector, section, tab, noun) {
    const container = this.root.querySelector(selector);
    if (!container) {
      return;
    }

    const page = Math.max(1, Number(section.page) || 1);
    const totalPages = Math.max(1, Number(section.totalPages) || 1);
    const total = Math.max(0, Number(section.total) || 0);
    if (total === 0) {
      container.innerHTML = '';
      return;
    }

    container.innerHTML = `
      <span class="admin-pagination__status">
        ${this.escapeHTML(this.number(total))} ${this.escapeHTML(noun)}, strona ${page} z ${totalPages}
      </span>
      <div class="admin-analytics__pagination-actions">
        <button type="button" class="btn btn--secondary btn--sm" data-analytics-page="${page - 1}" data-pagination-tab="${this.escapeHTML(tab)}" ${page <= 1 ? 'disabled' : ''}>
          Poprzednia
        </button>
        <button type="button" class="btn btn--secondary btn--sm" data-analytics-page="${page + 1}" data-pagination-tab="${this.escapeHTML(tab)}" ${page >= totalPages ? 'disabled' : ''}>
          Następna
        </button>
      </div>
    `;
  }

  renderChart(daily) {
    const labels = Array.isArray(daily.labels) ? daily.labels : [];
    const styles = getComputedStyle(document.documentElement);
    const accent = styles.getPropertyValue('--accent').trim() || '#10b981';
    const accentHover = styles.getPropertyValue('--accent-hover').trim() || '#059669';
    const navy = styles.getPropertyValue('--color-navy').trim() || '#0d1b3d';
    const datasets = [
      ['Odsłony', daily.pageViews || [], navy],
      ['Wejścia (strona główna)', daily.entries || [], '#8b5cf6'],
      ['Rejestracje', daily.registrations || [], '#f59e0b'],
      ['Logowania', daily.logins || [], accent],
      ['Starty testów', daily.testsStarted || [], accentHover],
      ['Ukończenia', daily.testsCompleted || [], '#ef4444'],
    ];
    const hasData = datasets.some(([, values]) => values.some((value) => Number(value) > 0));

    if (this.chartInstance) {
      this.chartInstance.destroy();
      this.chartInstance = null;
    }
    this.chartCanvas?.classList.toggle('hidden', !hasData);
    this.chartEmpty?.classList.toggle('hidden', hasData);
    if (!hasData || !window.Chart || !this.chartCanvas) {
      return;
    }

    this.chartInstance = new window.Chart(this.chartCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: labels.map((value) => this.shortDate(value, daily.granularity)),
        datasets: datasets.map(([label, values, color]) => ({
          label,
          data: values,
          borderColor: color,
          backgroundColor: `${color}1a`,
          borderWidth: 2,
          fill: false,
          tension: 0.25,
          pointRadius: labels.length > 40 ? 0 : 2,
          pointHoverRadius: 4,
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 12, usePointStyle: true },
          },
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 },
          },
          y: { beginAtZero: true, ticks: { precision: 0 } },
        },
      },
    });
  }

  applyRange(range) {
    const label = this.root.querySelector('[data-range-label]');
    if (label) label.textContent = range.label || '';
    if (!this.form) return;

    const rangeInput = this.form.querySelector('[data-range-input]');
    const fromInput = this.form.querySelector('[data-from-input]');
    const toInput = this.form.querySelector('[data-to-input]');
    if (rangeInput) rangeInput.value = range.value || '30d';
    if (fromInput) fromInput.value = range.from || '';
    if (toInput) toInput.value = range.to || '';
    this.form.querySelectorAll('[data-range-value]').forEach((button) => {
      button.classList.toggle('is-active', button.dataset.rangeValue === range.value);
    });
  }

  resetFilters() {
    if (!this.form) {
      return;
    }
    this.form.querySelectorAll('select, input').forEach((control) => {
      if (control.name === 'per_page') return;
      if (control.name === 'range') {
        control.value = '30d';
      } else if (control.name === 'page') {
        control.value = '1';
      } else {
        control.value = '';
      }
    });
  }

  resetPage() {
    const pageInput = this.form?.querySelector('[data-page-input]');
    if (pageInput) pageInput.value = '1';
  }

  replaceHistory(params = null) {
    if (!this.form) {
      return;
    }
    const query = params || new URLSearchParams(new FormData(this.form));
    const target = new URL(this.form.action, window.location.origin);
    target.search = query.toString();
    target.hash = this.activeTab;
    window.history.replaceState({}, '', target);
  }

  metric(label, data, icon, format = 'number', detail = '') {
    return { label, value: data?.value ?? 0, data: data || {}, icon, format, detail };
  }

  /**
   * Opis podziału wartości na gości i zalogowanych (np. "Goście: 30 · Zalogowani: 3").
   */
  audienceDetail(guests, loggedIn) {
    if (guests === undefined && loggedIn === undefined) return '';
    return `Goście: ${this.number(guests?.value ?? 0)} · Zalogowani: ${this.number(loggedIn?.value ?? 0)}`;
  }

  simpleMetric(label, value, icon, format = 'number') {
    return { label, value: value ?? 0, data: {}, icon, format };
  }

  metricValue(value, format) {
    if (format === 'percent') return this.percent(value);
    if (format === 'duration') return this.duration(value);
    return this.number(value);
  }

  comparison(data) {
    if (data?.previous === null || data?.previous === undefined) {
      return '<small class="admin-analytics__comparison is-neutral">Brak porównania</small>';
    }
    const change = data.changePercent;
    if (change === null || change === undefined) {
      return '<small class="admin-analytics__comparison is-positive">Nowa aktywność</small>';
    }
    const className = Number(change) > 0 ? 'is-positive' : (Number(change) < 0 ? 'is-negative' : 'is-neutral');
    const prefix = Number(change) > 0 ? '+' : '';
    return `<small class="admin-analytics__comparison ${className}">${this.escapeHTML(`${prefix}${this.number(change, 1)}% vs poprzedni okres`)}</small>`;
  }

  eventLabel(value) {
    const labels = {
      page_view: 'Odsłona',
      registration: 'Rejestracja',
      login: 'Logowanie',
      test_started: 'Start testu',
      test_completed: 'Ukończenie testu',
    };
    return labels[value] || value || 'Zdarzenie';
  }

  emptyState(message = 'Brak danych dla wybranego zakresu i filtrów.') {
    return `<div class="admin-empty">${this.escapeHTML(message)}</div>`;
  }

  setLoading(loading) {
    this.root.classList.toggle('is-loading', loading);
    this.root.setAttribute('aria-busy', loading ? 'true' : 'false');
    this.loadingStatus?.classList.toggle('hidden', !loading);
    this.form?.querySelector('[data-filter-submit]')?.toggleAttribute('disabled', loading);
  }

  showError(message) {
    if (!this.errorBox) return;
    this.errorBox.textContent = message;
    this.errorBox.classList.remove('hidden');
  }

  hideError() {
    this.errorBox?.classList.add('hidden');
  }

  fullName(row) {
    return [row.first_name, row.last_name].filter(Boolean).join(' ').trim();
  }

  testTypeLabel(value) {
    return value === 'full_exam' ? 'Pełny test' : (value === 'personalized' ? 'Test personalizowany' : value || '');
  }

  number(value, decimals = 0) {
    return new Intl.NumberFormat('pl-PL', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(Number(value) || 0);
  }

  percent(value) {
    return `${this.number(value, 1)}%`;
  }

  duration(value) {
    const seconds = Math.max(0, Number(value) || 0);
    const minutes = Math.floor(seconds / 60);
    const remainder = Math.round(seconds % 60);
    return minutes > 0 ? `${minutes} min ${remainder} s` : `${remainder} s`;
  }

  date(value) {
    if (!value || value === '1970-01-01 00:00:00') return 'Brak';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('pl-PL', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  }

  shortDate(value, granularity) {
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('pl-PL', granularity === 'month'
      ? { month: 'short', year: '2-digit' }
      : { day: '2-digit', month: '2-digit' }
    ).format(date);
  }

  truncate(value, limit) {
    const text = String(value || '');
    return text.length > limit ? `${text.slice(0, limit)}...` : text;
  }

  escapeHTML(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
}

export function initAdminAnalytics() {
  return new AdminAnalytics();
}
