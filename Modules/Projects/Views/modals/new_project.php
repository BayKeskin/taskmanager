<!-- 2. YENİ PROJE EKLEME MODALI -->
<div class="cu-modal-overlay" id="modalNewProject">
    <div class="cu-modal cu-project-modal">
        <div class="cu-modal-header cu-proj-modal-header">
            <h3 class="cu-proj-modal-title">
                <span>📁</span> <?= __('Projects::modal_new_project_title') ?>
            </h3>
            <button class="cu-proj-close-btn" data-close-modal="modalNewProject">✕</button>
        </div>
        <form id="formNewProject" class="cu-proj-form">
            <div class="cu-modal-body cu-proj-modal-body">
                <div class="cu-form-group">
                    <label><?= __('Projects::modal_proj_title') ?></label>
                    <input type="text" name="title" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Projects::modal_proj_name_ph')) ?>" required>
                </div>
                <div class="cu-proj-grid-2">
                    <div class="cu-form-group">
                        <label><?= __('Projects::modal_proj_code') ?></label>
                        <input type="text" name="project_code" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Projects::modal_proj_code_ph')) ?>" required>
                    </div>
                    <div class="cu-form-group">
                        <label><?= __('Projects::modal_proj_color') ?></label>
                        <input type="color" name="color" class="cu-form-control cu-proj-color-picker" value="#7B68EE">
                    </div>
                </div>
                <div class="cu-form-group">
                    <label><?= __('Projects::modal_proj_hourly_rate') ?></label>
                    <input type="number" step="0.5" min="0" name="hourly_rate" class="cu-form-control" value="500.00" placeholder="500.00">
                    <small class="cu-proj-hint">
                        💡 <?= __('Projects::modal_proj_hourly_rate_hint') ?>
                    </small>
                </div>
                <div class="cu-form-group">
                    <label><?= __('Projects::modal_proj_desc') ?></label>
                    <textarea name="description" class="cu-form-control" rows="2" placeholder="<?= htmlspecialchars(__('Projects::modal_proj_desc_ph')) ?>"></textarea>
                </div>

                <div class="cu-proj-ai-section">
                    <div class="cu-proj-ai-header-simple">
                        <span>🤖</span> <?= __('Projects::modal_ai_webhook_title') ?>
                    </div>
                    <div class="cu-form-group cu-proj-form-group-sm">
                        <label class="cu-proj-label-flex">
                            <span>📁 <?= __('Projects::modal_workspace_path') ?></span>
                            <span class="cu-proj-auto-badge">⚡ <?= __('Projects::modal_auto_detect') ?></span>
                        </label>
                        <input type="text" name="workspace_path" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Projects::modal_workspace_path_ph')) ?>">
                        <small class="cu-proj-hint">
                            💡 <?= __('Projects::modal_workspace_path_hint') ?>
                        </small>
                    </div>
                    <div class="cu-form-group cu-proj-form-group-sm">
                        <div class="cu-proj-label-bar">
                            <label class="cu-proj-label-zero">
                                <span>🤖 <?= __('Projects::modal_proj_agent_rules') ?></span>
                            </label>
                            <button type="button" id="btnRefineRulesNew" class="cu-btn-sm cu-proj-btn-refine" title="<?= htmlspecialchars(__('Projects::modal_refine_rules_hint')) ?>">
                                <?= __('Projects::modal_refine_rules_btn') ?>
                            </button>
                        </div>
                        <textarea name="agent_rules" id="newProjAgentRules" class="cu-form-control cu-proj-monospace-area-sm" rows="3" placeholder="<?= htmlspecialchars(__('Projects::modal_proj_agent_rules_ph')) ?>"></textarea>
                        <small class="cu-proj-hint">
                            💡 <?= __('Projects::modal_refine_rules_hint') ?>
                        </small>
                    </div>
                    <div class="cu-form-group cu-proj-form-group-sm">
                        <label class="cu-proj-label-sm"><?= __('Projects::modal_webhook_url') ?></label>
                        <input type="url" name="webhook_url" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Projects::modal_webhook_url_ph')) ?>">
                    </div>
                    <div class="cu-form-group">
                        <input type="hidden" name="agent_conversation_id" value="">
                        <div class="cu-proj-zero-config-box">
                            <span class="cu-proj-zero-icon">⚡</span>
                            <div>
                                <b class="cu-proj-zero-bold"><?= __('Projects::modal_zero_config') ?>:</b> <?= __('Projects::modal_conv_id_hint') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cu-modal-footer cu-proj-modal-footer">
                <button type="button" class="cu-btn-sm" data-close-modal="modalNewProject"><?= __('Projects::btn_cancel') ?></button>
                <button type="submit" class="cu-btn-quick-task"><?= __('Projects::modal_create_project') ?></button>
            </div>
        </form>
    </div>
</div>
