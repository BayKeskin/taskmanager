function toggleDocsSidebar() {
    const sb = document.getElementById('cuDocsSidebar');
    if (sb) sb.classList.toggle('mobile-open');
}

// TaskManager Docs Client-Side Controller
let isDocSaved = true;
let currentMode = 'visual'; // 'visual' | 'source'
let quill = null;
const availableIcons = ['📄', '🔑', '🚀', '🏛️', '🗄️', '🤖', '⚙️', '📝', '💡', '📑', '📊', '🛡️', '📦'];

function getDocI18n(key, fallback) {
    if (window.DOCS_I18N && window.DOCS_I18N[key]) {
        return window.DOCS_I18N[key];
    }
    return fallback || '';
}

// 1. Markdown to HTML Auto-Converter & Boşluk / Tablo Normalizasyonu
function processToHtml(rawText) {
    if (!rawText) return '<p></p>';
    
    // A) RAW METİN ÖN TEMİZLİĞİ:
    let cleaned = rawText.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    cleaned = cleaned.replace(/\n{3,}/g, '\n\n');
    cleaned = cleaned.replace(/```\s*\n{3,}/g, '```\n\n');
    cleaned = cleaned.replace(/\n\s*([-*_]\s*){3,}\n/g, '\n\n---\n\n');
    cleaned = cleaned.replace(/\n{3,}/g, '\n\n');

    // Zaten zengin HTML etiketleri içeriyorsa
    const hasHtmlTags = /<\/?(h[1-6]|p|ul|ol|li|pre|code|table|blockquote)[^>]*>/i.test(cleaned);
    const hasMarkdown = cleaned.includes('#') || cleaned.includes('**') || cleaned.includes('- ') || cleaned.includes('```') || cleaned.includes('|');
    
    let html = cleaned;
    if (hasMarkdown || !hasHtmlTags) {
        if (window.marked) {
            try {
                html = marked.parse(cleaned);
            } catch(e) {
                console.warn('Marked parse error:', e);
            }
        }
    }

    // B) HTML SONRASI NORMALİZASYON:
    html = html.replace(/<hr\s*\/?>/gi, '');
    html = html.replace(/<th\b([^>]*)>(.*?)<\/th>/gi, '<td$1><strong>$2</strong></td>');
    html = html.replace(/<\/?thead>/gi, '').replace(/<\/?tbody>/gi, '');
    html = html.replace(/(<p>\s*(<br\s*\/?>|&nbsp;|\s)*<\/p>\s*){2,}/gi, '<p><br></p>');
    html = html.replace(/(<p>\s*(<br\s*\/?>|&nbsp;|\s)*<\/p>\s*)+(?=<h[1-6])/gi, '');
    html = html.replace(/(<\/h[1-6]>)\s*(<p>\s*(<br\s*\/?>|&nbsp;|\s)*<\/p>\s*)+/gi, '$1');
    html = html.replace(/(<p>\s*(<br\s*\/?>|&nbsp;|\s)*<\/p>\s*)+(?=<pre|<table|<ul|<ol)/gi, '');
    html = html.replace(/(<\/pre>|<\/table>|<\/ul>|<\/ol>)\s*(<p>\s*(<br\s*\/?>|&nbsp;|\s)*<\/p>\s*)+(?=<h[1-6])/gi, '$1');
    
    return html;
}

// Güvenli ve Satırları Koruyan İçerik Yükleyici (dangerouslyPasteHTML)
function loadDocContent(content) {
    if (!quill) return;
    const parsedHtml = processToHtml(content || '');
    quill.setContents([]);
    quill.clipboard.dangerouslyPasteHTML(0, parsedHtml);
}

// Tam Ekran / Geniş Görünüm Toggle Fonksiyonu
function togglePaperWidth() {
    const docPaper = document.getElementById('docPaperContainer');
    const sourcePaper = document.getElementById('sourcePaperContainer');
    const btn = document.getElementById('btnToggleWidth');
    
    let isFull = false;
    if (docPaper) isFull = docPaper.classList.toggle('full-width');
    if (sourcePaper) sourcePaper.classList.toggle('full-width', isFull);
    
    if (btn) {
        btn.innerText = isFull ? getDocI18n('docs_width_standard') : getDocI18n('docs_width_wide');
        btn.classList.toggle('active', isFull);
    }
}

function markDocUnsaved() {
    isDocSaved = false;
    const status = document.getElementById('docSaveStatus');
    if (status) {
        status.innerText = '● ' + getDocI18n('docs_save_status_unsaved');
        status.style.color = '#f39c12';
    }
}

// 3. Editör Mod Değiştirme (WYSIWYG vs Kaynak Kod)
function switchEditorMode(mode) {
    if (!quill) return;
    currentMode = mode;

    const btnVisual = document.getElementById('btnModeVisual');
    const btnSource = document.getElementById('btnModeSource');
    const quillWrapper = document.getElementById('quillWrapper');
    const sourceWrapper = document.getElementById('sourceEditorWrapper');
    const textarea = document.getElementById('rawSourceTextarea');

    if (mode === 'source') {
        // Görselden Kaynak Koda Geçiş
        textarea.value = quill.root.innerHTML;
        quillWrapper.style.display = 'none';
        sourceWrapper.style.display = 'flex';

        if (btnVisual) btnVisual.classList.remove('active');
        if (btnSource) btnSource.classList.add('active');
    } else {
        // Kaynak Koddan Görsele Geçiş
        loadDocContent(textarea.value);
        sourceWrapper.style.display = 'none';
        quillWrapper.style.display = 'flex';

        if (btnSource) btnSource.classList.remove('active');
        if (btnVisual) btnVisual.classList.add('active');
    }
}

function cycleDocIcon() {
    const btn = document.getElementById('btnDocIcon');
    if (!btn) return;
    const current = btn.innerText.trim();
    let idx = availableIcons.indexOf(current);
    if (idx === -1 || idx === availableIcons.length - 1) idx = 0;
    else idx++;
    btn.innerText = availableIcons[idx];
    markDocUnsaved();
}

// 4. Belirli Dökümanı Seçme & Yükleme
async function selectDoc(docId) {
    try {
        const res = await fetch(`/taskmanager/ajax?action=get_doc&id=${docId}`);
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || getDocI18n('docs_toast_load_err'), 'error');
            return;
        }

        const doc = data.doc;
        document.getElementById('activeDocId').value = doc.id;
        document.getElementById('activeDocTitle').value = doc.title;
        document.getElementById('btnDocIcon').innerText = doc.icon || '📄';
        if (document.getElementById('activeDocProject')) {
            document.getElementById('activeDocProject').value = doc.project_id || '';
        }

        // İçeriği parse edip Quill'e yükle
        loadDocContent(doc.content || '');

        const sourceTextarea = document.getElementById('rawSourceTextarea');
        if (sourceTextarea) {
            sourceTextarea.value = doc.content || '';
        }

        // Sidebar active güncelle
        document.querySelectorAll('.cu-doc-sidebar-item').forEach(el => el.classList.remove('active'));
        const target = document.getElementById(`docItem-${doc.id}`);
        if (target) target.classList.add('active');

        // Döküman Eklerini Listele
        renderDocAttachments(data.attachments || []);

        isDocSaved = true;
        const status = document.getElementById('docSaveStatus');
        if (status) {
            status.innerText = '✓ ' + getDocI18n('docs_save_status_saved');
            status.style.color = 'var(--text-muted)';
        }
    } catch (e) {
        console.error(e);
        showToast(getDocI18n('docs_toast_conn_err'), 'error');
    }
}

