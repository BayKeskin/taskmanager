<?php
// Modules/Reports/init.php
namespace Modules\Reports;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('reports-styles', '/Modules/Reports/Assets/css/reports.css');
AssetManager::enqueueJs('reports-js', '/Modules/Reports/Assets/js/reports.js');

