<?php
require_once __DIR__ . '/../System/System.php';

$modules = \Core\ModuleLoader::getLoadedModules();
echo "TOTAL ACTIVE MODULES LOADED: " . count($modules) . PHP_EOL;

$missingReport = [];

foreach ($modules as $slug => $m) {
    echo sprintf(" [%-15s] %-40s (v%s)\n", $slug, $m['name'], $m['version']);
    
    // 5 Element Standardı Doğrulama
    $basePath = $m['_dir'];
    $hasJson = file_exists($basePath . '/module.json');
    $hasInit = file_exists($basePath . '/init.php');
    $hasTr = file_exists($basePath . '/Lang/tr.php');
    $hasEn = file_exists($basePath . '/Lang/en.php');
    $hasDe = file_exists($basePath . '/Lang/de.php');
    $hasCss = is_dir($basePath . '/Assets/css');
    $hasJs = is_dir($basePath . '/Assets/js');

    if (!$hasJson || !$hasInit || !$hasTr || !$hasEn || !$hasDe || !$hasCss || !$hasJs) {
        $missingReport[] = $slug;
    }
}

if (empty($missingReport)) {
    echo "\n>>> ALL " . count($modules) . " MODULES STRICTLY CONFORM TO THE 5-ELEMENT STANDARD! <<<\n";
} else {
    echo "\n>>> INCOMPLETE MODULES: " . implode(', ', $missingReport) . " <<<\n";
}
