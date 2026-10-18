<?php
require_once __DIR__ . '/../Core/System.php';
require_once __DIR__ . '/../Model/ProjectModel.php';

use Core\ModuleLoader;
use Modules\Mcp\Models\McpModel;
use Modules\Mcp\Controllers\McpController as ModuleMcpController;

echo "=== AUTONOMOUS TEST: MCP (#05) MODULE ===" . PHP_EOL;

// 1. Modül Yüklenme Kontrolü
assert(ModuleLoader::isModuleActive('mcp'), "Module 'mcp' must be active.");
echo "[PASS] Module 'mcp' (#05) active in ModuleLoader." . PHP_EOL;

// 2. McpModel Katalog Testi
$mcpModel = new McpModel();
$catalog = $mcpModel->getCatalog();
assert(is_array($catalog), "McpModel::getCatalog must return array.");
echo "[PASS] McpModel::getCatalog returned " . count($catalog) . " catalog items." . PHP_EOL;

// Proje MCP listesi testi
$projectMcps = $mcpModel->getProjectMcps(1);
assert(is_array($projectMcps), "McpModel::getProjectMcps must return array.");
echo "[PASS] McpModel::getProjectMcps returned " . count($projectMcps) . " project MCP entries." . PHP_EOL;

// 3. Controller Testi
$moduleCtrl = new ModuleMcpController();
$projRes = $moduleCtrl->getProjectMcps(1);
assert($projRes['success'] === true, "McpController::getProjectMcps failed.");
echo "[PASS] McpController::getProjectMcps returned project info: " . ($projRes['project']['title'] ?? 'N/A') . PHP_EOL;

// 4. Lang Dosyaları Çözümleme Testi (TR, EN, DE)
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $mName = __('Mcp::module_name');
    $mTitle = __('Mcp::mcp_title');
    $mSaved = __('Mcp::settings_saved');
    
    echo "  [PASS] Lang ({$loc}): Module => '{$mName}' | Title => '{$mTitle}' | Saved => '{$mSaved}'" . PHP_EOL;
    assert(!empty($mName) && !str_starts_with($mName, 'Mcp::'), "Mcp translation missing for {$loc}");
    assert(!empty($mTitle) && !str_starts_with($mTitle, 'Mcp::'), "Mcp translation missing for {$loc}");
    assert(!empty($mSaved) && !str_starts_with($mSaved, 'Mcp::'), "Mcp translation missing for {$loc}");
}

// 5. Legacy Controller Facade Testi
require_once __DIR__ . '/../Controller/McpController.php';
$legacyCtrl = new \McpController($system ?? null);
$legacyRes = $legacyCtrl->getProjectMcps(1);
assert($legacyRes['success'] === true, "Legacy McpController facade must return identical success status.");
echo "[PASS] Legacy McpController facade backwards-compatibility verified." . PHP_EOL;

echo "=== ALL MCP TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
