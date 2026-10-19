<?php
require_once __DIR__ . '/../Core/System.php';

$pngFile = [
    'name'     => 'test_sample.png',
    'type'     => 'image/png',
    'tmp_name' => __DIR__ . '/fixtures/test_sample.png',
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize(__DIR__ . '/fixtures/test_sample.png')
];

$dangerPhpFile = [
    'name'     => 'test_danger.php',
    'type'     => 'application/x-php',
    'tmp_name' => __DIR__ . '/fixtures/test_danger.php',
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize(__DIR__ . '/fixtures/test_danger.php')
];

echo "--- 1. Testing PNG Upload ---\n";
$pngRes = \Modules\Storage\Services\StorageService::upload($pngFile, 'tasks');
print_r($pngRes);

echo "\n--- 2. Testing Dangerous .PHP File Upload Neutralization (.safe) ---\n";
$dangerRes = \Modules\Storage\Services\StorageService::upload($dangerPhpFile, 'tasks');
print_r($dangerRes);

echo "\n--- 3. Testing Deletion ---\n";
if (!empty($pngRes['file_path'])) {
    $delRes1 = \Modules\Storage\Services\StorageService::delete($pngRes['file_path']);
    echo "PNG Delete result: " . ($delRes1 ? 'SUCCESS (Deleted)' : 'FAIL') . "\n";
}
if (!empty($dangerRes['file_path'])) {
    $delRes2 = \Modules\Storage\Services\StorageService::delete($dangerRes['file_path']);
    echo "Dangerous PHP Delete result: " . ($delRes2 ? 'SUCCESS (Deleted)' : 'FAIL') . "\n";
}
