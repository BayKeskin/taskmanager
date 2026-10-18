<?php
require_once __DIR__ . '/../Core/System.php';

use Core\ModuleLoader;

echo "=== AUTONOMOUS TEST: PHASE 7 (MODULE MANAGER UI & TOGGLE ENGINE) ===" . PHP_EOL;

// 1. Modül Listesi Okuma Testi
$allModules = ModuleLoader::getAllAvailableModules();
assert(!empty($allModules), "ModuleLoader::getAllAvailableModules() must not be empty.");
echo "[PASS] Successfully discovered " . count($allModules) . " modules via ModuleLoader." . PHP_EOL;

// 2. Modül Durum Değiştirme (setModuleStatus) Testi
$targetSlug = 'telegram';
$initialStatus = ModuleLoader::isModuleActive($targetSlug);

// Pasife al
$disableRes = ModuleLoader::setModuleStatus($targetSlug, false);
assert($disableRes === true, "ModuleLoader::setModuleStatus to false failed.");

// Kontrol et
$allAfterDisable = ModuleLoader::getAllAvailableModules();
assert(isset($allAfterDisable[$targetSlug]), "Target module must exist in list.");
assert($allAfterDisable[$targetSlug]['is_active'] === false, "Target module should be marked inactive.");
echo "[PASS] Successfully disabled module '{$targetSlug}' via setModuleStatus." . PHP_EOL;

// Tekrar aktife al
$enableRes = ModuleLoader::setModuleStatus($targetSlug, true);
assert($enableRes === true, "ModuleLoader::setModuleStatus to true failed.");

$allAfterEnable = ModuleLoader::getAllAvailableModules();
assert($allAfterEnable[$targetSlug]['is_active'] === true, "Target module should be marked active.");
echo "[PASS] Successfully re-enabled module '{$targetSlug}' via setModuleStatus." . PHP_EOL;

// 3. SettingsController::index Veri Doğrulama Testi
require_once __DIR__ . '/../Controller/SettingsController.php';
$settingsCtrl = new SettingsController($system ?? null);
$adminUser = (object)['id' => 1, 'role' => 'admin'];
$viewData = $settingsCtrl->index($adminUser);

assert(isset($viewData['availableModules']), "SettingsController::index must include 'availableModules'.");
assert(is_array($viewData['availableModules']), "'availableModules' must be an array.");
assert(count($viewData['availableModules']) === count($allModules), "Module count in viewData must match loaded modules.");
echo "[PASS] SettingsController provides valid 'availableModules' array for View." . PHP_EOL;

// 4. Tri-Lingual (TR, EN, DE) Çeviri Doğrulama Testi
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $tabText = __('app.settings_tab_modules');
    $titleText = __('app.settings_modules_title');
    $badgeText = __('app.settings_modules_active_badge');
    
    echo "  [PASS] Lang ({$loc}): tab => '{$tabText}' | title => '{$titleText}' | badge => '{$badgeText}'" . PHP_EOL;
    assert(!empty($tabText) && !str_starts_with($tabText, 'app.'), "Missing settings_tab_modules translation for {$loc}");
    assert(!empty($titleText) && !str_starts_with($titleText, 'app.'), "Missing settings_modules_title translation for {$loc}");
    assert(!empty($badgeText) && !str_starts_with($badgeText, 'app.'), "Missing settings_modules_active_badge translation for {$loc}");
}

echo "=== ALL PHASE 7 TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
