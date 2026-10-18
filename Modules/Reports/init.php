<?php
// Modules/Reports/init.php
namespace Modules\Reports;

use Core\EventManager;
use Core\AssetManager;

EventManager::listen('reports.rendered', function() {
    AssetManager::enqueueCss('/Modules/Reports/Assets/css/reports.css');
    AssetManager::enqueueJs('/Modules/Reports/Assets/js/reports.js');
});
