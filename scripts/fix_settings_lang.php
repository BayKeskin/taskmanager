<?php
// Replace all __('app.settings_ with __('Settings::settings_ in Modules/Settings
$settingsDir = 'Modules/Settings';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($settingsDir));

$count = 0;
foreach ($iterator as $file) {
    if ($file->isDir() || !in_array($file->getExtension(), ['php', 'js'])) continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    
    // Replace __('app.settings_ with __('Settings::settings_
    $newContent = preg_replace("/__\(\s*['\"]app\.settings_([a-zA-Z0-9_]+)['\"]/i", "__('Settings::settings_$1'", $content);
    
    if ($newContent !== $content) {
        file_put_contents($path, $newContent);
        $count++;
        echo "Updated $path\n";
    }
}
echo "Total updated files: $count\n";
