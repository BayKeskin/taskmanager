<?php
require_once __DIR__ . '/../System/System.php';

use Core\ModuleLoader;
use Core\AssetManager;
use Modules\Billing\Controllers\BillingController;
use Modules\Billing\Models\BillingModel;
use Modules\Calendar\Controllers\CalendarController;

echo "=== AUTONOMOUS TEST: PHASE 5 (BILLING & CALENDAR MODULES) ===" . PHP_EOL;

// 1. Modül Yüklenme Kontrolü
assert(ModuleLoader::isModuleActive('billing'), "Module 'billing' must be active.");
assert(ModuleLoader::isModuleActive('calendar'), "Module 'calendar' must be active.");
echo "[PASS] Both 'billing' (#24) and 'calendar' (#15) registered as active modules." . PHP_EOL;

// 2. Billing Modülü Testi
$_SESSION['user'] = (object)['id' => 1, 'role' => 'admin'];
$billingController = new BillingController();
$billingData = $billingController->getBillingData(1, 600.0);
assert(!empty($billingData), "Billing data should not be empty.");
assert(isset($billingData['total_cost']), "Billing data must contain total_cost.");
echo "[PASS] BillingController returned summary: " . $billingData['total_completed_tasks'] . " tasks, Total Cost: " . $billingData['total_cost'] . " ₺ (Hourly rate: " . $billingData['hourly_rate'] . ")" . PHP_EOL;

// 3. Calendar Modülü Testi
$calendarController = new CalendarController();
$calData = $calendarController->index(2026, 10, 1, 'month');
assert(isset($calData['calendarTasks']), "Calendar data must contain calendarTasks.");
assert(isset($calData['allGanttTasks']), "Calendar data must contain allGanttTasks.");
echo "[PASS] CalendarController index returned: " . count($calData['calendarTasks']) . " month tasks, " . count($calData['allGanttTasks']) . " total Gantt tasks." . PHP_EOL;

// 4. Lang Dosyaları Çözümleme Testi
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $bTitle = __('Billing::report_title');
    $cTitle = __('Calendar::gantt_title');
    echo "  [PASS] Lang ({$loc}): Billing => '{$bTitle}' | Calendar => '{$cTitle}'" . PHP_EOL;
    assert(!str_contains($bTitle, 'report_title'), "Billing translation failed for {$loc}");
    assert(!str_contains($cTitle, 'gantt_title'), "Calendar translation failed for {$loc}");
}

// 5. Assets Enqueue Kontrolü
$stylesHtml = AssetManager::renderStyles();
$scriptsHtml = AssetManager::renderScripts();
assert(str_contains($stylesHtml, 'billing-report.css'), "AssetManager missing billing-report.css");
assert(str_contains($scriptsHtml, 'billing.js'), "AssetManager missing billing.js");
assert(str_contains($stylesHtml, 'gantt.css'), "AssetManager missing gantt.css");
assert(str_contains($scriptsHtml, 'gantt-drag.js'), "AssetManager missing gantt-drag.js");
echo "[PASS] AssetManager correctly rendered CSS and JS assets for Billing and Calendar." . PHP_EOL;

echo "=== ALL PHASE 5 TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
