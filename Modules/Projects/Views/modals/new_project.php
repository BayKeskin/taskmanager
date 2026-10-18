<!-- 2. YENİ PROJE EKLEME MODALI -->
<div class="cu-modal-overlay" id="modalNewProject" onclick="if(event.target === this) closeModal('modalNewProject')">
    <div class="cu-modal" style="max-height:calc(100vh - 40px); height:auto; display:flex; flex-direction:column; overflow:hidden;">
        <div class="cu-modal-header" style="flex-shrink:0;">
            <h3 style="font-size:15px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px;">
                <span>📁</span> <?= __('Projects::modal_new_project_title') ?>
            </h3>
            <button onclick="closeModal('modalNewProject')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;">✕</button>
        </div>
        <form id="formNewProject" onsubmit="handleCreateProject(event)" style="display:flex; flex-direction:column; flex:1 1 auto; min-height:0; max-height:calc(100vh - 100px); overflow:hidden; margin:0;">
            <div class="cu-modal-body" style="overflow-y:auto; overflow-x:hidden; flex:1 1 auto; min-height:0; padding:20px; overscroll-behavior:contain;">
                <div class="cu-form-group">
                    <label><?= __('Projects::modal_proj_title') ?></label>
                    <input type="text" name="title" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Projects::modal_proj_name_ph')) ?>" required>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="cu-form-group">
                        <label><?= __('Projects::modal_proj_code') ?></label>
                        <input type="text" name="project_code" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Projects::modal_proj_code_ph')) ?>" required>
                    </div>
                    <div class="cu-form-group">
                        <label><?= __('Projects::modal_proj_color') ?></label>
                        <input type="color" name="color" class="cu-form-control" value="#7B68EE" style="height:40px; padding:2px;">
                    </div>
                </div>
                <div class="cu-form-group">
                    <label><?= __('Projects::modal_proj_hourly_rate', 'Saatlik Birim Ücret (₺/saat)') ?></label>
                    <input type="number" step="0.5" min="0" name="hourly_rate" class="cu-form-control" value="500.00" placeholder="500.00">
                    <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                        💡 <?= __('Projects::modal_proj_hourly_rate_hint', 'Hakediş ve efor raporlarında baz alınacak birim işçilik tutarı.') ?>
                    </small>
                </div>
                <div class="cu-form-group">
                    <label><?= __('Projects::modal_proj_desc') ?></label>
                    <textarea name="description" class="cu-form-control" rows="2" placeholder="<?= htmlspecialchars(__('Projects::modal_proj_desc_ph')) ?>"></textarea>
                </div>

                <div style="border-top:1px dashed var(--border-subtle); margin:14px 0 10px; padding-top:10px;">
                    <div style="font-size:11.5px; font-weight:700; color:var(--brand-primary); text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                        <span>🤖</span> <?= __('Projects::modal_ai_webhook_title') ?>
                    </div>
                    <div class="cu-form-group" style="margin-bottom:8px;">
                        <label style="font-size:11.5px; display:flex; justify-content:space-between;">
                            <span>📁 <?= __('Projects::modal_workspace_path') ?></span>
                            <span style="color:#00b894; font-weight:700;">⚡ <?= __('Projects::modal_auto_detect') ?></span>
                        </label>
                        <input type="text" name="workspace_path" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Projects::modal_workspace_path_ph')) ?>">
                        <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                            💡 <?= __('Projects::modal_workspace_path_hint') ?>
                        </small>
                    </div>
                    <div class="cu-form-group" style="margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <label style="font-size:11.5px; margin:0;">
                                <span>🤖 <?= __('Projects::modal_proj_agent_rules') ?></span>
                            </label>
                            <button type="button" id="btnRefineRulesNew" onclick="refineProjectRules(false)" class="cu-btn-sm" style="font-size:10.5px; padding:2px 8px; background:rgba(123,104,238,0.15); color:#7b68ee; border:1px solid rgba(123,104,238,0.3);" title="<?= htmlspecialchars(__('Projects::modal_refine_rules_hint')) ?>">
                                <?= __('Projects::modal_refine_rules_btn') ?>
                            </button>
                        </div>
                        <textarea name="agent_rules" id="newProjAgentRules" class="cu-form-control" rows="3" placeholder="<?= htmlspecialchars(__('Projects::modal_proj_agent_rules_ph')) ?>" style="font-family:monospace; font-size:11.5px;"></textarea>
                        <small style="font-size:10.5px; color:var(--text-muted); display:block; margin-top:3px;">
                            💡 <?= __('Projects::modal_refine_rules_hint') ?>
                        </small>
                    </div>
                    <div class="cu-form-group" style="margin-bottom:8px;">
                        <label style="font-size:11.5px;"><?= __('Projects::modal_webhook_url') ?></label>
                        <input type="url" name="webhook_url" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Projects::modal_webhook_url_ph')) ?>">
                    </div>
                    <div class="cu-form-group">
                        <input type="hidden" name="agent_conversation_id" value="">
                        <div style="background:rgba(0,184,148,0.08); border:1px solid rgba(0,184,148,0.25); border-radius:6px; padding:10px 14px; display:flex; align-items:center; gap:10px; font-size:12px; color:var(--text-primary); margin-top:4px;">
                            <span style="font-size:18px; line-height:1;">⚡</span>
                            <div>
                                <b style="color:#00b894;"><?= __('Projects::modal_zero_config') ?>:</b> <?= __('Projects::modal_conv_id_hint') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cu-modal-footer" style="flex-shrink:0; position:sticky; bottom:0; z-index:10; padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalNewProject')"><?= __('Board::btn_cancel', 'İptal') ?></button>
                <button type="submit" class="cu-btn-quick-task"><?= __('Projects::modal_create_project') ?></button>
            </div>
        </form>
    </div>
</div>
