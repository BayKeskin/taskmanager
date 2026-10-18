<?php
$targets = [
    'Modules/Board/Controllers/AjaxController.php',
    'Modules/RestApi/Controllers/index.php'
];

foreach ($targets as $f) {
    echo "=== $f ===\n";
    $c = file_get_contents($f);
    if (preg_match_all('/(\$db->|global\s+\$db|Database::getInstance|\bDataLayer\b)/i', $c, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $offset = $match[1];
            $line = substr_count(substr($c, 0, $offset), "\n") + 1;
            echo "Line $line: " . trim(substr($c, max(0, $offset - 40), 100)) . "\n";
        }
    } else {
        echo "No matches found\n";
    }
}
