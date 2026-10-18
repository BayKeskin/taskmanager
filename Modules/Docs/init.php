<?php
// Modules/Docs/init.php
namespace Modules\Docs;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('quill-snow', '/taskmanager/Modules/Docs/Assets/css/quill.snow.css');
AssetManager::enqueueCss('docs-styles', '/taskmanager/Modules/Docs/Assets/css/docs.css');
AssetManager::enqueueJs('marked-js', '/taskmanager/Modules/Docs/Assets/js/marked.min.js');
AssetManager::enqueueJs('quill-js', '/taskmanager/Modules/Docs/Assets/js/quill.min.js');
AssetManager::enqueueJs('docs-js', '/taskmanager/Modules/Docs/Assets/js/docs.js');

