const dateElement = document.querySelector('[data-dashboard-date]');
const timeElement = document.querySelector('[data-dashboard-time]');

if (dateElement && timeElement) {
    const updateDashboardClock = () => {
        const currentTime = new Date();
        dateElement.textContent = currentTime.toLocaleDateString('en-US', {
            weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
        }).toUpperCase();
        timeElement.textContent = currentTime.toLocaleTimeString('en-US', {
            hour: 'numeric', minute: '2-digit', second: '2-digit'
        });
    };

    updateDashboardClock();
    window.setInterval(updateDashboardClock, 1000);
}
