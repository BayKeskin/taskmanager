function toggleDocsSidebar() {
    const sb = document.getElementById('cuDocsSidebar');
    if (sb) sb.classList.toggle('mobile-open');
}

// TaskManager Docs Client-Side Controller
let isDocSaved = true;
let currentMode = 'visual'; // 'visual' | 'source'
let quill = null;
const availableIcons = ['📄', '🔑', '🚀', '🏛️', '🗄️', '🤖', '⚙️', '📝', '💡', '📑', '📊', '🛡️', '📦'];

// 1. Markdown to HTML Auto-Converter & Boşluk / Tablo Normalizasyonu
function processToHtml(rawText) {
    if (!rawText) return '<p></p>';
    
    // A) RAW METİN ÖN TEMİZLİĞİ (Yapay zeka dökümanlarındaki aşırı boşlukları ve ayraçları sıkılaştır):
    // 1. Satır sonlarını standardize et
    let cleaned = rawText.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    
    // 2. 3 veya daha fazla ardışık boş satırı en fazla 2 satıra indir
    cleaned = cleaned.replace(/\n{3,}/g, '\n\n');
    
    // 3. Kod bloklarının bitimindeki gereksiz boşlukları temizle
    cleaned = cleaned.replace(/```\s*\n{3,}/g, '```\n\n');
    
    // 4. Ayraç çizgilerini (---, ***, ___) ve çevresindeki aşırı boşlukları sıkılaştır
    cleaned = cleaned.replace(/\n\s*([-*_]\s*){3,}\n/g, '\n\n---\n\n');
    cleaned = cleaned.replace(/\n{3,}/g, '\n\n');

    // Zaten zengin HTML etiketleri içeriyorsa
    const hasHtmlTags = /<\/?(h[1-6]|p|ul|ol|li|pre|code|table|blockquote)[^>]*>/i.test(cleaned);
    
    // Markdown desenleri (#, **, -, `, |, [) içeriyorsa marked.js ile ayrıştır
    const hasMarkdown = cleaned.includes('#') || cleaned.includes('**') || cleaned.includes('- ') || cleaned.includes('```') || cleaned.includes('|');
    
    let html = cleaned;
    if (hasMarkdown || !hasHtmlTags) {
        if (window.marked) {
            try {
                html = marked.parse(cleaned);
            } catch(e) {
                console.warn('Marked parse hatası:', e);
            }
        }
    }

    // B) HTML SONRASI NORMALİZASYON:
    // 1) Quill'de blot karşılığı olmayan ve boşluk üreten <hr> etiketlerini kaldır
    html = html.replace(/<hr\s*\/?>/gi, '');

    // 2) TABLO NORMALİZASYONU (Quill 2.0 için):
    // <th> etiketlerini <td><strong>...</strong></td> formatına çevir ki Quill header hücrelerini eksiltmesin
    html = html.replace(/<th\b([^>]*)>(.*?)<\/th>/gi, '<td$1><strong>$2</strong></td>');
    // <thead> ve <tbody> etiketlerini temizle ki tüm satırlar tek ve kesintisiz tablo olarak Quill'e aktarılsın
    html = html.replace(/<\/?thead>/gi, '').replace(/<\/?tbody>/gi, '');

    // 3) BOŞLUK VE PARAGRAF SIKIŞTIRMA:
    // Ardışık boş paragrafları tek boş paragrafa indir
    html = html.replace(/(<p>\s*(<br\s*\/?>|&nbsp;|\s)*<\/p>\s*){2,}/gi, '<p><br></p>');
    
    // Başlıkların (h1-h6) hemen öncesindeki veya sonrasındaki boş paragrafları temizle
    html = html.replace(/(<p>\s*(<br\s*\/?>|&nbsp;|\s)*<\/p>\s*)+(?=<h[1-6])/gi, '');
    html = html.replace(/(<\/h[1-6]>)\s*(<p>\s*(<br\s*\/?>|&nbsp;|\s)*<\/p>\s*)+/gi, '$1');
    
    // Kod bloklarının (pre) hemen öncesindeki veya sonrasındaki boş paragrafları temizle
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
        btn.innerText = isFull ? <?= json_encode(__('Docs::docs_width_standard', '📄 Standart Genişlik')) ?> : <?= json_encode(__('Docs::docs_width_wide', '↔️ Geniş Görünüm')) ?>;
        btn.classList.toggle('active', isFull);
    }
}

