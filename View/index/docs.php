<?php
// TaskManager Modern Dökümanlar & Zengin Metin Editörü (Docs WYSIWYG View)
require_once __DIR__ . '/../../Controller/DocController.php';

$system->title = __('app.docs_title', 'Dökümanlar - TaskManager');
include __DIR__ . '/../../Components/header.php';

$activeProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$selectedDocId = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : null;

$docController = new DocController($system);
$docData = $docController->index($activeProjectId, $selectedDocId);
extract($docData);
?>

<!-- Quill.js & Marked.js Kütüphaneleri -->
<link rel="stylesheet" href="/taskmanager/Public/css/quill.snow.css">
<script src="/taskmanager/Public/js/marked.min.js"></script>
<script src="/taskmanager/Public/js/quill.min.js"></script>

<!-- ÜST GÖRÜNÜM ÇUBUĞU (VIEW HEADER) -->
<div class="cu-view-header">
    <div class="cu-view-title-wrap" style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:16px;">📄</span>
        <span style="font-weight:700; font-size:14px; color:var(--text-white);"><?= $isClient ? __('tasks.view_client_docs', 'Proje Dökümanları') : __('tasks.view_docs', 'Dökümanlar (Docs)') ?></span>
    </div>

    <div class="cu-filter-bar" style="display:flex; align-items:center; gap:8px;">
        <button type="button" class="cu-btn-sm cu-mobile-docs-toggle" onclick="toggleDocsSidebar()" style="display:none; align-items:center; gap:6px; background:rgba(255,255,255,0.06); border:1px solid var(--border-subtle); color:var(--text-white); font-size:12px; height:32px; padding:0 12px; border-radius:var(--radius-sm); cursor:pointer;">
            <span>📂</span> <span><?= __('app.docs_mobile_btn', 'Belgeler') ?></span>
        </button>
        <button class="cu-btn-quick-task" onclick="openNewDocPrompt()">
            <span>➕</span> <?= __('app.docs_new_doc', 'Yeni Döküman') ?>
        </button>
    </div>
</div>

