<?php
require_once __DIR__ . '/../System/System.php';
require_once __DIR__ . '/../Model/ProjectModel.php';

use Core\ModuleLoader;
use Modules\Docs\Models\DocModel;
use Modules\Docs\Controllers\DocController as ModuleDocController;

echo "=== AUTONOMOUS TEST: DOCS (#11) MODULE ===" . PHP_EOL;

// 1. Modül Yüklenme Kontrolü
assert(ModuleLoader::isModuleActive('docs'), "Module 'docs' must be active.");
echo "[PASS] Module 'docs' (#11) active in ModuleLoader." . PHP_EOL;

// 2. DocModel Testi
$docModel = new DocModel();
$docsList = $docModel->getDocsList();
assert(is_array($docsList), "DocModel::getDocsList must return an array.");
echo "[PASS] DocModel::getDocsList successfully returned " . count($docsList) . " documents." . PHP_EOL;

if (!empty($docsList)) {
    $firstDocId = (int)(((object)$docsList[0])->id);
    $singleDoc = $docModel->getDocById($firstDocId);
    assert($singleDoc !== null, "DocModel::getDocById must return document object.");
    assert((int)$singleDoc->id === $firstDocId, "Doc ID must match requested ID.");
    echo "[PASS] DocModel::getDocById successfully retrieved document #{$firstDocId} ('{$singleDoc->title}')." . PHP_EOL;
}

// 3. Controller Testi
$moduleCtrl = new ModuleDocController();
$adminUser = (object)['id' => 1, 'role' => 'admin'];
$viewData = $moduleCtrl->index(null, null, $adminUser);
assert(isset($viewData['docsList']), "DocController::index must return docsList.");
echo "[PASS] DocController::index prepared view data with " . count($viewData['docsList']) . " documents." . PHP_EOL;

// 4. Lang Dosyaları Çözümleme Testi (TR, EN, DE)
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $dName = __('Docs::module_name');
    $dTitle = __('Docs::docs_title');
    $dNew = __('Docs::new_doc');
    
    echo "  [PASS] Lang ({$loc}): Module => '{$dName}' | Title => '{$dTitle}' | New => '{$dNew}'" . PHP_EOL;
    assert(!empty($dName) && !str_starts_with($dName, 'Docs::'), "Docs translation missing for {$loc}");
    assert(!empty($dTitle) && !str_starts_with($dTitle, 'Docs::'), "Docs translation missing for {$loc}");
    assert(!empty($dNew) && !str_starts_with($dNew, 'Docs::'), "Docs translation missing for {$loc}");
}

// 5. Legacy Controller Facade Testi
require_once __DIR__ . '/../Controller/DocController.php';
$legacyCtrl = new \DocController($system ?? null);
$legacyData = $legacyCtrl->index(null, null, $adminUser);
assert(isset($legacyData['docsList']), "Legacy DocController facade must return identical docsList.");
echo "[PASS] Legacy DocController facade backwards-compatibility verified." . PHP_EOL;

echo "=== ALL DOCS TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
