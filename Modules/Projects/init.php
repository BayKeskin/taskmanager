<?php
// Modules/Projects/init.php
namespace Modules\Projects;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('projects-styles', '/taskmanager/Modules/Projects/Assets/css/projects.css');
AssetManager::enqueueJs('projects-js', '/taskmanager/Modules/Projects/Assets/js/projects.js');
