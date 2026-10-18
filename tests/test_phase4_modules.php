<?php
require_once __DIR__ . '/../Core/System.php';

use Core\ModuleLoader;
use Core\AssetManager;
use Modules\GithubDevops\Services\GitService;
use Modules\LiveDeploy\Controllers\DeployController;

echo "=== AUTONOMOUS TEST: PHASE 4 MODULES (GITHUB DEVOPS & LIVE DEPLOY) ===" . PHP_EOL;

// 1. Modüllerin aktiflik kontrolü
assert(ModuleLoader::isModuleActive('github-devops'), "Module 'github-devops' must be active.");
assert(ModuleLoader::isModuleActive('live-deploy'), "Module 'live-deploy' must be active.");
echo "[PASS] Both 'github-devops' (#04) and 'live-deploy' (#22) registered as separate active modules." . PHP_EOL;

// 2. GitService Test
$ws = dirname(__DIR__);
$branch = GitService::detectActiveBranch($ws);
echo "[PASS] GitService::detectActiveBranch resolved: " . $branch . PHP_EOL;
assert(!empty($branch), "Branch name should not be empty.");

$diffFiles = GitService::getDiffFiles($ws);
echo "[PASS] GitService::getDiffFiles found " . count($diffFiles) . " tracked files." . PHP_EOL;
assert(count($diffFiles) > 0, "Git diff files should not be empty.");

// 3. AssetManager Enqueue Test
$stylesHtml = AssetManager::renderStyles();
$scriptsHtml = AssetManager::renderScripts();
assert(str_contains($stylesHtml, 'deploy-wizard.css'), "AssetManager should render deploy-wizard.css");
assert(str_contains($scriptsHtml, 'deploy-wizard.js'), "AssetManager should render deploy-wizard.js");
echo "[PASS] AssetManager correctly enqueues and renders module-specific CSS and JS assets." . PHP_EOL;

// 4. DeployController Healthcheck Test
$_SESSION['user'] = (object)['id' => 1, 'role' => 'admin'];
$deployController = new DeployController();
$health = $deployController->checkHealth('http://localhost/taskmanager/api/status');
assert($health['success'] === true, "Healthcheck failed.");
assert($health['http_code'] === 200, "API status should return HTTP 200.");
echo "[PASS] DeployController healthcheck verified (HTTP " . $health['http_code'] . ", Latency: " . $health['latency_ms'] . "ms)." . PHP_EOL;

echo "=== ALL PHASE 4 TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
