<?php
// Modules/Projects/init.php
namespace Modules\Projects;

use Core\EventManager;
use Core\AssetManager;

EventManager::listen('projects.rendered', function() {
    AssetManager::enqueueCss('/Modules/Projects/Assets/css/projects.css');
    AssetManager::enqueueJs('/Modules/Projects/Assets/js/projects.js');
});
