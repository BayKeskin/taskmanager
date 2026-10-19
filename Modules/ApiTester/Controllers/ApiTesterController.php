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
        $endpointsCatalog = $model->getEndpointsCatalog($sampleProject, $sampleTask);

        \Core\AssetManager::localizeScript('apitester-live-js', 'APITESTER_LIVE_DATA', [
            'catalog'       => $endpointsCatalog,
            'baseUrl'       => $baseUrl,
            'activeToken'   => $userToken,
            'sampleProject' => $sampleProject,
            'sampleTask'    => $sampleTask,
            'i18n'          => [
                'json_syntax_error'  => __('ApiTester::empty_response_title'),
                'sending_request'    => __('ApiTester::btn_send'),
                'network_error'      => __('ApiTester::response_not_sent'),
                'network_error_desc' => __('ApiTester::empty_response_desc'),
                'btn_send'           => __('ApiTester::btn_send'),
                'batch_ready_count'  => __('ApiTester::batch_ready'),
                'batch_scanning'     => __('ApiTester::btn_start_scan'),
                'batch_testing'      => __('ApiTester::core_health_scan'),
                'batch_retest'       => __('ApiTester::btn_start_scan'),
                'batch_success_count'=> __('ApiTester::batch_ready'),
                'batch_fail_count'   => __('ApiTester::batch_ready'),
                'invalid_json'       => __('ApiTester::empty_response_title'),
                'token_copied_alert' => __('ApiTester::token_copied'),
                'response_copied'    => __('ApiTester::btn_copy_response'),
                'command_copied'     => __('ApiTester::btn_copy'),
                'expected_status'    => __('ApiTester::expected_status_label'),
                'no_params_needed'   => __('ApiTester::no_params_needed')
            ]
        ]);

        $this->render(__DIR__ . '/../Views/api_test.php', [
            'baseUrl'          => $baseUrl,
            'userToken'        => $userToken,
            'sampleProject'    => $sampleProject,
            'sampleTask'       => $sampleTask,
            'currentUser'      => $currentUser,
            'endpointsCatalog' => $endpointsCatalog
        ]);
    }
}
