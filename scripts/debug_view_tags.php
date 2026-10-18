<?php
$files = [
    'Modules/ApiTester/Views/api_docs.php',
    'Modules/ApiTester/Views/api_test.php',
    'Modules/Board/Views/board.php',
    'Modules/Docs/Views/docs.php',
    'Modules/Mcp/Views/modals/mcp_marketplace.php',
    'Modules/Team/Views/login.php',
    'Modules/Team/Views/team.php'
];

foreach ($files as $f) {
    if (!file_exists($f)) {
        echo "$f does not exist\n";
        continue;
    }
    $c = file_get_contents($f);
    $hasStyle = preg_match('/<style\b[^>]*>/i', $c, $mStyle);
    $hasScript = preg_match('/<script\b[^>]*>/i', $c, $mScript);
    echo "$f:\n";
    echo "  style: " . ($hasStyle ? "YES (line " . substr_count(substr($c, 0, strpos($c, $mStyle[0])), "\n") + 1 . "): " . substr($mStyle[0], 0, 40) : "NO") . "\n";
    echo "  script: " . ($hasScript ? "YES (line " . substr_count(substr($c, 0, strpos($c, $mScript[0])), "\n") + 1 . "): " . substr($mScript[0], 0, 40) : "NO") . "\n";
}
