<?php
use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('settings-styles', '/taskmanager/Modules/Settings/Assets/css/settings.css');
AssetManager::enqueueJs('settings-js', '/taskmanager/Modules/Settings/Assets/js/settings.js');