<!-- TaskManager DOCS İKİ PANELLİ ÇALIŞMA ALANI -->
<div class="cu-docs-container" style="flex:1; display:flex; height:calc(100vh - 120px); overflow:hidden; background:var(--bg-main); position:relative;">
    
    <!-- SOL PANEL: DÖKÜMAN LİSTESİ / GEZGİN (SIDEBAR) -->
    <div id="cuDocsSidebar" class="cu-docs-sidebar" style="width:280px; min-width:280px; background:var(--bg-card); border-right:1px solid var(--border-subtle); display:flex; flex-direction:column; overflow:hidden;">
        <!-- Panel Başlığı & Arama -->
        <div style="padding:14px 16px; border-bottom:1px solid var(--border-subtle);">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <span style="font-size:12px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;"><?= __('app.docs_heading', 'DÖKÜMANLAR') ?></span>
                <button type="button" onclick="openNewDocPrompt()" style="background:none; border:none; color:var(--brand-primary); cursor:pointer; font-size:12px; font-weight:600;">
                    <?= __('app.docs_new_page', '+ Yeni Sayfa') ?>
                </button>
            </div>
            <div style="position:relative;">
                <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:11px; color:var(--text-muted);">🔍</span>
                <input type="text" id="docSearchInput" oninput="filterDocsList(this.value)" placeholder="<?= htmlspecialchars(__('app.docs_search_ph', 'Döküman ara...'), ENT_QUOTES, 'UTF-8') ?>" class="cu-form-control" style="height:30px; font-size:12px; padding-left:28px;">
            </div>
        </div>

        <!-- Dökümanlar Ağacı / Listesi -->
        <div id="docsListSidebar" style="flex:1; overflow-y:auto; padding:8px;">
            <?php if (empty($docsList)): ?>
                <div style="padding:20px; text-align:center; font-size:12px; color:var(--text-muted);">
                    <?= __('app.docs_empty', 'Henüz döküman oluşturulmamış.') ?>
                </div>
            <?php else: ?>
                <?php foreach ($docsList as $doc): ?>
                    <div class="cu-doc-sidebar-item <?= ($firstDoc && $firstDoc->id == $doc->id) ? 'active' : '' ?>" 
                         id="docItem-<?= $doc->id ?>" 
                         onclick="selectDoc(<?= $doc->id ?>)"
                         style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; margin-bottom:4px; transition:var(--transition);">
                        <span style="font-size:16px;" id="docIconBadge-<?= $doc->id ?>"><?= htmlspecialchars($doc->icon ?: '📄') ?></span>
                        <div style="flex:1; overflow:hidden;">
                            <div style="font-size:13px; font-weight:600; color:var(--text-white); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" id="docSidebarTitle-<?= $doc->id ?>">
                                <?= htmlspecialchars($doc->title) ?>
                            </div>
                            <div style="font-size:11px; color:var(--text-muted); display:flex; align-items:center; gap:6px; margin-top:3px;">
                                <?php if ($doc->project_title): ?>
                                    <span style="color:<?= htmlspecialchars($doc->project_color ?? '#7B68EE') ?>;">● <?= htmlspecialchars($doc->project_title) ?></span>
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
    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:var(--bg-main);">
        <?php if ($firstDoc): ?>
            <!-- Doküman Üst Barı -->
            <div style="padding:12px 24px; border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; background:var(--bg-card);">
                <div style="display:flex; align-items:center; gap:12px; flex:1; min-width:280px;">
                    <!-- Emoji Seçici -->
                    <button type="button" onclick="cycleDocIcon()" id="btnDocIcon" style="font-size:22px; background:var(--bg-card-hover); border:1px solid var(--border-subtle); border-radius:8px; width:40px; height:40px; display:flex; align-items:center; justify-content:center; cursor:pointer;" title="<?= htmlspecialchars(__('app.docs_icon_change', 'İkon Değiştir'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($firstDoc->icon ?: '📄') ?>
                    </button>

                    <!-- Başlık Input -->
                    <input type="text" id="activeDocTitle" value="<?= htmlspecialchars($firstDoc->title) ?>" oninput="markDocUnsaved()" placeholder="<?= htmlspecialchars(__('app.docs_title_ph', 'Döküman Başlığı...'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:18px; font-weight:800; color:var(--text-white); background:transparent; border:none; outline:none; flex:1;">
                </div>

                <div style="display:flex; align-items:center; gap:10px;">
                    <!-- Görsel / Kaynak Kod Mod Butonu -->
                    <div style="display:inline-flex; background:rgba(0,0,0,0.3); border:1px solid var(--border-subtle); border-radius:6px; padding:2px;">
                        <button type="button" id="btnModeVisual" onclick="switchEditorMode('visual')" class="cu-mode-btn active" title="<?= htmlspecialchars(__('app.docs_mode_visual', 'Görsel WYSIWYG Metin Editörü'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= __('app.docs_mode_visual', '👁️ Görsel Editör') ?>
                        </button>
                        <button type="button" id="btnModeSource" onclick="switchEditorMode('source')" class="cu-mode-btn" title="<?= htmlspecialchars(__('app.docs_mode_source', 'Markdown / HTML Kaynak Kodu'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= __('app.docs_mode_source', '📝 Kaynak Kod') ?>
                        </button>
                    </div>

                    <!-- Tam Ekran / Geniş Görünüm Toggle Butonu -->
                    <button type="button" id="btnToggleWidth" onclick="togglePaperWidth()" class="cu-mode-btn" style="border:1px solid var(--border-subtle); background:rgba(0,0,0,0.25);" title="<?= htmlspecialchars(__('app.docs_width_wide', 'Tam Ekran / Standart Genişlik'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= __('app.docs_width_wide', '↔️ Geniş Görünüm') ?>
                    </button>

                    <!-- Proje Seçici -->
                    <select id="activeDocProject" onchange="markDocUnsaved()" class="cu-form-control" style="height:32px; font-size:12px; width:150px;">
                        <option value=""><?= __('app.docs_proj_workspace', '📁 Workspace (Genel)') ?></option>
                        <?php foreach ($allProjects as $proj): ?>
                            <option value="<?= $proj->id ?>" <?= ($firstDoc->project_id == $proj->id) ? 'selected' : '' ?>>
                                📁 <?= htmlspecialchars($proj->title) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Kayıt Durumu Göstergesi -->
                    <span id="docSaveStatus" style="font-size:11.5px; color:var(--text-muted); font-weight:500; min-width:85px;">
                        ✓ <?= __('app.docs_save_status_saved', 'Kaydedildi') ?>
                    </span>

                    <!-- Kaydet Butonu -->
                    <button type="button" onclick="saveActiveDoc()" class="cu-btn-sm" style="height:32px; padding:0 14px; background:var(--brand-primary); font-weight:600; display:flex; align-items:center; gap:6px;">
                        <span>💾</span> <span><?= __('app.docs_btn_save', 'Kaydet') ?></span>
                    </button>

                    <!-- Sil Butonu -->
                    <button type="button" onclick="deleteActiveDoc()" class="cu-btn-sm" style="height:32px; padding:0 10px; background:rgba(231,76,60,0.15); color:#e74c3c; border:1px solid rgba(231,76,60,0.3);" title="<?= htmlspecialchars(__('app.docs_btn_delete', 'Dökümanı Sil'), ENT_QUOTES, 'UTF-8') ?>">
                        🗑️
                    </button>
                </div>
            </div>

            <!-- EDİTÖR ALANI (QUILL WYSIWYG & SOURCE AREA) -->
            <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; position:relative;">
                <!-- Quill Editör Kutusu -->
                <div id="quillWrapper" style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
                    <!-- Quill Toolbar Alanı (Quill Snow otomatik burayı doldurur) -->
                    <div id="quillToolbar">
                        <span class="ql-formats">
                            <select class="ql-header">
                                <option value="1"><?= __('app.docs_h1') ?></option>
                                <option value="2"><?= __('app.docs_h2') ?></option>
                                <option value="3"><?= __('app.docs_h3') ?></option>
                                <option selected><?= __('tasks.format_normal', 'Normal') ?></option>
                            </select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-bold" title="<?= htmlspecialchars(__('app.docs_tool_bold')) ?>"></button>
                            <button class="ql-italic" title="<?= htmlspecialchars(__('app.docs_tool_italic')) ?>"></button>
                            <button class="ql-underline" title="<?= htmlspecialchars(__('app.docs_tool_underline')) ?>"></button>
                            <button class="ql-strike" title="<?= htmlspecialchars(__('tasks.format_strike', 'Üstü Çizili')) ?>"></button>
                        </span>
                        <span class="ql-formats">
                            <select class="ql-color" title="<?= htmlspecialchars(__('tasks.format_color', 'Yazı Rengi')) ?>"></select>
                            <select class="ql-background" title="<?= htmlspecialchars(__('tasks.format_bg', 'Arka Plan')) ?>"></select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-list" value="ordered" title="<?= htmlspecialchars(__('app.docs_tool_ordered')) ?>"></button>
                            <button class="ql-list" value="bullet" title="<?= htmlspecialchars(__('app.docs_tool_bullet')) ?>"></button>
                            <button class="ql-list" value="check" title="<?= htmlspecialchars(__('tasks.checklist_title', 'Kontrol Listesi')) ?>"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-blockquote" title="<?= htmlspecialchars(__('app.tasks_format_quote')) ?>"></button>
                            <button class="ql-code-block" title="<?= htmlspecialchars(__('app.docs_tool_code')) ?>"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-link" title="<?= htmlspecialchars(__('app.docs_tool_link')) ?>"></button>
                            <button class="ql-clean" title="<?= htmlspecialchars(__('tasks.format_clean', 'Biçimlendirmeyi Temizle')) ?>"></button>
                        </span>
                    </div>

                    <!-- Quill Düzenlenebilir İçerik Tuvali -->
                    <div style="flex:1; overflow-y:auto; padding:24px 30px; background:var(--bg-main);">
                        <div id="docPaperContainer" class="cu-doc-paper" style="max-width:1120px; width:100%; margin:0 auto; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.3); overflow:visible; transition:max-width 0.25s ease;">
                            <div id="quillEditor" style="min-height:500px; padding:30px 40px;"></div>

                            <!-- DÖKÜMAN EKLERİ & DOSYA DEPOLAMA ALANI -->
                            <div id="docAttachmentsSection" style="margin:20px 40px 30px; padding-top:20px; border-top:1px dashed var(--border-subtle);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                    <h4 style="font-size:13.5px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px; margin:0;">
                                        <span>📎</span> <?= __('app.docs_attachments_title', 'Döküman Ekleri & Dosyalar') ?>
                                        <span id="docAttachmentsCount" class="cu-nav-badge" style="background:var(--brand-primary); color:#fff; font-size:11px;">0</span>
                                    </h4>
                                    <button type="button" onclick="document.getElementById('docFileInput').click()" class="cu-btn-sm" style="font-size:12px; height:28px; padding:0 12px; background:rgba(123, 104, 238, 0.15); color:var(--brand-primary); border:1px solid rgba(123, 104, 238, 0.3);">
                                        <?= __('app.docs_upload_file', '+ Dosya Yükle') ?>
                                    </button>
                                </div>

                                <!-- Drag & Drop & Paste Zone for Docs -->
                                <div id="docDropzone"
                                     ondragover="handleDocDropzoneOver(event)"
                                     ondragleave="handleDocDropzoneLeave(event)"
                                     ondrop="handleDocDropzoneDrop(event)"
                                     onclick="document.getElementById('docFileInput').click()"
                                     style="border:2px dashed var(--border-subtle); border-radius:8px; padding:18px; text-align:center; cursor:pointer; background:rgba(255,255,255,0.02); transition:all 0.2s; margin-bottom:14px;">
                                    <input type="file" id="docFileInput" onchange="handleDocFileUpload(this)" style="display:none;" multiple accept="image/*,.pdf,.zip,.rar,.7z,.txt,.doc,.docx,.xls,.xlsx">
                                    <span style="font-size:22px; display:block; margin-bottom:4px;">☁️</span>
                                    <span style="font-size:12.5px; font-weight:600; color:var(--text-white);"><?= __('app.docs_dropzone_title', 'Dosyaları buraya sürükleyin veya') ?> <span style="color:var(--brand-primary); text-decoration:underline;"><?= __('app.docs_dropzone_select', 'seçin') ?></span></span>
                                    <span style="font-size:11px; color:var(--text-muted); display:block; margin-top:2px;"><?= __('app.docs_dropzone_sub', 'PDF, ZIP, Görsel veya panodan (Ctrl+V) yapıştırın (Max 20MB)') ?></span>
                                </div>

                                <!-- Döküman Ekleri Grid Listesi -->
                                <div id="docAttachmentsList" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px;">
                                    <!-- Dinamik olarak JS ile yüklenir -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kaynak Kod (Markdown / HTML) Editörü (Opsiyonel / Gizli) -->
                <div id="sourceEditorWrapper" style="display:none; flex:1; overflow:hidden; flex-direction:column; padding:20px 30px; background:var(--bg-main);">
                    <div id="sourcePaperContainer" style="max-width:1120px; width:100%; margin:0 auto; height:100%; display:flex; flex-direction:column; transition:max-width 0.25s ease;">
                        <div style="font-size:12px; color:var(--text-muted); margin-bottom:8px; display:flex; justify-content:space-between;">
                            <span><?= __('app.docs_raw_source_title', '📝 Ham İçerik (Markdown veya HTML olarak düzenleyebilirsiniz)') ?></span>
                            <span><?= __('app.docs_auto_sync', 'Otomatik senkronize edilir') ?></span>
                        </div>
                        <textarea id="rawSourceTextarea" oninput="markDocUnsaved()" style="flex:1; width:100%; background:var(--bg-card); color:var(--text-primary); font-family:'Fira Code', 'Courier New', monospace; font-size:13.5px; line-height:1.65; border:1px solid var(--border-subtle); border-radius:8px; padding:20px; outline:none; resize:none;"></textarea>
                    </div>
                </div>
            </div>

            <input type="hidden" id="activeDocId" value="<?= $firstDoc->id ?>">
            <!-- İlk Ham İçerik (JS Tarafından Parse Edilmek Üzere) -->
            <textarea id="initialRawContent" style="display:none;"><?= htmlspecialchars($firstDoc->content, ENT_QUOTES, 'UTF-8') ?></textarea>

        <?php else: ?>
            <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--text-muted);">
                <div style="font-size:54px; margin-bottom:16px;">📄</div>
                <div style="font-size:18px; font-weight:700; color:var(--text-white); margin-bottom:8px;"><?= __('app.docs_no_docs_title', 'Döküman Bulunmuyor') ?></div>
                <div style="font-size:13px; margin-bottom:24px;"><?= __('app.docs_no_docs_desc', 'Sol panelden veya yukarıdaki butondan yeni bir döküman oluşturabilirsiniz.') ?></div>
                <button type="button" onclick="openNewDocPrompt()" class="cu-btn-quick-task" style="padding:10px 20px; font-size:13px;">
                    <span>➕</span> <?= __('app.docs_new_doc', 'Yeni Döküman Oluştur') ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* TaskManager Docs & Quill Dark Theme Customizations */
