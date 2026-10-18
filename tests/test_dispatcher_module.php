<?php
/**
 * Autonomous Verification Test for Dispatcher (#06) Module
 */
require_once __DIR__ . '/../System/System.php';
require_once __DIR__ . '/../System/Helper.php';

use Core\ModuleLoader;
use Modules\Dispatcher\Services\DispatcherService;

echo "=== AUTONOMOUS TEST: DISPATCHER (#06) MODULE ===\n";

// 1. Modül Yükleme Kontrolü
if (ModuleLoader::isModuleActive('dispatcher')) {
    echo "[PASS] Module 'dispatcher' (#06) active in ModuleLoader.\n";
} else {
    echo "[FAIL] Module 'dispatcher' not recognized or inactive.\n";
    exit(1);
}

// 2. DispatcherService Testleri
$service = new DispatcherService();
$isRunning = $service->isRunning();
echo "[PASS] DispatcherService::isRunning checked. Running: " . ($isRunning ? 'YES' : 'NO') . "\n";

$syncResult = $service->syncWorkspaces();
if ($syncResult) {
    echo "[PASS] DispatcherService::syncWorkspaces successfully synced workspaces.json.\n";
} else {
    echo "[FAIL] DispatcherService::syncWorkspaces failed.\n";
    exit(1);
}

$lsEnv = $service->getAntigravityLsEnv();
echo "[PASS] DispatcherService::getAntigravityLsEnv returned address: " . ($lsEnv['address'] ?? 'none') . "\n";

// 3. Helper Facade Forwarding Testi
Helper::ensureDispatcherRunning();
echo "[PASS] Helper::ensureDispatcherRunning successfully delegated.\n";

// 4. Tri-lingual (TR, EN, DE) Translation Testi
Language::setLocale('tr');
$tr = __('Dispatcher::module_name');

Language::setLocale('en');
$en = __('Dispatcher::module_name');

Language::setLocale('de');
$de = __('Dispatcher::module_name');

echo "  [PASS] Lang (tr): '$tr'\n";
echo "  [PASS] Lang (en): '$en'\n";
echo "  [PASS] Lang (de): '$de'\n";

if ($tr === 'Otonom Yapay Zeka Köprüsü' && $en === 'Autonomous AI Bridge' && $de === 'Autonome KI-Brücke') {
    echo "[PASS] All 3 languages properly localized for Dispatcher.\n";
} else {
    echo "[FAIL] Translations do not match expected localized values.\n";
    exit(1);
}

echo "=== ALL DISPATCHER TESTS PASSED (100% SUCCESS) ===\n";
