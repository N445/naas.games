import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';

const PALETTE = ['#4f7cff', '#22c55e', '#f59e0b', '#ef4444'];

export default class extends Controller {
    static targets = ['canvas'];
    static values = { type: String, labels: Array, datasets: Array };

    connect() {
        this.chart = new Chart(this.canvasTarget, {
            type: this.typeValue,
            data: this.buildData(),
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: this.datasetsValue.length > 1 } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }

    disconnect() {
        this.chart?.destroy();
    }

    datasetsValueChanged() {
        if (!this.chart) {
            return;
        }
        this.chart.data = this.buildData();
        this.chart.options.plugins.legend.display = this.datasetsValue.length > 1;
        this.chart.update();
    }

    buildData() {
        return {
            labels: this.labelsValue,
            datasets: this.datasetsValue.map((dataset, index) => ({
                ...dataset,
                borderColor: PALETTE[index],
                backgroundColor: this.typeValue === 'bar' ? PALETTE[index] : `${PALETTE[index]}22`,
                fill: this.typeValue === 'line',
                tension: 0.3,
                pointRadius: 0,
                borderRadius: 4,
            })),
        };
    }
}
