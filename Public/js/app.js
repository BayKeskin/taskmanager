/**
 * TaskManager JavaScript Engine & Interactive Workflow Controller
 * - Slide-Over Task Drawer
 * - Real-time Comments & Discussion
 * - HTML5 Kanban Drag & Drop
 * - Modals & Toast Engine
 * - Global Filter & Search
 */

let currentDrawerTaskId = null;
let draggedTaskId = null;

// ==========================================================================
// 0. ÇOK DİLLİ MOTOR & İSTEK YAKALAYICI (I18N & GLOBAL FETCH INTERCEPTOR)
// ==========================================================================
window.cleanMojibake = function(str) {
    if (!str || typeof str !== 'string') return str || '';
    const map = {
        'Ä±': 'ı', 'ÄŸ': 'ğ', 'Ã¼': 'ü', 'ÅŸ': 'ş', 'Ã¶': 'ö', 'Ã§': 'ç',
        'Ä°': 'İ', 'Äž': 'Ğ', 'Ãœ': 'Ü', 'Åž': 'Ş', 'Ã–': 'Ö', 'Ã‡': 'Ç',
        'Ã¢': 'â', 'Ã®': 'î', 'Ã»': 'û', 'Ã‚': 'Â', 'ÃŽ': 'Î',
        'â€™': "'", 'â€œ': '"', 'â€': '"', 'â€“': '–', 'â€”': '—',
        'ğŸ”‘': '🔑', 'âš ï¸ ': '⚠️', 'âš ï¸': '⚠️', 'âš ': '⚠️', 'âš¡': '⚡',
        'âš™ï¸ ': '⚙️', 'âš™ï¸': '⚙️', 'âš™': '⚙️', 'ğŸ§©': '🧩', 'ğŸ’¾': '💾',
        'ğŸ”„': '🔄', 'ğŸš€': '🚀', 'ğŸ’¡': '💡', 'ğŸ“ ': '📝', 'ğŸ“Š': '📊',
        'ğŸ” ': '🔍', 'âœ…': '✅', 'â Œ': '❌', 'âž¡': '➡️', 'â¬…': '⬅️'
    };
    let res = str;
    for (const [k, v] of Object.entries(map)) {
        if (res.includes(k)) {
            res = res.split(k).join(v);
        }
    }
    return res;
};

function getAppLanguage() {
    return window.CURRENT_LANG || document.documentElement.lang || 'tr';
}

