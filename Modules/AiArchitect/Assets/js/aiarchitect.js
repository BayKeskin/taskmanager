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
    if (size) size.innerText = Math.round(file.size / 1024) + ' KB • Analize hazır';
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
    const projSelect = document.getElementById('aiArchProjectSelect');
    const projId = projSelect ? projSelect.value : 1;

    const fileInput = document.getElementById('aiArchFileInput');
    const fileToUpload = selectedAiArchFile || (fileInput && fileInput.files && fileInput.files[0]) || null;

    if (!brief && !fileToUpload) {
        showToast('Lütfen önce proje brifini yazın veya bir Word (.docx) / döküman dosyası yükleyin.', 'error');
        return;
    }

    const btn = document.getElementById('btnRunAiDecompose');
    const origText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳ Döküman & Görev Adımları Analiz Ediliyor...';
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
            showToast('Sunucu hatası (HTTP ' + res.status + ')', 'error');
            return;
        }

        let data;
        try {
            data = await res.json();
        } catch (jsonErr) {
            console.error('Failed to parse server response as JSON:', jsonErr);
            showToast('Sunucu yanıtı okunamadı (Geçersiz JSON formatı).', 'error');
            return;
        }

        if (data.success && data.epics) {
            aiArchExtractedImgs = data.extracted_imgs || [];
            aiArchOriginalDoc = data.original_doc || null;

            aiArchTreeData = data.epics.map(ep => ({
                title: ep.title || 'Modül',
                description: ep.description || '',
                priority: ep.priority || 'high',
                execution_mode: ep.execution_mode === 'gated' ? 'gated' : 'auto',
                subtasks: (ep.subtasks || []).map(st => ({
                    title: st.title || '',
                    description: st.description || '🛠️ NASIL YAPILACAK:\n1. İlgili geliştirmeyi tamamla ve canlı ortamda test et.',
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
            showToast('✨ Görev ağacı, uygulama adımları ve döküman görselleri hazır!', 'success');
        } else {
            showToast(data.message || 'Ayrıştırma başarısız oldu', 'error');
        }
    } catch (e) {
        console.error('runAiBriefDecomposition error:', e);
        showToast(e.message || 'Sunucu bağlantı hatası', 'error');
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

    const teamUsers = window.ALL_TEAM_USERS || [{ id: 1, name: 'Yönetici' }];
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
                showToast('Alt görev yeni modüle taşındı!', 'info');
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

            subtasksHtml += `
                <div class="cu-ai-arch-subtask-card ${sub.is_ai ? 'is-ai-active' : ''}">
                    <div draggable="true"
                         class="cu-ai-arch-subtask-row"
                         ondragstart="draggedSubtaskRef = { epicIdx: ${eIdx}, subIdx: ${sIdx} }; this.style.opacity='0.4';"
                         ondragend="this.style.opacity='1'; draggedSubtaskRef = null;">
                        <span style="color:var(--text-muted); font-size:15px; user-select:none;" title="Sürükle ve başka modüle bırak">⠿</span>
                        <input type="text" value="${escapeAiHtml(sub.title)}" oninput="aiArchTreeData[${eIdx}].subtasks[${sIdx}].title = this.value" placeholder="Alt görev başlığı..." style="background:transparent; border:none; color:var(--text-white); font-size:13px; font-weight:700; outline:none; width:100%;">
                        <select onchange="aiArchTreeData[${eIdx}].subtasks[${sIdx}].category = this.value" style="background:var(--bg-card); border:1px solid var(--border-subtle); color:var(--text-secondary); font-size:11px; padding:4px 6px; border-radius:4px;">
                            <option value="coding" ${sub.category === 'coding' ? 'selected' : ''}>💻 Yazılım</option>
                            <option value="design" ${sub.category === 'design' ? 'selected' : ''}>🎨 Tasarım</option>
                            <option value="seo" ${sub.category === 'seo' ? 'selected' : ''}>🔍 SEO</option>
                            <option value="marketing" ${sub.category === 'marketing' ? 'selected' : ''}>📢 Pazarlama</option>
                            <option value="legal" ${sub.category === 'legal' ? 'selected' : ''}>⚖️ Yasal/KVKK</option>
                            <option value="content" ${sub.category === 'content' ? 'selected' : ''}>📝 İçerik</option>
                        </select>
                        <select onchange="aiArchTreeData[${eIdx}].subtasks[${sIdx}].assigned_to = parseInt(this.value)" style="background:var(--bg-card); border:1px solid var(--border-subtle); color:var(--text-secondary); font-size:11px; padding:4px 6px; border-radius:4px;">
                            ${userOptions}
                        </select>
                        <button type="button" onclick="aiArchTreeData[${eIdx}].subtasks[${sIdx}].show_desc = !aiArchTreeData[${eIdx}].subtasks[${sIdx}].show_desc; renderAiArchitectTree();" class="cu-ai-arch-btn-detail" title="Nasıl Yapılacak Açıklamasını Göster/Gizle">
                            📝 ${showDesc ? 'Gizle' : 'Detay'}
                        </button>
                        <label class="cu-ai-arch-ai-badge ${sub.is_ai ? 'active' : ''}">
                            <input type="checkbox" ${sub.is_ai ? 'checked' : ''} onchange="aiArchTreeData[${eIdx}].subtasks[${sIdx}].is_ai = this.checked; renderAiArchitectTree();" style="display:none;">
                            <span>🤖 AI</span>
                        </label>
                        <button type="button" onclick="aiArchTreeData[${eIdx}].subtasks.splice(${sIdx}, 1); renderAiArchitectTree();" style="background:none; border:none; color:#ff4757; cursor:pointer; font-size:13px;" title="Alt Görevi Sil">🗑️</button>
                    </div>
                    <!-- NASIL YAPILACAK / TEKNİK UYGULAMA AÇIKLAMASI (DoD) -->
                    <div class="cu-ai-arch-details-box" style="display:${showDesc ? 'block' : 'none'};">
                        <div style="font-size:11px; font-weight:700; color:#c4b5fd; margin-bottom:4px;">🛠️ Nasıl Yapılacak? (Uygulama Adımları, Teknik Detaylar & Kabul Kriterleri):</div>
                        <textarea rows="3" oninput="aiArchTreeData[${eIdx}].subtasks[${sIdx}].description = this.value" placeholder="Bu alt görevin nasıl yapılacağını, teknik gereksinimlerini ve kabul kriterlerini buraya yazın..." class="cu-ai-arch-details-textarea">${escapeAiHtml(sub.description || '')}</textarea>
                    </div>
                </div>
            `;
        });

        epicBox.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:8px;">
                <div style="display:flex; align-items:center; gap:8px; flex:1;">
                    <span style="font-size:18px;">📂</span>
                    <input type="text" value="${escapeAiHtml(epic.title)}" oninput="aiArchTreeData[${eIdx}].title = this.value" style="background:transparent; border:1px dashed transparent; color:var(--text-white); font-size:14px; font-weight:800; width:100%; padding:2px 6px; border-radius:4px;" onfocus="this.style.borderColor='var(--brand-primary)'" onblur="this.style.borderColor='transparent'">
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <select onchange="aiArchTreeData[${eIdx}].execution_mode = this.value; renderAiArchitectTree();" style="background:${isGated ? 'rgba(230,126,34,0.18)' : 'rgba(0,184,148,0.18)'}; border:1px solid ${isGated ? '#e67e22' : '#00b894'}; color:${isGated ? '#e67e22' : '#00b894'}; font-size:11px; font-weight:700; padding:5px 8px; border-radius:6px; cursor:pointer;">
                        <option value="auto" ${!isGated ? 'selected' : ''}>🚀 Tam Otonom Akış (Gece Modu)</option>
                        <option value="gated" ${isGated ? 'selected' : ''}>🛑 Onay Kapılı (Adım Adım Onaylı)</option>
                    </select>
                    <button type="button" onclick="addSubtaskToEpic(${eIdx})" style="background:rgba(123,104,238,0.15); border:1px solid rgba(123,104,238,0.4); color:#d8b4fe; font-size:11px; font-weight:700; padding:5px 10px; border-radius:5px; cursor:pointer;">+ Alt Görev</button>
                    <button type="button" onclick="aiArchTreeData.splice(${eIdx}, 1); renderAiArchitectTree();" style="background:rgba(255,71,87,0.12); border:1px solid rgba(255,71,87,0.3); color:#ff4757; font-size:11px; padding:5px 8px; border-radius:5px; cursor:pointer;" title="Modülü Sil">✕</button>
                </div>
            </div>
            <!-- ANA MODÜL AÇIKLAMASI -->
            <div style="margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid var(--border-subtle);">
                <input type="text" value="${escapeAiHtml(epic.description || '')}" oninput="aiArchTreeData[${eIdx}].description = this.value" placeholder="Ana modülün genel amacı, mimari kapsamı ve açıklaması..." class="cu-ai-arch-epic-desc-input">
            </div>
            <div>${subtasksHtml || '<div style="font-size:12px; color:var(--text-muted); padding:10px; text-align:center; border:1px dashed var(--border-subtle); border-radius:6px;">Bu modülde alt görev yok. Buraya alt görev sürükleyebilir veya "+ Alt Görev" butonuna basabilirsiniz.</div>'}</div>
        `;
        container.appendChild(epicBox);
    });

    const imgNote = aiArchExtractedImgs.length > 0 ? ` • 🖼️ <b>${aiArchExtractedImgs.length} Döküman Görseli</b> eklenecek` : '';
    const badge = document.getElementById('aiArchSummaryBadge');
    if (badge) {
        badge.innerHTML = `📊 Toplam <b>${aiArchTreeData.length} Ana Modül</b>, <b>${totalSubs} Detaylı Alt Görev</b> (<b>${totalAiSubs} tanesi 🤖 AI Otonom</b>)${imgNote}`;
    }
}

function addNewBlankEpic() {
    aiArchTreeData.push({
        title: 'Yeni Modül / Ana Görev',
        description: 'Bu modül kapsamında yapılacak mimari geliştirmeler.',
        priority: 'normal',
        execution_mode: 'auto',
        subtasks: [
            {
                title: 'Yeni alt görev 1',
                description: '🛠️ NASIL YAPILACAK (Uygulama Adımları):\n1. Gerekli altyapıyı kodla.\n\n✅ KABUL KRİTERLERİ:\n- Canlı ortamda test edilmelidir.',
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
        title: 'Yeni alt görev adımı',
        description: '🛠️ NASIL YAPILACAK (Uygulama Adımları):\n1. Gerekli altyapıyı kodla.\n\n✅ KABUL KRİTERLERİ:\n- Canlı ortamda test edilmelidir.',
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
        showToast('Kaydedilecek modül bulunamadı.', 'error');
        return;
    }

    const projSelect = document.getElementById('aiArchProjectSelect');
    const projId = projSelect ? projSelect.value : 1;
    const btn = document.getElementById('btnApproveAiArch');
    const origText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳ Projeye İşleniyor...';
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
            showToast(data.message || 'Kaydedilemedi', 'error');
        }
    } catch (e) {
        console.error('submitAiArchitectTree error:', e);
        showToast('Sunucu bağlantı hatası', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    }
}
