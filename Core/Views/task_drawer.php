<!-- TaskManager Slide-Over Görev Detay Çekmecesi -->
<div class="cu-drawer-overlay" id="cuDrawerOverlay" onclick="closeTaskDrawer()"></div>

<div class="cu-drawer" id="cuTaskDrawer">
    <div class="cu-drawer-header">
        <div style="display:flex; align-items:center; gap:8px; overflow:hidden;">
            <span class="cu-drawer-ws-text" style="font-size:11px; color:var(--text-muted);"><?= __('tasks.drawer_workspace', 'Workspace') ?> &gt;</span>
            <span id="drawerProjectBadge" class="cu-nav-badge" style="background:rgba(123, 104, 238, 0.2); color:#7B68EE; font-weight:600; white-space:nowrap;"><?= __('tasks.drawer_project_fallback', 'Proje') ?></span>
            <span style="font-size:11px; color:var(--text-muted);">&gt;</span>
            <span class="cu-task-code" id="drawerTaskCode" style="font-size:13px; font-weight:700; white-space:nowrap;">TSK-000</span>
        </div>
        <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
            <button type="button" onclick="deleteCurrentTask()" class="cu-btn-sm" style="background:rgba(231,76,60,0.15); color:#e74c3c; border:1px solid rgba(231,76,60,0.3); font-size:11px; padding:4px 8px; display:flex; align-items:center; gap:4px;" title="<?= htmlspecialchars(__('tasks.drawer_btn_delete_task', 'Görevi Sil')) ?>">
                <span>🗑️</span> <span class="cu-drawer-del-label"><?= __('tasks.drawer_btn_delete_task', 'Görevi Sil') ?></span>
            </button>
            <button onclick="closeTaskDrawer()" style="background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer; padding:4px 8px; border-radius:4px;">✕</button>
        </div>
    </div>

    <div class="cu-drawer-body">
        <!-- Başlık (TaskManager Inline Düzenlenebilir) -->
        <div style="position:relative; margin-bottom:14px;">
            <h2 id="drawerTaskTitle" contenteditable="true" spellcheck="false" title="<?= htmlspecialchars(__('tasks.click_to_edit', 'Düzenlemek için tıklayın')) ?>" onblur="handleInlineTitleSave(this)" style="font-size:18px; font-weight:700; color:var(--text-white); line-height:1.4; padding:4px 8px; border-radius:var(--radius-sm); border:1px solid transparent; outline:none; transition:var(--transition);">
                <?= __('tasks.drawer_title_placeholder', 'Görev Başlığı') ?>
            </h2>
            <span style="font-size:10px; color:var(--text-muted); margin-left:8px;"><?= __('tasks.drawer_title_edit_hint', '(Başlığı doğrudan düzenleyebilirsiniz)') ?></span>
        </div>

        <!-- Etiketler (Tags) -->
        <div style="display:flex; align-items:center; gap:6px; margin-bottom:16px;" id="drawerTagsContainer">
            <!-- Dinamik etiketler JS ile yüklenir -->
        </div>

        <!-- Meta Bilgiler & Custom Fields (Grid) -->
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; background:var(--bg-app); padding:14px; border-radius:var(--radius-sm); border:1px solid var(--border-subtle); margin-bottom:20px;">
            <div>
                <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600;"><?= __('tasks.label_status', 'DURUM:') ?></span>
                <div id="drawerStatusBadge" style="margin-top:4px;"></div>
            </div>
            <div>
                <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600;"><?= __('tasks.label_priority', 'ÖNCELİK:') ?></span>
                <div id="drawerPriorityBadge" style="margin-top:4px;"></div>
            </div>
            <div>
                <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600;"><?= __('tasks.label_deadline', 'SON TARİH:') ?></span>
                <div id="drawerDeadline" style="margin-top:4px; font-size:12px; font-weight:500; color:var(--text-primary);"></div>
            </div>
            <div>
                <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600;"><?= __('tasks.label_time_estimate', 'TAHMİNİ SÜRE:') ?></span>
                <div id="drawerTimeEstimate" style="margin-top:4px; font-size:12px; font-weight:600; color:var(--brand-primary);">4h</div>
            </div>
            <div>
                <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600;"><?= __('tasks.label_time_spent', 'HARCANAN SÜRE:') ?></span>
                <div id="drawerTimeSpent" style="margin-top:4px; font-size:12px; font-weight:600; color:#00b894;">1h 30m</div>
            </div>
            <div style="display:none;" id="drawerAssignee"></div>

            <!-- TaskManager ÇOKLU ATANAN ÇALIŞANLAR (@ MENTION SEÇİCİ) -->
            <div style="grid-column: span 3; border-top:1px solid rgba(255,255,255,0.06); padding-top:12px; margin-top:4px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.5px;"><?= __('tasks.label_assignees', '👥 ATANAN ÇALIŞANLAR:') ?></span>
                    <button type="button" onclick="focusDrawerAssigneeInput()" style="background:none; border:none; color:var(--brand-primary); font-size:11px; font-weight:600; cursor:pointer; padding:2px 6px;">
                        <?= __('tasks.btn_add_assignee', '+ @ ile Çalışan Ekle') ?>
                    </button>
                </div>

                <!-- Yan yana dizilen atanan çalışanlar listesi -->
                <div id="drawerAssigneesContainer" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; min-height:32px;">
                    <!-- JS ile dinamik chipler render edilir -->
                </div>

                <!-- @ Hızlı Ekleme Inputu ve Açılır Liste -->
                <div style="position:relative; margin-top:8px; max-width:320px;">
                    <div style="position:relative; display:flex; align-items:center;">
                        <span style="position:absolute; left:10px; color:var(--brand-primary); font-weight:700; font-size:13px; pointer-events:none;">@</span>
                        <input type="text" id="drawerAssigneeInput" class="cu-form-control" style="height:32px; font-size:12px; padding-left:26px;" placeholder="<?= htmlspecialchars(__('tasks.input_assignee_placeholder', '@ yazarak çalışan ekleyin...')) ?>" autocomplete="off" onclick="handleDrawerAssigneeSearch(this.value)" oninput="handleDrawerAssigneeSearch(this.value)" onfocus="handleDrawerAssigneeSearch(this.value)">
                    </div>
                    <div id="drawerAssigneeDropdown" style="display:none; position:absolute; left:0; right:0; top:calc(100% + 4px); background:#1a1c26; border:1px solid rgba(123, 104, 238, 0.4); border-radius:var(--radius-sm); box-shadow:0 12px 36px rgba(0,0,0,0.85); z-index:99999; max-height:180px; overflow-y:auto;">
                        <!-- Dinamik kullanıcı listesi -->
                    </div>
                </div>
            </div>
        </div>

        <!-- SÜRE TAKİBİ (TIME TRACKER) -->
        <div style="margin-bottom:24px; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:14px;">⏱️</span>
                    <span style="font-size:13px; font-weight:700; color:var(--text-white);"><?= __('tasks.time_tracking', 'Süre Takibi') ?></span>
                </div>
                <div id="timerDisplay" style="font-family:monospace; font-size:13px; font-weight:700; color:var(--brand-primary); background:rgba(123, 104, 238, 0.15); padding:3px 10px; border-radius:12px; border:1px solid rgba(123, 104, 238, 0.3);">
                    00:00:00
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <button type="button" id="btnToggleTimer" onclick="toggleTaskTimer()" class="cu-btn-sm" style="background:var(--brand-primary); color:#fff; font-weight:600; display:flex; align-items:center; gap:6px;">
                    <span id="btnTimerIcon">▶</span> <span id="btnTimerText"><?= __('tasks.btn_start_timer', 'Sayacı Başlat') ?></span>
                </button>
                <div style="display:flex; align-items:center; gap:6px; margin-left:auto;">
                    <span style="font-size:11px; color:var(--text-muted);"><?= __('tasks.quick_add', 'Hızlı Ekle:') ?></span>
                    <button type="button" onclick="quickAddMinutes(15)" class="cu-btn-sm" style="background:rgba(255,255,255,0.06); font-size:11px; padding:4px 8px;"><?= __('tasks.quick_add_15m', '+15 dk') ?></button>
                    <button type="button" onclick="quickAddMinutes(30)" class="cu-btn-sm" style="background:rgba(255,255,255,0.06); font-size:11px; padding:4px 8px;"><?= __('tasks.quick_add_30m', '+30 dk') ?></button>
                    <button type="button" onclick="quickAddMinutes(60)" class="cu-btn-sm" style="background:rgba(255,255,255,0.06); font-size:11px; padding:4px 8px;"><?= __('tasks.quick_add_1h', '+1 saat') ?></button>
                </div>
            </div>
        </div>

        <!-- TaskManager KONTROL LİSTESİ (CHECKLIST & SUB-ITEMS) -->
        <div style="margin-bottom:24px; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:14px;">☑️</span>
                    <span style="font-size:13px; font-weight:700; color:var(--text-white);"><?= __('tasks.checklist', 'Kontrol Listesi (Checklist)') ?></span>
                </div>
                <span id="drawerChecklistProgressText" style="font-size:11.5px; font-weight:700; color:var(--brand-primary);">0 / 0 (%0)</span>
            </div>

            <!-- İlerleme Çubuğu -->
            <div style="width:100%; height:6px; background:rgba(255,255,255,0.08); border-radius:3px; overflow:hidden; margin-bottom:14px;">
                <div id="drawerChecklistProgressBar" style="width:0%; height:100%; background:linear-gradient(90deg, #7B68EE, #00b894); transition:width 0.3s ease;"></div>
            </div>

            <!-- Liste Maddeleri -->
            <div id="drawerChecklistItems" style="display:flex; flex-direction:column; gap:8px; margin-bottom:12px;">
                <!-- Dinamik maddeler JS ile yüklenir -->
            </div>

            <!-- Yeni Madde Ekleme Formu -->
            <form id="formAddChecklist" onsubmit="handleAddChecklistItem(event)" style="display:flex; gap:8px; align-items:center;">
                <input type="text" id="inputNewChecklistTitle" class="cu-form-control" placeholder="<?= htmlspecialchars(__('tasks.input_checklist_placeholder', '+ Kontrol maddesi ekle... (Örn: Birim testleri yaz)')) ?>" style="height:32px; font-size:12px;" required>
                <button type="submit" class="cu-btn-sm" style="height:32px; padding:0 12px; flex-shrink:0;"><?= __('tasks.btn_add', 'Ekle') ?></button>
            </form>
        </div>

        <!-- TaskManager ALT GÖREVLER (NESTED SUBTASKS) -->
        <div style="margin-bottom:24px; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:14px;">📂</span>
                    <span style="font-size:13px; font-weight:700; color:var(--text-white);"><?= __('tasks.subtasks', 'Alt Görevler (Subtasks)') ?></span>
                </div>
                <span id="drawerSubtaskCount" class="cu-nav-badge" style="background:rgba(123, 104, 238, 0.2); color:#7B68EE; font-weight:700;">0</span>
            </div>

            <!-- Alt Görev Listesi -->
            <div id="drawerSubtaskList" style="display:flex; flex-direction:column; gap:6px; margin-bottom:12px;">
                <!-- Dinamik alt görevler JS ile yüklenir -->
            </div>

            <!-- Yeni Alt Görev Ekleme Formu -->
            <form id="formAddSubtask" onsubmit="handleAddSubtask(event)" style="display:flex; gap:8px; align-items:center;">
                <input type="text" id="inputNewSubtaskTitle" class="cu-form-control" placeholder="<?= htmlspecialchars(__('tasks.input_subtask_placeholder', '+ Yeni alt görev ekle... (Örn: Veritabanı tablosunu bağla)')) ?>" style="height:32px; font-size:12px;" required>
                <button type="submit" class="cu-btn-sm" style="height:32px; padding:0 12px; flex-shrink:0;"><?= __('tasks.btn_add', 'Ekle') ?></button>
            </form>
        </div>

        <!-- TaskManager BAĞIMLILIKLAR (TASK DEPENDENCIES: WAITING ON & BLOCKING) -->
        <div style="margin-bottom:24px; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:14px;">🔗</span>
                    <span style="font-size:13px; font-weight:700; color:var(--text-white);"><?= __('tasks.dependencies', 'Bağımlılıklar (Dependencies)') ?></span>
                </div>
                <span id="drawerDepCount" class="cu-nav-badge" style="background:rgba(255, 170, 0, 0.15); color:#ffaa00; font-weight:700;">0</span>
            </div>

            <!-- Bağımlılık Listesi -->
            <div id="drawerDependenciesList" style="display:flex; flex-direction:column; gap:6px; margin-bottom:12px;">
                <!-- Dinamik bağımlılıklar JS ile yüklenir -->
            </div>

            <!-- Yeni Bağımlılık Ekleme Formu -->
            <form id="formAddDependency" onsubmit="handleAddDependency(event)" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <select id="selectDepType" class="cu-form-control" style="min-width:160px; width:160px; height:32px; font-size:12px;">
                    <option value="waiting_on"><?= __('tasks.dep_waiting_on', '⏳ Bekliyor (Waiting on)') ?></option>
                    <option value="blocking"><?= __('tasks.dep_blocking', '🚫 Engelliyor (Blocking)') ?></option>
                </select>
                <select id="selectDepTask" class="cu-form-control" style="flex:1; min-width:160px; height:32px; font-size:12px;">
                    <!-- JS ile dinamik doldurulur -->
                </select>
                <button type="submit" class="cu-btn-sm" style="height:32px; padding:0 12px; flex-shrink:0;"><?= __('tasks.btn_bind', 'Bağla') ?></button>
            </form>
        </div>

        <!-- İŞ AKIŞI AKSİYON PANELİ (WORKFLOW ACTION BOX) -->
        <div class="cu-drawer-actions" id="drawerWorkflowBox">
            <h4 id="drawerActionTitle"><?= __('tasks.workflow_actions', '⚡ Görev Aksiyonları') ?></h4>
            <p id="drawerActionDesc" style="font-size:12px; color:var(--text-secondary); margin-bottom:12px;"></p>
            <div class="cu-workflow-btn-group" id="drawerBtnGroup">
                <!-- Dinamik Butonlar AJAX / JS ile basılır -->
            </div>
        </div>

        <!-- TaskManager DÜZENLENEBİLİR AÇIKLAMA ALANI (EDITABLE RICH DESCRIPTION) -->
        <div style="margin-bottom:24px; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:14px;">📝</span>
                    <h4 style="font-size:12px; text-transform:uppercase; font-weight:700; color:var(--text-white); margin:0;"><?= __('tasks.description_instructions', 'Açıklama & Talimatlar') ?></h4>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span id="drawerDescSaveStatus" style="font-size:11px; color:var(--text-muted);"><?= __('tasks.click_to_edit', 'Düzenlemek için tıklayın') ?></span>
                    <button type="button" id="btnSaveDrawerDesc" onclick="saveTaskDescription()" class="cu-btn-sm" style="display:none; height:26px; padding:0 10px; font-size:11px; background:var(--brand-primary); font-weight:600;">
                        <?= __('tasks.btn_save', '💾 Kaydet') ?>
                    </button>
                </div>
            </div>

            <!-- Mini Format Toolbar -->
            <div id="drawerDescToolbar" style="display:flex; gap:4px; margin-bottom:8px; padding-bottom:6px; border-bottom:1px solid rgba(255,255,255,0.06);">
                <button type="button" onclick="formatDescText('bold')" class="cu-doc-tool-btn" style="padding:2px 6px; font-size:11px;" title="<?= htmlspecialchars(__('app.tasks_format_bold')) ?>"><b>B</b></button>
                <button type="button" onclick="formatDescText('italic')" class="cu-doc-tool-btn" style="padding:2px 6px; font-size:11px;" title="<?= htmlspecialchars(__('app.tasks_format_italic')) ?>"><i>I</i></button>
                <button type="button" onclick="formatDescText('insertUnorderedList')" class="cu-doc-tool-btn" style="padding:2px 6px; font-size:11px;" title="<?= htmlspecialchars(__('app.tasks_format_list')) ?>">• <?= __('app.tasks_format_list') ?></button>
                <button type="button" onclick="formatDescText('insertOrderedList')" class="cu-doc-tool-btn" style="padding:2px 6px; font-size:11px;" title="<?= htmlspecialchars(__('app.tasks_format_numbered_list')) ?>">1. <?= __('app.tasks_format_list') ?></button>
                <button type="button" onclick="formatDescBlock('blockquote')" class="cu-doc-tool-btn" style="padding:2px 6px; font-size:11px;" title="<?= htmlspecialchars(__('app.tasks_format_quote')) ?>">❝</button>
                <button type="button" onclick="formatDescBlock('pre')" class="cu-doc-tool-btn" style="padding:2px 6px; font-size:11px;" title="<?= htmlspecialchars(__('app.tasks_format_code')) ?>">&lt;/&gt;</button>
            </div>

            <!-- Düzenlenebilir İçerik Tuvali -->
            <div id="drawerDescription" 
                 contenteditable="true" 
                 oninput="handleDescInput()"
                 onfocus="handleDescFocus()"
                 onblur="handleDescBlur()"
                 style="font-size:13.5px; line-height:1.65; color:var(--text-primary); background:rgba(0,0,0,0.2); padding:12px; border-radius:var(--radius-sm); border:1px solid var(--border-subtle); min-height:80px; outline:none; transition:var(--transition);">
            </div>
        </div>

        <!-- GÖREV RESİMLERİ & EKLERİ (TASK IMAGES & ATTACHMENTS) -->
        <div style="margin-bottom:24px; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:14px;">🖼️</span>
                    <h4 style="font-size:12px; text-transform:uppercase; font-weight:700; color:var(--text-white); margin:0;"><?= __('tasks.images_attachments', 'Görseller & Ek Dosyalar') ?></h4>
                    <span id="drawerAttachmentCount" class="cu-nav-badge" style="background:rgba(123, 104, 238, 0.2); color:var(--brand-primary); font-weight:700;">0</span>
                </div>
                <div>
                    <input type="file" id="drawerAttachmentFileInput" multiple accept="image/*,.pdf,.zip" style="display:none;" onchange="handleDrawerFileUpload(this)">
                    <button type="button" onclick="document.getElementById('drawerAttachmentFileInput').click()" class="cu-btn-sm" style="display:flex; align-items:center; gap:6px; background:var(--brand-primary); color:#fff; font-weight:600; cursor:pointer;">
                        <span>➕</span> <span><?= __('tasks.btn_add_attachment', 'Resim / Ek Ekle') ?></span>
                    </button>
                </div>
            </div>

            <!-- Sürükle Bırak / Yapıştır Alanı -->
            <div id="drawerDropzone" 
                 ondragover="handleDropzoneOver(event)" 
                 ondragleave="handleDropzoneLeave(event)" 
                 ondrop="handleDropzoneDrop(event)"
                 onclick="document.getElementById('drawerAttachmentFileInput').click()"
                 style="border: 2px dashed var(--border-subtle); border-radius: var(--radius-sm); padding: 14px; text-align: center; cursor: pointer; background: rgba(255,255,255,0.02); transition: var(--transition); margin-bottom: 12px;">
                <div style="font-size: 18px; margin-bottom: 4px;">📸 📤</div>
                <div style="font-size: 12px; font-weight: 600; color: var(--text-primary);"><?= __('tasks.dropzone_hint_main', 'Resim yüklemek için tıklayın veya buraya sürükleyin') ?></div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?= __('tasks.dropzone_hint_sub', 'Panodan doğrudan Ctrl+V ile resim yapıştırabilirsiniz (PNG, JPG, WEBP, GIF, PDF)') ?></div>
            </div>

            <!-- Yüklenen Görseller Grid Listesi -->
            <div id="drawerAttachmentsContainer" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(130px, 1fr)); gap:10px;">
                <!-- JS ile dinamik render edilir -->
            </div>
        </div>

        <!-- Teslimat & Revizyon Notları (Varsa) -->
        <div id="drawerNotesBox" style="display:none; margin-bottom:24px; background:rgba(230, 126, 34, 0.08); border:1px solid rgba(230, 126, 34, 0.25); border-radius:var(--radius-sm); padding:14px;">
            <h4 id="drawerNotesTitle" style="font-size:12px; font-weight:700; color:#e67e22; margin-bottom:6px;"><?= __('tasks.notes_revision_title', 'Çalışanın Revizyon / Açıklama Gerekçesi:') ?></h4>
            <p id="drawerNotesContent" style="font-size:12.5px; color:var(--text-primary); line-height:1.5;"></p>
            <div id="drawerAttachmentLink" style="margin-top:8px;"></div>
            <div id="drawerDevopsContainer" style="margin-top:10px;"></div>
        </div>

        <!-- TaskManager YORUMLAR & SORU-CEVAP (COMMENTS & DISCUSSION) -->
        <div style="margin-bottom:28px; background:var(--bg-app); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <h4 style="font-size:13px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px;">
                    <span>💬</span> <?= __('tasks.discussion_comments', 'Görev Tartışması & Yorumlar') ?>
                </h4>
                <span id="drawerCommentCount" class="cu-nav-badge" style="background:var(--brand-primary); color:#fff;">0</span>
            </div>

            <!-- Yorum Listesi -->
            <div id="drawerCommentsList" style="display:flex; flex-direction:column; gap:12px; max-height:280px; overflow-y:auto; padding-right:6px; margin-bottom:16px;">
                <!-- Dinamik yorumlar JS ile yüklenir -->
            </div>

            <!-- Yeni Yorum / Soru Formu (Sürükle-Bırak & Panodan Yapıştırma Destekli) -->
            <form id="formAddComment" onsubmit="handleAddComment(event)" style="border-top:1px solid var(--border-subtle); padding-top:14px;">
                <div style="display:flex; gap:10px; align-items:flex-start;">
                    <div class="cu-avatar" id="drawerMyAvatar" style="width:30px; height:30px; font-size:11px; flex-shrink:0;">
                        <?= __('tasks.comment_me', 'Ben') ?>
                    </div>
                    <div style="flex:1;">
                        <div id="commentDropzoneContainer"
                             ondragover="handleCommentDropzoneOver(event)"
                             ondragleave="handleCommentDropzoneLeave(event)"
                             ondrop="handleCommentDropzoneDrop(event)"
                             style="border-radius:var(--radius-md); transition:all 0.2s;">
                            <textarea id="drawerCommentInput" name="comment" class="cu-form-control" rows="2" placeholder="<?= htmlspecialchars(__('tasks.comment_placeholder', 'Soru sorun, bilgi paylaşın veya yorum yazın... (Ctrl+Enter, Ctrl+V ile resim yapıştırın)')) ?>" style="resize:vertical; font-size:12.5px; line-height:1.4;" required></textarea>
                            
                            <!-- Eklenen Dosya Önizlemesi -->
                            <div id="commentFilePreview" style="display:none; margin-top:8px; padding:6px 12px; background:rgba(255,255,255,0.04); border:1px solid var(--border-subtle); border-radius:6px; align-items:center; gap:8px;">
                                <span id="commentPreviewIcon" style="font-size:16px;">📎</span>
                                <div id="commentPreviewThumb" style="display:none; width:28px; height:28px; border-radius:4px; overflow:hidden; background-size:cover; background-position:center;"></div>
                                <span id="commentPreviewName" style="font-size:12px; font-weight:600; color:var(--text-white); flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></span>
                                <span id="commentPreviewSize" style="font-size:11px; color:var(--text-muted);"></span>
                                <button type="button" onclick="clearCommentFile()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px; padding:0 4px;" title="<?= htmlspecialchars(__('app.tasks_remove_file')) ?>">✕</button>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <label style="cursor:pointer; font-size:12px; color:var(--brand-primary); display:inline-flex; align-items:center; gap:5px; margin:0; padding:4px 10px; border-radius:6px; background:rgba(123, 104, 238, 0.12); border:1px solid rgba(123, 104, 238, 0.25); font-weight:600;">
                                    <span>📎</span> <span><?= __('tasks.btn_add_file', 'Dosya Ekle') ?></span>
                                    <input type="file" id="commentFileInput" onchange="handleCommentFileSelect(this)" style="display:none;" accept="image/*,.pdf,.zip,.rar,.7z,.txt,.doc,.docx,.xls,.xlsx">
                                </label>
                                <span style="font-size:11px; color:var(--text-muted);"><?= __('tasks.paste_image_hint', 'Panodan (Ctrl+V) görsel yapıştırabilirsiniz') ?></span>
                            </div>
                            <button type="submit" class="cu-btn-quick-task" style="height:30px; padding:0 14px; font-size:12px; font-weight:600;">
                                <span>💬</span> <?= __('tasks.btn_send_comment', 'Gönder') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- TaskManager Aktivite Zaman Çizelgesi (Audit Stream) -->
        <div>
            <h4 style="font-size:12px; text-transform:uppercase; font-weight:700; color:var(--text-muted); margin-bottom:12px;"><?= __('tasks.timeline_audit', 'Sistem Zaman Çizelgesi (Audit Log)') ?></h4>
            <div class="cu-timeline" id="drawerTimeline">
                <!-- Dinamik loglar JS ile basılır -->
            </div>
        </div>
    </div>
