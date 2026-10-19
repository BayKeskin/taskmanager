/**
 * AiArchitect Module Client Interactions
 */
window.AiArchitect = {
    async refineSpecification(projectId, instruction, images = [], codeFiles = []) {
        const formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('instruction', instruction);

        if (images && images.length) {
            for (let i = 0; i < images.length; i++) {
                formData.append('images[]', images[i]);
            }
        }
        if (codeFiles && codeFiles.length) {
            for (let i = 0; i < codeFiles.length; i++) {
                formData.append('code_files[]', codeFiles[i]);
            }
        }

        const res = await fetch('/taskmanager/ajax?action=refine_task_specification', {
            method: 'POST',
            body: formData
        });
        return await res.json();
    }
};

window.aiArchitectFiles = [];

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
        window.aiArchitectFiles.push(files[i]);
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
    if (index >= 0 && index < window.aiArchitectFiles.length) {
        window.aiArchitectFiles.splice(index, 1);
        renderAiArchitectFiles();
    }
}

function renderAiArchitectFiles() {
    const previewContainer = document.getElementById('aiArchitectFilesPreview');
    if (!previewContainer) return;
    previewContainer.innerHTML = '';

    const removeText = (window.AI_ARCHITECT_I18N && window.AI_ARCHITECT_I18N.ai_arch_remove_file) ? window.AI_ARCHITECT_I18N.ai_arch_remove_file : 'Kaldır';

    window.aiArchitectFiles.forEach((file, idx) => {
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
        delBtn.title = removeText;
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
    const promptReqText = (window.AI_ARCHITECT_I18N && window.AI_ARCHITECT_I18N.empty_instruction) ? window.AI_ARCHITECT_I18N.empty_instruction : 'Lütfen ne yapmak istediğinize dair kısa bir açıklama girin.';
    if (!instruction) {
        if (typeof showToast === 'function') {
            showToast(promptReqText, 'warning');
        } else {
            alert(promptReqText);
        }
        if (instEl) instEl.focus();
        return;
    }

    const projSelect = document.getElementById('modalNewTaskProjectSelect') || document.querySelector('#modalNewTask select[name="project_id"]');
    const projectId = projSelect ? projSelect.value : 1;

    const btn = document.getElementById('btnRunAiArchitect');
    const btnText = document.getElementById('btnRunAiArchitectText');
    const origText = btnText ? btnText.innerText : 'Şartname Üret';

    if (btn) btn.disabled = true;
    if (btnText) btnText.innerText = (window.AI_ARCHITECT_I18N && window.AI_ARCHITECT_I18N.analyzing_btn) ? window.AI_ARCHITECT_I18N.analyzing_btn : 'Mimar analiz ediyor...';

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('instruction', instruction);

    // Eklenen referans görseller ve kod dosyalarını ayırarak ekle
    window.aiArchitectFiles.forEach(file => {
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
            if (window.aiArchitectFiles.length > 0) {
                const imgFiles = window.aiArchitectFiles.filter(f => f.type.startsWith('image/'));
                if (imgFiles.length > 0 && typeof handleModalNewTaskFiles === 'function') {
                    handleModalNewTaskFiles(imgFiles);
                }
            }

            const successMsg = (window.AI_ARCHITECT_I18N && window.AI_ARCHITECT_I18N.spec_generated_success) ? window.AI_ARCHITECT_I18N.spec_generated_success : 'Teknik şartname üretildi!';
            if (typeof showToast === 'function') {
                showToast(successMsg, 'success');
            }
        } else {
            const failMsg = d.message || ((window.AI_ARCHITECT_I18N && window.AI_ARCHITECT_I18N.spec_generate_failed) ? window.AI_ARCHITECT_I18N.spec_generate_failed : 'Şartname üretilemedi.');
            if (typeof showToast === 'function') {
                showToast(failMsg, 'error');
            }
        }
    } catch(err) {
        const connErr = (window.AI_ARCHITECT_I18N && window.AI_ARCHITECT_I18N.conn_error_toast) ? window.AI_ARCHITECT_I18N.conn_error_toast : 'Bağlantı hatası';
        if (typeof showToast === 'function') {
            showToast(connErr, 'error');
        }
    } finally {
        if (btn) btn.disabled = false;
        if (btnText) btnText.innerText = origText;
    }
}

// Global scope export
window.toggleAiArchitectPanel = toggleAiArchitectPanel;
window.handleAiArchitectFiles = handleAiArchitectFiles;
window.removeAiArchitectFile = removeAiArchitectFile;
window.renderAiArchitectFiles = renderAiArchitectFiles;
window.handleAiArchitectDragOver = handleAiArchitectDragOver;
window.handleAiArchitectDragLeave = handleAiArchitectDragLeave;
window.handleAiArchitectDrop = handleAiArchitectDrop;
window.runAiArchitect = runAiArchitect;

/* ==========================================================================
   AI PROJE MİMARI & SÜRÜKLE-BIRAK GÖREV AĞACI (TASK DECOMPOSER)
   ========================================================================== */
let aiArchTreeData = [];
let aiArchExtractedImgs = [];
let aiArchOriginalDoc = null;
let selectedAiArchFile = null;
let draggedSubtaskRef = null; // { epicIdx, subIdx }
let allSubtaskDetailsOpen = true;

function escapeAiHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openAiArchitectModal() {
    openModal('modalAiArchitect');
    const s1 = document.getElementById('aiArchStep1');
    const s2 = document.getElementById('aiArchStep2');
    const btnApprove = document.getElementById('btnApproveAiArch');
    const badge = document.getElementById('aiArchSummaryBadge');

    if (s1) s1.style.display = 'block';
    if (s2) s2.style.display = 'none';
    if (btnApprove) btnApprove.style.display = 'none';
    if (badge) badge.innerText = '';

    // Aktif bulunulan projeyi otomatik seçili getir
    const projSelect = document.getElementById('aiArchProjectSelect');
    if (projSelect) {
        let activeId = null;
        if (window.BOARD_CONTEXT && window.BOARD_CONTEXT.projectId) {
            activeId = String(window.BOARD_CONTEXT.projectId);
        } else {
            const urlParams = new URLSearchParams(window.location.search);
            activeId = urlParams.get('project_id');
        }
        if (activeId && projSelect.querySelector(`option[value="${activeId}"]`)) {
            projSelect.value = activeId;
        }
    }
}


function handleAiArchDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('aiArchDropzone');
    if (dz) dz.classList.add('drag-active');
}

function handleAiArchDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('aiArchDropzone');
    if (dz) dz.classList.remove('drag-active');
}

function handleAiArchFileDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('aiArchDropzone');
    if (dz) dz.classList.remove('drag-active');
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        setAiArchFile(e.dataTransfer.files[0]);
    }
}

function handleAiArchFileSelect(input) {
    if (input.files && input.files.length > 0) {
        setAiArchFile(input.files[0]);
    }
}

function setAiArchFile(file) {
    selectedAiArchFile = file;
    const prompt = document.getElementById('aiArchFilePrompt');
    const sel = document.getElementById('aiArchFileSelected');
    const name = document.getElementById('aiArchFileName');
    const size = document.getElementById('aiArchFileSize');
    if (prompt) prompt.style.display = 'none';
    if (sel) sel.style.display = 'flex';
    if (name) name.innerText = file.name;
    const readyTxt = window.AI_ARCHITECT_I18N?.ready_for_analysis || 'Ready';
    if (size) size.innerText = Math.round(file.size / 1024) + ' KB • ' + readyTxt;
}

function clearAiArchFile(e) {
    if (e) e.stopPropagation();
    selectedAiArchFile = null;
    const input = document.getElementById('aiArchFileInput');
    if (input) input.value = '';
    const prompt = document.getElementById('aiArchFilePrompt');
    const sel = document.getElementById('aiArchFileSelected');
    if (prompt) prompt.style.display = 'block';
    if (sel) sel.style.display = 'none';
}

function toggleAllSubtaskDetails() {
    allSubtaskDetailsOpen = !allSubtaskDetailsOpen;
    aiArchTreeData.forEach(ep => {
        (ep.subtasks || []).forEach(st => { st.show_desc = allSubtaskDetailsOpen; });
    });
    renderAiArchitectTree();
}