.cu-doc-sidebar-item:hover {
    background: rgba(255, 255, 255, 0.05);
}
.cu-doc-sidebar-item.active {
    background: rgba(123, 104, 238, 0.16) !important;
    border-left: 3px solid var(--brand-primary);
}

.cu-mode-btn {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    padding: 4px 10px;
    font-size: 11.5px;
    font-weight: 600;
    border-radius: 4px;
    cursor: pointer;
    transition: var(--transition);
}
.cu-mode-btn:hover {
    color: var(--text-white);
}
.cu-mode-btn.active {
    background: var(--brand-primary);
    color: #fff !important;
}

/* Quill Toolbar Theme Support */
.ql-toolbar.ql-snow {
    background: var(--bg-card) !important;
    border: none !important;
    border-bottom: 1px solid var(--border-subtle) !important;
    padding: 10px 24px !important;
    font-family: var(--font-sans) !important;
}
.ql-snow .ql-stroke {
    stroke: var(--text-secondary) !important;
}
.ql-snow .ql-fill {
    fill: var(--text-secondary) !important;
}
.ql-snow .ql-picker {
    color: var(--text-secondary) !important;
}
.ql-snow .ql-picker-options {
    background: var(--bg-card) !important;
    border: 1px solid var(--border-subtle) !important;
    border-radius: 6px !important;
    box-shadow: var(--shadow-modal) !important;
    padding: 6px !important;
}
.ql-snow .ql-picker-item {
    color: var(--text-primary) !important;
    padding: 6px 10px !important;
    border-radius: 4px !important;
}
.ql-snow .ql-picker-item:hover {
    background: rgba(123, 104, 238, 0.15) !important;
    color: var(--brand-primary) !important;
}
.ql-snow .ql-picker.ql-expanded .ql-picker-label {
    border-color: var(--brand-primary) !important;
    color: var(--brand-primary) !important;
}
.ql-snow button:hover .ql-stroke,
.ql-snow .ql-picker-label:hover .ql-stroke {
    stroke: var(--brand-primary) !important;
}
.ql-snow button.ql-active .ql-stroke {
    stroke: var(--brand-primary) !important;
}
.ql-snow button:hover .ql-fill,
.ql-snow button.ql-active .ql-fill {
    fill: var(--brand-primary) !important;
}

