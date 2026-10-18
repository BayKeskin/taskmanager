<?php
$dir = __DIR__ . '/../Modules';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$results = [];
foreach ($it as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        if (strpos($path, 'Views') !== false) {
            $content = file_get_contents($path);
            preg_match_all('/style\s*=\s*["\']([^"\']+)["\']/i', $content, $matches);
            if (!empty($matches[0])) {
                $rel = str_replace(realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR, '', $path);
                $results[$rel] = count($matches[0]);
            }
        }
    }
}
arsort($results);
echo "=== INLINE STYLE COUNTS IN MODULE VIEWS ===\n";
foreach ($results as $p => $c) {
    echo sprintf("%4d  %s\n", $c, $p);
}
