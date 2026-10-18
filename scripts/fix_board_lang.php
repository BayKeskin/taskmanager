<?php
// Replace all __('app. with __('Board:: in Modules/Board
$boardDir = 'Modules/Board';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($boardDir));

$count = 0;
foreach ($iterator as $file) {
    if ($file->isDir() || !in_array($file->getExtension(), ['php', 'js'])) continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    
    // Replace __('app.modal_ with __('Board::modal_
    $newContent = preg_replace("/__\(\s*['\"]app\.modal_([a-zA-Z0-9_]+)['\"]/i", "__('Board::modal_$1'", $content);
    // Replace __('app.tasks_ with __('Board::tasks_
    $newContent = preg_replace("/__\(\s*['\"]app\.tasks_([a-zA-Z0-9_]+)['\"]/i", "__('Board::tasks_$1'", $newContent);
    // Replace __('app.btn_ai_architect') with __('AiArchitect::btn_ai_architect', 'AI Mimar')
    $newContent = str_replace("__('app.btn_ai_architect')", "__('AiArchitect::btn_ai_architect', 'AI Mimar')", $newContent);
    $newContent = str_replace("__('app.tooltip_ai_architect')", "__('AiArchitect::tooltip_ai_architect', 'AI Mimar ile Görev Oluştur')", $newContent);
    // Any remaining __('app.XYZ') to __('Board::XYZ')
    $newContent = preg_replace("/__\(\s*['\"]app\.([a-zA-Z0-9_]+)['\"]/i", "__('Board::$1'", $newContent);

    if ($newContent !== $content) {
        file_put_contents($path, $newContent);
        $count++;
        echo "Updated $path\n";
    }
}
echo "Total updated files: $count\n";
