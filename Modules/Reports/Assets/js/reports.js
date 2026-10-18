// Modules/Reports/Assets/js/reports.js
window.TaskManagerReports = {
    loadChart: function(canvasId, chartData) {
        if (typeof Chart !== 'undefined') {
            const ctx = document.getElementById(canvasId)?.getContext('2d');
            if (ctx) {
                new Chart(ctx, chartData);
            }
        }
    }
};
