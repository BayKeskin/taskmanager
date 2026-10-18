<?php
// Modules/LiveDeploy/Views/wizard_modal.php
// Canlı Dağıtım Sihirbazı Modali (HTML Şablonu)
?>
<div id="deployWizardModal" class="deploy-wizard-modal" style="display:none;">
    <div class="deploy-wizard-dialog">
        <div class="deploy-wizard-header">
            <h3><i class="fas fa-rocket"></i> <?= __('app.live_deployment_wizard', '🚀 Canlı Dağıtım Sihirbazı') ?></h3>
            <button type="button" class="close-btn" onclick="closeDeployWizard()">&times;</button>
        </div>
        <div class="deploy-wizard-body" id="deployWizardBody">
            <div class="step-indicator">
                <span class="step active" data-step="1">1. <?= __('app.deploy_step_credentials', 'Panel & FTP') ?></span>
                <span class="step" data-step="2">2. <?= __('app.deploy_step_scope', 'Kapsam & Diff') ?></span>
                <span class="step" data-step="3">3. <?= __('app.deploy_step_sync', 'Dağıtım & Sağlık') ?></span>
            </div>
            <div class="step-content" id="deployStepContent">
                <!-- Dinamik JavaScript içeriği yüklenecek -->
            </div>
        </div>
    </div>
</div>
