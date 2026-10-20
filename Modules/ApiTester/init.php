<?php
use Core\AssetManager;

AssetManager::enqueueCss('apitester-css', '/Modules/ApiTester/Assets/css/apitester.css');
AssetManager::enqueueJs('apitester-js', '/Modules/ApiTester/Assets/js/apitester.js');

AssetManager::enqueueCss('apitester-live-css', '/Modules/ApiTester/Assets/css/api_test.css');
AssetManager::enqueueJs('apitester-live-js', '/Modules/ApiTester/Assets/js/api_test.js');
