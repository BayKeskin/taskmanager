<?php
/**
 * Autonomous QA Verification for Hosting Panel Adapters (TSK-1082)
 */
require_once __DIR__ . '/../System/System.php';

echo "=== 1. TESTING ADAPTER FACTORY & INTERFACES ===" . PHP_EOL;

$types = ['cpanel', 'plesk', 'cyberpanel', 'standard'];
foreach ($types as $type) {
    $adapter = HostingManager::createAdapter($type, ['host' => 'localhost']);
    assert($adapter instanceof HostingAdapterInterface, "Adapter for {$type} does not implement HostingAdapterInterface");
    assert($adapter->getType() === $type, "Adapter type mismatch for {$type}");
    echo "[PASS] Adapter Factory for '{$type}' successfully verified" . PHP_EOL;
}

echo "=== 2. TESTING STANDARD / FALLBACK HOSTING ADAPTER (LOCAL MYSQL) ===" . PHP_EOL;
$standardAdapter = HostingManager::createAdapter('standard', [
    'host'     => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'taskmanager_db'
]);

$connTest = $standardAdapter->testConnection();
assert($connTest['success'] === true, "Standard connection test failed: " . ($connTest['message'] ?? ''));
assert($connTest['data']['database'] === 'taskmanager_db', "Database name mismatch in connection test");
assert($connTest['data']['table_count'] > 0, "Table count should be greater than 0");
echo "[PASS] StandardHostingAdapter live connection test passed: " . $connTest['data']['server_version'] . PHP_EOL;

// Test provisioning a test database via Standard adapter
$testDbName = 'tm_test_autodb_' . time();
$testDbUser = 'tm_usr_' . substr(time(), -5);
$testDbPass = 'TestPass2026!#';

$provRes = $standardAdapter->provisionDatabase($testDbName, $testDbUser, $testDbPass);
assert($provRes['success'] === true, "Standard provisioning failed: " . ($provRes['message'] ?? ''));
assert($provRes['data']['database'] === $testDbName, "Provisioned db name mismatch");
echo "[PASS] StandardHostingAdapter live database provisioning passed: {$testDbName}" . PHP_EOL;

// Clean up test database
global $db;
$db->exec_query("DROP DATABASE IF EXISTS `{$testDbName}`");
$db->exec_query("DROP USER IF EXISTS '{$testDbUser}'@'%'");
echo "[PASS] Cleanup of test database verified" . PHP_EOL;

echo "=== 3. TESTING CPANEL UAPI ADAPTER STRUCTURE ===" . PHP_EOL;
$cpanel = HostingManager::createAdapter('cpanel', [
    'host'      => 'cpanel.example.com',
    'username'  => 'myuser',
    'api_token' => 'CPANEL_TOKEN_123'
]);
assert($cpanel->getType() === 'cpanel', "cPanel type mismatch");
echo "[PASS] CpanelAdapter structure verified" . PHP_EOL;

echo "=== 4. TESTING PLESK REST API ADAPTER STRUCTURE ===" . PHP_EOL;
$plesk = HostingManager::createAdapter('plesk', [
    'host'    => 'plesk.example.com',
    'api_key' => 'PLESK_KEY_123'
]);
assert($plesk->getType() === 'plesk', "Plesk type mismatch");
echo "[PASS] PleskAdapter structure verified" . PHP_EOL;

echo "=== 5. TESTING CYBERPANEL API ADAPTER STRUCTURE ===" . PHP_EOL;
$cyber = HostingManager::createAdapter('cyberpanel', [
    'host'         => 'cyber.example.com',
    'username'     => 'admin',
    'password'     => 'CyberPass123',
    'website_name' => 'mywebsite.com'
]);
assert($cyber->getType() === 'cyberpanel', "CyberPanel type mismatch");
echo "[PASS] CyberPanelAdapter structure verified" . PHP_EOL;

echo "=== 6. TESTING REST API ENDPOINTS VIA CURL ===" . PHP_EOL;
$locales = [
    'tr' => 'Barındırma paneli bağlantısı başarıyla doğrulandı.',
    'en' => 'Hosting panel connection verified successfully.',
    'de' => 'Hosting-Panel-Verbindung erfolgreich verifiziert.'
];

foreach ($locales as $loc => $expectedMsg) {
    $ch = curl_init("http://localhost/taskmanager/api/projects/01/hosting/test?lang={$loc}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer tm_admin_talha_keskin_token_2026',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'panel_type' => 'standard',
        'host'       => 'localhost',
        'username'   => 'root',
        'password'   => '',
        'database'   => 'taskmanager_db'
    ]));
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($res, true);
    assert($code === 200, "API hosting test failed with HTTP {$code}: {$res}");
    assert($json['success'] === true, "API hosting test success false");
    assert($json['message'] === $expectedMsg, "Message mismatch for {$loc}: expected '{$expectedMsg}', got '{$json['message']}'");
    echo "[PASS] REST API /hosting/test ({$loc}) passed" . PHP_EOL;
}

// Test /api/projects/01/hosting/provision-db
$ch = curl_init("http://localhost/taskmanager/api/projects/01/hosting/provision-db?lang=tr");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer tm_admin_talha_keskin_token_2026',
    'Content-Type: application/json'
]);
$apiTestDb = 'tm_apitest_' . time();
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'panel_type' => 'standard',
    'host'       => 'localhost',
    'username'   => 'root',
    'password'   => '',
    'db_name'    => $apiTestDb,
    'db_user'    => 'api_u_' . substr(time(), -4),
    'db_pass'    => 'ApiPass2026!'
]));
$apiProvRes = curl_exec($ch);
$apiProvCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$apiProvJson = json_decode($apiProvRes, true);
assert($apiProvCode === 201, "API provision-db failed with HTTP {$apiProvCode}: {$apiProvRes}");
assert($apiProvJson['success'] === true, "API provision-db success was not true");
echo "[PASS] REST API /hosting/provision-db passed (HTTP 201 Created)" . PHP_EOL;

// Clean up
$db->exec_query("DROP DATABASE IF EXISTS `{$apiTestDb}`");

echo "=== 7. TESTING AJAX ENDPOINTS ===" . PHP_EOL;
$ch = curl_init("http://localhost/taskmanager/Controller/ajax/ajax.php?action=test_hosting_panel&lang=en");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'panel_type' => 'standard',
    'host'       => 'localhost',
    'username'   => 'root',
    'password'   => '',
    'database'   => 'taskmanager_db'
]));
$ajaxRes = curl_exec($ch);
curl_close($ch);

$ajaxJson = json_decode($ajaxRes, true);
echo "ACTUAL AJAX RESPONSE: " . $ajaxRes . PHP_EOL;
assert(!empty($ajaxJson['success']), "AJAX test_hosting_panel failed: {$ajaxRes}");
assert($ajaxJson['message'] === 'Hosting panel connection verified successfully.', "AJAX EN message mismatch: " . $ajaxJson['message']);
echo "[PASS] AJAX action=test_hosting_panel verified" . PHP_EOL;

echo "=== ALL HOSTING ADAPTER VERIFICATION TESTS COMPLETED SUCCESSFULLY ===" . PHP_EOL;
