<?php
// TaskManager Server-Sent Events (SSE) Realtime Event Streamer
ignore_user_abort(true);
set_time_limit(35);

require_once __DIR__ . '/../Core/System.php';
require_once __DIR__ . '/../Model/DataLayer.php';

// Oturumu hemen kapat (Concurrent HTTP istekleri bloke olmasın)
if (session_status() === PHP_SESSION_ACTIVE) {
    $currentUser = $_SESSION['user'] ?? null;
    session_write_close();
} else {
    $currentUser = null;
}

// Apache ve PHP sıkıştırma/tamponlarını tamamen devre dışı bırak
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// PHP output buffer temizle (System.php'deki ob_start dahil)
while (ob_get_level() > 0) {
    @ob_end_clean();
}
ob_implicit_flush(true);

// mod_fcgid ve Apache proxy tamponunu hemen boşaltmak için boşluk paddingi
echo ":" . str_repeat(" ", 2048) . "\n\n";
@ob_flush();
flush();

global $db;
if (!$db || empty($db->connect)) {
    $db = new Database();
}

$projectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) && (int)$_GET['project_id'] > 0 
    ? (int)$_GET['project_id'] 
    : null;

$lastEventId = 0;
if (isset($_SERVER['HTTP_LAST_EVENT_ID']) && is_numeric($_SERVER['HTTP_LAST_EVENT_ID'])) {
    $lastEventId = (int)$_SERVER['HTTP_LAST_EVENT_ID'];
} elseif (isset($_GET['last_event_id']) && is_numeric($_GET['last_event_id'])) {
    $lastEventId = (int)$_GET['last_event_id'];
}

// İlk bağlantıda son ID'yi tespit et ve connected eventi yolla
if ($lastEventId <= 0) {
    $maxId = (int)$db->single_query("SELECT MAX(id) FROM live_events");
    $lastEventId = max(0, $maxId);
    
    echo "event: connected\n";
    echo "data: " . json_encode([
        'connected' => true,
        'last_event_id' => $lastEventId,
        'project_id' => $projectId,
        'user_id' => $currentUser->id ?? null,
        'server_time' => date('H:i:s')
    ], JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush();
    flush();
}

$startTime = time();
$heartbeatTime = time();

// 25 saniyelik yayın döngüsü (Apache/PHP zaman aşımına uğramadan tarayıcı otomatik yeniden bağlanır)
while (time() - $startTime < 25) {
    if (connection_aborted()) {
        break;
    }

    $params = [$lastEventId];
    $sql = "SELECT id, project_id, task_id, user_id, event_type, payload, created_at FROM live_events WHERE id > ? ";
    if ($projectId) {
        $sql .= "AND (project_id = ? OR project_id IS NULL) ";
        $params[] = $projectId;
    }
    $sql .= "ORDER BY id ASC LIMIT 25";

    $events = $db->multi_record_query($sql, $params) ?: [];

    if (!empty($events)) {
        foreach ($events as $ev) {
            $lastEventId = (int)$ev->id;
            echo "id: {$ev->id}\n";
            echo "event: {$ev->event_type}\n";
            echo "data: {$ev->payload}\n\n";
        }
        @ob_flush();
        flush();
        $heartbeatTime = time();
    }

    // 10 saniyede bir bağlantıyı diri tutmak için ping
    if (time() - $heartbeatTime >= 10) {
        echo ": heartbeat " . time() . "\n\n";
        @ob_flush();
        flush();
        $heartbeatTime = time();
    }

    usleep(600000); // 600ms bekleme
}
