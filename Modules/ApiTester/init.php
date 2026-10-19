<?php
use Core\AssetManager;

AssetManager::enqueueCss('apitester-css', '/taskmanager/Modules/ApiTester/Assets/css/apitester.css');
AssetManager::enqueueJs('apitester-js', '/taskmanager/Modules/ApiTester/Assets/js/apitester.js');

AssetManager::enqueueCss('apitester-live-css', '/taskmanager/Modules/ApiTester/Assets/css/api_test.css');
AssetManager::enqueueJs('apitester-live-js', '/taskmanager/Modules/ApiTester/Assets/js/api_test.js');
