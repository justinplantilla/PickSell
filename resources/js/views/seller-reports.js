const dailyChart = document.querySelector('[data-seller-daily-chart]');
if (dailyChart && window.ApexCharts) {
    new ApexCharts(document.getElementById('dailyChart'), {
        chart: { type: 'bar', height: 220, toolbar: { show: false } },
        series: [{ name: 'Sales (₱)', data: JSON.parse(dailyChart.dataset.sales || '[]') }],
        xaxis: { categories: JSON.parse(dailyChart.dataset.days || '[]'), labels: { rotate: -45, style: { fontSize: '10px' } } },
        colors: ['#E8472A'], dataLabels: { enabled: false },
        yaxis: { labels: { formatter: value => '₱' + value.toLocaleString() } },
        tooltip: { y: { formatter: value => '₱' + value.toLocaleString() } }, grid: { borderColor: '#f0ebe0' },
    }).render();
}