// 5. Döküman Kaydetme
async function saveActiveDoc() {
    const docId = document.getElementById('activeDocId')?.value;
    const title = document.getElementById('activeDocTitle')?.value.trim();
    const icon = document.getElementById('btnDocIcon')?.innerText.trim() || '📄';
    const projectId = document.getElementById('activeDocProject')?.value || '';

    // Aktif moda göre içeriği al
    let content = '';
    if (currentMode === 'source') {
        content = document.getElementById('rawSourceTextarea')?.value || '';
    } else if (quill) {
        content = quill.root.innerHTML;
    }

    if (!docId || !title) {
        showToast(getDocI18n('docs_toast_title_req'), 'error');
        return;
    }

    const formData = new FormData();
    formData.append('id', docId);
    formData.append('title', title);
    formData.append('content', content);
    formData.append('icon', icon);
    formData.append('project_id', projectId);

    const status = document.getElementById('docSaveStatus');
    if (status) {
        status.innerText = '⏳ ' + getDocI18n('docs_save_status_saving');
        status.style.color = 'var(--brand-primary)';
    }

    try {
        const res = await fetch('/taskmanager/ajax?action=save_doc', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            isDocSaved = true;
            if (status) {
                status.innerText = '✓ ' + getDocI18n('docs_save_status_saved');
                status.style.color = '#00b894';
            }
            showToast(getDocI18n('docs_toast_save_success'), 'success');
            
            // Sidebar başlık ve ikon güncelle
            const sideTitle = document.getElementById(`docSidebarTitle-${docId}`);
            if (sideTitle) sideTitle.innerText = title;
            const sideIcon = document.getElementById(`docIconBadge-${docId}`);
            if (sideIcon) sideIcon.innerText = icon;
        } else {
            showToast(data.message || getDocI18n('docs_toast_save_fail'), 'error');
        }
    } catch (e) {
        console.error(e);
        showToast(getDocI18n('docs_toast_conn_err'), 'error');
    }
}

