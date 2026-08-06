/**
 * @file DashboardSparklines.js
 * @module DashboardSparklines
 * @description
 * Mini-wykresy trendów (sparklines) na kartach KPI dashboardu admina.
 * Dane dociągane asynchronicznie po pierwszym renderze strony — liczby KPI
 * są serwerowane od razu, wykresy to progresywne wzbogacenie.
 *
 * Kontrakt HTML: kontener `.admin-kpi-grid[data-trends-endpoint]`,
 * karty `[data-sparkline-metric="<klucz serii>"]` z `<canvas class="admin-kpi__sparkline">`.
 * Wymaga globalnego `window.Chart` (ładowanego przez loadScriptOnce w main.js).
 *
 * @author Tobiasz Szerszeń
 */

export async function initDashboardSparklines() {
  const grid = document.querySelector('[data-trends-endpoint]');
  if (!grid || typeof window.Chart !== 'function') return;

  let series;
  try {
    const response = await fetch(grid.dataset.trendsEndpoint, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await response.json();
    if (!response.ok || !data.ok) return;
    series = data.series || {};
  } catch (error) {
    return; // Sparklines są opcjonalne — cicha rezygnacja przy błędzie sieci.
  }

  const accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#10b981';

  document.querySelectorAll('[data-sparkline-metric]').forEach((card) => {
    const canvas = card.querySelector('canvas.admin-kpi__sparkline');
    const points = series[card.dataset.sparklineMetric];
    if (!canvas || !Array.isArray(points) || points.length === 0) return;

    new window.Chart(canvas, {
      type: 'line',
      data: {
        labels: points.map((point) => point.date),
        datasets: [{
          data: points.map((point) => point.value),
          borderColor: accent,
          borderWidth: 2,
          backgroundColor: 'transparent',
          fill: false,
          tension: 0.35,
          pointRadius: 0,
          pointHitRadius: 6,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            displayColors: false,
            callbacks: {
              title: (items) => items[0]?.label || '',
              label: (item) => String(item.parsed.y),
            },
          },
        },
        scales: {
          x: { display: false },
          y: { display: false, beginAtZero: true },
        },
      },
    });
  });
}
