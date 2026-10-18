<?php
// Modallar ve Toast Konteyneri
$layoutMgr = \Core\LayoutManager::getInstance();
$modalsData = $layoutMgr->getModalsData();
$allUsers = $modalsData['allUsers'];
$allProjects = $modalsData['allProjects'];
$sysSettings = $modalsData['sysSettings'];
$currentUser = $_SESSION['user'] ?? null;
$employeeCanAiTasks = (int)($sysSettings->employee_can_ai_tasks ?? 0);
$canUseAiTasks = ($currentUser && $currentUser->role === 'admin') || ($currentUser && $currentUser->role !== 'client' && $employeeCanAiTasks);
?>

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

<!-- 2. YENİ PROJE EKLEME MODALI -->
<div class="cu-modal-overlay" id="modalNewProject" onclick="if(event.target === this) closeModal('modalNewProject')">
    <div class="cu-modal" style="max-height:calc(100vh - 40px); height:auto; display:flex; flex-direction:column; overflow:hidden;">
        <div class="cu-modal-header" style="flex-shrink:0;">
            <h3 style="font-size:15px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px;">
                <span>📁</span> <?= __('app.modal_new_project_title') ?>
            </h3>
            <button onclick="closeModal('modalNewProject')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;">✕</button>
        </div>
        <form id="formNewProject" onsubmit="handleCreateProject(event)" style="display:flex; flex-direction:column; flex:1 1 auto; min-height:0; max-height:calc(100vh - 100px); overflow:hidden; margin:0;">
            <div class="cu-modal-body" style="overflow-y:auto; overflow-x:hidden; flex:1 1 auto; min-height:0; padding:20px; overscroll-behavior:contain;">
                <div class="cu-form-group">
                    <label><?= __('app.modal_proj_title') ?></label>
                    <input type="text" name="title" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_proj_name_ph')) ?>" required>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="cu-form-group">
                        <label><?= __('app.modal_proj_code') ?></label>
                        <input type="text" name="project_code" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_proj_code_ph')) ?>" required>
                    </div>
                    <div class="cu-form-group">
                        <label><?= __('app.modal_proj_color') ?></label>
                        <input type="color" name="color" class="cu-form-control" value="#7B68EE" style="height:40px; padding:2px;">
                    </div>
                </div>
                <div class="cu-form-group">
                    <label><?= __('app.modal_proj_hourly_rate', 'Saatlik Birim Ücret (₺/saat)') ?></label>
                    <input type="number" step="0.5" min="0" name="hourly_rate" class="cu-form-control" value="500.00" placeholder="500.00">
                    <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                        💡 <?= __('app.modal_proj_hourly_rate_hint', 'Hakediş ve efor raporlarında baz alınacak birim işçilik tutarı.') ?>
                    </small>
                </div>
                <div class="cu-form-group">
                    <label><?= __('app.modal_proj_desc') ?></label>
                    <textarea name="description" class="cu-form-control" rows="2" placeholder="<?= htmlspecialchars(__('app.modal_proj_desc_ph')) ?>"></textarea>
                </div>

                <div style="border-top:1px dashed var(--border-subtle); margin:14px 0 10px; padding-top:10px;">
                    <div style="font-size:11.5px; font-weight:700; color:var(--brand-primary); text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                        <span>🤖</span> <?= __('app.modal_ai_webhook_title') ?>
                    </div>
                    <div class="cu-form-group" style="margin-bottom:8px;">
                        <label style="font-size:11.5px; display:flex; justify-content:space-between;">
                            <span>📁 <?= __('app.modal_workspace_path') ?></span>
                            <span style="color:#00b894; font-weight:700;">⚡ <?= __('app.modal_auto_detect') ?></span>
                        </label>
                        <input type="text" name="workspace_path" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_workspace_path_ph')) ?>">
                        <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                            💡 <?= __('app.modal_workspace_path_hint') ?>
                        </small>
                    </div>
                    <div class="cu-form-group" style="margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <label style="font-size:11.5px; margin:0;">
                                <span>🤖 <?= __('app.modal_proj_agent_rules') ?></span>
                            </label>
                            <button type="button" id="btnRefineRulesNew" onclick="refineProjectRules(false)" class="cu-btn-sm" style="font-size:10.5px; padding:2px 8px; background:rgba(123,104,238,0.15); color:#7b68ee; border:1px solid rgba(123,104,238,0.3);" title="<?= htmlspecialchars(__('app.modal_refine_rules_hint')) ?>">
                                <?= __('app.modal_refine_rules_btn') ?>
                            </button>
                        </div>
                        <textarea name="agent_rules" id="newProjAgentRules" class="cu-form-control" rows="3" placeholder="<?= htmlspecialchars(__('app.modal_proj_agent_rules_ph')) ?>" style="font-family:monospace; font-size:11.5px;"></textarea>
                        <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                            💡 <?= __('app.modal_refine_rules_hint') ?>
                        </small>
                    </div>
                    <div class="cu-form-group" style="margin-bottom:8px;">
                        <label style="font-size:11.5px;"><?= __('app.modal_webhook_url') ?></label>
                        <input type="url" name="webhook_url" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_webhook_url_ph')) ?>">
                    </div>
                    <div class="cu-form-group">
                        <input type="hidden" name="agent_conversation_id" value="">
                        <div style="background:rgba(0,184,148,0.08); border:1px solid rgba(0,184,148,0.25); border-radius:6px; padding:10px 14px; display:flex; align-items:center; gap:10px; font-size:12px; color:var(--text-primary); margin-top:4px;">
                            <span style="font-size:18px; line-height:1;">⚡</span>
                            <div>
                                <b style="color:#00b894;"><?= __('app.modal_zero_config') ?>:</b> <?= __('app.modal_conv_id_hint') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cu-modal-footer" style="flex-shrink:0; position:sticky; bottom:0; z-index:10; padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalNewProject')"><?= __('app.modal_cancel') ?></button>
                <button type="submit" class="cu-btn-quick-task"><?= __('app.modal_create_project') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- 2.1 PROJE DÜZENLEME & AI WEBHOOK MODALI -->
