<?php
/**
 * Autonomous QA Verification for LiveConfigGenerator & Schema Dumper (TSK-1083)
 */
require_once __DIR__ . '/../Core/System.php';

echo "=== 1. TESTING IN-MEMORY LIVE DOTENV GENERATION ===" . PHP_EOL;

$origEnv = <<<'ENV'
APP_NAME=TaskManager
APP_ENV=local
APP_KEY=base64:origkey123
APP_DEBUG=true
APP_URL=http://localhost/taskmanager

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taskmanager_local
DB_USERNAME=root
DB_PASSWORD=
ENV;

$replacements = [
    'APP_ENV'     => 'production',
    'APP_DEBUG'   => false,
    'APP_URL'     => 'https://live-taskmanager.com',
    'DB_HOST'     => 'live-mysql.host.net',
    'DB_DATABASE' => 'prod_tm_db',
    'DB_USERNAME' => 'prod_tm_user',
    'DB_PASSWORD' => 'SecurePass123!#$'
];

$genEnv = LiveConfigGenerator::generateDotEnv($origEnv, $replacements);
assert(str_contains($genEnv, 'APP_ENV=production'), "APP_ENV not production");
assert(str_contains($genEnv, 'APP_DEBUG=false'), "APP_DEBUG not false");
assert(str_contains($genEnv, 'APP_URL=https://live-taskmanager.com'), "APP_URL not live domain");
assert(str_contains($genEnv, 'DB_DATABASE=prod_tm_db'), "DB_DATABASE not prod_tm_db");
assert(str_contains($genEnv, 'DB_PASSWORD="SecurePass123!#$"'), "DB_PASSWORD not properly quoted with special chars");
echo "[PASS] In-Memory DotEnv Generation Verified" . PHP_EOL;

echo "=== 2. TESTING IN-MEMORY PHP DATABASE CONFIG GENERATION ===" . PHP_EOL;

$origPhp = <<<'PHP'
<?php
return [
    'default' => [
        'host'      => 'localhost',
        'database'  => 'taskmanager_db',
        'user'      => 'root',
        'pass'      => '',
        'charset'   => 'utf8mb4'
    ]
];
PHP;

$genPhp = LiveConfigGenerator::generatePhpConfig($origPhp, [
    'host'     => 'sql.remotehost.com',
    'database' => 'live_db_name',
    'username' => 'remote_user',
    'password' => 'RemotePass2026'
]);

assert(str_contains($genPhp, "'host'      => 'sql.remotehost.com'"), "PHP host not updated");
assert(str_contains($genPhp, "'database'  => 'live_db_name'"), "PHP database not updated");
assert(str_contains($genPhp, "'user'      => 'remote_user'"), "PHP user not updated");
assert(str_contains($genPhp, "'pass'      => 'RemotePass2026'"), "PHP pass not updated");
echo "[PASS] In-Memory PHP Config Generation Verified" . PHP_EOL;

echo "=== 3. TESTING IN-MEMORY WP-CONFIG GENERATION ===" . PHP_EOL;
$origWp = <<<'WP'
<?php
define('DB_NAME', 'wp_local');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('WP_DEBUG', true);
WP;

$genWp = LiveConfigGenerator::generateWpConfig($origWp, [
    'database' => 'wp_prod',
    'username' => 'wp_prod_user',
    'password' => 'WpProdPass!',
    'host'     => 'localhost'
], 'https://myblog.com');

assert(str_contains($genWp, "define('DB_NAME', 'wp_prod');"), "WP DB_NAME mismatch");
assert(str_contains($genWp, "define('DB_USER', 'wp_prod_user');"), "WP DB_USER mismatch");
assert(str_contains($genWp, "define('WP_SITEURL', 'https://myblog.com');"), "WP_SITEURL mismatch");
assert(str_contains($genWp, "define('WP_DEBUG', false);"), "WP_DEBUG mismatch");
echo "[PASS] In-Memory wp-config.php Generation Verified" . PHP_EOL;

echo "=== 4. TESTING DATABASE SCHEMA DUMPER & DOMAIN REPLACEMENT ===" . PHP_EOL;

global $db;
$localPdo = $db->connect;
$dumpSql = LiveConfigGenerator::dumpDatabase($localPdo, ['dump_data' => true]);

