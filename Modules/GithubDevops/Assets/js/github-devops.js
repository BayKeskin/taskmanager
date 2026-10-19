/**
 * Modules/GithubDevops/Assets/js/github-devops.js
 * GitHub Entegrasyonu ve Cerrahi Rollback İstemci Mantığı
 */
window.openGithubSettingsModal = async function() {
    if (typeof openModal === 'function') {
        openModal('modalGithubSettings');
    }
    
    // Veritabanındaki güncel GitHub ayarlarını çek ve doldur
    try {
        const res = await fetch('/taskmanager/ajax?action=get_settings');
        const data = await res.json();
        if (data && data.success && data.data) {
            const userInput = document.getElementById('ghUsernameInput');
            const tokenInput = document.getElementById('ghTokenInput');
            if (userInput && data.data.username) {
                userInput.value = data.data.username;
            }
            if (tokenInput && data.data.token) {
                tokenInput.value = data.data.token;
            }
        }
    } catch (err) {
        console.warn('Could not pre-fill github settings:', err);
    }
};

window.saveGithubSettings = async function() {
    const username = document.getElementById('ghUsernameInput')?.value?.trim() || '';
    const token = document.getElementById('ghTokenInput')?.value?.trim() || '';
    const dI18n = window.DEVOPS_I18N || window.TASK_I18N?.devops || {};

    if (!username || !token) {
        if (typeof showToast === 'function') {
            showToast(dI18n.err_input_required || '', 'warning');
        }
        return;
    }
    try {
        const fd = new FormData();
        fd.append('github_username', username);
        fd.append('github_token', token);
        const res = await fetch('/taskmanager/ajax?action=save_github_settings', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            if (typeof showToast === 'function') showToast(data.message, 'success');
            if (typeof closeModal === 'function') closeModal('modalGithubSettings');
            setTimeout(() => window.location.reload(), 500);
        } else {
            if (typeof showToast === 'function') showToast(data.message || dI18n.err_general || '', 'error');
        }
    } catch (e) {
        console.error('saveGithubSettings error:', e);
        if (typeof showToast === 'function') showToast(dI18n.err_server || '', 'error');
    }
};

window.syncProjectToGithub = async function(projectId) {
    if (!projectId) return;
    const dI18n = window.DEVOPS_I18N || window.TASK_I18N?.devops || {};
    const confirmMsg = dI18n.sync_confirm || '';
    if (confirmMsg && !confirm(confirmMsg)) return;
    try {
        if (typeof showToast === 'function') showToast(dI18n.syncing || '', 'info');
        const fd = new FormData();
        fd.append('project_id', projectId);
        const res = await fetch('/taskmanager/ajax?action=sync_project_github', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message + (data.data?.repo_name ? ' (' + data.data.repo_name + ')' : ''), 'success');
            }
        } else {
            if (typeof showToast === 'function') showToast(data.message || dI18n.sync_failed || '', 'error');
        }
    } catch (e) {
        console.error('syncProjectToGithub error:', e);
        if (typeof showToast === 'function') showToast(dI18n.err_server || '', 'error');
    }
};

window.rollbackTask = async function(taskId, mode = 'safe') {
    if (!taskId) return;
    const dI18n = window.DEVOPS_I18N || window.TASK_I18N?.devops || {};
    if (mode === 'safe') {
        const confirmMsg = dI18n.rollback_confirm || '';
        if (confirmMsg && !confirm(confirmMsg)) {
            return;
        }
    }

    try {
        const runningMsg = (mode === 'ai_surgeon') 
            ? (dI18n.ai_surgeon_running || '') 
            : (dI18n.rollback_running || '');
        if (typeof showToast === 'function' && runningMsg) showToast(runningMsg, 'info');

        const fd = new FormData();
        fd.append('task_id', taskId);
        fd.append('mode', mode);

        const res = await fetch('/taskmanager/ajax?action=rollback_task', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            if (typeof showToast === 'function') showToast(data.message || dI18n.rollback_success || '', 'success');
            if (typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == taskId) {
                if (typeof openTaskDrawer === 'function') openTaskDrawer(taskId);
            }
        } else {
            if (typeof showToast === 'function') showToast(data.message || dI18n.rollback_failed || '', 'error');
        }
    } catch (e) {
        console.error('rollbackTask error:', e);
        if (typeof showToast === 'function') showToast(dI18n.err_server || '', 'error');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('modalGithubSettings');
    if (modalEl) {
        modalEl.addEventListener('click', function(e) {
            if (e.target === this && typeof closeModal === 'function') {
                closeModal('modalGithubSettings');
            }
        });
    }

    const btnCloseTop = document.getElementById('btnGhCloseTop');
    if (btnCloseTop) {
        btnCloseTop.addEventListener('click', function() {
            if (typeof closeModal === 'function') closeModal('modalGithubSettings');
        });
    }

    const btnCancel = document.getElementById('btnGhCancel');
    if (btnCancel) {
        btnCancel.addEventListener('click', function() {
            if (typeof closeModal === 'function') closeModal('modalGithubSettings');
        });
    }

    const btnSave = document.getElementById('btnGhSave');
    if (btnSave) {
        btnSave.addEventListener('click', function() {
            window.saveGithubSettings();
        });
    }

    const btnToggleToken = document.getElementById('btnGhToggleToken');
    if (btnToggleToken) {
        btnToggleToken.addEventListener('click', function() {
            const tokenInput = document.getElementById('ghTokenInput');
            if (tokenInput) {
                if (tokenInput.type === 'password') {
                    tokenInput.type = 'text';
                    this.textContent = '🔒';
                } else {
                    tokenInput.type = 'password';
                    this.textContent = '👁️';
                }
            }
        });
    }
});