<div class="cu-modal-overlay" id="modalEditProject" onclick="if(event.target === this) closeModal('modalEditProject')">
    <div class="cu-modal" style="max-height:calc(100vh - 40px); height:auto; display:flex; flex-direction:column; overflow:hidden;">
        <div class="cu-modal-header" style="flex-shrink:0;">
            <h3 style="font-size:15px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px;">
                <span>⚙️</span> <?= __('app.modal_edit_project_title') ?>
            </h3>
            <button onclick="closeModal('modalEditProject')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;">✕</button>
        </div>
        <form id="formEditProject" onsubmit="handleUpdateProject(event)" style="display:flex; flex-direction:column; flex:1 1 auto; min-height:0; max-height:calc(100vh - 100px); overflow:hidden; margin:0;">
            <input type="hidden" name="project_id" id="editProjId">
            <div class="cu-modal-body" style="overflow-y:auto; overflow-x:hidden; flex:1 1 auto; min-height:0; padding:20px; overscroll-behavior:contain;">
                <div class="cu-form-group">
                    <label><?= __('app.modal_proj_title') ?></label>
                    <input type="text" name="title" id="editProjTitle" class="cu-form-control" required>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="cu-form-group">
                        <label><?= __('app.modal_proj_code') ?></label>
                        <input type="text" id="editProjCode" class="cu-form-control" readonly style="opacity:0.6; cursor:not-allowed;">
                    </div>
                    <div class="cu-form-group">
                        <label><?= __('app.modal_proj_color') ?></label>
                        <input type="color" name="color" id="editProjColor" class="cu-form-control" style="height:40px; padding:2px;">
                    </div>
                </div>
                <div class="cu-form-group">
                    <label><?= __('app.modal_proj_hourly_rate', 'Saatlik Birim Ücret (₺/saat)') ?></label>
                    <input type="number" step="0.5" min="0" name="hourly_rate" id="editProjHourlyRate" class="cu-form-control" placeholder="500.00">
                    <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                        💡 <?= __('app.modal_proj_hourly_rate_hint', 'Hakediş ve efor raporlarında baz alınacak birim işçilik tutarı.') ?>
                    </small>
                </div>
                <div class="cu-form-group">
                    <label><?= __('app.modal_proj_desc') ?></label>
                    <textarea name="description" id="editProjDesc" class="cu-form-control" rows="2"></textarea>
                </div>

                <div style="border-top:1px dashed var(--border-subtle); margin:14px 0 10px; padding-top:12px;">
                    <div style="font-size:11.5px; font-weight:700; color:var(--brand-primary); text-transform:uppercase; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
                        <span style="display:flex; align-items:center; gap:6px;"><span>🤖</span> <?= __('app.modal_ai_webhook_title') ?></span>
                        <button type="button" onclick="testCurrentProjectWebhook()" class="cu-btn-sm" style="font-size:10.5px; padding:2px 8px; background:rgba(123,104,238,0.15); color:#7b68ee; border:1px solid rgba(123,104,238,0.3);">
                            🔔 <?= __('app.modal_test_webhook') ?>
                        </button>
                    </div>
                    <div class="cu-form-group" style="margin-bottom:8px;">
                        <label style="font-size:11.5px; display:flex; justify-content:space-between;">
                            <span>📁 <?= __('app.modal_workspace_path') ?></span>
                            <span style="color:#00b894; font-weight:700;">⚡ <?= __('app.modal_auto_detect') ?></span>
                        </label>
                        <input type="text" name="workspace_path" id="editProjWorkspacePath" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_workspace_path_ph')) ?>">
                        <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                            💡 <?= __('app.modal_workspace_path_hint') ?>
                        </small>
                    </div>
                    <div class="cu-form-group" style="margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <label style="font-size:11.5px; margin:0;">
                                <span>🤖 <?= __('app.modal_proj_agent_rules') ?></span>
                            </label>
                            <div style="display:flex; gap:6px;">
                                <button type="button" id="btnRefineRulesEdit" onclick="refineProjectRules(true)" class="cu-btn-sm" style="font-size:10.5px; padding:2px 8px; background:rgba(123,104,238,0.15); color:#7b68ee; border:1px solid rgba(123,104,238,0.3);" title="<?= htmlspecialchars(__('app.modal_refine_rules_hint')) ?>">
                                    <?= __('app.modal_refine_rules_btn') ?>
                                </button>
                                <button type="button" onclick="syncCurrentProjectRules()" class="cu-btn-sm" style="font-size:10.5px; padding:2px 8px; background:rgba(0,184,148,0.15); color:#00b894; border:1px solid rgba(0,184,148,0.3);" title="<?= htmlspecialchars(__('app.modal_sync_rules_btn')) ?>">
                                    📁 <?= __('app.modal_sync_rules_btn') ?>
                                </button>
                                <button type="button" onclick="openDeployWizard(document.getElementById('editProjId').value)" class="cu-btn-sm" style="font-size:10.5px; padding:2px 8px; background:linear-gradient(135deg, rgba(0,184,148,0.2), rgba(9,132,227,0.25)); color:#00cec9; border:1px solid rgba(0,184,148,0.5);" title="<?= htmlspecialchars(__('app.deploy_wizard_desc')) ?>">
                                    🚀 <?= __('app.deploy_btn_wizard') ?>
                                </button>
                                <button type="button" onclick="openProjectMcpModal(document.getElementById('editProjId').value)" class="cu-btn-sm" style="font-size:10.5px; padding:2px 8px; background:linear-gradient(135deg, rgba(230,126,34,0.15), rgba(243,156,18,0.25)); color:#f39c12; border:1px solid rgba(243,156,18,0.4);" title="<?= htmlspecialchars(__('app.mcp_marketplace_desc')) ?>">
                                    🧩 <?= __('app.mcp_btn_open') ?>
                                </button>
                            </div>
                        </div>
                        <textarea name="agent_rules" id="editProjAgentRules" class="cu-form-control" rows="4" placeholder="<?= htmlspecialchars(__('app.modal_proj_agent_rules_ph')) ?>" style="font-family:monospace; font-size:11.5px;"></textarea>
                        <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                            💡 <?= __('app.modal_refine_rules_hint') ?>
                        </small>
                    </div>
                    <div style="background:rgba(123,104,238,0.06); border:1px solid rgba(123,104,238,0.2); border-radius:6px; padding:8px 10px; margin-bottom:10px; font-size:11px; color:var(--text-muted); line-height:1.4;">
                        <b style="color:var(--text-white);"><?= __('app.modal_rules_architecture_title') ?></b><br>
                        1. <b><?= __('app.modal_rules_doctrine') ?></b><br>
                        2. <b><?= __('app.modal_rules_proj_rules') ?></b><br>
                        3. <b><?= __('app.modal_rules_task_reqs') ?></b>
                    </div>
                    <div class="cu-form-group" style="margin-bottom:8px;">
                        <label style="font-size:11.5px;"><?= __('app.modal_webhook_url') ?></label>
                        <input type="url" name="webhook_url" id="editProjWebhookUrl" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_webhook_url_ph')) ?>">
                    </div>
                    <div class="cu-form-group">
                        <input type="hidden" name="agent_conversation_id" id="editProjConvId" value="">
                        <div style="background:rgba(0,184,148,0.08); border:1px solid rgba(0,184,148,0.25); border-radius:6px; padding:10px 14px; display:flex; align-items:center; gap:10px; font-size:12px; color:var(--text-primary); margin-top:4px;">
                            <span style="font-size:18px; line-height:1;">⚡</span>
                            <div>
                                <b style="color:#00b894;"><?= __('app.modal_zero_config') ?>:</b> <?= __('app.modal_conv_id_hint') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cu-modal-footer" style="flex-shrink:0; position:sticky; bottom:0; z-index:10; padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalEditProject')"><?= __('app.modal_cancel') ?></button>
                <button type="submit" class="cu-btn-quick-task"><?= __('app.modal_update_project') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- 3. REVİZYON / AÇIKLAMA İSTEME MODALI (KULLANICI) -->
