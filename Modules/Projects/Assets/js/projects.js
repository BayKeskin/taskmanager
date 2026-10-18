// Modules/Projects/Assets/js/projects.js

async function handleCreateProject(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    try {
        const res = await fetch('/taskmanager/ajax?action=create_project', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            showToast('Proje başarıyla oluşturuldu!', 'success');
            closeModal('modalNewProject');
            setTimeout(() => window.location.reload(), 700);
        } else {
            showToast(result.message || 'Proje oluşturulamadı', 'error');
        }
    } catch (err) {
        showToast('Sunucu hatası', 'error');
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
        fetch('/taskmanager/ajax?action=get_project&id=' + projId)
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
            .catch(err => console.error('Proje detayları yüklenemedi:', err));
    }
}

async function handleUpdateProject(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    try {
        const res = await fetch('/taskmanager/ajax?action=update_project', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            showToast('✅ Proje ayarları kaydedildi!', 'success');
            closeModal('modalEditProject');
            setTimeout(() => window.location.reload(), 700);
        } else {
            showToast(result.message || 'Güncelleme başarısız', 'error');
        }
    } catch (err) {
        showToast('Sunucu hatası', 'error');
    }
}

async function testCurrentProjectWebhook() {
    const projId = document.getElementById('editProjId')?.value;
    if (!projId) return;

    showToast('⏳ Webhook test sinyali gönderiliyor...', 'info');

    try {
        const formData = new FormData();
        formData.append('project_id', projId);

        const res = await fetch('/taskmanager/ajax?action=test_project_webhook', {
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
        showToast('Test bağlantı hatası', 'error');
    }
}

async function syncCurrentProjectRules() {
    const projId = document.getElementById('editProjId')?.value;
    const rules = document.getElementById('editProjAgentRules')?.value;
    if (!projId) return;

    showToast('⏳ Proje kural dosyaları (AGENTS.md) senkronize ediliyor...', 'info');

    try {
        const formData = new FormData();
        formData.append('project_id', projId);
        if (rules !== undefined) {
            formData.append('agent_rules', rules);
        }

        const res = await fetch('/taskmanager/ajax?action=sync_project_rules', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            showToast('✅ ' + (result.message || 'Kural dosyaları güncellendi'), 'success');
        } else {
            showToast('⚠️ ' + (result.message || 'Senkronizasyon başarısız'), 'error');
        }
    } catch (err) {
        showToast('Bağlantı hatası', 'error');
    }
}

async function refineProjectRules(isEdit) {
    const btn = document.getElementById(isEdit ? 'btnRefineRulesEdit' : 'btnRefineRulesNew');
    const textarea = document.getElementById(isEdit ? 'editProjAgentRules' : 'newProjAgentRules');
    const titleInput = isEdit ? document.getElementById('editProjTitle') : document.querySelector('#formNewProject [name="title"]');
    const codeInput = isEdit ? document.getElementById('editProjCode') : document.querySelector('#formNewProject [name="project_code"]');
    const projId = isEdit ? document.getElementById('editProjId')?.value : null;

    if (!textarea) return;

    let raw = textarea.value.trim();
    if (!raw) {
        showToast('Lütfen derlenmesini istediğiniz kural veya kısıtlamaları kısaca yazın (Örn: "css kullanmak yasak, gutenberg projesi")', 'info');
        textarea.focus();
        return;
    }

    const origBtnHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳ AI Derliyor...';
    }

    try {
        const formData = new FormData();
        formData.append('raw_rules', raw);
        if (codeInput?.value) formData.append('project_code', codeInput.value);
        if (titleInput?.value) formData.append('project_title', titleInput.value);
        if (projId) formData.append('project_id', projId);

        const res = await fetch('/taskmanager/ajax?action=refine_project_rules', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success && result.data && result.data.refined_rules) {
            textarea.value = result.data.refined_rules;
            showToast('✨ ' + (result.message || 'Kurallar yapay zeka ile başarıyla derlendi!'), 'success');
        } else {
            showToast('⚠️ ' + (result.message || 'Kurallar derlenemedi'), 'error');
        }
    } catch (err) {
        showToast('Bağlantı hatası oluştu', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origBtnHtml;
        }
    }
}

// 5. Projeyi Aktif <-> Geçmiş / Tamamlandı Olarak Değiştir
async function toggleProjectStatus(projectId, newStatus) {
    const actionText = (newStatus === 'completed' || newStatus === 'archived') 
        ? 'Bu projeyi geçmiş projelere taşımak istediğinize emin misiniz?' 
        : 'Bu projeyi aktif projelere geri yüklemek istediğinize emin misiniz?';

    if (!confirm(actionText)) return;

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('status', newStatus);

    try {
        const res = await fetch('/taskmanager/ajax?action=toggle_project_status', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || 'İşlem başarısız', 'error');
        }
    } catch (e) {
        console.error(e);
    }
}

// 6. Projeyi Tamamen Sil
async function deleteProjectPermanently(projectId) {
    if (!confirm('DİKKAT: Bu projeyi ve içindeki TÜM görevleri ve dökümanları kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz!')) return;

    const formData = new FormData();
    formData.append('project_id', projectId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_project', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.href = '/taskmanager/tasks', 700);
        } else {
            showToast(data.message || 'Proje silinemedi', 'error');
        }
    } catch (e) {
        console.error(e);
    }
}
