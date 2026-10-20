document.addEventListener('DOMContentLoaded', function () {
    const btnSaveGlobal = document.getElementById('btnSaveGlobal');
    const btnSyncAll = document.getElementById('btnSyncAll');
    const globalRulesInput = document.getElementById('globalRulesInput');

    function getBaseUrl() {
        if (window.APP_CONFIG && window.APP_CONFIG.baseUrl) {
            return window.APP_CONFIG.baseUrl.replace(/\/$/, '');
        }
        return '';
    }

    if (btnSaveGlobal && globalRulesInput) {
        btnSaveGlobal.addEventListener('click', async function () {
            const content = globalRulesInput.value.trim();
            btnSaveGlobal.disabled = true;

            try {
                const formData = new FormData();
                formData.append('global_rules', content);

                const response = await fetch(getBaseUrl() + '/ajax?action=save_global_rules', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                } else {
                    alert(data.message || 'Error');
                }
            } catch (err) {
                alert('Connection error');
            } finally {
                btnSaveGlobal.disabled = false;
            }
        });
    }

    if (btnSyncAll) {
        btnSyncAll.addEventListener('click', async function () {
            const confirmMsg = (window.agentRulesTranslations && window.agentRulesTranslations.confirmSyncAll) 
                ? window.agentRulesTranslations.confirmSyncAll 
                : 'Synchronize all projects?';

            if (!confirm(confirmMsg)) {
                return;
            }

            btnSyncAll.disabled = true;

            try {
                const response = await fetch(getBaseUrl() + '/ajax?action=sync_all_projects', {
                    method: 'POST'
                });

                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Error');
                }
            } catch (err) {
                alert('Connection error');
            } finally {
                btnSyncAll.disabled = false;
            }
        });
    }

    document.querySelectorAll('.btn-sync-single').forEach(function (button) {
        button.addEventListener('click', async function () {
            const projectId = this.getAttribute('data-project-id');
            const row = this.closest('tr');
            this.disabled = true;

            try {
                const formData = new FormData();
                formData.append('project_id', projectId);

                const response = await fetch(getBaseUrl() + '/ajax?action=sync_project_rules', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    const syncCell = row.querySelector('.cell-last-sync');
                    if (syncCell && data.last_sync) {
                        syncCell.textContent = data.last_sync;
                    }
                    const badgeCell = row.querySelector('.cell-file-badge');
                    if (badgeCell) {
                        badgeCell.innerHTML = '<span class="badge-tag badge-success">' + 
                            ((window.agentRulesTranslations && window.agentRulesTranslations.statusSynced) ? window.agentRulesTranslations.statusSynced : 'Synced') + 
                            '</span>';
                    }
                } else {
                    alert(data.message || 'Error');
                }
            } catch (err) {
                alert('Connection error');
            } finally {
                button.disabled = false;
            }
        });
    });
});