<div class="cu-modal-overlay" id="modalRequestMod">
    <div class="cu-modal">
        <div class="cu-modal-header">
            <h3 style="font-size:15px; font-weight:700; color:#e67e22; display:flex; align-items:center; gap:8px;">
                <span>📝</span> <?= __('app.modal_request_mod_title') ?>
            </h3>
            <button onclick="closeModal('modalRequestMod')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;">✕</button>
        </div>
        <form id="formRequestMod" onsubmit="handleRequestMod(event)">
            <input type="hidden" name="task_id" id="modTaskId">
            <div class="cu-modal-body">
                <p style="font-size:12.5px; color:var(--text-secondary); margin-bottom:14px;">
                    <?= __('app.modal_request_mod_desc') ?>
                </p>
                <div class="cu-form-group">
                    <label><?= __('app.modal_request_mod_reason') ?></label>
                    <textarea name="reason" class="cu-form-control" rows="4" placeholder="<?= htmlspecialchars(__('app.modal_request_mod_reason_ph')) ?>" required></textarea>
                </div>
            </div>
            <div class="cu-modal-footer" style="padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalRequestMod')"><?= __('app.modal_cancel') ?></button>
                <button type="submit" class="cu-btn-modify"><?= __('app.modal_request_mod_submit') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- 4. GÖREVİ TESLİM ETME MODALI (KULLANICI) -->
<div class="cu-modal-overlay" id="modalSubmitTask">
    <div class="cu-modal">
        <div class="cu-modal-header">
            <h3 style="font-size:15px; font-weight:700; color:#9b5de5; display:flex; align-items:center; gap:8px;">
                <span>🚀</span> <?= __('app.modal_submit_task_title') ?>
            </h3>
            <button onclick="closeModal('modalSubmitTask')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;">✕</button>
        </div>
        <form id="formSubmitTask" onsubmit="handleSubmitTask(event)">
            <input type="hidden" name="task_id" id="submitTaskId">
            <div class="cu-modal-body">
                <p style="font-size:12.5px; color:var(--text-secondary); margin-bottom:14px;">
                    <?= __('app.modal_submit_task_desc') ?>
                </p>
                <div class="cu-form-group">
                    <label><?= __('app.modal_submit_task_notes') ?></label>
                    <textarea name="notes" class="cu-form-control" rows="3" placeholder="<?= htmlspecialchars(__('app.modal_submit_task_notes_ph')) ?>" required></textarea>
                </div>
                <div class="cu-form-group">
                    <label style="display:flex; justify-content:space-between; align-items:center;">
                        <span>📎 <?= __('app.modal_submit_task_attach') ?></span>
                        <span style="font-size:11px; color:var(--text-muted);"><?= __('app.modal_submit_task_attach_hint') ?></span>
                    </label>

                    <!-- Drag & Drop & Paste Alanı -->
                    <div id="submitTaskDropzone" 
                         ondragover="handleSubmitDropzoneOver(event)" 
                         ondragleave="handleSubmitDropzoneLeave(event)" 
                         ondrop="handleSubmitDropzoneDrop(event)"
                         onclick="document.getElementById('submitTaskFileInput').click()"
                         style="border:2px dashed var(--border-subtle); border-radius:var(--radius-md); padding:16px; text-align:center; cursor:pointer; background:rgba(255,255,255,0.02); transition:all 0.2s;">
                        <input type="file" id="submitTaskFileInput" name="attachment" onchange="handleSubmitFileSelect(this)" style="display:none;" accept="image/*,.pdf,.zip,.rar,.7z,.txt,.doc,.docx,.xls,.xlsx">
                        <div id="submitTaskUploadPrompt">
                            <span style="font-size:24px; display:block; margin-bottom:4px;">☁️</span>
                            <span style="font-size:12.5px; font-weight:600; color:var(--text-white);"><?= __('app.modal_submit_task_drop') ?> <span style="color:var(--brand-primary); text-decoration:underline;"><?= __('app.modal_submit_task_drop_select') ?></span></span>
                            <span style="font-size:11px; color:var(--text-muted); display:block; margin-top:3px;"><?= __('app.modal_submit_task_drop_sub') ?></span>
                        </div>
                        <div id="submitTaskFilePreview" style="display:none; align-items:center; justify-content:center; gap:10px;">
                            <div id="submitTaskPreviewThumb"></div>
                            <div style="text-align:left;">
                                <div id="submitTaskPreviewName" style="font-size:12.5px; font-weight:600; color:var(--text-white);"></div>
                                <div id="submitTaskPreviewSize" style="font-size:11px; color:var(--text-muted);"></div>
                            </div>
                            <button type="button" onclick="clearSubmitTaskFile(event)" style="background:rgba(231,76,60,0.2); border:none; color:#e74c3c; width:24px; height:24px; border-radius:50%; cursor:pointer; font-size:12px; margin-left:10px;">✕</button>
                        </div>
                    </div>
                </div>

                <div class="cu-form-group">
                    <label><?= __('app.modal_submit_task_link') ?></label>
                    <input type="text" name="attachment_url" id="submitTaskAttachmentUrl" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_submit_task_link_ph')) ?>">
                </div>
            </div>
            <div class="cu-modal-footer" style="padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalSubmitTask')"><?= __('app.modal_cancel') ?></button>
                <button type="submit" class="cu-btn-submit"><?= __('app.modal_submit_task_submit') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- 4.1 KULLANICI PROFİL AYARLARI MODALI -->
