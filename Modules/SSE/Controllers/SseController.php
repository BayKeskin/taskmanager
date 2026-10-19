<?php
namespace Modules\SSE\Controllers;

use Core\BaseController;
use Modules\SSE\Models\SseEventModel;

/**
 * SseController - Server-Sent Events (SSE) Gerçek Zamanlı Akış Yöneticisi
 */
class SseController extends BaseController
{
    private SseEventModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new SseEventModel();
    }

    /**
     * Canlı SSE olay akışını başlatır
     */
    public function stream(): void
    {
        @ignore_user_abort(true);
        @set_time_limit(35);

        // Oturumu hemen kapat (Concurrent HTTP istekleri bloke olmasın)
        if (session_status() === PHP_SESSION_ACTIVE) {
            $currentUser = $_SESSION['user'] ?? null;
            session_write_close();
        } else {
            $currentUser = null;
        }

        // Apache ve PHP sıkıştırma tamponlarını kapat
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

        // mod_fcgid ve Apache proxy tamponunu hemen boşaltmak için padding
        echo ":" . str_repeat(" ", 2048) . "\n\n";
        @ob_flush();
        flush();

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
            $maxId = $this->model->getMaxEventId();
            $lastEventId = max(0, $maxId);
            
            echo "event: connected\n";
            echo "data: " . json_encode([
                'connected'     => true,
                'last_event_id' => $lastEventId,
                'project_id'    => $projectId,
                'user_id'       => $currentUser->id ?? null,
                'server_time'   => date('H:i:s')
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
        }

        $startTime = time();

        // 25 saniyelik yayın döngüsü
        while (time() - $startTime < 25) {
            if (connection_aborted()) {
                break;
            }

            $events = $this->model->getEventsAfter($lastEventId, $projectId, 25);

            if (!empty($events)) {
                foreach ($events as $ev) {
                    $item = (object)$ev;
                    $lastEventId = (int)$item->id;
                    echo "id: {$item->id}\n";
                    echo "event: {$item->event_type}\n";
                    echo "data: {$item->payload}\n\n";
                }
                @ob_flush();
                flush();
            }

            // 1 saniye bekle
            sleep(1);
        }

        exit;
    }
}
