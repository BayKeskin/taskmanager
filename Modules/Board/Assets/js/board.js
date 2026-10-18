// Modules/Board/Assets/js/board.js

// 3. GÖREV DETAY ÇEKMECESİ (SLIDE-OVER TASK DRAWER)
// ==========================================================================
function closeTaskDrawer() {
    const drawer = document.getElementById('cuTaskDrawer');
    const overlay = document.getElementById('cuDrawerOverlay');
    if (drawer) drawer.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    currentDrawerTaskId = null;
}

async function openTaskDrawer(taskId) {
    currentDrawerTaskId = taskId;
    const drawer = document.getElementById('cuTaskDrawer');
    const overlay = document.getElementById('cuDrawerOverlay');
    if (!drawer || !overlay) return;

    overlay.classList.add('active');
    drawer.classList.add('active');

    // Drawer Yükleniyor durumu
    const i18n = window.TASK_I18N || {};
    document.getElementById('drawerTaskTitle').innerText = i18n.loading || 'Yükleniyor...';
    document.getElementById('drawerDescription').innerText = i18n.loading || 'Görev detayları getiriliyor...';
    document.getElementById('drawerBtnGroup').innerHTML = `<span style="font-size:12px; color:var(--text-muted);">${i18n.loading || 'Yükleniyor...'}</span>`;
    document.getElementById('drawerTimeline').innerHTML = '';
    document.getElementById('drawerNotesBox').style.display = 'none';
    const devopsBox = document.getElementById('drawerDevopsContainer');
    if (devopsBox) devopsBox.innerHTML = '';
    document.getElementById('drawerCommentsList').innerHTML = `<span style="font-size:12px; color:var(--text-muted);">${i18n.comments_loading || 'Yorumlar yükleniyor...'}</span>`;

    try {
        const response = await fetch(`/taskmanager/ajax?action=get_task_detail&id=${taskId}`);
        const data = await response.json();

        if (!data.success) {
            showToast(data.message || 'Görev detayları alınamadı', 'error');
            closeTaskDrawer();
            return;
        }

        const task = data.task;
        const currentUser = data.currentUser;
        const activities = data.activities || [];
        const modification = data.modification;
        const submission = data.submission;
        const comments = data.comments || [];

        // Meta Alanları & Üst Görev (Parent Task) Geri Dönüş Linki
        const codeEl = document.getElementById('drawerTaskCode');
        if (task.parent_task) {
            codeEl.innerHTML = `<span onclick="openTaskDrawer(${task.parent_task.id})" style="cursor:pointer; color:var(--text-muted); text-decoration:underline; margin-right:6px;" title="Ana Göreve Dön: ${escapeHtml(task.parent_task.title)}">⬆️ ${escapeHtml(task.parent_task.task_code)} /</span> ${escapeHtml(task.task_code)}`;
        } else {
            codeEl.innerText = task.task_code;
        }
        document.getElementById('drawerProjectBadge').innerText = task.project_title || (i18n.drawer_project_fallback || 'Proje');
        document.getElementById('drawerProjectBadge').style.color = task.project_color || '#7B68EE';
        document.getElementById('drawerTaskTitle').innerText = task.title;
        const descEl = document.getElementById('drawerDescription');
        if (descEl) {
            descEl.innerHTML = window.cleanMojibake(task.description) || `<p style="color:var(--text-muted); font-style:italic;">${i18n.desc_empty_hint || 'Görev açıklaması ve talimatları yazmak için buraya tıklayın...'}</p>`;
        }
        const descStatus = document.getElementById('drawerDescSaveStatus');
        if (descStatus) descStatus.innerText = i18n.click_to_edit || 'Düzenlemek için tıklayın';
        const descBtn = document.getElementById('btnSaveDrawerDesc');
        if (descBtn) descBtn.style.display = 'none';

        drawerAllTeamUsers = data.allUsers || (window.ALL_TEAM_USERS || []);
        renderDrawerAssignees(data.assignees || []);
        const drawerAssigneeLegacy = document.getElementById('drawerAssignee');
        if (drawerAssigneeLegacy) drawerAssigneeLegacy.innerText = task.assignee_name || (i18n.unassigned || 'Atanmamış');
        document.getElementById('drawerDeadline').innerText = task.deadline || (i18n.not_specified || 'Belirtilmemiş');

        // Zaman Takibi
        document.getElementById('drawerTimeEstimate').innerText = task.time_estimate || '4h';
        document.getElementById('drawerTimeSpent').innerText = task.time_spent || '1h 30m';

        // Etiketler (Tags & AI Otonom Kontrolü)
        renderDrawerTags(task);

        // Ekler & Görseller (Attachments)
        renderDrawerAttachments(data.attachments || []);

        // Aktif Kullanıcı Avatarı
        const myAvatar = document.getElementById('drawerMyAvatar');
        if (myAvatar && currentUser) {
            myAvatar.innerText = (currentUser.name || 'B').charAt(0);
            myAvatar.style.background = currentUser.avatar_color || '#7B68EE';
        }

        // ======================================================================
        // CHECKLISTS (KONTROL LİSTESİ) RENDER
        // ======================================================================
        renderDrawerChecklists(data.checklists || []);

        // ======================================================================
        // SUBTASKS (ALT GÖREVLER) RENDER
        // ======================================================================
        renderDrawerSubtasks(data.subtasks || []);

        // ======================================================================
        // DEPENDENCIES (GÖREV BAĞIMLILIKLARI) RENDER
        // ======================================================================
        renderDrawerDependencies(data.dependencies || [], data.availableTasks || []);

        // Zamanlayıcıyı sıfırla
        resetTimerState();

        // Durum Rozeti
        const statusMap = {
            'assigned': { text: i18n.status?.assigned || 'ATANDI', color: '#4f8ff7' },
            'accepted': { text: i18n.status?.accepted || 'KABUL EDİLDİ', color: '#00b4d8' },
            'modification_requested': { text: i18n.status?.modification_requested || 'REVİZYON İSTENDİ', color: '#e67e22' },
            'in_progress': { text: i18n.status?.in_progress || 'ÇALIŞILIYOR', color: '#9b5de5' },
            'submitted': { text: i18n.status?.submitted || 'TESLİM EDİLDİ', color: '#e056fd' },
            'under_review': { text: i18n.status?.under_review || 'İNCELENİYOR', color: '#f1c40f' },
            'completed': { text: i18n.status?.completed || 'TAMAMLANDI', color: '#00b894' }
        };
        const st = statusMap[task.status] || { text: task.status, color: '#6e7681' };
        document.getElementById('drawerStatusBadge').innerHTML = `
            <span class="cu-group-pill" style="background:${st.color}; font-size:10px;">${st.text}</span>
        `;

        // Öncelik Rozeti
        const prMap = {
            'urgent': i18n.priority?.urgent || '🔴 Acil',
            'high': i18n.priority?.high || '🟡 Yüksek',
            'normal': i18n.priority?.normal || '🔵 Normal',
            'low': i18n.priority?.low || '⚪ Düşük'
        };
        document.getElementById('drawerPriorityBadge').innerHTML = `
            <span class="cu-priority-badge cu-priority-${task.priority}">${prMap[task.priority] || task.priority}</span>
        `;

        // Revizyon / Teslimat Notları Kutusu
        const notesBox = document.getElementById('drawerNotesBox');
        if (task.status === 'modification_requested' && modification) {
            notesBox.style.display = 'block';
            document.getElementById('drawerNotesTitle').innerText = i18n.notes_revision_title || 'Çalışanın Revizyon / Açıklama Gerekçesi:';
            document.getElementById('drawerNotesTitle').style.color = '#e67e22';
            document.getElementById('drawerNotesContent').innerText = window.cleanMojibake(modification.reason);
            document.getElementById('drawerAttachmentLink').innerHTML = '';
        } else if ((task.status === 'submitted' || task.status === 'completed') && submission) {
            notesBox.style.display = 'block';
            document.getElementById('drawerNotesTitle').innerText = i18n.notes_submission_title || 'Çalışanın Teslim Notu:';
            document.getElementById('drawerNotesTitle').style.color = '#9b5de5';
            document.getElementById('drawerNotesContent').innerText = window.cleanMojibake(submission.notes);
            if (submission.attachment_url) {
                document.getElementById('drawerAttachmentLink').innerHTML = `
                    <a href="${submission.attachment_url}" target="_blank" style="color:var(--brand-primary); font-size:12px; text-decoration:underline;">
                        ${i18n.link_open_submission || '🔗 Teslimat / Çalışma Bağlantısını Aç'}
                    </a>
                `;
            } else {
                document.getElementById('drawerAttachmentLink').innerHTML = '';
            }
        }

        if (submission && (submission.commit_hash || submission.attachment_url)) {
            notesBox.style.display = 'block';
            const devopsBox = document.getElementById('drawerDevopsContainer');
            if (devopsBox) {
                const shortCommit = submission.commit_hash ? submission.commit_hash.substring(0, 7) : '';
                const repoUrl = submission.attachment_url || '';
                const isReverted = parseInt(submission.is_reverted || 0) === 1;
                const dI18n = i18n.devops || {};
                const schemaDiff = submission.schema_diff_summary || (dI18n.no_db_changes || 'Veritabanı şema değişikliği yok (Sadece Kod)');

                devopsBox.innerHTML = `
                    <div style="background:var(--bg-main); border:1px solid var(--border-subtle); border-radius:6px; padding:12px; margin-top:10px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-size:16px;">🐙</span>
                                <strong style="font-size:12px; color:var(--text-white);">${escapeHtml(dI18n.status_title || 'GitHub & DevOps Durumu')}</strong>
                                ${shortCommit ? `<span style="font-size:11px; background:rgba(123,104,238,0.2); color:#a29bfe; padding:2px 6px; border-radius:4px; font-family:monospace;">#${shortCommit}</span>` : ''}
                            </div>
                            ${isReverted ? 
                                `<span style="font-size:11px; color:#ff7675; background:rgba(255,118,117,0.15); padding:2px 8px; border-radius:4px; font-weight:600;">${escapeHtml(dI18n.reverted || '⏪ Geri Alındı (Reverted)')}</span>` : 
                                `<span style="font-size:11px; color:#55efc4; background:rgba(85,239,196,0.15); padding:2px 8px; border-radius:4px; font-weight:600;">${escapeHtml(dI18n.synced || '🚀 Senkronize')}</span>`
                            }
                        </div>
                        
                        <div style="font-size:11.5px; color:var(--text-muted); margin-bottom:10px; line-height:1.4;">
                            <div style="margin-bottom:4px;"><strong>${escapeHtml(dI18n.db_impact || '🗄️ Veritabanı Etkisi:')}</strong> ${escapeHtml(schemaDiff)}</div>
                        </div>

                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            ${repoUrl ? `
                                <a href="${repoUrl}" target="_blank" class="cu-btn-sm" style="display:inline-flex; align-items:center; gap:4px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:var(--text-white); font-size:11px; text-decoration:none; padding:4px 8px; border-radius:4px;">
                                    <span>🔗</span> ${escapeHtml(dI18n.view_diff || "GitHub'da İncele (Diff)")}
                                </a>
                            ` : ''}
                            ${!isReverted && submission.commit_hash ? `
                                <button type="button" class="cu-btn-sm" onclick="rollbackTask(${task.id}, 'safe')" style="display:inline-flex; align-items:center; gap:4px; background:rgba(255,71,87,0.15); border:1px solid rgba(255,71,87,0.4); color:#ff4757; font-size:11px; padding:4px 8px; border-radius:4px; cursor:pointer;" title="${escapeHtml(dI18n.btn_rollback || 'Değişiklikleri Geri Al (Rollback)')}">
                                    <span>⏪</span> ${escapeHtml(dI18n.btn_rollback || 'Değişiklikleri Geri Al (Rollback)')}
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            }
        }

        // AKILLI AKSİYON BUTONLARI (WORKFLOW BUTTONS)
        const btnGroup = document.getElementById('drawerBtnGroup');
        const actionBox = document.getElementById('drawerWorkflowBox');
        btnGroup.innerHTML = '';

        const isAssignee = (currentUser.id == task.assigned_to);
        const isAdmin = (currentUser.role === 'admin');

        let hasActions = false;

        // 1. Kullanıcı Atanmış Görevi Kabul Eder veya Revizyon İster
        if (isAssignee && task.status === 'assigned') {
            hasActions = true;
            btnGroup.innerHTML += `
                <button class="cu-btn-accept" onclick="executeWorkflowAction(${task.id}, 'accept')">${i18n.btn_accept_task || '⚡ Görevi Kabul Et'}</button>
                <button class="cu-btn-modify" onclick="triggerRequestModModal(${task.id})">${i18n.btn_request_mod || '📝 Revizyon / Açıklama İste'}</button>
            `;
        }

        // 2. Kullanıcı Kabul Edilmiş / Çalışılan Görevi Teslim Eder
        if (isAssignee && (task.status === 'accepted' || task.status === 'in_progress')) {
            hasActions = true;
            btnGroup.innerHTML += `
                <button class="cu-btn-submit" onclick="triggerSubmitTaskModal(${task.id})">${i18n.btn_submit_task || '🚀 Görevi Teslim Et (Tamamlandı)'}</button>
            `;
        }

        // 3. Admin Teslimatı İnceler: Onayla veya Düzeltme İste
        if (isAdmin && (task.status === 'submitted' || task.status === 'under_review')) {
            hasActions = true;
            btnGroup.innerHTML += `
                <button class="cu-btn-approve" onclick="executeWorkflowAction(${task.id}, 'approve')">${i18n.btn_approve_task || '✅ Teslimatı Onayla & Tamamla'}</button>
                <button class="cu-btn-modify" onclick="executeWorkflowAction(${task.id}, 'request_changes')">${i18n.btn_request_changes || '↩️ Düzeltme / Revize İste'}</button>
            `;
        }

        // 4. Admin Revizyon Talebini Yanıtlar
        if (isAdmin && task.status === 'modification_requested') {
            hasActions = true;
            btnGroup.innerHTML += `
                <button class="cu-btn-accept" onclick="executeWorkflowAction(${task.id}, 'resolve_modification')">${i18n.btn_resolve_mod || '✔️ Revizyonu Kabul Et & Yeniden Ata'}</button>
            `;
        }

        if (!hasActions) {
            actionBox.style.display = 'none';
        } else {
            actionBox.style.display = 'block';
        }

        // ======================================================================
        // TaskManager YORUMLAR (COMMENTS & DISCUSSION)
        // ======================================================================
        renderDrawerComments(comments);

        // ======================================================================
        // ZAMAN ÇİZELGESİ (TIMELINE / AUDIT STREAM)
        // ======================================================================
        renderDrawerTimeline(activities);

    } catch (err) {
        showToast('Bağlantı hatası oluştu', 'error');
        console.error(err);
    }
}

// 4. Yorum Gönderme ve Ek Dosya İşleyicisi
let selectedCommentFile = null;

function handleCommentDropzoneOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const c = document.getElementById('commentDropzoneContainer');
    if (c) c.style.outline = '2px dashed var(--brand-primary)';
}

function handleCommentDropzoneLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const c = document.getElementById('commentDropzoneContainer');
    if (c) c.style.outline = 'none';
}

function handleCommentDropzoneDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    handleCommentDropzoneLeave(e);
    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
        setCommentFile(files[0]);
    }
}

function handleCommentFileSelect(input) {
    if (input.files && input.files.length > 0) {
        setCommentFile(input.files[0]);
    }
}

function setCommentFile(file) {
    selectedCommentFile = file;
    const preview = document.getElementById('commentFilePreview');
    const nameElem = document.getElementById('commentPreviewName');
    const sizeElem = document.getElementById('commentPreviewSize');
    const thumbElem = document.getElementById('commentPreviewThumb');
    const iconElem = document.getElementById('commentPreviewIcon');

    if (preview) preview.style.display = 'flex';
    if (nameElem) nameElem.innerText = file.name;
    if (sizeElem) sizeElem.innerText = Math.round(file.size / 1024) + ' KB';

    if (file.type.startsWith('image/')) {
        if (iconElem) iconElem.style.display = 'none';
        if (thumbElem) {
            thumbElem.style.display = 'block';
            thumbElem.style.backgroundImage = `url(${URL.createObjectURL(file)})`;
        }
    } else {
        if (iconElem) iconElem.style.display = 'inline';
        if (thumbElem) thumbElem.style.display = 'none';
    }
}

function clearCommentFile() {
    selectedCommentFile = null;
    const preview = document.getElementById('commentFilePreview');
    const input = document.getElementById('commentFileInput');
    if (preview) preview.style.display = 'none';
    if (input) input.value = '';
}

async function handleAddComment(e) {
    e.preventDefault();
    if (!currentDrawerTaskId) return;

    const input = document.getElementById('drawerCommentInput');
    const commentText = input.value.trim();
    if (!commentText && !selectedCommentFile) return;

    const formData = new FormData();
    formData.append('task_id', currentDrawerTaskId);
    formData.append('comment', commentText);
    if (selectedCommentFile) {
        formData.append('attachment', selectedCommentFile);
    }

    try {
        const res = await fetch('/taskmanager/ajax?action=add_comment', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();

        if (result.success) {
            showToast('Yorumunuz başarıyla iletildi!', 'success');
            input.value = '';
            clearCommentFile();
            // Çekmecedeki yorumları ve ekleri anında canlı tazele (ekranı veya çekmeceyi sıfırlamadan)
            await refreshDrawerCommentsOnly(currentDrawerTaskId);
        } else {
            showToast(result.message || 'Yorum kaydedilemedi', 'error');
        }
    } catch (err) {
        showToast('Sunucu bağlantı hatası', 'error');
    }
}

async function refreshDrawerCommentsOnly(taskId) {
    if (!taskId) return;
    try {
        const response = await fetch(`/taskmanager/ajax?action=get_task_detail&id=${taskId}`);
        const data = await response.json();
        if (data.success) {
            if (data.comments) {
                renderDrawerComments(data.comments);
            }
            if (data.attachments) {
                renderDrawerAttachments(data.attachments);
            }
            if (data.activities) {
                renderDrawerTimeline(data.activities);
            }
        }
    } catch (err) {
        console.error('refreshDrawerCommentsOnly error:', err);
    }
}

// ==========================================================================
// 5. KANBAN SÜRÜKLE & BIRAK (HTML5 DRAG & DROP)
// ==========================================================================
function handleDragStart(e, taskId) {
    draggedTaskId = taskId;
    e.dataTransfer.setData('text/plain', taskId);
    e.dataTransfer.effectAllowed = 'move';
    setTimeout(() => {
        const card = document.querySelector(`.cu-board-card[data-task-id="${taskId}"]`);
        if (card) card.classList.add('dragging');
    }, 0);
}

function handleDragEnd(e) {
    draggedTaskId = null;
    document.querySelectorAll('.cu-board-card').forEach(c => c.classList.remove('dragging'));
    document.querySelectorAll('.cu-board-cards').forEach(col => col.classList.remove('drag-over'));
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const col = e.currentTarget;
    if (!col.classList.contains('drag-over')) {
        col.classList.add('drag-over');
    }
}

function handleDragLeave(e) {
    e.currentTarget.classList.remove('drag-over');
}

async function handleDrop(e, targetStatus) {
    e.preventDefault();
    const targetCol = e.currentTarget;
    targetCol.classList.remove('drag-over');

    const taskId = e.dataTransfer.getData('text/plain') || draggedTaskId;
    if (!taskId) return;

    const card = document.querySelector(`.cu-board-card[data-task-id="${taskId}"]`);
    if (!card) return;

    // Kartı anında yeni sütuna taşı (Optimistic UI)
    targetCol.appendChild(card);
    updateBoardBadges();

    // Backend AJAX Güncellemesi
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('status', targetStatus);

    try {
        const res = await fetch('/taskmanager/ajax?action=update_task_status', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();

        if (result.success) {
            showToast(`Görev '${targetStatus.toUpperCase()}' sütununa taşındı!`, 'success');
        } else {
            showToast(result.message || 'Durum güncellenemedi', 'error');
            setTimeout(() => window.location.reload(), 1000);
        }
    } catch (err) {
        showToast('Bağlantı hatası', 'error');
        setTimeout(() => window.location.reload(), 1000);
    }
}

function updateBoardBadges() {
    document.querySelectorAll('.cu-board-col').forEach(col => {
        const badge = col.querySelector('.cu-nav-badge');
        const cards = col.querySelectorAll('.cu-board-card');
        if (badge) badge.innerText = cards.length;
    });
}

// ==========================================================================
// 6. İŞ AKIŞI EYLEMLERİ (WORKFLOW AJAX)
// ==========================================================================
async function executeWorkflowAction(taskId, action, extraData = {}) {
    try {
        const formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('workflow_action', action);
        for (const key in extraData) {
            formData.append(key, extraData[key]);
        }

        const res = await fetch('/taskmanager/ajax?action=workflow_action', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();

        if (result.success) {
            showToast(result.message || 'İşlem başarıyla gerçekleştirildi', 'success');
            setTimeout(() => window.location.reload(), 700);
        } else {
            showToast(result.message || 'İşlem başarısız', 'error');
        }
    } catch (e) {
        showToast('İstek gönderilirken hata oluştu', 'error');
    }
}

function triggerRequestModModal(taskId) {
    document.getElementById('modTaskId').value = taskId;
    openModal('modalRequestMod');
}

function triggerSubmitTaskModal(taskId) {
    document.getElementById('submitTaskId').value = taskId;
    openModal('modalSubmitTask');
}

// 🪄 AI GÖREV MİMARI (TASK ARCHITECT LOGIC)
let aiArchitectFiles = [];

function toggleAiArchitectPanel() {
    const body = document.getElementById('aiArchitectPanelBody');
    const icon = document.getElementById('aiArchitectToggleIcon');
    if (!body) return;
    const isHidden = body.style.display === 'none' || body.style.display === '';
    body.style.display = isHidden ? 'block' : 'none';
    if (icon) icon.innerText = isHidden ? '▲' : '▼';
    if (isHidden) {
        setTimeout(() => {
            const txt = document.getElementById('aiArchitectInstruction');
            if (txt) txt.focus();
        }, 50);
    }
}

function handleAiArchitectFiles(files) {
    if (!files || files.length === 0) return;
    for (let i = 0; i < files.length; i++) {
        aiArchitectFiles.push(files[i]);
    }
    renderAiArchitectFiles();
    const fileInput = document.getElementById('aiArchitectFileInput');
    if (fileInput) fileInput.value = '';

    // Eklenen dosyaları/görselleri otomatik olarak görevin ana eklerine de dahil et
    if (typeof handleModalNewTaskFiles === 'function') {
        handleModalNewTaskFiles(files);
    }
}

function removeAiArchitectFile(index) {
    if (index >= 0 && index < aiArchitectFiles.length) {
        aiArchitectFiles.splice(index, 1);
        renderAiArchitectFiles();
    }
}

function renderAiArchitectFiles() {
    const previewContainer = document.getElementById('aiArchitectFilesPreview');
    if (!previewContainer) return;
    previewContainer.innerHTML = '';

    aiArchitectFiles.forEach((file, idx) => {
        const item = document.createElement('div');
        item.style.cssText = 'position:relative; display:flex; align-items:center; gap:5px; background:var(--bg-card); border:1px solid rgba(123, 104, 238, 0.3); border-radius:var(--radius-sm); padding:3px 8px; font-size:11px; max-width:200px;';

        const isImage = file.type.startsWith('image/');
        const isCode = /\.(php|js|blade\.php|sql|html|css|json|txt|py)$/i.test(file.name);
        const iconSpan = document.createElement('span');
        iconSpan.innerText = isImage ? '🖼️' : (isCode ? '💻' : '📄');

        const nameSpan = document.createElement('span');
        nameSpan.style.cssText = 'overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; color:var(--text-white); font-weight:500; font-size:10.5px;';
        nameSpan.innerText = file.name;
        nameSpan.title = `${file.name} (${Math.round(file.size / 1024)} KB)`;

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.innerHTML = '✕';
        delBtn.style.cssText = 'background:none; border:none; color:#ff4757; cursor:pointer; font-size:11px; font-weight:700; padding:0 2px;';
        delBtn.title = 'Kaldır';
        delBtn.onclick = (e) => {
            e.stopPropagation();
            removeAiArchitectFile(idx);
        };

        item.appendChild(iconSpan);
        item.appendChild(nameSpan);
        item.appendChild(delBtn);
        previewContainer.appendChild(item);
    });
}

function handleAiArchitectDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('aiArchitectDropzone');
    if (dz) {
        dz.style.borderColor = 'var(--brand-primary)';
        dz.style.background = 'rgba(123, 104, 238, 0.15)';
    }
}

function handleAiArchitectDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('aiArchitectDropzone');
    if (dz) {
        dz.style.borderColor = 'rgba(123, 104, 238, 0.35)';
        dz.style.background = 'rgba(0,0,0,0.1)';
    }
}

function handleAiArchitectDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    handleAiArchitectDragLeave(e);
    if (e.dataTransfer && e.dataTransfer.files) {
        handleAiArchitectFiles(e.dataTransfer.files);
    }
}

async function runAiArchitect() {
    const instEl = document.getElementById('aiArchitectInstruction');
    const instruction = instEl ? instEl.value.trim() : '';
    if (!instruction) {
        showToast('Lütfen ne yaptırmak istediğinize dair kısa bir not yazın.', 'warning');
        if (instEl) instEl.focus();
        return;
    }

    const projSelect = document.getElementById('modalNewTaskProjectSelect') || document.querySelector('#modalNewTask select[name="project_id"]');
    const projectId = projSelect ? projSelect.value : 1;

    const btn = document.getElementById('btnRunAiArchitect');
    const btnText = document.getElementById('btnRunAiArchitectText');
    const origText = btnText ? btnText.innerText : 'Şartname Üret';

    if (btn) btn.disabled = true;
    if (btnText) btnText.innerText = 'Mimar analiz ediyor...';

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('instruction', instruction);

    // Eklenen referans görseller ve kod dosyalarını ayırarak ekle
    aiArchitectFiles.forEach(file => {
        if (file.type.startsWith('image/')) {
            formData.append('images[]', file);
        } else {
            formData.append('code_files[]', file);
        }
    });

    try {
        const res = await fetch('/taskmanager/ajax?action=refine_task_specification', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();

        if (d.success && d.data && d.data.specification) {
            const descArea = document.getElementById('modalNewTaskDescription');
            if (descArea) {
                descArea.value = d.data.specification;
                descArea.style.borderColor = 'var(--brand-primary)';
                descArea.style.boxShadow = '0 0 0 3px rgba(123, 104, 238, 0.2)';
                setTimeout(() => {
                    descArea.style.borderColor = '';
                    descArea.style.boxShadow = '';
                }, 2000);
            }

            // Görev başlığı henüz boşsa veya varsayılansa, kısa nottan anlamlı bir başlık ata
            const titleInput = document.getElementById('modalNewTaskTitleInput');
            if (titleInput && (!titleInput.value.trim() || titleInput.value.length < 5)) {
                let cleanTitle = instruction.split('\n')[0].replace(/[.,;:].*$/, '').trim();
                if (cleanTitle.length > 60) cleanTitle = cleanTitle.substring(0, 57) + '...';
                titleInput.value = cleanTitle;
            }

            // Ayrıca yüklenen resimler varsa, doğrudan görevin eklerine de otomatik kopyala
            if (aiArchitectFiles.length > 0) {
                const imgFiles = aiArchitectFiles.filter(f => f.type.startsWith('image/'));
                if (imgFiles.length > 0) {
                    handleModalNewTaskFiles(imgFiles);
                }
            }

            showToast('✨ Teknik mimari şartname başarıyla üretildi!', 'success');
        } else {
            showToast(d.message || 'Şartname üretilemedi.', 'error');
        }
    } catch(err) {
        showToast('Bağlantı hatası oluştu.', 'error');
    } finally {
        if (btn) btn.disabled = false;
        if (btnText) btnText.innerText = origText;
    }
}

// Modal Yeni Görev Dosya & Ek Yönetimi
let modalNewTaskFiles = [];

function handleModalNewTaskFiles(files) {
    if (!files || files.length === 0) return;
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        // Aynı isim ve boyuttaki dosyaların mükerrer eklenmesini önle
        const alreadyExists = modalNewTaskFiles.some(f => f.name === file.name && f.size === file.size);
        if (!alreadyExists) {
            modalNewTaskFiles.push(file);
        }
    }
    renderModalNewTaskFiles();
    const fileInput = document.getElementById('modalNewTaskFileInput');
    if (fileInput) fileInput.value = '';
}

function removeModalNewTaskFile(index) {
    if (index >= 0 && index < modalNewTaskFiles.length) {
        modalNewTaskFiles.splice(index, 1);
        renderModalNewTaskFiles();
    }
}

function renderModalNewTaskFiles() {
    const previewContainer = document.getElementById('modalNewTaskFilesPreview');
    if (!previewContainer) return;
    previewContainer.innerHTML = '';

    modalNewTaskFiles.forEach((file, idx) => {
        const item = document.createElement('div');
        item.style.cssText = 'position:relative; display:flex; align-items:center; gap:6px; background:var(--bg-input); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); padding:4px 8px; font-size:11px; max-width:180px;';

        const isImage = file.type.startsWith('image/');
        const iconSpan = document.createElement('span');
        iconSpan.innerText = isImage ? '🖼️' : '📄';

        const nameSpan = document.createElement('span');
        nameSpan.style.cssText = 'overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; color:var(--text-white); font-weight:500;';
        nameSpan.innerText = file.name;
        nameSpan.title = `${file.name} (${Math.round(file.size / 1024)} KB)`;

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.innerHTML = '✕';
        delBtn.style.cssText = 'background:none; border:none; color:#ff4757; cursor:pointer; font-size:11px; font-weight:700; padding:0 2px;';
        delBtn.title = 'Kaldır';
        delBtn.onclick = (e) => {
            e.stopPropagation();
            removeModalNewTaskFile(idx);
        };

        item.appendChild(iconSpan);
        item.appendChild(nameSpan);
        item.appendChild(delBtn);
        previewContainer.appendChild(item);
    });
}

function handleModalNewTaskDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('modalNewTaskDropzone');
    if (dz) {
        dz.style.borderColor = 'var(--brand-primary)';
        dz.style.background = 'rgba(123, 104, 238, 0.08)';
    }
}

function handleModalNewTaskDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('modalNewTaskDropzone');
    if (dz) {
        dz.style.borderColor = 'var(--border-subtle)';
        dz.style.background = 'rgba(255,255,255,0.02)';
    }
}

function handleModalNewTaskDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    handleModalNewTaskDragLeave(e);
    if (e.dataTransfer && e.dataTransfer.files) {
        handleModalNewTaskFiles(e.dataTransfer.files);
    }
}

// Form Submit Handlers
async function handleCreateTask(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    // Seçilen veya panodan yapıştırılan tüm dosyaları formData'ya ekle
    const allFilesToUpload = [...modalNewTaskFiles];
    if (typeof aiArchitectFiles !== 'undefined' && aiArchitectFiles.length > 0) {
        aiArchitectFiles.forEach(f => {
            if (!allFilesToUpload.some(existing => existing.name === f.name && existing.size === f.size)) {
                allFilesToUpload.push(f);
            }
        });
    }

    if (allFilesToUpload.length > 0) {
        allFilesToUpload.forEach(file => {
            formData.append('attachments[]', file);
        });
    }

    try {
        const res = await fetch('/taskmanager/ajax?action=create_task', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            showToast('Görev başarıyla oluşturuldu!', 'success');
            modalNewTaskFiles = [];
            renderModalNewTaskFiles();
            closeModal('modalNewTask');
            setTimeout(() => window.location.reload(), 700);
        } else {
            showToast(result.message || 'Görev oluşturulamadı', 'error');
        }
    } catch (err) {
        showToast('Sunucu hatası', 'error');
    }
}

async function handleRequestMod(e) {
    e.preventDefault();
    const form = e.target;
    const taskId = document.getElementById('modTaskId').value;
    const reason = form.querySelector('[name="reason"]').value;

    await executeWorkflowAction(taskId, 'request_modification', { reason });
    closeModal('modalRequestMod');
}

let selectedSubmitTaskFile = null;

function handleSubmitDropzoneOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('submitTaskDropzone');
    if (dz) {
        dz.style.borderColor = 'var(--brand-primary)';
        dz.style.background = 'rgba(123, 104, 238, 0.08)';
    }
}

function handleSubmitDropzoneLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('submitTaskDropzone');
    if (dz) {
        dz.style.borderColor = 'var(--border-subtle)';
        dz.style.background = 'rgba(255,255,255,0.02)';
    }
}

function handleSubmitDropzoneDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    handleSubmitDropzoneLeave(e);
    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
        setSubmitTaskFile(files[0]);
    }
}

function handleSubmitFileSelect(input) {
    if (input.files && input.files.length > 0) {
        setSubmitTaskFile(input.files[0]);
    }
}

function setSubmitTaskFile(file) {
    selectedSubmitTaskFile = file;
    const prompt = document.getElementById('submitTaskUploadPrompt');
    const preview = document.getElementById('submitTaskFilePreview');
    const nameElem = document.getElementById('submitTaskPreviewName');
    const sizeElem = document.getElementById('submitTaskPreviewSize');
    const thumbElem = document.getElementById('submitTaskPreviewThumb');

    if (prompt) prompt.style.display = 'none';
    if (preview) preview.style.display = 'flex';
    if (nameElem) nameElem.innerText = file.name;
    if (sizeElem) sizeElem.innerText = Math.round(file.size / 1024) + ' KB';

    if (thumbElem) {
        thumbElem.innerHTML = '';
        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.style.cssText = 'width:36px; height:36px; border-radius:6px; object-fit:cover;';
            thumbElem.appendChild(img);
        } else {
            thumbElem.innerHTML = '<span style="font-size:20px;">📄</span>';
        }
    }
}

function clearSubmitTaskFile(e) {
    if (e) e.stopPropagation();
    selectedSubmitTaskFile = null;
    const prompt = document.getElementById('submitTaskUploadPrompt');
    const preview = document.getElementById('submitTaskFilePreview');
    const input = document.getElementById('submitTaskFileInput');
    if (prompt) prompt.style.display = 'block';
    if (preview) preview.style.display = 'none';
    if (input) input.value = '';
}

async function handleSubmitTask(e) {
    e.preventDefault();
    const form = e.target;
    const taskId = document.getElementById('submitTaskId').value;
    const notes = form.querySelector('[name="notes"]').value;
    const attachment_url = form.querySelector('[name="attachment_url"]').value;

    const extra = { notes, attachment_url };
    if (selectedSubmitTaskFile) {
        extra.attachment = selectedSubmitTaskFile;
    }

    await executeWorkflowAction(taskId, 'submit_task', extra);
    clearSubmitTaskFile();
    closeModal('modalSubmitTask');
}

// 7. Kullanıcı Açılır Menüsü (User Dropdown)
function toggleUserDropdown(e) {
    if (e) e.stopPropagation();
    const dropdown = document.getElementById('userDropdown');
    if (!dropdown) return;
    const isShown = (dropdown.style.display === 'block');
    dropdown.style.display = isShown ? 'none' : 'block';
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifDropdown) notifDropdown.style.display = 'none';
}

document.addEventListener('click', (e) => {
    const userDropdown = document.getElementById('userDropdown');
    if (userDropdown && !e.target.closest('#userProfileBtn') && !e.target.closest('#userDropdown')) {
        userDropdown.style.display = 'none';
    }
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifDropdown && !e.target.closest('#notifDropdown') && !e.target.closest('button[onclick="toggleNotificationsDropdown()"]')) {
        notifDropdown.style.display = 'none';
    }
    const modalDropdown = document.getElementById('modalAssigneeDropdown');
    if (modalDropdown && !e.target.closest('#modalAssigneeDropdown') && !e.target.closest('#modalAssigneeInput')) {
        modalDropdown.style.display = 'none';
    }
    const drawerDropdown = document.getElementById('drawerAssigneeDropdown');
    if (drawerDropdown && !e.target.closest('#drawerAssigneeDropdown') && !e.target.closest('#drawerAssigneeInput') && !e.target.closest('button[onclick="focusDrawerAssigneeInput()"]')) {
        drawerDropdown.style.display = 'none';
    }
});


// ==========================================================================
// 8. GLOBAL EVENT LISTENERS (ARAMA & KISAYOLLAR)
// ==========================================================================
document.addEventListener('DOMContentLoaded', () => {
    // Modal Çoklu Çalışan Seçimini Başlat
    if (typeof initModalAssignees === 'function') {
        initModalAssignees();
    }

    // Arama
    const searchInput = document.getElementById('cuGlobalSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.cu-task-row');
            const cards = document.querySelectorAll('.cu-board-card');

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });

            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                card.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // Ctrl+Enter ile Yorum Gönderme
    const commentInput = document.getElementById('drawerCommentInput');
    if (commentInput) {
        commentInput.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                const form = document.getElementById('formAddComment');
                if (form) form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });
    }
});

// ==========================================================================
// 9. CHECKLIST İŞLEVLERİ (TaskManager CHECKLIST ENGINE)
// ==========================================================================
let currentChecklistData = [];

function renderDrawerChecklists(checklists) {
    currentChecklistData = checklists;
    const container = document.getElementById('drawerChecklistItems');
    const progressText = document.getElementById('drawerChecklistProgressText');
    const progressBar = document.getElementById('drawerChecklistProgressBar');
    if (!container) return;

    container.innerHTML = '';
    const total = checklists.length;
    const completed = checklists.filter(c => c.is_completed == 1).length;
    const pct = total > 0 ? Math.round((completed / total) * 100) : 0;

    progressText.innerText = `${completed} / ${total} (%${pct})`;
    progressBar.style.width = `${pct}%`;

    if (total === 0) {
        container.innerHTML = '<span style="font-size:12px; color:var(--text-muted); font-style:italic;">Henüz kontrol maddesi eklenmemiş.</span>';
        return;
    }

    checklists.forEach(item => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; align-items:center; gap:8px; padding:6px 8px; border-radius:4px; background:var(--bg-app); border:1px solid var(--border-subtle); font-size:12.5px;';
        
        const isDone = item.is_completed == 1;
        row.innerHTML = `
            <input type="checkbox" ${isDone ? 'checked' : ''} onchange="toggleChecklistItem(${item.id}, this.checked)" style="cursor:pointer; accent-color:var(--brand-primary); width:15px; height:15px;">
            <span style="flex:1; color:${isDone ? 'var(--text-muted)' : 'var(--text-primary)'}; text-decoration:${isDone ? 'line-through' : 'none'}; transition:var(--transition);">
                ${item.title}
            </span>
            <button type="button" onclick="handleDeleteChecklistItem(${item.id})" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px;" title="Maddeyi Sil">
                ✕
            </button>
        `;
        container.appendChild(row);
    });
}

async function toggleChecklistItem(itemId, isChecked) {
    const newStatus = isChecked ? 1 : 0;
    
    // Yerel veriyi güncelle ve progress bar'ı anında tazele
    const target = currentChecklistData.find(c => c.id == itemId);
    if (target) target.is_completed = newStatus;
    renderDrawerChecklists(currentChecklistData);

    const formData = new FormData();
    formData.append('item_id', itemId);
    formData.append('is_completed', newStatus);

    try {
        await fetch('/taskmanager/ajax?action=toggle_checklist', {
            method: 'POST',
            body: formData
        });
        showToast(isChecked ? 'Madde tamamlandı!' : 'Madde geri alındı', 'info');
    } catch (err) {
        showToast('Bağlantı hatası', 'error');
    }
}

async function handleAddChecklistItem(e) {
    e.preventDefault();
    if (!currentDrawerTaskId) return;

    const input = document.getElementById('inputNewChecklistTitle');
    const title = input.value.trim();
    if (!title) return;

    const formData = new FormData();
    formData.append('task_id', currentDrawerTaskId);
    formData.append('title', title);

    try {
        const res = await fetch('/taskmanager/ajax?action=add_checklist_item', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            input.value = '';
            showToast('Kontrol maddesi eklendi!', 'success');
            // Çekmeceyi tazele
            openTaskDrawer(currentDrawerTaskId);
        } else {
            showToast(result.message || 'Eklenemedi', 'error');
        }
    } catch (err) {
        showToast('Sunucu hatası', 'error');
    }
}

// ==========================================================================
// 10. INLINE EDITING (TaskManager SATIR İÇİ BAŞLIK DÜZENLEME)
// ==========================================================================
async function handleInlineTitleSave(el) {
    if (!currentDrawerTaskId) return;
    const newTitle = el.innerText.trim();
    if (!newTitle) return;

    const formData = new FormData();
    formData.append('task_id', currentDrawerTaskId);
    formData.append('field', 'title');
    formData.append('value', newTitle);

    try {
        const res = await fetch('/taskmanager/ajax?action=update_task_field', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            showToast('Görev başlığı güncellendi!', 'success');
            // Sayfadaki liste satırını veya kartı anında canlı güncelle
            const card = document.querySelector(`.cu-board-card[data-task-id="${currentDrawerTaskId}"] .cu-board-card-title`);
            if (card) card.innerText = newTitle;
            const rowTitle = document.querySelector(`.cu-task-row[onclick*="${currentDrawerTaskId}"] .cu-task-title`);
            if (rowTitle) {
                // Proje rozetini koru
                const badge = rowTitle.querySelector('span');
                rowTitle.innerHTML = '';
                if (badge) rowTitle.appendChild(badge);
                rowTitle.appendChild(document.createTextNode(' ' + newTitle));
            }
        }
    } catch (err) {
        console.error(err);
    }
}

// ==========================================================================
// 11. SUBTASKS (TaskManager ALT GÖREV MOTORU)
// ==========================================================================
function renderDrawerSubtasks(subtasks) {
    const container = document.getElementById('drawerSubtaskList');
    const countBadge = document.getElementById('drawerSubtaskCount');
    if (!container) return;

    countBadge.innerText = subtasks.length;
    container.innerHTML = '';

    if (subtasks.length === 0) {
        container.innerHTML = '<span style="font-size:12px; color:var(--text-muted); font-style:italic;">Henüz alt görev oluşturulmamış.</span>';
        return;
    }

    subtasks.forEach(s => {
        const item = document.createElement('div');
        item.style.cssText = 'display:flex; align-items:center; justify-content:space-between; padding:8px 10px; background:var(--bg-app); border:1px solid var(--border-subtle); border-radius:4px; font-size:12px; cursor:pointer; transition:var(--transition);';
        item.onclick = (e) => {
            e.stopPropagation();
            openTaskDrawer(s.id);
        };

        const statusColors = {
            'assigned': '#4f8ff7',
            'in_progress': '#9b5de5',
            'submitted': '#e056fd',
            'completed': '#00b894'
        };
        const sColor = statusColors[s.status] || '#6e7681';
        const isDone = (s.status === 'completed');

        item.innerHTML = `
            <div style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" ${isDone ? 'checked' : ''} onclick="event.stopPropagation(); toggleSubtaskDone(${s.id}, this.checked)" style="cursor:pointer; accent-color:var(--brand-primary); width:14px; height:14px;" title="Tamamlandı olarak işaretle">
                <span style="font-weight:700; color:var(--brand-primary); font-size:11px;">${s.task_code}</span>
                <span style="color:var(--text-primary); font-weight:500; ${isDone ? 'text-decoration:line-through; opacity:0.6;' : ''}">${escapeHtml(s.title)}</span>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:9.5px; font-weight:700; text-transform:uppercase; padding:2px 6px; border-radius:3px; background:${sColor}; color:#fff;">${s.status}</span>
                <div class="cu-avatar" style="width:20px; height:20px; font-size:9px; background:${s.assignee_color || '#7B68EE'};">
                    ${(s.assignee_name || 'U').charAt(0)}
                </div>
                <button type="button" onclick="event.stopPropagation(); deleteSubtask(${s.id})" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px 4px;" title="Alt Görevi Sil">
                    ✕
                </button>
            </div>
        `;
        container.appendChild(item);
    });
}

async function toggleSubtaskDone(subtaskId, isCompleted) {
    try {
        const formData = new FormData();
        formData.append('task_id', subtaskId);
        formData.append('status', isCompleted ? 'completed' : 'assigned');
        const res = await fetch('/taskmanager/ajax?action=update_task_status', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && currentDrawerTaskId) {
            openTaskDrawer(currentDrawerTaskId);
            if (typeof loadTasks === 'function') loadTasks();
        }
    } catch(err) {
        console.error('toggleSubtaskDone error:', err);
    }
}

async function handleAddSubtask(e) {
    e.preventDefault();
    if (!currentDrawerTaskId) return;

    const input = document.getElementById('inputNewSubtaskTitle');
    const title = input.value.trim();
    if (!title) return;

    const formData = new FormData();
    formData.append('parent_task_id', currentDrawerTaskId);
    formData.append('title', title);

    try {
        const res = await fetch('/taskmanager/ajax?action=create_subtask', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            input.value = '';
            showToast('Alt görev başarıyla oluşturuldu!', 'success');
            // Çekmeceyi tazele
            openTaskDrawer(currentDrawerTaskId);
        } else {
            showToast(result.message || 'Alt görev oluşturulamadı', 'error');
        }
    } catch (err) {
        showToast('Sunucu hatası', 'error');
    }
}

// ==========================================================================
// 12. COMMAND PALETTE (CTRL + K UNIVERSAL SEARCH & ACTIONS)
// ==========================================================================
function openCommandPalette() {
    const modal = document.getElementById('modalCommandPalette');
    if (!modal) return;
    modal.classList.add('active');
    const input = document.getElementById('inputCommandPalette');
    if (input) {
        input.value = '';
        setTimeout(() => input.focus(), 50);
    }
    filterCommandPalette('');
}

function closeCommandPalette() {
    const modal = document.getElementById('modalCommandPalette');
    if (modal) modal.classList.remove('active');
}

function filterCommandPalette(query) {
    const q = query.toLowerCase().trim();
    const items = document.querySelectorAll('.cu-command-item');
    items.forEach(item => {
        const text = item.innerText.toLowerCase();
        item.style.display = text.includes(q) ? 'flex' : 'none';
    });
}

// Global Ctrl+K ve Esc Dinleyici
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        openCommandPalette();
    }
    if (e.key === 'Escape') {
        closeCommandPalette();
        closeTaskDrawer();
    }
});

// ==========================================================================
// 13. NOTIFICATIONS HUB (BİLDİRİM ZİLİ & LİSTESİ)
// ==========================================================================
let notifDropdownOpen = false;

function toggleNotificationsDropdown() {
    const dropdown = document.getElementById('notifDropdown');
    if (!dropdown) return;

    notifDropdownOpen = !notifDropdownOpen;
    dropdown.style.display = notifDropdownOpen ? 'block' : 'none';

    if (notifDropdownOpen) {
        loadNotifications();
    }
}

async function loadNotifications() {
    const list = document.getElementById('notifList');
    const badge = document.getElementById('notifBadge');
    if (!list) return;

    try {
        const res = await fetch('/taskmanager/ajax?action=get_notifications');
        const data = await res.json();
        const notifs = data.notifications || [];

        const unreadCount = notifs.filter(n => n.is_read == 0).length;
        if (badge) badge.style.display = unreadCount > 0 ? 'block' : 'none';

        list.innerHTML = '';
        if (notifs.length === 0) {
            list.innerHTML = '<div style="padding:16px; text-align:center; font-size:12px; color:var(--text-muted);">Yeni bildirim bulunmuyor.</div>';
            return;
        }

        notifs.forEach(n => {
            const item = document.createElement('div');
            item.style.cssText = `padding:10px 14px; border-bottom:1px solid var(--border-subtle); cursor:pointer; background:${n.is_read == 0 ? 'rgba(123,104,238,0.06)' : 'transparent'}; transition:var(--transition);`;
            item.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                    <span style="font-weight:700; font-size:12px; color:${n.is_read == 0 ? 'var(--brand-primary)' : 'var(--text-white)'};">${n.title}</span>
                    <span style="font-size:10px; color:var(--text-muted);">${n.created_at}</span>
                </div>
                <div style="font-size:11.5px; color:var(--text-secondary); line-height:1.4;">${n.message}</div>
            `;
            item.onclick = () => {
                markNotifRead(n.id);
                if (n.link && !isNaN(n.link)) {
                    openTaskDrawer(parseInt(n.link));
                }
                toggleNotificationsDropdown();
            };
            list.appendChild(item);
        });
    } catch (err) {
        console.error(err);
    }
}

async function markNotifRead(notifId) {
    const formData = new FormData();
    formData.append('notification_id', notifId);
    try {
        await fetch('/taskmanager/ajax?action=mark_notification_read', {
            method: 'POST',
            body: formData
        });
    } catch (e) {}
}

// Sayfa açıldığında bildirim durumunu kontrol et
document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();
});