async function runAiBriefDecomposition() {
    const input = document.getElementById('aiArchBriefInput');
    const brief = input ? input.value.trim() : '';
    const projSelect = document.getElementById('aiArchProjDropdown') || document.getElementById('aiArchProjectSelect');
    const projId = projSelect ? projSelect.value : 1;

    const fileInput = document.getElementById('aiArchFileInput');
    const fileToUpload = selectedAiArchFile || (fileInput && fileInput.files && fileInput.files[0]) || null;

    if (!brief && !fileToUpload) {
        showToast(window.AI_ARCHITECT_I18N?.empty_brief_error || 'Please provide brief or document.', 'error');
        return;
    }

    const btn = document.getElementById('btnRunAiDecompose');
    const origText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = window.AI_ARCHITECT_I18N?.analyzing_btn || 'Analyzing...';
    }

    try {
        const fd = new FormData();
        fd.append('brief', brief);
        fd.append('project_id', projId);
        if (fileToUpload) {
            fd.append('brief_file', fileToUpload, fileToUpload.name);
        }

        const res = await fetch('/taskmanager/ajax?action=decompose_brief_with_ai', { method: 'POST', body: fd });
        
        if (!res.ok) {
            const errTxt = await res.text().catch(() => '');
            console.error('Server error HTTP ' + res.status + ':', errTxt);
            const httpErr = (window.AI_ARCHITECT_I18N?.server_http_error || 'Server error (:status)').replace(':status', res.status);
            showToast(httpErr, 'error');
            return;
        }

        let data;
        try {
            data = await res.json();
        } catch (jsonErr) {
            console.error('Failed to parse server response as JSON:', jsonErr);
            showToast(window.AI_ARCHITECT_I18N?.invalid_json_error || 'Invalid server response.', 'error');
            return;
        }

        if (data.success && data.epics) {
            aiArchExtractedImgs = data.extracted_imgs || [];
            aiArchOriginalDoc = data.original_doc || null;

            aiArchTreeData = data.epics.map(ep => ({
                title: ep.title || window.AI_ARCHITECT_I18N?.default_module_title || 'Module',
                description: ep.description || '',
                priority: ep.priority || 'high',
                execution_mode: ep.execution_mode === 'gated' ? 'gated' : 'auto',
                subtasks: (ep.subtasks || []).map(st => ({
                    title: st.title || '',
                    description: st.description || window.AI_ARCHITECT_I18N?.default_subtask_desc || '',
                    category: st.category || 'coding',
                    priority: st.priority || 'normal',
                    time_estimate: st.time_estimate || '2h',
                    assigned_to: (window.CURRENT_USER_ID || 1),
                    is_ai: false, // Varsayılan olarak ASLA otomatik AI seçili gelmez!
                    show_desc: true // Açıklamalar ve uygulama adımları varsayılan olarak görünür gelir
                }))
            }));

            // Çıkarılan resimler varsa önizleme barını doldur
            const imgBar = document.getElementById('aiArchExtractedImagesBar');
            const imgList = document.getElementById('aiArchExtractedImagesList');
            if (aiArchExtractedImgs.length > 0 && imgBar && imgList) {
                imgBar.style.display = 'block';
                imgList.innerHTML = aiArchExtractedImgs.map((img) => `
                    <div class="cu-ai-arch-img-card">
                        <img src="${img.file_path}" onclick="window.open('${img.file_path}', '_blank')">
                        <div style="font-size:9.5px; color:var(--text-muted); padding:2px 4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeAiHtml(img.file_name)}</div>
                    </div>
                `).join('');
            } else if (imgBar) {
                imgBar.style.display = 'none';
            }

            const s1 = document.getElementById('aiArchStep1');
            const s2 = document.getElementById('aiArchStep2');
            const btnApprove = document.getElementById('btnApproveAiArch');

            if (s1) s1.style.display = 'none';
            if (s2) s2.style.display = 'block';
            if (btnApprove) btnApprove.style.display = 'inline-block';

            renderAiArchitectTree();
            showToast(window.AI_ARCHITECT_I18N?.decompose_done_toast || 'Tasks ready!', 'success');
        } else {
            showToast(data.message || window.AI_ARCHITECT_I18N?.decompose_failed_toast || 'Failed', 'error');
        }
    } catch (e) {
        console.error('runAiBriefDecomposition error:', e);
        showToast(e.message || window.AI_ARCHITECT_I18N?.conn_error_toast || 'Connection error', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    }
}

