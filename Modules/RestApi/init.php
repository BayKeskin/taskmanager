<?php
use Core\EventManager;
use Core\AssetManager;

EventManager::listen('app.init', function() {
    AssetManager::enqueueCss('/Modules/RestApi/Assets/css/restapi.css');
    AssetManager::enqueueJs('/Modules/RestApi/Assets/js/restapi.js');
});

if (!function_exists('rest_api_service')) {
    function rest_api_service(): \Modules\RestApi\Services\RestApiService {
        static $instance = null;
        if ($instance === null) {
            $instance = new \Modules\RestApi\Services\RestApiService();
        }
        return $instance;
    }
}