// ==========================================================================
// 14. TaskManager CANLI SÜRE TAKİBİ (LIVE TIME TRACKING ENGINE)
// ==========================================================================
let taskTimerInterval = null;
let taskTimerSeconds = 0;
let isTimerRunning = false;

function resetTimerState() {
    if (taskTimerInterval) {
        clearInterval(taskTimerInterval);
        taskTimerInterval = null;
    }
    taskTimerSeconds = 0;
    isTimerRunning = false;
    updateTimerDisplay();
    const btn = document.getElementById('btnToggleTimer');
    const icon = document.getElementById('btnTimerIcon');
    const text = document.getElementById('btnTimerText');
    if (btn) btn.style.background = 'var(--brand-primary)';
    if (icon) icon.innerText = '▶';
    if (text) text.innerText = 'Sayacı Başlat';
}

function updateTimerDisplay() {
    const display = document.getElementById('timerDisplay');
    if (!display) return;
    const h = String(Math.floor(taskTimerSeconds / 3600)).padStart(2, '0');
    const m = String(Math.floor((taskTimerSeconds % 3600) / 60)).padStart(2, '0');
    const s = String(taskTimerSeconds % 60).padStart(2, '0');
    display.innerText = `${h}:${m}:${s}`;
}

async function toggleTaskTimer() {
    if (!currentDrawerTaskId) return;

    const btn = document.getElementById('btnToggleTimer');
    const icon = document.getElementById('btnTimerIcon');
    const text = document.getElementById('btnTimerText');

    if (!isTimerRunning) {
        // Başlat
        isTimerRunning = true;
        if (btn) btn.style.background = '#e74c3c';
        if (icon) icon.innerText = '⏹';
        if (text) text.innerText = 'Sayacı Durdur & Kaydet';
        
        taskTimerInterval = setInterval(() => {
            taskTimerSeconds++;
            updateTimerDisplay();
        }, 1000);
        showToast('Zaman sayacı başlatıldı.', 'info');
    } else {
        // Durdur ve Kaydet
        clearInterval(taskTimerInterval);
        taskTimerInterval = null;
        isTimerRunning = false;

        const minutes = Math.max(1, Math.round(taskTimerSeconds / 60));
        await logTrackedTime(currentDrawerTaskId, minutes);
        resetTimerState();
    }
}

