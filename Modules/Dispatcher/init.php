<?php
use Core\EventManager;
use Core\AssetManager;

// 1. Varlık Yükleme (Assets & Çeviriler)
AssetManager::enqueueCss('dispatcher-css', '/Modules/Dispatcher/Assets/css/dispatcher.css');
AssetManager::enqueueJs('dispatcher-js', '/Modules/Dispatcher/Assets/js/dispatcher.js');
AssetManager::localizeScript('dispatcher-js', 'dispatcherTranslations', [
    'confirmDispatch' => __('Dispatcher::confirm_dispatch'),
    'confirmAbort'    => __('Dispatcher::confirm_abort')
]);

// 2. Otonom AI Görev Tetikleme Kancaları
EventManager::listen('task.created', function($payload) {
    $projectId = is_array($payload) ? ($payload['project_id'] ?? null) : ($payload->project_id ?? null);
    $tags = is_array($payload) ? ($payload['tags'] ?? '') : ($payload->tags ?? '');
    if ($projectId && \Modules\Board\Services\TaskLifecycleService::isAiTask($tags)) {
        \Modules\Board\Services\TaskLifecycleService::dispatchNextAiTaskIfAvailable((int)$projectId);
    }
});

EventManager::listen('task.status_changed', function($payload) {
    $projectId = is_array($payload) ? ($payload['project_id'] ?? null) : ($payload->project_id ?? null);
    $newStatus = is_array($payload) ? ($payload['new_status'] ?? '') : ($payload->new_status ?? '');
    $taskId = is_array($payload) ? ($payload['task_id'] ?? null) : ($payload->task_id ?? null);
    if ($projectId && $newStatus === 'in_progress' && $taskId) {
        \Modules\Board\Services\TaskLifecycleService::triggerTaskStartedWebhook((int)$taskId, 'assigned');
    }
});

// Modül İçi Yardımcı Fonksiyon
if (!function_exists('dispatcher_service')) {
    function dispatcher_service(): \Modules\Dispatcher\Services\DispatcherService {
        static $instance = null;
        if ($instance === null) {
            $instance = new \Modules\Dispatcher\Services\DispatcherService();
        }
        return $instance;
    }
}
