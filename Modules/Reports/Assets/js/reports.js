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

document.addEventListener('DOMContentLoaded', function() {
    // Fill progress bar from data-rate
    const progressFill = document.querySelector('.cu-rep-progress-fill[data-rate]');
    if (progressFill) {
        const rate = parseFloat(progressFill.getAttribute('data-rate')) || 0;
        progressFill.style.width = rate + '%';
    }

    // PDF delivery download top button
    const btnPdfTop = document.getElementById('btnDownloadDeliveryReportTop');
    if (btnPdfTop) {
        btnPdfTop.addEventListener('click', function() {
            if (typeof downloadDeliveryReportPdf === 'function') {
                downloadDeliveryReportPdf();
            }
        });
    }

    // PDF delivery print button
    const btnPrint = document.getElementById('btnPrintDeliveryReport');
    if (btnPrint) {
        btnPrint.addEventListener('click', function() {
            const pId = parseInt(this.getAttribute('data-project-id'), 10) || 0;
            if (typeof downloadDeliveryReportPdf === 'function') {
                downloadDeliveryReportPdf(pId);
            }
        });
    }
});