</div>

<!-- GÖRSEL ÖNİZLEME (IMAGE LIGHTBOX MODAL) -->
<div class="cu-modal-overlay" id="cuImageLightboxModal" onclick="closeImageLightbox(event)" style="z-index:99999; backdrop-filter:blur(8px); background:rgba(0,0,0,0.88);">
    <div style="position:relative; max-width:92vw; max-height:92vh; display:flex; flex-direction:column; align-items:center;">
        <!-- Kontrol Butonları -->
        <div style="position:absolute; top:-45px; right:0; display:flex; gap:10px; align-items:center;">
            <a id="lightboxDownloadBtn" href="#" download target="_blank" style="background:rgba(255,255,255,0.15); color:#fff; text-decoration:none; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:600; display:flex; align-items:center; gap:5px; backdrop-filter:blur(4px);">
                <span>⬇️</span> <?= __('tasks.btn_download', 'İndir') ?>
            </a>
            <button type="button" onclick="closeImageLightbox(null)" style="background:rgba(255,255,255,0.15); border:none; color:#fff; font-size:18px; cursor:pointer; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(4px);">✕</button>
        </div>
        <img id="lightboxImage" src="" alt="<?= htmlspecialchars(__('tasks.preview', 'Önizleme')) ?>" style="max-width:100%; max-height:82vh; border-radius:10px; box-shadow:0 20px 60px rgba(0,0,0,0.9); object-fit:contain; cursor:zoom-out;">
        <div id="lightboxCaption" style="margin-top:12px; color:#fff; font-size:13px; font-weight:600; text-align:center; text-shadow:0 2px 4px rgba(0,0,0,0.8);"></div>
    </div>