const APP_TRANSLATIONS = {
    en: {
        'Bağlantı hatası oluştu': 'Connection error occurred',
        'Yorumunuz başarıyla iletildi!': 'Your comment was successfully sent!',
        'Sunucu bağlantı hatası': 'Server connection error',
        'Bağlantı hatası': 'Connection error',
        'İstek gönderilirken hata oluştu': 'Error occurred while sending request',
        'Görev başarıyla oluşturuldu!': 'Task successfully created!',
        'Sunucu hatası': 'Server error',
        'Proje başarıyla oluşturuldu!': 'Project successfully created!',
        '✅ Proje ayarları kaydedildi!': '✅ Project settings saved!',
        '⏳ Webhook test sinyali gönderiliyor...': '⏳ Sending webhook test signal...',
        'Test bağlantı hatası': 'Test connection error',
        'Kontrol maddesi eklendi!': 'Checklist item added!',
        'Görev başlığı güncellendi!': 'Task title updated!',
        'Alt görev başarıyla oluşturuldu!': 'Subtask successfully created!',
        'Zaman sayacı başlatıldı.': 'Timer started.',
        'Lütfen bağlanacak görevi seçin': 'Please select a task to link',
        'Bağımlılık kaldırıldı': 'Dependency removed',
        'Filtreler sıfırlandı.': 'Filters reset.',
        'Lütfen alt görev başlığı girin': 'Please enter subtask title',
        'Görev açıklaması güncellendi!': 'Task description updated!',
        'Alt görev silindi': 'Subtask deleted',
        'Madde silindi': 'Item deleted',
        'Etiketler güncellendi!': 'Tags updated!',
        'Önce bir görev seçmelisiniz': 'You must select a task first',
        'Dosya yükleniyor...': 'Uploading file...',
        '✅ Dosya yüklendi!': '✅ File uploaded!',
        'Dosya yüklenirken bağlantı hatası oluştu': 'Connection error while uploading file',
        'Dosya silindi!': 'File deleted!',
        '📋 Ekran görüntüsü teslimat formuna eklendi!': '📋 Screenshot added to delivery form!',
        '📋 Ekran görüntüsü yoruma eklendi!': '📋 Screenshot added to comment!',
        '📋 Ekran görüntüsü panodan göreve yüklendi!': '📋 Screenshot uploaded to task!',
        'Yorum boş olamaz!': 'Comment cannot be empty!',
        'Yorum başarıyla güncellendi!': 'Comment successfully updated!',
        'Yükleniyor...': 'Loading...',
        'Görev detayları getiriliyor...': 'Fetching task details...',
        'Yorumlar yükleniyor...': 'Loading comments...',
        'Görev detayları alınamadı': 'Could not fetch task details',
        'Düzenlemek için tıklayın': 'Click to edit',
        'Görev açıklaması ve talimatları yazmak için buraya tıklayın...': 'Click here to write task description and instructions...',
        'Bu görevi ve bağlı tüm alt öğelerini silmek istediğinize emin misiniz? Bu işlem geri alınamaz!': 'Are you sure you want to delete this task and all related subtasks? This action cannot be undone!',
        'Bu görevi silmek istediğinize emin misiniz?': 'Are you sure you want to delete this task?',
        'Bu alt görevi silmek istediğinize emin misiniz?': 'Are you sure you want to delete this subtask?',
        'DİKKAT: Bu projeyi ve içindeki TÜM görevleri ve dökümanları kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz!': 'WARNING: Are you sure you want to permanently delete this project and ALL its tasks and documents? This action cannot be undone!',
        'Bu görseli/dosyayı silmek istediğinizden emin misiniz?': 'Are you sure you want to delete this file/image?',
        'Şifrenizi değiştirmek için lütfen mevcut şifrenizi girin.': 'Please enter your current password to change password.',
        'Yeni şifreniz en az 6 karakter olmalıdır.': 'New password must be at least 6 characters.',
        'Yeni şifreler birbiriyle uyuşmuyor.': 'New passwords do not match.',
        'Sunucu hatası: ': 'Server error: ',
        'Hata: ': 'Error: '
    },
    de: {
        'Bağlantı hatası oluştu': 'Verbindungsfehler aufgetreten',
        'Yorumunuz başarıyla iletildi!': 'Ihr Kommentar wurde erfolgreich gesendet!',
        'Sunucu bağlantı hatası': 'Serververbindungsfehler',
        'Bağlantı hatası': 'Verbindungsfehler',
        'İstek gönderilirken hata oluştu': 'Fehler beim Senden der Anfrage aufgetreten',
        'Görev başarıyla oluşturuldu!': 'Aufgabe erfolgreich erstellt!',
        'Sunucu hatası': 'Serverfehler',
        'Proje başarıyla oluşturuldu!': 'Projekt erfolgreich erstellt!',
        '✅ Proje ayarları kaydedildi!': '✅ Projekteinstellungen gespeichert!',
        '⏳ Webhook test sinyali gönderiliyor...': '⏳ Webhook-Testsignal wird gesendet...',
        'Test bağlantı hatası': 'Testverbindungsfehler',
        'Kontrol maddesi eklendi!': 'Checklistenpunkt hinzugefügt!',
        'Görev başlığı güncellendi!': 'Aufgabentitel aktualisiert!',
        'Alt görev başarıyla oluşturuldu!': 'Unteraufgabe erfolgreich erstellt!',
        'Zaman sayacı başlatıldı.': 'Zeiterfassung gestartet.',
        'Lütfen bağlanacak görevi seçin': 'Bitte wählen Sie eine zu verknüpfende Aufgabe aus',
        'Bağımlılık kaldırıldı': 'Abhängigkeit entfernt',
        'Filtreler sıfırlandı.': 'Filter zurückgesetzt.',
        'Lütfen alt görev başlığı girin': 'Bitte geben Sie einen Unteraufgabentitel ein',
        'Görev açıklaması güncellendi!': 'Aufgabenbeschreibung aktualisiert!',
        'Alt görev silindi': 'Unteraufgabe gelöscht',
        'Madde silindi': 'Punkt gelöscht',
        'Etiketler güncellendi!': 'Tags aktualisiert!',
        'Önce bir görev seçmelisiniz': 'Sie müssen zuerst eine Aufgabe auswählen',
        'Dosya yükleniyor...': 'Datei wird hochgeladen...',
        '✅ Dosya yüklendi!': '✅ Datei hochgeladen!',
        'Dosya yüklenirken bağlantı hatası oluştu': 'Verbindungsfehler beim Hochladen der Datei aufgetreten',
        'Dosya silindi!': 'Datei gelöscht!',
        '📋 Ekran görüntüsü teslimat formuna eklendi!': '📋 Screenshot zum Lieferformular hinzugefügt!',
        '📋 Ekran görüntüsü yoruma eklendi!': '📋 Screenshot zum Kommentar hinzugefügt!',
        '📋 Ekran görüntüsü panodan göreve yüklendi!': '📋 Screenshot zur Aufgabe hochgeladen!',
        'Yorum boş olamaz!': 'Kommentar darf nicht leer sein!',
        'Yorum başarıyla güncellendi!': 'Kommentar erfolgreich aktualisiert!',
        'Yükleniyor...': 'Wird geladen...',
        'Görev detayları getiriliyor...': 'Aufgabendetails werden geladen...',
        'Yorumlar yükleniyor...': 'Kommentare werden geladen...',
        'Görev detayları alınamadı': 'Aufgabendetails konnten nicht geladen werden',
        'Düzenlemek için tıklayın': 'Zum Bearbeiten klicken',
        'Görev açıklaması ve talimatları yazmak için buraya tıklayın...': 'Klicken Sie hier, um Aufgabenbeschreibung und Anweisungen einzugeben...',
        'Bu görevi ve bağlı tüm alt öğelerini silmek istediğinize emin misiniz? Bu işlem geri alınamaz!': 'Sind Sie sicher, dass Sie diese Aufgabe und alle untergeordneten Elemente löschen möchten? Dieser Vorgang kann nicht rückgängig gemacht werden!',
        'Bu görevi silmek istediğinize emin misiniz?': 'Sind Sie sicher, dass Sie diese Aufgabe löschen möchten?',
        'Bu alt görevi silmek istediğinize emin misiniz?': 'Sind Sie sicher, dass Sie diese Unteraufgabe löschen möchten?',
        'DİKKAT: Bu projeyi ve içindeki TÜM görevleri ve dökümanları kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz!': 'ACHTUNG: Sind Sie sicher, dass Sie dieses Projekt und ALLE darin enthaltenen Aufgaben und Dokumente dauerhaft löschen möchten? Dieser Vorgang kann nicht rückgängig gemacht werden!',
        'Bu görseli/dosyayı silmek istediğinizden emin misiniz?': 'Sind Sie sicher, dass Sie diese Datei/dieses Bild löschen möchten?',
        'Şifrenizi değiştirmek için lütfen mevcut şifrenizi girin.': 'Bitte geben Sie Ihr aktuelles Passwort ein, um das Passwort zu ändern.',
        'Yeni şifreniz en az 6 karakter olmalıdır.': 'Neues Passwort muss mindestens 6 Zeichen lang sein.',
        'Yeni şifreler birbiriyle uyuşmuyor.': 'Neue Passwörter stimmen nicht überein.',
        'Sunucu hatası: ': 'Serverfehler: ',
        'Hata: ': 'Fehler: '
    }
};

function t(key) {
    if (!key || typeof key !== 'string') return key;
    const lang = getAppLanguage();
    if (lang === 'tr' || !APP_TRANSLATIONS[lang]) return key;
    return APP_TRANSLATIONS[lang][key] || key;
}

// Global fetch interceptor: automatically append active language to /ajax and /api calls
(function() {
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        options = options || {};
        const lang = getAppLanguage();
        if (typeof url === 'string' && (url.includes('/ajax') || url.includes('/api'))) {
            if (!url.includes('lang=') && !url.includes('locale=') && !url.includes('set_lang=')) {
                const separator = url.includes('?') ? '&' : '?';
                url = `${url}${separator}lang=${encodeURIComponent(lang)}`;
            }
            options.headers = options.headers || {};
            if (options.headers instanceof Headers) {
                if (!options.headers.has('Accept-Language')) {
                    options.headers.set('Accept-Language', lang);
                }
                if (!options.headers.has('X-Language')) {
                    options.headers.set('X-Language', lang);
                }
            } else if (typeof options.headers === 'object') {
                if (!options.headers['Accept-Language']) {
                    options.headers['Accept-Language'] = lang;
                }
                if (!options.headers['X-Language']) {
                    options.headers['X-Language'] = lang;
                }
            }
        }
        return originalFetch.call(this, url, options);
    };

    // Global alert & confirm translation wrappers
    const originalConfirm = window.confirm;
    window.confirm = function(msg) {
        return originalConfirm.call(this, t(msg));
    };
    const originalAlert = window.alert;
    window.alert = function(msg) {
        return originalAlert.call(this, t(msg));
    };
})();

