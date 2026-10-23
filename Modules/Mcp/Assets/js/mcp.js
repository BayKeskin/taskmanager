// Modules/Mcp/Assets/js/mcp.js
const mcpBaseUrl = (window.BASE_URL || '').replace(/\/+$/, '');
window.MCP_CATALOG_DATA = [];
window.ACTIVE_MCP_CATEGORY = 'all';

window.openProjectMcpModal = async function(projectId) {
    const i18n = window.MCP_I18N || {};
    if (!projectId) {
        showToast(i18n.select_valid_project || 'Please select a valid project.', 'warning');
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
            <div class="mcp-loading-container">
                <div class="mcp-loading-spinner">🔄</div>
                <div class="mcp-loading-text">...</div>
            </div>
        `;
    }

    openModal('modalProjectMcp');

    try {
        const res = await fetch(`${mcpBaseUrl}/ajax?action=get_project_mcps&project_id=${projectId}`);
        const data = await res.json();

        if (data.success) {
            window.MCP_CATALOG_DATA = data.mcps || [];
            if (badge) {
                badge.textContent = `${data.project.title} (${data.project.project_code})`;
            }
            updateMcpCategoryCounts();
            renderMcpGrid();
        } else {
            showToast(data.message || i18n.catalog_load_fail || 'Failed', 'error');
            if (grid) grid.innerHTML = `<div class="mcp-error-container">${data.message || 'Error'}</div>`;
        }
    } catch (e) {
        console.error('openProjectMcpModal error:', e);
        showToast(i18n.server_error || 'Server error', 'error');
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
                    <div class="mcp-card-header">
                        <div class="mcp-card-title-group">
                            <span class="mcp-card-icon">${displayIcon}</span>
                            <div>
                                <h4 class="mcp-card-name">
                                    ${item.display_name || item.name}
                                </h4>
                                <span class="mcp-card-cat-badge" style="color:${catColor};">
                                    ${item.category}
                                </span>
                            </div>
                        </div>
                        <label class="mcp-toggle-switch" title="${isEnabled ? (i18n.status_disabled || 'Disabled') : (i18n.status_enabled || 'Enabled')}">
                            <input type="checkbox" ${isEnabled ? 'checked' : ''} onchange="toggleProjectMcp(${item.id}, this.checked)">
                            <span class="mcp-slider"></span>
                        </label>
                    </div>

                    <!-- Açıklama -->
                    <p class="mcp-card-desc">
                        ${item.description || ''}
                    </p>

                    <!-- Rozetler -->
                    <div class="mcp-card-tags">
                        <span class="mcp-tag-package">
                            ${item.package_type || 'npx'}
                        </span>
                        ${hasEnvReq ? `
                            <span class="mcp-tag-status ${envFilled ? 'configured' : 'warning'}">
                                ${envFilled ? (i18n.badge_key_configured || 'Key Configured') : (i18n.badge_required_env || 'Key Required')}
                            </span>
                        ` : `
                            <span class="mcp-tag-status ready">
                                ${i18n.badge_ready || 'Ready'}
                            </span>
                        `}
                    </div>
                </div>

                <!-- Kart Alt Eylemler -->
                <div class="mcp-card-footer">
                    <span class="mcp-card-status-indicator" style="color:${isEnabled ? '#3fb950' : 'var(--text-muted)'};">
                        <span class="mcp-status-dot ${isEnabled ? 'active' : 'inactive'}"></span>
                        ${isEnabled ? (i18n.status_enabled || 'Active') : (i18n.status_disabled || 'Inactive')}
                    </span>
                    <button type="button" onclick="openMcpConfig(${item.id})" class="cu-btn-sm mcp-card-btn-config" title="Config">
                        <span>⚙️</span>
                        <span>${hasEnvReq ? (i18n.btn_config || 'Config') : (i18n.btn_details || 'Details')}</span>
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

        const res = await fetch(`${mcpBaseUrl}/ajax?action=toggle_project_mcp`, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            if (item) item.is_enabled = isChecked ? 1 : 0;
            renderMcpGrid();
            const name = item ? (item.display_name || item.name) : 'MCP';
            const actionText = isChecked ? (i18n.active_toast || 'activated') : (i18n.deactive_toast || 'deactivated');
            const toastMsg = `${name} ${actionText}`;
            showToast(toastMsg, 'success');

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
            const failMsg = data.message || 'Status change failed';
            showToast(failMsg, 'error');
            renderMcpGrid();
        }
    } catch (e) {
        console.error('toggleProjectMcp error:', e);
        const serverErr = i18n.server_error || 'Connection error';
        showToast(serverErr, 'error');
        renderMcpGrid();
    }
};

window.openMcpConfig = function(catalogId) {
    const item = (window.MCP_CATALOG_DATA || []).find(x => parseInt(x.id, 10) === parseInt(catalogId, 10));
    if (!item) return;
    const i18n = window.MCP_I18N || {};

    document.getElementById('mcpConfigCatalogId').value = item.id;
    document.getElementById('mcpConfigIcon').textContent = window.getMcpDisplayIcon(item.icon);
    const suffix = i18n.config_title_suffix || 'Config';
    document.getElementById('mcpConfigTitle').textContent = `${item.display_name || item.name} ${suffix}`;
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
            <div class="mcp-config-info-box">
                <span class="mcp-config-info-box-icon">⚡</span>
                <div>
                    <strong class="mcp-config-info-box-title">${i18n.badge_ready || 'Ready'}</strong>
                    <div class="mcp-config-info-box-desc">${i18n.no_external_needed_info || 'Ready'}</div>
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
            const placeholder = field.placeholder || `${envKey} ${i18n.enter_value || '...'}`;
            const padStyle = isSecret ? 'padding: 0 36px 0 12px;' : 'padding: 0 12px;';

            return `
                <div class="cu-form-group">
                    <label class="mcp-form-label-row">
                        <span>${label} <code class="mcp-key-tag">${envKey}</code></span>
                        <span class="mcp-req-indicator ${isRequired ? 'required' : 'optional'}">
                            ${isRequired ? (i18n.field_required || 'Required') : (i18n.field_optional || 'Optional')}
                        </span>
                    </label>
                    <div class="mcp-input-wrap">
                        <input type="${isSecret ? 'password' : 'text'}" 
                                id="env_input_${envKey}" 
                                data-env-key="${envKey}"
                                value="${val}" 
                                placeholder="${placeholder}" 
                                class="cu-form-control mcp-input-control" 
                                style="${padStyle}">
                        ${isSecret ? `
                            <button type="button" onclick="mcpTogglePassVisibility('env_input_${envKey}', this)" class="mcp-toggle-pass-btn">👁️</button>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    openModal('modalMcpConfig');
};

window.mcpTogglePassVisibility = function(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerText = '🙈';
    } else {
        input.type = 'password';
        btn.innerText = '👁️';
    }
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
        btn.innerHTML = '...';
    }

    try {
        const fd = new FormData();
        fd.append('project_id', window.CURRENT_MCP_PROJECT_ID);
        fd.append('mcp_catalog_id', catalogId);
        fd.append('is_enabled', '1'); // Ayar kaydedildiğinde otomatik aktif et
        fd.append('env_values', JSON.stringify(envValues));

        const res = await fetch(`${mcpBaseUrl}/ajax?action=save_project_mcp`, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            const successMsg = i18n.save_success || 'Saved';
            showToast(successMsg, 'success');
            if (item) {
                item.is_enabled = 1;
                item.env_values = envValues;
            }
            closeModal('modalMcpConfig');
            renderMcpGrid();
        } else {
            const saveFail = data.message || 'Save error';
            showToast(saveFail, 'error');
        }
    } catch (err) {
        console.error('handleMcpConfigSubmit error:', err);
        const serverErr = i18n.server_error || 'Server error';
        showToast(serverErr, 'error');
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
    const i18n = window.MCP_I18N || {};

    try {
        const fd = new FormData();
        fd.append('project_id', window.CURRENT_MCP_PROJECT_ID);
        const res = await fetch(`${mcpBaseUrl}/ajax?action=sync_project_mcps`, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            const count = data.result?.synced_count ?? 0;
            const syncNotice = `✅ ${i18n.sync_notice || 'Synced!'} (${count})`;
            showToast(syncNotice, 'success');
        } else {
            const syncFail = data.message || 'Sync failed';
            showToast(syncFail, 'error');
        }
    } catch (e) {
        console.error('triggerMcpSync error:', e);
        const connErr = i18n.server_error || 'Connection error';
        showToast(connErr, 'error');
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
            const isSafe = (typeof window.isSafeBackdropClick === 'function')
                ? window.isSafeBackdropClick(e, e.target)
                : true;
            if (isSafe && typeof closeModal === 'function') {
                closeModal(e.target.id);
            }
        }
    });
});
