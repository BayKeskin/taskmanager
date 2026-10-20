<?php
use Core\EventManager;
use Core\AssetManager;

// 1. İstemci Tarafı Hata Radarı Scriptini Sayfalara Ekle
AssetManager::enqueueJs('error-radar', '/Modules/ErrorRadar/Assets/js/error-radar.js');

// 2. Modül Olay Dinleyicisi
EventManager::listen('error_radar.report', function(array $payload) {
    $controller = new \Modules\ErrorRadar\Controllers\ErrorRadarController();
    return $controller->processReport($payload);
});