// ==========================================================================
// 1. TOAST BİLDİRİMLERİ (TOAST NOTIFICATIONS)
// ==========================================================================
function showToast(message, type = 'info') {
    const container = document.getElementById('cuToastContainer');
    if (!container) return;

    message = t(message);

    const toast = document.createElement('div');
    toast.className = `cu-toast ${type}`;
    
    let icon = 'ℹ️';
    if (type === 'success') icon = '✅';
    if (type === 'error') icon = '⚠️';

    toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}

// ==========================================================================
// 2. MODAL YÖNETİMİ
// ==========================================================================
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    if (id === 'modalNewTask') {
        const urlParams = new URLSearchParams(window.location.search);
        const curProjId = urlParams.get('project_id');
        const projSelect = document.getElementById('modalNewTaskProjectSelect') || modal.querySelector('select[name="project_id"]');
        if (projSelect && curProjId) {
            projSelect.value = curProjId;
        }
    }
    modal.classList.add('active');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

function openNewTaskModal() { 
    initModalAssignees();
    const urlParams = new URLSearchParams(window.location.search);
    const curProjId = urlParams.get('project_id');
    const projSelect = document.getElementById('modalNewTaskProjectSelect') || document.querySelector('#modalNewTask select[name="project_id"]');
    if (projSelect && curProjId) {
        projSelect.value = curProjId;
    }
    openModal('modalNewTask'); 
}
function openNewProjectModal() { openModal('modalNewProject'); }

// ==========================================================================
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

// Form Submit Handlers
async function handleCreateTask(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    try {
        const res = await fetch('/taskmanager/ajax?action=create_task', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            showToast('Görev başarıyla oluşturuldu!', 'success');
            closeModal('modalNewTask');
            setTimeout(() => window.location.reload(), 700);
        } else {
            showToast(result.message || 'Görev oluşturulamadı', 'error');
        }
    } catch (err) {
        showToast('Sunucu hatası', 'error');
    }
}

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

function openEditProjectModal(proj) {
    if (!proj) return;
    document.getElementById('editProjId').value = proj.id;
    document.getElementById('editProjTitle').value = proj.title || '';
    document.getElementById('editProjCode').value = proj.project_code || '';
    document.getElementById('editProjColor').value = proj.color || '#7B68EE';
    document.getElementById('editProjDesc').value = proj.description || '';
    document.getElementById('editProjWorkspacePath').value = proj.workspace_path || '';
    document.getElementById('editProjAgentRules').value = proj.agent_rules || '';
    document.getElementById('editProjWebhookUrl').value = proj.webhook_url || '';
    document.getElementById('editProjConvId').value = proj.agent_conversation_id || '';
    openModal('modalEditProject');
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
// 15. KULLANICI PROFİL GÜNCELLEME VE ŞİFRE DEĞİŞTİRME
// ==========================================================================
function openProfileModal() {
    openModal('modalProfileSettings');
}

function closeProfileModal() {
    closeModal('modalProfileSettings');
}

function selectProfileColor(color) {
    document.querySelectorAll('.profile-color-dot').forEach(dot => {
        dot.style.borderColor = (dot.getAttribute('data-color') === color) ? '#fff' : 'transparent';
    });
    const avatar = document.getElementById('profileAvatarPreview');
    if (avatar) avatar.style.background = color;
}

function updateProfileAvatarPreview() {
    const nameInput = document.getElementById('profileInputName');
    const avatar = document.getElementById('profileAvatarPreview');
    const namePreview = document.getElementById('profileNamePreview');
    if (nameInput && avatar && namePreview) {
        const val = nameInput.value.trim();
        namePreview.innerText = val || 'Kullanıcı';
        avatar.innerText = val ? val.charAt(0).toUpperCase() : 'U';
    }
}

function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerText = '🙈';
    } else {
        input.type = 'password';
        btn.innerText = '👁️';
    }
}

