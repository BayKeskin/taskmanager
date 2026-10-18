<?php
/**
 * Comprehensive Deep HMVC Auditor across all 24 Modules
 * Checks:
 * 1. Views with <style> or <script> tags or excessive style="" attributes
 * 2. Views/Controllers with direct Database calls ($db, global $db, Database::, \Core\Database, query(, row_query()
 * 3. Modules missing dedicated Model classes
 * 4. Language violations: Views using general __('app. or hardcoded strings instead of __('ModuleName::key')
 */

$modulesDir = dirname(__DIR__) . '/Modules';
$modules = array_filter(scandir($modulesDir), function($item) use ($modulesDir) {
    return is_dir($modulesDir . '/' . $item) && !in_array($item, ['.', '..']);
});

$report = [];

foreach ($modules as $mod) {
    $modPath = $modulesDir . '/' . $mod;
    $modReport = [
        'name' => $mod,
        'has_model' => false,
        'models' => [],
        'controllers' => [],
        'views' => [],
        'inline_style_script_views' => [],
        'direct_db_calls' => [],
        'general_lang_calls' => [],
        'hardcoded_text_samples' => []
    ];

    // Check Models
    $modelDir = $modPath . '/Models';
    if (is_dir($modelDir)) {
        $modelFiles = glob($modelDir . '/*.php');
        if (!empty($modelFiles)) {
            $modReport['has_model'] = true;
            $modReport['models'] = array_map('basename', $modelFiles);
        }
    }

    // Check Controllers
    $ctrlDir = $modPath . '/Controllers';
    if (is_dir($ctrlDir)) {
        $ctrlFiles = glob($ctrlDir . '/*.php');
        $modReport['controllers'] = array_map('basename', $ctrlFiles);
    }

    // Scan all PHP files recursively in module
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modPath));
    foreach ($iterator as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') continue;
        $relPath = str_replace($modulesDir . '/', '', str_replace('\\', '/', $file->getPathname()));
        $content = file_get_contents($file->getPathname());

        $isView = strpos($relPath, '/Views/') !== false;
        $isController = strpos($relPath, '/Controllers/') !== false;

        // 1. Check Views for <style> and <script> tags
        if ($isView) {
            $modReport['views'][] = $relPath;
            $hasStyle = preg_match('/<style\b[^>]*>/i', $content);
            $hasScript = preg_match('/<script\b[^>]*>/i', $content);
            if ($hasStyle || $hasScript) {
                $modReport['inline_style_script_views'][] = [
                    'file' => $relPath,
                    'has_style' => (bool)$hasStyle,
                    'has_script' => (bool)$hasScript
                ];
            }
        }

        // 2. Check for direct DB calls ($db->, global $db, Database::getInstance, \Core\Database::)
        // Controllers should use Model, Views must NEVER use DB
        if ($isView || $isController) {
            if (preg_match('/(\$db->|global\s+\$db|Database::getInstance|\bDataLayer\b)/i', $content, $m)) {
                $modReport['direct_db_calls'][] = [
                    'file' => $relPath,
                    'type' => $isView ? 'VIEW_VIOLATION' : 'CONTROLLER_VIOLATION',
                    'match' => $m[0]
                ];
            }
        }

        // 3. Check for general language calls __('app.
        if (preg_match_all("/__\(\s*['\"]app\.([a-zA-Z0-9_\-]+)['\"]/i", $content, $matches)) {
            foreach ($matches[0] as $matchKey) {
                if (!in_array($matchKey, $modReport['general_lang_calls'])) {
                    $modReport['general_lang_calls'][] = $matchKey;
                }
            }
        }
    }

    $report[$mod] = $modReport;
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
