<!-- 🧩 6. PROJE ÖZEL ANTIGRAVITY MCP MARKETPLACE MODALI -->
<div class="cu-modal-overlay" id="modalProjectMcp" onclick="if(event.target === this) closeModal('modalProjectMcp')">
    <div class="cu-modal" style="max-width:1100px; width:95vw; height:88vh; max-height:860px; display:flex; flex-direction:column; background:var(--bg-card); border:1px solid rgba(123,104,238,0.4); box-shadow:0 25px 60px rgba(0,0,0,0.7); padding:0; border-radius:12px; overflow:hidden;">
        
        <!-- Header -->
        <div class="cu-modal-header" style="padding:16px 22px; border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between; background:rgba(0,0,0,0.25); flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="font-size:24px;">🧩</span>
                <div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:0;">
                            <?= __('app.mcp_marketplace_title') ?>
                        </h3>
                        <span id="mcpModalProjectBadge" style="font-size:11.5px; font-weight:600; padding:2px 10px; border-radius:14px; background:rgba(123,104,238,0.18); border:1px solid rgba(123,104,238,0.45); color:#c4b5fd;"></span>
                    </div>
                    <p style="font-size:12px; color:var(--text-muted); margin:3px 0 0 0;">
                        <?= __('app.mcp_marketplace_desc') ?>
                    </p>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <button type="button" id="btnSyncMcpFiles" onclick="triggerMcpSync()" class="cu-btn-sm" style="display:inline-flex; align-items:center; gap:6px; background:rgba(46,160,67,0.15); border:1px solid rgba(46,160,67,0.4); color:#3fb950; font-weight:600; padding:7px 14px; border-radius:6px; cursor:pointer;" title="<?= htmlspecialchars(__('app.mcp_btn_sync')) ?>">
                    <span id="mcpSyncIcon">🔄</span>
                    <span><?= __('app.mcp_btn_sync') ?></span>
                </button>
                <button type="button" onclick="closeModal('modalProjectMcp')" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; padding:4px 8px; line-height:1;">✕</button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div style="padding:12px 22px; border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between; gap:14px; background:rgba(255,255,255,0.015); flex-wrap:wrap; flex-shrink:0;">
            <!-- Category Tabs -->
            <div id="mcpCategoryTabs" style="display:flex; align-items:center; gap:6px; overflow-x:auto; padding:2px 0;">
                <button type="button" class="mcp-cat-pill active" data-cat="all" onclick="selectMcpCategory('all')">
                    <?= __('app.mcp_tab_all') ?> (<span id="mcpCountAll">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="design" onclick="selectMcpCategory('design')">
                    <?= __('app.mcp_tab_design') ?> (<span id="mcpCountDesign">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="database" onclick="selectMcpCategory('database')">
                    <?= __('app.mcp_tab_database') ?> (<span id="mcpCountDatabase">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="payment" onclick="selectMcpCategory('payment')">
                    <?= __('app.mcp_tab_payment') ?> (<span id="mcpCountPayment">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="devops" onclick="selectMcpCategory('devops')">
                    <?= __('app.mcp_tab_devops') ?> (<span id="mcpCountDevops">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="mobile" onclick="selectMcpCategory('mobile')">
                    <?= __('app.mcp_tab_mobile') ?> (<span id="mcpCountMobile">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="productivity" onclick="selectMcpCategory('productivity')">
                    <?= __('app.mcp_tab_productivity') ?> (<span id="mcpCountProductivity">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="custom" onclick="selectMcpCategory('custom')">
                    <?= __('app.mcp_tab_custom') ?> (<span id="mcpCountCustom">0</span>)
                </button>
            </div>

            <!-- Search Input -->
            <div style="position:relative; min-width:240px; flex:1; max-width:320px;">
                <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:13px; color:var(--text-muted); pointer-events:none;">🔍</span>
                <input type="text" id="mcpSearchInput" oninput="filterMcpCards()" placeholder="<?= htmlspecialchars(__('app.mcp_search_placeholder')) ?>" style="width:100%; height:34px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px 0 32px; color:var(--text-white); font-size:12px; outline:none; box-sizing:border-box;">
            </div>
        </div>

        <!-- Cards Grid Container -->
        <div id="mcpCardsScrollContainer" style="flex:1; overflow-y:auto; padding:20px 22px; background:var(--bg-base);">
            <div id="mcpCardsGrid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(310px, 1fr)); gap:16px;">
                <!-- Dinamik JavaScript ile doldurulacak -->
            </div>
            <div id="mcpCardsEmpty" style="display:none; text-align:center; padding:60px 20px; color:var(--text-muted);">
                <div style="font-size:36px; margin-bottom:10px;">🔍</div>
                <div style="font-size:14px; font-weight:600; color:var(--text-white);"><?= __('app.mcp_not_found') ?></div>
                <div style="font-size:12px; margin-top:4px;"><?= __('app.mcp_not_found_hint') ?></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="cu-modal-footer" style="padding:12px 22px; border-top:1px solid var(--border-subtle); display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.2); flex-shrink:0;">
            <div style="font-size:11.5px; color:var(--text-muted); display:flex; align-items:center; gap:8px;">
                <span>💡</span>
                <span><?= __('app.mcp_footer_info') ?></span>
            </div>
            <button type="button" class="cu-btn-sm" onclick="closeModal('modalProjectMcp')" style="background:rgba(255,255,255,0.08); color:var(--text-white); padding:6px 18px; border-radius:6px; font-size:12px; cursor:pointer;">
                <?= __('app.btn_cancel') ?>
            </button>
        </div>
    </div>
</div>

<script>
window.MCP_I18N = {
    badge_required_env: <?= json_encode(__('app.mcp_badge_required_env'), JSON_UNESCAPED_UNICODE) ?>,
    badge_key_configured: <?= json_encode(__('app.mcp_badge_key_configured'), JSON_UNESCAPED_UNICODE) ?>,
    badge_ready: <?= json_encode(__('app.mcp_badge_ready'), JSON_UNESCAPED_UNICODE) ?>,
    btn_details: <?= json_encode(__('app.mcp_btn_details'), JSON_UNESCAPED_UNICODE) ?>,
    btn_config: <?= json_encode(__('app.mcp_btn_config'), JSON_UNESCAPED_UNICODE) ?>,
    status_enabled: <?= json_encode(__('app.mcp_status_enabled'), JSON_UNESCAPED_UNICODE) ?>,
    status_disabled: <?= json_encode(__('app.mcp_status_disabled'), JSON_UNESCAPED_UNICODE) ?>,
    field_required: <?= json_encode(__('app.mcp_field_required'), JSON_UNESCAPED_UNICODE) ?>,
    field_optional: <?= json_encode(__('app.mcp_field_optional'), JSON_UNESCAPED_UNICODE) ?>,
    enter_value: <?= json_encode(__('app.mcp_enter_value'), JSON_UNESCAPED_UNICODE) ?>,
    active_toast: <?= json_encode(__('app.mcp_active_toast'), JSON_UNESCAPED_UNICODE) ?>,
    deactive_toast: <?= json_encode(__('app.mcp_deactive_toast'), JSON_UNESCAPED_UNICODE) ?>,
    no_external_needed_info: <?= json_encode(__('app.mcp_no_external_needed_info'), JSON_UNESCAPED_UNICODE) ?>,
    not_found: <?= json_encode(__('app.mcp_not_found'), JSON_UNESCAPED_UNICODE) ?>,
    not_found_hint: <?= json_encode(__('app.mcp_not_found_hint'), JSON_UNESCAPED_UNICODE) ?>,
    sync_success: <?= json_encode(__('app.mcp_sync_success'), JSON_UNESCAPED_UNICODE) ?>,
    save_success: <?= json_encode(__('app.mcp_save_success'), JSON_UNESCAPED_UNICODE) ?>
};
</script>

<!-- 🧩 7. MCP YAPILANDIRMA MODALI (API Keys / Env Değişkenleri) -->
<div class="cu-modal-overlay" id="modalMcpConfig" onclick="if(event.target === this) closeModal('modalMcpConfig')" style="z-index:1100;">
    <div class="cu-modal" style="max-width:540px; width:92vw; background:var(--bg-card); border:1px solid rgba(123,104,238,0.5); box-shadow:0 25px 60px rgba(0,0,0,0.8); border-radius:12px; overflow:hidden;">
        <div class="cu-modal-header" style="padding:16px 20px; border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between; background:rgba(0,0,0,0.25);">
            <div style="display:flex; align-items:center; gap:10px;">
                <span id="mcpConfigIcon" style="font-size:22px;">⚙️</span>
                <div>
                    <h3 id="mcpConfigTitle" style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.mcp_config_title') ?></h3>
                    <div id="mcpConfigKeyBadge" style="font-size:11px; color:#c4b5fd; font-family:monospace;"></div>
                </div>
            </div>
            <button type="button" onclick="closeModal('modalMcpConfig')" style="background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer;">✕</button>
        </div>
        <form id="formMcpConfig" onsubmit="handleMcpConfigSubmit(event)">
            <input type="hidden" id="mcpConfigCatalogId" value="">
            <div class="cu-modal-body" style="padding:18px 20px; max-height:60vh; overflow-y:auto;">
                <p id="mcpConfigDesc" style="font-size:12px; color:var(--text-secondary); margin-bottom:16px; line-height:1.4;"></p>
                <div id="mcpConfigFieldsContainer" style="display:flex; flex-direction:column; gap:14px;">
                    <!-- Dinamik input alanları -->
                </div>
            </div>
            <div class="cu-modal-footer" style="padding:12px 20px; border-top:1px solid var(--border-subtle); display:flex; justify-content:flex-end; gap:10px; background:rgba(0,0,0,0.15);">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalMcpConfig')" style="background:rgba(255,255,255,0.06); color:var(--text-secondary); padding:6px 14px; border-radius:6px; font-size:12px;"><?= __('app.btn_cancel') ?></button>
                <button type="submit" id="btnSaveMcpConfig" class="cu-btn-sm" style="background:#7B68EE; color:#fff; border:none; padding:7px 18px; border-radius:6px; font-weight:600; font-size:12.5px; cursor:pointer;">
                    <span>💾</span> <?= __('app.modal_profile_save') ?>
                </button>
            </div>
        </form>
    </div>
</div>


