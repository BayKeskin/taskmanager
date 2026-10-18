<?php
// Modules/Team/init.php
namespace Modules\Team;

use Core\EventManager;
use Core\AssetManager;

EventManager::listen('team.rendered', function() {
    AssetManager::enqueueCss('/Modules/Team/Assets/css/team.css');
    AssetManager::enqueueJs('/Modules/Team/Assets/js/team.js');
});
