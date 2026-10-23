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

// Global fetch interceptor: automatically normalize base URLs and append active language to /ajax and /api calls
(function() {
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        options = options || {};
        const lang = getAppLanguage();
        const base = typeof window.BASE_URL !== 'undefined' ? window.BASE_URL : '';

        if (typeof url === 'string') {
            // 1. Dinamik Base URL Normalizasyonu:
            // Eğer URL '/taskmanager/...' şeklinde sabit kalmışsa bunu dinamik BASE_URL ile değiştir.
            if (url.startsWith('/taskmanager/')) {
                url = (base ? base : '') + url.substring(12);
            } else if ((url.startsWith('/ajax') || url.startsWith('/api')) && base !== '') {
                url = base + url;
            }

            // 2. Çok Dilli (i18n) Parametre ve Başlık Enjeksiyonu
            if (url.includes('/ajax') || url.includes('/api')) {
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
let _modalMouseDownTarget = null;
document.addEventListener('mousedown', function(e) {
    _modalMouseDownTarget = e.target;
}, true);

window.isSafeBackdropClick = function(e, overlayEl) {
    if (!overlayEl || !e) return false;
    // Tıklama hedefi overlay olmalı VE fare basma başlangıcı da (mousedown) tam overlay üzerinde olmalı
    // Böylece textarea/input içinden metin seçip fareyi dışarıda bırakan kullanıcının modalı KAPANMAZ!
    return (e.target === overlayEl) && (_modalMouseDownTarget === overlayEl);
};

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
    if (id === 'modalNewTask') {
        modalNewTaskFiles = [];
        if (typeof renderModalNewTaskFiles === 'function') renderModalNewTaskFiles();
        aiArchitectFiles = [];
        if (typeof renderAiArchitectFiles === 'function') renderAiArchitectFiles();
        const inst = document.getElementById('aiArchitectInstruction');
        if (inst) inst.value = '';
    }
}

function openNewTaskModal() { 
    modalNewTaskFiles = [];
    if (typeof renderModalNewTaskFiles === 'function') renderModalNewTaskFiles();
    aiArchitectFiles = [];
    if (typeof renderAiArchitectFiles === 'function') renderAiArchitectFiles();
    const inst = document.getElementById('aiArchitectInstruction');
    if (inst) inst.value = '';
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
