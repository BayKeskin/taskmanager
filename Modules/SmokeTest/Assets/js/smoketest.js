// Modules/SmokeTest/Assets/js/smoketest.js
window.TaskManagerSmokeTest = {
    modalId: 'modalSmokeTest',

    init: function() {
        document.addEventListener('click', function(e) {
            if (e.target.closest('#btnOpenSmokeTestModal') || e.target.closest('#btnRunSmokeTestSettings') || e.target.closest('#btnRerunSmokeTest')) {
                e.preventDefault();
                TaskManagerSmokeTest.openAndRun();
            }
        });
    },

    openAndRun: function() {
        const modal = document.getElementById(this.modalId);
        if (modal) {
            modal.classList.add('active');
        }

        const bar = document.getElementById('smokeProgressBar');
        const statusText = document.getElementById('smokeStatusText');
        const list = document.getElementById('smokeTestResultsList');

        const i18n = window.SMOKE_I18N || {};
        const smokeBaseUrl = (window.BASE_URL || '').replace(/\/+$/, '');

        if (bar) bar.style.width = '10%';
        if (statusText) statusText.innerText = i18n.running_tests || '...';
        if (list) list.innerHTML = '';

        setTimeout(async () => {
            if (bar) bar.style.width = '45%';
            try {
                const res = await fetch(`${smokeBaseUrl}/ajax?action=run_smoke_test`);
                const data = await res.json();

                if (bar) bar.style.width = '100%';

                if (data && data.data && Array.isArray(data.data.results)) {
                    if (statusText) {
                        statusText.innerText = data.message || `${data.data.passed}/${data.data.total}`;
                    }

                    if (list) {
                        list.innerHTML = '';
                        data.data.results.forEach(item => {
                            const row = document.createElement('div');
                            row.className = 'cu-smoke-item';

                            const left = document.createElement('div');
                            left.className = 'cu-smoke-item-left';

                            const icon = document.createElement('span');
                            icon.className = 'cu-smoke-check-icon';
                            icon.innerText = item.passed ? '✔' : '✖';
                            if (!item.passed) {
                                icon.style.color = '#eb5757';
                            }

                            const name = document.createElement('span');
                            name.innerText = item.name;

                            left.appendChild(icon);
                            left.appendChild(name);

                            const badge = document.createElement('span');
                            badge.className = 'cu-smoke-badge ' + (item.passed ? 'cu-smoke-pass' : 'cu-smoke-fail');
                            badge.innerText = item.details || (item.passed ? 'PASS' : 'FAIL');

                            row.appendChild(left);
                            row.appendChild(badge);
                            list.appendChild(row);
                        });
                    }
                } else {
                    if (statusText) statusText.innerText = i18n.parse_error || 'Error';
                }
            } catch (err) {
                if (bar) bar.style.width = '100%';
                if (statusText) statusText.innerText = (i18n.error_prefix || 'Error: ') + err.message;
            }
        }, 300);
    }
};

document.addEventListener('DOMContentLoaded', function() {
    window.TaskManagerSmokeTest.init();
});
