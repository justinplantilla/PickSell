const salesChart = document.querySelector('[data-seller-sales-chart]');
if (salesChart && window.ApexCharts) {
    const chartElement = document.getElementById('salesChart');
    try {
        const sales = JSON.parse(salesChart.dataset.sales || '[]');
        const months = JSON.parse(salesChart.dataset.months || '[]');
        new ApexCharts(chartElement, {
            chart: { type: 'area', height: 220, toolbar: { show: false }, sparkline: { enabled: false } },
            series: [{ name: 'Sales (PHP)', data: sales }],
            xaxis: { categories: months },
            colors: ['#E8472A'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
            stroke: { curve: 'smooth', width: 2 }, dataLabels: { enabled: false },
            yaxis: { labels: { formatter: value => 'PHP ' + value.toLocaleString() } },
            tooltip: { y: { formatter: value => 'PHP ' + value.toLocaleString() } }, grid: { borderColor: '#e7e1d8' },
        }).render();
    } catch (error) {
        console.error('Unable to render seller sales chart.', error);
    }
}

