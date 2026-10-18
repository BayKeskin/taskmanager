<?php
/**
 * GithubDevops Modülü Başlatıcı (init.php)
 * Kancaları ve Olay Dinleyicilerini Kaydeder.
 */
use Core\EventManager;
use Core\AssetManager;
use Modules\GithubDevops\Services\GitService;

// 1. Statik JS Varlıklarını Kuyruğa Al
AssetManager::enqueueJs('github-devops', '/taskmanager/Modules/GithubDevops/Assets/js/github-devops.js');

// 2. Görev teslim edildiğinde otomatik Git Commit & Push kancası
EventManager::listen('task.submitted', function(array $payload) {
    if (!empty($payload['project']) && !empty($payload['task'])) {
        return GitService::autoCommitAndPushTask((object)$payload['project'], (object)$payload['task']);
    }
    return null;
});
