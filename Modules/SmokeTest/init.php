<?php
// Modules/SmokeTest/init.php
namespace Modules\SmokeTest;

use Core\EventManager;
use Core\AssetManager;

// Varlıkları ve İstemci Çevirilerini Kaydet
AssetManager::enqueueCss('smoketest-css', '/taskmanager/Modules/SmokeTest/Assets/css/smoketest.css');
AssetManager::enqueueJs('smoketest-js', '/taskmanager/Modules/SmokeTest/Assets/js/smoketest.js');
AssetManager::localizeScript('smoketest-js', 'SMOKE_I18N', [

    'running_tests' => __('SmokeTest::running_tests'),
    'all_passed'    => __('SmokeTest::all_passed'),
    'some_failed'   => __('SmokeTest::some_failed')
]);

EventManager::listen('system.smoketest', function() {
    $suite = new Services\SmokeTestService();
    return $suite->run();
});

