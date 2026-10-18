<?php
// Modules/Mcp/init.php
namespace Modules\Mcp;

use Core\EventManager;
use Core\AssetManager;

EventManager::listen('mcp.rendered', function() {
    AssetManager::enqueueCss('/Modules/Mcp/Assets/css/mcp.css');
    AssetManager::enqueueJs('/Modules/Mcp/Assets/js/mcp.js');
});
