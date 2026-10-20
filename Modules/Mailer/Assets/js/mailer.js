// Modules/Mailer/Assets/js/mailer.js
window.TaskManagerMailer = {
    testConnection: async function() {
        const base = (window.BASE_URL || '').replace(/\/+$/, '');
        const res = await fetch(`${base}/ajax?action=test_smtp_connection`);
        return await res.json();
    }
};
