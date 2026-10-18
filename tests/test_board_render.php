<?php
require_once __DIR__ . '/../System/System.php';

$_SESSION['user'] = (object)[
    'id'   => 1,
    'name' => 'Talha Keskin',
    'role' => 'admin'
];

ob_start();
include __DIR__ . '/../Modules/Board/Views/board.php';
$output = ob_get_clean();

echo "RENDER_LENGTH: " . strlen($output) . " bytes\n";

echo "HAS_HEADER: " . (strpos($output, 'cu-view-header') !== false ? 'YES' : 'NO') . "\n";
echo "HAS_CONTAINER: " . (strpos($output, 'cu-board-container') !== false ? 'YES' : 'NO') . "\n";
echo "HAS_COL: " . (strpos($output, 'cu-board-col') !== false ? 'YES' : 'NO') . "\n";

if (strpos($output, 'cu-view-header') !== false && strpos($output, 'cu-board-container') !== false && strpos($output, 'cu-board-col') !== false) {
    echo "[PASS] Modules/Board/Views/board.php rendered completely with Kanban columns and header!\n";
} else {
    echo "[FAIL] Board render output incomplete. Output snippet:\n" . substr($output, 0, 500) . "\n";
    exit(1);
}
