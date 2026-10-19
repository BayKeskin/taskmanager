// Modules/Board/Assets/js/board.js

// 3. GÖREV DETAY ÇEKMECESİ (SLIDE-OVER TASK DRAWER)
// ==========================================================================
const _bI18n = window.BOARD_I18N || {};
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
    document.getElementById('drawerTaskTitle').innerText = _bI18n.loading || 'Loading...';
    document.getElementById('drawerDescription').innerText = _bI18n.loading_details || 'Loading details...';
    document.getElementById('drawerBtnGroup').innerHTML = `<span style="font-size:12px; color:var(--text-muted);">${_bI18n.loading || 'Loading...'}</span>`;
    document.getElementById('drawerTimeline').innerHTML = '';
    document.getElementById('drawerNotesBox').style.display = 'none';
    const devopsBox = document.getElementById('drawerDevopsContainer');
    if (devopsBox) devopsBox.innerHTML = '';
    document.getElementById('drawerCommentsList').innerHTML = `<span style="font-size:12px; color:var(--text-muted);">${_bI18n.comments_loading || 'Loading comments...'}</span>`;

    try {
        const response = await fetch(`/taskmanager/ajax?action=get_task_detail&id=${taskId}`);
        const data = await response.json();

        if (!data.success) {
            showToast(data.message || _bI18n.err_task_details || 'Failed to load task', 'error');
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
            codeEl.innerHTML = `<span onclick="openTaskDrawer(${task.parent_task.id})" style="cursor:pointer; color:var(--text-muted); text-decoration:underline; margin-right:6px;" title="${_bI18n.back_to_parent || 'Back'}: ${escapeHtml(task.parent_task.title)}">⬆️ ${escapeHtml(task.parent_task.task_code)} /</span> ${escapeHtml(task.task_code)}`;
        } else {
            codeEl.innerText = task.task_code;
        }
        document.getElementById('drawerProjectBadge').innerText = task.project_title || (i18n.drawer_project_fallback || 'Proje');
        document.getElementById('drawerProjectBadge').style.color = task.project_color || '#7B68EE';
        document.getElementById('drawerTaskTitle').innerText = task.title;
        const descEl = document.getElementById('drawerDescription');
        if (descEl) {
            descEl.innerHTML = window.cleanMojibake(task.description) || `<p style="color:var(--text-muted); font-style:italic;">${_bI18n.desc_empty_hint || 'Click to edit description...'}</p>`;
        }
        const descStatus = document.getElementById('drawerDescSaveStatus');
        if (descStatus) descStatus.innerText = _bI18n.click_to_edit || 'Click to edit';
        const descBtn = document.getElementById('btnSaveDrawerDesc');
        if (descBtn) descBtn.style.display = 'none';

        drawerAllTeamUsers = data.allUsers || (window.ALL_TEAM_USERS || []);
        renderDrawerAssignees(data.assignees || []);
        const drawerAssigneeLegacy = document.getElementById('drawerAssignee');
        if (drawerAssigneeLegacy) drawerAssigneeLegacy.innerText = task.assignee_name || (_bI18n.unassigned || 'Unassigned');
        document.getElementById('drawerDeadline').innerText = task.deadline || (_bI18n.not_specified || 'Not specified');

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
            'accepted': { text: _bI18n.status_accepted || 'ACCEPTED', color: '#00b4d8' },
            'modification_requested': { text: _bI18n.status_modification_requested || 'MODIFICATION REQUESTED', color: '#e67e22' },
            'in_progress': { text: _bI18n.status_in_progress || 'IN PROGRESS', color: '#9b5de5' },
            'submitted': { text: _bI18n.status_submitted || 'SUBMITTED', color: '#e056fd' },
            'under_review': { text: _bI18n.status_under_review || 'UNDER REVIEW', color: '#f1c40f' },
            'completed': { text: i18n.status?.completed || 'TAMAMLANDI', color: '#00b894' }
        };
        const st = statusMap[task.status] || { text: task.status, color: '#6e7681' };
        document.getElementById('drawerStatusBadge').innerHTML = `
            <span class="cu-group-pill" style="background:${st.color}; font-size:10px;">${st.text}</span>
        `;

        // Öncelik Rozeti
        const prMap = {
            'urgent': i18n.priority?.urgent || '🔴 Acil',
            'high': _bI18n.priority_high || 'HIGH',
            'normal': i18n.priority?.normal || '🔵 Normal',
            'low': _bI18n.priority_low || 'LOW'
        };
        document.getElementById('drawerPriorityBadge').innerHTML = `
            <span class="cu-priority-badge cu-priority-${task.priority}">${prMap[task.priority] || task.priority}</span>
        `;

        // Revizyon / Teslimat Notları Kutusu
        const notesBox = document.getElementById('drawerNotesBox');
        if (task.status === 'modification_requested' && modification) {
            notesBox.style.display = 'block';
            document.getElementById('drawerNotesTitle').innerText = _bI18n.notes_revision_title || 'Revision Reason:';
            document.getElementById('drawerNotesTitle').style.color = '#e67e22';
            document.getElementById('drawerNotesContent').innerText = window.cleanMojibake(modification.reason);
            document.getElementById('drawerAttachmentLink').innerHTML = '';
        } else if ((task.status === 'submitted' || task.status === 'completed') && submission) {
            notesBox.style.display = 'block';
            document.getElementById('drawerNotesTitle').innerText = _bI18n.notes_submission_title || 'Submission Note:';
            document.getElementById('drawerNotesTitle').style.color = '#9b5de5';
            document.getElementById('drawerNotesContent').innerText = window.cleanMojibake(submission.notes);
            if (submission.attachment_url) {
                document.getElementById('drawerAttachmentLink').innerHTML = `
                    <a href="${submission.attachment_url}" target="_blank" style="color:var(--brand-primary); font-size:12px; text-decoration:underline;">
                        ${_bI18n.link_open_submission || 'Open Submission Link'}
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
                const schemaDiff = submission.schema_diff_summary || (dI18n.no_db_changes || _bI18n.no_db_changes || 'No DB Schema Changes');

                devopsBox.innerHTML = `
                    <div style="background:var(--bg-main); border:1px solid var(--border-subtle); border-radius:6px; padding:12px; margin-top:10px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-size:16px;">🐙</span>
                                <strong style="font-size:12px; color:var(--text-white);">${escapeHtml(dI18n.status_title || 'GitHub & DevOps Durumu')}</strong>
                                ${shortCommit ? `<span style="font-size:11px; background:rgba(123,104,238,0.2); color:#a29bfe; padding:2px 6px; border-radius:4px; font-family:monospace;">#${shortCommit}</span>` : ''}
                            </div>
                            ${isReverted ? 
                                `<span style="font-size:11px; color:#ff7675; background:rgba(255,118,117,0.15); padding:2px 8px; border-radius:4px; font-weight:600;">${escapeHtml(dI18n.reverted || _bI18n.reverted || 'Reverted')}</span>` : 
                                `<span style="font-size:11px; color:#55efc4; background:rgba(85,239,196,0.15); padding:2px 8px; border-radius:4px; font-weight:600;">${escapeHtml(dI18n.synced || '🚀 Senkronize')}</span>`
                            }
                        </div>
                        
                        <div style="font-size:11.5px; color:var(--text-muted); margin-bottom:10px; line-height:1.4;">
                            <div style="margin-bottom:4px;"><strong>${escapeHtml(dI18n.db_impact || _bI18n.db_impact || 'Database Impact:')}</strong> ${escapeHtml(schemaDiff)}</div>
                        </div>

                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            ${repoUrl ? `
                                <a href="${repoUrl}" target="_blank" class="cu-btn-sm" style="display:inline-flex; align-items:center; gap:4px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:var(--text-white); font-size:11px; text-decoration:none; padding:4px 8px; border-radius:4px;">
                                    <span>🔗</span> ${escapeHtml(dI18n.view_diff || _bI18n.view_diff || 'View Diff')}
                                </a>
                            ` : ''}
                            ${!isReverted && submission.commit_hash ? `
                                <button type="button" class="cu-btn-sm" onclick="rollbackTask(${task.id}, 'safe')" style="display:inline-flex; align-items:center; gap:4px; background:rgba(255,71,87,0.15); border:1px solid rgba(255,71,87,0.4); color:#ff4757; font-size:11px; padding:4px 8px; border-radius:4px; cursor:pointer;" title="${escapeHtml(dI18n.btn_rollback || _bI18n.btn_rollback || 'Rollback Changes')}">
                                    <span>⏪</span> ${escapeHtml(dI18n.btn_rollback || _bI18n.btn_rollback || 'Rollback Changes')}
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
                <button class="cu-btn-accept" onclick="executeWorkflowAction(${task.id}, 'accept')">${_bI18n.btn_accept_task || 'Accept Task'}</button>
                <button class="cu-btn-modify" onclick="triggerRequestModModal(${task.id})">${_bI18n.btn_request_mod || 'Request Revision'}</button>
            `;
        }

        // 2. Kullanıcı Kabul Edilmiş / Çalışılan Görevi Teslim Eder
        if (isAssignee && (task.status === 'accepted' || task.status === 'in_progress')) {
            hasActions = true;
            btnGroup.innerHTML += `
                <button class="cu-btn-submit" onclick="triggerSubmitTaskModal(${task.id})">${_bI18n.btn_submit_task || 'Submit Task'}</button>
            `;
        }

        // 3. Admin Teslimatı İnceler: Onayla veya Düzeltme İste
        if (isAdmin && (task.status === 'submitted' || task.status === 'under_review')) {
            hasActions = true;
            btnGroup.innerHTML += `
                <button class="cu-btn-approve" onclick="executeWorkflowAction(${task.id}, 'approve')">${_bI18n.btn_approve_task || 'Approve Task'}</button>
                <button class="cu-btn-modify" onclick="executeWorkflowAction(${task.id}, 'request_changes')">${_bI18n.btn_request_changes || 'Request Changes'}</button>
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
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
            showToast(_bI18n.comment_sent || 'Comment sent', 'success');
            input.value = '';
            clearCommentFile();
            // Çekmecedeki yorumları ve ekleri anında canlı tazele (ekranı veya çekmeceyi sıfırlamadan)
            await refreshDrawerCommentsOnly(currentDrawerTaskId);
        } else {
            showToast(result.message || 'Yorum kaydedilemedi', 'error');
        }
    } catch (err) {
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
            showToast((_bI18n.task_moved || 'Task moved') + ': ' + targetStatus.toUpperCase(), 'success');
        } else {
            showToast(result.message || (_bI18n.err_status_update || 'Update failed'), 'error');
            setTimeout(() => window.location.reload(), 1000);
        }
    } catch (err) {
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
            showToast(result.message || (_bI18n.action_success || 'Success'), 'success');
            setTimeout(() => window.location.reload(), 700);
        } else {
            showToast(result.message || (_bI18n.action_failed || 'Failed'), 'error');
        }
    } catch (e) {
        showToast(_bI18n.err_request || 'Request failed', 'error');
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
        delBtn.title = _bI18n.remove || 'Remove';
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
        showToast(_bI18n.ai_prompt_required || 'Please enter instruction', 'warning');
        if (instEl) instEl.focus();
        return;
    }

    const projSelect = document.getElementById('modalNewTaskProjectSelect') || document.querySelector('#modalNewTask select[name="project_id"]');
    const projectId = projSelect ? projSelect.value : 1;

    const btn = document.getElementById('btnRunAiArchitect');
    const btnText = document.getElementById('btnRunAiArchitectText');
    const origText = btnText ? btnText.innerText : (_bI18n.ai_btn_generate || 'Generate Spec');

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

            showToast(_bI18n.ai_spec_success || 'Specification generated', 'success');
        } else {
            showToast(d.message || (_bI18n.ai_spec_failed || 'Failed to generate spec'), 'error');
        }
    } catch(err) {
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
        delBtn.title = _bI18n.remove || 'Remove';
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
            showToast(_bI18n.task_created || 'Task created', 'success');
            modalNewTaskFiles = [];
            renderModalNewTaskFiles();
            closeModal('modalNewTask');
            setTimeout(() => window.location.reload(), 700);
        } else {
            showToast(result.message || (_bI18n.err_task_create || 'Failed to create task'), 'error');
        }
    } catch (err) {
        showToast(_bI18n.server_error || 'Server error', 'error');
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
        container.innerHTML = '<span style="font-size:12px; color:var(--text-muted); font-style:italic;">' + (_bI18n.no_checklists || 'No checklist items.') + '</span>';
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
        showToast(isChecked ? (_bI18n.item_completed || 'Item completed') : (_bI18n.item_reverted || 'Item reverted'), 'info');
    } catch (err) {
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
            showToast(_bI18n.checklist_added || 'Checklist item added', 'success');
            // Çekmeceyi tazele
            openTaskDrawer(currentDrawerTaskId);
        } else {
            showToast(result.message || 'Eklenemedi', 'error');
        }
    } catch (err) {
        showToast(_bI18n.server_error || 'Server error', 'error');
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
            showToast(_bI18n.title_updated || 'Task title updated', 'success');
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
        container.innerHTML = '<span style="font-size:12px; color:var(--text-muted); font-style:italic;">' + (_bI18n.no_subtasks || 'No subtasks.') + '</span>';
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
                <input type="checkbox" ${isDone ? 'checked' : ''} onclick="event.stopPropagation(); toggleSubtaskDone(${s.id}, this.checked)" style="cursor:pointer; accent-color:var(--brand-primary); width:14px; height:14px;" title="${_bI18n.mark_done || 'Mark as done'}">
                <span style="font-weight:700; color:var(--brand-primary); font-size:11px;">${s.task_code}</span>
                <span style="color:var(--text-primary); font-weight:500; ${isDone ? 'text-decoration:line-through; opacity:0.6;' : ''}">${escapeHtml(s.title)}</span>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:9.5px; font-weight:700; text-transform:uppercase; padding:2px 6px; border-radius:3px; background:${sColor}; color:#fff;">${s.status}</span>
                <div class="cu-avatar" style="width:20px; height:20px; font-size:9px; background:${s.assignee_color || '#7B68EE'};">
                    ${(s.assignee_name || 'U').charAt(0)}
                </div>
                <button type="button" onclick="event.stopPropagation(); deleteSubtask(${s.id})" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px 4px;" title="${_bI18n.delete_subtask || 'Delete subtask'}">
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
            showToast(_bI18n.subtask_created || 'Subtask created', 'success');
            // Çekmeceyi tazele
            openTaskDrawer(currentDrawerTaskId);
        } else {
            showToast(result.message || (_bI18n.err_subtask_create || 'Failed to create subtask'), 'error');
        }
    } catch (err) {
        showToast(_bI18n.server_error || 'Server error', 'error');
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
    if (text) text.innerText = _bI18n.btn_start_timer || 'Start Timer';
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
        if (text) text.innerText = _bI18n.btn_stop_timer || 'Stop Timer';
        
        taskTimerInterval = setInterval(() => {
            taskTimerSeconds++;
            updateTimerDisplay();
        }, 1000);
        showToast(_bI18n.timer_started || 'Timer started', 'info');
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
            showToast(data.message || (_bI18n.err_save_time || 'Failed to save time'), 'error');
        }
    } catch (err) {
        console.error(err);
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
        list.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:4px 0;">' + (_bI18n.no_dependencies || 'No dependencies.') + '</div>';
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
                <button type="button" onclick="handleRemoveDependency(${d.dep_id})" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px 6px;" title="${_bI18n.remove || 'Remove'}">
                    ✕
                </button>
            `;
            list.appendChild(item);
        });
    }

    // Seçim kutusunu doldur
    if (selectTask && availableTasks) {
        selectTask.innerHTML = '<option value="">' + (_bI18n.select_task || 'Select task...') + '</option>';
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
        showToast(_bI18n.select_task_error || 'Please select task', 'error');
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
            showToast(data.message || (_bI18n.error_prefix || 'Error'), 'error');
        }
    } catch (err) {
        console.error(err);
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
            showToast(_bI18n.dependency_removed || 'Dependency removed', 'success');
            openTaskDrawer(currentDrawerTaskId);
        } else {
            showToast(data.message || (_bI18n.error_prefix || 'Error'), 'error');
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
    showToast(_bI18n.filters_reset || 'Filters reset', 'info');
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
        showToast(_bI18n.subtask_title_required || 'Subtask title required', 'error');
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
            showToast(data.message || (_bI18n.err_subtask_create || 'Failed to create subtask'), 'error');
        }
    } catch (e) {
        console.error(e);
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
        if (descEl.querySelector('p[style*="font-style:italic"]') || descEl.innerHTML.includes(_bI18n.desc_empty_hint || 'desc_empty_hint')) {
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
        status.innerText = _bI18n.unsaved_changes || 'Unsaved changes';
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
            showToast(_bI18n.desc_updated || 'Description updated', 'success');
            if (btn) btn.style.display = 'none';
            if (status) {
                status.innerText = '✓ Kaydedildi';
                status.style.color = '#00b894';
            }
        } else {
            showToast(data.message || (_bI18n.err_desc_save || 'Failed to save description'), 'error');
        }
    } catch (e) {
        console.error(e);
        showToast(_bI18n.err_conn || 'Connection error', 'error');
    }
}

