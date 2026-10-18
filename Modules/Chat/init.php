<?php
// Modules/Chat/init.php
namespace Modules\Chat;

use Core\EventManager;
use Core\AssetManager;

// Chat sayfası görüntülendiğinde modülün static varlıklarını dahil et
EventManager::listen('chat.rendered', function() {
    AssetManager::enqueueCss('/Modules/Chat/Assets/css/chat.css');
    AssetManager::enqueueJs('/Modules/Chat/Assets/js/chat.js');
});
