/**
 * Dispatcher Module Interactions & Dashboard Logic
 */
(function() {
    'use strict';

    function getBaseUrl() {
        if (typeof window.BASE_URL !== 'undefined' && window.BASE_URL) {
            return window.BASE_URL.replace(/\/+$/, '');
        }
        if (window.APP_CONFIG && window.APP_CONFIG.baseUrl) {
            return window.APP_CONFIG.baseUrl.replace(/\/+$/, '');
        }
        return '';
    }

    async function fetchJson(action, params = {}, method = 'GET') {
        const base = getBaseUrl();
        const url = `${base}/ajax?action=${encodeURIComponent(action)}`;
        const options = {
            method: method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (method === 'POST') {
            const formData = new FormData();
            for (const key in params) {
                if (Object.prototype.hasOwnProperty.call(params, key)) {
                    formData.append(key, params[key]);
                }
            }
            options.body = formData;
        }

        const res = await fetch(url, options);
        return await res.json();
    }

    window.Dispatcher = {
        async checkStatus() {
            return await fetchJson('dispatcher_get_status');
        },
        async syncWorkspaces() {
            return await fetchJson('dispatcher_sync_workspaces', {}, 'POST');
        },
        async triggerTask(taskId, projectId) {
            return await fetchJson('dispatcher_trigger_task', { task_id: taskId, project_id: projectId }, 'POST');
        },
        async abortTask(taskId) {
            return await fetchJson('dispatcher_abort_task', { task_id: taskId }, 'POST');
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        const tabBtns = document.querySelectorAll('.dispatcher-tab-btn');
        const tabContents = document.querySelectorAll('.dispatcher-tab-content');
        const btnRefresh = document.getElementById('btnRefreshStatus');
        const btnSync = document.getElementById('btnSyncWorkspaces');
        const btnClearLogs = document.getElementById('btnClearLogs');
        const terminal = document.getElementById('dispatcherTerminal');

        // Tab Switcher
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-tab');
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                this.classList.add('active');
                const targetContent = document.getElementById(targetId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });

        // Clear Logs
        if (btnClearLogs && terminal) {
            btnClearLogs.addEventListener('click', function() {
                terminal.innerHTML = '<div class="log-line log-muted">Terminal cleared.</div>';
            });
        }

        // Refresh Data
        async function refreshDashboard() {
            try {
                if (btnRefresh) {
                    btnRefresh.disabled = true;
                }
                const data = await window.Dispatcher.checkStatus();
                if (data.status === 'success') {
                    // Update Bridge Metric
                    const metricBridge = document.getElementById('metricBridgeValue');
                    if (metricBridge) {
                        metricBridge.className = `dispatcher-badge-status ${data.is_running ? 'online' : 'offline'}`;
                        metricBridge.textContent = data.is_running ? 'ONLINE (Port 4000)' : 'OFFLINE';
                    }

                    // Update Counts
                    const qCount = document.getElementById('metricQueueCount');
                    if (qCount) qCount.textContent = data.queued_count;
                    const aCount = document.getElementById('metricActiveCount');
                    if (aCount) aCount.textContent = data.active_count;
                    const pCount = document.getElementById('metricProjectCount');
                    if (pCount) pCount.textContent = data.project_count;

                    const tbQueue = document.getElementById('tabBadgeQueue');
                    if (tbQueue) tbQueue.textContent = data.queued_count;
                    const tbActive = document.getElementById('tabBadgeActive');
                    if (tbActive) tbActive.textContent = data.active_count;

                    // Update Logs
                    if (terminal && Array.isArray(data.recent_logs)) {
                        if (data.recent_logs.length === 0) {
                            terminal.innerHTML = '<div class="log-line log-muted">No log entries recorded.</div>';
                        } else {
                            terminal.innerHTML = data.recent_logs.map(line => {
                                const escaped = String(line).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                return `<div class="log-line">${escaped}</div>`;
                            }).join('');
                            terminal.scrollTop = terminal.scrollHeight;
                        }
                    }
                }
            } catch (err) {
                console.error('Dispatcher refresh error:', err);
            } finally {
                if (btnRefresh) {
                    btnRefresh.disabled = false;
                }
            }
        }

        if (btnRefresh) {
            btnRefresh.addEventListener('click', refreshDashboard);
        }

        // Sync Workspaces
        if (btnSync) {
            btnSync.addEventListener('click', async function() {
                try {
                    btnSync.disabled = true;
                    const res = await window.Dispatcher.syncWorkspaces();
                    alert(res.message || 'Workspaces sync completed');
                } catch (err) {
                    alert('Sync error: ' + err.message);
                } finally {
                    btnSync.disabled = false;
                }
            });
        }

        // Action Buttons delegation (Dispatch / Abort)
        document.addEventListener('click', async function(e) {
            const i18n = window.dispatcherTranslations || {};
            const triggerBtn = e.target.closest('.btn-action-trigger');
            if (triggerBtn) {
                const taskId = triggerBtn.getAttribute('data-task-id');
                const projectId = triggerBtn.getAttribute('data-project-id');
                const confirmMsg = i18n.confirmDispatch || 'Dispatch task?';
                if (confirm(confirmMsg)) {
                    triggerBtn.disabled = true;
                    try {
                        const res = await window.Dispatcher.triggerTask(taskId, projectId);
                        alert(res.message || 'OK');
                        window.location.reload();
                    } catch (err) {
                        alert(err.message || 'Error');
                        triggerBtn.disabled = false;
                    }
                }
                return;
            }

            const abortBtn = e.target.closest('.btn-action-abort');
            if (abortBtn) {
                const taskId = abortBtn.getAttribute('data-task-id');
                const confirmMsg = i18n.confirmAbort || 'Abort task?';
                if (confirm(confirmMsg)) {
                    abortBtn.disabled = true;
                    try {
                        const res = await window.Dispatcher.abortTask(taskId);
                        alert(res.message || 'OK');
                        window.location.reload();
                    } catch (err) {
                        alert(err.message || 'Error');
                        abortBtn.disabled = false;
                    }
                }
            }
        });

        // Terminal auto-scroll to bottom on page load
        if (terminal) {
            terminal.scrollTop = terminal.scrollHeight;
        }
    });
})();
