<?php
/**
 * GithubDevops Modülü Başlatıcı (init.php)
 * Kancaları ve Olay Dinleyicilerini Kaydeder.
 */
use Core\EventManager;
use Core\AssetManager;
use Modules\GithubDevops\Services\GitService;

// 1. Statik CSS ve JS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('github-devops', '/taskmanager/Modules/GithubDevops/Assets/css/githubdevops.css');
AssetManager::enqueueJs('github-devops', '/taskmanager/Modules/GithubDevops/Assets/js/github-devops.js');

// 2. Görev teslim edildiğinde otomatik Git Commit & Push kancası
EventManager::listen('task.submitted', function(array $payload) {
    if (!empty($payload['project']) && !empty($payload['task'])) {
        return GitService::autoCommitAndPushTask((object)$payload['project'], (object)$payload['task']);
    }
    return null;
});
