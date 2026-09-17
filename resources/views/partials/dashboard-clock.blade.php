<style>
    .dashboard-datetime { display: inline-flex; align-items: center; gap: 1rem; color: #a7a0ad; font-size: 0.76rem; font-weight: 700; letter-spacing: 0.08em; white-space: nowrap; }
    .dashboard-date { text-transform: uppercase; }
    .dashboard-time { color: #0ea5ff; letter-spacing: 0.04em; }
    @media (max-width: 640px) {
        .dashboard-datetime { gap: 0.55rem; font-size: 0.65rem; letter-spacing: 0.05em; }
    }
</style>
<div class="dashboard-datetime" aria-label="Current date and time">
    <span class="dashboard-date" data-dashboard-date></span>
    <span class="dashboard-time" data-dashboard-time></span>
</div>
<script>
    (() => {
        const dateElement = document.querySelector('[data-dashboard-date]');
        const timeElement = document.querySelector('[data-dashboard-time]');
        if (!dateElement || !timeElement) return;

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
    })();
</script>