</div>

<!-- TaskManager İstemci Tarafı (JS) Dil Sözlüğü -->
<script>
window.TASK_I18N = {
    loading: '<?= addslashes(__('tasks.loading', 'Yükleniyor...')) ?>',
    comments_loading: '<?= addslashes(__('tasks.comments_loading', 'Yorumlar yükleniyor...')) ?>',
    desc_empty_hint: '<?= addslashes(__('tasks.desc_empty_hint', 'Görev açıklaması ve talimatları yazmak için buraya tıklayın...')) ?>',
    click_to_edit: '<?= addslashes(__('tasks.click_to_edit', 'Düzenlemek için tıklayın')) ?>',
    unassigned: '<?= addslashes(__('tasks.unassigned', 'Atanmamış')) ?>',
    not_specified: '<?= addslashes(__('tasks.not_specified', 'Belirtilmemiş')) ?>',
    notes_revision_title: '<?= addslashes(__('tasks.notes_revision_title', 'Çalışanın Revizyon / Açıklama Gerekçesi:')) ?>',
    notes_submission_title: '<?= addslashes(__('tasks.notes_submission_title', 'Çalışanın Teslim Notu:')) ?>',
    link_open_submission: '<?= addslashes(__('tasks.link_open_submission', '🔗 Teslimat / Çalışma Bağlantısını Aç')) ?>',
    btn_start_timer: '<?= addslashes(__('tasks.btn_start_timer', 'Sayacı Başlat')) ?>',
    btn_stop_timer: '<?= addslashes(__('tasks.btn_stop_timer', 'Sayacı Durdur')) ?>',
    btn_accept_task: '<?= addslashes(__('tasks.btn_accept_task', '⚡ Görevi Kabul Et')) ?>',
    btn_request_mod: '<?= addslashes(__('tasks.btn_request_mod', '📝 Revizyon / Açıklama İste')) ?>',
    btn_submit_task: '<?= addslashes(__('tasks.btn_submit_task', '🚀 Görevi Teslim Et (Tamamlandı)')) ?>',
    btn_approve_task: '<?= addslashes(__('tasks.btn_approve_task', '✅ Teslimatı Onayla & Tamamla')) ?>',
    btn_request_changes: '<?= addslashes(__('tasks.btn_request_changes', '↩️ Düzeltme / Revize İste')) ?>',
    btn_resolve_mod: '<?= addslashes(__('tasks.btn_resolve_mod', '✔️ Revizyonu Kabul Et & Yeniden Ata')) ?>',
    status: {
        assigned: '<?= addslashes(__('tasks.status_assigned', 'ATANDI')) ?>',
        accepted: '<?= addslashes(__('tasks.status_accepted', 'KABUL EDİLDİ')) ?>',
        modification_requested: '<?= addslashes(__('tasks.status_modification_requested', 'REVİZYON İSTENDİ')) ?>',
        in_progress: '<?= addslashes(__('tasks.status_in_progress', 'ÇALIŞILIYOR')) ?>',
        submitted: '<?= addslashes(__('tasks.status_submitted', 'TESLİM EDİLDİ')) ?>',
        under_review: '<?= addslashes(__('tasks.status_under_review', 'İNCELENİYOR')) ?>',
        completed: '<?= addslashes(__('tasks.status_completed', 'TAMAMLANDI')) ?>'
    },
    priority: {
        urgent: '<?= addslashes(__('tasks.priority_urgent_badge', '🔴 Acil')) ?>',
        high: '<?= addslashes(__('tasks.priority_high_badge', '🟡 Yüksek')) ?>',
        normal: '<?= addslashes(__('tasks.priority_normal_badge', '🔵 Normal')) ?>',
        low: '<?= addslashes(__('tasks.priority_low_badge', '⚪ Düşük')) ?>'
    },
    devops: {
        status_title: '<?= addslashes(__('app.devops_status_title', 'GitHub & DevOps Durumu')) ?>',
        reverted: '<?= addslashes(__('app.devops_reverted', '⏪ Geri Alındı (Reverted)')) ?>',
        synced: '<?= addslashes(__('app.devops_synced', '🚀 Senkronize')) ?>',
        db_impact: '<?= addslashes(__('app.devops_db_impact', '🗄️ Veritabanı Etkisi:')) ?>',
        no_db_changes: '<?= addslashes(__('app.devops_no_db_changes', 'Veritabanı şema değişikliği yok (Sadece Kod)')) ?>',
        view_diff: '<?= addslashes(__('app.devops_view_diff', '🔗 GitHub\'da İncele (Diff)')) ?>',
        btn_rollback: '<?= addslashes(__('app.devops_btn_rollback', '⏪ Değişiklikleri Geri Al (Rollback)')) ?>',
        rollback_confirm: '<?= addslashes(__('app.devops_rollback_confirm', 'Bu görevin Git commit değişikliklerini ve veritabanı etkilerini geri almak istediğinize emin misiniz?')) ?>',
        rollback_running: '<?= addslashes(__('app.devops_rollback_running', '⏪ Geri alma işlemi yürütülüyor...')) ?>',
        ai_surgeon_running: '<?= addslashes(__('app.devops_ai_surgeon_running', '🤖 AI Cerrah çakışmaları analiz edip temizliyor...')) ?>',
        ai_surgeon_prompt: '<?= addslashes(__('app.devops_ai_surgeon_prompt', '🤖 AI Cerrah Modunu (AI Surgeon) devreye alıp çakışmayı otomatik analiz edip çözerek geri alma işlemini tamamlamak ister misiniz?')) ?>'
    }
};
</script>
