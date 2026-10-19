<?php
/**
 * LiveDeploy Modülü Başlatıcı (init.php)
 * Kancaları, Dinleyicileri ve Sayfa Varlıklarını (Assets) Kaydeder.
 */
use Core\EventManager;
use Core\AssetManager;
use Modules\LiveDeploy\Controllers\DeployController;

// Auto-register legacy/service classes for LiveDeploy
require_once __DIR__ . '/Services/DeltaSyncEngine.php';
require_once __DIR__ . '/Services/LiveConfigGenerator.php';
require_once __DIR__ . '/Services/ProjectAnalyzer.php';
require_once __DIR__ . '/Services/HostingAdapters/HostingManager.php';

// 1. Kanban Araç Çubuğuna "Canlı Dağıtım" butonunu dinamik enjekte et
EventManager::addFilter('kanban.tools_menu', function(array $items, int $projectId) {
    $items[] = [
        'id'       => 'tool-live-deploy',
        'icon'     => 'fas fa-rocket',
        'title'    => __('LiveDeploy::deploy_btn_wizard'),
        'url'      => 'javascript:openDeployWizard(' . $projectId . ')',
        'order'    => 20
    ];
    return $items;
});

// 2. Modül Aktifken İlgili JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueJs('deploy-wizard', '/taskmanager/Modules/LiveDeploy/Assets/js/deploy-wizard.js');
AssetManager::enqueueCss('deploy-wizard', '/taskmanager/Modules/LiveDeploy/Assets/css/deploy-wizard.css');

// 3. Sağlık Kontrolü Hook Dinleyicisi
EventManager::listen('live_deploy.healthcheck', function(string $url) {
    $controller = new DeployController();
    return $controller->checkHealth($url);
});
