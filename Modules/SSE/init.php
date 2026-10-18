<?php
/**
 * SSE Modülü Başlatıcı (init.php)
 */
use Core\EventManager;
use Core\AssetManager;
use Modules\SSE\Services\SseBroadcaster;

// 1. Canlı SSE Senkronizasyon İstemci Kütüphanesini Kuyruğa Al
AssetManager::enqueueJs('sse-realtime', '/taskmanager/Modules/SSE/Assets/js/realtime.js');

// Canlı SSE yayını kancası
EventManager::listen('live_event.broadcast', function(array $data) {
    return SseBroadcaster::broadcast(
        $data['event_type'],
        $data['project_id'] ?? null,
        $data['task_id'] ?? null,
        $data['user_id'] ?? null,
        $data['payload'] ?? []
    );
});
