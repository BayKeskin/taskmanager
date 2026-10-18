<?php
use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('aiarchitect-styles', '/taskmanager/Modules/AiArchitect/Assets/css/aiarchitect.css');
AssetManager::enqueueJs('aiarchitect-js', '/taskmanager/Modules/AiArchitect/Assets/js/aiarchitect.js');

// Modül İçi Yardımcı Fonksiyon
if (!function_exists('ai_architect')) {
    function ai_architect(): \Modules\AiArchitect\Services\GeminiClient {
        static $instance = null;
        if ($instance === null) {
            $instance = new \Modules\AiArchitect\Services\GeminiClient();
        }
        return $instance;
    }
}