/* Quill Editor Typography */
.ql-container.ql-snow {
    border: none !important;
    font-family: var(--font-sans) !important;
    font-size: 15px !important;
}
.ql-editor {
    color: var(--text-primary) !important;
    line-height: 1.8 !important;
    font-size: 15px !important;
}
.ql-editor.ql-blank::before {
    color: var(--text-muted) !important;
    font-style: normal !important;
}

/* Headings in Editor */
.ql-editor h1 {
    font-size: 26px !important;
    font-weight: 800 !important;
    color: var(--text-white) !important;
    margin: 22px 0 12px 0 !important;
    border-bottom: 1px solid var(--border-subtle) !important;
    padding-bottom: 10px !important;
}
.ql-editor h2 {
    font-size: 20px !important;
    font-weight: 700 !important;
    color: var(--text-white) !important;
    margin: 18px 0 10px 0 !important;
}
.ql-editor h3 {
    font-size: 16.5px !important;
    font-weight: 600 !important;
    color: var(--text-white) !important;
    margin: 14px 0 8px 0 !important;
}
.ql-editor h4 {
    font-size: 14.5px !important;
    font-weight: 600 !important;
    color: var(--text-white) !important;
    margin: 12px 0 6px 0 !important;
}
.ql-editor p {
    margin-bottom: 10px !important;
}
/* Boş paragrafları (boş veya yalnızca <br> içeren) kompakt hale getir */
.ql-editor p:empty,
.ql-editor p:has(> br:only-child) {
    margin: 2px 0 !important;
    min-height: 6px !important;
    line-height: 6px !important;
}
/* Peş peşe gelen boş paragrafları TAMAMEN gizle (Aşırı yapay zeka boşluklarını yok et) */
.ql-editor p:empty + p:empty,
.ql-editor p:empty + p:has(> br:only-child),
.ql-editor p:has(> br:only-child) + p:empty,
.ql-editor p:has(> br:only-child) + p:has(> br:only-child) {
    display: none !important;
}
/* Başlıkların öncesi ve sonrasındaki boş paragrafları gizle */
.ql-editor h1 + p:has(> br:only-child),
.ql-editor h2 + p:has(> br:only-child),
.ql-editor h3 + p:has(> br:only-child),
.ql-editor h4 + p:has(> br:only-child),
.ql-editor h1 + p:empty,
.ql-editor h2 + p:empty,
.ql-editor h3 + p:empty,
.ql-editor h4 + p:empty {
    display: none !important;
}
.ql-editor p:has(> br:only-child) + h1,
.ql-editor p:has(> br:only-child) + h2,
.ql-editor p:has(> br:only-child) + h3,
.ql-editor p:has(> br:only-child) + h4,
.ql-editor p:empty + h1,
.ql-editor p:empty + h2,
.ql-editor p:empty + h3,
.ql-editor p:empty + h4 {
    display: none !important;
}
/* Kod blokları ve Tabloların bitişiğindeki boş paragrafları gizle */
.ql-editor .ql-code-block-container + p:has(> br:only-child),
.ql-editor .ql-code-block-container + p:empty,
.ql-editor p:has(> br:only-child) + .ql-code-block-container,
.ql-editor p:empty + .ql-code-block-container,
.ql-editor table + p:has(> br:only-child),
.ql-editor table + p:empty,
.ql-editor p:has(> br:only-child) + table,
.ql-editor p:empty + table {
    display: none !important;
}
/* Kod bloğu içindeki boş satırları yok say */
.ql-editor .ql-code-block:empty {
    display: none !important;
}
.ql-editor strong {
    color: var(--text-white) !important;
    font-weight: 700 !important;
}
.ql-editor code {
    background: rgba(123, 104, 238, 0.12) !important;
    color: #e84393 !important;
    padding: 2px 6px !important;
    border-radius: 4px !important;
    font-family: 'Fira Code', monospace !important;
    font-size: 13px !important;
}
.ql-editor .ql-code-block-container,
.ql-editor pre {
    background: #11111c !important;
    border: 1px solid var(--border-subtle) !important;
    border-radius: 8px !important;
    padding: 14px 16px !important;
    color: #69b4ff !important;
    font-family: 'Fira Code', monospace !important;
    font-size: 13.5px !important;
    line-height: 1.6 !important;
    margin: 12px 0 !important;
    overflow-x: auto !important;
}
.ql-editor .ql-code-block {
    color: #69b4ff !important;
    font-family: 'Fira Code', monospace !important;
    line-height: 1.6 !important;
}
.ql-editor blockquote {
    border-left: 3px solid var(--brand-primary) !important;
    padding: 10px 18px !important;
    margin: 16px 0 !important;
    color: var(--text-secondary) !important;
    background: rgba(123, 104, 238, 0.08) !important;
    border-radius: 0 8px 8px 0 !important;
}
.ql-editor ul, .ql-editor ol {
    padding-left: 26px !important;
    margin-bottom: 16px !important;
}
.ql-editor li {
    margin-bottom: 6px !important;
}
.ql-editor a {
    color: var(--brand-primary) !important;
    text-decoration: underline !important;
}

