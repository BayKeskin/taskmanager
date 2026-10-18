<?php
// Modules/Docs/init.php
namespace Modules\Docs;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('docs-styles', '/taskmanager/Modules/Docs/Assets/css/docs.css');
AssetManager::enqueueJs('docs-js', '/taskmanager/Modules/Docs/Assets/js/docs.js');
