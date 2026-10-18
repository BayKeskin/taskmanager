<?php
// Modules/SmokeTest/init.php
namespace Modules\SmokeTest;

use Core\EventManager;

EventManager::listen('system.smoketest', function() {
    $suite = new Services\SmokeTestService();
    return $suite->run();
});
