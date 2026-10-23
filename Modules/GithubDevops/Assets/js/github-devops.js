/**
 * Modules/GithubDevops/Assets/js/github-devops.js
 * GitHub Entegrasyonu ve Cerrahi Rollback İstemci Mantığı
 */
const ghBaseUrl = (window.BASE_URL || '').replace(/\/+$/, '');

window.openGithubSettingsModal = async function() {
    if (typeof openModal === 'function') {
        openModal('modalGithubSettings');
    }
    
    // Veritabanındaki güncel GitHub ayarlarını çek ve doldur
    try {
        const res = await fetch(`${ghBaseUrl}/ajax?action=get_settings`);
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
        const res = await fetch(`${ghBaseUrl}/ajax?action=save_github_settings`, { method: 'POST', body: fd });
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
        const res = await fetch(`${ghBaseUrl}/ajax?action=sync_project_github`, { method: 'POST', body: fd });
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

        const res = await fetch(`${ghBaseUrl}/ajax?action=rollback_task`, { method: 'POST', body: fd });
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
            const isSafe = (typeof window.isSafeBackdropClick === 'function')
                ? window.isSafeBackdropClick(e, this)
                : (e.target === this);
            if (isSafe && typeof closeModal === 'function') {
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

    // Zaman Tüneli Modalı Kapatma Olayları
    const modalTm = document.getElementById('modalProjectTimeline');
    if (modalTm) {
        modalTm.addEventListener('click', function(e) {
            const isSafe = (typeof window.isSafeBackdropClick === 'function')
                ? window.isSafeBackdropClick(e, this)
                : (e.target === this);
            if (isSafe && typeof closeModal === 'function') {
                closeModal('modalProjectTimeline');
            }
        });
    }

    const btnTmCloseTop = document.getElementById('btnTmCloseTop');
    if (btnTmCloseTop) {
        btnTmCloseTop.addEventListener('click', function() {
            if (typeof closeModal === 'function') closeModal('modalProjectTimeline');
        });
    }

    const btnTmCloseBottom = document.getElementById('btnTmCloseBottom');
    if (btnTmCloseBottom) {
        btnTmCloseBottom.addEventListener('click', function() {
            if (typeof closeModal === 'function') closeModal('modalProjectTimeline');
        });
    }

    // Pano üzerindeki Zaman Tüneli Açma Butonları
    const btnTimelineTrigger = document.getElementById('btnOpenProjectTimeline');
    if (btnTimelineTrigger) {
        btnTimelineTrigger.addEventListener('click', function() {
            const menu = document.getElementById('kanbanToolsMenu');
            if (menu) menu.classList.remove('active');
            const pId = this.dataset.projectId || window.ACTIVE_PROJECT_ID || 0;
            window.openProjectTimelineModal(pId);
        });
    }
});

/**
 * Proje Zaman Tüneli & Sürüm Geçmişi (Time Machine) Modalı
 */
window.activeTimelineProjectId = null;

window.openProjectTimelineModal = async function(projectId) {
    const pId = parseInt(projectId || window.ACTIVE_PROJECT_ID || 0, 10);
    if (!pId) return;

    window.activeTimelineProjectId = pId;

    if (typeof openModal === 'function') {
        openModal('modalProjectTimeline');
    }

    const loadingEl = document.getElementById('tmTimelineLoading');
    const emptyEl = document.getElementById('tmTimelineEmpty');
    const listEl = document.getElementById('tmTimelineList');

    if (loadingEl) loadingEl.classList.remove('cu-hide');
    if (emptyEl) emptyEl.classList.add('cu-hide');
    if (listEl) {
        listEl.classList.add('cu-hide');
        listEl.innerHTML = '';
    }

    try {
        const res = await fetch(`${ghBaseUrl}/ajax?action=get_project_timeline&project_id=${pId}`);
        const data = await res.json();

        if (loadingEl) loadingEl.classList.add('cu-hide');

        const isSuccess = data && (data.success === true || data.data?.success === true);
        const timeline = (data && Array.isArray(data.timeline) ? data.timeline : null) || 
                         (data && data.data && Array.isArray(data.data.timeline) ? data.data.timeline : []) || [];

        const subtitleEl = document.getElementById('tmProjectSubtitle');
        const projTitle = data.project_title || data.data?.project_title || '';
        const curBranch = data.current_branch || data.data?.current_branch || '';
        if (subtitleEl && (projTitle || curBranch)) {
            subtitleEl.textContent = `${projTitle ? projTitle + ' • ' : ''}Branch: ${curBranch || 'main'}`;
        }

        if (isSuccess && timeline.length > 0) {
            renderProjectTimeline(timeline, pId);
            if (emptyEl) emptyEl.classList.add('cu-hide');
            if (listEl) listEl.classList.remove('cu-hide');
        } else {
            if (listEl) listEl.classList.add('cu-hide');
            if (emptyEl) emptyEl.classList.remove('cu-hide');
        }
    } catch (err) {
        console.error('Error fetching project timeline:', err);
        if (loadingEl) loadingEl.classList.add('cu-hide');
        if (emptyEl) emptyEl.classList.remove('cu-hide');
    }
};

function renderProjectTimeline(timeline, projectId) {
    const listEl = document.getElementById('tmTimelineList');
    if (!listEl) return;

    const dI18n = window.DEVOPS_I18N || {};
    listEl.innerHTML = '';

    timeline.forEach(item => {
        const card = document.createElement('div');
        card.className = 'tm-card' + (item.is_current ? ' tm-current' : '');

        // Header: Badge veya Canlı Durum
        const header = document.createElement('div');
        header.className = 'tm-card-header';

        if (item.is_current) {
            const liveBadge = document.createElement('span');
            liveBadge.className = 'tm-live-badge';
            liveBadge.textContent = '🟢 ' + (dI18n.current_live_version || '');
            header.appendChild(liveBadge);
        } else {
            const hashBadge = document.createElement('span');
            hashBadge.className = 'tm-hash-badge';
            hashBadge.textContent = item.short_hash || '';
            header.appendChild(hashBadge);
        }

        if (item.changed_files_count > 0) {
            const filesBadge = document.createElement('span');
            filesBadge.className = 'tm-hash-badge';
            const tmFilesTpl = dI18n.timeline_changed_files || '';
            filesBadge.textContent = tmFilesTpl.replace('{count}', item.changed_files_count);
            header.appendChild(filesBadge);
        }

        card.appendChild(header);

        // Title: Görev Başlığı veya Commit Mesajı
        const titleEl = document.createElement('h4');
        titleEl.className = 'tm-card-title';

        if (item.task_code) {
            const codeBadge = document.createElement('span');
            codeBadge.className = 'tm-task-code-badge';
            codeBadge.textContent = item.task_code;
            titleEl.appendChild(codeBadge);
        }

        const titleText = item.task_title ? item.task_title : item.subject;
        const textNode = document.createTextNode(titleText || '');
        titleEl.appendChild(textNode);
        card.appendChild(titleEl);

        // Meta: Yapan kişi, tarih ve Geri Dön butonu
        const meta = document.createElement('div');
        meta.className = 'tm-card-meta';

        const metaLeft = document.createElement('div');
        metaLeft.className = 'tm-meta-left';

        // Author
        const authorBox = document.createElement('div');
        authorBox.className = 'tm-author-info';
        const authorName = item.assigned_user ? item.assigned_user.name : (item.author_name || dI18n.robot_coder || dI18n.author_unknown || '');
        authorBox.textContent = '👤 ' + authorName;
        metaLeft.appendChild(authorBox);

        // Date
        const dateBox = document.createElement('div');
        dateBox.textContent = '📅 ' + (item.committed_at ? item.committed_at.substring(0, 16) : '');
        metaLeft.appendChild(dateBox);

        meta.appendChild(metaLeft);

        // Geri Dön butonu (Sadece geçmiş sürümler için)
        if (!item.is_current) {
            const revertBtn = document.createElement('button');
            revertBtn.type = 'button';
            revertBtn.className = 'tm-revert-btn';
            revertBtn.textContent = dI18n.btn_revert_to_this || '';
            revertBtn.addEventListener('click', function() {
                window.revertProjectToCommit(projectId, item.commit_hash);
            });
            meta.appendChild(revertBtn);
        }

        card.appendChild(meta);
        listEl.appendChild(card);
    });
}

window.revertProjectToCommit = async function(projectId, commitHash) {
    if (!projectId || !commitHash) return;

    const dI18n = window.DEVOPS_I18N || {};
    const confirmMsg = dI18n.revert_confirm_text || '';

    if (confirmMsg && !confirm(confirmMsg)) {
        return;
    }

    if (typeof showToast === 'function') {
        showToast(dI18n.reverting_in_progress || '', 'info');
    }

    try {
        const fd = new FormData();
        fd.append('project_id', projectId);
        fd.append('commit_hash', commitHash);

        const res = await fetch(`${ghBaseUrl}/ajax?action=revert_to_version`, {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data && data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || dI18n.rollback_success || '', 'success');
            }
            if (typeof closeModal === 'function') {
                closeModal('modalProjectTimeline');
            }
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } else {
            if (typeof showToast === 'function') {
                showToast((data && data.message) ? data.message : (dI18n.err_general || ''), 'error');
            }
        }
    } catch (err) {
        console.error('revertProjectToCommit error:', err);
        if (typeof showToast === 'function') {
            showToast(dI18n.err_server || '', 'error');
        }
    }
};
