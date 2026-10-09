import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-report-chart]').forEach((canvas) => {
        const labels = JSON.parse(canvas.dataset.labels ?? '[]');
        const values = JSON.parse(canvas.dataset.values ?? '[]');
        const currency = canvas.dataset.currency === 'true';

        new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: canvas.dataset.label,
                    data: values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgb(23 45 78 / 12%)',
                    borderWidth: 2,
                    fill: true,
                    pointRadius: 2,
                    tension: 0.3,
                }],
            },
            options: {
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 450 },
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                const value = context.parsed.y ?? 0;

                                return currency
                                    ? `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                    : `${value}`;
                            },
                        },
                    },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: currency ? undefined : 0 } },
                    x: { ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                },
            },
        });
    });
});
