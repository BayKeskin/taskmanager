<?php
/**
 * Autonomous Verification Test for ApiTester (#09) Module
 */
require_once __DIR__ . '/../System/System.php';

use Core\ModuleLoader;
use Modules\ApiTester\Controllers\ApiTesterController;

echo "=== AUTONOMOUS TEST: API TESTER (#09) MODULE ===\n";

// 1. Modül Yükleme Kontrolü
if (ModuleLoader::isModuleActive('apitester')) {
    echo "[PASS] Module 'apitester' (#09) active in ModuleLoader.\n";
} else {
    echo "[FAIL] Module 'apitester' not recognized or inactive.\n";
    exit(1);
}

// 2. Controller Instance Testi
$controller = new ApiTesterController();
if ($controller instanceof \Core\BaseController) {
    echo "[PASS] ApiTesterController properly instantiated and extends BaseController.\n";
} else {
    echo "[FAIL] ApiTesterController hierarchy mismatch.\n";
    exit(1);
}

// 3. Tri-lingual (TR, EN, DE) Translation Testi
Language::setLocale('tr');
$tr = __('ApiTester::module_name');

Language::setLocale('en');
$en = __('ApiTester::module_name');

Language::setLocale('de');
$de = __('ApiTester::module_name');

echo "  [PASS] Lang (tr): '$tr'\n";
echo "  [PASS] Lang (en): '$en'\n";
echo "  [PASS] Lang (de): '$de'\n";

if ($tr === 'REST API Test Konsolu' && $en === 'REST API Test Console' && $de === 'REST-API-Testkonsole') {
    echo "[PASS] All 3 languages properly localized for ApiTester.\n";
} else {
    echo "[FAIL] Translations do not match expected localized values.\n";
    exit(1);
}

echo "=== ALL API TESTER TESTS PASSED (100% SUCCESS) ===\n";
