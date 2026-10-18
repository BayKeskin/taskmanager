<?php
// Extract CSS and JS from api_test.php
$file = 'Modules/ApiTester/Views/api_test.php';
$content = file_get_contents($file);

// Extract CSS: between <style> and </style>
if (preg_match('/<style>(.*?)<\/style>/s', $content, $mStyle)) {
    $css = trim($mStyle[1]);
    file_put_contents('Modules/ApiTester/Assets/css/api_test.css', $css);
    echo "Extracted api_test.css (" . strlen($css) . " bytes)\n";
    
    // Replace <style>...</style> with link tag
    $linkTag = '<link rel="stylesheet" href="/taskmanager/Modules/ApiTester/Assets/css/api_test.css">';
    $content = str_replace($mStyle[0], $linkTag, $content);
}

// Extract JS: between <script> and </script> at bottom
if (preg_match('/<script>(.*?)<\/script>\s*<\/body>/s', $content, $mScript)) {
    $js = trim($mScript[1]);
    // Separate the static functions and endpoints data from dynamic PHP variables
    // Let's create Modules/ApiTester/Assets/js/api_test.js
    file_put_contents('Modules/ApiTester/Assets/js/api_test.js', $js);
    echo "Extracted api_test.js (" . strlen($js) . " bytes)\n";
}

file_put_contents($file, $content);
echo "Updated api_test.php with css link\n";
