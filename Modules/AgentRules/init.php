<?php
use Core\Route;
use Core\AssetManager;
use Core\EventManager;

// 1. Varlık Yükleme (Assets & Çeviriler)
AssetManager::enqueueCss('agent-rules-css', '/Modules/AgentRules/Assets/css/agent-rules.css');
AssetManager::enqueueJs('agent-rules-js', '/Modules/AgentRules/Assets/js/agent-rules.js');
AssetManager::localizeScript('agent-rules-js', 'agentRulesTranslations', [
    'confirmSyncAll' => __('AgentRules::confirm_sync_all'),
    'syncing'        => __('AgentRules::btn_sync_single'),
    'statusSynced'   => __('AgentRules::status_synced')
]);

// 3. Proje Eklendiğinde veya Düzenlendiğinde Otomatik AGENTS.md Senkronizasyonu
EventManager::listen('project.created', function($payload) {
    $projectId = is_array($payload) ? ($payload['project_id'] ?? ($payload['id'] ?? null)) : ($payload->project_id ?? ($payload->id ?? null));
    if ($projectId) {
        $controller = new \Modules\AgentRules\Controllers\AgentRulesController();
        $controller->executeSyncForProject((int)$projectId);
    }
});

EventManager::listen('project.updated', function($payload) {
    $projectId = is_array($payload) ? ($payload['project_id'] ?? ($payload['id'] ?? null)) : ($payload->project_id ?? ($payload->id ?? null));
    if ($projectId) {
        $controller = new \Modules\AgentRules\Controllers\AgentRulesController();
        $controller->executeSyncForProject((int)$projectId);
    }
});
