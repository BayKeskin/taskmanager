<!-- 🧩 6. PROJE ÖZEL ANTIGRAVITY MCP MARKETPLACE MODALI -->
<div class="cu-modal-overlay" id="modalProjectMcp">
    <div class="cu-modal mcp-main-modal">
        
        <!-- Header -->
        <div class="cu-modal-header mcp-modal-header">
            <div class="mcp-header-left">
                <span class="mcp-header-icon">🧩</span>
                <div>
                    <div class="mcp-header-title-row">
                        <h3 class="mcp-header-title">
                            <?= __('Mcp::mcp_marketplace_title') ?>
                        </h3>
                        <span id="mcpModalProjectBadge" class="mcp-project-badge"></span>
                    </div>
                    <p class="mcp-header-desc">
                        <?= __('Mcp::mcp_marketplace_desc') ?>
                    </p>
                </div>
            </div>
            <div class="mcp-header-actions">
                <button type="button" id="btnSyncMcpFiles" class="cu-btn-sm mcp-sync-btn" title="<?= htmlspecialchars(__('Mcp::mcp_btn_sync')) ?>">
                    <span id="mcpSyncIcon">🔄</span>
                    <span><?= __('Mcp::mcp_btn_sync') ?></span>
                </button>
                <button type="button" class="mcp-close-btn" data-close-modal="modalProjectMcp">✕</button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="mcp-toolbar">
            <!-- Category Tabs -->
            <div id="mcpCategoryTabs" class="mcp-category-tabs">
                <button type="button" class="mcp-cat-pill active" data-cat="all">
                    <?= __('Mcp::mcp_tab_all') ?> (<span id="mcpCountAll">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="design">
                    <?= __('Mcp::mcp_tab_design') ?> (<span id="mcpCountDesign">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="database">
                    <?= __('Mcp::mcp_tab_database') ?> (<span id="mcpCountDatabase">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="payment">
                    <?= __('Mcp::mcp_tab_payment') ?> (<span id="mcpCountPayment">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="devops">
                    <?= __('Mcp::mcp_tab_devops') ?> (<span id="mcpCountDevops">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="mobile">
                    <?= __('Mcp::mcp_tab_mobile') ?> (<span id="mcpCountMobile">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="productivity">
                    <?= __('Mcp::mcp_tab_productivity') ?> (<span id="mcpCountProductivity">0</span>)
                </button>
                <button type="button" class="mcp-cat-pill" data-cat="custom">
                    <?= __('Mcp::mcp_tab_custom') ?> (<span id="mcpCountCustom">0</span>)
                </button>
            </div>

            <!-- Search Input -->
            <div class="mcp-search-wrap">
                <span class="mcp-search-icon">🔍</span>
                <input type="text" id="mcpSearchInput" placeholder="<?= htmlspecialchars(__('Mcp::mcp_search_placeholder')) ?>" class="mcp-search-input">
            </div>
        </div>

        <!-- Cards Grid Container -->
        <div id="mcpCardsScrollContainer" class="mcp-cards-scroll-container">
            <div id="mcpCardsGrid" class="mcp-cards-grid">
                <!-- Dinamik JavaScript ile doldurulacak -->
            </div>
            <div id="mcpCardsEmpty" class="mcp-cards-empty">
                <div class="mcp-cards-empty-icon">🔍</div>
                <div class="mcp-cards-empty-title"><?= __('Mcp::mcp_not_found') ?></div>
                <div class="mcp-cards-empty-desc"><?= __('Mcp::mcp_not_found_hint') ?></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="cu-modal-footer mcp-modal-footer">
            <div class="mcp-footer-info">
                <span>💡</span>
                <span><?= __('Mcp::mcp_footer_info') ?></span>
            </div>
            <button type="button" class="cu-btn-sm mcp-btn-footer-close" data-close-modal="modalProjectMcp">
                <?= __('Mcp::btn_cancel') ?>
            </button>
        </div>
    </div>
</div>

<!-- 🧩 7. MCP YAPILANDIRMA MODALI (API Keys / Env Değişkenleri) -->
<div class="cu-modal-overlay mcp-config-overlay" id="modalMcpConfig">
    <div class="cu-modal mcp-config-modal">
        <div class="cu-modal-header mcp-config-header">
            <div class="mcp-config-header-left">
                <span id="mcpConfigIcon" class="mcp-config-icon">⚙️</span>
                <div>
                    <h3 id="mcpConfigTitle" class="mcp-config-title"><?= __('Mcp::mcp_config_title') ?></h3>
                    <div id="mcpConfigKeyBadge" class="mcp-config-key-badge"></div>
                </div>
            </div>
            <button type="button" class="mcp-config-close-btn" data-close-modal="modalMcpConfig">✕</button>
        </div>
        <form id="formMcpConfig">
            <input type="hidden" id="mcpConfigCatalogId" value="">
            <div class="cu-modal-body mcp-config-body">
                <p id="mcpConfigDesc" class="mcp-config-desc"></p>
                <div id="mcpConfigFieldsContainer" class="mcp-config-fields">
                    <!-- Dinamik input alanları -->
                </div>
            </div>
            <div class="cu-modal-footer mcp-config-footer">
                <button type="button" class="cu-btn-sm mcp-config-btn-cancel" data-close-modal="modalMcpConfig"><?= __('Mcp::btn_cancel') ?></button>
                <button type="submit" id="btnSaveMcpConfig" class="cu-btn-sm mcp-config-btn-save">
                    <span>💾</span> <?= __('Mcp::btn_save') ?>
                </button>
            </div>
        </form>
    </div>
</div>


