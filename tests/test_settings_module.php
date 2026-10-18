<?php
/**
 * Autonomous Verification Test for Settings (#17) Module
 */
require_once __DIR__ . '/../System/System.php';
use Core\ModuleLoader;
use Modules\Settings\Models\SettingsModel;
use Modules\Settings\Controllers\SettingsController;

echo "=== AUTONOMOUS TEST: SETTINGS (#17) MODULE ===\n";

// 1. Modül Yükleme Kontrolü
if (ModuleLoader::isModuleActive('settings')) {
    echo "[PASS] Module 'settings' (#17) active in ModuleLoader.\n";
} else {
    echo "[FAIL] Module 'settings' not recognized or inactive.\n";
    exit(1);
}

// 2. SettingsModel Testi
$model = new SettingsModel();
$settings = $model->getSettings();
if ($settings !== null && !empty($settings->title)) {
    echo "[PASS] SettingsModel::getSettings loaded title: '{$settings->title}'\n";
} else {
    echo "[FAIL] SettingsModel failed to return settings object.\n";
    exit(1);
}

// 3. SettingsController Testi
$controller = new SettingsController();
$data = $controller->index();
if (!empty($data['s']) && isset($data['availableModules'])) {
    echo "[PASS] SettingsController::index prepared data with " . count($data['availableModules']) . " available modules.\n";
} else {
    echo "[FAIL] SettingsController failed to prepare data.\n";
    exit(1);
}

// 5. Tri-lingual (TR, EN, DE) Translation Testi
Language::setLocale('tr');
$tr = __('Settings::module_name');

Language::setLocale('en');
$en = __('Settings::module_name');

Language::setLocale('de');
$de = __('Settings::module_name');

echo "  [PASS] Lang (tr): '$tr'\n";
echo "  [PASS] Lang (en): '$en'\n";
echo "  [PASS] Lang (de): '$de'\n";

if ($tr === 'Sistem Ayarları' && $en === 'System Settings' && $de === 'Systemeinstellungen') {
    echo "[PASS] All 3 languages properly localized for Settings.\n";
} else {
    echo "[FAIL] Translations do not match expected localized values.\n";
    exit(1);
}

echo "=== ALL SETTINGS TESTS PASSED (100% SUCCESS) ===\n";