// ==========================================================================
// 19. TaskManager SİLME VE PROJE YÖNETİM MOTORU (DELETION & SPACES LIFECYCLE)
// ==========================================================================

// 1. Görevi Çekmeceden Sil
async function deleteCurrentTask() {
    if (!currentDrawerTaskId) return;
    if (!confirm(_bI18n.confirm_delete_task_all || 'Delete task and all subtasks?')) return;

    await executeDeleteTask(currentDrawerTaskId);
    closeTaskDrawer();
}

// 2. Görevi Liste Görünümünden Sil
async function deleteTaskFromList(taskId) {
    if (!taskId) return;
    if (!confirm(_bI18n.confirm_delete_task || 'Delete this task?')) return;

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
            showToast(data.message || (_bI18n.err_task_delete || 'Failed to delete task'), 'error');
        }
    } catch (e) {
        console.error(e);
        showToast(_bI18n.err_conn || 'Connection error', 'error');
    }
}

// 3. Alt Görevi Sil
async function deleteSubtask(subtaskId) {
    if (!subtaskId) return;
    if (!confirm(_bI18n.confirm_delete_subtask || 'Delete subtask?')) return;

    const formData = new FormData();
    formData.append('task_id', subtaskId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_task', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(_bI18n.subtask_deleted || 'Subtask deleted', 'success');
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
            showToast(_bI18n.item_deleted || 'Item deleted', 'success');
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
        container.innerHTML = '<span style="font-size:12px; color:var(--text-muted); font-style:italic;">' + (_bI18n.no_assignee_selected || 'No assignees selected.') + '</span>';
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
            <button type="button" onclick="removeModalAssignee(${u.id})" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:0 2px; margin-left:2px; display:flex; align-items:center;" title="${_bI18n.remove || 'Remove'}" onmouseover="this.style.color='#ff4757'" onmouseout="this.style.color='var(--text-muted)'">✕</button>
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
        dropdown.innerHTML = '<div style="padding:10px 12px; font-size:12px; color:var(--text-muted);">' + (_bI18n.no_more_assignees || 'No more assignees.') + '</div>';
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
        container.innerHTML = '<span style="font-size:12px; color:var(--text-muted); font-style:italic;">' + (_bI18n.no_task_assignees || 'No assignees for task.') + '</span>';
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
            <button type="button" onclick="removeAssigneeFromDrawerTask(${u.id})" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:0 2px; margin-left:2px; display:flex; align-items:center;" title="${_bI18n.remove_assignee || 'Remove'}" onmouseover="this.style.color='#ff4757'" onmouseout="this.style.color='var(--text-muted)'">✕</button>
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
        dropdown.innerHTML = '<div style="padding:10px 12px; font-size:12px; color:var(--text-muted);">' + (_bI18n.no_more_assignees || 'No more assignees.') + '</div>';
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
            showToast(data.message || (_bI18n.err_assign || 'Failed to assign'), 'error');
        }
    } catch (e) {
        console.error(e);
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
            showToast(data.message || (_bI18n.err_unassign || 'Failed to remove'), 'error');
        }
    } catch (e) {
        console.error(e);
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
        ? '<span>🤖</span><span>' + (_bI18n.ai_autonomous_active || 'AI Active') + '</span><span style="background:rgba(0,0,0,0.3); border-radius:50%; width:16px; height:16px; display:inline-flex; align-items:center; justify-content:center; font-size:10px; margin-left:3px;" title="' + (_bI18n.remove_ai_tag || 'Remove') + '">✕</span>' 
        : '<span>🤖</span><span>+ AI Otonom Yap</span>';
    aiBtn.title = isAi ? (_bI18n.ai_title_remove || 'Click to remove AI') : (_bI18n.ai_title_enable || 'Click to enable AI');
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
        
        chip.innerHTML = `<span>#${escapeHtml(t)}</span><button type="button" class="btn-remove-tag" style="background:none; border:none; color:var(--text-muted); font-size:11px; font-weight:800; cursor:pointer; padding:0 3px; border-radius:3px; display:inline-flex; align-items:center; justify-content:center; line-height:1; transition:all 0.15s ease;" title="${_bI18n.remove_tag || 'Remove tag'}: #${escapeHtml(t)}">✕</button>`;
        
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
            showToast(_bI18n.tags_updated || 'Tags updated', 'success');
            syncBoardCardTags(taskId, newTagsStr);
            return true;
        } else {
            showToast(d.message || (_bI18n.action_failed || 'Failed'), 'error');
            return false;
        }
    } catch(e) {
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
            aiBadge.title = _bI18n.ai_queue_hint || 'Autonomous Queue: AI will process automatically';
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
        topbarBtn.setAttribute('title', isLight ? (_bI18n.switch_dark || 'Dark Mode') : (_bI18n.switch_light || 'Light Mode'));
    }

    // Profil açılır menüsü içindeki buton
    const ddIcon = document.getElementById('userDropdownThemeIcon');
    const ddText = document.getElementById('userDropdownThemeText');
    const ddBadge = document.getElementById('userDropdownThemeBadge');
    if (ddIcon) ddIcon.textContent = isLight ? '🌙' : '☀️';
    if (ddText) ddText.textContent = isLight ? (_bI18n.switch_dark || 'Dark Mode') : (_bI18n.switch_light || 'Light Mode');
    if (ddBadge) {
        ddBadge.textContent = isLight ? (_bI18n.theme_light || 'Light') : (_bI18n.theme_dark || 'Dark');
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
        showToast(newTheme === 'light' ? (_bI18n.theme_light_active || 'Light mode active') : (_bI18n.theme_dark_active || 'Dark mode active'), 'info');
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
                <button type="button" onclick="deleteTaskAttachment(${att.id})" style="background: none; border: none; color: #ff4757; font-size: 12px; cursor: pointer; padding: 2px 4px; border-radius: 3px;" title="${_bI18n.delete_file || 'Delete file'}" onmouseover="this.style.background='rgba(255,71,87,0.15)'" onmouseout="this.style.background='none'">
                    🗑️
                </button>
            </div>
        `;

        container.appendChild(card);
    });
}

async function uploadAttachmentFile(file) {
    if (!currentDrawerTaskId) {
        showToast(_bI18n.select_task_first || 'Select task first', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('task_id', currentDrawerTaskId);
    formData.append('attachment', file);

    showToast(_bI18n.file_uploading || 'Uploading file...', 'info');

    try {
        const res = await fetch('/taskmanager/ajax?action=upload_task_attachment', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        const attData = d.data || d.attachment || (d.id ? d : null);
        if (d.success && attData) {
            showToast(_bI18n.file_uploaded || 'File uploaded', 'success');
            currentTaskAttachments = currentTaskAttachments.filter(a => a.id != attData.id);
            currentTaskAttachments.unshift(attData);
            renderDrawerAttachments(currentTaskAttachments);
        } else {
            showToast(d.message || (_bI18n.err_upload || 'Upload failed'), 'error');
        }
    } catch(e) {
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
    if (!confirm(_bI18n.confirm_delete_attachment || 'Delete this attachment?')) return;

    const formData = new FormData();
    formData.append('attachment_id', attId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_task_attachment', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        if (d.success) {
            showToast(_bI18n.file_deleted || 'File deleted', 'success');
            currentTaskAttachments = currentTaskAttachments.filter(a => a.id != attId);
            renderDrawerAttachments(currentTaskAttachments);
        } else {
            showToast(d.message || 'Silinemedi', 'error');
        }
    } catch(e) {
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
            showToast(_bI18n.screenshot_added_ai || 'Screenshot added to AI', 'info');
        } else {
            handleModalNewTaskFiles([safeFile]);
            showToast(_bI18n.screenshot_added_task || 'Screenshot added to task', 'info');
        }
        return;
    }

    // 1. Görev Teslim Modalı Açıksa
    const modalSubmit = document.getElementById('modalSubmitTask');
    if (modalSubmit && modalSubmit.classList.contains('active')) {
        const ext = imageBlob.type.split('/')[1] || 'png';
        const safeFile = new File([imageBlob], `teslimat_${Date.now()}.${ext}`, { type: imageBlob.type });
        setSubmitTaskFile(safeFile);
        showToast(_bI18n.screenshot_added_sub || 'Screenshot added to submission', 'info');
        return;
    }

    // 2. Yorum Yazma Alanı Odaklıysa
    const commentInput = document.getElementById('drawerCommentInput');
    if (commentInput && document.activeElement === commentInput) {
        const ext = imageBlob.type.split('/')[1] || 'png';
        const safeFile = new File([imageBlob], `yorum_${Date.now()}.${ext}`, { type: imageBlob.type });
        setCommentFile(safeFile);
        showToast(_bI18n.screenshot_added_comment || 'Screenshot added to comment', 'info');
        return;
    }

    // 3. Görev Çekmecesi Açıksa Doğrudan Göreve Yükle
    const drawer = document.getElementById('cuTaskDrawer');
    if (drawer && drawer.classList.contains('active') && currentDrawerTaskId) {
        const ext = imageBlob.type.split('/')[1] || 'png';
        const safeFile = new File([imageBlob], `ek_${Date.now()}.${ext}`, { type: imageBlob.type });
        await uploadAttachmentFile(safeFile);
        showToast(_bI18n.screenshot_uploaded || 'Screenshot uploaded', 'success');
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
        commentsList.innerHTML = '<span style="font-size:12px; color:var(--text-muted); font-style:italic;">' + (_bI18n.no_comments_yet || 'No comments yet. Write the first one!') + '</span>';
    } else {
        comments.forEach(c => {
            const item = document.createElement('div');
            item.className = 'cu-comment-item';
            const internalBadge = c.is_internal == 1 
                ? '<span style="font-size:10px; background:rgba(230,126,34,0.18); color:#e67e22; padding:1px 5px; border-radius:3px; font-weight:700;">🔒 ' + (_bI18n.internal_team || 'Internal') + '</span>' 
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
                                 title="${_bI18n.click_to_zoom || 'Click to zoom'}">
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
                ? `<button type="button" onclick="startEditComment(${c.id})" class="cu-comment-edit-btn" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:11px; padding:2px 6px; border-radius:4px; display:inline-flex; align-items:center; gap:3px; transition:all 0.15s;" onmouseover="this.style.color='var(--brand-primary)'; this.style.background='rgba(123,104,238,0.1)';" onmouseout="this.style.color='var(--text-muted)'; this.style.background='none';" title="${_bI18n.edit_comment || 'Edit comment'}"><span>✏️</span> ${_bI18n.btn_edit || 'Edit'}</button>` 
                : '';
            const editedBadge = (c.is_edited == 1 || (c.updated_at && c.updated_at !== '0000-00-00 00:00:00')) 
                ? `<span id="commentEditedBadge_${c.id}" style="font-size:10px; color:var(--text-muted); font-style:italic;">(${_bI18n.edited || 'edited'})</span>` 
                : `<span id="commentEditedBadge_${c.id}" style="display:none; font-size:10px; color:var(--text-muted); font-style:italic;">(${_bI18n.edited || 'edited'})</span>`;

            item.innerHTML = `
                <div class="cu-avatar" style="width:28px; height:28px; font-size:11px; background:${c.avatar_color || '#7B68EE'}; flex-shrink:0;">
                    ${(c.user_name || 'U').charAt(0)}
                </div>
                <div class="cu-comment-bubble" style="flex:1;">
                    <div class="cu-comment-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="font-weight:600; font-size:12px; color:var(--text-white);">${escapeHtml(c.user_name || '')}</span>
                            <span style="font-size:10px; color:var(--text-muted); text-transform:uppercase;">${escapeHtml(c.user_title || c.user_role || (_bI18n.member || 'Member'))}</span>
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
        showToast(_bI18n.err_comment_empty || 'Comment cannot be empty', 'warning');
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
            showToast(_bI18n.comment_updated || 'Comment updated', 'success');
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
            showToast(data.message || (_bI18n.err_comment_update || 'Failed to update comment'), 'error');
        }
    } catch (err) {
        showToast(_bI18n.err_conn || 'Connection error', 'error');
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
        timeline.innerHTML = '<span style="font-size:12px; color:var(--text-muted);">' + (_bI18n.no_activity_records || 'No activity records yet.') + '</span>';
    } else {
        activities.forEach(act => {
            const item = document.createElement('div');
            item.className = 'cu-timeline-item';
            item.innerHTML = `
                <div style="font-weight:600; color:var(--text-primary);">${act.user_name || (_bI18n.user || 'User')}</div>
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
    const base = (typeof window.BASE_URL !== 'undefined') ? window.BASE_URL : '';
    const url = base + '/billing-report' + (projectId ? '?project_id=' + projectId : '');
    window.open(url, '_blank');
}

