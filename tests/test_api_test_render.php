<?php
require_once __DIR__ . '/../Core/System.php';

$_SESSION['user'] = (object)[
    'id' => 1,
    'name' => 'Talha Keskin',
    'role' => 'admin',
    'api_token' => 'test_token_123'
];

ob_start();
(new \Modules\ApiTester\Controllers\ApiTesterController())->index();
$html = ob_get_clean();

echo "Render Length: " . strlen($html) . " bytes\n";
echo "Has APITESTER_LIVE_DATA script: " . (strpos($html, 'APITESTER_LIVE_DATA') !== false ? 'YES' : 'NO') . "\n";
echo "Has AssetManager::renderJs: " . (strpos($html, 'api_test.js') !== false ? 'YES' : 'NO') . "\n";
echo "Has AssetManager::renderCss: " . (strpos($html, 'api_test.css') !== false ? 'YES' : 'NO') . "\n";
file_put_contents(__DIR__ . '/scratch_api_test.html', $html);
echo "Saved to tests/scratch_api_test.html\n";
