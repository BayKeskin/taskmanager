<?php
require_once __DIR__ . '/../System/System.php';
require_once __DIR__ . '/../Model/ProjectModel.php';
require_once __DIR__ . '/../Model/TaskModel.php';

use Core\ModuleLoader;
use Modules\Team\Models\UserModel;
use Modules\Team\Controllers\TeamController as ModuleTeamController;

echo "=== AUTONOMOUS TEST: TEAM (#14) MODULE ===" . PHP_EOL;

// 1. Modül Yüklenme Kontrolü
assert(ModuleLoader::isModuleActive('team'), "Module 'team' must be active.");
echo "[PASS] Module 'team' (#14) active in ModuleLoader." . PHP_EOL;

// 2. UserModel Testi
$userModel = new UserModel();
$users = $userModel->getAllUsers();
assert(is_array($users) && !empty($users), "UserModel::getAllUsers must return users.");
echo "[PASS] UserModel::getAllUsers returned " . count($users) . " users." . PHP_EOL;

$firstUserId = (int)(((object)$users[0])->id);
$singleUser = $userModel->getUserById($firstUserId);
assert($singleUser !== null, "UserModel::getUserById must return user.");
echo "[PASS] UserModel::getUserById found user: " . $singleUser->name . " (Role: {$singleUser->role})" . PHP_EOL;

// 3. Controller Testi
$moduleCtrl = new ModuleTeamController();
$adminUser = (object)['id' => 1, 'role' => 'admin'];
$viewData = $moduleCtrl->index($adminUser);
assert($viewData['isAuthorized'] === true, "Admin user must be authorized.");
assert(isset($viewData['usersList']), "viewData must have usersList.");
echo "[PASS] TeamController::index successfully prepared view data for admin." . PHP_EOL;

// 4. Lang Dosyaları Çözümleme Testi (TR, EN, DE)
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $tName = __('Team::module_name');
    $tTitle = __('Team::team_title');
    $tRole = __('Team::role_admin');
    
    echo "  [PASS] Lang ({$loc}): Module => '{$tName}' | Title => '{$tTitle}' | Role => '{$tRole}'" . PHP_EOL;
    assert(!empty($tName) && !str_starts_with($tName, 'Team::'), "Team translation missing for {$loc}");
    assert(!empty($tTitle) && !str_starts_with($tTitle, 'Team::'), "Team translation missing for {$loc}");
    assert(!empty($tRole) && !str_starts_with($tRole, 'Team::'), "Team translation missing for {$loc}");
}

// 5. Legacy Controller Facade Testi
require_once __DIR__ . '/../Controller/TeamController.php';
$legacyCtrl = new \TeamController($system ?? null);
$legacyData = $legacyCtrl->index($adminUser);
assert(count($legacyData['usersList']) === count($viewData['usersList']), "Legacy TeamController facade must return identical users count.");
echo "[PASS] Legacy TeamController facade backwards-compatibility verified." . PHP_EOL;

echo "=== ALL TEAM TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
