// Modules/Mailer/Assets/js/mailer.js
window.TaskManagerMailer = {
    testConnection: async function() {
        const res = await fetch('/taskmanager/ajax?action=test_smtp_connection');
        return await res.json();
    }
};