// Kanban Tools Dropdown
function toggleKanbanToolsDropdown(e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('kanbanToolsMenu');
    if (!menu) return;
    const isVisible = (menu.style.display === 'block');
    menu.style.display = isVisible ? 'none' : 'block';
}

function closeKanbanToolsDropdown() {
    const menu = document.getElementById('kanbanToolsMenu');
    if (menu) menu.style.display = 'none';
}

document.addEventListener('click', function(e) {
    const dropdownWrap = document.querySelector('.cu-tools-dropdown-wrap');
    if (dropdownWrap && !dropdownWrap.contains(e.target)) {
        closeKanbanToolsDropdown();
    }
});

// Client Workflow Actions
function clientApproveTask(taskId) {
    const i18n = window.BOARD_I18N || {};
    if (!confirm(i18n.approve_confirm || 'Onaylamak istiyor musunuz?')) return;
    const fd = new FormData();
    fd.append('action', 'task_workflow');
    fd.append('workflow_action', 'approve');
    fd.append('task_id', taskId);
    fetch('/taskmanager/ajax/task_workflow', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert(_bI18n.approve_success || 'Approved');
                location.reload();
            } else {
                alert((i18n.error_prefix || 'Hata: ') + res.message);
            }
        })
        .catch(err => alert((_bI18n.server_error || 'Server error: ') + err.message));
}

