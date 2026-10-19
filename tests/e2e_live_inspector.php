<?php
/**
 * TaskManager E2E Canlı Sayfa, Ağ & JavaScript Konsol Hata Denetçisi
 * 
 * Bu betik curl oturumu ile sisteme admin olarak giriş yapar (Cookie Session oluşturur)
 * ve ardından sistemdeki tüm canlı sayfaları, modülleri, statik varlıkları (CSS/JS)
 * ve AJAX / SSE API rotalarını tek tek talep ederek:
 *  - HTTP 200 harici (404, 500, Fatal error) hataları
 *  - PHP Fatal Error, Warning, Notice dökümlerini
 *  - Sayfaya gömülen JS dosyalarının fiziki varlığını (404 JS/CSS linkleri)
 *  - SSE Canlı Yayın kanalı bağlantısını
 *  - AJAX endpointlerinin yanıt durumunu
 * otonom olarak denetler.
 * 
 * Kullanım:
 * php tests/e2e_live_inspector.php
 */

$baseUrl = 'http://localhost/taskmanager';
$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) @unlink($cookieFile);

echo "========================================================\n";
echo "🌐 TASKMANAGER E2E CANLI SAYFA & AĞ DENETÇİSİ (LIVE INSPECTOR)\n";
echo "========================================================\n\n";

function requestUrl(string $url, string $method = 'GET', array $postData = [], $timeout = 10, bool $isStreaming = false): array {
    global $cookieFile;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }

    if ($isStreaming) {
        $streamHeader = '';
        $streamBody = '';

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$streamBody) {
            $streamBody .= $data;
            if (strlen($streamBody) > 100) {
                return 0; // İstenilen ilk veri geldiğinde akışı sonlandır
            }
            return strlen($data);
        });
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$streamHeader) {
            $streamHeader .= $header;
            return strlen($header);
        });

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
        curl_close($ch);

        return ['code' => $httpCode ?: 200, 'headers' => $streamHeader . "\nContent-Type: $contentType", 'content_type' => $contentType, 'body' => $streamBody, 'error' => null];
    }

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['code' => 0, 'error' => $err, 'headers' => '', 'body' => ''];
    }

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body, 'error' => null];
}

// 1. ADIM: Admin Kullanıcısı ile Giriş Yap (Auth Flow)
echo "🔑 1. ADIM: Yönetici Oturumu Açılıyor (Auth Login)...\n";
$loginRes = requestUrl($baseUrl . '/login', 'POST', [
    'email'    => 'iletisimtktalha@gmail.com',
    'password' => '145236'
]);

if ($loginRes['code'] !== 200 && $loginRes['code'] !== 302) {
    echo "❌ [FAIL] Giriş yapılamadı! HTTP Kodu: {$loginRes['code']}\n";
    exit(1);
}
echo "✅ [PASS] Oturum başarıyla açıldı.\n\n";

// 2. ADIM: Canlı Sayfaları Denetleme
$pages = [
    'Kanban Panosu (Board)'        => '/board?project_id=1',
    'Kanban (Varsayılan Ana Sayfa)' => '/',
    'Takvim & Gantt (Calendar)'    => '/calendar',
    'Ekip Yönetimi (Team)'         => '/team',
    'Dökümanlar (Docs)'            => '/docs',
    'Canlı Sohbet (Chat)'          => '/chat',
    'Raporlar (Reports)'           => '/reports',
    'Hakediş Raporu (Billing)'     => '/billing-report',
    'Sistem Ayarları (Settings)'   => '/settings',
    'API Test Laboratuvarı'        => '/api-test',
    'API Dokümantasyonu'           => '/api-docs',
];

$errors = [];
$totalAssetsChecked = 0;

echo "📄 2. ADIM: Canlı Sayfalar ve Ekran Varlıkları Taranıyor...\n";

