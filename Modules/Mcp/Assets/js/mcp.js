// Modules/Mcp/Assets/js/mcp.js
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

// Event Listeners Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Sync Button
    const syncBtn = document.getElementById('btnSyncMcpFiles');
    if (syncBtn) {
        syncBtn.addEventListener('click', function() {
            if (typeof window.triggerMcpSync === 'function') {
                window.triggerMcpSync();
            }
        });
    }

    // Category Tabs Delegation
    const catTabs = document.getElementById('mcpCategoryTabs');
    if (catTabs) {
        catTabs.addEventListener('click', function(e) {
            const btn = e.target.closest('.mcp-cat-pill');
            if (btn && btn.dataset.cat && typeof window.selectMcpCategory === 'function') {
                window.selectMcpCategory(btn.dataset.cat);
            }
        });
    }

    // Search Input
    const searchInput = document.getElementById('mcpSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            if (typeof window.filterMcpCards === 'function') {
                window.filterMcpCards();
            }
        });
    }

    // Config Form Submit
    const configForm = document.getElementById('formMcpConfig');
    if (configForm) {
        configForm.addEventListener('submit', function(e) {
            if (typeof window.handleMcpConfigSubmit === 'function') {
                window.handleMcpConfigSubmit(e);
            }
        });
    }

    // Modal Close Buttons (data-close-modal)
    document.addEventListener('click', function(e) {
        const closeBtn = e.target.closest('[data-close-modal]');
        if (closeBtn) {
            const modalId = closeBtn.getAttribute('data-close-modal');
            if (modalId && typeof closeModal === 'function') {
                closeModal(modalId);
            }
        } else if (e.target.classList.contains('cu-modal-overlay') && e.target.id) {
            if (typeof closeModal === 'function') {
                closeModal(e.target.id);
            }
        }
    });
});
