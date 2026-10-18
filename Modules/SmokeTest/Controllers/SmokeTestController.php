<?php
namespace Modules\SmokeTest\Controllers;

use Core\BaseController;
use Modules\SmokeTest\Services\SmokeTestService;

class SmokeTestController extends BaseController
{
    public function runDiagnostics(): void
    {
        $this->requireRole('admin');
        $service = new SmokeTestService();
        $report = $service->run();
        $this->json($report, $report['failed'] === 0 ? 200 : 500);
    }
}