/* Full Width / Geniş Görünüm Modu */
.cu-doc-paper.full-width,
#sourcePaperContainer.full-width {
    max-width: 98% !important;
}

/* TaskManager Docs Tablo Stilleri & Yatay Kaydırma Desteği */
.ql-editor {
    overflow-x: auto !important;
}
.ql-editor table {
    min-width: 100% !important;
    width: 100% !important;
    max-width: 100% !important;
    table-layout: auto !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
    margin: 20px 0 !important;
    background: var(--bg-card) !important;
    border: 1px solid var(--border-subtle) !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08) !important;
}
.ql-editor tr {
    transition: background 0.15s ease !important;
}
.ql-editor tr:hover {
    background: rgba(123, 104, 238, 0.04) !important;
}
.ql-editor td, 
.ql-editor th {
    border: 1px solid var(--border-subtle) !important;
    padding: 10px 14px !important;
    font-size: 13.5px !important;
    line-height: 1.55 !important;
    color: var(--text-primary) !important;
    vertical-align: middle !important;
    word-break: normal !important;
    white-space: normal !important;
    min-width: 120px !important;
}
/* İlk satırı Başlık (Header) olarak vurgula */
.ql-editor tr:first-child td,
.ql-editor th {
    background: var(--bg-card-hover) !important;
    color: var(--text-white) !important;
    font-weight: 700 !important;
    border-bottom: 2px solid var(--brand-primary) !important;
    font-size: 12.5px !important;
    letter-spacing: 0.5px !important;
    white-space: nowrap !important;
}
.ql-editor tr:nth-child(even) td {
    background: rgba(125, 125, 125, 0.03) !important;
}
.ql-editor tr:first-child:nth-child(even) td {
    background: var(--bg-card-hover) !important;
}
.ql-editor td code {
    background: rgba(123, 104, 238, 0.12) !important;
    color: #e84393 !important;
    padding: 2px 6px !important;
    border-radius: 4px !important;
    font-family: 'Fira Code', monospace !important;
    font-size: 12.5px !important;
    white-space: nowrap !important;
}