assert(strlen($dumpSql) > 1000, "Dump SQL too short: " . strlen($dumpSql));
assert(str_contains($dumpSql, "CREATE TABLE"), "CREATE TABLE not found in dump");
assert(str_contains($dumpSql, "DROP TABLE IF EXISTS `tasks`"), "Tasks table not found in dump");

$replacedSql = LiveConfigGenerator::replaceDomainsInSql($dumpSql, 'http://localhost/taskmanager', 'https://live-domain.com');
assert(!str_contains($replacedSql, 'http://localhost/taskmanager'), "Old domain still present in replaced SQL");
echo "[PASS] Database Schema Dumper and Domain Replacement Verified" . PHP_EOL;

echo "=== 5. TESTING REST API ENDPOINTS VIA CURL ===" . PHP_EOL;

$locales = [
    'tr' => [
        'config' => 'Canlıya özel izolasyonlu konfigürasyon dosyaları başarıyla üretildi.',
        'schema' => 'Yerel veritabanı şeması ve verileri canlı domain eşitlemesiyle başarıyla dışa aktarıldı.'
    ],
    'en' => [
        'config' => 'Live isolated configuration files successfully generated.',
        'schema' => 'Local database schema and data successfully exported with live domain synchronization.'
    ],
    'de' => [
        'config' => 'Live-isolierte Konfigurationsdateien erfolgreich generiert.',
        'schema' => 'Lokales Datenbankschema und Daten erfolgreich mit Live-Domain-Synchronisation exportiert.'
    ]
];

foreach ($locales as $loc => $msgs) {
    // 5.1 POST /api/projects/01/hosting/generate-config
    $ch = curl_init("http://localhost/taskmanager/api/projects/01/hosting/generate-config?lang={$loc}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer tm_admin_talha_keskin_token_2026',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'live_domain' => 'https://taskmanager.enterprise.io',
        'db_host'     => 'prod-sql.enterprise.io',
        'db_name'     => 'prod_tm_live',
        'db_user'     => 'prod_user',
        'db_pass'     => 'ProdPass2026!'
    ]));
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($res, true);
    assert($code === 200, "generate-config failed with HTTP {$code}: {$res}");
    assert($json['success'] === true, "generate-config success false");
    assert($json['message'] === $msgs['config'], "Message mismatch for {$loc}: expected '{$msgs['config']}', got '{$json['message']}'");
    assert(!empty($json['data']['generated_files']), "Generated files empty");
    echo "[PASS] REST API /hosting/generate-config ({$loc}) passed" . PHP_EOL;

    // 5.2 POST /api/projects/01/hosting/dump-schema
    $ch = curl_init("http://localhost/taskmanager/api/projects/01/hosting/dump-schema?lang={$loc}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer tm_admin_talha_keskin_token_2026',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'live_domain' => 'https://taskmanager.enterprise.io',
        'dump_data'   => false
    ]));
    $schemaRes = curl_exec($ch);
    $schemaCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $schemaJson = json_decode($schemaRes, true);
    assert($schemaCode === 200, "dump-schema failed with HTTP {$schemaCode}: {$schemaRes}");
    assert($schemaJson['success'] === true, "dump-schema success false");
    assert($schemaJson['message'] === $msgs['schema'], "Message mismatch for {$loc}: expected '{$msgs['schema']}', got '{$schemaJson['message']}'");
    assert($schemaJson['data']['sql_size'] > 500, "SQL size too small");
    echo "[PASS] REST API /hosting/dump-schema ({$loc}) passed" . PHP_EOL;
}

echo "=== 6. TESTING AJAX ENDPOINTS ===" . PHP_EOL;
$ch = curl_init("http://localhost/taskmanager/Controller/ajax/ajax.php?action=generate_live_config&lang=en");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'project_id'  => 1,
    'live_domain' => 'https://taskmanager.enterprise.io',
    'db_name'     => 'live_db',
    'db_user'     => 'live_usr',
    'db_pass'     => 'LivePass123'
]));
$ajaxRes = curl_exec($ch);
curl_close($ch);
$ajaxJson = json_decode($ajaxRes, true);
assert(!empty($ajaxJson['success']), "AJAX generate_live_config failed: {$ajaxRes}");
assert($ajaxJson['message'] === 'Live isolated configuration files successfully generated.', "AJAX EN message mismatch");
echo "[PASS] AJAX action=generate_live_config verified" . PHP_EOL;

echo "=== ALL QA VERIFICATION TESTS COMPLETED SUCCESSFULLY ===" . PHP_EOL;