function clientRequestChanges(taskId) {
    const i18n = window.BOARD_I18N || {};
    const reason = prompt(_bI18n.request_prompt || 'Enter revision reason:');
    if (!reason || !reason.trim()) return;
    const fd = new FormData();
    fd.append('action', 'task_workflow');
    fd.append('workflow_action', 'request_changes');
    fd.append('task_id', taskId);
    fd.append('feedback', reason.trim());
    fetch('/taskmanager/ajax/task_workflow', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert(_bI18n.request_success || 'Revision requested');
                location.reload();
            } else {
                alert((i18n.error_prefix || 'Hata: ') + res.message);
            }
        })
        .catch(err => alert((_bI18n.server_error || 'Server error: ') + err.message));
}

// AI Standup
function openAiStandupModal() {
    const i18n = window.BOARD_I18N || {};
    const modal = document.getElementById('modalAiStandup');
    const content = document.getElementById('aiStandupContent');
    if (!modal || !content) return;
    openModal('modalAiStandup');
    content.innerHTML = `<div style="padding:40px; text-align:center; color:var(--text-muted);"><div style="font-size:32px; margin-bottom:12px;">🤖</div><strong>${i18n.analyzing || 'Analiz ediliyor...'}</strong></div>`;

    const projId = (window.BOARD_CONTEXT && window.BOARD_CONTEXT.projectId) ? window.BOARD_CONTEXT.projectId : '';
    fetch('/taskmanager/ajax/get_daily_standup' + (projId ? '?project_id=' + projId : ''))
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const d = (res.data || res);
                const raw = d.raw_data || {};
                const period = raw.period || d.date || (i18n.last_24h || 'Son 24 Saat');
                const compCount = raw.completed_count !== undefined ? raw.completed_count : 0;
                const inProgCount = raw.in_progress_count !== undefined ? raw.in_progress_count : 0;
                const delayedCount = raw.delayed_count !== undefined ? raw.delayed_count : 0;
                const summaryText = d.summary || '';

                let normalizedText = (summaryText || '').replace(/\r\n/g, '\n');
                let formattedSummary = escapeHtml(normalizedText)
                    .replace(/^\s*###\s*(.*$)/gim, '<div style="font-size:13px; font-weight:700; color:var(--brand-primary); margin-top:16px; margin-bottom:8px; border-bottom:1px solid var(--border-subtle); padding-bottom:4px;">$1</div>')
                    .replace(/^\s*#\s*(.*$)/gim, '<div style="font-size:15px; font-weight:800; color:var(--text-white); margin-bottom:10px;">$1</div>')
                    .replace(/\*\*(.*?)\*\*/g, '<strong style="color:var(--text-white);">$1</strong>')
                    .replace(/_(.*?)_/g, '<em style="color:var(--text-muted);">$1</em>')
                    .replace(/^\s*-\s*(.*$)/gim, '<div style="margin-left:8px; margin-bottom:6px; display:flex; align-items:flex-start; gap:8px;"><span style="color:var(--brand-primary);">•</span><span>$1</span></div>');

                const compLabel = (i18n.comp_stat || ':count Tamamlanan').replace(':count', compCount);
                const inProgLabel = (i18n.in_prog_stat || ':count Aktif').replace(':count', inProgCount);
                const delayedLabel = (i18n.delayed_stat || ':count Geciken').replace(':count', delayedCount);

                content.innerHTML = `
                    <div style="background:rgba(123,104,238,0.08); border:1px solid rgba(123,104,238,0.25); border-radius:8px; padding:16px; margin-bottom:18px;">
                        <div style="font-size:11px; font-weight:700; color:var(--brand-primary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">${_bI18n.period || 'Period'}</div>
                        <div style="font-size:13px; color:var(--text-white); font-weight:600;">📅 ${period}</div>
                        <div style="display:flex; gap:12px; margin-top:12px; flex-wrap:wrap;">
                            <div style="background:rgba(0,184,148,0.15); border:1px solid rgba(0,184,148,0.3); border-radius:6px; padding:6px 12px; font-size:12px; color:#00b894; font-weight:700;">${compLabel}</div>
                            <div style="background:rgba(155,93,229,0.15); border:1px solid rgba(155,93,229,0.3); border-radius:6px; padding:6px 12px; font-size:12px; color:#9b5de5; font-weight:700;">${inProgLabel}</div>
                            <div style="background:rgba(231,76,60,0.15); border:1px solid rgba(231,76,60,0.3); border-radius:6px; padding:6px 12px; font-size:12px; color:#e74c3c; font-weight:700;">${delayedLabel}</div>
                        </div>
                    </div>
                    <div style="background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:8px; padding:18px; font-size:13px; line-height:1.6; color:var(--text-primary); max-height:360px; overflow-y:auto;">
                        ${formattedSummary}
                    </div>
                `;
            } else {
                content.innerHTML = '<div style="padding:30px; text-align:center; color:#e74c3c;">' + (i18n.err_fetch || 'Hata: ') + (res.message || '') + '</div>';
            }
        })
        .catch(err => {
            content.innerHTML = '<div style="padding:30px; text-align:center; color:#e74c3c;">' + (_bI18n.err_conn || 'Connection error: ') + err.message + '</div>';
        });
}