async function quickAddMinutes(minutes) {
    if (!currentDrawerTaskId) return;
    await logTrackedTime(currentDrawerTaskId, minutes);
}

async function logTrackedTime(taskId, minutes) {
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('minutes', minutes);

    try {
        const res = await fetch('/taskmanager/ajax?action=track_time', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            const el = document.getElementById('drawerTimeSpent');
            if (el && data.new_time_spent) el.innerText = data.new_time_spent;
        } else {
            showToast(data.message || 'Süre kaydedilemedi', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Bağlantı hatası', 'error');
    }
}

// ==========================================================================
// 15. TaskManager GÖREV BAĞIMLILIKLARI (TASK DEPENDENCIES)
// ==========================================================================
function renderDrawerDependencies(dependencies, availableTasks) {
    const list = document.getElementById('drawerDependenciesList');
    const countBadge = document.getElementById('drawerDepCount');
    const selectTask = document.getElementById('selectDepTask');
    if (!list) return;

    if (countBadge) countBadge.innerText = dependencies.length;
    list.innerHTML = '';

    if (dependencies.length === 0) {
        list.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:4px 0;">Henüz bağımlılık ilişkisi tanımlanmamış.</div>';
    } else {
        dependencies.forEach(d => {
            const isWaiting = (d.dep_type === 'waiting_on');
            const item = document.createElement('div');
            item.style.cssText = 'display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); padding:6px 10px; border-radius:var(--radius-sm); font-size:12px;';
            item.innerHTML = `
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:11px; padding:2px 6px; border-radius:4px; font-weight:700; ${isWaiting ? 'background:rgba(243,156,18,0.15); color:#f39c12;' : 'background:rgba(231,76,60,0.15); color:#e74c3c;'}">
                        ${isWaiting ? '⏳ Bekliyor' : '🚫 Engelliyor'}
                    </span>
                    <strong style="color:var(--text-white);">${d.dep_code}</strong>
                    <span style="color:var(--text-secondary); max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${d.dep_title}</span>
                </div>
                <button type="button" onclick="handleRemoveDependency(${d.dep_id})" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px 6px;" title="Kaldır">
                    ✕
                </button>
            `;
            list.appendChild(item);
        });
    }

    // Seçim kutusunu doldur
    if (selectTask && availableTasks) {
        selectTask.innerHTML = '<option value="">Görev seçin...</option>';
        availableTasks.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.innerText = `${t.task_code} - ${t.title}`;
            selectTask.appendChild(opt);
        });
    }
}

