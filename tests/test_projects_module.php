<?php
require_once __DIR__ . '/../System/System.php';

use Core\ModuleLoader;
use Modules\Projects\Models\ProjectModel;
use Modules\Projects\Controllers\ProjectController as ModuleProjectController;

echo "=== AUTONOMOUS TEST: PROJECTS (#13) MODULE ===" . PHP_EOL;

// 1. Modül Yüklenme Kontrolü
assert(ModuleLoader::isModuleActive('projects'), "Module 'projects' must be active.");
echo "[PASS] Module 'projects' (#13) active in ModuleLoader." . PHP_EOL;

// 2. ProjectModel Testi
$projModel = new ProjectModel();
$projects = $projModel->getProjects();
assert(is_array($projects) && !empty($projects), "ProjectModel::getProjects must return projects.");
echo "[PASS] ProjectModel::getProjects returned " . count($projects) . " projects." . PHP_EOL;

$firstProjectId = (int)(((object)$projects[0])->id);
$singleProj = $projModel->getProjectById($firstProjectId);
assert($singleProj !== null, "ProjectModel::getProjectById must return project object.");
assert((int)$singleProj->id === $firstProjectId, "Project ID must match.");
echo "[PASS] ProjectModel::getProjectById found project: {$singleProj->title} ({$singleProj->project_code})" . PHP_EOL;

$stats = $projModel->getProjectStats();
assert(is_array($stats), "ProjectModel::getProjectStats must return array.");
echo "[PASS] ProjectModel::getProjectStats returned stats for " . count($stats) . " projects." . PHP_EOL;

// 3. Controller Testi
$moduleCtrl = new ModuleProjectController();
$viewData = $moduleCtrl->index();
assert(isset($viewData['projects']) && isset($viewData['stats']), "ProjectController::index structure valid.");
echo "[PASS] ProjectController::index successfully prepared view data." . PHP_EOL;

// 4. Lang Dosyaları Çözümleme Testi (TR, EN, DE)
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $pName = __('Projects::module_name');
    $pTitle = __('Projects::projects_title');
    $pCode = __('Projects::project_code');
    
    echo "  [PASS] Lang ({$loc}): Module => '{$pName}' | Title => '{$pTitle}' | Code => '{$pCode}'" . PHP_EOL;
    assert(!empty($pName) && !str_starts_with($pName, 'Projects::'), "Projects translation missing for {$loc}");
    assert(!empty($pTitle) && !str_starts_with($pTitle, 'Projects::'), "Projects translation missing for {$loc}");
    assert(!empty($pCode) && !str_starts_with($pCode, 'Projects::'), "Projects translation missing for {$loc}");
}

echo "=== ALL PROJECTS TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
