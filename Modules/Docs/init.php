<?php
// Modules/Docs/init.php
namespace Modules\Docs;

use Core\EventManager;
use Core\AssetManager;

EventManager::listen('docs.rendered', function() {
    AssetManager::enqueueCss('/Modules/Docs/Assets/css/docs.css');
    AssetManager::enqueueJs('/Modules/Docs/Assets/js/docs.js');
});