function handleUpdateProfile(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitProfile');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳</span> <span>Güncelleniyor...</span>';

    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'update_profile');

    const newPass = form.querySelector('[name="new_password"]').value;
    const newPassConfirm = form.querySelector('[name="new_password_confirm"]').value;
    const currentPass = form.querySelector('[name="current_password"]').value;

    if (newPass) {
        if (!currentPass) {
            alert('Şifrenizi değiştirmek için lütfen mevcut şifrenizi girin.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }
        if (newPass.length < 6) {
            alert('Yeni şifreniz en az 6 karakter olmalıdır.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }
        if (newPass !== newPassConfirm) {
            alert('Yeni şifreler birbiriyle uyuşmuyor.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }
    }

    fetch('/taskmanager/ajax/update_profile', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('✅ ' + data.message, 'success');
            } else {
                alert('✅ ' + data.message);
            }
            closeProfileModal();
            setTimeout(() => {
                location.reload();
            }, 600);
        } else {
            alert('❌ Hata: ' + data.message);
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Sunucu hatası: ' + err.message);
    });
}

// ==============================================================================
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
// 15. CANLI SENKRONİZASYON & SERVER-SENT EVENTS (SSE ENGINE)
// ==========================================================================
let sseSource = null;
let sseReconnectTimer = null;
let sseRetryCount = 0;

function initRealtimeSync() {
    if (!window.EventSource) {
        console.warn('Tarayıcınız Server-Sent Events (SSE) desteklemiyor.');
        const badge = document.getElementById('cuRealtimeStatus');
        if (badge) badge.style.display = 'none';
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const activeProjId = urlParams.get('project_id');
    const sseEndpoint = '/taskmanager/api/sse' + (activeProjId ? '?project_id=' + encodeURIComponent(activeProjId) : '');

    try {
        if (sseSource) {
            sseSource.close();
        }

        sseSource = new EventSource(sseEndpoint);

        sseSource.addEventListener('connected', (e) => {
            sseRetryCount = 0;
            updateRealtimeBadge(true);
        });

        // 1. Görev Durumu Değişti (Kanban & Liste Senkronizasyonu)
        sseSource.addEventListener('task_status_changed', (e) => {
            try {
                const data = JSON.parse(e.data);
                handleLiveTaskStatusChanged(data);
            } catch (err) {
                console.error('SSE task_status_changed parse error:', err);
            }
        });

        // 2. Görev Teslim Edildi
        sseSource.addEventListener('task_submitted', (e) => {
            try {
                const data = JSON.parse(e.data);
                handleLiveTaskSubmitted(data);
            } catch (err) {
                console.error('SSE task_submitted parse error:', err);
            }
        });

        // 3. Yeni Görev Oluşturuldu
        sseSource.addEventListener('task_created', (e) => {
            try {
                const data = JSON.parse(e.data);
                handleLiveTaskCreated(data);
            } catch (err) {
                console.error('SSE task_created parse error:', err);
            }
        });

        // 4. Yeni Yorum / Soru Eklendi
        sseSource.addEventListener('new_comment', (e) => {
            try {
                const data = JSON.parse(e.data);
                handleLiveNewComment(data);
            } catch (err) {
                console.error('SSE new_comment parse error:', err);
            }
        });

        // 5. Yeni Ekip Sohbeti Mesajı (Realtime Chat)
        sseSource.addEventListener('new_chat_message', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (typeof appendMessageToChat === 'function') {
                    appendMessageToChat(data, true);
                    if (typeof scrollToBottom === 'function') scrollToBottom();
                } else {
                    showToast(`💬 <b>${data.user_name || 'Ekip Üyesi'}</b>: ${data.message}`, 'info');
                }
            } catch (err) {
                console.error('SSE new_chat_message parse error:', err);
            }
        });

        // 5.1 Yeni Birebir Özel Mesaj (Direct Message - DM)
        sseSource.addEventListener('new_direct_message', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (typeof window.handleIncomingDirectMessage === 'function') {
                    window.handleIncomingDirectMessage(data);
                } else if (typeof window.CURRENT_USER_ID !== 'undefined' && data.receiver_id == window.CURRENT_USER_ID) {
                    showToast(`🔒 <b>${data.user_name || 'Bir Üye'}</b> size özel mesaj gönderdi: "${(data.message || 'Ekli dosya').substring(0, 35)}" <a href="/taskmanager/chat?user_id=${data.user_id}" style="color:#fff; text-decoration:underline; margin-left:6px;">Sohbete Git</a>`, 'info');
                }
            } catch (err) {
                console.error('SSE new_direct_message parse error:', err);
            }
        });

        // 6. Görev Silindi (Realtime Task Deleted)
        sseSource.addEventListener('task_deleted', (e) => {
            try {
                const data = JSON.parse(e.data);
                handleLiveTaskDeleted(data);
            } catch (err) {
                console.error('SSE task_deleted parse error:', err);
            }
        });

        // 7. Göreve Ek Dosya/Resim Yüklendi (Canlı Senkronizasyon)
        sseSource.addEventListener('task_attachment_added', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (data && data.attachment && typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == data.task_id) {
                    currentTaskAttachments = currentTaskAttachments.filter(a => a.id != data.attachment.id);
                    currentTaskAttachments.unshift(data.attachment);
                    renderDrawerAttachments(currentTaskAttachments);
                    showToast(`📎 Göreve yeni dosya eklendi: "${data.attachment.file_name}"`, 'info');
                }
            } catch (err) {
                console.error('SSE task_attachment_added parse error:', err);
            }
        });

        // 8. Görevden Ek Dosya/Resim Silindi (Canlı Senkronizasyon)
        sseSource.addEventListener('task_attachment_deleted', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (data && data.attachment_id && typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == data.task_id) {
                    currentTaskAttachments = currentTaskAttachments.filter(a => a.id != data.attachment_id);
                    renderDrawerAttachments(currentTaskAttachments);
                }
            } catch (err) {
                console.error('SSE task_attachment_deleted parse error:', err);
            }
        });

        // 9. Görev Yorumu Güncellendi (Realtime Comment Updated)
        sseSource.addEventListener('comment_updated', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (data && typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == data.task_id) {
                    const textEl = document.getElementById(`commentText_${data.comment_id}`);
                    if (textEl) {
                        textEl.innerText = data.comment;
                    }
                    const editedBadge = document.getElementById(`commentEditedBadge_${data.comment_id}`);
                    if (editedBadge) {
                        editedBadge.style.display = 'inline';
                    }
                }
            } catch (err) {
                console.error('SSE comment_updated parse error:', err);
            }
        });

        sseSource.onopen = () => {
            sseRetryCount = 0;
            updateRealtimeBadge(true);
        };

        sseSource.onerror = () => {
            updateRealtimeBadge(false);
            if (sseSource.readyState === EventSource.CLOSED) {
                clearTimeout(sseReconnectTimer);
                const delay = Math.min(30000, 2000 * Math.pow(1.5, sseRetryCount++));
                sseReconnectTimer = setTimeout(() => {
                    initRealtimeSync();
                }, delay);
            }
        };

    } catch (err) {
        console.error('SSE bağlantı hatası:', err);
        updateRealtimeBadge(false);
    }
}

function updateRealtimeBadge(isConnected) {
    const badge = document.getElementById('cuRealtimeStatus');
    const text = document.getElementById('cuRealtimeText');
    if (!badge) return;

    const titleActive = badge.dataset.titleActive || 'Canlı Pano ve Bildirim Senkronizasyonu Aktif';
    const titleReconnecting = badge.dataset.titleReconnecting || 'Bağlantı kesildi, yeniden bağlanılıyor...';
    const textLive = badge.dataset.textLive || 'Canlı';
    const textConnecting = badge.dataset.textConnecting || 'Bağlanıyor';

    if (isConnected) {
        badge.classList.remove('disconnected');
        badge.title = titleActive;
        if (text) text.innerText = textLive;
    } else {
        badge.classList.add('disconnected');
        badge.title = titleReconnecting;
        if (text) text.innerText = textConnecting;
    }
}

// Canlı Görev Durumu Değişimi İşleyicisi
function handleLiveTaskStatusChanged(data) {
    const { task_id, task_code, title, old_status, new_status, user_name } = data;

    // A) Kanban Panosunda Kartı Taşı
    const card = document.querySelector(`.cu-board-card[data-task-id="${task_id}"]`);
    if (card) {
        const targetCol = document.querySelector(`.cu-board-cards[data-status="${new_status}"]`);
        if (targetCol && card.parentElement !== targetCol) {
            targetCol.prepend(card);
            card.classList.add('live-pulse-anim');
            setTimeout(() => card.classList.remove('live-pulse-anim'), 1500);
            updateBoardBadges();
        }
    }

    // B) Liste Görünümünde (tasks / index.php) Satırı Taşı
    const taskContainer = document.getElementById(`task-container-${task_id}`);
    if (taskContainer) {
        const targetList = document.getElementById(`taskList-${new_status}`);
        if (targetList && taskContainer.parentElement !== targetList) {
            targetList.prepend(taskContainer);
            taskContainer.classList.add('live-pulse-anim');
            setTimeout(() => taskContainer.classList.remove('live-pulse-anim'), 1500);
            updateListGroupCounts();
        }
    }

    // C) Eğer Task Drawer Açık ve Bu Göreve Aitse Durumu Güncelle
    if (typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == task_id) {
        const badge = document.getElementById('drawerStatusBadge');
        if (badge && typeof statusBadgeConfig !== 'undefined') {
            const cfg = statusBadgeConfig[new_status] || { label: (new_status || '').toUpperCase(), color: '#7B68EE' };
            badge.innerHTML = `<span class="cu-status-pill" style="background:${cfg.color};">${cfg.label}</span>`;
        }
    }

    // D) Canlı Bildirim Toast Göster
    showToast(`⚡ <b>${task_code || 'Görev'}</b>: Durum <b>${(new_status || '').toUpperCase()}</b> yapıldı. (${user_name || 'Ekip'})`, 'info');
}