<?php if ($currentUser): ?>
<div class="cu-modal-overlay" id="modalProfileSettings" onclick="if(event.target === this) closeProfileModal()">
    <div class="cu-modal" style="max-width:540px; max-height:90vh; display:flex; flex-direction:column; overflow:hidden;">
        <div class="cu-modal-header" style="flex-shrink:0;">
            <h3 style="font-size:15px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px; margin:0;">
                <span>⚙️</span> <?= __('app.modal_profile_title') ?>
            </h3>
            <button type="button" onclick="closeProfileModal()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:18px; line-height:1;">&times;</button>
        </div>

        <form id="formProfileSettings" onsubmit="handleUpdateProfile(event)" style="display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden; margin:0;">
            <div class="cu-modal-body" style="padding:18px 20px; overflow-y:auto; flex:1;">
                
                <!-- Canlı Profil Önizleme Kartı -->
                <div style="display:flex; align-items:center; gap:14px; padding:14px; background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); border-radius:var(--radius-md); margin-bottom:16px;">
                    <div id="profileAvatarPreview" class="cu-avatar" style="width:50px; height:50px; font-size:20px; font-weight:700; background:<?= htmlspecialchars($currentUser->avatar_color ?? '#7B68EE') ?>; box-shadow:0 4px 12px rgba(0,0,0,0.3); flex-shrink:0;">
                        <?= mb_substr($currentUser->name ?? 'U', 0, 1) ?>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div id="profileNamePreview" style="font-size:14px; font-weight:700; color:var(--text-white); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($currentUser->name ?? '') ?></div>
                        <div style="font-size:11.5px; color:var(--text-muted); margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($currentUser->email ?? '') ?></div>
                        <div style="margin-top:5px; display:flex; gap:6px; align-items:center;">
                            <span style="font-size:10px; font-weight:700; text-transform:uppercase; padding:2px 8px; border-radius:4px; <?= ($currentUser->role ?? '') === 'admin' ? 'background:rgba(123,104,238,0.2); color:#7B68EE;' : 'background:rgba(255,255,255,0.06); color:var(--text-secondary);' ?>">
                                <?= ($currentUser->role ?? '') === 'admin' ? __('app.layout_role_admin') : __('app.layout_role_member') ?>
                            </span>
                            <span id="profileTitlePreview" style="font-size:11px; color:var(--text-secondary);"><?= htmlspecialchars($currentUser->title ?? __('app.layout_role_member')) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Ad Soyad & Pozisyon -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.modal_profile_name') ?></label>
                        <input type="text" id="profileInputName" name="name" class="cu-form-control" value="<?= htmlspecialchars($currentUser->name ?? '') ?>" required oninput="updateProfileAvatarPreview()" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.modal_profile_title_role') ?></label>
                        <input type="text" id="profileInputTitle" name="title" class="cu-form-control" value="<?= htmlspecialchars($currentUser->title ?? '') ?>" placeholder="<?= htmlspecialchars(__('app.modal_profile_title_role_ph')) ?>" oninput="document.getElementById('profileTitlePreview').innerText = this.value || '<?= htmlspecialchars(__('app.layout_role_member')) ?>'" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                    </div>
                </div>

                <!-- E-Posta -->
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.modal_profile_email') ?></label>
                    <input type="email" name="email" class="cu-form-control" value="<?= htmlspecialchars($currentUser->email ?? '') ?>" required style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                </div>

                <!-- Kullanım Dili (Interface Language) -->
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;">🌐 <?= __('app.modal_profile_language') ?></label>
                    <select name="language" id="profileInputLanguage" class="cu-form-control" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                        <?php 
                        $userLang = $currentUser->language ?? ($_SESSION['lang'] ?? ($_SESSION['admin_lang'] ?? 'tr')); 
                        ?>
                        <option value="tr" <?= $userLang === 'tr' ? 'selected' : '' ?>>🇹🇷 Türkçe (Turkish)</option>
                        <option value="de" <?= $userLang === 'de' ? 'selected' : '' ?>>🇩🇪 Deutsch (German)</option>
                        <option value="en" <?= $userLang === 'en' ? 'selected' : '' ?>>🇬🇧 English (American / Global)</option>
                    </select>
                    <small style="font-size:11px; color:var(--text-muted); margin-top:4px; display:block;"><?= __('app.modal_profile_lang_hint') ?></small>
                </div>

                <!-- Avatar Rengi Seçimi -->
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:8px;"><?= __('app.modal_profile_avatar_color') ?></label>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <?php 
                        $colors = ['#7B68EE', '#06D6A0', '#f59e0b', '#ef4444', '#3b82f6', '#ec4899', '#14b8a6', '#8b5cf6'];
                        $currColor = $currentUser->avatar_color ?? '#7B68EE';
                        foreach ($colors as $c): 
                        ?>
                            <label style="cursor:pointer; margin:0;">
                                <input type="radio" name="avatar_color" value="<?= $c ?>" <?= ($currColor === $c) ? 'checked' : '' ?> style="display:none;" onchange="selectProfileColor('<?= $c ?>')">
                                <span class="profile-color-dot" data-color="<?= $c ?>" style="display:inline-block; width:28px; height:28px; border-radius:50%; background:<?= $c ?>; border:2px solid <?= ($currColor === $c) ? '#fff' : 'transparent' ?>; box-shadow:0 2px 5px rgba(0,0,0,0.2); transition:var(--transition); vertical-align:middle;"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- API Anahtarı (Personal Access Token) -->
                <div style="background:rgba(123,104,238,0.06); border:1px solid rgba(123,104,238,0.25); border-radius:var(--radius-md); padding:12px 14px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-size:12px; font-weight:700; color:#7B68EE; display:flex; align-items:center; gap:6px;">
                            <span>⚡</span> <?= __('app.modal_profile_api_token') ?>
                        </span>
                        <a href="/taskmanager/api-docs" target="_blank" style="font-size:11px; color:var(--brand-primary); text-decoration:none; font-weight:600;"><?= __('app.modal_profile_api_doc') ?></a>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="text" id="profileApiTokenInput" readonly value="<?= htmlspecialchars($currentUser->api_token ?? 'Token oluşturulmadı') ?>" style="flex:1; height:34px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 10px; color:var(--text-white); font-family:monospace; font-size:11.5px; outline:none;">
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('profileApiTokenInput').value); if(typeof showToast==='function') showToast('API Token kopyalandı!','success'); else alert('Token kopyalandı!');" class="cu-btn-sm" style="background:rgba(255,255,255,0.08); border-color:var(--border-subtle); color:var(--text-white); padding:6px 12px; cursor:pointer;" title="<?= htmlspecialchars(__('app.modal_profile_copy')) ?>">
                            📋 <?= __('app.modal_profile_copy') ?>
                        </button>
                    </div>
                </div>

                <!-- Şifre Değiştirme Bölümü -->
                <div style="background:rgba(0,0,0,0.22); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:14px; margin-bottom:6px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                        <span style="font-size:12.5px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:6px;">
                            <span>🔒</span> <?= __('app.modal_profile_pwd_update') ?>
                        </span>
                        <span style="font-size:11px; color:var(--text-muted);"><?= __('app.modal_profile_optional') ?></span>
                    </div>
                    <p style="font-size:11.5px; color:var(--text-muted); margin:0 0 12px 0;"><?= __('app.modal_profile_pwd_hint') ?></p>

                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:11.5px; font-weight:600; color:var(--text-secondary); margin-bottom:5px;"><?= __('app.modal_profile_pwd_current') ?></label>
                        <div style="position:relative;">
                            <input type="password" id="inputCurrentPassword" name="current_password" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_profile_pwd_current_ph')) ?>" style="width:100%; height:36px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 36px 0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                            <button type="button" onclick="togglePassVisibility('inputCurrentPassword', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:4px;">👁️</button>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label style="display:block; font-size:11.5px; font-weight:600; color:var(--text-secondary); margin-bottom:5px;"><?= __('app.modal_profile_pwd_new') ?></label>
                            <div style="position:relative;">
                                <input type="password" id="inputNewPassword" name="new_password" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_profile_pwd_new_ph')) ?>" style="width:100%; height:36px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 36px 0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                                <button type="button" onclick="togglePassVisibility('inputNewPassword', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:4px;">👁️</button>
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-size:11.5px; font-weight:600; color:var(--text-secondary); margin-bottom:5px;"><?= __('app.modal_profile_pwd_confirm') ?></label>
                            <div style="position:relative;">
                                <input type="password" id="inputNewPasswordConfirm" name="new_password_confirm" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_profile_pwd_confirm_ph')) ?>" style="width:100%; height:36px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 36px 0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                                <button type="button" onclick="togglePassVisibility('inputNewPasswordConfirm', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:4px;">👁️</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="cu-modal-footer" style="padding:12px 20px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border-subtle); background:rgba(0,0,0,0.15); flex-shrink:0;">
                <button type="button" class="cu-btn cu-btn-secondary" onclick="closeProfileModal()" style="padding:8px 16px; font-size:13px;"><?= __('app.modal_cancel') ?></button>
                <button type="submit" id="btnSubmitProfile" class="cu-btn cu-btn-primary" style="padding:8px 20px; font-size:13px; font-weight:600;">
                    <span>💾</span>
                    <span><?= __('app.modal_profile_save') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- 5. TaskManager UNIVERSAL COMMAND PALETTE (CTRL+K) -->
