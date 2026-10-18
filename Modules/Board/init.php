<?php
use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('board-styles', '/taskmanager/Modules/Board/Assets/css/board.css');
AssetManager::enqueueJs('board-js', '/taskmanager/Modules/Board/Assets/js/board.js');