// 6. Yeni Döküman Oluşturma
async function openNewDocPrompt() {
    const title = window.prompt(getDocI18n('docs_create_prompt'), getDocI18n('docs_create_default'));
    if (!title) return;

    const activeProjectEl = document.getElementById('activeDocProject');
    const projId = activeProjectEl ? activeProjectEl.value : '';

    const formData = new FormData();
    formData.append('title', title);
    formData.append('icon', '📄');
    if (projId) {
        formData.append('project_id', projId);
    }

    try {
        const res = await fetch('/taskmanager/ajax?action=create_doc', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(getDocI18n('docs_toast_created'), 'success');
            window.location.reload();
        } else {
            showToast(data.message || getDocI18n('docs_toast_create_fail'), 'error');
        }
    } catch (e) {
        console.error(e);
    }
}

// 7. Döküman Silme
async function deleteActiveDoc() {
    const docId = document.getElementById('activeDocId')?.value;
    if (!docId) return;

    if (!window.confirm(getDocI18n('docs_confirm_delete'))) return;

    const formData = new FormData();
    formData.append('id', docId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_doc', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(getDocI18n('docs_toast_deleted'), 'success');
            window.location.href = '/taskmanager/docs';
        } else {
            showToast(data.message || getDocI18n('docs_toast_delete_fail'), 'error');
        }
    } catch (e) {
        console.error(e);
    }
}

// 8. Sol Panel Filtreleme
function filterDocsList(q) {
    const query = q.toLowerCase().trim();
    document.querySelectorAll('.cu-doc-sidebar-item').forEach(item => {
        const text = item.innerText.toLowerCase();
        item.style.display = text.includes(query) ? 'flex' : 'none';
    });
}

// ==========================================================================
// 10. DÖKÜMAN EKLERİ & BULUT DEPOLAMA YÖNETİMİ
// ==========================================================================
let currentDocAttachments = [];

function renderDocAttachments(attachments) {
    currentDocAttachments = attachments || [];
    const list = document.getElementById('docAttachmentsList');
    const badge = document.getElementById('docAttachmentsCount');
    if (badge) badge.innerText = currentDocAttachments.length;
    if (!list) return;

    list.innerHTML = '';
    if (currentDocAttachments.length === 0) {
        list.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:12px; color:var(--text-muted); font-size:12px; font-style:italic;">${getDocI18n('docs_attachments_empty')}</div>`;
        return;
    }

    currentDocAttachments.forEach(att => {
        const item = document.createElement('div');
        item.className = 'cu-doc-att-item';
        
        const isImg = /\.(jpg|jpeg|png|gif|webp|svg)($|\?)/i.test(att.file_path);
        const iconHtml = isImg 
            ? `<img src="${att.file_path}" alt="${att.file_name}" data-lightbox-src="${att.file_path}" data-lightbox-title="${escape(att.file_name)}" class="cu-doc-att-thumb">`
            : `<div class="cu-doc-att-icon-box">📄</div>`;

        const sizeKb = att.file_size ? Math.round(att.file_size / 1024) + ' KB' : '';

        item.innerHTML = `
            ${iconHtml}
            <div style="flex:1; overflow:hidden;">
                <div class="cu-doc-att-name" title="${att.file_name}">
                    ${att.file_name}
                </div>
                <div class="cu-doc-att-size">
                    ${sizeKb}
                </div>
            </div>
            <div class="cu-doc-att-actions">
                <a href="${att.file_path}" download target="_blank" class="cu-doc-att-dl" title="${getDocI18n('docs_attachment_download')}">⬇️</a>
                <button type="button" data-delete-att-id="${att.id}" class="cu-doc-att-del" title="${getDocI18n('docs_btn_delete')}">🗑️</button>
            </div>
        `;
        list.appendChild(item);
    });
}

