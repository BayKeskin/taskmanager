<?php
$modulesDir = __DIR__ . '/../Modules';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modulesDir));

$viewsWithModals = [];
$modalFiles = [];

foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    $path = str_replace('\\', '/', $file->getPathname());
    if (strpos($path, '/Views/') === false) continue;
    
    $content = file_get_contents($path);
    $isModalFile = strpos($path, '/modals/') !== false || strpos(basename($path), 'modal') !== false;
    if ($isModalFile) {
        $modalFiles[] = $path;
    } else {
        // Check if a normal view file has embedded modals (cu-modal, modal-overlay, etc.)
        if (preg_match_all('/<div\b[^>]*class=["\'][^"\']*\bcu-modal-overlay\b[^"\']*["\']/i', $content, $m)) {
            $viewsWithModals[$path] = count($m[0]);
        }
    }
}

echo "=== DEDICATED MODAL FILES (" . count($modalFiles) . ") ===\n";
foreach ($modalFiles as $mf) {
    echo "  - " . str_replace($modulesDir . '/', '', $mf) . "\n";
}

echo "\n=== VIEWS WITH EMBEDDED MODALS (" . count($viewsWithModals) . ") ===\n";
foreach ($viewsWithModals as $vf => $count) {
    echo "  - " . str_replace($modulesDir . '/', '', $vf) . " ($count modals embedded)\n";
}
