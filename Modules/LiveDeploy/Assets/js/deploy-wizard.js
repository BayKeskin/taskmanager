/**
 * Modules/LiveDeploy/Assets/js/deploy-wizard.js
 * Canlı Dağıtım Sihirbazı İstemci Mantığı
 */
window.openDeployWizard = function(projectId) {
    console.log("LiveDeploy Module: Opening wizard for project", projectId);
    const modal = document.getElementById('deployWizardModal');
    if (modal) {
        modal.style.display = 'flex';
    }
};

window.closeDeployWizard = function() {
    const modal = document.getElementById('deployWizardModal');
    if (modal) {
        modal.style.display = 'none';
    }
};
