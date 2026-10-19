<?php
// Modules/Storage/init.php
namespace Modules\Storage;

use Core\EventManager;
use Core\AssetManager;

// Modül JS Varlığını Kuyruğa Al ve Çok Dilli Desteği Enjekte Et
AssetManager::enqueueJs('storage-js', '/taskmanager/Modules/Storage/Assets/js/storage.js');
AssetManager::localizeScript('storage-js', 'STORAGE_I18N', [
    'file_too_large'    => __('Storage::file_too_large'),
    'invalid_extension' => __('Storage::invalid_extension'),
]);

EventManager::listen('file.uploaded', function($fileData) {
    // Dosya yüklendiğinde tetiklenecek kancalar
    return true;
});
