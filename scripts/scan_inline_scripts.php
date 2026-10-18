<?php
$rootDir = realpath(__DIR__ . '/..');
$modulesDir = $rootDir . DIRECTORY_SEPARATOR . 'Modules';

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modulesDir));

$scriptBlocks = [];

foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    
    $path = $file->getPathname();
    $rel = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $path);
    
    // We only care about Views
    if (strpos($path, 'Views') === false) {
        continue;
    }
    
    $content = file_get_contents($path);
    
    // Match <script> tags that are NOT external (<script src=...></script>)
    // i.e. <script ...> containing actual code
    if (preg_match_all('/<script\b(?![^>]*\bsrc\s*=)[^>]*>(.*?)<\/script>/is', $content, $matches)) {
        $totalLines = 0;
        $snippets = [];
        foreach ($matches[1] as $idx => $scriptContent) {
            $trimmed = trim($scriptContent);
            if (!empty($trimmed)) {
                $lines = count(explode("\n", $trimmed));
                $totalLines += $lines;
                $snippets[] = [
                    'lines' => $lines,
                    'preview' => mb_substr(preg_replace('/\s+/', ' ', $trimmed), 0, 80)
                ];
            }
        }
        if ($totalLines > 0) {
            $scriptBlocks[$rel] = [
                'totalLines' => $totalLines,
                'count'      => count($snippets),
                'snippets'   => $snippets
            ];
        }
    }
}

uasort($scriptBlocks, fn($a, $b) => $b['totalLines'] <=> $a['totalLines']);

echo "=== INLINE <script> BLOCKS IN MODULE VIEWS (" . count($scriptBlocks) . " files) ===\n\n";
foreach ($scriptBlocks as $file => $info) {
    echo sprintf("[%3d lines in %d block(s)]  %s\n", $info['totalLines'], $info['count'], $file);
    foreach ($info['snippets'] as $s) {
        echo sprintf("   └── (%2d lines): %s...\n", $s['lines'], $s['preview']);
    }
    echo "\n";
}
