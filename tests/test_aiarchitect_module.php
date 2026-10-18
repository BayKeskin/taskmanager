<?php
/**
 * Autonomous Verification Test for AiArchitect (#02) Module
 */
require_once __DIR__ . '/../Core/System.php';
require_once __DIR__ . '/../Core/Helper.php';

use Core\ModuleLoader;
use Modules\AiArchitect\Services\GeminiClient;
use Modules\AiArchitect\Controllers\AiArchitectController;

echo "=== AUTONOMOUS TEST: AI ARCHITECT (#02) MODULE ===\n";

// 1. Modül Yükleme Kontrolü
if (ModuleLoader::isModuleActive('aiarchitect')) {
    echo "[PASS] Module 'aiarchitect' (#02) active in ModuleLoader.\n";
} else {
    echo "[FAIL] Module 'aiarchitect' not recognized or inactive.\n";
    exit(1);
}

// 2. GeminiClient Fallback & Spec Generation Testi
$client = new GeminiClient();
$sampleInstruction = "Mobil alt menüye bildirim ikonu ekle ve tıklandığında popup açılsın.";
$specResult = $client->refineTaskSpecification(1, $sampleInstruction, [], [], true);

if (!empty($specResult['success']) && !empty($specResult['specification'])) {
    echo "[PASS] GeminiClient returned valid specification. Engine: " . $specResult['engine'] . "\n";
    if (strpos($specResult['specification'], '1. GÖREV AMACI') !== false) {
        echo "[PASS] Specification contains structured architecture headers.\n";
    } else {
        echo "[WARN] Specification structure differed slightly.\n";
    }
} else {
    echo "[FAIL] GeminiClient failed to generate specification.\n";
    exit(1);
}

// 3. Helper Facade Forwarding Testi
$facadeResult = Helper::refineTaskPromptToSpecification(1, "Test talebi", [], [], true);
if (!empty($facadeResult['success']) && !empty($facadeResult['specification'])) {
    echo "[PASS] Helper::refineTaskPromptToSpecification successfully forwarded to GeminiClient.\n";
} else {
    echo "[FAIL] Helper facade delegation failed.\n";
    exit(1);
}

// 4. Tri-lingual (TR, EN, DE) Translation Testi
Language::setLocale('tr');
$tr = __('AiArchitect::module_name');

Language::setLocale('en');
$en = __('AiArchitect::module_name');

Language::setLocale('de');
$de = __('AiArchitect::module_name');

echo "  [PASS] Lang (tr): '$tr'\n";
echo "  [PASS] Lang (en): '$en'\n";
echo "  [PASS] Lang (de): '$de'\n";

if ($tr === 'AI Görev Mimarı' && $en === 'AI Task Architect' && $de === 'KI-Aufgabenarchitekt') {
    echo "[PASS] All 3 languages properly localized for AiArchitect.\n";
} else {
    echo "[FAIL] Translations do not match expected localized values.\n";
    exit(1);
}

echo "=== ALL AI ARCHITECT TESTS PASSED (100% SUCCESS) ===\n";
