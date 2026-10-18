<?php
// Modules/Mcp/init.php
namespace Modules\Mcp;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('mcp-styles', '/taskmanager/Modules/Mcp/Assets/css/mcp.css');
AssetManager::enqueueJs('mcp-catalog', '/taskmanager/Modules/Mcp/Assets/js/mcp.js');

