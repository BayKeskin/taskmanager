/**
 * Modules/GithubDevops/Assets/js/github-devops.js
 * GitHub Entegrasyonu ve Cerrahi Rollback İstemci Mantığı
 */
window.openGithubSettingsModal = function() {
    if (typeof openModal === 'function') {
        openModal('modalGithubSettings');
    }
};

window.saveGithubSettings = async function() {
    const username = document.getElementById('ghUsernameInput')?.value?.trim() || '';
    const token = document.getElementById('ghTokenInput')?.value?.trim() || '';
    const dI18n = window.TASK_I18N?.devops || {};

    if (!username || !token) {
        if (typeof showToast === 'function') {
            showToast(dI18n.err_input_required || 'Lütfen GitHub kullanıcı adı ve PAT token giriniz.', 'warning');
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
            if (typeof showToast === 'function') showToast(data.message || 'Hata oluştu', 'error');
        }
    } catch (e) {
        console.error('saveGithubSettings error:', e);
        if (typeof showToast === 'function') showToast('Sunucu hatası', 'error');
    }
};

window.syncProjectToGithub = async function(projectId) {
    if (!projectId) return;
    const dI18n = window.TASK_I18N?.devops || {};
    const confirmMsg = dI18n.sync_confirm || 'Bu projenin GitHub reposunu kontrol edip eksikse oluşturmak ve senkronize etmek istiyor musunuz?';
    if (!confirm(confirmMsg)) return;
    try {
        if (typeof showToast === 'function') showToast(dI18n.syncing || 'GitHub ile senkronize ediliyor...', 'info');
        const fd = new FormData();
        fd.append('project_id', projectId);
        const res = await fetch('/taskmanager/ajax?action=sync_project_github', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message + (data.data?.repo_name ? ' (' + data.data.repo_name + ')' : ''), 'success');
            }
        } else {
            if (typeof showToast === 'function') showToast(data.message || 'Senkronizasyon başarısız', 'error');
        }
    } catch (e) {
        console.error('syncProjectToGithub error:', e);
        if (typeof showToast === 'function') showToast('Sunucu hatası', 'error');
    }
};

window.rollbackTask = async function(taskId, mode = 'safe') {
    if (!taskId) return;
    const dI18n = window.TASK_I18N?.devops || {};
    if (mode === 'safe') {
        const confirmMsg = dI18n.rollback_confirm || 'Bu görevin Git commit değişikliklerini ve veritabanı etkilerini geri almak istediğinize emin misiniz?';
        if (!confirm(confirmMsg)) {
            return;
        }
    }

    try {
        const runningMsg = (mode === 'ai_surgeon') 
            ? (dI18n.ai_surgeon_running || '🤖 AI Cerrah çakışmaları analiz edip temizliyor...') 
            : (dI18n.rollback_running || '⏪ Geri alma işlemi yürütülüyor...');
        if (typeof showToast === 'function') showToast(runningMsg, 'info');

        const fd = new FormData();
        fd.append('task_id', taskId);
        fd.append('mode', mode);

        const res = await fetch('/taskmanager/ajax?action=rollback_task', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            if (typeof showToast === 'function') showToast(data.message || 'Geri alma işlemi başarılı.', 'success');
            if (typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == taskId) {
                if (typeof openTaskDrawer === 'function') openTaskDrawer(taskId);
            }
        } else {
            if (typeof showToast === 'function') showToast(data.message || 'Geri alma başarısız.', 'error');
        }
    } catch (e) {
        console.error('rollbackTask error:', e);
        if (typeof showToast === 'function') showToast('Sunucu hatası', 'error');
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
});