function sendStandupEmail() {
    const i18n = window.BOARD_I18N || {};
    const defaultEmail = (window.BOARD_CONTEXT && window.BOARD_CONTEXT.userEmail) ? window.BOARD_CONTEXT.userEmail : '';
    const email = prompt(i18n.email_prompt || 'E-posta giriniz:', defaultEmail);
    if (!email || !email.trim()) return;

    const fd = new FormData();
    fd.append('action', 'send_standup_email');
    fd.append('email', email.trim());
    if (window.BOARD_CONTEXT && window.BOARD_CONTEXT.projectId) {
        fd.append('project_id', window.BOARD_CONTEXT.projectId);
    }

    fetch('/taskmanager/ajax/send_standup_email', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert((_bI18n.email_success || 'Sent: ') + email);
            } else {
                alert((i18n.error_prefix || 'Hata: ') + res.message);
            }
        })
        .catch(err => alert((_bI18n.server_error || 'Server error: ') + err.message));
}

// ==========================================================================
// 8. UNOBTRUSIVE EVENT LISTENERS & DELEGATION (ZERO INLINE EVENT HANDLERS)
// ==========================================================================
document.addEventListener('DOMContentLoaded', function() {
    // 1. Dinamik Renk / Background Attributelerini Uygula
    function applyDynamicColors() {
        document.querySelectorAll('[data-color]').forEach(el => {
            const color = el.getAttribute('data-color');
            if (color) {
                if (el.classList.contains('cu-col-status-dot')) {
                    el.style.backgroundColor = color;
                } else if (el.classList.contains('cu-project-avatar-box')) {
                    el.style.backgroundColor = color;
                } else {
                    el.style.color = color;
                }
            }
        });

        document.querySelectorAll('[data-bg]').forEach(el => {
            const bg = el.getAttribute('data-bg');
            if (bg) {
                el.style.backgroundColor = bg;
            }
        });
    }

    applyDynamicColors();

    // 2. Kanban Sürükle-Bırak Event Listenerlarını Bağla
    const boardCardsContainers = document.querySelectorAll('.cu-board-cards');
    boardCardsContainers.forEach(container => {
        const colStatus = container.getAttribute('data-status');

        container.addEventListener('dragover', function(e) {
            handleDragOver(e);
        });

        container.addEventListener('dragleave', function(e) {
            handleDragLeave(e);
        });

        container.addEventListener('drop', function(e) {
            handleDrop(e, colStatus);
        });
    });

    // 3. Kartlar Üzerindeki Drag & Click Listenerları
    document.querySelectorAll('.cu-board-card').forEach(card => {
        const taskId = card.getAttribute('data-task-id');
        if (!taskId) return;

        card.addEventListener('dragstart', function(e) {
            handleDragStart(e, taskId);
        });

        card.addEventListener('dragend', function(e) {
            handleDragEnd(e);
        });

        card.addEventListener('click', function(e) {
            if (e.target.closest('.cu-client-actions-bar') || e.target.closest('button')) {
                return;
            }
            if (typeof openTaskDrawer === 'function') {
                openTaskDrawer(taskId);
            }
        });
    });

    // 4. Modal Kapatma Attribute Delegasyonu ([data-close-modal])
    document.addEventListener('click', function(e) {
        const closeBtn = e.target.closest('[data-close-modal]');
        if (closeBtn) {
            const modalId = closeBtn.getAttribute('data-close-modal');
            if (modalId && typeof closeModal === 'function') {
                closeModal(modalId);
            }
        }
    });

    // Modal Arkaplanına Tıklayınca Kapatma (Overlay Click)
    document.querySelectorAll('.cu-modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay && typeof closeModal === 'function') {
                closeModal(overlay.id);
            }
        });
    });

    // 5. Araçlar (Tools) Menüsü Tetikleyicisi
    const btnKanbanTools = document.getElementById('btnKanbanTools');
    const kanbanToolsMenu = document.getElementById('kanbanToolsMenu');
    if (btnKanbanTools && kanbanToolsMenu) {
        btnKanbanTools.addEventListener('click', function(e) {
            e.stopPropagation();
            kanbanToolsMenu.classList.toggle('active');
        });

        document.addEventListener('click', function(e) {
            if (!kanbanToolsMenu.contains(e.target) && e.target !== btnKanbanTools) {
                kanbanToolsMenu.classList.remove('active');
            }
        });
    }

    // 6. Tools Menüsü Altındaki Butonlar
    const btnDownloadDeliveryPdf = document.getElementById('btnDownloadDeliveryPdf');
    if (btnDownloadDeliveryPdf) {
        btnDownloadDeliveryPdf.addEventListener('click', function() {
            if (kanbanToolsMenu) kanbanToolsMenu.classList.remove('active');
            const pId = this.getAttribute('data-project-id') || 0;
            if (typeof downloadDeliveryReportPdf === 'function') {
                downloadDeliveryReportPdf(pId);
            }
        });
    }

    const btnOpenAiStandupModal = document.getElementById('btnOpenAiStandupModal');
    if (btnOpenAiStandupModal) {
        btnOpenAiStandupModal.addEventListener('click', function() {
            if (kanbanToolsMenu) kanbanToolsMenu.classList.remove('active');
            if (typeof openAiStandupModal === 'function') {
                openAiStandupModal();
            }
        });
    }

    const btnOpenGithubSettings = document.getElementById('btnOpenGithubSettings');
    if (btnOpenGithubSettings) {
        btnOpenGithubSettings.addEventListener('click', function() {
            if (kanbanToolsMenu) kanbanToolsMenu.classList.remove('active');
            if (typeof openGithubSettingsModal === 'function') {
                openGithubSettingsModal();
            }
        });
    }

    const btnSyncGithub = document.getElementById('btnSyncGithub');
    if (btnSyncGithub) {
        btnSyncGithub.addEventListener('click', function() {
            if (kanbanToolsMenu) kanbanToolsMenu.classList.remove('active');
            const pId = this.getAttribute('data-project-id') || 0;
            if (typeof syncProjectToGithub === 'function') {
                syncProjectToGithub(pId);
            }
        });
    }

    const btnOpenDeployWizard = document.getElementById('btnOpenDeployWizard');
    if (btnOpenDeployWizard) {
        btnOpenDeployWizard.addEventListener('click', function() {
            if (kanbanToolsMenu) kanbanToolsMenu.classList.remove('active');
            const pId = this.getAttribute('data-project-id') || 0;
            if (typeof openDeployWizard === 'function') {
                openDeployWizard(pId);
            }
        });
    }

    const btnOpenProjectMcp = document.getElementById('btnOpenProjectMcp');
    if (btnOpenProjectMcp) {
        btnOpenProjectMcp.addEventListener('click', function() {
            if (kanbanToolsMenu) kanbanToolsMenu.classList.remove('active');
            const pId = this.getAttribute('data-project-id') || 0;
            if (typeof openProjectMcpModal === 'function') {
                openProjectMcpModal(pId);
            }
        });
    }

    // 7. Üst Başlık Butonları (Yeni Görev & AI Mimar)
    const btnOpenNewTaskModal = document.getElementById('btnOpenNewTaskModal');
    if (btnOpenNewTaskModal) {
        btnOpenNewTaskModal.addEventListener('click', function() {
            if (typeof openNewTaskModal === 'function') {
                openNewTaskModal();
            }
        });
    }

    const btnMobileQuickTask = document.getElementById('btnMobileQuickTask');
    if (btnMobileQuickTask) {
        btnMobileQuickTask.addEventListener('click', function() {
            if (typeof openNewTaskModal === 'function') {
                openNewTaskModal();
            }
        });
    }

    const btnOpenAiArchitectModal = document.getElementById('btnOpenAiArchitectModal');
    if (btnOpenAiArchitectModal) {
        btnOpenAiArchitectModal.addEventListener('click', function() {
            if (typeof openAiArchitectModal === 'function') {
                openAiArchitectModal();
            }
        });
    }

    // 8. Proje Yönetim Butonları (Geri Yükle, Sil, Arşive Taşı)
    const btnRestoreProj = document.querySelector('.cu-btn-restore-project');
    if (btnRestoreProj) {
        btnRestoreProj.addEventListener('click', function() {
            const pId = this.getAttribute('data-project-id');
            if (pId && typeof toggleProjectStatus === 'function') {
                toggleProjectStatus(pId, 'active');
            }
        });
    }

    const btnDeleteProj = document.querySelector('.cu-btn-delete-project');
    if (btnDeleteProj) {
        btnDeleteProj.addEventListener('click', function() {
            const pId = this.getAttribute('data-project-id');
            if (pId && typeof deleteProjectPermanently === 'function') {
                deleteProjectPermanently(pId);
            }
        });
    }

    const btnArchiveProj = document.querySelector('.cu-btn-archive-project');
    if (btnArchiveProj) {
        btnArchiveProj.addEventListener('click', function() {
            const pId = this.getAttribute('data-project-id');
            if (pId && typeof toggleProjectStatus === 'function') {
                toggleProjectStatus(pId, 'completed');
            }
        });
    }

    // 9. Kolon Akordeon Başlığı (Mobilde Daraltma/Genişletme)
    document.querySelectorAll('.cu-col-toggle-header').forEach(header => {
        header.addEventListener('click', function() {
            if (typeof toggleColAccordion === 'function') {
                toggleColAccordion(this);
            }
        });
    });

    // 10. Müşteri Portalı Onay ve Düzeltme Butonları
    document.addEventListener('click', function(e) {
        const approveBtn = e.target.closest('.cu-btn-client-approve');
        if (approveBtn) {
            e.stopPropagation();
            const taskId = approveBtn.getAttribute('data-task-id');
            if (taskId && typeof clientApproveTask === 'function') {
                clientApproveTask(taskId);
            }
        }

        const modBtn = e.target.closest('.cu-btn-client-mod');
        if (modBtn) {
            e.stopPropagation();
            const taskId = modBtn.getAttribute('data-task-id');
            if (taskId && typeof clientRequestChanges === 'function') {
                clientRequestChanges(taskId);
            }
        }
    });

    // 11. Modal Form Submit Olayları
    const formNewTask = document.getElementById('formNewTask');
    if (formNewTask) {
        formNewTask.addEventListener('submit', function(e) {
            if (typeof handleCreateTask === 'function') {
                handleCreateTask(e);
            }
        });
    }

    const formRequestMod = document.getElementById('formRequestMod');
    if (formRequestMod) {
        formRequestMod.addEventListener('submit', function(e) {
            if (typeof handleRequestMod === 'function') {
                handleRequestMod(e);
            }
        });
    }

    const formSubmitTask = document.getElementById('formSubmitTask');
    if (formSubmitTask) {
        formSubmitTask.addEventListener('submit', function(e) {
            if (typeof handleSubmitTask === 'function') {
                handleSubmitTask(e);
            }
        });
    }

    // 12. Submit Task Modal Dropzone & Önizleme
    const submitTaskDropzone = document.getElementById('submitTaskDropzone');
    const submitTaskFileInput = document.getElementById('submitTaskFileInput');
    if (submitTaskDropzone && submitTaskFileInput) {
        submitTaskDropzone.addEventListener('click', function(e) {
            if (!e.target.closest('#btnClearSubmitTaskFile')) {
                submitTaskFileInput.click();
            }
        });

        submitTaskDropzone.addEventListener('dragover', function(e) {
            if (typeof handleSubmitDropzoneOver === 'function') handleSubmitDropzoneOver(e);
        });

        submitTaskDropzone.addEventListener('dragleave', function(e) {
            if (typeof handleSubmitDropzoneLeave === 'function') handleSubmitDropzoneLeave(e);
        });

        submitTaskDropzone.addEventListener('drop', function(e) {
            if (typeof handleSubmitDropzoneDrop === 'function') handleSubmitDropzoneDrop(e);
        });

        submitTaskFileInput.addEventListener('change', function() {
            if (typeof handleSubmitFileSelect === 'function') handleSubmitFileSelect(this);
        });

        const btnClearSubmitTaskFile = document.getElementById('btnClearSubmitTaskFile');
        if (btnClearSubmitTaskFile) {
            btnClearSubmitTaskFile.addEventListener('click', function(e) {
                e.stopPropagation();
                if (typeof clearSubmitTaskFile === 'function') clearSubmitTaskFile(e);
            });
        }
    }

    // 13. New Task Modal Dropzone
    const modalNewTaskDropzone = document.getElementById('modalNewTaskDropzone');
    const modalNewTaskFileInput = document.getElementById('modalNewTaskFileInput');
    if (modalNewTaskDropzone && modalNewTaskFileInput) {
        modalNewTaskDropzone.addEventListener('click', function() {
            modalNewTaskFileInput.click();
        });

        modalNewTaskDropzone.addEventListener('dragover', function(e) {
            if (typeof handleModalNewTaskDragOver === 'function') handleModalNewTaskDragOver(e);
        });

        modalNewTaskDropzone.addEventListener('dragleave', function(e) {
            if (typeof handleModalNewTaskDragLeave === 'function') handleModalNewTaskDragLeave(e);
        });

        modalNewTaskDropzone.addEventListener('drop', function(e) {
            if (typeof handleModalNewTaskDrop === 'function') handleModalNewTaskDrop(e);
        });

        modalNewTaskFileInput.addEventListener('change', function() {
            if (typeof handleModalNewTaskFiles === 'function') handleModalNewTaskFiles(this.files);
        });
    }

    // 14. New Task Modal AI Architect Paneli & Hızlı Etiketler
    const btnToggleAiArchitectPanel = document.getElementById('btnToggleAiArchitectPanel');
    if (btnToggleAiArchitectPanel) {
        btnToggleAiArchitectPanel.addEventListener('click', function() {
            if (typeof toggleAiArchitectPanel === 'function') toggleAiArchitectPanel();
        });
    }

    const aiArchitectDropzone = document.getElementById('aiArchitectDropzone');
    const aiArchitectFileInput = document.getElementById('aiArchitectFileInput');
    if (aiArchitectDropzone && aiArchitectFileInput) {
        aiArchitectDropzone.addEventListener('click', function() {
            aiArchitectFileInput.click();
        });

        aiArchitectDropzone.addEventListener('dragover', function(e) {
            if (typeof handleAiArchitectDragOver === 'function') handleAiArchitectDragOver(e);
        });

        aiArchitectDropzone.addEventListener('dragleave', function(e) {
            if (typeof handleAiArchitectDragLeave === 'function') handleAiArchitectDragLeave(e);
        });

        aiArchitectDropzone.addEventListener('drop', function(e) {
            if (typeof handleAiArchitectDrop === 'function') handleAiArchitectDrop(e);
        });

        aiArchitectFileInput.addEventListener('change', function() {
            if (typeof handleAiArchitectFiles === 'function') handleAiArchitectFiles(this.files);
        });
    }

    const btnRunAiArchitect = document.getElementById('btnRunAiArchitect');
    if (btnRunAiArchitect) {
        btnRunAiArchitect.addEventListener('click', function() {
            if (typeof runAiArchitect === 'function') runAiArchitect();
        });
    }

    const btnToggleAiTag = document.getElementById('btnToggleAiTag');
    if (btnToggleAiTag) {
        btnToggleAiTag.addEventListener('click', function() {
            if (typeof toggleNewTaskAiTag === 'function') toggleNewTaskAiTag();
        });
    }

    document.querySelectorAll('[data-quick-tag]').forEach(btn => {
        btn.addEventListener('click', function() {
            const tag = this.getAttribute('data-quick-tag');
            if (tag && typeof appendTagToModal === 'function') {
                appendTagToModal(tag);
            }
        });
    });

    // 15. Assignee Arama Inputu (@ mention)
    const modalAssigneeInput = document.getElementById('modalAssigneeInput');
    if (modalAssigneeInput) {
        ['click', 'input', 'focus'].forEach(evName => {
            modalAssigneeInput.addEventListener(evName, function() {
                if (typeof handleModalAssigneeSearch === 'function') {
                    handleModalAssigneeSearch(this.value);
                }
            });
        });
    }

    // 16. AI Standup Modal E-Posta Butonu
    const btnSendStandupEmail = document.getElementById('btnSendStandupEmail');
    if (btnSendStandupEmail) {
        btnSendStandupEmail.addEventListener('click', function() {
            if (typeof sendStandupEmail === 'function') {
                sendStandupEmail();
            }
        });
    }

    // 17. LIST VIEW EVENT LISTENERS & DELEGATION
    document.addEventListener('click', function(e) {
        const treeToggleBtn = e.target.closest('.cu-btn-tree-toggle');
        if (treeToggleBtn) {
            e.stopPropagation();
            const parentId = treeToggleBtn.getAttribute('data-parent-id');
            if (parentId && typeof toggleSubtasksTree === 'function') {
                toggleSubtasksTree(parentId);
            }
            return;
        }

        const subtaskAddBtn = e.target.closest('.cu-btn-list-subtask');
        if (subtaskAddBtn) {
            e.stopPropagation();
            const parentId = subtaskAddBtn.getAttribute('data-parent-id');
            if (parentId && typeof expandAndFocusSubtask === 'function') {
                expandAndFocusSubtask(parentId);
            }
            return;
        }

        const detailBtn = e.target.closest('.cu-btn-list-detail');
        if (detailBtn) {
            e.stopPropagation();
            const taskId = detailBtn.getAttribute('data-task-id');
            if (taskId && typeof openTaskDrawer === 'function') {
                openTaskDrawer(taskId);
            }
            return;
        }

        const deleteBtn = e.target.closest('.cu-btn-list-delete');
        if (deleteBtn) {
            e.stopPropagation();
            const taskId = deleteBtn.getAttribute('data-task-id');
            if (taskId && typeof deleteTaskFromList === 'function') {
                deleteTaskFromList(taskId);
            }
            return;
        }

        const subtaskDeleteBtn = e.target.closest('.cu-subtask-delete-btn');
        if (subtaskDeleteBtn) {
            e.stopPropagation();
            const subtaskId = subtaskDeleteBtn.getAttribute('data-subtask-id');
            if (subtaskId && typeof deleteSubtask === 'function') {
                deleteSubtask(subtaskId);
            }
            return;
        }

        const quickSubtaskBtn = e.target.closest('.cu-quick-subtask-btn');
        if (quickSubtaskBtn) {
            e.stopPropagation();
            const parentId = quickSubtaskBtn.getAttribute('data-parent-id');
            if (parentId && typeof handleQuickAddSubtask === 'function') {
                handleQuickAddSubtask(parentId);
            }
            return;
        }

        const quickAddGroupBtn = e.target.closest('.cu-btn-quick-add-group');
        if (quickAddGroupBtn) {
            e.stopPropagation();
            if (typeof openNewTaskModal === 'function') {
                openNewTaskModal();
            }
            return;
        }

        const taskRow = e.target.closest('.cu-task-row');
        if (taskRow && !e.target.closest('button') && !e.target.closest('input')) {
            const taskId = taskRow.getAttribute('data-task-id');
            if (taskId && typeof openTaskDrawer === 'function') {
                openTaskDrawer(taskId);
            }
            return;
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const input = e.target.closest('.cu-quick-subtask-input');
            if (input) {
                e.preventDefault();
                const parentId = input.getAttribute('data-parent-id');
                if (parentId && typeof handleQuickAddSubtask === 'function') {
                    handleQuickAddSubtask(parentId);
                }
            }
        }
    });
});


