const salesChart = document.querySelector('[data-sales-chart]');
if (salesChart && window.ApexCharts) {
    new ApexCharts(document.getElementById('salesChart'), {
        chart: { type: 'bar', height: 280, toolbar: { show: false } },
        series: [{ name: 'Sales (₱)', data: JSON.parse(salesChart.dataset.sales || '[]') }],
        xaxis: { categories: JSON.parse(salesChart.dataset.months || '[]') },
        colors: ['#E8472A'],
        dataLabels: { enabled: false },
        plotOptions: { bar: { borderRadius: 4 } },
    }).render();
}
