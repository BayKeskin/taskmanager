<?php
$rootDir = realpath(__DIR__ . '/..');
$modulesDir = $rootDir . DIRECTORY_SEPARATOR . 'Modules';

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modulesDir));

$dbInControllersServices = [];
$modelsInViews = [];

foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    
    $path = $file->getPathname();
    $rel = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $path);
    $content = file_get_contents($path);
    
    // Check Controllers and Services for direct DB calls ($db->, Database::, DataLayer calls, raw SQL in query/row_query/multi_record)
    if (strpos($path, 'Controllers') !== false || strpos($path, 'Services') !== false) {
        $matches = [];
        // Detect: ->query(, ->row_query(, ->multi_record(, ->single_record(, ->count_record(, ->execute(, Database::, $db
        if (preg_match_all('/(\$db\b|\$this->db\b|Database::get|->(query|row_query|multi_record|single_record|count_record|execute)\s*\()/i', $content, $m)) {
            $dbInControllersServices[$rel] = count($m[0]);
        }
    }
    
    // Check Views for direct Model instantiation or static Model method calls: new *Model, *Model::
    if (strpos($path, 'Views') !== false) {
        if (preg_match_all('/(new\s+[\\\\\w]*Model|[A-Z]\w+Model::)/i', $content, $m)) {
            $modelsInViews[$rel] = count($m[0]);
        }
    }
}

echo "=== 1. DIRECT DB CALLS IN CONTROLLERS & SERVICES (" . count($dbInControllersServices) . " files) ===\n";
foreach ($dbInControllersServices as $f => $c) {
    echo sprintf("%4d  %s\n", $c, $f);
}

echo "\n=== 2. DIRECT MODEL USAGE IN VIEWS (" . count($modelsInViews) . " files) ===\n";
foreach ($modelsInViews as $f => $c) {
    echo sprintf("%4d  %s\n", $c, $f);
}
