// Modules/Projects/Assets/js/projects.js
const projectsBaseUrl = (window.BASE_URL || '').replace(/\/+$/, '');

async function handleCreateProject(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const prI18n = window.PROJECTS_I18N || {};

    try {
        const res = await fetch(`${projectsBaseUrl}/ajax?action=create_project`, {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            const msg = prI18n.project_created || 'Project created successfully!';
            showToast(msg, 'success');
            closeModal('modalNewProject');
            setTimeout(() => window.location.reload(), 700);
        } else {
            const failMsg = result.message || prI18n.project_create_failed || 'Failed';
            showToast(failMsg, 'error');
        }
    } catch (err) {
        const sErr = prI18n.server_error || 'Server error';
        showToast(sErr, 'error');
    }
}

function openEditProjectModal(projOrId) {
    if (!projOrId) return;
    openModal('modalEditProject');

    let projId = null;
    if (typeof projOrId === 'object' && projOrId !== null) {
        projId = projOrId.id;
        document.getElementById('editProjId').value = projOrId.id || '';
        document.getElementById('editProjTitle').value = projOrId.title || '';
        document.getElementById('editProjCode').value = projOrId.project_code || '';
        document.getElementById('editProjColor').value = projOrId.color || '#7B68EE';
        document.getElementById('editProjDesc').value = projOrId.description || '';
        document.getElementById('editProjWorkspacePath').value = projOrId.workspace_path || '';
        document.getElementById('editProjHourlyRate').value = projOrId.hourly_rate !== undefined ? projOrId.hourly_rate : '500.00';
        document.getElementById('editProjAgentRules').value = projOrId.agent_rules || '';
        document.getElementById('editProjWebhookUrl').value = projOrId.webhook_url || '';
        document.getElementById('editProjConvId').value = projOrId.agent_conversation_id || '';
    } else {
        projId = projOrId;
        document.getElementById('editProjId').value = projId;
    }

    if (projId) {
        fetch(projectsBaseUrl + '/ajax?action=get_project&id=' + projId)
            .then(res => res.json())
            .then(result => {
                if (result.success && result.data) {
                    const p = result.data;
                    document.getElementById('editProjId').value = p.id;
                    document.getElementById('editProjTitle').value = p.title || '';
                    document.getElementById('editProjCode').value = p.project_code || '';
                    document.getElementById('editProjColor').value = p.color || '#7B68EE';
                    document.getElementById('editProjHourlyRate').value = p.hourly_rate !== undefined ? p.hourly_rate : '500.00';
                    document.getElementById('editProjDesc').value = p.description || '';
                    document.getElementById('editProjWorkspacePath').value = p.workspace_path || '';
                    document.getElementById('editProjAgentRules').value = p.agent_rules || '';
                    document.getElementById('editProjWebhookUrl').value = p.webhook_url || '';
                    document.getElementById('editProjConvId').value = p.agent_conversation_id || '';
                }
            })
            .catch(err => console.error('Project details fetch error:', err));
    }
}

async function handleUpdateProject(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const prI18n = window.PROJECTS_I18N || {};

    try {
        const res = await fetch(`${projectsBaseUrl}/ajax?action=update_project`, {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            const savedMsg = prI18n.settings_saved || 'Settings saved!';
            showToast(savedMsg, 'success');
            closeModal('modalEditProject');
            setTimeout(() => window.location.reload(), 700);
        } else {
            const upFail = result.message || prI18n.update_failed || 'Failed';
            showToast(upFail, 'error');
        }
    } catch (err) {
        const sErr = prI18n.server_error || 'Server error';
        showToast(sErr, 'error');
    }
}

async function testCurrentProjectWebhook() {
    const projId = document.getElementById('editProjId')?.value;
    if (!projId) return;
    const prI18n = window.PROJECTS_I18N || {};

    const testingMsg = prI18n.webhook_testing || 'Sending webhook test...';
    showToast(testingMsg, 'info');

    try {
        const formData = new FormData();
        formData.append('project_id', projId);

        const res = await fetch(`${projectsBaseUrl}/ajax?action=test_project_webhook`, {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            showToast(result.message, 'success');
        } else {
            showToast(result.message, 'error');
        }
    } catch (err) {
        const connErr = prI18n.conn_error || 'Connection error';
        showToast(connErr, 'error');
    }
}

async function syncCurrentProjectRules() {
    const projId = document.getElementById('editProjId')?.value;
    const rules = document.getElementById('editProjAgentRules')?.value;
    if (!projId) return;
    const prI18n = window.PROJECTS_I18N || {};

    const syncMsg = prI18n.syncing_rules || 'Syncing rules...';
    showToast(syncMsg, 'info');

    try {
        const formData = new FormData();
        formData.append('project_id', projId);
        if (rules !== undefined) {
            formData.append('agent_rules', rules);
        }

        const res = await fetch(`${projectsBaseUrl}/ajax?action=sync_project_rules`, {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            const updatedMsg = '✅ ' + (result.message || prI18n.rules_updated || 'Updated');
            showToast(updatedMsg, 'success');
        } else {
            const syncFail = '⚠️ ' + (result.message || prI18n.sync_failed || 'Failed');
            showToast(syncFail, 'error');
        }
    } catch (err) {
        const connErr = prI18n.conn_error || 'Connection error';
        showToast(connErr, 'error');
    }
}

async function refineProjectRules(isEdit) {
    const btn = document.getElementById(isEdit ? 'btnRefineRulesEdit' : 'btnRefineRulesNew');
    const textarea = document.getElementById(isEdit ? 'editProjAgentRules' : 'newProjAgentRules');
    const titleInput = isEdit ? document.getElementById('editProjTitle') : document.querySelector('#formNewProject [name="title"]');
    const codeInput = isEdit ? document.getElementById('editProjCode') : document.querySelector('#formNewProject [name="project_code"]');
    const projId = isEdit ? document.getElementById('editProjId')?.value : null;
    const prI18n = window.PROJECTS_I18N || {};

    if (!textarea) return;

    let raw = textarea.value.trim();
    if (!raw) {
        const promptMsg = prI18n.refine_prompt || 'Please enter instructions.';
        showToast(promptMsg, 'info');
        textarea.focus();
        return;
    }

    const origBtnHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳ AI...';
    }

    try {
        const formData = new FormData();
        formData.append('raw_rules', raw);
        if (codeInput?.value) formData.append('project_code', codeInput.value);
        if (titleInput?.value) formData.append('project_title', titleInput.value);
        if (projId) formData.append('project_id', projId);

        const res = await fetch(`${projectsBaseUrl}/ajax?action=refine_project_rules`, {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success && result.data && result.data.refined_rules) {
            textarea.value = result.data.refined_rules;
            const successMsg = '✨ ' + (result.message || prI18n.rules_compiled || 'Compiled');
            showToast(successMsg, 'success');
        } else {
            const failMsg = '⚠️ ' + (result.message || prI18n.rules_compile_failed || 'Failed');
            showToast(failMsg, 'error');
        }
    } catch (err) {
        const connErr = prI18n.conn_error || 'Connection error';
        showToast(connErr, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origBtnHtml;
        }
    }
}

// 5. Projeyi Aktif <-> Geçmiş / Tamamlandı Olarak Değiştir
async function toggleProjectStatus(projectId, newStatus) {
    const prI18n = window.PROJECTS_I18N || {};
    const actionText = (newStatus === 'completed' || newStatus === 'archived') 
        ? (prI18n.archive_confirm || 'Archive this project?') 
        : (prI18n.restore_confirm || 'Restore this project?');

    if (!confirm(actionText)) return;

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('status', newStatus);

    try {
        const res = await fetch(`${projectsBaseUrl}/ajax?action=toggle_project_status`, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            const actFail = data.message || prI18n.action_failed || 'Failed';
            showToast(actFail, 'error');
        }
    } catch (e) {
        console.error(e);
    }
}

// 6. Projeyi Tamamen Sil
async function deleteProjectPermanently(projectId) {
    const prI18n = window.PROJECTS_I18N || {};
    const delConfirm = prI18n.delete_confirm || 'Are you sure you want to delete this project?';
    if (!confirm(delConfirm)) return;

    const formData = new FormData();
    formData.append('project_id', projectId);

    try {
        const res = await fetch(`${projectsBaseUrl}/ajax?action=delete_project`, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.href = projectsBaseUrl + '/tasks', 700);
        } else {
            showToast(data.message || 'Proje silinemedi', 'error');
        }
    } catch (e) {
        console.error(e);
    }
}

// Event Listeners Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Form submits
    const formNew = document.getElementById('formNewProject');
    if (formNew) {
        formNew.addEventListener('submit', handleCreateProject);
    }
    const formEdit = document.getElementById('formEditProject');
    if (formEdit) {
        formEdit.addEventListener('submit', handleUpdateProject);
    }

    // Modal Edit buttons
    const btnTestWebhook = document.getElementById('btnTestWebhookEdit');
    if (btnTestWebhook) {
        btnTestWebhook.addEventListener('click', testCurrentProjectWebhook);
    }
    const btnRefineEdit = document.getElementById('btnRefineRulesEdit');
    if (btnRefineEdit) {
        btnRefineEdit.addEventListener('click', () => refineProjectRules(true));
    }
    const btnSyncEdit = document.getElementById('btnSyncRulesEdit');
    if (btnSyncEdit) {
        btnSyncEdit.addEventListener('click', syncCurrentProjectRules);
    }
    const btnDeployEdit = document.getElementById('btnOpenDeployWizardEdit');
    if (btnDeployEdit) {
        btnDeployEdit.addEventListener('click', () => {
            const pId = document.getElementById('editProjId')?.value;
            if (pId && typeof openDeployWizard === 'function') openDeployWizard(pId);
        });
    }
    const btnMcpEdit = document.getElementById('btnOpenMcpModalEdit');
    if (btnMcpEdit) {
        btnMcpEdit.addEventListener('click', () => {
            const pId = document.getElementById('editProjId')?.value;
            if (pId && typeof openProjectMcpModal === 'function') openProjectMcpModal(pId);
        });
    }

    // Modal New buttons
    const btnRefineNew = document.getElementById('btnRefineRulesNew');
    if (btnRefineNew) {
        btnRefineNew.addEventListener('click', () => refineProjectRules(false));
    }

    // Modal close & overlay click delegation
    document.addEventListener('click', function(e) {
        const closeBtn = e.target.closest('[data-close-modal]');
        if (closeBtn) {
            const modalId = closeBtn.getAttribute('data-close-modal');
            if (modalId && typeof closeModal === 'function') {
                closeModal(modalId);
            }
        } else if (e.target.classList.contains('cu-modal-overlay') && e.target.id) {
            if (typeof closeModal === 'function') {
                closeModal(e.target.id);
            }
        }
    });
});
