const salesChart = document.querySelector('[data-seller-sales-chart]');
const chartVisual = salesChart?.closest('[data-chart-visual]');
const setChartState = state => {
    if (chartVisual) chartVisual.dataset.chartState = state;
};

if (salesChart && window.ApexCharts) {
    const chartElement = document.getElementById('salesChart');
    try {
        const values = JSON.parse(salesChart.dataset.chartValues || '[]');
        const labels = JSON.parse(salesChart.dataset.chartLabels || '[]');
        const isOrderCount = salesChart.dataset.chartMetric === 'orders';
        const formatValue = value => isOrderCount
            ? `${Math.round(value).toLocaleString()} orders`
            : `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

        new ApexCharts(chartElement, {
            chart: { type: 'area', height: 330, toolbar: { show: false }, sparkline: { enabled: false } },
            series: [{ name: isOrderCount ? 'Orders' : 'Sales (PHP)', data: values }],
            xaxis: { categories: labels },
            colors: ['#E8472A'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
            stroke: { curve: 'smooth', width: 2 }, dataLabels: { enabled: false },
            yaxis: { labels: { formatter: value => isOrderCount ? Math.round(value).toLocaleString() : `₱${Number(value).toLocaleString('en-PH')}` } },
            tooltip: { y: { formatter: formatValue } },
            grid: { borderColor: '#e7e1d8' },
        }).render()
            .then(() => setChartState('ready'))
            .catch(error => {
                console.error('Unable to render seller sales chart.', error);
                setChartState('error');
            });
    } catch (error) {
        console.error('Unable to render seller sales chart.', error);
        setChartState('error');
    }
} else if (salesChart) {
    setChartState('error');
}

