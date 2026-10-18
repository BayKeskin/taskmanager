<?php
require_once __DIR__ . '/../System/System.php';

use Core\EventManager;
use Core\ModuleLoader;
use Core\BaseController;
use Core\BaseModel;
use Core\View;

echo "=== CORE INFRASTRUCTURE TEST ===" . PHP_EOL;

// 1. ModuleLoader Test
assert(class_exists('Core\ModuleLoader'), "ModuleLoader class should exist.");
echo "[PASS] ModuleLoader loaded." . PHP_EOL;

// 2. EventManager Test
$eventFired = false;
$receivedData = null;

EventManager::listen('test.event', function($data) use (&$eventFired, &$receivedData) {
    $eventFired = true;
    $receivedData = $data;
});

EventManager::dispatch('test.event', ['sample' => 123]);
assert($eventFired === true, "EventManager failed to dispatch test.event.");
assert($receivedData['sample'] === 123, "EventManager payload mismatch.");
echo "[PASS] EventManager action dispatch & listen verified." . PHP_EOL;

// 3. Filter Test
EventManager::addFilter('test.filter', function($val) {
    return $val . '_filtered';
});
$filtered = EventManager::applyFilters('test.filter', 'hello');
assert($filtered === 'hello_filtered', "EventManager filter failed.");
echo "[PASS] EventManager filter pipeline verified." . PHP_EOL;

// 4. BaseModel Test
class DummyModel extends BaseModel {
    protected string $table = 'projects';
}
$dummy = new DummyModel();
$projectOne = $dummy->find(1);
assert($projectOne !== null, "BaseModel find() query failed.");
assert(isset($projectOne->title), "BaseModel project object missing title.");
echo "[PASS] BaseModel PDO queries verified on table 'projects' (Found Project: " . $projectOne->title . ")." . PHP_EOL;

// 5. BaseController Test
assert(class_exists('Core\BaseController'), "BaseController class should exist.");
echo "[PASS] BaseController loaded." . PHP_EOL;

// 6. View Resolver Test
$resolvedPath = View::resolveViewPath('SampleModule::test');
assert(str_contains($resolvedPath, 'Modules' . DIRECTORY_SEPARATOR . 'SampleModule' . DIRECTORY_SEPARATOR . 'Views'), "View resolver failed for module path.");
echo "[PASS] View path resolver verified." . PHP_EOL;

echo "=== ALL CORE TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
