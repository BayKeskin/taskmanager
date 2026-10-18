/**
 * Modules/GithubDevops/Assets/js/github-devops.js
 * GitHub Entegrasyonu ve Cerrahi Rollback İstemci Mantığı
 */
window.openGithubSettingsModal = function() {
    openModal('modalGithubSettings');
};

window.saveGithubSettings = async function() {
    const username = document.getElementById('ghUsernameInput')?.value?.trim() || '';
    const token = document.getElementById('ghTokenInput')?.value?.trim() || '';
    if (!username || !token) {
        showToast('Lütfen GitHub kullanıcı adı ve PAT token giriniz.', 'warning');
        return;
    }
    try {
        const fd = new FormData();
        fd.append('github_username', username);
        fd.append('github_token', token);
        const res = await fetch('/taskmanager/ajax?action=save_github_settings', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modalGithubSettings');
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(data.message || 'Hata oluştu', 'error');
        }
    } catch (e) {
        console.error('saveGithubSettings error:', e);
        showToast('Sunucu hatası', 'error');
    }
};

window.syncProjectToGithub = async function(projectId) {
    if (!projectId) return;
    if (!confirm('Bu projenin GitHub reposunu kontrol edip eksikse oluşturmak ve senkronize etmek istiyor musunuz?')) return;
    try {
        showToast('GitHub ile senkronize ediliyor...', 'info');
        const fd = new FormData();
        fd.append('project_id', projectId);
        const res = await fetch('/taskmanager/ajax?action=sync_project_github', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message + (data.data?.repo_name ? ' (' + data.data.repo_name + ')' : ''), 'success');
        } else {
            showToast(data.message || 'Senkronizasyon başarısız', 'error');
        }
    } catch (e) {
        console.error('syncProjectToGithub error:', e);
        showToast('Sunucu hatası', 'error');
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
        showToast(runningMsg, 'info');

        const fd = new FormData();
        fd.append('task_id', taskId);
        fd.append('mode', mode);

        const res = await fetch('/taskmanager/ajax?action=rollback_task', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Geri alma işlemi başarılı.', 'success');
            if (typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == taskId) {
                if (typeof openTaskDrawer === 'function') openTaskDrawer(taskId);
            }
        } else {
            showToast(data.message || 'Geri alma başarısız.', 'error');
        }
    } catch (e) {
        console.error('rollbackTask error:', e);
        showToast('Sunucu hatası', 'error');
    }
};
