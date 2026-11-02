const dailyChart = document.querySelector('[data-seller-daily-chart]');
if (dailyChart && window.ApexCharts) {
    const chartElement = document.getElementById('dailyChart');
    try {
        const sales = JSON.parse(dailyChart.dataset.sales || '[]');
        const days = JSON.parse(dailyChart.dataset.days || '[]');
        // Text, grid and tooltip follow the console theme, light or dark.
        const themed = () => {
            const dark = document.body.classList.contains('dark');
            return {
                chart: { foreColor: dark ? '#9a958c' : '#667085', fontFamily: "'Instrument Sans', system-ui, sans-serif" },
                grid: { borderColor: dark ? '#2c3238' : '#ece6dc', strokeDashArray: 4 },
                tooltip: { theme: dark ? 'dark' : 'light' },
            };
        };
        const theme = themed();
        const chart = new ApexCharts(chartElement, {
            chart: { type: 'bar', height: 220, toolbar: { show: false }, ...theme.chart },
            series: [{ name: 'Sales (PHP)', data: sales }],
            xaxis: { categories: days, labels: { rotate: -45, style: { fontSize: '10px' } } },
            colors: ['#E8472A'], dataLabels: { enabled: false },
            plotOptions: { bar: { borderRadius: 4 } },
            yaxis: { labels: { formatter: value => 'PHP ' + value.toLocaleString() } },
            tooltip: { y: { formatter: value => 'PHP ' + value.toLocaleString() }, theme: theme.tooltip.theme }, grid: theme.grid,
        });
        // The layout flips body.dark on the same event; read it once that has run.
        window.addEventListener('picksell:theme-toggle', () => setTimeout(() => chart.updateOptions(themed(), false, false), 0));
        chart.render();
    } catch (error) {
        console.error('Unable to render seller daily chart.', error);
    }
}

