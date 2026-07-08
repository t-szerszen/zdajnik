/**
 * Komponent wykresu trendu skuteczności oparty na Chart.js
 * Wymaga załadowanej biblioteki Chart.js
 */
export class EfficiencyChart {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas) {
      console.warn(`EfficiencyChart: Nie znaleziono elementu canvas o ID "${canvasId}"`);
      return;
    }

    this.ctx = this.canvas.getContext('2d');
    this.chartInstance = null;
    this.init();
  }

  init() {
    const rawData = this.canvas.dataset.chartData;
    if (!rawData) {
      console.warn('EfficiencyChart: Brak danych w atrybucie data-chart-data');
      return;
    }

    try {
      const data = JSON.parse(rawData);
      this.render(data);
    } catch (e) {
      console.error('EfficiencyChart: Błąd parsowania danych JSON', e);
    }
  }

  getAccentColor() {
    return getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#10b981';
  }

  render(data) {
    const labels = data.map(point => {
      const date = new Date(point.completed_at);
      return date.toLocaleDateString('pl-PL', { day: '2-digit', month: '2-digit' });
    });

    const scores = data.map(point => point.moving_avg_score);
    const accentColor = this.getAccentColor();

    // Destroy existing instance if re-rendering
    if (this.chartInstance) {
      this.chartInstance.destroy();
    }

    this.chartInstance = new window.Chart(this.ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Skuteczność',
          data: scores,
          borderColor: accentColor,
          backgroundColor: (context) => {
            const chart = context.chart;
            const { ctx, chartArea } = chart;
            if (!chartArea) return null;
            const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
            gradient.addColorStop(0, accentColor + 'CC'); // 80% opacity
            gradient.addColorStop(1, accentColor + '1A'); // 10% opacity
            return gradient;
          },
          borderWidth: 3,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: accentColor,
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          pointHoverBackgroundColor: accentColor,
          pointHoverBorderColor: '#fff',
          pointHoverBorderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: { size: 14, weight: 'bold' },
            bodyFont: { size: 14 },
            callbacks: {
              label: function (context) {
                return `Skuteczność: ${context.parsed.y}%`;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            grid: {
              color: 'rgba(0, 0, 0, 0.05)',
              drawBorder: false
            },
            ticks: {
              stepSize: 20,
              font: { size: 11 },
              callback: value => value + '%'
            }
          },
          x: {
            grid: {
              display: false
            },
            ticks: {
              font: { size: 11 }
            }
          }
        }
      }
    });
  }
}

export function initEfficiencyChart(canvasId = 'efficiencyTrendChart') {
  if (!document.getElementById(canvasId)) return null;
  return new EfficiencyChart(canvasId);
}
