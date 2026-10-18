<?php
// Modules/Storage/init.php
namespace Modules\Storage;

use Core\EventManager;

EventManager::listen('file.uploaded', function($fileData) {
    // Dosya yüklendiğinde tetiklenecek kancalar
    return true;
});
