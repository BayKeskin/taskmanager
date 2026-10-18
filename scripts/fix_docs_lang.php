<?php
// Update all __('app.docs_ occurrences in Modules/Docs to __('Docs::docs_
$docsDir = 'Modules/Docs';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsDir));

$count = 0;
foreach ($iterator as $file) {
    if ($file->isDir() || !in_array($file->getExtension(), ['php', 'js'])) continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    
    // Replace __('app.docs_ with __('Docs::docs_
    $newContent = preg_replace("/__\(\s*['\"]app\.docs_([a-zA-Z0-9_]+)['\"]/i", "__('Docs::docs_$1'", $content);
    
    // If it's a pure .js file, ensure any PHP tags <?php ... are cleanly handled or if we need pure JS
    if ($newContent !== $content) {
        file_put_contents($path, $newContent);
        $count++;
        echo "Updated $path\n";
    }
}
echo "Total updated files: $count\n";