/* Light Theme Overrides for Docs */
[data-theme="light"] .cu-doc-sidebar-item:hover {
    background: rgba(0, 0, 0, 0.04);
}
[data-theme="light"] .cu-doc-paper {
    background: #ffffff !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06) !important;
}
[data-theme="light"] #rawSourceTextarea {
    background: #ffffff !important;
    color: #1e293b !important;
    border-color: #cbd5e1 !important;
}
[data-theme="light"] .ql-editor .ql-code-block-container,
[data-theme="light"] .ql-editor pre {
    background: #f8fafc !important;
    color: #0f172a !important;
    border-color: #e2e8f0 !important;
}
[data-theme="light"] .ql-editor .ql-code-block {
    color: #0f172a !important;
}
[data-theme="light"] .ql-editor blockquote {
    color: #334155 !important;
    background: rgba(123, 104, 238, 0.07) !important;
}

/* Responsive / Mobil Docs Stilleri */
@media (max-width: 768px) {
    .cu-mobile-docs-toggle {
        display: inline-flex !important;
    }
    .cu-docs-sidebar {
        display: none !important;
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        bottom: 0 !important;
        width: 285px !important;
        max-width: 85vw !important;
        z-index: 100 !important;
        box-shadow: 10px 0 30px rgba(0,0,0,0.6) !important;
        background: var(--bg-card) !important;
    }
    .cu-docs-sidebar.mobile-open {
        display: flex !important;
    }
    .ql-toolbar.ql-snow {
        overflow-x: auto !important;
        white-space: nowrap !important;
        padding: 8px 12px !important;
    }
    .cu-doc-paper {
        border-radius: 6px !important;
        margin: 0 !important;
    }
    #quillEditor {
        padding: 16px 14px !important;
    }
    #docAttachmentsSection {
        margin: 16px 14px 20px !important;
    }
}
</style>

