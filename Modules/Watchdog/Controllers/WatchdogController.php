<?php
namespace Modules\Watchdog\Controllers;

use Core\BaseController;
use Modules\Watchdog\Services\WatchdogService;

class WatchdogController extends BaseController
{
    /**
     * Watchdog sistem sağlığı ve kurtarma raporu
     * GET /api/watchdog/health
     */
    public function health(): void
    {
        $minutes = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 25;
        if ($minutes <= 0) {
            $minutes = 25;
        }

        $report = WatchdogService::getHealthReport($minutes);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
