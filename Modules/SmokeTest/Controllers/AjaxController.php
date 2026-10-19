<?php
namespace Modules\SmokeTest\Controllers;

use Core\BaseController;
use Modules\SmokeTest\Services\SmokeTestService;

class AjaxController extends BaseController
{
    /**
     * AJAX üzerinden otonom smoke test çalıştırır
     * POST/GET ajax?action=run_smoke_test
     */
    public function runSmokeTest(): void
    {
        $lang = $_GET['lang'] ?? ($_SESSION['user']->language ?? 'tr');
        
        // CLI echo çıktılarını yakala, JSON yanıtının bozulmasını önle
        ob_start();
        $service = new SmokeTestService($lang);
        $report = $service->run();
        $cliOutput = ob_get_clean();

        // Daha önce oluşmuş olabilecek header/output'u temizle
        if (ob_get_length()) {
            ob_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => ($report['failed'] === 0),
            'data'    => $report,
            'message' => $report['failed'] === 0 ? __('SmokeTest::all_passed') : __('SmokeTest::some_failed'),
            'cli_log' => $cliOutput
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
