const chartColors = ['#ef625b', '#f1a53a', '#43ad68', '#607ee8', '#9b67d5'];
const darkMode = document.body.classList.contains('dark');
const chartTextColor = darkMode ? '#a3a3a3' : '#777';
const chartGridColor = darkMode ? '#303030' : 'rgba(0,0,0,0.06)';

function chartData(selector) {
    const element = document.querySelector(selector);
    if (!element) return null;

    return {
        labels: JSON.parse(element.dataset.labels || '[]'),
        values: JSON.parse(element.dataset.values || '[]'),
    };
}

function renderDonut(elementId, data, centerLabel) {
    const target = document.getElementById(elementId);
    if (!target || !data) return;

    const values = data.values.map(Number);
    const total = values.reduce((sum, value) => sum + value, 0);
    if (total === 0) {
        target.classList.add('dashboard-chart-empty');
        target.textContent = 'No data available';
        return;
    }

    new window.ApexCharts(target, {
        chart: { type: 'donut', height: 190, fontFamily: 'inherit' },
        series: values,
        labels: data.labels,
        colors: chartColors,
        stroke: { width: 2, colors: [darkMode ? '#1e1e1e' : '#fff'] },
        dataLabels: { enabled: false },
        legend: { show: false },
        tooltip: { y: { formatter: value => Math.round(value).toLocaleString() } },
        plotOptions: {
            pie: {
                expandOnClick: false,
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { show: false },
                        value: { show: false },
                        total: {
                            show: true,
                            label: centerLabel,
                            color: chartTextColor,
                            formatter: () => total.toLocaleString(),
                        },
                    },
                },
            },
        },
    }).render();
}

if (window.ApexCharts) {
    const trend = chartData('[data-admin-trend]');
    const trendTarget = document.getElementById('trendChart');
    if (trend && trendTarget) {
        new window.ApexCharts(trendTarget, {
            chart: { type: 'area', height: 220, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'New registrations', data: trend.values.map(Number) }],
            xaxis: { categories: trend.labels, labels: { style: { colors: chartTextColor } } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Math.round(value), style: { colors: chartTextColor } } },
            colors: ['#E8472A'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02 } },
            stroke: { curve: 'smooth', width: 2 },
            markers: { size: 4, strokeWidth: 2 },
            grid: { borderColor: chartGridColor },
            dataLabels: { enabled: false },
            tooltip: { y: { formatter: value => `${value} ${value === 1 ? 'account' : 'accounts'}` } },
        }).render();
    }

    renderDonut('lifecycleChart', chartData('[data-admin-lifecycle]'), 'Orders');
    renderDonut('accountsChart', chartData('[data-admin-accounts]'), 'Accounts');
}
