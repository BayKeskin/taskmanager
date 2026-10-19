<?php
// TaskManager Modern Dökümanlar & Zengin Metin Editörü (Docs WYSIWYG View)
$activeProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$selectedDocId = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : null;

$docController = new \Modules\Docs\Controllers\DocController();
$docData = $docController->index($activeProjectId, $selectedDocId);
extract($docData);
$isClient = $isClient ?? false;

$system->title = __('Docs::docs_title');
include dirname(__DIR__, 3) . '/Core/Views/header.php';
?>

<!-- ÜST GÖRÜNÜM ÇUBUĞU (VIEW HEADER) -->
<div class="cu-view-header">
    <div class="cu-view-title-wrap cu-docs-header-title">
        <span class="cu-docs-header-icon">📄</span>
        <span class="cu-docs-header-text"><?= $isClient ? __('Docs::view_client_docs') : __('Docs::view_docs') ?></span>
    </div>

    <div class="cu-filter-bar cu-docs-filter-bar">
        <button type="button" class="cu-btn-sm cu-mobile-docs-toggle cu-hidden">
            <span>📂</span> <span><?= __('Docs::docs_mobile_btn') ?></span>
        </button>
        <?php if (!$isClient): ?>
        <button type="button" class="cu-btn-quick-task cu-docs-btn-new cu-btn-trigger-new-doc">
            <span>➕</span> <?= __('Docs::docs_new_doc') ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- TaskManager DOCS İKİ PANELLİ ÇALIŞMA ALANI -->
