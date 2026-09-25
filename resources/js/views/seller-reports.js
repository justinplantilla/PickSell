const dailyChart = document.querySelector('[data-seller-daily-chart]');
if (dailyChart && window.ApexCharts) {
    const chartElement = document.getElementById('dailyChart');
    try {
        const sales = JSON.parse(dailyChart.dataset.sales || '[]');
        const days = JSON.parse(dailyChart.dataset.days || '[]');
        new ApexCharts(chartElement, {
            chart: { type: 'bar', height: 220, toolbar: { show: false } },
            series: [{ name: 'Sales (PHP)', data: sales }],
            xaxis: { categories: days, labels: { rotate: -45, style: { fontSize: '10px' } } },
            colors: ['#E8472A'], dataLabels: { enabled: false },
            yaxis: { labels: { formatter: value => 'PHP ' + value.toLocaleString() } },
            tooltip: { y: { formatter: value => 'PHP ' + value.toLocaleString() } }, grid: { borderColor: '#e7e1d8' },
        }).render();
    } catch (error) {
        console.error('Unable to render seller daily chart.', error);
    }
}

