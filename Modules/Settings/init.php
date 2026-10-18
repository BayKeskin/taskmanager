<?php
use Core\EventManager;
use Core\AssetManager;

EventManager::listen('app.init', function() {
    AssetManager::enqueueCss('/Modules/Settings/Assets/css/settings.css');
    AssetManager::enqueueJs('/Modules/Settings/Assets/js/settings.js');
});
