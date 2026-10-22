// Modules/Settings/Assets/js/settings.js

const settingsBaseUrl = (typeof window.BASE_URL !== 'undefined') ? window.BASE_URL.replace(/\/+$/, '') : '';

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
    if (!form) return;
    const fd = new FormData(form);

    const btnTop = document.getElementById('btnSaveSettingsTop');
    const btnBottom = document.getElementById('btnSaveSettingsBottom');
    if (btnTop) btnTop.disabled = true;
    if (btnBottom) btnBottom.disabled = true;

    const i18n = window.SettingsI18n || {};

    try {
        const res = await fetch(`${settingsBaseUrl}/ajax?action=save_system_settings`, {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (btnTop) btnTop.disabled = false;
        if (btnBottom) btnBottom.disabled = false;

        if (data.success) {
            const successMsg = data.message || i18n.saved_success || 'Settings saved successfully';
            if (typeof showToast === 'function') {
                showToast(successMsg, 'success');
            } else {
                alert(successMsg);
            }
        } else {
            const failMsg = data.message || i18n.saved_fail || 'Failed to save settings';
            if (typeof showToast === 'function') {
                showToast(failMsg, 'error');
            } else {
                alert(failMsg);
            }
        }
    } catch (err) {
        if (btnTop) btnTop.disabled = false;
        if (btnBottom) btnBottom.disabled = false;
        alert((i18n.server_error || 'Server error: ') + err.message);
    }
}

// Telegram Bağlantı Testi
async function handleTestTelegram() {
    const btn = document.getElementById('btnTestTelegram');
    const i18n = window.SettingsI18n || {};
    if (btn) {
        btn.disabled = true;
        btn.innerText = i18n.test_testing || 'Testing...';
    }

    try {
        const res = await fetch(`${settingsBaseUrl}/ajax?action=test_telegram_connection`);
        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>📡</span> <span>' + (i18n.test_telegram_btn || 'Test Connection') + '</span>';
        }

        if (data.success) {
            const msg = data.message || i18n.test_telegram_ok || 'Connection successful';
            if (typeof showToast === 'function') {
                showToast(msg, 'success');
            } else {
                alert(msg);
            }
        } else {
            const msg = data.message || i18n.test_telegram_fail || 'Connection failed';
            if (typeof showToast === 'function') {
                showToast(msg, 'error');
            } else {
                alert(msg);
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>📡</span> <span>' + (i18n.test_telegram_btn || 'Test Connection') + '</span>';
        }
        alert((i18n.test_telegram_err || 'Connection test error: ') + err.message);
    }
}

// SMTP Bağlantı Testi
async function handleTestSmtp() {
    const btn = document.getElementById('btnTestSmtp');
    const i18n = window.SettingsI18n || {};
    if (btn) {
        btn.disabled = true;
        btn.innerText = i18n.test_testing || 'Testing...';
    }

    try {
        const res = await fetch(`${settingsBaseUrl}/ajax?action=test_smtp_connection`);
        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>✉️</span> <span>' + (i18n.test_smtp_btn || 'Test SMTP') + '</span>';
        }

        if (data.success) {
            const msg = data.message || i18n.test_smtp_ok || 'SMTP connection successful';
            if (typeof showToast === 'function') {
                showToast(msg, 'success');
            } else {
                alert(msg);
            }
        } else {
            const msg = data.message || i18n.test_smtp_fail || 'SMTP connection error';
            if (typeof showToast === 'function') {
                showToast(msg, 'error');
            } else {
                alert(msg);
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>✉️</span> <span>' + (i18n.test_smtp_btn || 'Test SMTP') + '</span>';
        }
        alert((i18n.test_smtp_err || 'SMTP test error: ') + err.message);
    }
}

// Slack & Discord Webhook Testi
async function handleTestWebhook(type) {
    const btnId = (type === 'slack') ? 'btnTestSlack' : 'btnTestDiscord';
    const action = (type === 'slack') ? 'test_slack_webhook' : 'test_discord_webhook';
    const i18n = window.SettingsI18n || {};
    const label = (type === 'slack') 
        ? (i18n.test_slack_btn || 'Send Slack Test') 
        : (i18n.test_discord_btn || 'Send Discord Test');
    const btn = document.getElementById(btnId);

    if (btn) {
        btn.disabled = true;
        btn.innerText = i18n.test_sending || 'Sending...';
    }

    try {
        const res = await fetch(`${settingsBaseUrl}/ajax?action=` + action);
        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = (type === 'slack' ? '<span>💬</span> ' : '<span>🎮</span> ') + '<span>' + label + '</span>';
        }

        if (data.success) {
            const msg = data.message || i18n.test_webhook_ok || 'Webhook sent';
            if (typeof showToast === 'function') {
                showToast(msg, 'success');
            } else {
                alert(msg);
            }
        } else {
            const msg = data.message || i18n.test_webhook_fail || 'Webhook failed';
            if (typeof showToast === 'function') {
                showToast(msg, 'error');
            } else {
                alert(msg);
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = (type === 'slack' ? '<span>💬</span> ' : '<span>🎮</span> ') + '<span>' + label + '</span>';
        }
        alert((i18n.test_webhook_err || 'Webhook error: ') + err.message);
    }
}

// Modül Durumu Aç / Kapat (Module Manager)
async function toggleModuleStatus(slug, isActive) {
    const badge = document.getElementById('badge-status-slug-' + slug) || document.getElementById('badge-status-' + slug);
    const i18n = window.SettingsI18n || {};
    const fd = new FormData();
    fd.append('slug', slug);
    fd.append('status', isActive ? '1' : '0');

    try {
        const res = await fetch(`${settingsBaseUrl}/ajax?action=toggle_module_status`, {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            if (badge) {
                badge.innerText = isActive 
                    ? (i18n.active_badge || 'Active') 
                    : (i18n.passive_badge || 'Inactive');
                badge.style.color = isActive ? '#00b894' : '#eb5757';
            }
            if (typeof showToast === 'function') {
                showToast(data.message || i18n.toggle_success || 'Module status updated', 'success');
            }
        } else {
            alert(data.message || i18n.toggle_fail || 'Failed to update module status');
            const toggle = document.getElementById('toggle-mod-' + slug);
            if (toggle) toggle.checked = !isActive;
        }
    } catch (err) {
        alert((i18n.module_toggle_err || 'Module toggle error: ') + err.message);
        const toggle = document.getElementById('toggle-mod-' + slug);
        if (toggle) toggle.checked = !isActive;
    }
}

// Event Listeners bağlama (Zero Inline JS)
document.addEventListener('DOMContentLoaded', () => {
    // 1. Sekme butonları
    document.querySelectorAll('.cu-settings-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            if (tabId) {
                switchSettingsTab(tabId, btn);
            }
        });
    });

    // 2. Şifre göster / gizle butonları
    document.querySelectorAll('.cu-toggle-pwd-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            if (targetId) {
                togglePasswordVisibility(targetId, btn);
            }
        });
    });

    // 3. Form submit ve Kaydet butonları
    const form = document.getElementById('formSystemSettings');
    if (form) {
        form.addEventListener('submit', handleSaveSettings);
    }
    const btnSaveTop = document.getElementById('btnSaveSettingsTop');
    if (btnSaveTop) {
        btnSaveTop.addEventListener('click', handleSaveSettings);
    }

    // 4. Test butonları
    const btnTelegram = document.getElementById('btnTestTelegram');
    if (btnTelegram) {
        btnTelegram.addEventListener('click', handleTestTelegram);
    }

    const btnSmtp = document.getElementById('btnTestSmtp');
    if (btnSmtp) {
        btnSmtp.addEventListener('click', handleTestSmtp);
    }

    const btnSlack = document.getElementById('btnTestSlack');
    if (btnSlack) {
        btnSlack.addEventListener('click', () => handleTestWebhook('slack'));
    }

    const btnDiscord = document.getElementById('btnTestDiscord');
    if (btnDiscord) {
        btnDiscord.addEventListener('click', () => handleTestWebhook('discord'));
    }

    // 5. Modül toggle checkboxları
    document.querySelectorAll('.cu-mod-toggle-input').forEach(input => {
        input.addEventListener('change', () => {
            const slug = input.getAttribute('data-slug');
            if (slug) {
                toggleModuleStatus(slug, input.checked);
            }
        });
    });

    // 7. AI Accounts Vault handlers
    const btnAddAi = document.getElementById('btnAddNewAiAccount');
    if (btnAddAi) {
        btnAddAi.addEventListener('click', async () => {
            btnAddAi.disabled = true;
            const originalHtml = btnAddAi.innerHTML;
            btnAddAi.innerHTML = '<span>⏳</span> ' + (window.SettingsI18n?.test_testing || '');

            try {
                // 1. Yetkilendirme linkini al ve arka plan dinleyicisini başlat
                const res = await fetch(`${settingsBaseUrl}/ajax?action=start_ai_account_oauth`, {
                    method: 'POST'
                });
                const data = await res.json();

                if (!data.success || !data.auth_url) {
                    btnAddAi.disabled = false;
                    btnAddAi.innerHTML = originalHtml;
                    alert(data.message || window.SettingsI18n?.ai_generic_error || '');
                    return;
                }

                // 2. Google OAuth penceresini aç
                const authWindow = window.open(data.auth_url, '_blank', 'width=600,height=700');
                if (typeof showToast === 'function') {
                    showToast(window.SettingsI18n?.ai_oauth_init || 'Google login window opened...', 'info');
                }

                // 3. Arka planda polling ile sonucun tamamlanmasını dinle (max 3 dakika)
                let pollAttempts = 0;
                const maxPollAttempts = 90; // 90 x 2s = 180s
                const pollInterval = setInterval(async () => {
                    pollAttempts++;
                    try {
                        const pollRes = await fetch(`${settingsBaseUrl}/ajax?action=poll_oauth_result`, {
                            method: 'POST'
                        });
                        const pollData = await pollRes.json();

                        if (pollData.success && pollData.status === 'success') {
                            clearInterval(pollInterval);
                            btnAddAi.disabled = false;
                            btnAddAi.innerHTML = originalHtml;
                            if (authWindow && !authWindow.closed) {
                                authWindow.close();
                            }
                            if (typeof showToast === 'function') {
                                showToast(pollData.message, 'success');
                            } else {
                                alert(pollData.message);
                            }
                            renderAiAccountsTable(pollData.accounts || []);
                        } else if (!pollData.success && pollData.status !== 'pending' && pollData.message) {
                            clearInterval(pollInterval);
                            btnAddAi.disabled = false;
                            btnAddAi.innerHTML = originalHtml;
                            alert(pollData.message);
                        } else if (pollAttempts >= maxPollAttempts) {
                            clearInterval(pollInterval);
                            btnAddAi.disabled = false;
                            btnAddAi.innerHTML = originalHtml;
                            alert(window.SettingsI18n?.ai_oauth_timeout || 'Timeout: Login not completed.');
                        }
                    } catch (pollErr) {
                        // Sessiz devam et
                    }
                }, 2000);

            } catch (err) {
                btnAddAi.disabled = false;
                btnAddAi.innerHTML = originalHtml;
                alert((window.SettingsI18n?.server_error || '') + err.message);
            }
        });
    }

    const btnSyncAi = document.getElementById('btnSyncAiAccounts');
    if (btnSyncAi) {
        btnSyncAi.addEventListener('click', async () => {
            btnSyncAi.disabled = true;
            const originalText = btnSyncAi.innerHTML;
            btnSyncAi.innerHTML = '<span>⏳</span> ' + (window.SettingsI18n?.test_testing || '');

            try {
                const res = await fetch(`${settingsBaseUrl}/ajax?action=sync_local_vault_accounts`, {
                    method: 'POST'
                });
                const data = await res.json();
                btnSyncAi.disabled = false;
                btnSyncAi.innerHTML = originalText;

                if (data.success) {
                    if (typeof showToast === 'function') {
                        showToast(data.message, 'success');
                    } else {
                        alert(data.message);
                    }
                    renderAiAccountsTable(data.accounts || []);
                } else {
                    const errMsg = data.message || window.SettingsI18n?.ai_generic_error || '';
                    if (typeof showToast === 'function') {
                        showToast(errMsg, 'error');
                    } else {
                        alert(errMsg);
                    }
                }
            } catch (err) {
                btnSyncAi.disabled = false;
                btnSyncAi.innerHTML = originalText;
                alert((window.SettingsI18n?.server_error || '') + err.message);
            }
        });
    }

    // AI Accounts tablosu içindeki dinamik butonlar (Aktif Yap / Sil)
    const tableBody = document.getElementById('aiAccountsTableBody');
    if (tableBody) {
        tableBody.addEventListener('click', async (e) => {
            const btnActivate = e.target.closest('.btn-activate-ai-account');
            if (btnActivate) {
                const email = btnActivate.getAttribute('data-email');
                if (!email) return;
                btnActivate.disabled = true;
                try {
                    const fd = new FormData();
                    fd.append('email', email);
                    const res = await fetch(`${settingsBaseUrl}/ajax?action=activate_ai_account`, {
                        method: 'POST',
                        body: fd
                    });
                    const data = await res.json();
                    if (data.success) {
                        if (typeof showToast === 'function') {
                            showToast(data.message, 'success');
                        }
                        renderAiAccountsTable(data.accounts || []);
                    } else {
                        alert(data.message || window.SettingsI18n?.ai_generic_error || '');
                        btnActivate.disabled = false;
                    }
                } catch (err) {
                    alert((window.SettingsI18n?.server_error || '') + err.message);
                    btnActivate.disabled = false;
                }
                return;
            }

            const btnDelete = e.target.closest('.btn-delete-ai-account');
            if (btnDelete) {
                const id = btnDelete.getAttribute('data-id');
                if (!id) return;
                const confirmMsg = window.SettingsI18n?.ai_delete_confirm || '';
                if (!confirm(confirmMsg)) return;
                btnDelete.disabled = true;
                try {
                    const fd = new FormData();
                    fd.append('id', id);
                    const res = await fetch(`${settingsBaseUrl}/ajax?action=delete_ai_account`, {
                        method: 'POST',
                        body: fd
                    });
                    const data = await res.json();
                    if (data.success) {
                        if (typeof showToast === 'function') {
                            showToast(data.message, 'success');
                        }
                        renderAiAccountsTable(data.accounts || []);
                    } else {
                        alert(data.message || window.SettingsI18n?.ai_generic_error || '');
                        btnDelete.disabled = false;
                    }
                } catch (err) {
                    alert((window.SettingsI18n?.server_error || '') + err.message);
                    btnDelete.disabled = false;
                }
                return;
            }
        });
    }

    function initQuotaBars() {
        document.querySelectorAll('.cu-quota-bar-fill[data-quota-pct]').forEach(el => {
            const pct = el.getAttribute('data-quota-pct');
            el.style.width = pct + '%';
        });
    }
    initQuotaBars();

    function renderAiAccountsTable(accounts) {
        if (!tableBody) return;
        const i18n = window.SettingsI18n || {};
        if (!accounts || accounts.length === 0) {
            tableBody.innerHTML = `
                <tr id="rowAiAccountsEmpty">
                    <td colspan="5" class="cu-text-center cu-text-muted cu-py-20">
                        ${i18n.ai_empty_vault || ''}
                    </td>
                </tr>`;
            return;
        }

        let html = '';
        accounts.forEach(acc => {
            const pct = parseInt(acc.gemini_quota_pct || 0, 10);
            const statusClass = pct >= 50 ? 'cu-badge-success' : (pct > 0 ? 'cu-badge-warning' : 'cu-badge-danger');
            const fillClass = pct >= 50 ? 'fill-green' : (pct > 0 ? 'fill-yellow' : 'fill-red');
            const isActive = parseInt(acc.is_active || 0, 10) === 1;

            html += `
                <tr data-id="${acc.id}" data-email="${acc.email}">
                    <td>
                        <div class="cu-flex-col">
                            <span class="cu-font-bold">${acc.name || acc.email}</span>
                            <span class="cu-text-muted cu-text-xs">${acc.email}</span>
                        </div>
                    </td>
                    <td>
                        <div class="cu-quota-bar-wrap">
                            <div class="cu-quota-bar-track">
                                <div class="cu-quota-bar-fill ${fillClass}" data-quota-pct="${pct}"></div>
                            </div>
                            <span class="cu-quota-pct-label">%${pct}</span>
                        </div>
                    </td>
                    <td>
                        <span class="cu-badge ${statusClass}">
                            ${acc.status || 'HEALTHY'}
                        </span>
                    </td>
                    <td>
                        <span class="cu-text-xs cu-text-muted">${acc.reset_time || 'N/A'}</span>
                    </td>
                    <td class="cu-text-right cu-actions-cell">
                        ${isActive ? 
                            `<span class="cu-badge cu-badge-active-tag">${i18n.ai_active_tag || ''}</span>` : 
                            `<button type="button" class="cu-btn-xs cu-btn-primary btn-activate-ai-account" data-email="${acc.email}">${i18n.ai_activate_btn || ''}</button>`
                        }
                        <button type="button" class="cu-btn-xs cu-btn-danger btn-delete-ai-account" data-id="${acc.id}">✕</button>
                    </td>
                </tr>`;
        });
        tableBody.innerHTML = html;
        initQuotaBars();
    }
});

