<?php
// TaskManager Modern Dökümanlar & Zengin Metin Editörü (Docs WYSIWYG View)
$activeProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$selectedDocId = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : null;

$docController = new \Modules\Docs\Controllers\DocController();
$docData = $docController->index($activeProjectId, $selectedDocId);
extract($docData);
$isClient = $isClient ?? false;

$system->title = __('Docs::docs_title', 'Dökümanlar - TaskManager');
include dirname(__DIR__, 3) . '/Core/Views/header.php';
?>




<!-- ÜST GÖRÜNÜM ÇUBUĞU (VIEW HEADER) -->
<div class="cu-view-header">
    <div class="cu-view-title-wrap" style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:16px;">📄</span>
        <span style="font-weight:700; font-size:14px; color:var(--text-white);"><?= $isClient ? __('tasks.view_client_docs', 'Proje Dökümanları') : __('tasks.view_docs', 'Dökümanlar (Docs)') ?></span>
    </div>

    <div class="cu-filter-bar" style="display:flex; align-items:center; gap:8px;">
        <button type="button" class="cu-btn-sm cu-mobile-docs-toggle" onclick="toggleDocsSidebar()" style="display:none; align-items:center; gap:6px; background:rgba(255,255,255,0.06); border:1px solid var(--border-subtle); color:var(--text-white); font-size:12px; height:32px; padding:0 12px; border-radius:var(--radius-sm); cursor:pointer;">
            <span>📂</span> <span><?= __('Docs::docs_mobile_btn', 'Belgeler') ?></span>
        </button>
        <?php if (!$isClient): ?>
        <button type="button" class="cu-btn-quick-task" onclick="openNewDocPrompt()" style="height:32px; padding:0 14px; font-size:12.5px; display:inline-flex; align-items:center; gap:6px; box-sizing:border-box;">
            <span>➕</span> <?= __('Docs::docs_new_doc', 'Yeni Döküman') ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- TaskManager DOCS İKİ PANELLİ ÇALIŞMA ALANI -->
