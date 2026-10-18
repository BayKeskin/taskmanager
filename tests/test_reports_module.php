<?php
require_once __DIR__ . '/../System/System.php';
use Core\ModuleLoader;
use Modules\Reports\Models\ReportModel;
use Modules\Reports\Controllers\ReportController as ModuleReportController;

echo "=== AUTONOMOUS TEST: REPORTS (#16) MODULE ===" . PHP_EOL;

// 1. Modül Yüklenme Kontrolü
assert(ModuleLoader::isModuleActive('reports'), "Module 'reports' must be active.");
echo "[PASS] Module 'reports' (#16) active in ModuleLoader." . PHP_EOL;

// 2. ReportModel Testi
$reportModel = new ReportModel();
$summary = $reportModel->getAnalyticsSummary();
assert(isset($summary['totalTasks']), "Analytics summary must include totalTasks.");
assert(isset($summary['completedTasks']), "Analytics summary must include completedTasks.");
echo "[PASS] ReportModel::getAnalyticsSummary returned Total: {$summary['totalTasks']}, Completed: {$summary['completedTasks']} (Rate: {$summary['completionRate']}%)" . PHP_EOL;

$billing = $reportModel->getProjectBillingSummary(1);
assert(isset($billing['total_completed_tasks']), "Billing summary must include total_completed_tasks.");
echo "[PASS] ReportModel::getProjectBillingSummary returned {$billing['total_completed_tasks']} completed tasks, Total: {$billing['total_cost']} TL" . PHP_EOL;

// 3. Controller Testi
$moduleCtrl = new ModuleReportController();
$adminUser = (object)['id' => 1, 'role' => 'admin'];
$viewData = $moduleCtrl->index($adminUser);
assert(isset($viewData['completionRate']), "ReportController::index must include completionRate.");
echo "[PASS] ReportController::index successfully prepared view data." . PHP_EOL;

// 4. Lang Dosyaları Çözümleme Testi (TR, EN, DE)
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $rName = __('Reports::module_name');
    $rTitle = __('Reports::reports_title');
    $rTotal = __('Reports::total_tasks');
    
    echo "  [PASS] Lang ({$loc}): Module => '{$rName}' | Title => '{$rTitle}' | Total => '{$rTotal}'" . PHP_EOL;
    assert(!empty($rName) && !str_starts_with($rName, 'Reports::'), "Reports translation missing for {$loc}");
    assert(!empty($rTitle) && !str_starts_with($rTitle, 'Reports::'), "Reports translation missing for {$loc}");
    assert(!empty($rTotal) && !str_starts_with($rTotal, 'Reports::'), "Reports translation missing for {$loc}");
}

echo "=== ALL REPORTS TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
