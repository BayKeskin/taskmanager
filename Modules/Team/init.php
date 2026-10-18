<?php
// Modules/Team/init.php
namespace Modules\Team;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('team-styles', '/taskmanager/Modules/Team/Assets/css/team.css');
AssetManager::enqueueJs('team-js', '/taskmanager/Modules/Team/Assets/js/team.js');
