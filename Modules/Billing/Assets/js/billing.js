/**
 * Modules/Billing/Assets/js/billing.js
 */
window.BillingModule = {
    printReport: function() {
        window.print();
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const btnPrint = document.getElementById('btnBillingPrint');
    if (btnPrint) {
        btnPrint.addEventListener('click', function() {
            window.BillingModule.printReport();
        });
    }
});