<div class="cu-modal-overlay" id="modalCommandPalette" onclick="if(event.target === this) closeCommandPalette()">
    <div class="cu-modal" style="max-width:580px; background:var(--bg-sidebar); border-color:var(--brand-glow); box-shadow:0 25px 60px rgba(0,0,0,0.6);">
        <div style="padding:14px 18px; border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; gap:12px;">
            <span style="font-size:18px;">🔍</span>
            <input type="text" id="inputCommandPalette" placeholder="<?= htmlspecialchars(__('app.palette_search_ph')) ?>" style="width:100%; background:transparent; border:none; color:var(--text-white); font-size:15px; outline:none;" autocomplete="off" oninput="filterCommandPalette(this.value)">
            <span style="font-size:11px; color:var(--text-muted); background:rgba(255,255,255,0.06); padding:2px 6px; border-radius:4px;">ESC</span>
        </div>
        <div id="commandPaletteList" style="max-height:340px; overflow-y:auto; padding:8px;">
            <div style="font-size:10.5px; font-weight:700; color:var(--text-muted); text-transform:uppercase; padding:6px 10px;"><?= __('app.palette_quick_actions') ?></div>
            <div class="cu-command-item" onclick="closeCommandPalette(); openNewTaskModal();" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>➕</span> <span><?= __('app.modal_new_task_title') ?></span>
            </div>
            <div class="cu-command-item" onclick="closeCommandPalette(); openNewProjectModal();" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📁</span> <span><?= __('app.sidebar_add_project') ?></span>
            </div>
            <div class="cu-command-item" onclick="closeCommandPalette(); openProfileModal();" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>⚙️</span> <span><?= __('app.palette_edit_profile') ?></span>
            </div>
            <div class="cu-command-item" onclick="window.location.href='/taskmanager/api-docs';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:#7B68EE; transition:var(--transition);">
                <span>⚡</span> <span><?= __('app.layout_api_guide') ?></span>
            </div>
            
            <div style="font-size:10.5px; font-weight:700; color:var(--text-muted); text-transform:uppercase; padding:10px 10px 6px;"><?= __('app.palette_views_heading') ?></div>
            <div class="cu-command-item" onclick="window.location.href='/taskmanager/tasks';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📋</span> <span><?= __('app.sidebar_list') ?></span>
            </div>
            <div class="cu-command-item" onclick="window.location.href='/taskmanager/board';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📌</span> <span><?= __('app.sidebar_board') ?></span>
            </div>
            <div class="cu-command-item" onclick="window.location.href='/taskmanager/calendar';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📅</span> <span><?= __('app.sidebar_calendar') ?></span>
            </div>
            <div class="cu-command-item" onclick="window.location.href='/taskmanager/docs';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📄</span> <span><?= __('app.sidebar_docs') ?></span>
            </div>
            <div class="cu-command-item" onclick="window.location.href='/taskmanager/reports';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📊</span> <span><?= __('app.sidebar_reports') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- 🪄 AI PROJE MİMARI & SÜRÜKLE-BIRAK GÖREV AYRIŞTIRMA MODALI -->
