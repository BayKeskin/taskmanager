<?php
/**
 * CLI Test Sandbox Trigger (Port 4006 - Demo Dispatcher)
 * 
 * Bu betik CLI üzerinden çalıştırıldığında port 4006'daki `dispatcher_demo.py` servisine
 * örnek bir `task.started` JSON yükü fırlatır, Vardiya Değişimi ve Yarım İş Devri motorunun
 * tepkilerini ve oluşturulan nihai prompt'u ekrana yazdırır.
 */

require_once __DIR__ . '/../../../Core/System.php';

use Modules\Dispatcher\Services\DispatcherService_demo;
use Modules\Board\Models\TaskModel;
use Modules\RestApi\Services\RestApiService;

echo "=======================================================\n";
echo "🚀 [DEMO TEST TRIGGER] Port 4006 Sandbox Dispatcher Testi\n";
echo "=======================================================\n";

$dispDemo = new DispatcherService_demo();

// 1. Port 4006 açık mı kontrol et
echo "1. Port 4006 Durumu Denetleniyor: ";
if (!$dispDemo->isRunning()) {
    echo "KAPALI (Başlatılması deneniyor...)\n";
    $dispDemo->ensureRunning();
    sleep(1);
}

if ($dispDemo->isRunning()) {
    echo "✅ AKTİF (Port 4006 dinliyor)\n";
} else {
    echo "⚠️ Henüz arka planda başlamadı veya python yürütülemedi. Test scripti curl isteği deneyecektir.\n";
}

// 2. Proje Bilgisini DB'den çek (ASKERLIK veya ilk proje)
$db = (new Database())->connect;
$proj = $db->query("SELECT * FROM projects WHERE project_code = 'ASKERLIK' OR id = 15 LIMIT 1")->fetch(PDO::FETCH_OBJ);
if (!$proj) {
    $proj = $db->query("SELECT * FROM projects ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_OBJ);
}

// Test öncesi DB'deki mevcut ID'leri yaz
$beforeRow = $db->query("SELECT id, project_code, dev_conversation_id, agent_conversation_id FROM projects WHERE id = {$proj->id}")->fetch(PDO::FETCH_ASSOC);

$taskCode = 'TSK-DEAD-ID-TEST';
$taskTitle = 'Ölü ID Temizliği ve Otonom Sohbet Keşfi Testi';
$taskDesc = 'Bu testte bilinçli olarak dev_conversation_id = dead-conv-id-99999 gönderilerek sistemin ölü ID yi reddedip açık pencereyi veya taze sohbeti bulması test edilmektedir.';

echo "2. Test Öncesi Veritabanı Durumu:\n";
echo "   - Proje       : [{$proj->project_code}] {$proj->title}\n";
echo "   - DB Dev CID  : " . ($beforeRow['dev_conversation_id'] ?? 'NULL') . "\n";
echo "   - DB Agent CID: " . ($beforeRow['agent_conversation_id'] ?? 'NULL') . "\n\n";

$apiCfg = RestApiService::getDynamicApiConfig();

// ÖLÜ ID GÖNDERİLİYOR
$deadConvId = 'dead-conv-id-99999';

$testPayload = [
    'event'                 => 'task.started',
    'project_id'            => (int)$proj->id,
    'project_code'          => $proj->project_code,
    'project_title'         => $proj->title,
    'workspace_path'        => $proj->workspace_path,
    'agent_rules'           => $proj->agent_rules,
    'dev_conversation_id'   => $deadConvId, // BİLİNÇLİ ÖLÜ ID
    'qa_conversation_id'    => $proj->qa_conversation_id,
    'api_base_url'          => $apiCfg['base_url'],
    'api_token'             => $apiCfg['api_token'],
    'timestamp'             => date('c'),
    'data'                  => [
        'task_id'     => 9999,
        'task_code'   => $taskCode,
        'title'       => $taskTitle,
        'description' => $taskDesc,
        'priority'    => 'high',
        'resumed'     => false
    ]
];

echo "3. Port 4006'ya Gönderilecek Test Parametreleri:\n";
echo "   - Gönderilen dev_conversation_id: {$deadConvId} (ÖLÜ ID)\n";
echo "   - Görev Kodu                    : {$taskCode}\n";
echo "   - Hedef Webhook                 : http://127.0.0.1:4006/webhook\n\n";

// 3. Webhook HTTP İsteği Gönder (Port 4006)
echo "4. Port 4006'ya Webhook Payload Gönderiliyor...\n";
$targetUrl = 'http://127.0.0.1:4006/webhook';
$jsonPayload = json_encode($testPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json; charset=utf-8',
    'User-Agent: TaskManager-Sandbox-Tester/1.0',
    'X-TaskManager-Event: task.started',
    'X-TaskManager-Project: ' . $proj->project_code,
    'X-TaskManager-Conversation-ID: ' . $deadConvId
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "\n-------------------------------------------------------\n";
echo "📡 HTTP Yanıt Kodu : " . ($httpCode ?: 'BAĞLANTI SAĞLANAMADI') . "\n";
if ($curlErr) {
    echo "❌ cURL Hatası     : {$curlErr}\n";
} else {
    echo "✅ Servis Yanıtı   : {$response}\n";
}
echo "-------------------------------------------------------\n\n";

// 5. Arka plandaki Python işleyicisinin tamamlanması için kısa bir süre bekle
echo "5. Arka plan işleminin Language Server ile tamamlanması bekleniyor (2 sn)...\n";
sleep(2);

// 6. Test sonrası Veritabanı ve Log Durumunu Doğrula
$afterRow = $db->query("SELECT id, project_code, dev_conversation_id, agent_conversation_id FROM projects WHERE id = {$proj->id}")->fetch(PDO::FETCH_ASSOC);

echo "\n-------------------------------------------------------\n";
echo "📊 TEST SONRASI VERİTABANI KONTROLÜ:\n";
echo "   - DB Dev CID (Önce): " . ($beforeRow['dev_conversation_id'] ?? 'NULL') . "\n";
echo "   - DB Dev CID (Sonra): " . ($afterRow['dev_conversation_id'] ?? 'NULL') . "\n";
echo "   - DB Agent CID (Sonra): " . ($afterRow['agent_conversation_id'] ?? 'NULL') . "\n";
echo "-------------------------------------------------------\n\n";

echo "6. Son Dispatcher Demo Log Kayıtları (dispatcher_demo.log):\n";
$demoLogFile = __DIR__ . '/dispatcher_demo.log';
if (file_exists($demoLogFile)) {
    $lines = file($demoLogFile);
    $lastLines = array_slice($lines, -35);
    echo implode("", $lastLines);
} else {
    echo "*(Henüz log dosyası oluşmadı)*\n";
}

echo "\n=======================================================\n";
echo "🏁 Test Sandbox Tetikleyicisi Tamamlandı.\n";
echo "=======================================================\n";