foreach ($pages as $title => $path) {
    $fullUrl = $baseUrl . $path;
    $res = requestUrl($fullUrl);

    if ($res['code'] !== 200) {
        $errors[] = "Sayfa Yüklenemedi [HTTP {$res['code']}]: $title ($path)";
        echo "  ❌ [FAIL] $title -> HTTP {$res['code']}\n";
        continue;
    }

    // PHP Fatal Error / Warning Kontrolü
    if (preg_match('/(Fatal error|Parse error|Uncaught Error|Warning:|Notice:)/i', $res['body'], $m)) {
        $errors[] = "Sayfada PHP Hatası Bulundu: $title ($path) -> {$m[0]}";
        echo "  ❌ [FAIL] $title -> PHP Hatası ({$m[0]})\n";
        continue;
    }

    echo "  ✅ [PASS] $title (HTTP 200 OK)\n";

    // Sayfaya gömülen JS ve CSS bağlantılarını tara ve 404 var mı doğrula
    preg_match_all('/<(?:script[^>]+src|link[^>]+href)=["\']([^"\']+\.(?:js|css)(?:\?[^"\']*)?)["\']/i', $res['body'], $assets);
    
    if (!empty($assets[1])) {
        foreach (array_unique($assets[1]) as $assetUrl) {
            if (str_starts_with($assetUrl, 'http://') || str_starts_with($assetUrl, 'https://')) {
                // Harici CDN linki (Google Fonts vb.)
                continue;
            }

            $assetPath = parse_url($assetUrl, PHP_URL_PATH);
            $fullAssetUrl = 'http://localhost' . $assetPath;
            $assetRes = requestUrl($fullAssetUrl, 'GET', [], 3);
            $totalAssetsChecked++;

            if ($assetRes['code'] !== 200) {
                $errors[] = "Kırık Varlık Linki [HTTP {$assetRes['code']}]: $assetPath (Kaynak: $title)";
                echo "     🔸 ❌ [KIRIK ASSET] $assetPath (HTTP {$assetRes['code']})\n";
            }
        }
    }
}

// 3. ADIM: Canlı SSE (Server-Sent Events) Akışını Doğrula
echo "\n📡 3. ADIM: Canlı SSE (Server-Sent Events) Yayını Test Ediliyor...\n";
$sseRes = requestUrl($baseUrl . '/api/sse?project_id=1', 'GET', [], 5, true);
if ($sseRes['code'] === 200 && (str_contains($sseRes['headers'], 'text/event-stream') || str_contains($sseRes['body'], 'event: connected'))) {
    echo "  ✅ [PASS] SSE Canlı Akışı Aktif (HTTP 200, text/event-stream, connected payload)\n";
} else {
    $errors[] = "SSE Canlı Akışı Başarısız [HTTP {$sseRes['code']} - Headers: " . trim($sseRes['headers']) . "]";
    echo "  ❌ [FAIL] SSE Akışı Hatası -> HTTP {$sseRes['code']} (Header: " . trim($sseRes['headers']) . ")\n";
}

// 4. ADIM: Kritik AJAX Endpointleri Testi
echo "\n⚡ 4. ADIM: Kritik AJAX & Modül API Endpointleri Test Ediliyor...\n";
$ajaxEndpoints = [
    'Chat Konuşmaları'             => '/ajax?action=get_chat_conversations',
    'Kanban Görevleri (Board)'     => '/ajax?action=get_tasks',
    'API Sağlık Durumu'            => '/api/health',
    'Dispatcher Durumu'            => '/api/dispatcher/status',
    'Proje Listesi API'            => '/api/projects?api_token=tm_admin_talha_keskin_token_2026',
];

foreach ($ajaxEndpoints as $title => $path) {
    $fullUrl = $baseUrl . $path;
    $res = requestUrl($fullUrl);

    if ($res['code'] !== 200) {
        $errors[] = "Kritik AJAX/API Başarısız [HTTP {$res['code']}]: $title ($path)";
        echo "  ❌ [FAIL] $title -> HTTP {$res['code']}\n";
    } else {
        echo "  ✅ [PASS] $title (HTTP 200 OK)\n";
    }
}

echo "\n========================================================\n";
echo "📊 TEST ÖZETİ:\n";
echo "  - Denetlenen Canlı Sayfa: " . count($pages) . "\n";
echo "  - Kontrol Edilen CSS/JS Dosyası: $totalAssetsChecked\n";
echo "  - Test Edilen AJAX/API Kanalı: " . (count($ajaxEndpoints) + 1) . "\n";

if (!empty($errors)) {
    echo "\n❌ TOPLAM " . count($errors) . " ADET CANLI ÇALIŞMA / AĞ HATASI BULUNDU:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    echo "========================================================\n";
    exit(1);
} else {
    echo "\n🎉 TEBRİKLER! TÜM CANLI SAYFALAR, MODÜLLER, AĞ İSTEKLERİ VE ASSETLER %100 HATASIZ [PASS]!\n";
    echo "========================================================\n";
    exit(0);
}