<div class="cu-docs-container">
    
    <!-- SOL PANEL: DÖKÜMAN LİSTESİ / GEZGİN (SIDEBAR) -->
    <div id="cuDocsSidebar" class="cu-docs-sidebar">
        <!-- Panel Başlığı & Arama -->
        <div class="cu-docs-sidebar-header">
            <div class="cu-docs-sidebar-title-row">
                <span class="cu-docs-sidebar-heading"><?= __('Docs::docs_heading') ?></span>
                <?php if (!$isClient): ?>
                <button type="button" class="cu-docs-btn-new-page cu-btn-trigger-new-doc">
                    <?= __('Docs::docs_new_page') ?>
                </button>
                <?php endif; ?>
            </div>
            <div class="cu-docs-search-box">
                <span class="cu-docs-search-icon">🔍</span>
                <input type="text" id="docSearchInput" placeholder="<?= htmlspecialchars(__('Docs::docs_search_ph'), ENT_QUOTES, 'UTF-8') ?>" class="cu-form-control cu-docs-search-input">
            </div>
        </div>

        <!-- Dökümanlar Ağacı / Listesi -->
        <div id="docsListSidebar" class="cu-docs-list-scroll">
            <?php if (empty($docsList)): ?>
                <div class="cu-docs-empty-text">
                    <?= __('Docs::docs_empty') ?>
                </div>
            <?php else: ?>
                <?php foreach ($docsList as $doc): ?>
                    <div class="cu-doc-sidebar-item <?= ($firstDoc && $firstDoc->id == $doc->id) ? 'active' : '' ?>" 
                         id="docItem-<?= (int)$doc->id ?>" 
                         data-select-doc-id="<?= (int)$doc->id ?>">
                        <span class="cu-doc-icon-badge" id="docIconBadge-<?= (int)$doc->id ?>"><?= htmlspecialchars($doc->icon ?: '📄') ?></span>
                        <div class="cu-ellipsis">
                            <div class="cu-doc-item-title" id="docSidebarTitle-<?= (int)$doc->id ?>">
                                <?= htmlspecialchars($doc->title) ?>
                            </div>
                            <div class="cu-doc-item-sub">
                                <?php if ($doc->project_title): ?>
                                    <span>● <?= htmlspecialchars($doc->project_title) ?></span>
                                <?php else: ?>
                                    <span>● Workspace</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- SAĞ PANEL: TaskManager ZENGİN METİN EDİTÖRÜ (WYSIWYG) -->
    <div class="cu-docs-main-pane">
        <?php if ($firstDoc): ?>
            <!-- Doküman Üst Barı -->
            <div class="cu-docs-toolbar-top">
                <div class="cu-docs-title-wrapper">
                    <!-- Emoji Seçici -->
                    <button type="button" id="btnDocIcon" class="cu-docs-btn-icon" title="<?= htmlspecialchars(__('Docs::docs_icon_change'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($firstDoc->icon ?: '📄') ?>
                    </button>

                    <!-- Başlık Input -->
                    <input type="text" id="activeDocTitle" value="<?= htmlspecialchars($firstDoc->title) ?>" placeholder="<?= htmlspecialchars(__('Docs::docs_title_ph'), ENT_QUOTES, 'UTF-8') ?>" class="cu-docs-title-input">
                </div>

                <div class="cu-docs-actions-wrapper">
                    <!-- Görsel / Kaynak Kod Mod Butonu -->
                    <div class="cu-docs-mode-group">
                        <button type="button" id="btnModeVisual" class="cu-mode-btn active" title="<?= htmlspecialchars(__('Docs::docs_mode_visual'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= __('Docs::docs_mode_visual') ?>
                        </button>
                        <button type="button" id="btnModeSource" class="cu-mode-btn" title="<?= htmlspecialchars(__('Docs::docs_mode_source'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= __('Docs::docs_mode_source') ?>
                        </button>
                    </div>

                    <!-- Tam Ekran / Geniş Görünüm Toggle Butonu -->
                    <button type="button" id="btnToggleWidth" class="cu-mode-btn cu-docs-btn-toggle-width" title="<?= htmlspecialchars(__('Docs::docs_width_wide'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= __('Docs::docs_width_wide') ?>
                    </button>

                    <!-- Proje Seçici -->
                    <select id="activeDocProject" class="cu-form-control cu-docs-select-project">
                        <option value=""><?= __('Docs::docs_proj_workspace') ?></option>
                        <?php foreach ($allProjects as $proj): ?>
                            <option value="<?= (int)$proj->id ?>" <?= ($firstDoc->project_id == $proj->id) ? 'selected' : '' ?>>
                                📁 <?= htmlspecialchars($proj->title) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (!$isClient): ?>
                    <!-- Kayıt Durumu Göstergesi -->
                    <span id="docSaveStatus" class="cu-docs-save-status">
                        ✓ <?= __('Docs::docs_save_status_saved') ?>
                    </span>

                    <!-- Kaydet Butonu -->
                    <button type="button" id="btnSaveActiveDoc" class="cu-btn-sm cu-docs-btn-save">
                        <span>💾</span> <span><?= __('Docs::docs_btn_save') ?></span>
                    </button>

                    <!-- Sil Butonu -->
                    <button type="button" id="btnDeleteActiveDoc" class="cu-btn-sm cu-docs-btn-delete" title="<?= htmlspecialchars(__('Docs::docs_btn_delete'), ENT_QUOTES, 'UTF-8') ?>">
                        🗑️
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- EDİTÖR ALANI (QUILL WYSIWYG & SOURCE AREA) -->
            <div class="cu-docs-editor-container">
                <!-- Quill Editör Kutusu -->
                <div id="quillWrapper" class="cu-docs-quill-wrapper">
                    <!-- Quill Toolbar Alanı -->
                    <div id="quillToolbar">
                        <span class="ql-formats">
                            <select class="ql-header">
                                <option value="1"><?= __('Docs::docs_h1') ?></option>
                                <option value="2"><?= __('Docs::docs_h2') ?></option>
                                <option value="3"><?= __('Docs::docs_h3') ?></option>
                                <option selected><?= __('Docs::docs_normal') ?></option>
                            </select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-bold" title="<?= htmlspecialchars(__('Docs::docs_tool_bold')) ?>"></button>
                            <button class="ql-italic" title="<?= htmlspecialchars(__('Docs::docs_tool_italic')) ?>"></button>
                            <button class="ql-underline" title="<?= htmlspecialchars(__('Docs::docs_tool_underline')) ?>"></button>
                            <button class="ql-strike" title="<?= htmlspecialchars(__('Docs::docs_tool_strike')) ?>"></button>
                        </span>
                        <span class="ql-formats">
                            <select class="ql-color" title="<?= htmlspecialchars(__('Docs::docs_tool_color')) ?>"></select>
                            <select class="ql-background" title="<?= htmlspecialchars(__('Docs::docs_tool_bg')) ?>"></select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-list" value="ordered" title="<?= htmlspecialchars(__('Docs::docs_tool_ordered')) ?>"></button>
                            <button class="ql-list" value="bullet" title="<?= htmlspecialchars(__('Docs::docs_tool_bullet')) ?>"></button>
                            <button class="ql-list" value="check" title="<?= htmlspecialchars(__('Docs::docs_tool_check')) ?>"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-blockquote" title="<?= htmlspecialchars(__('Docs::docs_tool_quote')) ?>"></button>
                            <button class="ql-code-block" title="<?= htmlspecialchars(__('Docs::docs_tool_code')) ?>"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-link" title="<?= htmlspecialchars(__('Docs::docs_tool_link')) ?>"></button>
                            <button class="ql-clean" title="<?= htmlspecialchars(__('Docs::docs_tool_clean')) ?>"></button>
                        </span>
                    </div>

                    <!-- Quill Düzenlenebilir İçerik Tuvali -->
                    <div class="cu-docs-quill-scroll">
                        <div id="docPaperContainer" class="cu-doc-paper">
                            <div id="quillEditor" class="cu-doc-editor-inner"></div>

                            <!-- DÖKÜMAN EKLERİ & DOSYA DEPOLAMA ALANI -->
                            <div id="docAttachmentsSection" class="cu-docs-attachments-section">
                                <div class="cu-docs-attachments-header">
                                    <h4 class="cu-docs-attachments-title">
                                        <span>📎</span> <?= __('Docs::docs_attachments_title') ?>
                                        <span id="docAttachmentsCount" class="cu-nav-badge cu-docs-attachments-badge">0</span>
                                    </h4>
                                    <button type="button" id="btnTriggerDocUpload" class="cu-btn-sm cu-docs-btn-upload-att">
                                        <?= __('Docs::docs_upload_file') ?>
                                    </button>
                                </div>

                                <!-- Drag & Drop & Paste Zone for Docs -->
                                <div id="docDropzone" class="cu-docs-dropzone">
                                    <input type="file" id="docFileInput" class="cu-hidden" multiple accept="image/*,.pdf,.zip,.rar,.7z,.txt,.doc,.docx,.xls,.xlsx">
                                    <span class="cu-docs-dropzone-icon">☁️</span>
                                    <span class="cu-docs-dropzone-main"><?= __('Docs::docs_dropzone_title') ?> <span class="cu-docs-dropzone-link"><?= __('Docs::docs_dropzone_select') ?></span></span>
                                    <span class="cu-docs-dropzone-sub"><?= __('Docs::docs_dropzone_sub') ?></span>
                                </div>

                                <!-- Döküman Ekleri Grid Listesi -->
                                <div id="docAttachmentsList" class="cu-docs-attachments-grid">
                                    <!-- Dinamik olarak JS ile yüklenir -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kaynak Kod (Markdown / HTML) Editörü -->
                <div id="sourceEditorWrapper" class="cu-docs-source-wrapper">
                    <div id="sourcePaperContainer" class="cu-docs-source-paper">
                        <div class="cu-docs-source-info-bar">
                            <span><?= __('Docs::docs_raw_source_title') ?></span>
                            <span><?= __('Docs::docs_auto_sync') ?></span>
                        </div>
                        <textarea id="rawSourceTextarea" class="cu-docs-raw-textarea"></textarea>
                    </div>
                </div>
            </div>

            <input type="hidden" id="activeDocId" value="<?= (int)$firstDoc->id ?>">
            <!-- İlk Ham İçerik -->
            <textarea id="initialRawContent" class="cu-hidden"><?= htmlspecialchars($firstDoc->content, ENT_QUOTES, 'UTF-8') ?></textarea>

        <?php else: ?>
            <div class="cu-docs-no-docs-box">
                <div class="cu-docs-no-docs-icon">📄</div>
                <div class="cu-docs-no-docs-title"><?= __('Docs::docs_no_docs_title') ?></div>
                <div class="cu-docs-no-docs-desc"><?= __('Docs::docs_no_docs_desc') ?></div>
                <button type="button" class="cu-btn-quick-task cu-docs-btn-create-first cu-btn-trigger-new-doc">
                    <span>➕</span> <?= __('Docs::docs_new_doc') ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
