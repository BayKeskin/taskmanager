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
