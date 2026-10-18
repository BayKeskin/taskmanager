<?php
// Modules/Chat/init.php
namespace Modules\Chat;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('chat-styles', '/taskmanager/Modules/Chat/Assets/css/chat.css');
AssetManager::enqueueJs('chat-js', '/taskmanager/Modules/Chat/Assets/js/chat.js');
