// Registration trend: real monthly counts supplied by AdminDashboardService.
const trend = document.querySelector('[data-admin-trend]');
const trendTarget = document.getElementById('trendChart');
if (trend && trendTarget && window.ApexCharts) {
    const labels = JSON.parse(trend.dataset.labels || '[]');
    const values = JSON.parse(trend.dataset.values || '[]');
    new ApexCharts(trendTarget, {
        chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'New registrations', data: values }],
        xaxis: { categories: labels },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Math.round(value) } },
        colors: ['#E8472A'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02 } },
        stroke: { curve: 'smooth', width: 2 },
        markers: { size: 4, strokeWidth: 2 },
        grid: { borderColor: 'rgba(0,0,0,0.06)' },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: value => `${value} ${value === 1 ? 'account' : 'accounts'}` } },
    }).render();
}
