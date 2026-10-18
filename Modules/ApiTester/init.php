<?php
use Core\EventManager;
use Core\AssetManager;

EventManager::listen('app.init', function() {
    AssetManager::enqueueCss('/Modules/ApiTester/Assets/css/apitester.css');
    AssetManager::enqueueJs('/Modules/ApiTester/Assets/js/apitester.js');
});
