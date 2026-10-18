<?php
require_once __DIR__ . '/../Core/System.php';

echo "=== TESTING API & I18N ENDPOINTS ===" . PHP_EOL;

$locales = [
    'tr' => 'Proje ortam değişkenleri ve canlı yapılandırma analizi tamamlandı.',
    'en' => 'Project environment variables and live deployment configuration analysis completed.',
    'de' => 'Analyse der Projektumgebungsvariablen und Live-Bereitstellungskonfiguration abgeschlossen.'
];

foreach ($locales as $loc => $expectedMsg) {
    $url = "http://localhost/taskmanager/api/projects/01/analyze?lang={$loc}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer tm_admin_talha_keskin_token_2026',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'live_domain' => 'https://taskmanager.example.com',
        'db_name'     => 'live_taskmanager_db',
        'db_user'     => 'live_tm_user',
        'db_pass'     => 'LivePass2026!'
    ]));
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($res, true);
    assert($httpCode === 200, "API failed with HTTP {$httpCode}: {$res}");
    assert(!empty($json['success']), "API success was not true");
    assert($json['message'] === $expectedMsg, "Message mismatch for {$loc}: expected '{$expectedMsg}', got '{$json['message']}'");
    assert($json['data']['framework']['type'] === 'custom_php_mvc', "Framework mismatch");
    assert($json['data']['live_payload']['target_domain'] === 'https://taskmanager.example.com', "Domain mismatch");
    assert($json['data']['live_payload']['target_database']['database'] === 'live_taskmanager_db', "DB mismatch");

    echo "[PASS] REST API ({$loc}) responded 200 OK with correct translation and payload" . PHP_EOL;
}

echo "=== TESTING AJAX ENDPOINT ===" . PHP_EOL;
$ch = curl_init('http://localhost/taskmanager/Controller/ajax/ajax.php?action=analyze_project&lang=en');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'project_id'  => 1,
    'live_domain' => 'https://live-ajax.io'
]));
$ajaxRes = curl_exec($ch);
curl_close($ch);
$ajaxJson = json_decode($ajaxRes, true);
assert(!empty($ajaxJson['success']), "AJAX analyze_project failed: {$ajaxRes}");
assert($ajaxJson['message'] === 'Project environment variables and live deployment configuration analysis completed.', "AJAX EN message mismatch");
echo "[PASS] AJAX analyze_project endpoint verified" . PHP_EOL;

echo "=== ALL AUTONOMOUS VERIFICATION TESTS PASSED ===" . PHP_EOL;
