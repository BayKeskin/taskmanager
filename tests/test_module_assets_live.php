<?php
$cookieFile = __DIR__ . '/test_cookie.txt';

// 1. Post to login form directly
$ch = curl_init("http://localhost/taskmanager/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'email' => 'iletisimtktalha@gmail.com',
    'password' => '145236'
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Login HTTP Code: " . $code . "\n";

// 2. Fetch Board
$ch = curl_init("http://localhost/taskmanager/board?project_id=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Board HTTP Code: " . $code . "\n";

preg_match_all('/<link[^>]+Modules[^>]+>/i', $html, $cssMatches);
preg_match_all('/<script[^>]+Modules[^>]+><\/script>/i', $html, $jsMatches);

echo "Module CSS files rendered: " . count($cssMatches[0]) . "\n";
foreach ($cssMatches[0] as $t) {
    echo "  " . $t . "\n";
}

echo "Module JS files rendered: " . count($jsMatches[0]) . "\n";
foreach ($jsMatches[0] as $t) {
    echo "  " . $t . "\n";
}

@unlink($cookieFile);
