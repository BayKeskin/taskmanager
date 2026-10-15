<?php
// Modallar ve Toast Konteyneri
require_once __DIR__ . '/../Controller/LayoutController.php';

$layoutCtrl = new LayoutController();
$modalsData = $layoutCtrl->getModalsData();
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
                    <input type="text" name="title" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_task_title_placeholder')) ?>" required>
                </div>

                <div class="cu-form-group">
                    <label><?= __('app.modal_desc_instructions') ?></label>
                    <textarea name="description" class="cu-form-control" rows="3" placeholder="<?= htmlspecialchars(__('app.modal_desc_placeholder')) ?>"></textarea>
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
                        <label style="font-size:11.5px; display:flex; justify-content:space-between;">
                            <span><?= __('app.modal_conv_id') ?></span>
                            <span style="color:#00b894; font-weight:700;">⚡ <?= __('app.modal_auto_detect_supported') ?></span>
                        </label>
                        <input type="text" name="agent_conversation_id" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_conv_id_ph')) ?>">
                        <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                            💡 <b><?= __('app.modal_zero_config') ?></b> <?= __('app.modal_conv_id_hint') ?>
                        </small>
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
                        <label style="font-size:11.5px; display:flex; justify-content:space-between;">
                            <span><?= __('app.modal_conv_id') ?></span>
                            <span style="color:#00b894; font-weight:700;">⚡ <?= __('app.modal_auto_detect_supported') ?></span>
                        </label>
                        <input type="text" name="agent_conversation_id" id="editProjConvId" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_conv_id_ph')) ?>">
                        <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                            💡 <b><?= __('app.modal_zero_config') ?></b> <?= __('app.modal_conv_id_hint') ?>
                        </small>
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
    $pm = new ProjectModel();
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

<!-- TOAST CONTAINER -->
<div class="cu-toast-container" id="cuToastContainer"></div>

<script>
window.ALL_TEAM_USERS = <?= json_encode($allUsers, JSON_UNESCAPED_UNICODE) ?>;
</script>
