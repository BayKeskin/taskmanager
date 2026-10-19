<?php
require_once __DIR__ . '/../Core/System.php';

$_SESSION['user'] = (object)[
    'id' => 1,
    'name' => 'Talha Keskin',
    'role' => 'admin',
    'api_token' => 'test_token_123'
];

ob_start();
require __DIR__ . '/../Modules/ApiTester/Views/api_docs.php';
$html = ob_get_clean();

echo "Render Length: " . strlen($html) . " bytes\n";
echo "Has Header: " . (strpos($html, 'cu-view-header') !== false ? 'YES' : 'NO') . "\n";
echo "Has Token Card: " . (strpos($html, 'cu-docs-token-card') !== false ? 'YES' : 'NO') . "\n";
echo "Has Endpoint Cards: " . (strpos($html, 'cu-endpoint-card') !== false ? 'YES' : 'NO') . "\n";
echo "Has apitester.css enqueued: " . (strpos($html, 'apitester.css') !== false ? 'YES' : 'NO') . "\n";
echo "Has btnCopyApiToken: " . (strpos($html, 'btnCopyApiToken') !== false ? 'YES' : 'NO') . "\n";
echo "Has Base URL: " . (strpos($html, 'api/v1') !== false ? 'YES' : 'NO') . "\n";
