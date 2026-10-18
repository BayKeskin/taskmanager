<?php
use Core\EventManager;
use Core\AssetManager;

EventManager::listen('app.init', function() {
    AssetManager::enqueueCss('/Modules/Dispatcher/Assets/css/dispatcher.css');
    AssetManager::enqueueJs('/Modules/Dispatcher/Assets/js/dispatcher.js');
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
