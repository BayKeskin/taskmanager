<!-- 🚀 CANLI DAĞITIM & HOSTİNG SİHİRBAZI MODALI (TSK-1085) -->
<div class="cu-modal-overlay" id="modalDeployWizard">
    <div class="cu-modal cu-modal-deploy-dialog">
        <div class="cu-modal-header cu-modal-deploy-header">
            <div>
                <h3 class="deploy-modal-title">
                    <span>🚀</span> <?= __('LiveDeploy::deploy_wizard_title') ?>
                </h3>
                <p class="deploy-modal-subtitle">
                    <?= __('LiveDeploy::deploy_wizard_desc') ?>
                </p>
            </div>
            <button type="button" class="deploy-modal-close-btn" id="btnDeployWizardHeaderClose">✕</button>
        </div>

        <!-- SİHİRBAZ ADIM GÖSTERGESİ (STEPPER) -->
        <div class="deploy-stepper-bar">
            <div id="deployStepTab1" class="deploy-step-tab active">
                <span class="deploy-step-num">1</span>
                <span><?= __('LiveDeploy::deploy_step1_title') ?></span>
            </div>
            <div id="deployStepTab2" class="deploy-step-tab">
                <span class="deploy-step-num">2</span>
                <span><?= __('LiveDeploy::deploy_step2_title') ?></span>
            </div>
            <div id="deployStepTab3" class="deploy-step-tab">
                <span class="deploy-step-num">3</span>
                <span><?= __('LiveDeploy::deploy_step3_title') ?></span>
            </div>
        </div>

        <div class="cu-modal-body deploy-modal-scroll-body">
            <input type="hidden" id="deployWizardProjId" value="">

            <!-- ADIM 1: HOSTİNG & PANEL/FTP GİRİŞİ -->
            <div id="deployStepContent1" class="deploy-step-content">
                <div class="cu-form-group deploy-form-group-mb">
                    <label class="deploy-label-title"><?= __('LiveDeploy::hosting_adapter_title') ?></label>
                    <select id="deployPanelType" class="cu-form-control deploy-select-main">
                        <option value="standard"><?= __('LiveDeploy::hosting_panel_standard') ?></option>
                        <option value="cpanel"><?= __('LiveDeploy::hosting_panel_cpanel') ?></option>
                        <option value="plesk"><?= __('LiveDeploy::hosting_panel_plesk') ?></option>
                        <option value="cyberpanel"><?= __('LiveDeploy::hosting_panel_cyberpanel') ?></option>
                    </select>
                </div>

                <div class="deploy-grid-2col">
                    <div class="cu-form-group">
                        <label class="deploy-label-sm"><?= __('LiveDeploy::deploy_target_domain') ?></label>
                        <input type="url" id="deployLiveDomain" class="cu-form-control" placeholder="https://ornekproje.com" value="">
                    </div>
                    <div class="cu-form-group">
                        <label class="deploy-label-sm"><?= __('LiveDeploy::label_ftp_host') ?></label>
                        <input type="text" id="deployFtpHost" class="cu-form-control" placeholder="ftp.ornekproje.com" value="">
                    </div>
                </div>

                <div class="deploy-grid-3col">
                    <div class="cu-form-group">
                        <label class="deploy-label-sm"><?= __('LiveDeploy::label_ftp_user') ?></label>
                        <input type="text" id="deployFtpUser" class="cu-form-control" placeholder="cpanel_user">
                    </div>
                    <div class="cu-form-group">
                        <label class="deploy-label-sm"><?= __('LiveDeploy::label_ftp_pass') ?></label>
                        <input type="password" id="deployFtpPass" class="cu-form-control" placeholder="••••••••">
                    </div>
                    <div class="cu-form-group">
                        <label class="deploy-label-sm"><?= __('LiveDeploy::label_port') ?></label>
                        <input type="number" id="deployFtpPort" class="cu-form-control" value="21">
                    </div>
                </div>

                <div class="deploy-panel-test-bar">
                    <span id="deployPanelTestStatus" class="deploy-panel-status-text">
                        <?= __('LiveDeploy::hint_verify_credentials') ?>
                    </span>
                    <button type="button" class="cu-btn-sm deploy-panel-test-btn" id="btnTestDeployPanel">
                        <?= __('LiveDeploy::deploy_btn_test_panel') ?>
                    </button>
                </div>
            </div>

            <!-- ADIM 2: CANLI DOMAİN VE VERİTABANI TEYİDİ -->
            <div id="deployStepContent2" class="deploy-step-content deploy-modal-hidden">
                <div class="deploy-isolation-box">
                    <b>ℹ️ <?= __('LiveDeploy::label_live_protection_title') ?>:</b> <?= __('LiveDeploy::label_live_protection_desc') ?>
                </div>

                <div class="deploy-grid-2col">
                    <div class="cu-form-group">
                        <label class="deploy-label-sm"><?= __('LiveDeploy::label_live_db_host') ?></label>
                        <input type="text" id="deployDbHost" class="cu-form-control" value="localhost">
                    </div>
                    <div class="cu-form-group">
                        <label class="deploy-label-sm"><?= __('LiveDeploy::label_live_db_name') ?></label>
                        <input type="text" id="deployDbName" class="cu-form-control" placeholder="ornek_db">
                    </div>
                </div>

                <div class="deploy-grid-2col">
                    <div class="cu-form-group">
                        <label class="deploy-label-sm"><?= __('LiveDeploy::label_live_db_user') ?></label>
                        <input type="text" id="deployDbUser" class="cu-form-control" placeholder="ornek_user">
                    </div>
                    <div class="cu-form-group">
                        <label class="deploy-label-sm"><?= __('LiveDeploy::label_live_db_pass') ?></label>
                        <input type="password" id="deployDbPass" class="cu-form-control" placeholder="••••••••">
                    </div>
                </div>

                <!-- Git Diff Özeti -->
                <div id="deployDiffSummaryBox" class="deploy-diff-summary-card">
                    <div class="deploy-diff-header">
                        <span><?= __('LiveDeploy::label_sync_scope') ?></span>
                        <span id="deployDiffBadge" class="deploy-diff-badge-text"><?= __('LiveDeploy::label_calculating') ?></span>
                    </div>
                    <div id="deployDiffDetails" class="deploy-diff-details-text">
                        <?= __('LiveDeploy::label_loading_changes') ?>
                    </div>
                </div>
            </div>

            <!-- ADIM 3: TEK TIKLA DAĞITIM, SAĞLIK KONTROLÜ VE ROLLBACK -->
            <div id="deployStepContent3" class="deploy-step-content deploy-modal-hidden">
                <div class="deploy-status-wrap">
                    <div id="deployHealthBadge" class="deploy-health-badge-wrap">
                        <span>🩺</span> <span><?= __('LiveDeploy::label_health_waiting') ?></span>
                    </div>
                    <h4 id="deployStatusTitle" class="deploy-status-heading"><?= __('LiveDeploy::label_ready_to_deploy') ?></h4>
                    <p id="deployStatusDesc" class="deploy-status-lead">
                        <?= __('LiveDeploy::label_ready_to_deploy_desc') ?>
                    </p>
                </div>

                <div id="deployLiveLogBox" class="deploy-live-log-console">
                </div>

                <!-- Cerrahi Rollback Kutusu -->
                <div id="deployRollbackBox" class="deploy-rollback-card">
                    <div>
                        <div class="deploy-rollback-title"><?= __('LiveDeploy::label_rollback_title') ?></div>
                        <div class="deploy-rollback-sub"><?= __('LiveDeploy::label_rollback_desc') ?></div>
                    </div>
                    <button type="button" class="cu-btn-sm deploy-rollback-action-btn" id="btnExecuteDeployRollback">
                        <?= __('LiveDeploy::deploy_btn_rollback') ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="cu-modal-footer cu-modal-deploy-footer">
            <button type="button" id="btnDeployPrev" class="cu-btn-sm cu-btn-secondary deploy-modal-hidden">
                <?= __('LiveDeploy::deploy_btn_prev') ?>
            </button>
            <div class="deploy-footer-actions">
                <button type="button" class="cu-btn-sm" id="btnDeployModalCancel"><?= __('LiveDeploy::btn_cancel') ?></button>
                <button type="button" id="btnDeployNext" class="cu-btn-sm deploy-btn-next-step">
                    <?= __('LiveDeploy::deploy_btn_next') ?>
                </button>
                <button type="button" id="btnDeployAction" class="cu-btn-sm deploy-btn-start-action">
                    <?= __('LiveDeploy::deploy_btn_deploy_now') ?>
                </button>
            </div>
        </div>
    </div>
</div>
