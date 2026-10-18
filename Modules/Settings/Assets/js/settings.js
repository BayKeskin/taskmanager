// Sekme Değiştirici
function switchSettingsTab(tabId, btn) {
    document.querySelectorAll('.cu-settings-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.cu-settings-tab-pane').forEach(p => p.classList.remove('active'));

    btn.classList.add('active');
    const pane = document.getElementById(tabId);
    if (pane) pane.classList.add('active');
}

// Şifre Göster / Gizle
function togglePasswordVisibility(inputId, btn) {
    const el = document.getElementById(inputId);
    if (!el) return;
    if (el.type === 'password') {
        el.type = 'text';
        btn.innerText = '🙈';
    } else {
        el.type = 'password';
        btn.innerText = '👁️';
    }
}

// Ayarları Kaydet
async function handleSaveSettings(e) {
    if (e) e.preventDefault();

    const form = document.getElementById('formSystemSettings');
    const fd = new FormData(form);

    const btnTop = document.getElementById('btnSaveSettingsTop');
    const btnBottom = document.getElementById('btnSaveSettingsBottom');
    if (btnTop) btnTop.disabled = true;
    if (btnBottom) btnBottom.disabled = true;

    try {
        const res = await fetch('/taskmanager/ajax?action=save_system_settings', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (btnTop) btnTop.disabled = false;
        if (btnBottom) btnBottom.disabled = false;

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(<?= json_encode(__('Settings::settings_saved_success')) ?>, 'success');
            } else {
                alert(<?= json_encode(__('Settings::settings_saved_success')) ?>);
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || <?= json_encode(__('Settings::settings_saved_fail')) ?>, 'error');
            } else {
                alert('Hata: ' + (data.message || <?= json_encode(__('Settings::settings_saved_fail')) ?>));
            }
        }
    } catch (err) {
        if (btnTop) btnTop.disabled = false;
        if (btnBottom) btnBottom.disabled = false;
        alert('Sunucu bağlantı hatası: ' + err.message);
    }
}

// Telegram Bağlantı Testi
async function handleTestTelegram() {
    const btn = document.getElementById('btnTestTelegram');
    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Test ediliyor...';
    }

    try {
        const res = await fetch('/taskmanager/ajax?action=test_telegram_connection');
        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>📡</span> <span>Bağlantıyı Test Et</span>';
        }

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || '✅ Bağlantı başarılı!', 'success');
            } else {
                alert(data.message || 'Bağlantı başarılı!');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || '❌ Bağlantı kurulamadı.', 'error');
            } else {
                alert(data.message || 'Bağlantı kurulamadı.');
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>📡</span> <span>Bağlantıyı Test Et</span>';
        }
        alert('Bağlantı testi hatası: ' + err.message);
    }
}

// SMTP Bağlantı Testi
async function handleTestSmtp() {
    const btn = document.getElementById('btnTestSmtp');
    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Test ediliyor...';
    }

    try {
        const res = await fetch('/taskmanager/ajax?action=test_smtp_connection');
        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>✉️</span> <span>SMTP Bağlantısını Test Et</span>';
        }

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || '✅ SMTP bağlantısı başarılı!', 'success');
            } else {
                alert(data.message || 'SMTP bağlantısı başarılı!');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || '❌ SMTP bağlantı hatası.', 'error');
            } else {
                alert(data.message || 'SMTP bağlantı hatası.');
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>✉️</span> <span>SMTP Bağlantısını Test Et</span>';
        }
        alert('SMTP test hatası: ' + err.message);
    }
}

// Slack & Discord Webhook Testi
async function handleTestWebhook(type) {
    const btnId = (type === 'slack') ? 'btnTestSlack' : 'btnTestDiscord';
    const action = (type === 'slack') ? 'test_slack_webhook' : 'test_discord_webhook';
    const label = (type === 'slack') ? 'Slack Test Gönder' : 'Discord Test Gönder';
    const btn = document.getElementById(btnId);

    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Gönderiliyor...';
    }

    try {
        const res = await fetch('/taskmanager/ajax?action=' + action);
        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = (type === 'slack' ? '<span>💬</span> ' : '<span>🎮</span> ') + '<span>' + label + '</span>';
        }

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || '✅ Webhook mesajı iletildi!', 'success');
            } else {
                alert(data.message || 'Webhook mesajı iletildi!');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || '❌ Webhook iletilemedi.', 'error');
            } else {
                alert(data.message || 'Webhook iletilemedi.');
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = (type === 'slack' ? '<span>💬</span> ' : '<span>🎮</span> ') + '<span>' + label + '</span>';
        }
        alert('Webhook test hatası: ' + err.message);
    }
}

// Modül Durumu Aç / Kapat (Module Manager)
async function toggleModuleStatus(slug, isActive) {
    const badge = document.getElementById('badge-status-slug-' + slug) || document.getElementById('badge-status-' + slug);
    const fd = new FormData();
    fd.append('slug', slug);
    fd.append('status', isActive ? '1' : '0');

    try {
        const res = await fetch('/taskmanager/ajax?action=toggle_module_status', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            if (badge) {
                badge.innerText = isActive ? '<?= __('Settings::settings_modules_active_badge', 'Aktif') ?>' : '<?= __('Settings::settings_modules_passive_badge', 'Pasif') ?>';
                badge.style.color = isActive ? '#00b894' : '#eb5757';
            }
            if (typeof showToast === 'function') {
                showToast(data.message || '<?= __('Settings::settings_modules_toggle_success', 'Modül durumu güncellendi.') ?>', 'success');
            }
        } else {
            alert(data.message || '<?= __('Settings::settings_modules_toggle_fail', 'Modül durumu güncellenemedi.') ?>');
            // Geri al
            const toggle = document.getElementById('toggle-mod-' + slug);
            if (toggle) toggle.checked = !isActive;
        }
    } catch (err) {
        alert('Modül toggle hatası: ' + err.message);
        const toggle = document.getElementById('toggle-mod-' + slug);
        if (toggle) toggle.checked = !isActive;
    }
}

// URL tab parametresi desteği (?tab=modules vb.)
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    if (tabParam) {
        const tabPaneId = 'tab-' + tabParam;
        const targetBtn = document.querySelector(`[onclick*="${tabPaneId}"]`);
        if (targetBtn) {
            targetBtn.click();
        }
    }
});