async function loadCurrentDocAttachments() {
    const docId = document.getElementById('activeDocId')?.value;
    if (!docId) return;
    try {
        const res = await fetch(`/taskmanager/ajax?action=get_doc_attachments&doc_id=${docId}`);
        const data = await res.json();
        if (data.success) {
            const list = data.attachments || (Array.isArray(data.data) ? data.data : (data.data?.attachments || []));
            renderDocAttachments(list);
        }
    } catch(e) {
        console.error(e);
    }
}

function handleDocDropzoneOver(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('docDropzone');
    if (dz) {
        dz.style.borderColor = 'var(--brand-primary)';
        dz.style.background = 'rgba(123, 104, 238, 0.08)';
    }
}

function handleDocDropzoneLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    const dz = document.getElementById('docDropzone');
    if (dz) {
        dz.style.borderColor = 'var(--border-subtle)';
        dz.style.background = 'rgba(255,255,255,0.02)';
    }
}

async function handleDocDropzoneDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    handleDocDropzoneLeave(e);

    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
        for (let i = 0; i < files.length; i++) {
            await uploadDocFile(files[i]);
        }
    }
}

async function handleDocFileUpload(input) {
    if (!input.files || input.files.length === 0) return;
    for (let i = 0; i < input.files.length; i++) {
        await uploadDocFile(input.files[i]);
    }
    input.value = '';
}

async function uploadDocFile(file) {
    const docId = document.getElementById('activeDocId')?.value;
    if (!docId) {
        showToast(getDocI18n('docs_toast_select_first'), 'error');
        return;
    }

    const formData = new FormData();
    formData.append('doc_id', docId);
    formData.append('file', file);

    showToast(getDocI18n('docs_toast_uploading'), 'info');

    try {
        const res = await fetch('/taskmanager/ajax?action=upload_doc_attachment', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        const attData = d.data || d.attachment || (d.id ? d : null);
        if (d.success && attData) {
            showToast(getDocI18n('docs_toast_upload_success'), 'success');
            currentDocAttachments = currentDocAttachments.filter(a => a.id != attData.id);
            currentDocAttachments.unshift(attData);
            renderDocAttachments(currentDocAttachments);
        } else {
            showToast(d.message || getDocI18n('docs_toast_upload_fail'), 'error');
        }
    } catch(e) {
        showToast(getDocI18n('docs_toast_conn_err'), 'error');
    }
}

async function deleteDocAttachment(attId) {
    if (!window.confirm(getDocI18n('docs_confirm_delete_att'))) return;

    const formData = new FormData();
    formData.append('attachment_id', attId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_doc_attachment', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        if (d.success) {
            showToast(getDocI18n('docs_toast_att_deleted'), 'success');
            currentDocAttachments = currentDocAttachments.filter(a => a.id != attId);
            renderDocAttachments(currentDocAttachments);
        } else {
            showToast(d.message || getDocI18n('docs_toast_delete_fail'), 'error');
        }
    } catch(e) {
        showToast(getDocI18n('docs_toast_conn_err'), 'error');
    }
}

// Döküman Sayfasında Panodan (Ctrl+V) Yapıştırma Dinleyicisi
window.addEventListener('paste', async (e) => {
    const docId = document.getElementById('activeDocId')?.value;
    if (!docId) return;

    const items = (e.clipboardData || window.clipboardData)?.items;
    if (!items) return;

    for (let index in items) {
        const item = items[index];
        if (item.kind === 'file' && item.type.startsWith('image/')) {
            const blob = item.getAsFile();
            const safeFile = new File([blob], `doc_screenshot_${Date.now()}.png`, { type: blob.type });
            await uploadDocFile(safeFile);
            showToast(getDocI18n('docs_toast_paste_success'), 'success');
            break;
        }
    }
});