<div class="cu-docs-container" style="flex:1; display:flex; height:calc(100vh - 120px); overflow:hidden; background:var(--bg-main); position:relative;">
    
    <!-- SOL PANEL: DÖKÜMAN LİSTESİ / GEZGİN (SIDEBAR) -->
    <div id="cuDocsSidebar" class="cu-docs-sidebar" style="width:280px; min-width:280px; background:var(--bg-card); border-right:1px solid var(--border-subtle); display:flex; flex-direction:column; overflow:hidden;">
        <!-- Panel Başlığı & Arama -->
        <div style="padding:14px 16px; border-bottom:1px solid var(--border-subtle);">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <span style="font-size:12px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;"><?= __('Docs::docs_heading', 'DÖKÜMANLAR') ?></span>
                <?php if (!$isClient): ?>
                <button type="button" onclick="openNewDocPrompt()" style="background:none; border:none; color:var(--brand-primary); cursor:pointer; font-size:12px; font-weight:600;">
                    <?= __('Docs::docs_new_page', '+ Yeni Sayfa') ?>
                </button>
                <?php endif; ?>
            </div>
            <div style="position:relative;">
                <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:11px; color:var(--text-muted);">🔍</span>
                <input type="text" id="docSearchInput" oninput="filterDocsList(this.value)" placeholder="<?= htmlspecialchars(__('Docs::docs_search_ph', 'Döküman ara...'), ENT_QUOTES, 'UTF-8') ?>" class="cu-form-control" style="height:30px; font-size:12px; padding-left:28px;">
            </div>
        </div>

        <!-- Dökümanlar Ağacı / Listesi -->
        <div id="docsListSidebar" style="flex:1; overflow-y:auto; padding:8px;">
            <?php if (empty($docsList)): ?>
                <div style="padding:20px; text-align:center; font-size:12px; color:var(--text-muted);">
                    <?= __('Docs::docs_empty', 'Henüz döküman oluşturulmamış.') ?>
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
                    <button type="button" onclick="cycleDocIcon()" id="btnDocIcon" style="font-size:22px; background:var(--bg-card-hover); border:1px solid var(--border-subtle); border-radius:8px; width:40px; height:40px; display:flex; align-items:center; justify-content:center; cursor:pointer;" title="<?= htmlspecialchars(__('Docs::docs_icon_change', 'İkon Değiştir'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($firstDoc->icon ?: '📄') ?>
                    </button>

                    <!-- Başlık Input -->
                    <input type="text" id="activeDocTitle" value="<?= htmlspecialchars($firstDoc->title) ?>" oninput="markDocUnsaved()" placeholder="<?= htmlspecialchars(__('Docs::docs_title_ph', 'Döküman Başlığı...'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:18px; font-weight:800; color:var(--text-white); background:transparent; border:none; outline:none; flex:1;">
                </div>

                <div style="display:flex; align-items:center; gap:10px;">
                    <!-- Görsel / Kaynak Kod Mod Butonu -->
                    <div style="display:inline-flex; background:rgba(0,0,0,0.3); border:1px solid var(--border-subtle); border-radius:6px; padding:2px;">
                        <button type="button" id="btnModeVisual" onclick="switchEditorMode('visual')" class="cu-mode-btn active" title="<?= htmlspecialchars(__('Docs::docs_mode_visual', 'Görsel WYSIWYG Metin Editörü'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= __('Docs::docs_mode_visual', '👁️ Görsel Editör') ?>
                        </button>
                        <button type="button" id="btnModeSource" onclick="switchEditorMode('source')" class="cu-mode-btn" title="<?= htmlspecialchars(__('Docs::docs_mode_source', 'Markdown / HTML Kaynak Kodu'), ENT_QUOTES, 'UTF-8') ?>">
                            <?= __('Docs::docs_mode_source', '📝 Kaynak Kod') ?>
                        </button>
                    </div>

                    <!-- Tam Ekran / Geniş Görünüm Toggle Butonu -->
                    <button type="button" id="btnToggleWidth" onclick="togglePaperWidth()" class="cu-mode-btn" style="border:1px solid var(--border-subtle); background:rgba(0,0,0,0.25);" title="<?= htmlspecialchars(__('Docs::docs_width_wide', 'Tam Ekran / Standart Genişlik'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= __('Docs::docs_width_wide', '↔️ Geniş Görünüm') ?>
                    </button>

                    <!-- Proje Seçici -->
                    <select id="activeDocProject" onchange="markDocUnsaved()" class="cu-form-control" style="height:32px; font-size:12px; width:150px;">
                        <option value=""><?= __('Docs::docs_proj_workspace', '📁 Workspace (Genel)') ?></option>
                        <?php foreach ($allProjects as $proj): ?>
                            <option value="<?= $proj->id ?>" <?= ($firstDoc->project_id == $proj->id) ? 'selected' : '' ?>>
                                📁 <?= htmlspecialchars($proj->title) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (!$isClient): ?>
                    <!-- Kayıt Durumu Göstergesi -->
                    <span id="docSaveStatus" style="font-size:11.5px; color:var(--text-muted); font-weight:500; min-width:85px;">
                        ✓ <?= __('Docs::docs_save_status_saved', 'Kaydedildi') ?>
                    </span>

                    <!-- Kaydet Butonu -->
                    <button type="button" onclick="saveActiveDoc()" class="cu-btn-sm" style="height:32px; padding:0 14px; background:var(--brand-primary); font-weight:600; display:flex; align-items:center; gap:6px;">
                        <span>💾</span> <span><?= __('Docs::docs_btn_save', 'Kaydet') ?></span>
                    </button>

                    <!-- Sil Butonu -->
                    <button type="button" onclick="deleteActiveDoc()" class="cu-btn-sm" style="height:32px; padding:0 10px; background:rgba(231,76,60,0.15); color:#e74c3c; border:1px solid rgba(231,76,60,0.3);" title="<?= htmlspecialchars(__('Docs::docs_btn_delete', 'Dökümanı Sil'), ENT_QUOTES, 'UTF-8') ?>">
                        🗑️
                    </button>
                    <?php endif; ?>
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
                                <option value="1"><?= __('Docs::docs_h1') ?></option>
                                <option value="2"><?= __('Docs::docs_h2') ?></option>
                                <option value="3"><?= __('Docs::docs_h3') ?></option>
                                <option selected><?= __('tasks.format_normal', 'Normal') ?></option>
                            </select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-bold" title="<?= htmlspecialchars(__('Docs::docs_tool_bold')) ?>"></button>
                            <button class="ql-italic" title="<?= htmlspecialchars(__('Docs::docs_tool_italic')) ?>"></button>
                            <button class="ql-underline" title="<?= htmlspecialchars(__('Docs::docs_tool_underline')) ?>"></button>
                            <button class="ql-strike" title="<?= htmlspecialchars(__('tasks.format_strike', 'Üstü Çizili')) ?>"></button>
                        </span>
                        <span class="ql-formats">
                            <select class="ql-color" title="<?= htmlspecialchars(__('tasks.format_color', 'Yazı Rengi')) ?>"></select>
                            <select class="ql-background" title="<?= htmlspecialchars(__('tasks.format_bg', 'Arka Plan')) ?>"></select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-list" value="ordered" title="<?= htmlspecialchars(__('Docs::docs_tool_ordered')) ?>"></button>
                            <button class="ql-list" value="bullet" title="<?= htmlspecialchars(__('Docs::docs_tool_bullet')) ?>"></button>
                            <button class="ql-list" value="check" title="<?= htmlspecialchars(__('tasks.checklist_title', 'Kontrol Listesi')) ?>"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-blockquote" title="<?= htmlspecialchars(__('Docs::docs_tool_quote', 'Alıntı Ekle')) ?>"></button>
                            <button class="ql-code-block" title="<?= htmlspecialchars(__('Docs::docs_tool_code')) ?>"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-link" title="<?= htmlspecialchars(__('Docs::docs_tool_link')) ?>"></button>
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
                                        <span>📎</span> <?= __('Docs::docs_attachments_title', 'Döküman Ekleri & Dosyalar') ?>
                                        <span id="docAttachmentsCount" class="cu-nav-badge" style="background:var(--brand-primary); color:#fff; font-size:11px;">0</span>
                                    </h4>
                                    <button type="button" onclick="document.getElementById('docFileInput').click()" class="cu-btn-sm" style="font-size:12px; height:28px; padding:0 12px; background:rgba(123, 104, 238, 0.15); color:var(--brand-primary); border:1px solid rgba(123, 104, 238, 0.3);">
                                        <?= __('Docs::docs_upload_file', '+ Dosya Yükle') ?>
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
                                    <span style="font-size:12.5px; font-weight:600; color:var(--text-white);"><?= __('Docs::docs_dropzone_title', 'Dosyaları buraya sürükleyin veya') ?> <span style="color:var(--brand-primary); text-decoration:underline;"><?= __('Docs::docs_dropzone_select', 'seçin') ?></span></span>
                                    <span style="font-size:11px; color:var(--text-muted); display:block; margin-top:2px;"><?= __('Docs::docs_dropzone_sub', 'PDF, ZIP, Görsel veya panodan (Ctrl+V) yapıştırın (Max 20MB)') ?></span>
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
                            <span><?= __('Docs::docs_raw_source_title', '📝 Ham İçerik (Markdown veya HTML olarak düzenleyebilirsiniz)') ?></span>
                            <span><?= __('Docs::docs_auto_sync', 'Otomatik senkronize edilir') ?></span>
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
                <div style="font-size:18px; font-weight:700; color:var(--text-white); margin-bottom:8px;"><?= __('Docs::docs_no_docs_title', 'Döküman Bulunmuyor') ?></div>
                <div style="font-size:13px; margin-bottom:24px;"><?= __('Docs::docs_no_docs_desc', 'Sol panelden veya yukarıdaki butondan yeni bir döküman oluşturabilirsiniz.') ?></div>
                <button type="button" onclick="openNewDocPrompt()" class="cu-btn-quick-task" style="padding:10px 20px; font-size:13px;">
                    <span>➕</span> <?= __('Docs::docs_new_doc', 'Yeni Döküman Oluştur') ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
