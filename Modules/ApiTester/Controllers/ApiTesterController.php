<?php
namespace Modules\ApiTester\Controllers;

use Core\BaseController;

class ApiTesterController extends BaseController
{
    /**
     * API Test Konsolu sayfasını hazırlar
     */
    public function index(): void
    {
        $dynCfg = \Helper::getDynamicApiConfig();
        $currentUser = $_SESSION['user'] ?? null;
        $userToken = (!empty($currentUser->api_token)) ? $currentUser->api_token : ($dynCfg['api_token'] ?: 'tm_live_demo_token');
        $baseUrl = $dynCfg['base_url'];

        // Örnek proje ve görevleri otomatik çek
        $sampleProject = '01';
        $sampleTask = 'TSK-101';
        try {
            global $db;
            if ($db && !empty($db->connect)) {
                $pRow = $db->row_query("SELECT project_code FROM projects WHERE status = 'active' ORDER BY id ASC LIMIT 1");
                if ($pRow && !empty($pRow->project_code)) $sampleProject = $pRow->project_code;

                $tRow = $db->row_query("SELECT task_code FROM tasks ORDER BY id DESC LIMIT 1");
                if ($tRow && !empty($tRow->task_code)) $sampleTask = $tRow->task_code;
            }
        } catch (\Throwable $e) {}

        $this->render(__DIR__ . '/../Views/api_test_view.php', [
            'baseUrl'       => $baseUrl,
            'userToken'     => $userToken,
            'sampleProject' => $sampleProject,
            'sampleTask'    => $sampleTask,
            'currentUser'   => $currentUser
        ]);
    }
}
