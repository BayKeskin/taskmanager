<?php
use Core\EventManager;
use Core\AssetManager;

EventManager::listen('app.init', function() {
    AssetManager::enqueueCss('/Modules/Board/Assets/css/board.css');
    AssetManager::enqueueJs('/Modules/Board/Assets/js/board.js');
});
