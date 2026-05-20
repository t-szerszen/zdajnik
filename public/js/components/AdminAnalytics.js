class AdminAnalytics {
  constructor() {
    this.root = document.querySelector('[data-analytics-root]');
    if (!this.root) {
      return;
    }

    this.endpoint = this.root.dataset.endpoint;
    this.form = this.root.querySelector('[data-analytics-form]');
    this.chartCanvas = this.root.querySelector('[data-chart-canvas]');
    this.chartEmpty = this.root.querySelector('[data-chart-empty]');
    this.initialData = this.readInitialData();
    this.chartInstance = null;

    this.bindEvents();
    this.render(this.initialData);
  }

  readInitialData() {
    const node = document.getElementById('analytics-initial-data');
    if (!node) {
      return null;
    }

    try {
      return JSON.parse(node.textContent || '{}');
    } catch (error) {
      console.error('AdminAnalytics: nie udało się odczytać danych startowych', error);
      return null;
    }
  }

  bindEvents() {
    if (!this.form) {
      return;
    }

    const rangeInput = this.form.querySelector('[data-range-input]');
    const fromInput = this.form.querySelector('[data-from-input]');
    const toInput = this.form.querySelector('[data-to-input]');
    const rangeButtons = this.form.querySelectorAll('[data-range-value]');

    rangeButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (rangeInput) {
          rangeInput.value = button.dataset.rangeValue || '30d';
        }

        if (fromInput) {
          fromInput.value = '';
        }
        if (toInput) {
          toInput.value = '';
        }

        rangeButtons.forEach((item) => item.classList.remove('is-active'));
        button.classList.add('is-active');
        this.submitForm();
      });
    });

    if (fromInput) {
      fromInput.addEventListener('change', () => {
        if (rangeInput) {
          rangeInput.value = 'custom';
        }
      });
    }

    if (toInput) {
      toInput.addEventListener('change', () => {
        if (rangeInput) {
          rangeInput.value = 'custom';
        }
      });
    }

    this.form.addEventListener('submit', (event) => {
      event.preventDefault();
      this.submitForm();
    });
  }

  async submitForm() {
    if (!this.form) {
      return;
    }

    const params = new URLSearchParams(new FormData(this.form));
    const query = params.toString();
    const endpoint = this.endpoint + (query ? `?${query}` : '');

    this.setLoading(true);

    try {
      const response = await fetch(endpoint, {
        headers: {
          'Accept': 'application/json',
        },
      });

      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || `Błąd serwera: ${response.status}`);
      }

      this.render(payload.data);
      window.history.replaceState({}, '', this.form.action + (query ? `?${query}` : ''));
    } catch (error) {
      console.error('AdminAnalytics: błąd pobierania danych', error);
      this.renderError(error.message || 'Nie udało się pobrać danych analitycznych.');
    } finally {
      this.setLoading(false);
    }
  }

  setLoading(isLoading) {
    this.root.classList.toggle('is-loading', isLoading);
  }

  render(data) {
    if (!data) {
      return;
    }

    this.applyRangeState(data.range);
    this.updateRangeLabel(data.range);
    this.updateKpis(data.summary || {});
    this.renderChart(data.daily || { labels: [] });
    this.renderExamActivity(data.examActivity || []);
    this.renderSingleQuestionActivity(data.singleQuestionActivity || []);
    this.renderHardestQuestions(data.hardestQuestions || []);
    this.renderRecentActivity(data.recentActivity || {});
  }

  renderError(message) {
    if (this.chartEmpty) {
      this.chartEmpty.classList.remove('hidden');
      this.chartEmpty.innerHTML = `<div class="admin-empty">${this.escapeHTML(message)}</div>`;
    }

    if (this.chartCanvas) {
      this.chartCanvas.classList.add('hidden');
    }

    if (this.chartInstance) {
      this.chartInstance.destroy();
      this.chartInstance = null;
    }
  }

  updateRangeLabel(range) {
    const label = this.root.querySelector('[data-range-label]');
    if (label && range && range.label) {
      label.textContent = range.label;
    }
  }

  applyRangeState(range) {
    if (!this.form || !range) {
      return;
    }

    const rangeInput = this.form.querySelector('[data-range-input]');
    const fromInput = this.form.querySelector('[data-from-input]');
    const toInput = this.form.querySelector('[data-to-input]');
    const rangeButtons = this.form.querySelectorAll('[data-range-value]');

    if (rangeInput) {
      rangeInput.value = range.value || '30d';
    }
    if (fromInput && typeof range.from === 'string') {
      fromInput.value = range.from;
    }
    if (toInput && typeof range.to === 'string') {
      toInput.value = range.to;
    }

    rangeButtons.forEach((button) => {
      button.classList.toggle('is-active', (button.dataset.rangeValue || '') === (range.value || ''));
    });
  }

  updateKpis(summary) {
    const mappings = {
      totalUsers: summary.totalUsers ?? 0,
      newRegistrationsToday: summary.newRegistrationsToday ?? 0,
      loginsToday: summary.loginsToday ?? 0,
      pageViewsToday: summary.pageViewsToday ?? 0,
      testsStartedToday: summary.testsStartedToday ?? 0,
      testsCompletedToday: summary.testsCompletedToday ?? 0,
      averageScore: `${this.formatNumber(summary.averageScore ?? 0, 1)}%`,
      questionsAnsweredToday: summary.questionsAnsweredToday ?? 0,
      singleQuestionStartedToday: summary.singleQuestionStartedToday ?? 0,
      singleQuestionStartedInRange: summary.singleQuestionStartedInRange ?? 0,
      singleQuestionAnsweredToday: summary.singleQuestionAnsweredToday ?? 0,
      singleQuestionAccuracy: `${this.formatNumber(summary.singleQuestionAccuracy ?? 0, 1)}%`,
    };

    Object.entries(mappings).forEach(([key, value]) => {
      const card = this.root.querySelector(`[data-kpi="${key}"] .admin-tile__value`);
      if (card) {
        card.textContent = String(value);
      }
    });
  }

  renderChart(daily) {
    const labels = (daily.labels || []).map((value) => {
      const date = new Date(`${value}T00:00:00`);
      if (Number.isNaN(date.getTime())) {
        return value;
      }

      return new Intl.DateTimeFormat('pl-PL', {
        day: '2-digit',
        month: '2-digit',
      }).format(date);
    });
    const datasets = [
      { label: 'Odwiedziny', data: daily.pageViews || [], color: 'rgba(59, 130, 246, 0.85)' },
      { label: 'Logowania', data: daily.logins || [], color: 'rgba(16, 185, 129, 0.85)' },
      { label: 'Rejestracje', data: daily.registrations || [], color: 'rgba(245, 158, 11, 0.85)' },
      { label: 'Rozpoczęte testy', data: daily.testsStarted || [], color: 'rgba(139, 92, 246, 0.85)' },
      { label: 'Ukończone testy', data: daily.testsCompleted || [], color: 'rgba(239, 68, 68, 0.85)' },
      { label: 'Start modułu jednego pytania', data: daily.singleQuestionStarted || [], color: 'rgba(236, 72, 153, 0.85)' },
      { label: 'Odpowiedzi w module', data: daily.singleQuestionAnswered || [], color: 'rgba(14, 165, 233, 0.85)' },
    ];

    const hasData = datasets.some((dataset) => Array.isArray(dataset.data) && dataset.data.some((value) => Number(value) > 0));

    if (!hasData) {
      if (this.chartInstance) {
        this.chartInstance.destroy();
        this.chartInstance = null;
      }

      if (this.chartEmpty) {
        this.chartEmpty.classList.remove('hidden');
        this.chartEmpty.innerHTML = '<div class="admin-empty">Brak danych dla wybranego zakresu.</div>';
      }

      if (this.chartCanvas) {
        this.chartCanvas.classList.add('hidden');
      }
      return;
    }

    if (this.chartCanvas) {
      this.chartCanvas.classList.remove('hidden');
    }
    if (this.chartEmpty) {
      this.chartEmpty.classList.add('hidden');
    }

    if (!window.Chart || !this.chartCanvas) {
      return;
    }

    if (this.chartInstance) {
      this.chartInstance.destroy();
    }

    const ctx = this.chartCanvas.getContext('2d');
    this.chartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: datasets.map((dataset) => ({
          label: dataset.label,
          data: dataset.data,
          backgroundColor: dataset.color,
          borderColor: dataset.color,
          borderRadius: 8,
          borderSkipped: false,
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              usePointStyle: true,
              boxWidth: 12,
            },
          },
          tooltip: {
            padding: 12,
            callbacks: {
              label: (context) => `${context.dataset.label}: ${context.parsed.y}`,
            },
          },
        },
        scales: {
          x: {
            stacked: false,
            grid: {
              display: false,
            },
            ticks: {
              maxRotation: 0,
              autoSkip: true,
            },
            title: {
              display: true,
              text: 'Data',
            },
          },
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0,
            },
            title: {
              display: true,
              text: 'Liczba zdarzeń',
            },
          },
        },
      },
    });
  }

  renderExamActivity(rows) {
    const container = this.root.querySelector('[data-exam-activity]');
    if (!container) {
      return;
    }

    if (!rows.length) {
      container.innerHTML = '<div class="admin-empty">Brak aktywności egzaminów dla wybranego zakresu.</div>';
      return;
    }

    container.innerHTML = rows.map((row) => `
      <div class="admin-table__row admin-analytics__table-row">
        <div>
          <strong>${this.escapeHTML(row.code || '')}</strong>
          <div class="admin-table__muted">${this.escapeHTML(row.name || '')}</div>
        </div>
        <span>${this.formatNumber(row.started_tests || 0)}</span>
        <span>${this.formatNumber(row.completed_tests || 0)}</span>
        <span>${this.formatNumber(row.avg_score || 0, 1)}%</span>
        <span>${this.formatNumber(row.unique_users || 0)}</span>
      </div>
    `).join('');
  }

  renderSingleQuestionActivity(rows) {
    const container = this.root.querySelector('[data-single-question-activity]');
    if (!container) {
      return;
    }

    if (!rows.length) {
      container.innerHTML = '<div class="admin-empty">Brak aktywności modułu jednego pytania dla wybranego zakresu.</div>';
      return;
    }

    container.innerHTML = rows.map((row) => `
      <div class="admin-table__row admin-analytics__table-row admin-analytics__table-row--single-question">
        <div>
          <strong>${this.escapeHTML(row.code || 'Brak przypisanego egzaminu')}</strong>
          <div class="admin-table__muted">${this.escapeHTML(row.name || 'Brak przypisanego egzaminu')}</div>
        </div>
        <span>${this.formatNumber(row.started_count || 0)}</span>
        <span>${this.formatNumber(row.answered_count || 0)}</span>
        <span>${this.formatNumber(row.unique_users || 0)}</span>
        <span>${this.formatNumber(row.accuracy || 0, 1)}%</span>
        <span>${this.escapeHTML(this.truncate(row.top_question_text || 'Brak danych', 80))}</span>
      </div>
    `).join('');
  }

  renderHardestQuestions(rows) {
    const container = this.root.querySelector('[data-hardest-questions]');
    if (!container) {
      return;
    }

    if (!rows.length) {
      container.innerHTML = '<div class="admin-empty">Brak danych dla wybranego zakresu.</div>';
      return;
    }

    container.innerHTML = rows.map((row) => `
      <div class="admin-table__row admin-analytics__table-row admin-analytics__table-row--hardest">
        <div>
          <strong>${this.escapeHTML(this.truncate(row.question_text || '', 120))}</strong>
        </div>
        <span>${this.escapeHTML(row.exam_code || '')}</span>
        <span>${this.formatNumber(row.total_answers || 0)}</span>
        <span>${this.formatNumber(row.wrong_answers || 0)}</span>
        <span>${this.formatNumber(row.wrong_percent || 0, 1)}%</span>
      </div>
    `).join('');
  }

  renderRecentActivity(recentActivity) {
    this.renderActivityList(
      this.root.querySelector('[data-recent-logins]'),
      recentActivity.logins || [],
      (item) => `
        <div class="admin-list__row">
          <div class="admin-list__primary">
            <strong>${this.escapeHTML(item.display_name || this.fullName(item) || 'Użytkownik nieznany')}</strong>
            <span>${this.escapeHTML(item.email || '')}</span>
          </div>
          <div class="admin-list__secondary">
            <span class="badge badge--secondary">${this.escapeHTML(item.ip_address || 'Brak IP')}</span>
            <span class="badge badge--secondary">${this.escapeHTML(item.browser_label || 'Brak danych')}</span>
            <span class="badge badge--secondary">login</span>
            <span class="badge badge--accent">${this.formatDate(item.event_date)}</span>
          </div>
        </div>
      `
    );

    this.renderActivityList(
      this.root.querySelector('[data-recent-registrations]'),
      recentActivity.registrations || [],
      (item) => `
        <div class="admin-list__row">
          <div class="admin-list__primary">
            <strong>${this.escapeHTML(this.fullName(item))}</strong>
            <span>${this.escapeHTML(item.email || '')}</span>
          </div>
          <div class="admin-list__secondary">
            <span class="badge badge--secondary">${this.escapeHTML(item.auth_provider || 'local')}</span>
            <span class="badge badge--accent">${this.formatDate(item.created_at)}</span>
          </div>
        </div>
      `
    );

    this.renderActivityList(
      this.root.querySelector('[data-recent-tests]'),
      recentActivity.testsCompleted || [],
      (item) => `
        <div class="admin-list__row">
          <div class="admin-list__primary">
            <strong>${this.escapeHTML(this.fullName(item))}</strong>
            <span>${this.escapeHTML(item.exam_code || '')} - ${this.escapeHTML(item.exam_name || '')}</span>
          </div>
          <div class="admin-list__secondary">
            <span class="badge badge--secondary">${this.formatNumber(item.correct_count || 0)}/${this.formatNumber(item.total_questions || 0)}</span>
            <span class="badge badge--accent">${this.formatDate(item.completed_at)}</span>
          </div>
        </div>
      `
    );
  }

  renderActivityList(container, rows, renderer) {
    if (!container) {
      return;
    }

    if (!rows.length) {
      container.innerHTML = '<div class="admin-empty">Brak danych.</div>';
      return;
    }

    container.innerHTML = rows.map(renderer).join('');
  }

  fullName(item) {
    return [item.first_name, item.last_name].filter(Boolean).join(' ').trim();
  }

  truncate(value, limit) {
    if (!value) {
      return '';
    }

    return value.length > limit ? `${value.slice(0, limit)}...` : value;
  }

  formatDate(value) {
    if (!value) {
      return 'Brak';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return String(value);
    }

    return new Intl.DateTimeFormat('pl-PL', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  }

  formatNumber(value, decimals = 0) {
    const num = Number(value) || 0;
    return new Intl.NumberFormat('pl-PL', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(num);
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

new AdminAnalytics();
