const salesChart = document.querySelector('[data-seller-sales-chart]');
if (salesChart && window.ApexCharts) {
    new ApexCharts(document.getElementById('salesChart'), {
        chart: { type: 'area', height: 220, toolbar: { show: false }, sparkline: { enabled: false } },
        series: [{ name: 'Sales (₱)', data: JSON.parse(salesChart.dataset.sales || '[]') }],
        xaxis: { categories: JSON.parse(salesChart.dataset.months || '[]') },
        colors: ['#E8472A'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 }, dataLabels: { enabled: false },
        yaxis: { labels: { formatter: value => '₱' + value.toLocaleString() } },
        tooltip: { y: { formatter: value => '₱' + value.toLocaleString() } }, grid: { borderColor: '#f0ebe0' },
    }).render();
}