// Canlı Görev Teslimatı
function handleLiveTaskSubmitted(data) {
    const { task_id, task_code, title, user_name, notes } = data;
    handleLiveTaskStatusChanged({ ...data, new_status: 'submitted' });
    showToast(`🚀 <b>${task_code || 'Görev'}</b> teslim edildi! Not: "${(notes || '').substring(0, 40)}"`, 'success');
}

// Canlı Görev Silinmesi (Tüm açık ekranlardan anında kaldır)
function handleLiveTaskDeleted(data) {
    const taskId = data.task_id;
    if (!taskId) return;

    // 1. Kanban panosundaki kartı animasyonla sil
    const card = document.querySelector(`.cu-board-card[data-task-id="${taskId}"], .cu-card[data-task-id="${taskId}"], #task-card-${taskId}`);
    if (card) {
        card.style.transition = 'all 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.85)';
        setTimeout(() => {
            card.remove();
            if (typeof updateBoardBadges === 'function') updateBoardBadges();
        }, 300);
    }

    // 2. Liste görünümündeki satırı sil
    const taskContainer = document.getElementById(`task-container-${taskId}`);
    if (taskContainer) {
        taskContainer.style.transition = 'all 0.3s ease';
        taskContainer.style.opacity = '0';
        setTimeout(() => {
            taskContainer.remove();
            if (typeof updateListGroupCounts === 'function') updateListGroupCounts();
        }, 300);
    }

    // 3. Çekmece veya modal bu göreve aitse kapat
    if (typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == taskId) {
        if (typeof closeTaskDrawer === 'function') closeTaskDrawer();
    }
    const modal = document.getElementById('modalTaskDetail');
    if (modal && modal.style.display !== 'none' && window.currentOpenTaskId === taskId) {
        if (typeof closeTaskModal === 'function') closeTaskModal();
    }

    showToast(`🗑️ <b>${data.task_code || 'Görev'}</b> panodan silindi.`, 'warning');
}

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

// Canlı Yeni Görev (Kanban & Liste Panolarına Anlık Kart Ekleme)
function handleLiveTaskCreated(data) {
    const { task_id, task_code, title, user_name } = data;
    showToast(`➕ <b>${task_code || 'Yeni Görev'}</b> eklendi: "${(title || '').substring(0, 35)}" (${user_name || 'Admin'})`, 'info');

    if (!task_id) return;
    if (document.querySelector(`.cu-board-card[data-task-id="${task_id}"]`)) return;

    const urlParams = new URLSearchParams(window.location.search);
    const activeProjId = urlParams.get('project_id');

    fetch(`/taskmanager/ajax?action=get_task_card_html&task_id=${task_id}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.task) {
                // Aktif proje filtresi varsa ve görev başka bir projeye aitse panoya ekleme
                if (activeProjId && res.task.project_id != activeProjId) {
                    return;
                }

                // 1. Kanban Board üzerinde 'assigned' sütununa ekle
                const assignedCol = document.querySelector('.cu-board-cards[data-status="assigned"]');
                if (assignedCol && res.card_html) {
                    assignedCol.insertAdjacentHTML('afterbegin', res.card_html);
                    updateBoardBadges();
                }

                // 2. Liste Görünümünde (tasks) 'taskList-assigned' içine ekle
                const assignedList = document.getElementById('taskList-assigned');
                if (assignedList && res.row_html) {
                    const emptyMsg = assignedList.querySelector('.cu-empty-group-msg');
                    if (emptyMsg) emptyMsg.style.display = 'none';
                    assignedList.insertAdjacentHTML('afterbegin', res.row_html);
                    updateListGroupCounts();
                }
            }
        })
        .catch(err => console.error('Live task card fetch error:', err));
}

// Canlı Yeni Yorum (Drawer Açık İse Yorumları Anında Yenile)
function handleLiveNewComment(data) {
    const { task_id, task_code, author, comment } = data;
    showToast(`💬 <b>${author}</b> (${task_code || 'Görev'}): "${(comment || '').substring(0, 45)}"`, 'info');

    // Panodaki kartı da görsel olarak dalgalanma efektiyle vurgula
    const boardCard = document.querySelector(`.cu-board-card[data-task-id="${task_id}"]`);
    if (boardCard) {
        boardCard.classList.add('live-pulse-anim');
        setTimeout(() => boardCard.classList.remove('live-pulse-anim'), 1500);
    }

    // Eğer Task Drawer açık ve bu göreve aitse yorumları ve aktiviteyi anında yenile
    if (typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == task_id) {
        fetch(`/taskmanager/ajax?action=get_task_detail&id=${task_id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (res.comments) {
                        renderDrawerComments(res.comments);
                    }
                    if (res.activities) {
                        renderDrawerTimeline(res.activities);
                    }
                }
            }).catch(err => console.error('Live comment drawer refresh error:', err));
    }
}

// Liste Görünümü Grup Sayaçlarını Güncelle
function updateListGroupCounts() {
    document.querySelectorAll('.cu-group').forEach(group => {
        const countSpan = group.querySelector('.cu-group-count');
        const rows = group.querySelectorAll('.cu-task-container');
        if (countSpan) {
            countSpan.innerText = `${rows.length} görev`;
        }
    });
}

// Sayfa yüklendiğinde Realtime SSE başlat
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRealtimeSync);
} else {
    initRealtimeSync();
}

// ==========================================================================
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

// ======================================================================
// 🐙 GITHUB DEVOPS & SURGICAL ROLLBACK ENTEGRASYONU
// ======================================================================
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
            showToast(data.message, 'success');
            // Çekmeceyi tazele
            if (typeof openTaskDrawer === 'function') {
                openTaskDrawer(taskId);
            }
        } else {
            if (data.data && data.data.has_conflict) {
                const surgeonPrompt = (data.message || 'Otomatik geri almada Git çakışması tespit edildi!') +
                    '\n\n' + (dI18n.ai_surgeon_prompt || '🤖 AI Cerrah Modunu (AI Surgeon) devreye alıp çakışmayı otomatik analiz edip çözerek geri alma işlemini tamamlamak ister misiniz?');
                const runSurgeon = confirm(surgeonPrompt);
                if (runSurgeon) {
                    rollbackTask(taskId, 'ai_surgeon');
                }
            } else {
                showToast(data.message || 'Geri alma işlemi başarısız oldu.', 'error');
            }
        }
    } catch (e) {
        console.error('rollbackTask error:', e);
        showToast('Sunucu bağlantı hatası oluştu', 'error');
    }
};

