<?php
/**
 * Autonomous verification test for TSK-1084 (DeltaSyncEngine, Git Diff, Live Migrations, API, AJAX)
 */
require_once __DIR__ . '/../Core/System.php';

echo "=== TSK-1084 AUTONOMOUS TEST SUITE ===\n";

// 1. DeltaSyncEngine Class & Schema test
DeltaSyncEngine::ensureSchema();
echo "[PASS] 1. DeltaSyncEngine schema ensured.\n";

// 2. Git Diff Test (Full vs Delta)
$workspace = realpath(__DIR__ . '/..');
$diffFull = DeltaSyncEngine::calculateGitDiff($workspace, null, 'HEAD');
assert(!empty($diffFull['success']), "calculateGitDiff full should succeed");
assert($diffFull['is_full'] === true, "calculateGitDiff should be full deployment");
assert($diffFull['total_changes'] > 0, "calculateGitDiff should find tracked files");
echo "[PASS] 2.1 Git Diff full files count: " . $diffFull['total_changes'] . "\n";

$headCommit = trim(shell_exec("cd /d \"{$workspace}\" && git rev-parse HEAD") ?: '');
$prevCommit = trim(shell_exec("cd /d \"{$workspace}\" && git rev-parse HEAD~1") ?: '');
if (!empty($headCommit) && !empty($prevCommit)) {
    $diffDelta = DeltaSyncEngine::calculateGitDiff($workspace, $prevCommit, $headCommit);
    assert(!empty($diffDelta['success']), "calculateGitDiff delta should succeed");
    assert($diffDelta['is_full'] === false, "calculateGitDiff should be delta");
    echo "[PASS] 2.2 Git Diff delta: Added=" . count($diffDelta['added']) . ", Mod=" . count($diffDelta['modified']) . ", Del=" . count($diffDelta['deleted']) . "\n";
}

// 3. Pending Migrations Detection
$pendingMigs = DeltaSyncEngine::detectPendingMigrations($workspace, null, 'HEAD');
assert(is_array($pendingMigs), "detectPendingMigrations should return array");
echo "[PASS] 3. Detected pending migrations count: " . count($pendingMigs) . "\n";

// 4. Live DB Migrations Runner Test
global $db;
$testMigrations = [
    [
        'file' => '.migrations/test_tsk1084_fixture.sql',
        'name' => 'test_tsk1084_fixture',
        'sql'  => 'CREATE TABLE IF NOT EXISTS `_test_tsk1084_fixture` (`id` INT AUTO_INCREMENT PRIMARY KEY, `col` VARCHAR(50));'
    ]
];
$migRes = DeltaSyncEngine::applyLiveMigrations($db->connect, $testMigrations);
assert($migRes['success'] === true, "applyLiveMigrations should succeed");
echo "[PASS] 4.1 Live DB migration applied successfully.\n";

// Re-apply to verify idempotency (should skip already applied)
$migRes2 = DeltaSyncEngine::applyLiveMigrations($db->connect, $testMigrations);
assert($migRes2['success'] === true, "applyLiveMigrations second run should succeed");
assert(in_array('.migrations/test_tsk1084_fixture.sql', $migRes2['skipped']), "Should skip duplicate migration");
echo "[PASS] 4.2 Migration idempotency verified (skipped duplicate).\n";

// Cleanup fixture table and migration record
$db->connect->exec("DROP TABLE IF EXISTS `_test_tsk1084_fixture`;");
$db->connect->exec("DELETE FROM `taskmanager_schema_migrations` WHERE migration_file = '.migrations/test_tsk1084_fixture.sql';");

// 5. Deployment Record & History Test
$depId = DeltaSyncEngine::recordDeployment(1, [
    'deployment_type' => 'delta',
    'from_commit'     => $prevCommit ?: '0000000000000000000000000000000000000000',
    'to_commit'       => $headCommit ?: '1111111111111111111111111111111111111111',
    'status'          => 'completed',
    'files_uploaded'  => 5,
    'files_deleted'   => 1,
    'migrations_run'  => 1,
    'details'         => ['test' => true],
    'deployed_by'     => 1
]);
assert($depId > 0, "Deployment ID should be positive");
$lastDep = DeltaSyncEngine::getLastDeployment(1);
assert($lastDep !== null && $lastDep->id === $depId, "getLastDeployment should return recorded deployment");
echo "[PASS] 5. Deployment record and retrieval verified: ID={$depId}\n";

// Cleanup test deployment
$db->connect->exec("DELETE FROM `project_deployments` WHERE id = {$depId}");

// 6. REST API Endpoint Tests (/api/projects/01/hosting/delta-diff & /deploy)
$token = 'tm_admin_talha_keskin_token_2026';

// 6.1 Delta Diff API
$ch = curl_init('http://localhost/taskmanager/api/projects/01/hosting/delta-diff');
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}", "Accept-Language: tr"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$apiResp = json_decode(curl_exec($ch), true);
curl_close($ch);
assert(!empty($apiResp['success']), "REST API delta-diff should return success");
assert(isset($apiResp['data']['diff']), "REST API delta-diff should include diff");
echo "[PASS] 6.1 REST API /hosting/delta-diff returned success.\n";

// 6.2 Deploy Record API
$ch = curl_init('http://localhost/taskmanager/api/projects/01/hosting/deploy');
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}", "Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'deployment_type' => 'delta',
    'to_commit'       => $headCommit,
    'files_uploaded'  => 2,
    'files_deleted'   => 0,
    'migrations_run'  => 0,
    'details'         => ['source' => 'api_test']
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$deployResp = json_decode(curl_exec($ch), true);
curl_close($ch);
assert(!empty($deployResp['success']), "REST API /hosting/deploy should return success");
$apiDepId = $deployResp['data']['deployment_id'] ?? 0;
assert($apiDepId > 0, "Created deployment ID should be > 0");
echo "[PASS] 6.2 REST API /hosting/deploy created deployment ID: {$apiDepId}\n";
$db->connect->exec("DELETE FROM `project_deployments` WHERE id = {$apiDepId}");

// 7. Multi-lingual verification
$languages = ['tr', 'en', 'de'];
foreach ($languages as $lang) {
    $ch = curl_init('http://localhost/taskmanager/api/projects/01/hosting/delta-diff');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}", "Accept-Language: {$lang}"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $langResp = json_decode(curl_exec($ch), true);
    curl_close($ch);
    assert(!empty($langResp['success']), "Lang {$lang} request should succeed");
    assert(!empty($langResp['message']), "Lang {$lang} should have localized message");
    echo "[PASS] 7. Localized response for '{$lang}': {$langResp['message']}\n";
}

echo "\n=== ALL TSK-1084 AUTONOMOUS TESTS PASSED PERFECTLY! ===\n";
