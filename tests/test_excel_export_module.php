<?php
require_once __DIR__ . '/../Core/System.php';

use Core\ModuleLoader;
use Modules\ExcelExport\Models\ExcelExportModel;
use Modules\ExcelExport\Controllers\ExcelExportController;

echo "=== MODULE TEST: EXCEL EXPORT (HMVC) ===" . PHP_EOL;

// 1. Modülün yüklendiğini doğrula
assert(ModuleLoader::isModuleActive('excel-export'), "Module 'excel-export' should be active.");
echo "[PASS] 'excel-export' registered and active in ModuleLoader." . PHP_EOL;

// 2. Model Testi
$exportModel = new ExcelExportModel();
$tasks = $exportModel->getTasksForExport(1);
assert(is_array($tasks), "getTasksForExport should return array.");
echo "[PASS] ExcelExportModel query executed successfully. Tasks count: " . count($tasks) . PHP_EOL;

// 3. Controller Sınıf Testi
$controller = new ExcelExportController();
assert($controller instanceof \Core\BaseController, "ExcelExportController must extend Core\BaseController.");
echo "[PASS] ExcelExportController extends Core\BaseController properly." . PHP_EOL;

echo "=== ALL EXCEL EXPORT TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