// ======================================================================
// 🧩 ANTIGRAVITY MCP MARKETPLACE & PROJE ARAÇ KUTUSU MOTORU
// ======================================================================
window.CURRENT_MCP_PROJECT_ID = null;
window.MCP_CATALOG_DATA = [];
window.ACTIVE_MCP_CATEGORY = 'all';

window.openProjectMcpModal = async function(projectId) {
    if (!projectId) {
        showToast('Lütfen geçerli bir proje seçiniz.', 'warning');
        return;
    }
    window.CURRENT_MCP_PROJECT_ID = parseInt(projectId, 10);
    window.ACTIVE_MCP_CATEGORY = 'all';

    // UI Temizliği ve Yükleniyor Durumu
    const badge = document.getElementById('mcpModalProjectBadge');
    if (badge) badge.textContent = '...';
    const searchInput = document.getElementById('mcpSearchInput');
    if (searchInput) searchInput.value = '';

    // Kategori butonlarını sıfırla
    document.querySelectorAll('.mcp-cat-pill').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-cat') === 'all');
    });

    const grid = document.getElementById('mcpCardsGrid');
    const emptyState = document.getElementById('mcpCardsEmpty');
    if (emptyState) emptyState.style.display = 'none';
    if (grid) {
        grid.innerHTML = `
            <div style="grid-column: 1 / -1; text-align:center; padding:50px 20px; color:var(--text-muted);">
                <div style="font-size:28px; animation:spin 1s linear infinite; display:inline-block; margin-bottom:8px;">🔄</div>
                <div style="font-size:13px; font-weight:600; color:var(--text-white);">MCP Kataloğu ve Proje Ayarları Yükleniyor...</div>
            </div>
        `;
    }

    openModal('modalProjectMcp');

    try {
        const res = await fetch(`/taskmanager/ajax?action=get_project_mcps&project_id=${projectId}`);
        const data = await res.json();

        if (data.success) {
            window.MCP_CATALOG_DATA = data.mcps || [];
            if (badge) {
                badge.textContent = `${data.project.title} (${data.project.project_code})`;
            }
            updateMcpCategoryCounts();
            renderMcpGrid();
        } else {
            showToast(data.message || 'MCP kataloğu yüklenemedi.', 'error');
            if (grid) grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; color:#ff7675; padding:30px;">${data.message || 'Hata oluştu'}</div>`;
        }
    } catch (e) {
        console.error('openProjectMcpModal error:', e);
        showToast('Sunucu bağlantı hatası oluştu', 'error');
    }
};

window.updateMcpCategoryCounts = function() {
    const list = window.MCP_CATALOG_DATA || [];
    const counts = { all: list.length, design: 0, database: 0, payment: 0, devops: 0, mobile: 0, productivity: 0, custom: 0 };
    list.forEach(item => {
        if (counts[item.category] !== undefined) {
            counts[item.category]++;
        }
    });

    for (const [cat, cnt] of Object.entries(counts)) {
        const cap = cat.charAt(0).toUpperCase() + cat.slice(1);
        const el = document.getElementById(`mcpCount${cap}`);
        if (el) el.textContent = cnt;
    }
};

window.selectMcpCategory = function(cat) {
    window.ACTIVE_MCP_CATEGORY = cat;
    document.querySelectorAll('.mcp-cat-pill').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-cat') === cat);
    });
    filterMcpCards();
};

window.filterMcpCards = function() {
    renderMcpGrid();
};

window.getMcpDisplayIcon = function(icon) {
    if (!icon) return '🧩';
    if (!icon.includes('fa-')) return icon;
    const map = {
        'fa-figma': '🎨',
        'fa-layer-group': '📐',
        'fa-paint-brush': '🖌️',
        'fa-mobile-alt': '📱',
        'fa-apple': '🍎',
        'fa-android': '🤖',
        'fa-database': '🗄️',
        'fa-bolt': '⚡',
        'fa-chart-pie': '📊',
        'fa-cloud': '☁️',
        'fa-toolbox': '🧰',
        'fa-leaf': '🍃',
        'fa-cubes': '🧊',
        'fa-table': '▦',
        'fa-server': '🖥️',
        'fa-stripe': '💳',
        'fa-paypal': '🅿️',
        'fa-credit-card': '💳',
        'fa-github': '🐙',
        'fa-gitlab': '🦊',
        'fa-network-wired': '🌐',
        'fa-brain': '🧠',
        'fa-search': '🔍',
        'fa-globe': '🌍',
        'fa-book': '📚',
        'fa-file-alt': '📝',
        'fa-clock': '⏱️',
        'fa-puzzle-piece': '🧩',
        'fa-tools': '🛠️'
    };
    for (const [key, emoji] of Object.entries(map)) {
        if (icon.includes(key)) return emoji;
    }
    return '🧩';
};

