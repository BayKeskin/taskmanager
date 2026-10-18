<?php
/**
 * Autonomous Verification Test for RestApi (#08) Module
 */
require_once __DIR__ . '/../Core/System.php';

use Core\ModuleLoader;
use Modules\RestApi\Services\RestApiService;

echo "=== AUTONOMOUS TEST: REST API (#08) MODULE ===\n";

// 1. Modül Yükleme Kontrolü
if (ModuleLoader::isModuleActive('restapi')) {
    echo "[PASS] Module 'restapi' (#08) active in ModuleLoader.\n";
} else {
    echo "[FAIL] Module 'restapi' not recognized or inactive.\n";
    exit(1);
}

// 2. RestApiService Yetkilendirme Testi
$apiService = new RestApiService();

// Test A: Header olmadan yetkilendirme (Başarısız dönmeli)
unset($_SERVER['HTTP_AUTHORIZATION']);
unset($_SERVER['HTTP_X_API_KEY']);
$unauth = $apiService->authenticate();
if ($unauth === null) {
    echo "[PASS] RestApiService correctly rejected request without token.\n";
} else {
    echo "[FAIL] RestApiService should reject unauthenticated request.\n";
    exit(1);
}

// Test B: Bearer token ile yetkilendirme (Başarılı dönmeli)
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tm_admin_talha_keskin_token_2026';
$auth = $apiService->authenticate();
if ($auth !== null && !empty($auth->email)) {
    echo "[PASS] RestApiService authenticated admin token. User: {$auth->name} ({$auth->email})\n";
} else {
    echo "[FAIL] RestApiService failed to authenticate valid admin token.\n";
    exit(1);
}

// 3. Response Formatlama Testi
$formatted = $apiService->formatResponse(['total' => 5], 'Success', 200);
if ($formatted['success'] === true && $formatted['data']['total'] === 5) {
    echo "[PASS] RestApiService::formatResponse returned structured JSON payload.\n";
} else {
    echo "[FAIL] RestApiService format mismatch.\n";
    exit(1);
}

// 4. Tri-lingual (TR, EN, DE) Translation Testi
Language::setLocale('tr');
$tr = __('RestApi::module_name');

Language::setLocale('en');
$en = __('RestApi::module_name');

Language::setLocale('de');
$de = __('RestApi::module_name');

echo "  [PASS] Lang (tr): '$tr'\n";
echo "  [PASS] Lang (en): '$en'\n";
echo "  [PASS] Lang (de): '$de'\n";

if ($tr === 'Enterprise REST API' && $en === 'Enterprise REST API' && $de === 'Enterprise REST-API') {
    echo "[PASS] All 3 languages properly localized for RestApi.\n";
} else {
    echo "[FAIL] Translations do not match expected localized values.\n";
    exit(1);
}

echo "=== ALL REST API TESTS PASSED (100% SUCCESS) ===\n";
