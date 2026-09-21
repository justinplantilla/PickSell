const commissionChart = document.querySelector('[data-commission-chart]');
if (commissionChart && window.ApexCharts) {
    const commissions = JSON.parse(commissionChart.dataset.commissions || '[]');
    const orders = JSON.parse(commissionChart.dataset.orders || '[]');
    if (commissions.length) {
        new ApexCharts(document.getElementById('commissionChart'), {
            chart: { type: 'bar', height: 280, toolbar: { show: false } },
            series: [{ name: 'Commission (₱)', data: commissions }],
            xaxis: { categories: orders },
            colors: ['#E8472A'],
            dataLabels: { enabled: false },
            plotOptions: { bar: { borderRadius: 4 } },
        }).render();
    } else {
        document.getElementById('commissionChart').innerHTML = '<p style="text-align:center;color:#aaa;padding:2rem;">No data available</p>';
    }
}
