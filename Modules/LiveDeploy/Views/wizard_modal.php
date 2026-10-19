<?php
// Modules/LiveDeploy/Views/wizard_modal.php
// Canlı Dağıtım Sihirbazı Modali (HTML Şablonu)
?>
<div id="deployWizardModal" class="deploy-wizard-modal deploy-modal-hidden">
    <div class="deploy-wizard-dialog">
        <div class="deploy-wizard-header">
            <h3><i class="fas fa-rocket"></i> <?= __('LiveDeploy::wizard_title') ?></h3>
            <button type="button" class="close-btn" id="btnDeployWizardClose">&times;</button>
        </div>
        <div class="deploy-wizard-body" id="deployWizardBody">
            <div class="step-indicator">
                <span class="step active" data-step="1">1. <?= __('LiveDeploy::step_panel') ?></span>
                <span class="step" data-step="2">2. <?= __('LiveDeploy::step_scope') ?></span>
                <span class="step" data-step="3">3. <?= __('LiveDeploy::step_deploy') ?></span>
            </div>
            <div class="step-content" id="deployStepContent">
                <!-- Dinamik JavaScript içeriği yüklenecek -->
            </div>
        </div>
    </div>
</div>
