<?php
$file = 'Modules/ApiTester/Views/api_test.php';
$content = file_get_contents($file);

$start = strpos($content, '<script>');
$end = strrpos($content, '</script>');

if ($start !== false && $end !== false) {
    $scriptReplacement = '    <script>' . "\n" .
        '        window.APITESTER_CONFIG = {' . "\n" .
        '            baseUrl: <?= json_encode(rtrim($baseUrl, \'/\')) ?>,' . "\n" .
        '            activeToken: <?= json_encode($userToken) ?>,' . "\n" .
        '            sampleProject: <?= json_encode($sampleProject) ?>,' . "\n" .
        '            sampleTask: <?= json_encode($sampleTask) ?>' . "\n" .
        '        };' . "\n" .
        '    </script>' . "\n" .
        '    <script src="/taskmanager/Modules/ApiTester/Assets/js/api_test.js"></script>';
    
    $content = substr($content, 0, $start) . $scriptReplacement . substr($content, $end + strlen('</script>'));
    file_put_contents($file, $content);
    echo "Successfully replaced inline script in api_test.php!\n";
} else {
    echo "Could not find script tags\n";
}
