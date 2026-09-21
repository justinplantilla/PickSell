const chartData = document.querySelector('[data-admin-chart-data]');
if (chartData && window.ApexCharts) {
    const buyers = Number(chartData.dataset.buyers);
    const sellers = Number(chartData.dataset.sellers);
    const couriers = Number(chartData.dataset.couriers);
    const total = Number(chartData.dataset.total);
    new ApexCharts(document.getElementById('userChart'), {
        chart: { type: 'donut', height: 280 },
        series: [buyers, sellers, couriers],
        labels: ['Buyers', 'Sellers', 'Couriers'],
        colors: ['#2563eb', '#16a34a', '#d97706'],
        legend: { position: 'bottom' },
        plotOptions: { pie: { donut: { size: '60%' } } },
    }).render();
    new ApexCharts(document.getElementById('trendChart'), {
        chart: { type: 'area', height: 280, toolbar: { show: false } },
        series: [{ name: 'Registrations', data: [4, 7, 5, 12, 9, total] }],
        xaxis: { categories: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'] },
        colors: ['#E8472A'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
    }).render();
}
