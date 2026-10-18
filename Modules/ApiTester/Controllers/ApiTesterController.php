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
        $dynCfg = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();
        $currentUser = $_SESSION['user'] ?? null;
        $userToken = (!empty($currentUser->api_token)) ? $currentUser->api_token : ($dynCfg['api_token'] ?: 'tm_live_demo_token');
        $baseUrl = $dynCfg['base_url'];

        $model = new \Modules\ApiTester\Models\ApiTesterModel();
        $sampleProject = $model->getFirstActiveProjectCode();
        $sampleTask = $model->getLatestTaskCode();

        $this->render(__DIR__ . '/../Views/api_test.php', [
            'baseUrl'       => $baseUrl,
            'userToken'     => $userToken,
            'sampleProject' => $sampleProject,
            'sampleTask'    => $sampleTask,
            'currentUser'   => $currentUser
        ]);
    }
}