// 2. Quill Editör Başlatma
document.addEventListener('DOMContentLoaded', () => {
    const editorElem = document.getElementById('quillEditor');
    if (editorElem) {
        quill = new Quill('#quillEditor', {
            theme: 'snow',
            modules: {
                toolbar: '#quillToolbar',
                table: true
            },
            placeholder: <?= json_encode(__('Docs::docs_editor_placeholder', 'Dökümanınızı buraya yazmaya başlayın...')) ?>
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
});

function markDocUnsaved() {
    isDocSaved = false;
    const status = document.getElementById('docSaveStatus');
    if (status) {
        status.innerText = <?= json_encode('● ' . __('Docs::docs_save_status_unsaved', 'Kaydedilmedi')) ?>;
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

        btnVisual.classList.remove('active');
        btnSource.classList.add('active');
    } else {
        // Kaynak Koddan Görsele Geçiş
        loadDocContent(textarea.value);
        sourceWrapper.style.display = 'none';
        quillWrapper.style.display = 'flex';

        btnSource.classList.remove('active');
        btnVisual.classList.add('active');
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
            showToast(data.message || <?= json_encode(__('Docs::docs_toast_load_err', 'Döküman yüklenemedi')) ?>, 'error');
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
            status.innerText = <?= json_encode('✓ ' . __('Docs::docs_save_status_saved', 'Kaydedildi')) ?>;
            status.style.color = 'var(--text-muted)';
        }
    } catch (e) {
        console.error(e);
        showToast(<?= json_encode(__('Docs::docs_toast_conn_err', 'Bağlantı hatası')) ?>, 'error');
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
        showToast(<?= json_encode(__('Docs::docs_toast_title_req', 'Başlık zorunludur')) ?>, 'error');
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
        status.innerText = <?= json_encode('⏳ ' . __('Docs::docs_save_status_saving', 'Kaydediliyor...')) ?>;
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
                status.innerText = <?= json_encode('✓ ' . __('Docs::docs_save_status_saved', 'Kaydedildi')) ?>;
                status.style.color = '#00b894';
            }
            showToast(<?= json_encode(__('Docs::docs_toast_save_success', '✅ Döküman kaydedildi!')) ?>, 'success');
            
            // Sidebar başlık ve ikon güncelle
            const sideTitle = document.getElementById(`docSidebarTitle-${docId}`);
            if (sideTitle) sideTitle.innerText = title;
            const sideIcon = document.getElementById(`docIconBadge-${docId}`);
            if (sideIcon) sideIcon.innerText = icon;
        } else {
            showToast(data.message || <?= json_encode(__('Docs::docs_toast_save_fail', 'Kayıt başarısız')) ?>, 'error');
        }
    } catch (e) {
        console.error(e);
        showToast(<?= json_encode(__('Docs::docs_toast_conn_err', 'Bağlantı hatası')) ?>, 'error');
    }
}

// 6. Yeni Döküman Oluşturma
async function openNewDocPrompt() {
    const title = prompt(<?= json_encode(__('Docs::docs_create_prompt', 'Yeni Döküman Başlığı:')) ?>, <?= json_encode(__('Docs::docs_create_default', 'Yeni Doküman')) ?>);
    if (!title) return;

    const formData = new FormData();
    formData.append('title', title);
    formData.append('icon', '📄');
    <?php if ($activeProjectId): ?>
        formData.append('project_id', '<?= $activeProjectId ?>');
    <?php endif; ?>

    try {
        const res = await fetch('/taskmanager/ajax?action=create_doc', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(<?= json_encode(__('Docs::docs_toast_created', '✅ Yeni döküman oluşturuldu!')) ?>, 'success');
            window.location.reload();
        } else {
            showToast(data.message || <?= json_encode(__('Docs::docs_toast_create_fail', 'Oluşturulamadı')) ?>, 'error');
        }
    } catch (e) {
        console.error(e);
    }
}

