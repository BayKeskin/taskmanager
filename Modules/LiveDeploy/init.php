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

AssetManager::localizeScript('deploy-wizard', 'DEPLOY_I18N', [
    'select_project_first'     => __('LiveDeploy::select_project_first'),
    'err_project_info_failed'  => __('LiveDeploy::err_project_info_failed'),
    'status_verifying'         => __('LiveDeploy::status_verifying'),
    'status_conn_success'      => __('LiveDeploy::status_conn_success'),
    'status_conn_failed'       => __('LiveDeploy::status_conn_failed'),
    'status_server_error'      => __('LiveDeploy::status_server_error'),
    'badge_calculating'        => __('LiveDeploy::badge_calculating'),
    'badge_initial_deploy'     => __('LiveDeploy::badge_initial_deploy'),
    'badge_delta_sync'         => __('LiveDeploy::badge_delta_sync'),
    'badge_no_diff'            => __('LiveDeploy::badge_no_diff'),
    'err_delta_diff_failed'    => __('LiveDeploy::err_delta_diff_failed'),
    'files_to_upload'          => __('LiveDeploy::files_to_upload'),
    'files_to_delete'          => __('LiveDeploy::files_to_delete'),
    'migrations_to_run'        => __('LiveDeploy::migrations_to_run'),
    'btn_deploying'            => __('LiveDeploy::btn_deploying'),
    'btn_retry'                => __('LiveDeploy::btn_retry'),
    'btn_deploy_done'          => __('LiveDeploy::btn_deploy_done'),
    'log_step1'                => __('LiveDeploy::log_step1'),
    'log_step2'                => __('LiveDeploy::log_step2'),
    'log_step3'                => __('LiveDeploy::log_step3'),
    'log_step4'                => __('LiveDeploy::log_step4'),
    'log_rollback_start'       => __('LiveDeploy::log_rollback_start'),
    'title_deploy_completed'   => __('LiveDeploy::title_deploy_completed'),
    'desc_deploy_completed'    => __('LiveDeploy::desc_deploy_completed'),
    'badge_health_checking'    => __('LiveDeploy::badge_health_checking'),
    'badge_health_success'     => __('LiveDeploy::badge_health_success'),
    'badge_health_error'       => __('LiveDeploy::badge_health_error'),
    'badge_health_conn_error'  => __('LiveDeploy::badge_health_conn_error'),
    'deploy_rollback_confirm'  => __('LiveDeploy::deploy_rollback_confirm'),
    'msg_rollback_done'        => __('LiveDeploy::msg_rollback_done'),
    'err_rollback_msg'         => __('LiveDeploy::err_rollback_msg'),
]);

// 3. Sağlık Kontrolü Hook Dinleyicisi
EventManager::listen('live_deploy.healthcheck', function(string $url) {
    $controller = new DeployController();
    return $controller->checkHealth($url);
});