function renderAiArchitectTree() {
    const container = document.getElementById('aiArchEpicsContainer');
    if (!container) return;
    container.innerHTML = '';

    const adminName = window.AI_ARCHITECT_I18N?.default_admin_name || 'Admin';
    const teamUsers = window.ALL_TEAM_USERS || [{ id: 1, name: adminName }];
    let totalSubs = 0;
    let totalAiSubs = 0;

    aiArchTreeData.forEach((epic, eIdx) => {
        const epicBox = document.createElement('div');
        epicBox.className = 'cu-ai-arch-epic-card';
        
        // Dropzone Events (Alt görevi başka bir ana göreve sürükleyip bırakma)
        epicBox.ondragover = (ev) => {
            ev.preventDefault();
            epicBox.classList.add('drag-over');
        };
        epicBox.ondragleave = () => {
            epicBox.classList.remove('drag-over');
        };
        epicBox.ondrop = (ev) => {
            ev.preventDefault();
            epicBox.classList.remove('drag-over');
            if (!draggedSubtaskRef) return;
            const { epicIdx: fromE, subIdx: fromS } = draggedSubtaskRef;
            if (fromE === eIdx) return;

            const movedItem = aiArchTreeData[fromE].subtasks.splice(fromS, 1)[0];
            if (movedItem) {
                aiArchTreeData[eIdx].subtasks.push(movedItem);
                draggedSubtaskRef = null;
                renderAiArchitectTree();
                showToast(window.AI_ARCHITECT_I18N?.subtask_moved_toast || 'Subtask moved!', 'info');
            }
        };

        const isGated = (epic.execution_mode === 'gated');

        let subtasksHtml = '';
        (epic.subtasks || []).forEach((sub, sIdx) => {
            totalSubs++;
            if (sub.is_ai) totalAiSubs++;

            const userOptions = teamUsers.map(u => 
                `<option value="${u.id}" ${sub.assigned_to == u.id ? 'selected' : ''}>👤 ${escapeAiHtml(u.name)}</option>`
            ).join('');

            const showDesc = sub.show_desc !== false;
            const dragTitle = escapeAiHtml(window.AI_ARCHITECT_I18N?.drag_handle_title || 'Drag and drop');
            const subTitlePh = escapeAiHtml(window.AI_ARCHITECT_I18N?.ai_arch_subtask_title_ph || 'Subtask title...');
            const toggleTitle = escapeAiHtml(window.AI_ARCHITECT_I18N?.toggle_how_to_title || 'Toggle');
            const deleteSubTitle = escapeAiHtml(window.AI_ARCHITECT_I18N?.ai_arch_remove_subtask || 'Delete');
            const descPh = escapeAiHtml(window.AI_ARCHITECT_I18N?.ai_arch_subtask_details_ph || 'Details...');

            subtasksHtml += `
                <div class="cu-ai-arch-subtask-card ${sub.is_ai ? 'is-ai-active' : ''}">
                    <div draggable="true"
                         class="cu-ai-arch-subtask-row"
                         ondragstart="draggedSubtaskRef = { epicIdx: ${eIdx}, subIdx: ${sIdx} }; this.style.opacity='0.4';"
                         ondragend="this.style.opacity='1'; draggedSubtaskRef = null;">
                        <span style="color:var(--text-muted); font-size:15px; user-select:none;" title="${dragTitle}">⠿</span>
                        <input type="text" value="${escapeAiHtml(sub.title)}" oninput="aiArchTreeData[${eIdx}].subtasks[${sIdx}].title = this.value" placeholder="${subTitlePh}" style="background:transparent; border:none; color:var(--text-white); font-size:13px; font-weight:700; outline:none; width:100%;">
                        <select onchange="aiArchTreeData[${eIdx}].subtasks[${sIdx}].category = this.value" style="background:var(--bg-card); border:1px solid var(--border-subtle); color:var(--text-secondary); font-size:11px; padding:4px 6px; border-radius:4px;">
                            <option value="coding" ${sub.category === 'coding' ? 'selected' : ''}>💻 Coding</option>
                            <option value="design" ${sub.category === 'design' ? 'selected' : ''}>🎨 Design</option>
                            <option value="seo" ${sub.category === 'seo' ? 'selected' : ''}>🔍 SEO</option>
                            <option value="marketing" ${sub.category === 'marketing' ? 'selected' : ''}>📢 Marketing</option>
                            <option value="legal" ${sub.category === 'legal' ? 'selected' : ''}>⚖️ Legal</option>
                            <option value="content" ${sub.category === 'content' ? 'selected' : ''}>📝 Content</option>
                        </select>
                        <select onchange="aiArchTreeData[${eIdx}].subtasks[${sIdx}].assigned_to = parseInt(this.value)" style="background:var(--bg-card); border:1px solid var(--border-subtle); color:var(--text-secondary); font-size:11px; padding:4px 6px; border-radius:4px;">
                            ${userOptions}
                        </select>
                        <button type="button" onclick="aiArchTreeData[${eIdx}].subtasks[${sIdx}].show_desc = !aiArchTreeData[${eIdx}].subtasks[${sIdx}].show_desc; renderAiArchitectTree();" class="cu-ai-arch-btn-detail" title="${toggleTitle}">
                            📝 ${showDesc ? 'Hide' : 'Detail'}
                        </button>
                        <label class="cu-ai-arch-ai-badge ${sub.is_ai ? 'active' : ''}">
                            <input type="checkbox" ${sub.is_ai ? 'checked' : ''} onchange="aiArchTreeData[${eIdx}].subtasks[${sIdx}].is_ai = this.checked; renderAiArchitectTree();" style="display:none;">
                            <span>🤖 AI</span>
                        </label>
                        <button type="button" onclick="aiArchTreeData[${eIdx}].subtasks.splice(${sIdx}, 1); renderAiArchitectTree();" style="background:none; border:none; color:#ff4757; cursor:pointer; font-size:13px;" title="${deleteSubTitle}">🗑️</button>
                    </div>
                    <!-- NASIL YAPILACAK / TEKNİK UYGULAMA AÇIKLAMASI (DoD) -->
                    <div class="cu-ai-arch-details-box" style="display:${showDesc ? 'block' : 'none'};">
                        <textarea rows="3" oninput="aiArchTreeData[${eIdx}].subtasks[${sIdx}].description = this.value" placeholder="${descPh}" class="cu-ai-arch-details-textarea">${escapeAiHtml(sub.description || '')}</textarea>
                    </div>
                </div>
            `;
        });

        const deleteModuleTitle = escapeAiHtml(window.AI_ARCHITECT_I18N?.ai_arch_remove_module || 'Delete Module');
        const epicDescPh = escapeAiHtml(window.AI_ARCHITECT_I18N?.ai_arch_epic_desc_ph || 'Description...');
        const emptyModuleText = escapeAiHtml(window.AI_ARCHITECT_I18N?.ai_arch_empty_epic || 'Empty module.');

        epicBox.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:8px;">
                <div style="display:flex; align-items:center; gap:8px; flex:1;">
                    <span style="font-size:18px;">📂</span>
                    <input type="text" value="${escapeAiHtml(epic.title)}" oninput="aiArchTreeData[${eIdx}].title = this.value" style="background:transparent; border:1px dashed transparent; color:var(--text-white); font-size:14px; font-weight:800; width:100%; padding:2px 6px; border-radius:4px;" onfocus="this.style.borderColor='var(--brand-primary)'" onblur="this.style.borderColor='transparent'">
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <select onchange="aiArchTreeData[${eIdx}].execution_mode = this.value; renderAiArchitectTree();" style="background:${isGated ? 'rgba(230,126,34,0.18)' : 'rgba(0,184,148,0.18)'}; border:1px solid ${isGated ? '#e67e22' : '#00b894'}; color:${isGated ? '#e67e22' : '#00b894'}; font-size:11px; font-weight:700; padding:5px 8px; border-radius:6px; cursor:pointer;">
                        <option value="auto" ${!isGated ? 'selected' : ''}>🚀 Auto</option>
                        <option value="gated" ${isGated ? 'selected' : ''}>🛑 Gated</option>
                    </select>
                    <button type="button" onclick="addSubtaskToEpic(${eIdx})" style="background:rgba(123,104,238,0.15); border:1px solid rgba(123,104,238,0.4); color:#d8b4fe; font-size:11px; font-weight:700; padding:5px 10px; border-radius:5px; cursor:pointer;">+ Subtask</button>
                    <button type="button" onclick="aiArchTreeData.splice(${eIdx}, 1); renderAiArchitectTree();" style="background:rgba(255,71,87,0.12); border:1px solid rgba(255,71,87,0.3); color:#ff4757; font-size:11px; padding:5px 8px; border-radius:5px; cursor:pointer;" title="${deleteModuleTitle}">✕</button>
                </div>
            </div>
            <!-- ANA MODÜL AÇIKLAMASI -->
            <div style="margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid var(--border-subtle);">
                <input type="text" value="${escapeAiHtml(epic.description || '')}" oninput="aiArchTreeData[${eIdx}].description = this.value" placeholder="${epicDescPh}" class="cu-ai-arch-epic-desc-input">
            </div>
            <div>${subtasksHtml || `<div style="font-size:12px; color:var(--text-muted); padding:10px; text-align:center; border:1px dashed var(--border-subtle); border-radius:6px;">${emptyModuleText}</div>`}</div>
        `;
        container.appendChild(epicBox);
    });

    const imgNoteTemplate = window.AI_ARCHITECT_I18N?.img_note_badge || ' • 🖼️ <b>:count</b> images';
    const imgNote = aiArchExtractedImgs.length > 0 ? imgNoteTemplate.replace(':count', aiArchExtractedImgs.length) : '';
    const badge = document.getElementById('aiArchSummaryBadge');
    if (badge) {
        const badgeTemplate = window.AI_ARCHITECT_I18N?.tree_summary_badge || 'Total :epics Epics, :subtasks Subtasks (:ai AI):imgNote';
        badge.innerHTML = badgeTemplate
            .replace(':epics', aiArchTreeData.length)
            .replace(':subtasks', totalSubs)
            .replace(':ai', totalAiSubs)
            .replace(':imgNote', imgNote);
    }
}

function addNewBlankEpic() {
    aiArchTreeData.push({
        title: window.AI_ARCHITECT_I18N?.new_epic_default_title || 'New Epic',
        description: window.AI_ARCHITECT_I18N?.new_epic_default_desc || '',
        priority: 'normal',
        execution_mode: 'auto',
        subtasks: [
            {
                title: window.AI_ARCHITECT_I18N?.new_subtask_default_title || 'New Subtask',
                description: window.AI_ARCHITECT_I18N?.new_subtask_default_desc || '',
                category: 'coding',
                priority: 'normal',
                time_estimate: '2h',
                assigned_to: (window.CURRENT_USER_ID || 1),
                is_ai: false,
                show_desc: true
            }
        ]
    });
    renderAiArchitectTree();
}

function addSubtaskToEpic(epicIdx) {
    if (!aiArchTreeData[epicIdx]) return;
    if (!Array.isArray(aiArchTreeData[epicIdx].subtasks)) {
        aiArchTreeData[epicIdx].subtasks = [];
    }
    aiArchTreeData[epicIdx].subtasks.push({
        title: window.AI_ARCHITECT_I18N?.new_subtask_default_title || 'New Subtask',
        description: window.AI_ARCHITECT_I18N?.new_subtask_default_desc || '',
        category: 'coding',
        priority: 'normal',
        time_estimate: '2h',
        assigned_to: (window.CURRENT_USER_ID || 1),
        is_ai: false,
        show_desc: true
    });
    renderAiArchitectTree();
}

async function submitAiArchitectTree() {
    if (!aiArchTreeData || aiArchTreeData.length === 0) {
        showToast(window.AI_ARCHITECT_I18N?.no_module_to_save || 'No modules to save.', 'error');
        return;
    }

    const projSelect = document.getElementById('aiArchProjDropdown') || document.getElementById('aiArchProjectSelect');
    const projId = projSelect ? projSelect.value : 1;
    const btn = document.getElementById('btnApproveAiArch');
    const origText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = window.AI_ARCHITECT_I18N?.processing_btn || 'Processing...';
    }

    try {
        const fd = new FormData();
        fd.append('project_id', projId);
        fd.append('tree_json', JSON.stringify(aiArchTreeData));
        fd.append('extracted_imgs_json', JSON.stringify(aiArchExtractedImgs));
        fd.append('original_doc_json', JSON.stringify(aiArchOriginalDoc));

        const res = await fetch('/taskmanager/ajax?action=bulk_create_hierarchical_tasks', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modalAiArchitect');
            setTimeout(() => window.location.reload(), 700);
        } else {
            showToast(data.message || window.AI_ARCHITECT_I18N?.conn_error_toast || 'Error', 'error');
        }
    } catch (e) {
        console.error('submitAiArchitectTree error:', e);
        showToast(window.AI_ARCHITECT_I18N?.conn_error_toast || 'Connection error', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalAiArchitect');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal('modalAiArchitect');
            }
        });
    }

    const btnCloseHeader = document.getElementById('btnAiArchCloseHeader');
    if (btnCloseHeader) {
        btnCloseHeader.addEventListener('click', function() {
            closeModal('modalAiArchitect');
        });
    }

    const btnCancelModal = document.getElementById('btnAiArchCancelModal');
    if (btnCancelModal) {
        btnCancelModal.addEventListener('click', function() {
            closeModal('modalAiArchitect');
        });
    }

    const dropzone = document.getElementById('aiArchDropzone');
    const fileInput = document.getElementById('aiArchFileInput');
    if (dropzone && fileInput) {
        dropzone.addEventListener('click', function(e) {
            if (e.target.closest('#btnAiArchClearFile')) return;
            fileInput.click();
        });
        dropzone.addEventListener('dragover', handleAiArchDragOver);
        dropzone.addEventListener('dragleave', handleAiArchDragLeave);
        dropzone.addEventListener('drop', handleAiArchFileDrop);

        fileInput.addEventListener('change', function() {
            handleAiArchFileSelect(this);
        });
    }

    const btnClearFile = document.getElementById('btnAiArchClearFile');
    if (btnClearFile) {
        btnClearFile.addEventListener('click', clearAiArchFile);
    }

    const btnRun = document.getElementById('btnRunAiDecompose');
    if (btnRun) {
        btnRun.addEventListener('click', runAiBriefDecomposition);
    }

    const btnToggleDetails = document.getElementById('btnAiArchToggleAllDetails');
    if (btnToggleDetails) {
        btnToggleDetails.addEventListener('click', toggleAllSubtaskDetails);
    }

    const btnAddEpic = document.getElementById('btnAiArchAddNewEpic');
    if (btnAddEpic) {
        btnAddEpic.addEventListener('click', addNewBlankEpic);
    }

    const btnBack = document.getElementById('btnAiArchBackToStep1');
    if (btnBack) {
        btnBack.addEventListener('click', function() {
            const s1 = document.getElementById('aiArchStep1');
            const s2 = document.getElementById('aiArchStep2');
            if (s2) s2.style.display = 'none';
            if (s1) s1.style.display = 'block';
        });
    }

    const btnApprove = document.getElementById('btnApproveAiArch');
    if (btnApprove) {
        btnApprove.addEventListener('click', submitAiArchitectTree);
    }

    // New Task Modal AI Architect Paneli & Buton Dinleyicileri
    const btnTogglePanel = document.getElementById('btnToggleAiArchitectPanel');
    if (btnTogglePanel) {
        btnTogglePanel.addEventListener('click', toggleAiArchitectPanel);
    }

    const archDropzone = document.getElementById('aiArchitectDropzone');
    const archFileInput = document.getElementById('aiArchitectFileInput');
    if (archDropzone && archFileInput) {
        archDropzone.addEventListener('click', function() {
            archFileInput.click();
        });
        archDropzone.addEventListener('dragover', handleAiArchitectDragOver);
        archDropzone.addEventListener('dragleave', handleAiArchitectDragLeave);
        archDropzone.addEventListener('drop', handleAiArchitectDrop);

        archFileInput.addEventListener('change', function() {
            handleAiArchitectFiles(this.files);
        });
    }

    const btnRunArchitect = document.getElementById('btnRunAiArchitect');
    if (btnRunArchitect) {
        btnRunArchitect.addEventListener('click', runAiArchitect);
    }
});