async function handleAddDependency(e) {
    e.preventDefault();
    if (!currentDrawerTaskId) return;

    const selectTask = document.getElementById('selectDepTask');
    const selectType = document.getElementById('selectDepType');
    const dependsOnId = selectTask ? selectTask.value : null;
    const depType = selectType ? selectType.value : 'waiting_on';

    if (!dependsOnId) {
        showToast('Lütfen bağlanacak görevi seçin', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('task_id', currentDrawerTaskId);
    formData.append('depends_on_task_id', dependsOnId);
    formData.append('type', depType);

    try {
        const res = await fetch('/taskmanager/ajax?action=add_dependency', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            openTaskDrawer(currentDrawerTaskId); // Yeniden yükle
        } else {
            showToast(data.message || 'Hata oluştu', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Bağlantı hatası', 'error');
    }
}

async function handleRemoveDependency(depId) {
    if (!depId || !currentDrawerTaskId) return;
    const formData = new FormData();
    formData.append('dep_id', depId);

    try {
        const res = await fetch('/taskmanager/ajax?action=remove_dependency', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast('Bağımlılık kaldırıldı', 'success');
            openTaskDrawer(currentDrawerTaskId);
        } else {
            showToast(data.message || 'Hata oluştu', 'error');
        }
    } catch (err) {
        console.error(err);
    }
}

// ==========================================================================
// 16. TaskManager LİSTE GÖRÜNÜMÜ FİLTRE VE SIRALAMA MOTORU (LIST FILTER ENGINE)
// ==========================================================================
let myTasksFilterActive = false;

function toggleMyTasksFilter(myUserId) {
    myTasksFilterActive = !myTasksFilterActive;
    const btn = document.getElementById('btnFilterMyTasks');
    if (btn) {
        if (myTasksFilterActive) {
            btn.style.background = 'rgba(123, 104, 238, 0.25)';
            btn.style.borderColor = 'var(--brand-primary)';
            btn.style.color = '#7B68EE';
        } else {
            btn.style.background = 'rgba(255,255,255,0.06)';
            btn.style.borderColor = 'var(--border-subtle)';
            btn.style.color = 'var(--text-primary)';
        }
    }
    applyFilters(myUserId);
}

function applyFilters(myUserId) {
    const searchVal = (document.getElementById('cuListSearch')?.value || '').toLowerCase().trim();
    const priorityVal = document.getElementById('cuFilterPriority')?.value || '';
    const assigneeVal = document.getElementById('cuFilterAssignee')?.value || '';

    let visibleCount = 0;

    // 1. Liste Görünümü Filtreleme
    const containers = document.querySelectorAll('.cu-task-container');
    if (containers.length > 0) {
        containers.forEach(container => {
            const row = container.querySelector('.cu-task-row');
            if (!row) return;

            const title = (row.getAttribute('data-title') || '').toLowerCase();
            const code = (row.getAttribute('data-code') || '').toLowerCase();
            const tags = (row.getAttribute('data-tags') || '').toLowerCase();
            const priority = row.getAttribute('data-priority') || '';
            const assigneeIds = (row.getAttribute('data-assignee-ids') || row.getAttribute('data-assignee-id') || '').split(',').map(s => s.trim());

            const matchesSearch = !searchVal || title.includes(searchVal) || code.includes(searchVal) || tags.includes(searchVal);
            const matchesPriority = !priorityVal || priority === priorityVal;
            const matchesAssignee = !assigneeVal || assigneeIds.includes(String(assigneeVal));
            const matchesMyTasks = !myTasksFilterActive || (myUserId && assigneeIds.includes(String(myUserId)));

            if (matchesSearch && matchesPriority && matchesAssignee && matchesMyTasks) {
                container.style.display = 'block';
                visibleCount++;
            } else {
                container.style.display = 'none';
            }
        });
    }

    // 2. Kanban Panosu Filtreleme
    const boardCols = document.querySelectorAll('.cu-board-col');
    if (boardCols.length > 0) {
        boardCols.forEach(col => {
            const cards = col.querySelectorAll('.cu-board-card');
            let colVisibleCount = 0;

            cards.forEach(card => {
                const title = (card.getAttribute('data-title') || '').toLowerCase();
                const code = (card.getAttribute('data-code') || '').toLowerCase();
                const tags = (card.getAttribute('data-tags') || '').toLowerCase();
                const priority = card.getAttribute('data-priority') || '';
                const assigneeIds = (card.getAttribute('data-assignee-ids') || card.getAttribute('data-assignee-id') || '').split(',').map(s => s.trim());

                const matchesSearch = !searchVal || title.includes(searchVal) || code.includes(searchVal) || tags.includes(searchVal);
                const matchesPriority = !priorityVal || priority === priorityVal;
                const matchesAssignee = !assigneeVal || assigneeIds.includes(String(assigneeVal));
                const matchesMyTasks = !myTasksFilterActive || (myUserId && assigneeIds.includes(String(myUserId)));

                if (matchesSearch && matchesPriority && matchesAssignee && matchesMyTasks) {
                    card.style.display = 'block';
                    colVisibleCount++;
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Sütun başlığındaki sayaç rozetini güncelle
            const badge = col.querySelector('.cu-board-col-header .cu-nav-badge');
            if (badge) badge.innerText = colVisibleCount;
        });
    }

    const countEl = document.getElementById('cuVisibleTaskCount');
    if (countEl) countEl.innerText = visibleCount;
}

const applyListFilters = applyFilters;

function applySorting() {
    const sortVal = document.getElementById('cuSortBy')?.value || 'default';
    const prOrder = { 'urgent': 4, 'high': 3, 'normal': 2, 'low': 1 };

    // 1. Liste Görünümü Sıralama
    const groups = document.querySelectorAll('.cu-group, .cu-group-container');
    groups.forEach(group => {
        const taskList = group.querySelector('.cu-task-list');
        if (!taskList) return;

        const containers = Array.from(taskList.querySelectorAll('.cu-task-container'));
        containers.sort((a, b) => {
            const rowA = a.querySelector('.cu-task-row');
            const rowB = b.querySelector('.cu-task-row');
            if (!rowA || !rowB) return 0;

            if (sortVal === 'deadline_asc') {
                return (rowA.getAttribute('data-deadline') || '9999-12-31').localeCompare(rowB.getAttribute('data-deadline') || '9999-12-31');
            } else if (sortVal === 'priority_desc') {
                return (prOrder[rowB.getAttribute('data-priority')] || 0) - (prOrder[rowA.getAttribute('data-priority')] || 0);
            } else if (sortVal === 'title_asc') {
                return (rowA.getAttribute('data-title') || '').localeCompare(rowB.getAttribute('data-title') || '');
            }
            return 0;
        });
        containers.forEach(c => taskList.appendChild(c));
    });

    // 2. Kanban Panosu Sıralama
    const boardCardsContainers = document.querySelectorAll('.cu-board-cards');
    boardCardsContainers.forEach(container => {
        const cards = Array.from(container.querySelectorAll('.cu-board-card'));
        cards.sort((a, b) => {
            if (sortVal === 'deadline_asc') {
                return (a.getAttribute('data-deadline') || '9999-12-31').localeCompare(b.getAttribute('data-deadline') || '9999-12-31');
            } else if (sortVal === 'priority_desc') {
                return (prOrder[b.getAttribute('data-priority')] || 0) - (prOrder[a.getAttribute('data-priority')] || 0);
            } else if (sortVal === 'title_asc') {
                return (a.getAttribute('data-title') || '').localeCompare(b.getAttribute('data-title') || '');
            }
            return 0;
        });
        cards.forEach(c => container.appendChild(c));
    });
}

const applyListSorting = applySorting;

function resetFilters(myUserId) {
    if (document.getElementById('cuListSearch')) document.getElementById('cuListSearch').value = '';
    if (document.getElementById('cuFilterPriority')) document.getElementById('cuFilterPriority').value = '';
    if (document.getElementById('cuFilterAssignee')) document.getElementById('cuFilterAssignee').value = '';
    if (document.getElementById('cuSortBy')) document.getElementById('cuSortBy').value = 'default';
    if (myTasksFilterActive) toggleMyTasksFilter(myUserId);
    applyFilters(myUserId);
    showToast('Filtreler sıfırlandı.', 'info');
}

const resetListFilters = resetFilters;

// ==========================================================================
// 17. TaskManager NESTED SUBTASKS TREE TOGGLER (AĞAÇ GÖRÜNÜMÜ)
// ==========================================================================
function toggleSubtasksTree(parentTaskId) {
    const tree = document.getElementById(`subtasksTree-${parentTaskId}`);
    const btn = document.getElementById(`btnTreeToggle-${parentTaskId}`);
    if (!tree) return;

    if (tree.style.display === 'none' || tree.style.display === '') {
        tree.style.display = 'block';
        if (btn) {
            btn.innerText = '▼';
            btn.style.color = 'var(--brand-primary)';
        }
    } else {
        tree.style.display = 'none';
        if (btn) {
            btn.innerText = '▶';
            btn.style.color = 'var(--text-muted)';
        }
    }
}

function expandAndFocusSubtask(parentTaskId) {
    const tree = document.getElementById(`subtasksTree-${parentTaskId}`);
    const btn = document.getElementById(`btnTreeToggle-${parentTaskId}`);
    if (tree) {
        tree.style.display = 'block';
        if (btn) {
            btn.innerText = '▼';
            btn.style.color = 'var(--brand-primary)';
        }
    }
    const input = document.getElementById(`quickSubtaskInput-${parentTaskId}`);
    if (input) {
        input.focus();
    }
}

async function handleQuickAddSubtask(parentTaskId) {
    const input = document.getElementById(`quickSubtaskInput-${parentTaskId}`);
    if (!input) return;
    const title = input.value.trim();
    if (!title) {
        showToast('Lütfen alt görev başlığı girin', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('parent_task_id', parentTaskId);
    formData.append('title', title);
    formData.append('priority', 'normal');

    try {
        const res = await fetch('/taskmanager/ajax?action=create_subtask', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            input.value = '';

            // Yeni alt görev satırını anında DOM'a ekle
            const rowsContainer = document.getElementById(`subtasksRows-${parentTaskId}`);
            if (rowsContainer) {
                const row = document.createElement('div');
                row.className = 'cu-task-row';
                row.style.cssText = 'padding:7px 16px; border-bottom:1px solid rgba(255,255,255,0.03); font-size:12px; cursor:pointer;';
                row.onclick = () => openTaskDrawer(data.subtask_id);
                row.innerHTML = `
                    <div style="color:var(--text-muted); font-size:11px; font-family:monospace; margin-right:8px;">
                        └── <strong style="color:var(--brand-primary);">${data.task_code}</strong>
                    </div>
                    <div style="flex:1; color:var(--text-primary); font-weight:500;">
                        ${data.title}
                    </div>
                    <div>
                        <span class="cu-priority-badge cu-priority-normal" style="font-size:10px; padding:2px 6px;">
                            🔵 Normal
                        </span>
                    </div>
                    <div class="cu-task-assignee" style="margin-left:12px;">
                        <div class="cu-avatar" style="width:18px; height:18px; font-size:9px; background:#7B68EE;">
                            B
                        </div>
                        <span style="font-size:11px; color:var(--text-secondary);">
                            Ben
                        </span>
                    </div>
                `;
                rowsContainer.appendChild(row);
            }
        } else {
            showToast(data.message || 'Alt görev oluşturulamadı', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Bağlantı hatası', 'error');
    }
}

// ==========================================================================
// 18. TaskManager GÖREV AÇIKLAMASI DÜZENLEME MOTORU (TASK DESCRIPTION INLINE EDITOR)
// ==========================================================================
function formatDescText(command) {
    document.execCommand(command, false, null);
    handleDescInput();
}

function formatDescBlock(tag) {
    document.execCommand('formatBlock', false, tag);
    handleDescInput();
}

function handleDescFocus() {
    const descEl = document.getElementById('drawerDescription');
    if (descEl) {
        descEl.style.borderColor = 'var(--brand-primary)';
        descEl.style.background = 'rgba(0,0,0,0.35)';
        // Eğer placeholder varsa temizle
        if (descEl.innerHTML.includes('Görev açıklaması ve talimatları')) {
            descEl.innerHTML = '';
        }
    }
}

function handleDescBlur() {
    const descEl = document.getElementById('drawerDescription');
    if (descEl) {
        descEl.style.borderColor = 'var(--border-subtle)';
        descEl.style.background = 'rgba(0,0,0,0.2)';
    }
}

function handleDescInput() {
    const btn = document.getElementById('btnSaveDrawerDesc');
    const status = document.getElementById('drawerDescSaveStatus');
    if (btn) btn.style.display = 'inline-flex';
    if (status) {
        status.innerText = '● Kaydedilmemiş değişiklikler';
        status.style.color = '#f39c12';
    }
}

async function saveTaskDescription() {
    if (!currentDrawerTaskId) return;

    const descEl = document.getElementById('drawerDescription');
    const btn = document.getElementById('btnSaveDrawerDesc');
    const status = document.getElementById('drawerDescSaveStatus');

    const content = descEl ? descEl.innerHTML.trim() : '';

    if (status) {
        status.innerText = '⏳ Kaydediliyor...';
        status.style.color = 'var(--brand-primary)';
    }

    const formData = new FormData();
    formData.append('task_id', currentDrawerTaskId);
    formData.append('field', 'description');
    formData.append('value', content);

    try {
        const res = await fetch('/taskmanager/ajax?action=update_task_field', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast('Görev açıklaması güncellendi!', 'success');
            if (btn) btn.style.display = 'none';
            if (status) {
                status.innerText = '✓ Kaydedildi';
                status.style.color = '#00b894';
            }
        } else {
            showToast(data.message || 'Açıklama kaydedilemedi', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Bağlantı hatası', 'error');
    }
}

// ==========================================================================
// 19. TaskManager SİLME VE PROJE YÖNETİM MOTORU (DELETION & SPACES LIFECYCLE)
// ==========================================================================

// 1. Görevi Çekmeceden Sil
async function deleteCurrentTask() {
    if (!currentDrawerTaskId) return;
    if (!confirm('Bu görevi ve bağlı tüm alt öğelerini silmek istediğinize emin misiniz? Bu işlem geri alınamaz!')) return;

    await executeDeleteTask(currentDrawerTaskId);
    closeTaskDrawer();
}

// 2. Görevi Liste Görünümünden Sil
async function deleteTaskFromList(taskId) {
    if (!taskId) return;
    if (!confirm('Bu görevi silmek istediğinize emin misiniz?')) return;

    await executeDeleteTask(taskId);
}

async function executeDeleteTask(taskId) {
    const formData = new FormData();
    formData.append('task_id', taskId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_task', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            // DOM'dan görevi temizle (Liste konteyneri veya Kanban kartı)
            const listContainer = document.getElementById(`task-container-${taskId}`);
            if (listContainer) listContainer.remove();
            const boardCard = document.querySelector(`.cu-board-card[data-task-id="${taskId}"]`);
            if (boardCard) boardCard.remove();
        } else {
            showToast(data.message || 'Görev silinemedi', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Bağlantı hatası', 'error');
    }
}

// 3. Alt Görevi Sil
async function deleteSubtask(subtaskId) {
    if (!subtaskId) return;
    if (!confirm('Bu alt görevi silmek istediğinize emin misiniz?')) return;

    const formData = new FormData();
    formData.append('task_id', subtaskId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_task', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast('Alt görev silindi', 'success');
            if (currentDrawerTaskId) openTaskDrawer(currentDrawerTaskId);
            else window.location.reload();
        } else {
            showToast(data.message || 'Silinemedi', 'error');
        }
    } catch (e) {
        console.error(e);
    }
}

// 4. Kontrol Listesi Maddesini Sil
async function handleDeleteChecklistItem(itemId) {
    if (!itemId) return;
    const formData = new FormData();
    formData.append('item_id', itemId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_checklist_item', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast('Madde silindi', 'success');
            currentChecklistData = currentChecklistData.filter(c => c.id != itemId);
            renderDrawerChecklists(currentChecklistData);
        } else {
            showToast(data.message || 'Silinemedi', 'error');
        }
    } catch (e) {
        console.error(e);
    }
}

// ==========================================================================
// 22. TaskManager MULTI-ASSIGNEE ENGINE (@ MENTION SEÇİCİ)
// ==========================================================================
let drawerCurrentAssignees = [];
let drawerAllTeamUsers = [];
let modalSelectedAssignees = [];

// 1. MODAL ÇOKLU ÇALIŞAN SEÇİM İŞLEVLERİ
function initModalAssignees() {
    modalSelectedAssignees = [];
    const team = window.ALL_TEAM_USERS || [];
    if (team.length > 0) {
        modalSelectedAssignees.push(team[0]); // Varsayılan ilk çalışan (Yönetici)
    }
    renderModalAssignees();
    const input = document.getElementById('modalAssigneeInput');
    if (input) input.value = '';
    const dropdown = document.getElementById('modalAssigneeDropdown');
    if (dropdown) dropdown.style.display = 'none';
}

function renderModalAssignees() {
    const container = document.getElementById('modalSelectedAssignees');
    const hiddenContainer = document.getElementById('modalAssigneeHiddenInputs');
    if (!container || !hiddenContainer) return;

    container.innerHTML = '';
    hiddenContainer.innerHTML = '';

    if (modalSelectedAssignees.length === 0) {
        container.innerHTML = '<span style="font-size:12px; color:var(--text-muted); font-style:italic;">Henüz çalışan seçilmedi. Aşağıdan @ yazarak ekleyin.</span>';
        return;
    }

    modalSelectedAssignees.forEach(u => {
        const chip = document.createElement('div');
        chip.style.cssText = 'display:inline-flex; align-items:center; gap:6px; background:rgba(123, 104, 238, 0.15); border:1px solid rgba(123, 104, 238, 0.35); border-radius:16px; padding:3px 10px 3px 4px; font-size:12px; font-weight:600; color:var(--text-white); animation:cuFadeIn 0.2s ease;';
        chip.innerHTML = `
            <div class="cu-avatar" style="width:22px; height:22px; font-size:10px; background:${u.avatar_color || '#7B68EE'}; flex-shrink:0;">
                ${(u.name || 'U').charAt(0)}
            </div>
            <span>${u.name}</span>
            <button type="button" onclick="removeModalAssignee(${u.id})" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:0 2px; margin-left:2px; display:flex; align-items:center;" title="Kaldır" onmouseover="this.style.color='#ff4757'" onmouseout="this.style.color='var(--text-muted)'">✕</button>
        `;
        container.appendChild(chip);

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'assigned_to[]';
        input.value = u.id;
        hiddenContainer.appendChild(input);
    });
}

function handleModalAssigneeSearch(query) {
    const dropdown = document.getElementById('modalAssigneeDropdown');
    if (!dropdown) return;

    const cleanQuery = (query || '').replace(/@/g, '').toLowerCase().trim();
    const team = window.ALL_TEAM_USERS || [];

    const selectedIds = modalSelectedAssignees.map(u => u.id);
    const filtered = team.filter(u => {
        if (selectedIds.includes(u.id)) return false;
        if (!cleanQuery) return true;
        return (u.name || '').toLowerCase().includes(cleanQuery) || 
               (u.email || '').toLowerCase().includes(cleanQuery) ||
               (u.title || '').toLowerCase().includes(cleanQuery);
    });

    if (filtered.length === 0) {
        dropdown.innerHTML = '<div style="padding:10px 12px; font-size:12px; color:var(--text-muted);">Eşleşen veya eklenebilecek çalışan kalmadı.</div>';
        dropdown.style.display = 'block';
        return;
    }

    dropdown.innerHTML = filtered.map(u => `
        <div class="cu-dropdown-user-item" onmousedown="event.preventDefault(); addModalAssignee(${u.id});" style="display:flex; align-items:center; justify-content:space-between; padding:8px 12px; cursor:pointer; border-bottom:1px solid rgba(255,255,255,0.03); transition:background 0.15s ease;" onmouseover="this.style.background='rgba(123, 104, 238, 0.12)'" onmouseout="this.style.background='transparent'">
            <div style="display:flex; align-items:center; gap:8px;">
                <div class="cu-avatar" style="width:24px; height:24px; font-size:11px; background:${u.avatar_color || '#7B68EE'}; flex-shrink:0;">
                    ${(u.name || 'U').charAt(0)}
                </div>
                <div>
                    <div style="font-size:12.5px; font-weight:600; color:var(--text-white);">${u.name}</div>
                    <div style="font-size:11px; color:var(--text-muted);">${u.title || u.role}</div>
                </div>
            </div>
            <span style="font-size:11px; color:var(--brand-primary); font-weight:600;">+ Ekle</span>
        </div>
    `).join('');
    dropdown.style.display = 'block';
}

function addModalAssignee(userId) {
    const team = window.ALL_TEAM_USERS || [];
    const user = team.find(u => u.id == userId);
    if (user && !modalSelectedAssignees.some(u => u.id == userId)) {
        modalSelectedAssignees.push(user);
        renderModalAssignees();
    }
    const input = document.getElementById('modalAssigneeInput');
    if (input) input.value = '';
    const dropdown = document.getElementById('modalAssigneeDropdown');
    if (dropdown) dropdown.style.display = 'none';
}

function removeModalAssignee(userId) {
    modalSelectedAssignees = modalSelectedAssignees.filter(u => u.id != userId);
    renderModalAssignees();
}

// 2. DRAWER ÇOKLU ÇALIŞAN İŞLEVLERİ
function renderDrawerAssignees(assignees) {
    drawerCurrentAssignees = assignees || [];
    const container = document.getElementById('drawerAssigneesContainer');
    if (!container) return;

    container.innerHTML = '';
    if (drawerCurrentAssignees.length === 0) {
        container.innerHTML = '<span style="font-size:12px; color:var(--text-muted); font-style:italic;">Bu göreve atanmış çalışan yok.</span>';
        return;
    }

    drawerCurrentAssignees.forEach(u => {
        const chip = document.createElement('div');
        chip.style.cssText = 'display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.06); border:1px solid var(--border-subtle); border-radius:16px; padding:3px 10px 3px 4px; font-size:12px; font-weight:500; color:var(--text-white); transition:all 0.2s ease;';
        chip.innerHTML = `
            <div class="cu-avatar" style="width:22px; height:22px; font-size:10px; background:${u.avatar_color || '#7B68EE'}; flex-shrink:0;">
                ${(u.name || 'U').charAt(0)}
            </div>
            <span>${u.name}</span>
            <button type="button" onclick="removeAssigneeFromDrawerTask(${u.id})" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:0 2px; margin-left:2px; display:flex; align-items:center;" title="Görevden Çıkar" onmouseover="this.style.color='#ff4757'" onmouseout="this.style.color='var(--text-muted)'">✕</button>
        `;
        container.appendChild(chip);
    });
}

function focusDrawerAssigneeInput() {
    const input = document.getElementById('drawerAssigneeInput');
    if (input) {
        input.focus();
        handleDrawerAssigneeSearch(input.value);
    }
}

function handleDrawerAssigneeSearch(query) {
    const dropdown = document.getElementById('drawerAssigneeDropdown');
    if (!dropdown) return;

    const cleanQuery = (query || '').replace(/@/g, '').toLowerCase().trim();
    const team = drawerAllTeamUsers.length > 0 ? drawerAllTeamUsers : (window.ALL_TEAM_USERS || []);

    const assignedIds = drawerCurrentAssignees.map(u => u.id);
    const filtered = team.filter(u => {
        if (assignedIds.includes(u.id)) return false;
        if (!cleanQuery) return true;
        return (u.name || '').toLowerCase().includes(cleanQuery) || 
               (u.email || '').toLowerCase().includes(cleanQuery) ||
               (u.title || '').toLowerCase().includes(cleanQuery);
    });

    if (filtered.length === 0) {
        dropdown.innerHTML = '<div style="padding:10px 12px; font-size:12px; color:var(--text-muted);">Eklenebilecek çalışan kalmadı veya bulunamadı.</div>';
        dropdown.style.display = 'block';
        return;
    }

    dropdown.innerHTML = filtered.map(u => `
        <div class="cu-dropdown-user-item" onmousedown="event.preventDefault(); addAssigneeToDrawerTask(${u.id});" style="display:flex; align-items:center; justify-content:space-between; padding:8px 12px; cursor:pointer; border-bottom:1px solid rgba(255,255,255,0.03); transition:background 0.15s ease;" onmouseover="this.style.background='rgba(123, 104, 238, 0.12)'" onmouseout="this.style.background='transparent'">
            <div style="display:flex; align-items:center; gap:8px;">
                <div class="cu-avatar" style="width:24px; height:24px; font-size:11px; background:${u.avatar_color || '#7B68EE'}; flex-shrink:0;">
                    ${(u.name || 'U').charAt(0)}
                </div>
                <div>
                    <div style="font-size:12.5px; font-weight:600; color:var(--text-white);">${u.name}</div>
                    <div style="font-size:11px; color:var(--text-muted);">${u.title || u.role}</div>
                </div>
            </div>
            <span style="font-size:11px; color:var(--brand-primary); font-weight:600;">+ Ata</span>
        </div>
    `).join('');
    dropdown.style.display = 'block';
}

async function addAssigneeToDrawerTask(userId) {
    if (!currentDrawerTaskId) return;

    const formData = new FormData();
    formData.append('task_id', currentDrawerTaskId);
    formData.append('user_id', userId);

    try {
        const res = await fetch('/taskmanager/ajax?action=add_task_assignee', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            if (data.user) {
                drawerCurrentAssignees.push(data.user);
                renderDrawerAssignees(drawerCurrentAssignees);
            }
            const input = document.getElementById('drawerAssigneeInput');
            if (input) input.value = '';
            const dropdown = document.getElementById('drawerAssigneeDropdown');
            if (dropdown) dropdown.style.display = 'none';

            updateTaskCardAssigneesDOM(currentDrawerTaskId, drawerCurrentAssignees);
        } else {
            showToast(data.message || 'Çalışan atanamadı', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Bağlantı hatası', 'error');
    }
}

async function removeAssigneeFromDrawerTask(userId) {
    if (!currentDrawerTaskId) return;

    const formData = new FormData();
    formData.append('task_id', currentDrawerTaskId);
    formData.append('user_id', userId);

    try {
        const res = await fetch('/taskmanager/ajax?action=remove_task_assignee', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            drawerCurrentAssignees = drawerCurrentAssignees.filter(u => u.id != userId);
            renderDrawerAssignees(drawerCurrentAssignees);
            updateTaskCardAssigneesDOM(currentDrawerTaskId, drawerCurrentAssignees);
        } else {
            showToast(data.message || 'Çıkarılamadı', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Bağlantı hatası', 'error');
    }
}

function updateTaskCardAssigneesDOM(taskId, assignees) {
    // 1. Kanban Kartı Avatarları
    const card = document.querySelector(`.cu-board-card[data-task-id="${taskId}"]`);
    if (card) {
        card.setAttribute('data-assignee-ids', assignees.map(u => u.id).join(','));
        const footer = card.querySelector('.cu-board-card-footer');
        if (footer) {
            let avatarsContainer = footer.querySelector('.cu-card-avatars');
            if (!avatarsContainer) {
                const oldAvatar = footer.querySelector('.cu-avatar');
                if (oldAvatar) oldAvatar.remove();
                avatarsContainer = document.createElement('div');
                avatarsContainer.className = 'cu-card-avatars';
                avatarsContainer.style.cssText = 'display:flex; align-items:center; gap:4px; flex-wrap:wrap;';
                footer.appendChild(avatarsContainer);
            }
            avatarsContainer.innerHTML = assignees.map(u => `
                <div class="cu-avatar" style="width:22px; height:22px; font-size:10px; background:${u.avatar_color || '#7B68EE'};" title="${u.name}">
                    ${(u.name || 'U').charAt(0)}
                </div>
            `).join('');
        }
    }

    // 2. Liste Görünümü Satırı Avatarları
    const row = document.querySelector(`.cu-task-row[data-task-id="${taskId}"]`);
    if (row) {
        row.setAttribute('data-assignee-ids', assignees.map(u => u.id).join(','));
        const assigneeDiv = row.querySelector('.cu-task-assignee');
        if (assigneeDiv) {
            assigneeDiv.innerHTML = assignees.map(u => `
                <div class="cu-avatar" style="width:22px; height:22px; font-size:10px; background:${u.avatar_color || '#7B68EE'}; flex-shrink:0;" title="${u.name}">
                    ${(u.name || 'U').charAt(0)}
                </div>
            `).join('');
        }
    }
}
// ==========================================================================

// ETİKETLER (TAGS) & AI OTONOM YÖNETİMİ
// ==============================================================================

// 1. Yeni Görev Modalındaki AI Otonom Butonunu Değiştir
function toggleNewTaskAiTag() {
    const input = document.getElementById('modalNewTaskTagsInput');
    const btn = document.getElementById('btnToggleAiTag');
    const btnText = document.getElementById('btnToggleAiTagText');
    if (!input || !btn) return;

    let tags = input.value.split(',').map(t => t.trim()).filter(Boolean);
    const hasAi = tags.some(t => ['ai', '#ai', 'otonom', 'agent', 'yapay zeka'].includes(t.toLowerCase()));

    if (hasAi) {
        tags = tags.filter(t => !['ai', '#ai', 'otonom', 'agent', 'yapay zeka'].includes(t.toLowerCase()));
        btn.style.background = 'rgba(155, 93, 229, 0.15)';
        btn.style.borderColor = 'rgba(155, 93, 229, 0.45)';
        btn.style.color = '#d8b4fe';
        if (btnText) btnText.innerText = 'AI Otonom Ekle';
    } else {
        tags.push('ai');
        btn.style.background = 'linear-gradient(135deg, #7B68EE, #e056fd)';
        btn.style.borderColor = '#e056fd';
        btn.style.color = '#ffffff';
        if (btnText) btnText.innerText = 'AI Otonom Aktif ✓';
    }
    input.value = tags.join(', ');
}

// 2. Yeni Görev Modalına Hızlı Etiket Ekle
function appendTagToModal(tag) {
    const input = document.getElementById('modalNewTaskTagsInput');
    if (!input) return;
    let tags = input.value.split(',').map(t => t.trim()).filter(Boolean);
    if (!tags.map(t => t.toLowerCase()).includes(tag.toLowerCase())) {
        tags.push(tag);
        input.value = tags.join(', ');
    }
}

// 3. Görev Detayı Çekmecesinde (Drawer) Etiketleri İnteraktif Olarak Çiz
function renderDrawerTags(task) {
    const tagsContainer = document.getElementById('drawerTagsContainer');
    if (!tagsContainer) return;
    tagsContainer.innerHTML = '';

    const rawTags = (task.tags || '').split(',').map(t => t.trim().replace(/^#/, '')).filter(Boolean);
    const isAi = rawTags.some(t => ['ai', 'otonom', 'agent', 'yapay zeka'].includes(t.toLowerCase()));

    // A) AI Otonom Hızlı Ekle/Kaldır Butonu
    const aiBtn = document.createElement('button');
    aiBtn.type = 'button';
    aiBtn.style.cssText = isAi 
        ? 'padding:4px 10px; font-size:11px; font-weight:700; border-radius:6px; background:linear-gradient(135deg, #7B68EE, #e056fd); border:1px solid #e056fd; color:#fff; cursor:pointer; display:inline-flex; align-items:center; gap:5px; box-shadow:0 0 10px rgba(123, 104, 238, 0.4); transition:all 0.2s ease;'
        : 'padding:4px 10px; font-size:11px; font-weight:700; border-radius:6px; background:rgba(155, 93, 229, 0.12); border:1px solid rgba(155, 93, 229, 0.35); color:#c4b5fd; cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:all 0.2s ease;';
    aiBtn.innerHTML = isAi 
        ? '<span>🤖</span><span>AI Otonom Aktif</span><span style="background:rgba(0,0,0,0.3); border-radius:50%; width:16px; height:16px; display:inline-flex; align-items:center; justify-content:center; font-size:10px; margin-left:3px;" title="AI Etiketini Kaldır">✕</span>' 
        : '<span>🤖</span><span>+ AI Otonom Yap</span>';
    aiBtn.title = isAi ? 'Yapay zeka otonom etiketini kaldırmak için tıkla' : 'Yapay zekanın otomatik üstlenmesi için tıkla';
    aiBtn.onclick = () => toggleDrawerAiTag(task);
    tagsContainer.appendChild(aiBtn);

    // B) Tüm Etiket Chipleri (Her birinin yanında kırmızı hover efektli net bir ✕ silme butonu)
    rawTags.forEach(t => {
        const isTagAi = ['ai', 'otonom', 'agent', 'yapay zeka'].includes(t.toLowerCase());
        const chip = document.createElement('span');
        chip.className = 'cu-tag-pill-interactive';
        chip.style.cssText = isTagAi
            ? 'font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px; background:rgba(155, 93, 229, 0.2); color:#e0b0ff; border:1px solid rgba(155, 93, 229, 0.5); display:inline-flex; align-items:center; gap:6px;'
            : 'font-size:11px; font-weight:600; padding:3px 8px; border-radius:6px; background:var(--bg-card-hover, rgba(255,255,255,0.08)); color:var(--text-primary); border:1px solid var(--border-subtle); display:inline-flex; align-items:center; gap:6px;';
        
        chip.innerHTML = `<span>#${escapeHtml(t)}</span><button type="button" class="btn-remove-tag" style="background:none; border:none; color:var(--text-muted); font-size:11px; font-weight:800; cursor:pointer; padding:0 3px; border-radius:3px; display:inline-flex; align-items:center; justify-content:center; line-height:1; transition:all 0.15s ease;" title="'#${escapeHtml(t)}' etiketini kaldır">✕</button>`;
        
        const removeBtn = chip.querySelector('.btn-remove-tag');
        removeBtn.onmouseenter = () => { removeBtn.style.color = '#ff4d4d'; removeBtn.style.background = 'rgba(255, 77, 77, 0.15)'; };
        removeBtn.onmouseleave = () => { removeBtn.style.color = 'var(--text-muted)'; removeBtn.style.background = 'none'; };
        removeBtn.onclick = (e) => {
            e.stopPropagation();
            removeDrawerTag(task, t);
        };
        tagsContainer.appendChild(chip);
    });

    // C) Yeni Etiket Ekleme Form Alanı (+ Butonlu)
    const addWrap = document.createElement('div');
    addWrap.style.cssText = 'position:relative; display:inline-flex; align-items:center; gap:4px;';
    addWrap.innerHTML = `
        <input type="text" id="drawerNewTagInput" placeholder="+ Etiket ekle..." style="font-size:11px; padding:4px 8px; background:rgba(0,0,0,0.25); border:1px dashed var(--border-subtle); border-radius:6px; color:var(--text-primary); outline:none; width:95px; transition:all 0.2s ease;">
        <button type="button" id="drawerNewTagBtn" style="font-size:11px; padding:3px 7px; background:var(--brand-primary, #7B68EE); border:none; border-radius:5px; color:#fff; cursor:pointer; font-weight:700; display:none;">+</button>
    `;
    const input = addWrap.querySelector('input');
    const submitBtn = addWrap.querySelector('button');

    const doAdd = () => {
        const val = input.value.trim().replace(/^#/, '');
        if (val) {
            addDrawerTag(task, val);
            input.value = '';
            input.style.width = '95px';
            submitBtn.style.display = 'none';
        }
    };

    input.onfocus = () => { 
        input.style.width = '130px'; 
        input.style.borderColor = 'var(--brand-primary)'; 
        input.style.borderStyle = 'solid'; 
        submitBtn.style.display = 'inline-block';
    };
    input.onblur = () => { 
        setTimeout(() => {
            if (!input.value) { 
                input.style.width = '95px'; 
                input.style.borderColor = 'var(--border-subtle)'; 
                input.style.borderStyle = 'dashed'; 
                submitBtn.style.display = 'none';
            } 
        }, 200);
    };
    input.onkeydown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            doAdd();
        }
    };
    submitBtn.onclick = (e) => {
        e.preventDefault();
        doAdd();
    };
    tagsContainer.appendChild(addWrap);
}

// 4. Sunucuya Etiket Güncellemesini Gönder ve Panodaki Kartı Eşitle
async function updateTaskTagsOnServer(taskId, newTagsStr) {
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('field', 'tags');
    formData.append('value', newTagsStr);

    try {
        const res = await fetch('/taskmanager/ajax?action=update_task_field', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        if (d.success) {
            showToast('Etiketler güncellendi!', 'success');
            syncBoardCardTags(taskId, newTagsStr);
            return true;
        } else {
            showToast(d.message || 'Güncellenemedi', 'error');
            return false;
        }
    } catch(e) {
        showToast('Bağlantı hatası', 'error');
        return false;
    }
}

// 4.1 Panodaki (Kanban Board) Kartın Etiketlerini ve AI Rozetini Anında Güncelle
function syncBoardCardTags(taskId, newTagsStr) {
    const card = document.querySelector(`.cu-board-card[data-task-id="${taskId}"]`);
    if (!card) return;

    card.setAttribute('data-tags', (newTagsStr || '').toLowerCase());

    const tagsArr = (newTagsStr || '').split(',').map(t => t.trim().toLowerCase().replace(/^#/, '')).filter(Boolean);
    const isAi = tagsArr.some(t => ['ai', 'otonom', 'agent', 'yapay zeka'].includes(t));

    // Kart içindeki AI rozetini bul veya güncelle
    let aiBadge = card.querySelector('.cu-ai-tag-badge');
    const priorityRow = card.querySelector('.cu-board-card-title')?.nextElementSibling;

    if (isAi) {
        if (!aiBadge && priorityRow) {
            aiBadge = document.createElement('span');
            aiBadge.className = 'cu-ai-tag-badge';
            aiBadge.style.cssText = 'font-size:10px; padding:1px 6px; border-radius:4px; background:rgba(155, 93, 229, 0.2); border:1px solid rgba(155, 93, 229, 0.45); color:#d8b4fe; font-weight:700; display:inline-flex; align-items:center; gap:3px;';
            aiBadge.title = '🤖 Otonom Kuyruk: Sırası gelince yapay zeka otomatik başlar';
            aiBadge.innerHTML = '<span>🤖</span><span>AI Otonom</span>';
            priorityRow.appendChild(aiBadge);
        }
    } else {
        if (aiBadge) {
            aiBadge.remove();
        } else {
            // Eğer PHP'den render edilen inline badge varsa onu da temizle
            const inlineAiSpans = priorityRow ? priorityRow.querySelectorAll('span') : [];
            inlineAiSpans.forEach(s => {
                if (s.textContent.includes('AI Otonom')) s.remove();
            });
        }
    }
}

// 5. Drawer AI Etiketi Toggle (Ekle veya Çıkar)
async function toggleDrawerAiTag(task) {
    let currentTags = (task.tags || '').split(',').map(t => t.trim().replace(/^#/, '')).filter(Boolean);
    const hasAi = currentTags.some(t => ['ai', 'otonom', 'agent', 'yapay zeka'].includes(t.toLowerCase()));
    
    if (hasAi) {
        currentTags = currentTags.filter(t => !['ai', 'otonom', 'agent', 'yapay zeka'].includes(t.toLowerCase()));
    } else {
        currentTags.push('ai');
    }
    const newTagsStr = currentTags.join(', ');
    task.tags = newTagsStr;
    const ok = await updateTaskTagsOnServer(task.id, newTagsStr);
    if (ok) renderDrawerTags(task);
}

// 6. Drawer Yeni Etiket Ekle
async function addDrawerTag(task, tag) {
    const cleanTag = tag.trim().replace(/^#/, '');
    if (!cleanTag) return;

    let currentTags = (task.tags || '').split(',').map(t => t.trim().replace(/^#/, '')).filter(Boolean);
    if (!currentTags.map(t => t.toLowerCase()).includes(cleanTag.toLowerCase())) {
        currentTags.push(cleanTag);
        const newTagsStr = currentTags.join(', ');
        task.tags = newTagsStr;
        const ok = await updateTaskTagsOnServer(task.id, newTagsStr);
        if (ok) renderDrawerTags(task);
    }
}

// 7. Drawer Etiketi Kaldır (Kusursuz Normalizasyon İle)
async function removeDrawerTag(task, tag) {
    const targetClean = tag.trim().replace(/^#/, '').toLowerCase();
    let currentTags = (task.tags || '').split(',').map(t => t.trim().replace(/^#/, '')).filter(Boolean);
    
    currentTags = currentTags.filter(t => t.toLowerCase() !== targetClean);
    const newTagsStr = currentTags.join(', ');
    task.tags = newTagsStr;
    const ok = await updateTaskTagsOnServer(task.id, newTagsStr);
    if (ok) renderDrawerTags(task);
}

// ==========================================================================
// TEMA YÖNETİMİ (LIGHT / DARK THEME MANAGER)
// ==========================================================================
function getStoredTheme() {
    return localStorage.getItem('taskmanager_theme') || 'dark';
}

function updateThemeUI(theme) {
    const isLight = (theme === 'light');
    
    // Topbar ikonu: Açık moddaysa ay (🌙 koyu moda geç), koyu moddaysa güneş (☀️ açık moda geç)
    const topbarIcon = document.getElementById('cuThemeIcon');
    if (topbarIcon) {
        topbarIcon.textContent = isLight ? '🌙' : '☀️';
    }
    const topbarBtn = document.getElementById('cuThemeToggleBtn');
    if (topbarBtn) {
        topbarBtn.setAttribute('title', isLight ? 'Koyu Moda Geç' : 'Açık Moda Geç');
    }

    // Profil açılır menüsü içindeki buton
    const ddIcon = document.getElementById('userDropdownThemeIcon');
    const ddText = document.getElementById('userDropdownThemeText');
    const ddBadge = document.getElementById('userDropdownThemeBadge');
    if (ddIcon) ddIcon.textContent = isLight ? '🌙' : '☀️';
    if (ddText) ddText.textContent = isLight ? 'Koyu Moda Geç' : 'Açık Moda Geç';
    if (ddBadge) {
        ddBadge.textContent = isLight ? 'Açık' : 'Koyu';
        ddBadge.style.background = isLight ? 'rgba(0, 184, 148, 0.15)' : 'rgba(123, 104, 238, 0.15)';
        ddBadge.style.color = isLight ? '#00b894' : '#7B68EE';
    }
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('taskmanager_theme', theme);
    updateThemeUI(theme);
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || getStoredTheme();
    const newTheme = (currentTheme === 'light') ? 'dark' : 'light';
    applyTheme(newTheme);
    if (typeof showToast === 'function') {
        showToast(newTheme === 'light' ? '☀️ Açık mod aktif edildi' : '🌙 Koyu mod aktif edildi', 'info');
    }
}

// Sayfa yüklendiğinde UI ikonlarını senkronize et
document.addEventListener('DOMContentLoaded', () => {
    const activeTheme = getStoredTheme();
    document.documentElement.setAttribute('data-theme', activeTheme);
    updateThemeUI(activeTheme);
});

// ==========================================================================
// GÖREV EKLERİ VE RESİMLERİ (ATTACHMENTS & IMAGE VIEWER)
// ==========================================================================
let currentTaskAttachments = [];

function renderDrawerAttachments(attachments) {
    currentTaskAttachments = attachments || [];
    const countBadge = document.getElementById('drawerAttachmentCount');
    if (countBadge) countBadge.innerText = currentTaskAttachments.length;

    const container = document.getElementById('drawerAttachmentsContainer');
    if (!container) return;

    container.innerHTML = '';

    if (currentTaskAttachments.length === 0) {
        container.innerHTML = `
            <div style="grid-column: 1 / -1; padding: 14px; text-align: center; color: var(--text-muted); font-size: 11.5px; background: rgba(0,0,0,0.1); border-radius: var(--radius-sm);">
                Henüz görsel veya dosya eklenmemiş. Yukarıdaki butondan veya sürükleyerek ekleyebilirsiniz.
            </div>
        `;
        return;
    }

    currentTaskAttachments.forEach(att => {
        const card = document.createElement('div');
        card.style.cssText = 'position: relative; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow: hidden; display: flex; flex-direction: column; transition: var(--transition); box-shadow: 0 2px 6px rgba(0,0,0,0.15);';
        card.onmouseover = () => { card.style.borderColor = 'var(--brand-primary)'; card.style.transform = 'translateY(-2px)'; };
        card.onmouseout = () => { card.style.borderColor = 'var(--border-subtle)'; card.style.transform = 'translateY(0)'; };

        const isImg = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(att.file_path);
        
        let mediaHtml = '';
        if (isImg) {
            mediaHtml = `
                <div style="width: 100%; height: 95px; background: #0b0c0e; display: flex; align-items: center; justify-content: center; overflow: hidden; cursor: pointer;" onclick="openImageLightbox('${att.file_path}', '${escape(att.file_name)}')">
                    <img src="${att.file_path}" alt="${att.file_name}" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.2s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                </div>
            `;
        } else {
            mediaHtml = `
                <a href="${att.file_path}" target="_blank" style="width: 100%; height: 95px; background: rgba(123, 104, 238, 0.08); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; text-decoration: none; color: var(--brand-primary); cursor: pointer;">
                    <span style="font-size: 26px;">📄</span>
                    <span style="font-size: 10px; font-weight: 700; text-transform: uppercase;">AÇ / İNDİR</span>
                </a>
            `;
        }

        const sizeKb = att.file_size ? (Math.round(att.file_size / 1024) + ' KB') : '';

        card.innerHTML = `
            ${mediaHtml}
            <div style="padding: 6px 8px; display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.15); border-top: 1px solid var(--border-subtle);">
                <div style="overflow: hidden; flex: 1; padding-right: 4px;" title="${att.file_name}">
                    <div style="font-size: 11px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${att.file_name}</div>
                    <div style="font-size: 9.5px; color: var(--text-muted);">${sizeKb}</div>
                </div>
                <button type="button" onclick="deleteTaskAttachment(${att.id})" style="background: none; border: none; color: #ff4757; font-size: 12px; cursor: pointer; padding: 2px 4px; border-radius: 3px;" title="Dosyayı Sil" onmouseover="this.style.background='rgba(255,71,87,0.15)'" onmouseout="this.style.background='none'">
                    🗑️
                </button>
            </div>
        `;

        container.appendChild(card);
    });
}

async function uploadAttachmentFile(file) {
    if (!currentDrawerTaskId) {
        showToast('Önce bir görev seçmelisiniz', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('task_id', currentDrawerTaskId);
    formData.append('attachment', file);

    showToast('Dosya yükleniyor...', 'info');

    try {
        const res = await fetch('/taskmanager/ajax?action=upload_task_attachment', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        const attData = d.data || d.attachment || (d.id ? d : null);
        if (d.success && attData) {
            showToast('✅ Dosya yüklendi!', 'success');
            currentTaskAttachments = currentTaskAttachments.filter(a => a.id != attData.id);
            currentTaskAttachments.unshift(attData);
            renderDrawerAttachments(currentTaskAttachments);
        } else {
            showToast(d.message || 'Yükleme başarısız', 'error');
        }
    } catch(e) {
        showToast('Dosya yüklenirken bağlantı hatası oluştu', 'error');
    }
}

async function handleDrawerFileUpload(input) {
    if (!input.files || input.files.length === 0) return;
    for (let i = 0; i < input.files.length; i++) {
        await uploadAttachmentFile(input.files[i]);
    }
    input.value = '';
}

async function deleteTaskAttachment(attId) {
    if (!confirm('Bu görseli/dosyayı silmek istediğinizden emin misiniz?')) return;

    const formData = new FormData();
    formData.append('attachment_id', attId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_task_attachment', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        if (d.success) {
            showToast('Dosya silindi!', 'success');
            currentTaskAttachments = currentTaskAttachments.filter(a => a.id != attId);
            renderDrawerAttachments(currentTaskAttachments);
        } else {
            showToast(d.message || 'Silinemedi', 'error');
        }
    } catch(e) {
        showToast('Bağlantı hatası', 'error');
    }
}

// Drag & Drop Handlers for Dropzone
function handleDropzoneOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const dropzone = document.getElementById('drawerDropzone');
    if (dropzone) {
        dropzone.style.borderColor = 'var(--brand-primary)';
        dropzone.style.background = 'rgba(123, 104, 238, 0.1)';
    }
}

function handleDropzoneLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const dropzone = document.getElementById('drawerDropzone');
    if (dropzone) {
        dropzone.style.borderColor = 'var(--border-subtle)';
        dropzone.style.background = 'rgba(255,255,255,0.02)';
    }
}

async function handleDropzoneDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    handleDropzoneLeave(e);

    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
        for (let i = 0; i < files.length; i++) {
            await uploadAttachmentFile(files[i]);
        }
    }
}

// Lightbox Handlers
function openImageLightbox(src, caption) {
    const modal = document.getElementById('cuImageLightboxModal');
    const img = document.getElementById('lightboxImage');
    const cap = document.getElementById('lightboxCaption');
    const dlBtn = document.getElementById('lightboxDownloadBtn');
    if (modal && img) {
        img.src = src;
        try {
            if (cap) cap.innerText = decodeURIComponent(caption || '');
        } catch(e) {
            if (cap) cap.innerText = caption || '';
        }
        if (dlBtn) {
            dlBtn.href = src;
            dlBtn.setAttribute('download', src.split('/').pop() || 'image');
        }
        modal.classList.add('active');
    }
}

function closeImageLightbox(e) {
    if (!e || e.target.id === 'cuImageLightboxModal' || e.target.tagName === 'BUTTON') {
        const modal = document.getElementById('cuImageLightboxModal');
        if (modal) modal.classList.remove('active');
    }
}

// ESC Tuşu ile Lightbox Kapatma
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('cuImageLightboxModal');
        if (modal && modal.classList.contains('active')) {
            modal.classList.remove('active');
        }
    }
});

// Global Clipboard Paste Handler: Resim yapıştırıldığında (Ctrl+V) otomatik yönlendir!
window.addEventListener('paste', async (e) => {
    const items = (e.clipboardData || window.clipboardData)?.items;
    if (!items) return;

    let imageBlob = null;
    for (let index in items) {
        const item = items[index];
        if (item.kind === 'file') {
            const blob = item.getAsFile();
            if (blob) {
                imageBlob = blob;
                break;
            }
        }
    }
    if (!imageBlob) return;

    // 0. Yeni Görev Oluşturma Modalı Açıksa (modalNewTask)
    const modalNewTask = document.getElementById('modalNewTask');
    if (modalNewTask && modalNewTask.classList.contains('active')) {
        const ext = imageBlob.type.split('/')[1] || 'png';
        const safeFile = new File([imageBlob], `gorev_${Date.now()}.${ext}`, { type: imageBlob.type });
        
        // Eğer AI Mimarı Paneli açıksa oraya ekle
        const aiBody = document.getElementById('aiArchitectPanelBody');
        const isAiPanelOpen = aiBody && aiBody.style.display !== 'none' && aiBody.style.display !== '';
        if (isAiPanelOpen) {
            handleAiArchitectFiles([safeFile]);
            showToast('📋 Ekran görüntüsü AI Görev Mimarina eklendi!', 'info');
        } else {
            handleModalNewTaskFiles([safeFile]);
            showToast('📋 Ekran görüntüsü yeni göreve eklendi!', 'info');
        }
        return;
    }

    // 1. Görev Teslim Modalı Açıksa
    const modalSubmit = document.getElementById('modalSubmitTask');
    if (modalSubmit && modalSubmit.classList.contains('active')) {
        const ext = imageBlob.type.split('/')[1] || 'png';
        const safeFile = new File([imageBlob], `teslimat_${Date.now()}.${ext}`, { type: imageBlob.type });
        setSubmitTaskFile(safeFile);
        showToast('📋 Ekran görüntüsü teslimat formuna eklendi!', 'info');
        return;
    }

    // 2. Yorum Yazma Alanı Odaklıysa
    const commentInput = document.getElementById('drawerCommentInput');
    if (commentInput && document.activeElement === commentInput) {
        const ext = imageBlob.type.split('/')[1] || 'png';
        const safeFile = new File([imageBlob], `yorum_${Date.now()}.${ext}`, { type: imageBlob.type });
        setCommentFile(safeFile);
        showToast('📋 Ekran görüntüsü yoruma eklendi!', 'info');
        return;
    }

    // 3. Görev Çekmecesi Açıksa Doğrudan Göreve Yükle
    const drawer = document.getElementById('cuTaskDrawer');
    if (drawer && drawer.classList.contains('active') && currentDrawerTaskId) {
        const ext = imageBlob.type.split('/')[1] || 'png';
        const safeFile = new File([imageBlob], `ek_${Date.now()}.${ext}`, { type: imageBlob.type });
        await uploadAttachmentFile(safeFile);
        showToast('📋 Ekran görüntüsü panodan göreve yüklendi!', 'success');
    }
});

// ==========================================================================

// ÇEKMECE YORUM & ZAMAN ÇİZELGESİ RENDER FONKSİYONLARI
// ==========================================================================
function renderDrawerComments(comments) {
    const commentsList = document.getElementById('drawerCommentsList');
    const commentCountBadge = document.getElementById('drawerCommentCount');
    if (commentCountBadge) commentCountBadge.innerText = (comments || []).length;
    if (!commentsList) return;
    commentsList.innerHTML = '';

    if (!comments || comments.length === 0) {
        commentsList.innerHTML = '<span style="font-size:12px; color:var(--text-muted); font-style:italic;">Henüz yorum veya soru yok. İlk mesajı siz yazın!</span>';
    } else {
        comments.forEach(c => {
            const item = document.createElement('div');
            item.className = 'cu-comment-item';
            const internalBadge = c.is_internal == 1 
                ? '<span style="font-size:10px; background:rgba(230,126,34,0.18); color:#e67e22; padding:1px 5px; border-radius:3px; font-weight:700;">🔒 Ekip İçi</span>' 
                : '';
            
            let attachmentHtml = '';
            if (c.attachment_url) {
                const isImg = /\.(jpeg|jpg|png|gif|webp|svg|bmp)(\?.*)?$/i.test(c.attachment_url);
                if (isImg) {
                    attachmentHtml = `
                        <div style="margin-top:8px;">
                            <img src="${c.attachment_url}" 
                                 onclick="openImageLightbox('${c.attachment_url}', '${encodeURIComponent(c.comment || 'Yorum Eki')}')" 
                                 style="max-width:200px; max-height:140px; border-radius:6px; border:1px solid var(--border-subtle); cursor:pointer; object-fit:cover; display:block; transition:transform 0.15s ease;" 
                                 onmouseover="this.style.transform='scale(1.02)'" 
                                 onmouseout="this.style.transform='scale(1)'" 
                                 title="Büyütmek için tıklayın">
                        </div>
                    `;
                } else {
                    const fileName = c.attachment_url.split('/').pop();
                    attachmentHtml = `
                        <div style="margin-top:8px;">
                            <a href="${c.attachment_url}" target="_blank" download style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.06); border:1px solid var(--border-subtle); padding:5px 10px; border-radius:6px; color:var(--text-white); font-size:11.5px; text-decoration:none; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.12)'" onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                                <span>📎</span> <span>${fileName}</span>
                            </a>
                        </div>
                    `;
                }
            }

            const canEdit = (typeof window.CURRENT_USER_ID !== 'undefined' && (c.user_id == window.CURRENT_USER_ID || window.CURRENT_USER_ROLE === 'admin'));
            const editBtnHtml = canEdit 
                ? `<button type="button" onclick="startEditComment(${c.id})" class="cu-comment-edit-btn" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:11px; padding:2px 6px; border-radius:4px; display:inline-flex; align-items:center; gap:3px; transition:all 0.15s;" onmouseover="this.style.color='var(--brand-primary)'; this.style.background='rgba(123,104,238,0.1)';" onmouseout="this.style.color='var(--text-muted)'; this.style.background='none';" title="Yorumu Düzenle"><span>✏️</span> Düzenle</button>` 
                : '';
            const editedBadge = (c.is_edited == 1 || (c.updated_at && c.updated_at !== '0000-00-00 00:00:00')) 
                ? `<span id="commentEditedBadge_${c.id}" style="font-size:10px; color:var(--text-muted); font-style:italic;">(düzenlendi)</span>` 
                : `<span id="commentEditedBadge_${c.id}" style="display:none; font-size:10px; color:var(--text-muted); font-style:italic;">(düzenlendi)</span>`;

            item.innerHTML = `
                <div class="cu-avatar" style="width:28px; height:28px; font-size:11px; background:${c.avatar_color || '#7B68EE'}; flex-shrink:0;">
                    ${(c.user_name || 'U').charAt(0)}
                </div>
                <div class="cu-comment-bubble" style="flex:1;">
                    <div class="cu-comment-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="font-weight:600; font-size:12px; color:var(--text-white);">${escapeHtml(c.user_name || '')}</span>
                            <span style="font-size:10px; color:var(--text-muted); text-transform:uppercase;">${escapeHtml(c.user_title || c.user_role || 'Üye')}</span>
                            ${internalBadge}
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:11px; color:var(--text-muted);">${c.created_at}</span>
                            ${editedBadge}
                            ${editBtnHtml}
                        </div>
                    </div>
                    <div class="cu-comment-body">
                        <div id="commentText_${c.id}" style="word-break:break-word; white-space:pre-wrap; line-height:1.45; font-size:12.5px;">${escapeHtml(c.comment || '')}</div>
                        <div id="commentEditArea_${c.id}" style="display:none; margin-top:8px;">
                            <textarea id="commentEditText_${c.id}" class="cu-form-control" rows="2" style="font-size:12px; width:100%; box-sizing:border-box; margin-bottom:6px; resize:vertical; background:var(--bg-card); color:var(--text-primary); border:1px solid var(--border-medium); border-radius:6px; padding:6px 10px;"></textarea>
                            <div style="display:flex; justify-content:flex-end; gap:6px;">
                                <button type="button" onclick="cancelEditComment(${c.id})" style="background:transparent; border:1px solid var(--border-subtle); color:var(--text-muted); padding:3px 10px; font-size:11.5px; border-radius:4px; cursor:pointer;">Vazgeç</button>
                                <button type="button" onclick="saveEditComment(${c.id})" style="background:var(--brand-primary); border:none; color:#fff; padding:3px 12px; font-size:11.5px; font-weight:600; border-radius:4px; cursor:pointer;">Kaydet</button>
                            </div>
                        </div>
                        ${attachmentHtml}
                    </div>
                </div>
            `;
            commentsList.appendChild(item);
        });
        commentsList.scrollTop = commentsList.scrollHeight;
    }
}

// Yorum Düzenleme İşleyicileri
function startEditComment(commentId) {
    const textEl = document.getElementById(`commentText_${commentId}`);
    const editArea = document.getElementById(`commentEditArea_${commentId}`);
    const editText = document.getElementById(`commentEditText_${commentId}`);
    if (!textEl || !editArea || !editText) return;

    editText.value = textEl.innerText.trim();
    textEl.style.display = 'none';
    editArea.style.display = 'block';
    editText.focus();
}

function cancelEditComment(commentId) {
    const textEl = document.getElementById(`commentText_${commentId}`);
    const editArea = document.getElementById(`commentEditArea_${commentId}`);
    if (textEl) textEl.style.display = 'block';
    if (editArea) editArea.style.display = 'none';
}

async function saveEditComment(commentId) {
    const editText = document.getElementById(`commentEditText_${commentId}`);
    if (!editText) return;
    const newText = editText.value.trim();
    if (!newText) {
        showToast('Yorum boş olamaz!', 'warning');
        return;
    }

    const fd = new FormData();
    fd.append('comment_id', commentId);
    fd.append('comment', newText);

    try {
        const res = await fetch('/taskmanager/ajax?action=update_comment', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            showToast('Yorum başarıyla güncellendi!', 'success');
            const textEl = document.getElementById(`commentText_${commentId}`);
            if (textEl) {
                textEl.innerText = newText;
                textEl.style.display = 'block';
            }
            const editArea = document.getElementById(`commentEditArea_${commentId}`);
            if (editArea) editArea.style.display = 'none';

            // Düzenlendi rozetini göster
            const editedBadge = document.getElementById(`commentEditedBadge_${commentId}`);
            if (editedBadge) {
                editedBadge.style.display = 'inline';
            }
        } else {
            showToast(data.message || 'Yorum güncellenemedi', 'error');
        }
    } catch (err) {
        showToast('Sunucu bağlantı hatası', 'error');
    }
}

window.startEditComment = startEditComment;
window.cancelEditComment = cancelEditComment;
window.saveEditComment = saveEditComment;

function renderDrawerTimeline(activities) {
    const timeline = document.getElementById('drawerTimeline');
    if (!timeline) return;
    timeline.innerHTML = '';
    if (!activities || activities.length === 0) {
        timeline.innerHTML = '<span style="font-size:12px; color:var(--text-muted);">Henüz aktivite kaydı yok.</span>';
    } else {
        activities.forEach(act => {
            const item = document.createElement('div');
            item.className = 'cu-timeline-item';
            item.innerHTML = `
                <div style="font-weight:600; color:var(--text-primary);">${act.user_name || 'Kullanıcı'}</div>
                <div style="color:var(--text-secondary); margin-top:2px;">${act.comment || act.action_type}</div>
                <div class="cu-timeline-time">${act.created_at}</div>
            `;
            timeline.appendChild(item);
        });
    }
}

// 17. MOBİL UYUMLULUK VE RESPONSIVE ETKİLEŞİM YÖNETİCİSİ
// ==========================================================================
function toggleMobileSidebar() {
    const sidebar = document.querySelector('.cu-sidebar');
    const overlay = document.getElementById('cuSidebarOverlay');
    if (!sidebar) return;
    sidebar.classList.toggle('mobile-open');
    if (overlay) {
        overlay.classList.toggle('active', sidebar.classList.contains('mobile-open'));
    }
    document.body.classList.toggle('mobile-sidebar-active', sidebar.classList.contains('mobile-open'));
}

function closeMobileSidebar() {
    const sidebar = document.querySelector('.cu-sidebar');
    const overlay = document.getElementById('cuSidebarOverlay');
    if (sidebar) sidebar.classList.remove('mobile-open');
    if (overlay) overlay.classList.remove('active');
    document.body.classList.remove('mobile-sidebar-active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMobileSidebar();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.cu-sidebar a.cu-nav-item').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMobileSidebar();
            }
        });
    });
});

// ==========================================================================
// SIDEBAR GÖRÜNÜMLER SAĞA AÇILIR (FLYOUT) MENÜSÜ KONTROLLERİ
// ==========================================================================
let isFlyoutLockedByClick = false;
let flyoutCloseTimer = null;

function showViewsFlyout() {
    if (flyoutCloseTimer) {
        clearTimeout(flyoutCloseTimer);
        flyoutCloseTimer = null;
    }
    const menu = document.getElementById('cuViewsFlyoutMenu');
    const trigger = document.getElementById('cuViewsTriggerBtn');
    if (menu) menu.classList.add('show');
    if (trigger) trigger.classList.add('open');
}

function scheduleHideViewsFlyout() {
    if (isFlyoutLockedByClick) return; // Kullanıcı tıklayarak açtıysa fare hareketinde ASLA kapatma
    if (flyoutCloseTimer) clearTimeout(flyoutCloseTimer);
    flyoutCloseTimer = setTimeout(() => {
        closeViewsFlyout();
    }, 350);
}

function toggleViewsFlyout(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const menu = document.getElementById('cuViewsFlyoutMenu');
    if (!menu) return;

    const isOpen = menu.classList.contains('show');
    if (isOpen) {
        isFlyoutLockedByClick = false;
        closeViewsFlyout();
    } else {
        isFlyoutLockedByClick = true; // Tıklamayla açıldı, fare hareketinde kapanmaz!
        showViewsFlyout();
    }
}

function closeViewsFlyout() {
    if (flyoutCloseTimer) {
        clearTimeout(flyoutCloseTimer);
        flyoutCloseTimer = null;
    }
    isFlyoutLockedByClick = false;
    const menu = document.getElementById('cuViewsFlyoutMenu');
    const trigger = document.getElementById('cuViewsTriggerBtn');
    if (menu) menu.classList.remove('show');
    if (trigger) trigger.classList.remove('open');
}

// Mobilde Kanban Sütunlarını Akordiyon Olarak Aç/Kapa
function toggleColAccordion(headerEl) {
    if (window.innerWidth > 768) return; // Masaüstünde her zaman açık kalır
    const col = headerEl.closest('.cu-board-col');
    if (col) {
        col.classList.toggle('active');
    }
}

// Hover dinleyicileri (Hover ile açılma ve güvenli geçiş alanı)
document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.getElementById('cuViewsFlyoutWrapper');
    const menu = document.getElementById('cuViewsFlyoutMenu');
    const trigger = document.getElementById('cuViewsTriggerBtn');

    if (wrapper) {
        wrapper.addEventListener('mouseenter', () => {
            showViewsFlyout();
        });
        wrapper.addEventListener('mouseleave', () => {
            scheduleHideViewsFlyout();
        });
    }

    if (menu) {
        menu.addEventListener('mouseenter', () => {
            if (flyoutCloseTimer) clearTimeout(flyoutCloseTimer);
        });
        menu.addEventListener('mouseleave', () => {
            scheduleHideViewsFlyout();
        });
    }

    // Dışarı tıklandığında kapat
    document.addEventListener('click', function(e) {
        if (wrapper && !wrapper.contains(e.target)) {
            closeViewsFlyout();
        }
    });

    document.querySelectorAll('.cu-sidebar a.cu-nav-item').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMobileSidebar();
            }
        });
    });
});

function downloadDeliveryReportPdf(projectId) {
    const url = '/taskmanager/billing-report' + (projectId ? '?project_id=' + projectId : '');
    window.open(url, '_blank');
}
