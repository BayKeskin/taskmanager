<!-- 1. YENİ GÖREV EKLEME MODALI -->
<div class="cu-modal-overlay" id="modalNewTask">
    <div class="cu-modal">
        <div class="cu-modal-header">
            <h3 style="font-size:15px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px;">
                <span>➕</span> <?= __('app.modal_new_task_title') ?>
            </h3>
            <button onclick="closeModal('modalNewTask')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;">✕</button>
        </div>
        <form id="formNewTask" onsubmit="handleCreateTask(event)">
            <div class="cu-modal-body">
                <div class="cu-form-group">
                    <label><?= __('app.modal_project_space') ?></label>
                    <select name="project_id" id="modalNewTaskProjectSelect" class="cu-form-control" required>
                        <?php 
                        $curProjId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : ($activeProjectId ?? null);
                        foreach ($allProjects as $p): 
                            $isSelected = ($curProjId && (int)$p->id === (int)$curProjId) ? 'selected' : '';
                        ?>
                            <option value="<?= $p->id ?>" <?= $isSelected ?>><?= htmlspecialchars($p->title) ?> (<?= $p->project_code ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cu-form-group">
                    <label><?= __('app.modal_task_title') ?></label>
                    <input type="text" name="title" id="modalNewTaskTitleInput" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_task_title_placeholder')) ?>" required>
                </div>

                <!-- 🪄 AI GÖREV MİMARI (TASK ARCHITECT PANEL) -->
                <div class="cu-form-group" style="background:linear-gradient(135deg, rgba(123, 104, 238, 0.08) 0%, rgba(155, 93, 229, 0.04) 100%); border:1px solid rgba(123, 104, 238, 0.25); border-radius:var(--radius-sm); padding:12px; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;" onclick="toggleAiArchitectPanel()">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:14px;">🪄</span>
                            <span style="font-size:12px; font-weight:700; color:var(--brand-primary); text-transform:uppercase; letter-spacing:0.03em;">
                                <?= __('app.modal_ai_architect_badge') ?>
                            </span>
                            <span style="font-size:11px; color:var(--text-muted); font-weight:400;" class="d-none d-sm-inline">
                                — <?= __('app.modal_ai_architect_title') ?>
                            </span>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span id="aiArchitectToggleIcon" style="font-size:11px; color:var(--brand-primary); font-weight:700;">▼</span>
                        </div>
                    </div>

                    <!-- Açılır Panel Gövdesi -->
                    <div id="aiArchitectPanelBody" style="display:none; margin-top:12px; padding-top:12px; border-top:1px dashed rgba(123, 104, 238, 0.2);">
                        <p style="font-size:11px; color:var(--text-muted); margin-bottom:8px; line-height:1.4;">
                            <?= __('app.modal_ai_architect_desc') ?>
                        </p>

                        <!-- Kısa Talimat Alanı (Zorunlu) -->
                        <div style="margin-bottom:10px;">
                            <label style="font-size:11.5px; font-weight:600; color:var(--text-primary); margin-bottom:4px; display:block;">
                                <?= __('app.modal_ai_architect_prompt_label') ?> <span style="color:#ff4757;">*</span>
                            </label>
                            <textarea id="aiArchitectInstruction" class="cu-form-control" rows="2" style="font-size:12px; background:var(--bg-input);" placeholder="<?= htmlspecialchars(__('app.modal_ai_architect_prompt_ph')) ?>"></textarea>
                        </div>

                        <!-- Opsiyonel Ekler (Görsel ve Kod Dosyası) -->
                        <div style="margin-bottom:10px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                <label style="font-size:11.5px; font-weight:600; color:var(--text-primary); margin-bottom:0;">
                                    <?= __('app.modal_ai_architect_ref_label') ?>
                                </label>
                                <span style="font-size:10.5px; color:var(--text-muted);"><?= __('app.modal_ai_architect_ref_hint') ?></span>
                            </div>

                            <input type="file" id="aiArchitectFileInput" multiple accept="image/*,.php,.js,.blade.php,.sql,.html,.css,.json,.txt,.py" style="display:none;" onchange="handleAiArchitectFiles(this.files)">

                            <!-- Sürükle Bırak / Tıkla Alanı -->
                            <div id="aiArchitectDropzone"
                                 ondragover="handleAiArchitectDragOver(event)"
                                 ondragleave="handleAiArchitectDragLeave(event)"
                                 ondrop="handleAiArchitectDrop(event)"
                                 onclick="document.getElementById('aiArchitectFileInput').click()"
                                 style="border:1.5px dashed rgba(123, 104, 238, 0.35); border-radius:var(--radius-sm); padding:8px 12px; text-align:center; cursor:pointer; background:rgba(0,0,0,0.1); transition:var(--transition);">
                                <span style="font-size:12px;">📁 📸</span>
                                <span style="font-size:11px; font-weight:600; color:var(--brand-primary); margin-left:6px;">Dosya/Görsel Seç veya Sürükle (Opsiyonel)</span>
                                <span style="font-size:10.5px; color:var(--text-muted); margin-left:6px;">(Ctrl+V ile görsel yapıştırılabilir)</span>
                            </div>

                            <!-- Seçilen Referans Dosyalar Listesi -->
                            <div id="aiArchitectFilesPreview" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;"></div>
                        </div>

                        <!-- AI Üret Butonu -->
                        <div style="display:flex; justify-content:flex-end; align-items:center; gap:8px;">
                            <button type="button" id="btnRunAiArchitect" onclick="runAiArchitect()" class="cu-btn-sm" style="background:var(--brand-primary); color:#ffffff; font-weight:700; display:inline-flex; align-items:center; gap:6px; padding:6px 14px; font-size:11.5px; border-radius:var(--radius-sm); cursor:pointer; border:none; transition:var(--transition);">
                                <span>🪄</span> <span id="btnRunAiArchitectText"><?= __('app.modal_ai_architect_btn') ?></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="cu-form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="margin-bottom:0;"><?= __('app.modal_desc_instructions') ?></label>
                        <span style="font-size:11px; color:var(--text-muted);"><?= __('tasks.click_to_edit', 'Düzenlemek için tıklayın') ?></span>
                    </div>
                    <textarea name="description" id="modalNewTaskDescription" class="cu-form-control" rows="5" placeholder="<?= htmlspecialchars(__('app.modal_desc_placeholder')) ?>"></textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="cu-form-group">
                        <label><?= __('app.modal_priority') ?></label>
                        <select name="priority" class="cu-form-control">
                            <option value="urgent"><?= __('tasks.priority_urgent') ?></option>
                            <option value="high"><?= __('tasks.priority_high') ?></option>
                            <option value="normal" selected><?= __('tasks.priority_normal') ?></option>
                            <option value="low"><?= __('tasks.priority_low') ?></option>
                        </select>
                    </div>
                    <div class="cu-form-group">
                        <label><?= __('app.modal_deadline') ?></label>
                        <input type="date" name="deadline" class="cu-form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                    </div>
                </div>

                <!-- ETİKETLER (TAGS) & AI OTONOM SEÇİMİ -->
                <div class="cu-form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="margin-bottom:0;"><?= __('app.modal_tags') ?></label>
                        <span style="font-size:11px; color:var(--text-muted);"><?= __('app.modal_tags_hint') ?></span>
                    </div>
                    <div style="position:relative; display:flex; gap:8px; align-items:center;">
                        <input type="text" name="tags" id="modalNewTaskTagsInput" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_tags_placeholder')) ?>" style="flex:1;">
                        <?php if ($canUseAiTasks): ?>
                            <button type="button" id="btnToggleAiTag" onclick="toggleNewTaskAiTag()" style="padding:6px 12px; font-size:11.5px; font-weight:700; border-radius:var(--radius-sm); border:1px solid rgba(155, 93, 229, 0.45); background:rgba(155, 93, 229, 0.15); color:#d8b4fe; cursor:pointer; display:flex; align-items:center; gap:5px; white-space:nowrap; transition:var(--transition);" title="<?= htmlspecialchars(__('app.modal_ai_btn_title')) ?>">
                                <span>🤖</span> <span id="btnToggleAiTagText"><?= __('app.modal_ai_btn') ?></span>
                            </button>
                        <?php else: ?>
                            <div style="font-size:11px; color:var(--text-muted); background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); padding:6px 10px; border-radius:var(--radius-sm); display:flex; align-items:center; gap:4px;" title="<?= htmlspecialchars(__('app.modal_ai_admin_only_title')) ?>">
                                <span>🔒</span> <span><?= __('app.modal_ai_admin_only') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Hızlı Etiket Önerileri -->
                    <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap; align-items:center;">
                        <span style="font-size:10.5px; color:var(--text-muted);"><?= __('app.modal_quick_add_tags') ?></span>
                        <button type="button" onclick="appendTagToModal('frontend')" class="cu-tag-pill">#frontend</button>
                        <button type="button" onclick="appendTagToModal('backend')" class="cu-tag-pill">#backend</button>
                        <button type="button" onclick="appendTagToModal('api')" class="cu-tag-pill">#api</button>
                        <button type="button" onclick="appendTagToModal('bug')" class="cu-tag-pill">#bug</button>
                        <button type="button" onclick="appendTagToModal('ui')" class="cu-tag-pill">#ui</button>
                    </div>
                </div>

                <!-- TaskManager ÇOKLU ÇALIŞAN ATAMA (@ MENTION SEÇİCİ) -->
                <div class="cu-form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="margin-bottom:0;"><?= __('app.modal_assignees') ?></label>
                        <span style="font-size:11px; color:var(--text-muted);"><?= __('app.modal_assignees_hint') ?></span>
                    </div>

                    <!-- Yan yana dizilen seçili çalışanlar alanı -->
                    <div id="modalSelectedAssignees" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; min-height:40px; padding:6px 10px; background:var(--bg-input); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); margin-bottom:8px;">
                        <!-- JS ile dinamik chipler eklenecek -->
                    </div>

                    <!-- @ ile Arama Kutusu ve Popover Menü -->
                    <div style="position:relative;">
                        <div style="position:relative; display:flex; align-items:center;">
                            <span style="position:absolute; left:12px; color:var(--brand-primary); font-weight:700; font-size:14px; pointer-events:none;">@</span>
                            <input type="text" id="modalAssigneeInput" class="cu-form-control" style="padding-left:28px;" placeholder="<?= htmlspecialchars(__('app.modal_assignees_placeholder')) ?>" autocomplete="off" onclick="handleModalAssigneeSearch(this.value)" oninput="handleModalAssigneeSearch(this.value)" onfocus="handleModalAssigneeSearch(this.value)">
                        </div>

                        <!-- @ Açılır Menüsü (Dropdown) -->
                        <div id="modalAssigneeDropdown" style="display:none; position:absolute; left:0; right:0; top:calc(100% + 4px); background:var(--bg-card); border:1px solid var(--border-focus); border-radius:var(--radius-sm); box-shadow:0 12px 36px rgba(0,0,0,0.4); z-index:99999; max-height:200px; overflow-y:auto;">
                            <!-- Dinamik kullanıcı listesi -->
                        </div>
                    </div>

                    <!-- Form Gönderimi için Gizli Inputlar -->
                    <div id="modalAssigneeHiddenInputs"></div>
                </div>

                <!-- GÖREV RESİMLERİ & EKLERİ (TASK IMAGES & ATTACHMENTS) -->
                <div class="cu-form-group" style="margin-bottom:0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="margin-bottom:0; display:flex; align-items:center; gap:6px;">
                            <span>🖼️</span> <?= __('app.modal_attachments') ?>
                        </label>
                        <span style="font-size:11px; color:var(--text-muted);"><?= __('app.modal_attachments_hint') ?></span>
                    </div>

                    <!-- Gizli File Input -->
                    <input type="file" id="modalNewTaskFileInput" name="attachments[]" multiple accept="image/*,.pdf,.zip,.rar,.7z,.txt,.docx,.xlsx,.sql,.php,.js,.html,.css,.json" style="display:none;" onchange="handleModalNewTaskFiles(this.files)">

                    <!-- Dropzone Alanı -->
                    <div id="modalNewTaskDropzone"
                         ondragover="handleModalNewTaskDragOver(event)"
                         ondragleave="handleModalNewTaskDragLeave(event)"
                         ondrop="handleModalNewTaskDrop(event)"
                         onclick="document.getElementById('modalNewTaskFileInput').click()"
                         style="border: 2px dashed var(--border-subtle); border-radius: var(--radius-sm); padding: 12px; text-align: center; cursor: pointer; background: rgba(255,255,255,0.02); transition: var(--transition); margin-bottom: 8px;">
                        <div style="font-size: 16px; margin-bottom: 2px;">📸 📤</div>
                        <div style="font-size: 11.5px; font-weight: 600; color: var(--text-primary);"><?= __('app.modal_dropzone_title') ?></div>
                        <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;"><?= __('app.modal_dropzone_sub') ?></div>
                    </div>

                    <!-- Seçilen / Yapıştırılan Dosyaların Önizleme Listesi -->
                    <div id="modalNewTaskFilesPreview" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:6px;"></div>
                </div>
            </div>
            <div class="cu-modal-footer" style="padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalNewTask')"><?= __('app.modal_cancel') ?></button>
                <button type="submit" class="cu-btn-quick-task"><?= __('app.modal_create_task') ?></button>
            </div>
        </form>
    </div>
</div>