// Sayfa yüklendiğinde ve Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    // 2. Quill Editör Başlatma
    const editorElem = document.getElementById('quillEditor');
    if (editorElem && typeof Quill !== 'undefined') {
        quill = new Quill('#quillEditor', {
            theme: 'snow',
            modules: {
                toolbar: '#quillToolbar',
                table: true
            },
            placeholder: getDocI18n('docs_editor_placeholder')
        });

        // İlk içeriği yükle
        const rawInitial = document.getElementById('initialRawContent')?.value || '';
        loadDocContent(rawInitial);

        // Değişiklik dinleyici
        quill.on('text-change', (delta, oldDelta, source) => {
            if (source === 'user') {
                markDocUnsaved();
            }
        });
    }

    // Mobil Gezgin Butonu
    const btnToggleSidebar = document.querySelector('.cu-mobile-docs-toggle');
    if (btnToggleSidebar) {
        btnToggleSidebar.addEventListener('click', toggleDocsSidebar);
    }

    // Yeni Döküman Butonları
    document.querySelectorAll('.cu-btn-trigger-new-doc').forEach(btn => {
        btn.addEventListener('click', openNewDocPrompt);
    });

    // İkon Değiştirme
    const btnIcon = document.getElementById('btnDocIcon');
    if (btnIcon) {
        btnIcon.addEventListener('click', cycleDocIcon);
    }

    // Başlık ve Proje Değişikliği
    const titleInput = document.getElementById('activeDocTitle');
    if (titleInput) {
        titleInput.addEventListener('input', markDocUnsaved);
    }
    const projectSelect = document.getElementById('activeDocProject');
    if (projectSelect) {
        projectSelect.addEventListener('change', markDocUnsaved);
    }

    // Editör Mod Butonları
    const btnVisual = document.getElementById('btnModeVisual');
    if (btnVisual) {
        btnVisual.addEventListener('click', () => switchEditorMode('visual'));
    }
    const btnSource = document.getElementById('btnModeSource');
    if (btnSource) {
        btnSource.addEventListener('click', () => switchEditorMode('source'));
    }

    // Geniş Görünüm Butonu
    const btnWidth = document.getElementById('btnToggleWidth');
    if (btnWidth) {
        btnWidth.addEventListener('click', togglePaperWidth);
    }

    // Kaydet ve Sil Butonları
    const btnSave = document.getElementById('btnSaveActiveDoc');
    if (btnSave) {
        btnSave.addEventListener('click', saveActiveDoc);
    }
    const btnDelete = document.getElementById('btnDeleteActiveDoc');
    if (btnDelete) {
        btnDelete.addEventListener('click', deleteActiveDoc);
    }

    // Dosya Yükleme Tetikleyici Butonu
    const btnTriggerUpload = document.getElementById('btnTriggerDocUpload');
    const fileInput = document.getElementById('docFileInput');
    if (btnTriggerUpload && fileInput) {
        btnTriggerUpload.addEventListener('click', () => fileInput.click());
    }
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            handleDocFileUpload(this);
        });
    }

    // Dropzone Dinleyicileri
    const dropzone = document.getElementById('docDropzone');
    if (dropzone) {
        dropzone.addEventListener('dragover', handleDocDropzoneOver);
        dropzone.addEventListener('dragleave', handleDocDropzoneLeave);
        dropzone.addEventListener('drop', handleDocDropzoneDrop);
        dropzone.addEventListener('click', () => {
            if (fileInput) fileInput.click();
        });
    }

    // Ham Kaynak Metin Alanı Değişikliği
    const rawTextarea = document.getElementById('rawSourceTextarea');
    if (rawTextarea) {
        rawTextarea.addEventListener('input', markDocUnsaved);
    }

    // Arama Girişi
    const searchInput = document.getElementById('docSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterDocsList(this.value);
        });
    }

    // Sidebar Tıklama Delegation
    document.addEventListener('click', (e) => {
        const item = e.target.closest('[data-select-doc-id]');
        if (item) {
            const id = parseInt(item.getAttribute('data-select-doc-id'), 10);
            if (id) selectDoc(id);
            return;
        }

        const delBtn = e.target.closest('[data-delete-att-id]');
        if (delBtn) {
            const attId = parseInt(delBtn.getAttribute('data-delete-att-id'), 10);
            if (attId) deleteDocAttachment(attId);
            return;
        }

        const thumb = e.target.closest('[data-lightbox-src]');
        if (thumb && typeof openImageLightbox === 'function') {
            const src = thumb.getAttribute('data-lightbox-src');
            const title = thumb.getAttribute('data-lightbox-title') || '';
            openImageLightbox(src, title);
            return;
        }
    });

    // Ctrl + S ile Hızlı Kaydetme
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            saveActiveDoc();
        }
    });

    loadCurrentDocAttachments();
});