window.renderMcpGrid = function() {
    const grid = document.getElementById('mcpCardsGrid');
    const emptyState = document.getElementById('mcpCardsEmpty');
    if (!grid) return;

    const query = (document.getElementById('mcpSearchInput')?.value || '').toLowerCase().trim();
    const activeCat = window.ACTIVE_MCP_CATEGORY || 'all';

    const filtered = (window.MCP_CATALOG_DATA || []).filter(item => {
        const matchCat = (activeCat === 'all' || item.category === activeCat);
        const matchSearch = !query || 
            (item.name && item.name.toLowerCase().includes(query)) ||
            (item.display_name && item.display_name.toLowerCase().includes(query)) ||
            (item.description && item.description.toLowerCase().includes(query)) ||
            (item.mcp_key && item.mcp_key.toLowerCase().includes(query));
        return matchCat && matchSearch;
    });

    if (filtered.length === 0) {
        grid.innerHTML = '';
        if (emptyState) emptyState.style.display = 'block';
        return;
    }
    if (emptyState) emptyState.style.display = 'none';

    const i18n = window.MCP_I18N || {};

    function getMcpFields(item) {
        if (!item) return [];
        if (Array.isArray(item.required_env_fields) && item.required_env_fields.length > 0) {
            return item.required_env_fields;
        }
        if (Array.isArray(item.required_env) && item.required_env.length > 0) {
            return item.required_env.map(k => typeof k === 'string' ? { key: k, label: k, type: 'password', required: true } : k);
        }
        return [];
    }

    grid.innerHTML = filtered.map(item => {
        const isEnabled = parseInt(item.is_enabled, 10) === 1;
        const fields = getMcpFields(item);
        const hasEnvReq = fields.length > 0;
        const envFilled = hasEnvReq && fields.every(f => {
            if (f.required === false) return true;
            const val = item.env_values && item.env_values[f.key];
            return val && String(val).trim().length > 0;
        });

        // Kategori Rozeti Rengi
        const catColors = {
            design: '#e84393',
            database: '#0984e3',
            payment: '#00b894',
            devops: '#6c5ce7',
            mobile: '#fdcb6e',
            productivity: '#fd79a8',
            custom: '#636e72'
        };
        const catColor = catColors[item.category] || '#7B68EE';
        const displayIcon = window.getMcpDisplayIcon(item.icon);

        return `
            <div class="mcp-card ${isEnabled ? 'enabled' : ''}" id="mcpCard_${item.id}">
                <div>
                    <!-- Kart Üst Başlık & Toggle -->
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:8px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="font-size:24px; line-height:1;">${displayIcon}</span>
                            <div>
                                <h4 style="font-size:14px; font-weight:700; color:var(--text-white); margin:0; line-height:1.2;">
                                    ${item.display_name || item.name}
                                </h4>
                                <span style="font-size:10px; color:${catColor}; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">
                                    ${item.category}
                                </span>
                            </div>
                        </div>
                        <label class="mcp-toggle-switch" title="${isEnabled ? (i18n.status_disabled || 'Devre Dışı Bırak') : (i18n.status_enabled || 'Aktif Et')}">
                            <input type="checkbox" ${isEnabled ? 'checked' : ''} onchange="toggleProjectMcp(${item.id}, this.checked)">
                            <span class="mcp-slider"></span>
                        </label>
                    </div>

                    <!-- Açıklama -->
                    <p style="font-size:12px; color:var(--text-secondary); margin:0 0 10px 0; line-height:1.4; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;">
                        ${item.description || ''}
                    </p>

                    <!-- Rozetler -->
                    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;">
                        <span style="font-size:10.5px; font-family:monospace; background:rgba(255,255,255,0.06); padding:2px 7px; border-radius:4px; color:var(--text-muted); border:1px solid rgba(255,255,255,0.1);">
                            ${item.package_type || 'npx'}
                        </span>
                        ${hasEnvReq ? `
                            <span style="font-size:10.5px; background:${envFilled ? 'rgba(46,160,67,0.15)' : 'rgba(230,126,34,0.15)'}; color:${envFilled ? '#3fb950' : '#f39c12'}; border:1px solid ${envFilled ? 'rgba(46,160,67,0.4)' : 'rgba(230,126,34,0.4)'}; padding:2px 7px; border-radius:4px; font-weight:500;">
                                ${envFilled ? (i18n.badge_key_configured || '🔑 Anahtar Tanımlı') : (i18n.badge_required_env || '⚠️ API Anahtarı Gerekli')}
                            </span>
                        ` : `
                            <span style="font-size:10.5px; background:rgba(123,104,238,0.12); color:#c4b5fd; border:1px solid rgba(123,104,238,0.3); padding:2px 7px; border-radius:4px; font-weight:500;">
                                ${i18n.badge_ready || '⚡ Hemen Hazır (Harici Anahtar Gerekmez)'}
                            </span>
                        `}
                    </div>
                </div>

                <!-- Kart Alt Eylemler -->
                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid rgba(255,255,255,0.06); padding-top:10px; margin-top:4px;">
                    <span style="font-size:11px; display:inline-flex; align-items:center; gap:5px; color:${isEnabled ? '#3fb950' : 'var(--text-muted)'}; font-weight:600;">
                        <span style="width:7px; height:7px; border-radius:50%; background:${isEnabled ? '#3fb950' : 'var(--text-muted)'};"></span>
                        ${isEnabled ? (i18n.status_enabled || 'Aktif') : (i18n.status_disabled || 'Pasif')}
                    </span>
                    <button type="button" onclick="openMcpConfig(${item.id})" class="cu-btn-sm" style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:var(--text-white); font-size:11px; padding:4px 10px; border-radius:5px; cursor:pointer;" title="Yapılandırma & API Anahtarları">
                        <span>⚙️</span>
                        <span>${hasEnvReq ? (i18n.btn_config || 'Ayarlar') : (i18n.btn_details || 'Detaylar')}</span>
                    </button>
                </div>
            </div>
        `;
    }).join('');
};

window.toggleProjectMcp = async function(catalogId, isChecked) {
    if (!window.CURRENT_MCP_PROJECT_ID) return;
    const item = (window.MCP_CATALOG_DATA || []).find(x => parseInt(x.id, 10) === parseInt(catalogId, 10));
    const i18n = window.MCP_I18N || {};

    try {
        const fd = new FormData();
        fd.append('project_id', window.CURRENT_MCP_PROJECT_ID);
        fd.append('mcp_catalog_id', catalogId);
        fd.append('is_enabled', isChecked ? '1' : '0');

        const res = await fetch('/taskmanager/ajax?action=toggle_project_mcp', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            if (item) item.is_enabled = isChecked ? 1 : 0;
            renderMcpGrid();
            const name = item ? (item.display_name || item.name) : 'MCP';
            const actionText = isChecked ? (i18n.active_toast || 'aktif edildi 🚀') : (i18n.deactive_toast || 'devre dışı bırakıldı.');
            showToast(`${name} ${actionText}`, 'success');

            // Eğer açıldıysa ve doldurulmamış API anahtarı varsa kullanıcıya kolaylık için ayar modalını aç
            const fields = item && (Array.isArray(item.required_env_fields) && item.required_env_fields.length > 0 ? item.required_env_fields : (Array.isArray(item.required_env) ? item.required_env.map(k => typeof k === 'string' ? { key: k, required: true } : k) : []));
            const hasEnvReq = fields && fields.length > 0;
            const envFilled = hasEnvReq && fields.every(f => {
                if (f.required === false) return true;
                return item.env_values && item.env_values[f.key] && String(item.env_values[f.key]).trim().length > 0;
            });
            if (isChecked && hasEnvReq && !envFilled) {
                setTimeout(() => openMcpConfig(catalogId), 250);
            }
        } else {
            showToast(data.message || 'Durum değiştirilemedi.', 'error');
            renderMcpGrid();
        }
    } catch (e) {
        console.error('toggleProjectMcp error:', e);
        showToast('Bağlantı hatası', 'error');
        renderMcpGrid();
    }
};