<script>
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
        btn.innerText = isFull ? <?= json_encode(__('app.docs_width_standard', '📄 Standart Genişlik')) ?> : <?= json_encode(__('app.docs_width_wide', '↔️ Geniş Görünüm')) ?>;
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
            placeholder: <?= json_encode(__('app.docs_editor_placeholder', 'Dökümanınızı buraya yazmaya başlayın...')) ?>
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
        status.innerText = <?= json_encode('● ' . __('app.docs_save_status_unsaved', 'Kaydedilmedi')) ?>;
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
            showToast(data.message || <?= json_encode(__('app.docs_toast_load_err', 'Döküman yüklenemedi')) ?>, 'error');
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
            status.innerText = <?= json_encode('✓ ' . __('app.docs_save_status_saved', 'Kaydedildi')) ?>;
            status.style.color = 'var(--text-muted)';
        }
    } catch (e) {
        console.error(e);
        showToast(<?= json_encode(__('app.docs_toast_conn_err', 'Bağlantı hatası')) ?>, 'error');
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
        showToast(<?= json_encode(__('app.docs_toast_title_req', 'Başlık zorunludur')) ?>, 'error');
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
        status.innerText = <?= json_encode('⏳ ' . __('app.docs_save_status_saving', 'Kaydediliyor...')) ?>;
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
                status.innerText = <?= json_encode('✓ ' . __('app.docs_save_status_saved', 'Kaydedildi')) ?>;
                status.style.color = '#00b894';
            }
            showToast(<?= json_encode(__('app.docs_toast_save_success', '✅ Döküman kaydedildi!')) ?>, 'success');
            
            // Sidebar başlık ve ikon güncelle
            const sideTitle = document.getElementById(`docSidebarTitle-${docId}`);
            if (sideTitle) sideTitle.innerText = title;
            const sideIcon = document.getElementById(`docIconBadge-${docId}`);
            if (sideIcon) sideIcon.innerText = icon;
        } else {
            showToast(data.message || <?= json_encode(__('app.docs_toast_save_fail', 'Kayıt başarısız')) ?>, 'error');
        }
    } catch (e) {
        console.error(e);
        showToast(<?= json_encode(__('app.docs_toast_conn_err', 'Bağlantı hatası')) ?>, 'error');
    }
}

// 6. Yeni Döküman Oluşturma
async function openNewDocPrompt() {
    const title = prompt(<?= json_encode(__('app.docs_create_prompt', 'Yeni Döküman Başlığı:')) ?>, <?= json_encode(__('app.docs_create_default', 'Yeni Doküman')) ?>);
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
            showToast(<?= json_encode(__('app.docs_toast_created', '✅ Yeni döküman oluşturuldu!')) ?>, 'success');
            window.location.reload();
        } else {
            showToast(data.message || <?= json_encode(__('app.docs_toast_create_fail', 'Oluşturulamadı')) ?>, 'error');
        }
    } catch (e) {
        console.error(e);
    }
}

