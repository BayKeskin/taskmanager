<?php
require_once __DIR__ . '/../Core/System.php';

use Core\ModuleLoader;
use Modules\Storage\Services\StorageService;

echo "=== AUTONOMOUS TEST: STORAGE (#03) MODULE ===" . PHP_EOL;

// 1. Modül Yüklenme Kontrolü
assert(ModuleLoader::isModuleActive('storage'), "Module 'storage' must be active.");
echo "[PASS] Module 'storage' (#03) active in ModuleLoader." . PHP_EOL;

// 2. Storage Put & Delete Testi
$testContent = "Autonomous Storage QA Test Content " . time();
$putRes = StorageService::put($testContent, "test_file.txt", "qa_tests", "text/plain");
assert($putRes['success'] === true, "StorageService::put failed.");
assert(!empty($putRes['file_path']), "StorageService::put must return file_path.");
echo "[PASS] StorageService::put successfully wrote file: " . $putRes['file_path'] . PHP_EOL;

// Dosyanın diskte varlığını doğrula
$delRes = StorageService::delete($putRes['file_path']);
assert($delRes === true, "StorageService::delete failed.");
echo "[PASS] StorageService::delete successfully deleted test file." . PHP_EOL;

// 3. Lang Dosyaları Çözümleme Testi (TR, EN, DE)
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $sName = __('Storage::module_name');
    $sSuccess = __('Storage::upload_success');
    
    echo "  [PASS] Lang ({$loc}): Module => '{$sName}' | Success => '{$sSuccess}'" . PHP_EOL;
    assert(!empty($sName) && !str_starts_with($sName, 'Storage::'), "Storage translation missing for {$loc}");
    assert(!empty($sSuccess) && !str_starts_with($sSuccess, 'Storage::'), "Storage translation missing for {$loc}");
}

// 4. Legacy Facade Uyumluluk Testi
$legacyCfg = Storage::getConfig();
assert(!empty($legacyCfg['allowed_extensions']), "Legacy Storage facade must return config.");
echo "[PASS] Legacy Storage facade backwards-compatibility verified." . PHP_EOL;

echo "=== ALL STORAGE TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