<?php
$aiArchProjects = $projects ?? [];
if (empty($aiArchProjects)) {
    $pm = new \Modules\Projects\Models\ProjectModel();
    $aiArchProjects = $pm->getProjects();
}
?>
<div class="cu-modal-overlay" id="modalAiArchitect" onclick="if(event.target === this) closeModal('modalAiArchitect')">
    <div class="cu-modal cu-ai-arch-modal">
        <div class="cu-modal-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-subtle); padding:16px 22px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:24px;">🪄</span>
                <div>
                    <h3 style="font-size:16px; font-weight:800; color:var(--text-white); margin:0;"><?= __('app.ai_arch_title') ?></h3>
                    <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px;"><?= __('app.ai_arch_subtitle') ?></div>
                </div>
            </div>
            <button type="button" onclick="closeModal('modalAiArchitect')" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">&times;</button>
        </div>

        <div class="cu-modal-body" style="flex:1; overflow-y:auto; padding:20px;">
            <!-- ADIM 1: BRİF GİRİŞ EKRANI -->
            <div id="aiArchStep1">
                <div class="cu-ai-arch-step-grid">
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:var(--text-secondary); display:block; margin-bottom:6px;"><?= __('app.ai_arch_brief_label') ?></label>
                            <textarea id="aiArchBriefInput" rows="6" class="cu-ai-arch-brief-textarea" placeholder="<?= htmlspecialchars(__('app.ai_arch_brief_ph')) ?>"></textarea>
                        </div>

                        <!-- SÜRÜKLE BIRAK WORD / DÖKÜMAN YÜKLEME ALANI -->
                        <div id="aiArchDropzone" class="cu-ai-arch-dropzone"
                             onclick="document.getElementById('aiArchFileInput').click()"
                             ondragover="handleAiArchDragOver(event)"
                             ondragleave="handleAiArchDragLeave(event)"
                             ondrop="handleAiArchFileDrop(event)">
                            <input type="file" id="aiArchFileInput" accept=".docx,.doc,.pdf,.txt,.md,.png,.jpg,.jpeg,.webp" style="display:none;" onchange="handleAiArchFileSelect(this)">
                            <div id="aiArchFilePrompt">
                                <span style="font-size:22px;">📄 🖼️</span>
                                <div style="font-size:12.5px; font-weight:700; color:var(--text-white); margin-top:4px;"><?= __('app.ai_arch_dropzone_title') ?></div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;"><?= __('app.ai_arch_dropzone_desc') ?></div>
                            </div>
                            <div id="aiArchFileSelected" class="cu-ai-arch-file-selected" onclick="event.stopPropagation()">
                                <div style="display:flex; align-items:center; gap:8px; text-align:left;">
                                    <span style="font-size:18px;">📎</span>
                                    <div>
                                        <div id="aiArchFileName" style="font-size:12px; font-weight:700; color:var(--text-white);"></div>
                                        <div id="aiArchFileSize" style="font-size:10.5px; color:#00b894;"></div>
                                    </div>
                                </div>
                                <button type="button" onclick="clearAiArchFile(event)" style="background:rgba(255,71,87,0.2); border:none; color:#ff4757; border-radius:4px; padding:4px 8px; font-size:11px; font-weight:700; cursor:pointer;">✕ <?= __('app.ai_arch_remove_file') ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="cu-ai-arch-side-box">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:var(--text-white); display:block; margin-bottom:6px;"><?= __('app.ai_arch_target_project') ?></label>
                            <select id="aiArchProjectSelect" style="width:100%; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:6px; color:var(--text-white); padding:8px; font-size:12.5px; margin-bottom:12px;">
                                <?php foreach ($aiArchProjects as $p): ?>
                                    <option value="<?= $p->id ?>" <?= (isset($activeProjectId) && $activeProjectId == $p->id) ? 'selected' : '' ?>>
                                        [<?= htmlspecialchars($p->project_code) ?>] <?= htmlspecialchars($p->title) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div style="font-size:11.5px; color:var(--text-secondary); line-height:1.5;">
                                <b><?= __('app.ai_arch_guarantee_title') ?></b><br>
                                <?= __('app.ai_arch_guarantee_1') ?><br>
                                <?= __('app.ai_arch_guarantee_2') ?><br>
                                <?= __('app.ai_arch_guarantee_3') ?>
                            </div>
                        </div>
                        <button type="button" id="btnRunAiDecompose" onclick="runAiBriefDecomposition()" class="cu-ai-arch-btn-run">
                            <?= __('app.ai_arch_btn_decompose') ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ADIM 2: SÜRÜKLE-BIRAK İNTERAKTİF AĞAÇ ÖNİZLEME EKRANI -->
            <div id="aiArchStep2" style="display:none;">
                <div class="cu-ai-arch-tip-banner">
                    <div style="font-size:12.5px; color:var(--text-white); line-height:1.4;">
                        <?= __('app.ai_arch_drag_tip') ?>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" onclick="toggleAllSubtaskDetails()" class="cu-btn-sm" style="background:rgba(255,255,255,0.08); border:1px solid var(--border-subtle); color:var(--text-white); font-size:11.5px; padding:6px 12px; border-radius:6px; cursor:pointer;">
                            <?= __('app.ai_arch_toggle_all_details') ?>
                        </button>
                        <button type="button" onclick="addNewBlankEpic()" class="cu-btn-sm" style="background:rgba(123,104,238,0.2); border:1px solid #7B68EE; color:#fff; font-size:11.5px; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:600;">
                            <?= __('app.ai_arch_add_epic') ?>
                        </button>
                        <button type="button" onclick="document.getElementById('aiArchStep2').style.display='none'; document.getElementById('aiArchStep1').style.display='block';" class="cu-btn-sm" style="background:rgba(255,255,255,0.08); border:1px solid var(--border-subtle); color:var(--text-secondary); font-size:11.5px; padding:6px 12px; border-radius:6px; cursor:pointer;">
                            <?= __('app.ai_arch_back_to_brief') ?>
                        </button>
                    </div>
                </div>

                <!-- DÖKÜMANDAN ÇIKARILAN GÖRSELLER ÖNİZLEME BARI -->
                <div id="aiArchExtractedImagesBar" class="cu-ai-arch-extracted-bar" style="display:none;">
                    <div style="font-size:11.5px; font-weight:700; color:#d8b4fe; margin-bottom:8px;"><?= __('app.ai_arch_extracted_images') ?></div>
                    <div id="aiArchExtractedImagesList" style="display:flex; gap:10px; overflow-x:auto; padding-bottom:4px;"></div>
                </div>

                <div id="aiArchEpicsContainer" style="display:flex; flex-direction:column; gap:16px;"></div>
            </div>
        </div>

        <div class="cu-modal-footer" style="display:flex; justify-content:space-between; align-items:center; padding:14px 22px; border-top:1px solid var(--border-subtle);">
            <span id="aiArchSummaryBadge" style="font-size:12px; color:var(--text-muted); font-weight:600;"></span>
            <div style="display:flex; gap:10px;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalAiArchitect')" style="background:rgba(255,255,255,0.06); color:var(--text-secondary);"><?= __('app.btn_cancel') ?></button>
                <button type="button" id="btnApproveAiArch" onclick="submitAiArchitectTree()" style="display:none; background:#00b894; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer;">
                    <?= __('app.ai_arch_btn_approve') ?>
                </button>
            </div>
        </div>
    </div>