// 7. Döküman Silme
async function deleteActiveDoc() {
    const docId = document.getElementById('activeDocId')?.value;
    if (!docId) return;

    if (!confirm(<?= json_encode(__('app.docs_confirm_delete', 'Bu dökümanı silmek istediğinize emin misiniz?')) ?>)) return;

    const formData = new FormData();
    formData.append('id', docId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_doc', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast(<?= json_encode(__('app.docs_toast_deleted', 'Döküman silindi')) ?>, 'success');
            window.location.href = '/taskmanager/docs';
        } else {
            showToast(data.message || <?= json_encode(__('app.docs_toast_delete_fail', 'Silinemedi')) ?>, 'error');
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
        list.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:12px; color:var(--text-muted); font-size:12px; font-style:italic;"><?= addslashes(__('app.docs_attachments_empty', 'Bu dökümana henüz ek dosya veya görsel eklenmemiş.')) ?></div>';
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
                <a href="${att.file_path}" download target="_blank" style="color:var(--brand-primary); padding:4px; text-decoration:none; font-size:13px;" title="<?= htmlspecialchars(__('app.docs_attachment_download', 'İndir'), ENT_QUOTES, 'UTF-8') ?>">⬇️</a>
                <button type="button" onclick="deleteDocAttachment(${att.id})" style="background:none; border:none; color:#e74c3c; cursor:pointer; padding:4px; font-size:13px;" title="<?= htmlspecialchars(__('app.docs_btn_delete', 'Sil'), ENT_QUOTES, 'UTF-8') ?>">🗑️</button>
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
        showToast(<?= json_encode(__('app.docs_toast_select_first', 'Önce bir döküman seçmelisiniz')) ?>, 'error');
        return;
    }

    const formData = new FormData();
    formData.append('doc_id', docId);
    formData.append('file', file);

    showToast(<?= json_encode(__('app.docs_toast_uploading', 'Dosya yükleniyor...')) ?>, 'info');

    try {
        const res = await fetch('/taskmanager/ajax?action=upload_doc_attachment', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        const attData = d.data || d.attachment || (d.id ? d : null);
        if (d.success && attData) {
            showToast(<?= json_encode(__('app.docs_toast_upload_success', '✅ Dosya dökümana eklendi!')) ?>, 'success');
            currentDocAttachments = currentDocAttachments.filter(a => a.id != attData.id);
            currentDocAttachments.unshift(attData);
            renderDocAttachments(currentDocAttachments);
        } else {
            showToast(d.message || <?= json_encode(__('app.docs_toast_upload_fail', 'Yükleme başarısız')) ?>, 'error');
        }
    } catch(e) {
        showToast(<?= json_encode(__('app.docs_toast_conn_err', 'Bağlantı hatası oluştu')) ?>, 'error');
    }
}

async function deleteDocAttachment(attId) {
    if (!confirm(<?= json_encode(__('app.docs_confirm_delete_att', 'Bu eki silmek istediğinize emin misiniz?')) ?>)) return;

    const formData = new FormData();
    formData.append('attachment_id', attId);

    try {
        const res = await fetch('/taskmanager/ajax?action=delete_doc_attachment', {
            method: 'POST',
            body: formData
        });
        const d = await res.json();
        if (d.success) {
            showToast(<?= json_encode(__('app.docs_toast_att_deleted', 'Ek dosya silindi!')) ?>, 'success');
            currentDocAttachments = currentDocAttachments.filter(a => a.id != attId);
            renderDocAttachments(currentDocAttachments);
        } else {
            showToast(d.message || <?= json_encode(__('app.docs_toast_delete_fail', 'Silinemedi')) ?>, 'error');
        }
    } catch(e) {
        showToast(<?= json_encode(__('app.docs_toast_conn_err', 'Bağlantı hatası')) ?>, 'error');
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
            showToast(<?= json_encode(__('app.docs_toast_paste_success', '📋 Ekran görüntüsü dökümana eklendi!')) ?>, 'success');
            break;
        }
    }
});

// Sayfa yüklendiğinde döküman eklerini çek
document.addEventListener('DOMContentLoaded', () => {
    loadCurrentDocAttachments();
});
</script>

<?php include __DIR__ . '/../../Components/footer.php'; ?>