// 7. Döküman Silme
async function deleteActiveDoc() {
    const docId = document.getElementById('activeDocId')?.value;
    if (!docId) return;

    if (!confirm(<?= json_encode(__('Docs::docs_confirm_delete', 'Bu dökümanı silmek istediğinize emin misiniz?')) ?>)) return;

    const formData = new FormData();
    formData.append('id', docId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_doc', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(<?= json_encode(__('Docs::docs_toast_deleted', 'Döküman silindi')) ?>, 'success');
            window.location.href = '/taskmanager/docs';
        } else {
            showToast(data.message || <?= json_encode(__('Docs::docs_toast_delete_fail', 'Silinemedi')) ?>, 'error');
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

// 9. Ctrl + S ile Hızlı Kaydetme
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        saveActiveDoc();
    }
});

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
        list.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:12px; color:var(--text-muted); font-size:12px; font-style:italic;"><?= addslashes(__('Docs::docs_attachments_empty', 'Bu dökümana henüz ek dosya veya görsel eklenmemiş.')) ?></div>';
        return;
    }

    currentDocAttachments.forEach(att => {
        const item = document.createElement('div');
        item.style.cssText = 'background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); border-radius:8px; padding:10px; display:flex; align-items:center; gap:10px; transition:border-color 0.2s;';
        
        const isImg = /\.(jpg|jpeg|png|gif|webp|svg)($|\?)/i.test(att.file_path);
        const iconHtml = isImg 
            ? `<img src="${att.file_path}" alt="${att.file_name}" onclick="openImageLightbox('${att.file_path}', '${escape(att.file_name)}')" style="width:38px; height:38px; border-radius:6px; object-fit:cover; cursor:zoom-in; border:1px solid var(--border-subtle);">`
            : `<div style="width:38px; height:38px; border-radius:6px; background:rgba(123, 104, 238, 0.15); display:flex; align-items:center; justify-content:center; font-size:18px;">📄</div>`;

        const sizeKb = att.file_size ? Math.round(att.file_size / 1024) + ' KB' : '';

        item.innerHTML = `
            ${iconHtml}
            <div style="flex:1; overflow:hidden;">
                <div style="font-size:12px; font-weight:600; color:var(--text-white); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${att.file_name}">
                    ${att.file_name}
                </div>
                <div style="font-size:10.5px; color:var(--text-muted); margin-top:2px;">
                    ${sizeKb}
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:4px;">
                <a href="${att.file_path}" download target="_blank" style="color:var(--brand-primary); padding:4px; text-decoration:none; font-size:13px;" title="<?= htmlspecialchars(__('Docs::docs_attachment_download', 'İndir'), ENT_QUOTES, 'UTF-8') ?>">⬇️</a>
                <button type="button" onclick="deleteDocAttachment(${att.id})" style="background:none; border:none; color:#e74c3c; cursor:pointer; padding:4px; font-size:13px;" title="<?= htmlspecialchars(__('Docs::docs_btn_delete', 'Sil'), ENT_QUOTES, 'UTF-8') ?>">🗑️</button>
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
        showToast(<?= json_encode(__('Docs::docs_toast_select_first', 'Önce bir döküman seçmelisiniz')) ?>, 'error');
        return;
    }

    const formData = new FormData();
    formData.append('doc_id', docId);
    formData.append('file', file);

    showToast(<?= json_encode(__('Docs::docs_toast_uploading', 'Dosya yükleniyor...')) ?>, 'info');

    try {
        const res = await fetch('/taskmanager/ajax?action=upload_doc_attachment', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        const attData = d.data || d.attachment || (d.id ? d : null);
        if (d.success && attData) {
            showToast(<?= json_encode(__('Docs::docs_toast_upload_success', '✅ Dosya dökümana eklendi!')) ?>, 'success');
            currentDocAttachments = currentDocAttachments.filter(a => a.id != attData.id);
            currentDocAttachments.unshift(attData);
            renderDocAttachments(currentDocAttachments);
        } else {
            showToast(d.message || <?= json_encode(__('Docs::docs_toast_upload_fail', 'Yükleme başarısız')) ?>, 'error');
        }
    } catch(e) {
        showToast(<?= json_encode(__('Docs::docs_toast_conn_err', 'Bağlantı hatası oluştu')) ?>, 'error');
    }
}

async function deleteDocAttachment(attId) {
    if (!confirm(<?= json_encode(__('Docs::docs_confirm_delete_att', 'Bu eki silmek istediğinize emin misiniz?')) ?>)) return;

    const formData = new FormData();
    formData.append('attachment_id', attId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_doc_attachment', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        if (d.success) {
            showToast(<?= json_encode(__('Docs::docs_toast_att_deleted', 'Ek dosya silindi!')) ?>, 'success');
            currentDocAttachments = currentDocAttachments.filter(a => a.id != attId);
            renderDocAttachments(currentDocAttachments);
        } else {
            showToast(d.message || <?= json_encode(__('Docs::docs_toast_delete_fail', 'Silinemedi')) ?>, 'error');
        }
    } catch(e) {
        showToast(<?= json_encode(__('Docs::docs_toast_conn_err', 'Bağlantı hatası')) ?>, 'error');
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
            showToast(<?= json_encode(__('Docs::docs_toast_paste_success', '📋 Ekran görüntüsü dökümana eklendi!')) ?>, 'success');
            break;
        }
    }
});

// Sayfa yüklendiğinde döküman eklerini çek
document.addEventListener('DOMContentLoaded', () => {
    loadCurrentDocAttachments();
});