</div>

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

<style>
.mcp-cat-pill {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--text-secondary);
    padding: 5px 12px;
    border-radius: 16px;
    font-size: 11.5px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
}
.mcp-cat-pill:hover {
    background: rgba(123, 104, 238, 0.15);
    border-color: rgba(123, 104, 238, 0.4);
    color: #fff;
}
.mcp-cat-pill.active {
    background: linear-gradient(135deg, #7B68EE, #9b5de5);
    border-color: #7B68EE;
    color: #ffffff;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(123, 104, 238, 0.4);
}
.mcp-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 12px;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}
.mcp-card:hover {
    transform: translateY(-2px);
    border-color: rgba(123, 104, 238, 0.35);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    background: rgba(255, 255, 255, 0.045);
}
.mcp-card.enabled {
    border-color: rgba(46, 160, 67, 0.4);
    background: linear-gradient(180deg, rgba(46, 160, 67, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
}
.mcp-toggle-switch {
    position: relative;
    display: inline-block;
    width: 38px;
    height: 20px;
    margin: 0;
}
.mcp-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.mcp-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: rgba(255, 255, 255, 0.15);
    transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.mcp-slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 50%;
}
.mcp-toggle-switch input:checked + .mcp-slider {
    background-color: #2ea043;
    border-color: #3fb950;
    box-shadow: 0 0 10px rgba(46, 160, 67, 0.4);
}
.mcp-toggle-switch input:checked + .mcp-slider:before {
    transform: translateX(18px);
}
</style>

<!-- 🚀 CANLI DAĞITIM & HOSTİNG SİHİRBAZI MODALI (TSK-1085) -->
<div class="cu-modal-overlay" id="modalDeployWizard" onclick="if(event.target === this) closeModal('modalDeployWizard')">
    <div class="cu-modal" style="max-width:760px; max-height:calc(100vh - 40px); height:auto; display:flex; flex-direction:column; overflow:hidden;">
        <div class="cu-modal-header" style="flex-shrink:0; background:linear-gradient(135deg, rgba(0,184,148,0.1), rgba(9,132,227,0.15));">
            <div>
                <h3 style="font-size:16px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px; margin:0;">
                    <span>🚀</span> <?= __('app.deploy_wizard_title') ?>
                </h3>
                <p style="font-size:11.5px; color:var(--text-muted); margin:4px 0 0 0;">
                    <?= __('app.deploy_wizard_desc') ?>
                </p>
            </div>
            <button onclick="closeModal('modalDeployWizard')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:18px;">✕</button>
        </div>

        <!-- SİHİRBAZ ADIM GÖSTERGESİ (STEPPER) -->
        <div style="display:flex; border-bottom:1px solid var(--border-subtle); background:var(--bg-card); padding:10px 20px; gap:8px;">
            <div id="deployStepTab1" class="deploy-step-tab active" style="flex:1; display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; background:rgba(0,184,148,0.15); border:1px solid rgba(0,184,148,0.4); color:#00cec9; font-size:12px; font-weight:700;">
                <span style="display:inline-flex; width:20px; height:20px; border-radius:50%; background:#00cec9; color:#000; align-items:center; justify-content:center; font-size:11px;">1</span>
                <span><?= __('app.deploy_step1_title') ?></span>
            </div>
            <div id="deployStepTab2" class="deploy-step-tab" style="flex:1; display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); color:var(--text-muted); font-size:12px; font-weight:600;">
                <span style="display:inline-flex; width:20px; height:20px; border-radius:50%; background:var(--border-subtle); color:var(--text-white); align-items:center; justify-content:center; font-size:11px;">2</span>
                <span><?= __('app.deploy_step2_title') ?></span>
            </div>
            <div id="deployStepTab3" class="deploy-step-tab" style="flex:1; display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); color:var(--text-muted); font-size:12px; font-weight:600;">
                <span style="display:inline-flex; width:20px; height:20px; border-radius:50%; background:var(--border-subtle); color:var(--text-white); align-items:center; justify-content:center; font-size:11px;">3</span>
                <span><?= __('app.deploy_step3_title') ?></span>
            </div>
        </div>

        <div class="cu-modal-body" style="overflow-y:auto; flex:1 1 auto; padding:20px;">
            <input type="hidden" id="deployWizardProjId" value="">

            <!-- ADIM 1: HOSTİNG & PANEL/FTP GİRİŞİ -->
            <div id="deployStepContent1" class="deploy-step-content">
                <div class="cu-form-group" style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:700; color:var(--text-white); margin-bottom:6px;"><?= __('app.hosting_adapter_title') ?></label>
                    <select id="deployPanelType" class="cu-form-control" onchange="toggleDeployPanelFields()" style="background:var(--bg-main); color:var(--text-white);">
                        <option value="standard"><?= __('app.hosting_panel_standard') ?></option>
                        <option value="cpanel"><?= __('app.hosting_panel_cpanel') ?></option>
                        <option value="plesk"><?= __('app.hosting_panel_plesk') ?></option>
                        <option value="cyberpanel"><?= __('app.hosting_panel_cyberpanel') ?></option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;"><?= __('app.deploy_target_domain') ?></label>
                        <input type="url" id="deployLiveDomain" class="cu-form-control" placeholder="https://ornekproje.com" value="">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">FTP / SSH Host</label>
                        <input type="text" id="deployFtpHost" class="cu-form-control" placeholder="ftp.ornekproje.com" value="">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 100px; gap:12px; margin-bottom:12px;">
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">FTP / Panel Kullanıcı</label>
                        <input type="text" id="deployFtpUser" class="cu-form-control" placeholder="cpanel_user">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">FTP / Panel Şifre</label>
                        <input type="password" id="deployFtpPass" class="cu-form-control" placeholder="••••••••">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">Port</label>
                        <input type="number" id="deployFtpPort" class="cu-form-control" value="21">
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; padding:10px 14px; background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); border-radius:6px;">
                    <span id="deployPanelTestStatus" style="font-size:12px; color:var(--text-muted);">
                        ⚡ Giriş bilgilerinizi doğrulayın.
                    </span>
                    <button type="button" class="cu-btn-sm" onclick="testDeployPanelConnection()" style="background:rgba(123,104,238,0.2); color:#a29bfe; border:1px solid rgba(123,104,238,0.4);">
                        <?= __('app.deploy_btn_test_panel') ?>
                    </button>
                </div>
            </div>

            <!-- ADIM 2: CANLI DOMAİN VE VERİTABANI TEYİDİ -->
            <div id="deployStepContent2" class="deploy-step-content" style="display:none;">
                <div style="background:rgba(9,132,227,0.08); border:1px solid rgba(9,132,227,0.3); border-radius:6px; padding:12px 14px; margin-bottom:14px; font-size:12px; color:var(--text-white);">
                    <b>ℹ️ Canlı İzolasyon Koruması:</b> Yerel kodlarınız ve yerel veri tabanınız kesinlikle bozulmaz. Canlıya aktarılırken URL ve DB ayarları canlı sunucuya özel dönüştürülür.
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">Canlı DB Host</label>
                        <input type="text" id="deployDbHost" class="cu-form-control" value="localhost">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">Canlı Veritabanı Adı</label>
                        <input type="text" id="deployDbName" class="cu-form-control" placeholder="ornek_db">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">Canlı DB Kullanıcı Adı</label>
                        <input type="text" id="deployDbUser" class="cu-form-control" placeholder="ornek_user">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">Canlı DB Şifre</label>
                        <input type="password" id="deployDbPass" class="cu-form-control" placeholder="••••••••">
                    </div>
                </div>

                <!-- Git Diff Özeti -->
                <div id="deployDiffSummaryBox" style="background:rgba(0,0,0,0.25); border:1px solid var(--border-subtle); border-radius:6px; padding:12px; font-size:12px;">
                    <div style="font-weight:700; color:var(--text-white); margin-bottom:6px; display:flex; justify-content:space-between;">
                        <span>📦 Senkronizasyon Kapsamı (Git Diff)</span>
                        <span id="deployDiffBadge" style="color:#00cec9;">Hesaplanıyor...</span>
                    </div>
                    <div id="deployDiffDetails" style="font-size:11.5px; color:var(--text-secondary); line-height:1.5;">
                        Değişiklikler yükleniyor...
                    </div>
                </div>
            </div>

            <!-- ADIM 3: TEK TIKLA DAĞITIM, SAĞLIK KONTROLÜ VE ROLLBACK -->
            <div id="deployStepContent3" class="deploy-step-content" style="display:none;">
                <div style="text-align:center; padding:16px 0;">
                    <div id="deployHealthBadge" style="display:inline-flex; align-items:center; gap:8px; padding:6px 16px; border-radius:20px; font-size:13px; font-weight:700; background:rgba(255,255,255,0.06); border:1px solid var(--border-subtle); color:var(--text-muted); margin-bottom:14px;">
                        <span>🩺</span> <span>Sağlık durumu bekleniyor</span>
                    </div>
                    <h4 id="deployStatusTitle" style="font-size:15px; font-weight:700; color:var(--text-white); margin:0 0 6px 0;">Dağıtıma Hazır</h4>
                    <p id="deployStatusDesc" style="font-size:12px; color:var(--text-muted); margin:0 auto; max-width:500px;">
                        Tek tıkla dosyalarınızı yükleyin, yeni şema farklarını canlı veritabanında çalıştırın.
                    </p>
                </div>

                <div id="deployLiveLogBox" style="background:#0d1117; border:1px solid #30363d; border-radius:6px; padding:12px; font-family:monospace; font-size:11px; color:#58a6ff; max-height:150px; overflow-y:auto; margin-bottom:14px; display:none;">
                </div>

                <!-- Cerrahi Rollback Kutusu -->
                <div id="deployRollbackBox" style="background:rgba(231,76,60,0.08); border:1px solid rgba(231,76,60,0.3); border-radius:6px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-size:12px; font-weight:700; color:#e74c3c;">⏪ Cerrahi Geri Alma (Rollback)</div>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Terslik durumunda sunucuyu ve DB'yi önceki stabil commit'e döndürün.</div>
                    </div>
                    <button type="button" class="cu-btn-sm" onclick="executeDeployRollback()" style="background:#e74c3c; color:#fff; font-weight:700; border:none; padding:6px 14px; border-radius:6px; cursor:pointer;">
                        <?= __('app.deploy_btn_rollback') ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="cu-modal-footer" style="flex-shrink:0; padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:space-between; align-items:center;">
            <button type="button" id="btnDeployPrev" class="cu-btn-sm cu-btn-secondary" onclick="prevDeployStep()" style="display:none;">
                <?= __('app.deploy_btn_prev') ?>
            </button>
            <div style="display:flex; gap:10px; margin-left:auto;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalDeployWizard')"><?= __('app.modal_cancel') ?></button>
                <button type="button" id="btnDeployNext" class="cu-btn-sm" style="background:var(--brand-primary); color:#fff; font-weight:600;" onclick="nextDeployStep()">
                    <?= __('app.deploy_btn_next') ?>
                </button>
                <button type="button" id="btnDeployAction" class="cu-btn-sm" style="background:#00b894; color:#fff; font-weight:700; display:none;" onclick="startLiveDeployment()">
                    <?= __('app.deploy_btn_deploy_now') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- TOAST CONTAINER -->
<div class="cu-toast-container" id="cuToastContainer"></div>

<script>
window.ALL_TEAM_USERS = <?= json_encode($allUsers, JSON_UNESCAPED_UNICODE) ?>;
</script>
