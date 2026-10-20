<!-- 2.1 PROJE DÜZENLEME & AI WEBHOOK MODALI -->
<div class="cu-modal-overlay" id="modalEditProject">
    <div class="cu-modal cu-project-modal">
        <div class="cu-modal-header cu-proj-modal-header">
            <h3 class="cu-proj-modal-title">
                <span>⚙️</span> <?= __('Projects::modal_edit_project_title') ?>
            </h3>
            <button class="cu-proj-close-btn" data-close-modal="modalEditProject">✕</button>
        </div>
        <form id="formEditProject" class="cu-proj-form">
            <input type="hidden" name="project_id" id="editProjId">
            <div class="cu-modal-body cu-proj-modal-body">
                <div class="cu-form-group">
                    <label><?= __('Projects::modal_proj_title') ?></label>
                    <input type="text" name="title" id="editProjTitle" class="cu-form-control" required>
                </div>
                <div class="cu-proj-grid-2">
                    <div class="cu-form-group">
                        <label><?= __('Projects::modal_proj_code') ?></label>
                        <input type="text" id="editProjCode" class="cu-form-control cu-proj-code-input" readonly>
                    </div>
                    <div class="cu-form-group">
                        <label><?= __('Projects::modal_proj_color') ?></label>
                        <input type="color" name="color" id="editProjColor" class="cu-form-control cu-proj-color-picker">
                    </div>
                </div>
                <div class="cu-form-group">
                    <label><?= __('Projects::modal_proj_hourly_rate') ?></label>
                    <input type="number" step="0.5" min="0" name="hourly_rate" id="editProjHourlyRate" class="cu-form-control" placeholder="500.00">
                    <small class="cu-proj-hint">
                        💡 <?= __('Projects::modal_proj_hourly_rate_hint') ?>
                    </small>
                </div>
                <div class="cu-form-group">
                    <label><?= __('Projects::modal_proj_desc') ?></label>
                    <textarea name="description" id="editProjDesc" class="cu-form-control" rows="2"></textarea>
                </div>

                <div class="cu-proj-ai-section">
                    <div class="cu-proj-ai-header">
                        <span class="cu-proj-ai-title"><span>🤖</span> <?= __('Projects::modal_ai_webhook_title') ?></span>
                        <button type="button" id="btnTestWebhookEdit" class="cu-btn-sm cu-proj-btn-test-webhook">
                            🔔 <?= __('Projects::modal_test_webhook') ?>
                        </button>
                    </div>
                    <div class="cu-form-group cu-proj-form-group-sm">
                        <label class="cu-proj-label-flex">
                            <span>📁 <?= __('Projects::modal_workspace_path') ?></span>
                            <span class="cu-proj-auto-badge">⚡ <?= __('Projects::modal_auto_detect') ?></span>
                        </label>
                        <input type="text" name="workspace_path" id="editProjWorkspacePath" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Projects::modal_workspace_path_ph')) ?>">
                        <small class="cu-proj-hint">
                            💡 <?= __('Projects::modal_workspace_path_hint') ?>
                        </small>
                    </div>
                    <div class="cu-form-group cu-proj-form-group-sm">
                        <div class="cu-proj-label-bar">
                            <label class="cu-proj-label-zero">
                                <span>🤖 <?= __('Projects::modal_proj_agent_rules') ?></span>
                            </label>
                            <div class="cu-proj-btn-group-sm">
                                <button type="button" id="btnRefineRulesEdit" class="cu-btn-sm cu-proj-btn-refine" title="<?= htmlspecialchars(__('Projects::modal_refine_rules_hint')) ?>">
                                    <?= __('Projects::modal_refine_rules_btn') ?>
                                </button>
                                <button type="button" id="btnSyncRulesEdit" class="cu-btn-sm cu-proj-btn-sync" title="<?= htmlspecialchars(__('Projects::modal_sync_rules_btn')) ?>">
                                    📁 <?= __('Projects::modal_sync_rules_btn') ?>
                                </button>
                                <?php if (\Core\ModuleLoader::isModuleActive('live-deploy')): ?>
                                <button type="button" id="btnOpenDeployWizardEdit" class="cu-btn-sm cu-proj-btn-deploy" title="<?= htmlspecialchars(__('LiveDeploy::deploy_wizard_desc')) ?>">
                                    🚀 <?= __('LiveDeploy::deploy_btn_wizard') ?>
                                </button>
                                <?php endif; ?>
                                <?php if (\Core\ModuleLoader::isModuleActive('mcp')): ?>
                                <button type="button" id="btnOpenMcpModalEdit" class="cu-btn-sm cu-proj-btn-mcp" title="<?= htmlspecialchars(__('Mcp::mcp_marketplace_desc')) ?>">
                                    🧩 <?= __('Mcp::mcp_btn_open') ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <textarea name="agent_rules" id="editProjAgentRules" class="cu-form-control cu-proj-monospace-area" rows="4" placeholder="<?= htmlspecialchars(__('Projects::modal_proj_agent_rules_ph')) ?>"></textarea>
                        <small class="cu-proj-hint">
                            💡 <?= __('Projects::modal_refine_rules_hint') ?>
                        </small>
                    </div>
                    <div class="cu-proj-rules-doc-box">
                        <b class="cu-proj-doc-title"><?= __('Projects::modal_rules_architecture_title') ?></b><br>
                        1. <b><?= __('Projects::modal_rules_doctrine') ?></b><br>
                        2. <b><?= __('Projects::modal_rules_proj_rules') ?></b><br>
                        3. <b><?= __('Projects::modal_rules_task_reqs') ?></b>
                    </div>
                    <div class="cu-form-group cu-proj-form-group-sm">
                        <label class="cu-proj-label-sm"><?= __('Projects::modal_webhook_url') ?></label>
                        <input type="url" name="webhook_url" id="editProjWebhookUrl" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Projects::modal_webhook_url_ph')) ?>">
                    </div>
                    <input type="hidden" name="agent_conversation_id" id="editProjConvId" value="">
                    
                    <!-- ÇİFT AJAN (DEV + QA) PENCERE KİMLİKLERİ -->
                    <div class="cu-proj-dual-agents-box">
                        <div class="cu-form-group cu-proj-form-group-sm">
                            <label class="cu-proj-label-sm">
                                <?= __('Projects::modal_dev_conv_id') ?>
                            </label>
                            <input type="text" name="dev_conversation_id" id="editProjDevConvId" class="cu-form-control cu-proj-monospace-input" placeholder="<?= htmlspecialchars(__('Projects::modal_dev_conv_id_ph')) ?>">
                            <small class="cu-proj-hint">
                                💡 <?= __('Projects::modal_dev_conv_id_hint') ?>
                            </small>
                        </div>

                        <div class="cu-form-group cu-proj-form-group-sm">
                            <label class="cu-proj-label-sm">
                                <?= __('Projects::modal_qa_conv_id') ?>
                            </label>
                            <input type="text" name="qa_conversation_id" id="editProjQaConvId" class="cu-form-control cu-proj-monospace-input" placeholder="<?= htmlspecialchars(__('Projects::modal_qa_conv_id_ph')) ?>">
                            <small class="cu-proj-hint">
                                🧪 <?= __('Projects::modal_qa_conv_id_hint') ?>
                            </small>
                        </div>

                        <div class="cu-form-group cu-proj-form-group-sm">
                            <label class="cu-proj-label-sm">
                                <?= __('Projects::modal_qa_instructions') ?>
                            </label>
                            <textarea name="qa_test_instructions" id="editProjQaInstructions" class="cu-form-control cu-proj-monospace-area-sm" rows="3" placeholder="<?= htmlspecialchars(__('Projects::modal_qa_instructions_ph')) ?>"></textarea>
                            <small class="cu-proj-hint">
                                📋 <?= __('Projects::modal_qa_instructions_hint') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cu-modal-footer cu-proj-modal-footer">
                <button type="button" class="cu-btn-sm" data-close-modal="modalEditProject"><?= __('Projects::btn_cancel') ?></button>
                <button type="submit" class="cu-btn-quick-task"><?= __('Projects::modal_update_project') ?></button>
            </div>
        </form>
    </div>
</div>
