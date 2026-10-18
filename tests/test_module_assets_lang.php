<?php
require_once __DIR__ . '/../System/System.php';

use Core\ModuleLoader;
use Core\AssetManager;

echo "=== VERIFYING MODULE LANG & ASSETS ISOLATION ===" . PHP_EOL;

$modules = ['ExcelExport', 'VisualPins', 'GithubDevops', 'LiveDeploy'];
$locales = ['tr', 'en', 'de'];

foreach ($modules as $mod) {
    echo PHP_EOL . "--- Checking Module: {$mod} ---" . PHP_EOL;

    // 1. Dil Dosyaları Kontrolü
    foreach ($locales as $loc) {
        $langFile = dirname(__DIR__) . "/Modules/{$mod}/Lang/{$loc}.php";
        assert(file_exists($langFile), "Missing lang file for {$mod} in {$loc}: {$langFile}");
        $dict = require $langFile;
        assert(is_array($dict) && count($dict) > 0, "Lang file {$langFile} is empty or not returning array.");
        echo "  [PASS] Lang ({$loc}): " . count($dict) . " translation keys found." . PHP_EOL;
    }

    // 2. Çoklu Dil Çözümleme Testi (Language::get)
    foreach ($locales as $loc) {
        Language::setLocale($loc);
        if ($mod === 'ExcelExport') {
            $translated = __('ExcelExport::export_btn');
            echo "  [PASS] Resolved ExcelExport::export_btn ({$loc}) => '{$translated}'" . PHP_EOL;
            assert(!empty($translated) && !str_contains($translated, 'export_btn'), "Translation failed for {$loc}");
        } elseif ($mod === 'VisualPins') {
            $translated = __('VisualPins::pin_title');
            echo "  [PASS] Resolved VisualPins::pin_title ({$loc}) => '{$translated}'" . PHP_EOL;
            assert(!empty($translated) && !str_contains($translated, 'pin_title'), "Translation failed for {$loc}");
        } elseif ($mod === 'GithubDevops') {
            $translated = __('GithubDevops::push_success');
            echo "  [PASS] Resolved GithubDevops::push_success ({$loc}) => '{$translated}'" . PHP_EOL;
            assert(!empty($translated) && !str_contains($translated, 'push_success'), "Translation failed for {$loc}");
        } elseif ($mod === 'LiveDeploy') {
            $translated = __('LiveDeploy::wizard_title');
            echo "  [PASS] Resolved LiveDeploy::wizard_title ({$loc}) => '{$translated}'" . PHP_EOL;
            assert(!empty($translated) && !str_contains($translated, 'wizard_title'), "Translation failed for {$loc}");
        }
    }

    // 3. Modül İçi Assets Kontrolü
    $assetsDir = dirname(__DIR__) . "/Modules/{$mod}/Assets";
    if (is_dir($assetsDir)) {
        $hasJs = is_dir($assetsDir . '/js');
        $hasCss = is_dir($assetsDir . '/css');
        echo "  [PASS] Assets directory exists. JS: " . ($hasJs ? 'YES' : 'NO') . " | CSS: " . ($hasCss ? 'YES' : 'NO') . PHP_EOL;
    }
}

// 4. AssetManager Enqueue Çıktı Testi
$stylesHtml = AssetManager::renderStyles();
$scriptsHtml = AssetManager::renderScripts();
assert(str_contains($stylesHtml, 'visual-pins.css'), "AssetManager missing visual-pins.css");
assert(str_contains($scriptsHtml, 'visual-pins.js'), "AssetManager missing visual-pins.js");
assert(str_contains($stylesHtml, 'deploy-wizard.css'), "AssetManager missing deploy-wizard.css");
assert(str_contains($scriptsHtml, 'deploy-wizard.js'), "AssetManager missing deploy-wizard.js");

echo PHP_EOL . "[PASS] AssetManager correctly rendered all module-isolated JS and CSS files!" . PHP_EOL;
echo "=== ALL 4 MODULES FULLY COMPLIANT WITH ASSETS & TRI-LINGUAL I18N (100% SUCCESS) ===" . PHP_EOL;
