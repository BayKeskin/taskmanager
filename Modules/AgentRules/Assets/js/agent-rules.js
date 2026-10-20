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

    const btnSaveQaGlobal = document.getElementById('btnSaveQaGlobal');
    const qaGlobalRulesInput = document.getElementById('qaGlobalRulesInput');
    if (btnSaveQaGlobal && qaGlobalRulesInput) {
        btnSaveQaGlobal.addEventListener('click', async function () {
            const content = qaGlobalRulesInput.value.trim();
            btnSaveQaGlobal.disabled = true;

            try {
                const formData = new FormData();
                formData.append('qa_global_rules', content);

                const response = await fetch(getBaseUrl() + '/ajax?action=save_qa_global_rules', {
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
                btnSaveQaGlobal.disabled = false;
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
            const projectCode = this.getAttribute('data-project-code') || '';
            let hasDir = this.getAttribute('data-has-dir') === '1';
            let wsPath = this.getAttribute('data-workspace-path') || '';
            const row = this.closest('tr');

            // Eğer çalışma alanı tanımlı değilse kullanıcıya sor
            if (!wsPath) {
                const promptTemplate = (window.agentRulesTranslations && window.agentRulesTranslations.promptWorkspacePath)
                    ? window.agentRulesTranslations.promptWorkspacePath
                    : 'Enter workspace path for project [%s]:';
                const promptMsg = promptTemplate.replace('%s', projectCode || ('#' + projectId));
                const defaultSuggestion = 'c:/wamp64/www/' + (projectCode ? projectCode.toLowerCase() : 'project_' + projectId);
                const inputWs = window.prompt(promptMsg, defaultSuggestion);
                if (!inputWs) {
                    return;
                }
                wsPath = inputWs.trim();
            }

            this.disabled = true;

            try {
                const formData = new FormData();
                formData.append('project_id', projectId);
                if (wsPath) {
                    formData.append('workspace_path', wsPath);
                }

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
                    // Butonu senkronize et butonuna dönüştür
                    button.className = 'btn-agent-sync btn-sync-single';
                    button.textContent = (window.agentRulesTranslations && window.agentRulesTranslations.syncing) ? window.agentRulesTranslations.syncing : '🔄 Senkronize Et';
                    button.setAttribute('data-has-dir', '1');
                    button.setAttribute('data-workspace-path', wsPath);

                    // Workspace sütununu güncelle
                    const wsCell = row.querySelector('td:nth-child(2)');
                    if (wsCell) {
                        wsCell.innerHTML = '<span class="text-code-pill">' + wsPath + '</span>';
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