window.openMcpConfig = function(catalogId) {
    const item = (window.MCP_CATALOG_DATA || []).find(x => parseInt(x.id, 10) === parseInt(catalogId, 10));
    if (!item) return;
    const i18n = window.MCP_I18N || {};

    document.getElementById('mcpConfigCatalogId').value = item.id;
    document.getElementById('mcpConfigIcon').textContent = window.getMcpDisplayIcon(item.icon);
    document.getElementById('mcpConfigTitle').textContent = `${item.display_name || item.name} Yapılandırması`;
    document.getElementById('mcpConfigKeyBadge').textContent = `mcp_key: ${item.mcp_key} (${item.package_type || 'npx'})`;
    document.getElementById('mcpConfigDesc').textContent = item.description || '';

    const container = document.getElementById('mcpConfigFieldsContainer');
    if (!container) return;
    container.innerHTML = '';

    const fields = (Array.isArray(item.required_env_fields) && item.required_env_fields.length > 0)
        ? item.required_env_fields
        : (Array.isArray(item.required_env) ? item.required_env.map(k => typeof k === 'string' ? { key: k, label: k, required: true } : k) : []);

    const currentEnv = item.env_values || {};

    if (fields.length === 0) {
        container.innerHTML = `
            <div style="background:rgba(46,160,67,0.1); border:1px solid rgba(46,160,67,0.3); border-radius:8px; padding:14px 16px; color:#3fb950; font-size:12.5px; display:flex; align-items:flex-start; gap:12px;">
                <span style="font-size:22px; line-height:1;">⚡</span>
                <div>
                    <strong style="display:block; margin-bottom:4px; font-size:13px; color:#3fb950;">${i18n.badge_ready || 'Hemen Hazır'}</strong>
                    <div style="color:var(--text-secondary); font-size:12px; line-height:1.4;">${i18n.no_external_needed_info || 'Bu araç yerel derleyici ve ortam araçlarıyla çalışır. Harici bir API anahtarı veya servis tokenı gerekmez.'}</div>
                </div>
            </div>
        `;
    } else {
        container.innerHTML = fields.map(field => {
            const envKey = field.key;
            const label = field.label || envKey;
            const val = currentEnv[envKey] || '';
            const isRequired = field.required !== false;
            const isSecret = field.type === 'password' || (!field.type && (envKey.includes('TOKEN') || envKey.includes('KEY') || envKey.includes('SECRET') || envKey.includes('PASSWORD')));
            const placeholder = field.placeholder || `${envKey} ${i18n.enter_value || 'değerini giriniz...'}`;

            return `
                <div class="cu-form-group" style="margin-bottom:0;">
                    <label style="font-size:11.5px; font-weight:600; color:var(--text-white); display:flex; justify-content:space-between; align-items:center;">
                        <span>${label} <code style="font-size:10px; color:#c4b5fd; background:rgba(123,104,238,0.15); padding:1px 5px; border-radius:3px; margin-left:6px; font-family:monospace;">${envKey}</code></span>
                        <span style="color:${isRequired ? '#ff7675' : 'var(--text-muted)'}; font-size:10.5px; font-weight:500;">
                            ${isRequired ? (i18n.field_required || 'Zorunlu') : (i18n.field_optional || 'İsteğe Bağlı')}
                        </span>
                    </label>
                    <div style="position:relative; margin-top:5px;">
                        <input type="${isSecret ? 'password' : 'text'}" 
                               id="env_input_${envKey}" 
                               data-env-key="${envKey}"
                               value="${val}" 
                               placeholder="${placeholder}" 
                               class="cu-form-control" 
                               style="width:100%; height:36px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 ${isSecret ? '36px' : '12px'} 0 12px; color:var(--text-white); font-size:12.5px; font-family:monospace; box-sizing:border-box;">
                        ${isSecret ? `
                            <button type="button" onclick="togglePassVisibility('env_input_${envKey}', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:4px;">👁️</button>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    openModal('modalMcpConfig');
};

window.handleMcpConfigSubmit = async function(e) {
    e.preventDefault();
    const catalogId = document.getElementById('mcpConfigCatalogId')?.value;
    if (!catalogId || !window.CURRENT_MCP_PROJECT_ID) return;
    const i18n = window.MCP_I18N || {};

    const item = (window.MCP_CATALOG_DATA || []).find(x => parseInt(x.id, 10) === parseInt(catalogId, 10));
    const fields = item && ((Array.isArray(item.required_env_fields) && item.required_env_fields.length > 0)
        ? item.required_env_fields
        : (Array.isArray(item.required_env) ? item.required_env.map(k => typeof k === 'string' ? { key: k } : k) : []));

    const envValues = {};
    if (fields) {
        fields.forEach(f => {
            const input = document.getElementById(`env_input_${f.key}`);
            if (input) {
                envValues[f.key] = input.value.trim();
            }
        });
    }

    const btn = document.getElementById('btnSaveMcpConfig');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = 'Kaydediliyor...';
    }

    try {
        const fd = new FormData();
        fd.append('project_id', window.CURRENT_MCP_PROJECT_ID);
        fd.append('mcp_catalog_id', catalogId);
        fd.append('is_enabled', '1'); // Ayar kaydedildiğinde otomatik aktif et
        fd.append('env_values', JSON.stringify(envValues));

        const res = await fetch('/taskmanager/ajax?action=save_project_mcp', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast(i18n.save_success || 'MCP ayarları başarıyla kaydedildi!', 'success');
            if (item) {
                item.is_enabled = 1;
                item.env_values = envValues;
            }
            closeModal('modalMcpConfig');
            renderMcpGrid();
        } else {
            showToast(data.message || 'Kayıt sırasında hata oluştu.', 'error');
        }
    } catch (err) {
        console.error('handleMcpConfigSubmit error:', err);
        showToast('Sunucu bağlantı hatası', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
};

window.triggerMcpSync = async function() {
    if (!window.CURRENT_MCP_PROJECT_ID) return;
    const btn = document.getElementById('btnSyncMcpFiles');
    const icon = document.getElementById('mcpSyncIcon');
    if (icon) icon.style.animation = 'spin 1s linear infinite';
    if (btn) btn.disabled = true;

    try {
        const fd = new FormData();
        fd.append('project_id', window.CURRENT_MCP_PROJECT_ID);
        const res = await fetch('/taskmanager/ajax?action=sync_project_mcps', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            const count = data.result?.synced_count ?? 0;
            showToast(`✅ MCP yapılandırmaları senkronize edildi! (${count} aktif araç .agents/mcp_config.json dosyasına yazıldı)`, 'success');
        } else {
            showToast(data.message || 'Senkronizasyon başarısız oldu.', 'error');
        }
    } catch (e) {
        console.error('triggerMcpSync error:', e);
        showToast('Bağlantı hatası', 'error');
    } finally {
        if (icon) icon.style.animation = '';
        if (btn) btn.disabled = false;
    }
};
