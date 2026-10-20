<?php
/**
 * GithubDevops Modülü Başlatıcı (init.php)
 * Kancaları ve Olay Dinleyicilerini Kaydeder.
 */
use Core\EventManager;
use Core\AssetManager;
use Modules\GithubDevops\Services\GitService;

AssetManager::enqueueCss('github-devops', '/Modules/GithubDevops/Assets/css/githubdevops.css');
AssetManager::enqueueJs('github-devops', '/Modules/GithubDevops/Assets/js/github-devops.js');
AssetManager::localizeScript('github-devops', 'DEVOPS_I18N', [
    'err_input_required' => __('GithubDevops::err_input_required'),
    'err_general'        => __('GithubDevops::err_general'),
    'err_server'         => __('GithubDevops::err_server'),
    'sync_confirm'       => __('GithubDevops::sync_confirm'),
    'syncing'            => __('GithubDevops::syncing'),
    'sync_failed'        => __('GithubDevops::sync_failed'),
    'rollback_confirm'   => __('GithubDevops::rollback_confirm'),
    'ai_surgeon_running' => __('GithubDevops::ai_surgeon_running'),
    'rollback_running'   => __('GithubDevops::rollback_running'),
    'rollback_success'   => __('GithubDevops::rollback_success'),
    'rollback_failed'    => __('GithubDevops::rollback_failed'),
]);

// 2. Görev teslim edildiğinde otomatik Git Commit & Push kancası
EventManager::listen('task.submitted', function(array $payload) {
    if (!empty($payload['project']) && !empty($payload['task'])) {
        return GitService::autoCommitAndPushTask((object)$payload['project'], (object)$payload['task']);
    }
    return null;
});
