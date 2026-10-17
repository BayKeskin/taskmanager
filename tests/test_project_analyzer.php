<?php
/**
 * Test ProjectAnalyzer using synthetic isolated mock fixtures (Workspace Boundary Compliant)
 */
require_once __DIR__ . '/../System/System.php';

echo "=== 1. TEST SYNTHETIC FIXTURES ===" . PHP_EOL;

$fixturesBase = __DIR__ . '/fixtures';
if (!is_dir($fixturesBase)) {
    mkdir($fixturesBase, 0777, true);
}

// 1. Mock Laravel Project
$mockLaravel = "{$fixturesBase}/mock_laravel";
@mkdir("{$mockLaravel}/app/Http", 0777, true);
@file_put_contents("{$mockLaravel}/artisan", "<?php // Laravel Console");
@file_put_contents("{$mockLaravel}/composer.json", json_encode(['require' => ['laravel/framework' => 'v10.0.0']]));
@file_put_contents("{$mockLaravel}/.env", "APP_NAME=LaravelMock\nAPP_ENV=local\nAPP_KEY=base64:testkey123\nAPP_DEBUG=true\nAPP_URL=http://localhost:8000\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=laravel_mock_db\nDB_USERNAME=root\nDB_PASSWORD=secret\n");

$larRes = ProjectAnalyzer::analyze($mockLaravel, [
    'live_domain' => 'https://mock-laravel.com',
    'db_name'     => 'prod_laravel_db',
    'db_user'     => 'prod_user',
    'db_pass'     => 'ProdPass2026!'
]);

assert($larRes['success'] === true, 'Laravel analysis failed');
assert($larRes['framework']['type'] === 'laravel', 'Laravel type mismatch: ' . $larRes['framework']['type']);
assert($larRes['live_payload']['target_domain'] === 'https://mock-laravel.com', 'Target domain mismatch');
assert($larRes['live_payload']['replacements']['APP_URL']['target_live'] === 'https://mock-laravel.com', 'APP_URL replacement mismatch');
assert($larRes['live_payload']['replacements']['APP_ENV']['target_live'] === 'production', 'APP_ENV replacement mismatch');
assert($larRes['live_payload']['replacements']['APP_DEBUG']['target_live'] === 'false', 'APP_DEBUG replacement mismatch');
assert($larRes['live_payload']['replacements']['DB_DATABASE']['target_live'] === 'prod_laravel_db', 'DB replacement mismatch');
echo "[PASS] Mock Laravel Analysis & Live Payload Generation Verified" . PHP_EOL;

// 2. Mock WordPress Project
$mockWp = "{$fixturesBase}/mock_wordpress";
@mkdir("{$mockWp}/wp-includes", 0777, true);
@file_put_contents("{$mockWp}/wp-load.php", "<?php");
@file_put_contents("{$mockWp}/wp-includes/version.php", "<?php \$wp_version = '6.4.2';");
@file_put_contents("{$mockWp}/wp-config.php", "<?php\ndefine('DB_NAME', 'wp_local');\ndefine('DB_USER', 'wp_admin');\ndefine('DB_PASSWORD', 'wp_pass');\ndefine('DB_HOST', 'localhost');\ndefine('WP_SITEURL', 'http://localhost/wp');\ndefine('WP_DEBUG', true);\n");

$wpRes = ProjectAnalyzer::analyze($mockWp, [
    'live_domain' => 'https://mock-wordpress.org'
]);

assert($wpRes['success'] === true, 'WordPress analysis failed');
assert($wpRes['framework']['type'] === 'wordpress', 'WordPress type mismatch');
assert($wpRes['framework']['version'] === '6.4.2', 'WP version mismatch');
assert($wpRes['database_config']['database'] === 'wp_local', 'WP DB name mismatch');
assert($wpRes['live_payload']['replacements']['WP_SITEURL']['target_live'] === 'https://mock-wordpress.org', 'WP URL mismatch');
assert($wpRes['live_payload']['replacements']['WP_DEBUG']['target_live'] === 'false', 'WP debug mismatch');
echo "[PASS] Mock WordPress Analysis & Live Payload Generation Verified" . PHP_EOL;

// 3. Mock Custom PHP MVC Project (TaskManager-style)
$mockMvc = "{$fixturesBase}/mock_custom_mvc";
@mkdir("{$mockMvc}/Controller", 0777, true);
@mkdir("{$mockMvc}/Model", 0777, true);
@mkdir("{$mockMvc}/View", 0777, true);
@mkdir("{$mockMvc}/config", 0777, true);
@file_put_contents("{$mockMvc}/config/database.php", "<?php return ['default' => ['host' => 'localhost', 'database' => 'custom_db', 'username' => 'root', 'password' => '']];");

$mvcRes = ProjectAnalyzer::analyze($mockMvc);
assert($mvcRes['success'] === true, 'Custom MVC analysis failed');
assert($mvcRes['framework']['type'] === 'custom_php_mvc', 'Custom MVC type mismatch');
assert($mvcRes['database_config']['database'] === 'custom_db', 'MVC DB extraction mismatch');
echo "[PASS] Mock Custom PHP MVC Analysis Verified" . PHP_EOL;

// 4. Mock Static HTML Project
$mockHtml = "{$fixturesBase}/mock_static";
@mkdir($mockHtml, 0777, true);
@file_put_contents("{$mockHtml}/index.html", "<!DOCTYPE html><html><body><h1>Static</h1></body></html>");

$htmlRes = ProjectAnalyzer::analyze($mockHtml);
assert($htmlRes['success'] === true, 'Static analysis failed');
assert($htmlRes['framework']['type'] === 'static_html', 'Static type mismatch');
echo "[PASS] Mock Static HTML Analysis Verified" . PHP_EOL;

// 5. Test Live TaskManager Project #1
$tmRes = ProjectAnalyzer::analyze(__DIR__ . '/..', [
    'live_domain' => 'https://taskmanager.enterprise.io'
]);
assert($tmRes['success'] === true, 'TaskManager self-analysis failed');
assert($tmRes['framework']['type'] === 'custom_php_mvc', 'TaskManager type mismatch');
assert($tmRes['database_config']['database'] === 'taskmanager_db', 'TaskManager DB mismatch');
echo "[PASS] TaskManager Active Project Analysis Verified" . PHP_EOL;

// Cleanup Fixtures
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                    rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                else
                    unlink($dir. DIRECTORY_SEPARATOR .$object);
            }
        }
        rmdir($dir);
    }
}
rrmdir($fixturesBase);
echo "=== ALL UNIT & INTEGRATION TESTS PASSED ===" . PHP_EOL;
